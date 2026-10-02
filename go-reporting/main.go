package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

type application struct {
	db *sql.DB
}

type productReport struct {
	ID            int64   `json:"id"`
	Name          string  `json:"name"`
	SKU           string  `json:"sku"`
	TotalQuantity int64   `json:"total_quantity"`
	TotalSales    float64 `json:"total_sales"`
}

type monthlyReport struct {
	Month       string  `json:"month"`
	TotalOrders int64   `json:"total_orders"`
	TotalSales  float64 `json:"total_sales"`
}

func main() {
	db, err := openDatabase()
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	app := &application{db: db}
	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", app.health)
	mux.HandleFunc("GET /reports/sales-summary", app.salesSummary)
	mux.HandleFunc("GET /reports/top-products", app.topProducts)
	mux.HandleFunc("GET /reports/monthly-sales", app.monthlySales)

	port := environment("REPORTING_PORT", "8080")
	log.Printf("Go reporting service listening on port %s", port)
	log.Fatal(http.ListenAndServe(":"+port, mux))
}

func openDatabase() (*sql.DB, error) {
	host := environment("DB_HOST", "mysql")
	port := environment("DB_PORT", "3306")
	database := environment("DB_DATABASE", "inventory_management")
	username := environment("DB_USERNAME", "inventory_user")
	password := environment("DB_PASSWORD", "inventory_password")
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true", username, password, host, port, database)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, err
	}

	if err := db.Ping(); err != nil {
		db.Close()
		return nil, fmt.Errorf("connect to MySQL: %w", err)
	}

	return db, nil
}

func (app *application) health(response http.ResponseWriter, request *http.Request) {
	if err := app.db.PingContext(request.Context()); err != nil {
		writeJSON(response, http.StatusServiceUnavailable, map[string]any{
			"success": false,
			"message": "Database is unavailable",
		})
		return
	}

	writeJSON(response, http.StatusOK, map[string]any{
		"success": true,
		"message": "Go reporting service and MySQL are working",
	})
}

func (app *application) salesSummary(response http.ResponseWriter, request *http.Request) {
	query := `
		SELECT
			COUNT(*),
			COALESCE(SUM(total), 0),
			COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0),
			COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total ELSE 0 END), 0)
		FROM orders
		WHERE status = 'Delivered'`

	var totalOrders, todayOrders int64
	var totalSales, todaySales float64
	err := app.db.QueryRowContext(request.Context(), query).Scan(
		&totalOrders, &totalSales, &todayOrders, &todaySales,
	)
	if err != nil {
		serverError(response, err)
		return
	}

	writeJSON(response, http.StatusOK, map[string]any{
		"success": true,
		"data": map[string]any{
			"total_orders": totalOrders,
			"total_sales":  money(totalSales),
			"today_orders": todayOrders,
			"today_sales":  money(todaySales),
		},
	})
}

func (app *application) topProducts(response http.ResponseWriter, request *http.Request) {
	limit, err := numberParameter(request, "limit", 10, 1, 100)
	if err != nil {
		validationError(response, err.Error())
		return
	}

	query := `
		SELECT products.id, products.name, products.sku,
			SUM(order_items.quantity) AS total_quantity,
			SUM(order_items.total_price) AS total_sales
		FROM order_items
		JOIN orders ON orders.id = order_items.order_id
		JOIN products ON products.id = order_items.product_id
		WHERE orders.status = 'Delivered'
		GROUP BY products.id, products.name, products.sku
		ORDER BY total_quantity DESC
		LIMIT ?`

	rows, err := app.db.QueryContext(request.Context(), query, limit)
	if err != nil {
		serverError(response, err)
		return
	}
	defer rows.Close()

	products := make([]productReport, 0)
	for rows.Next() {
		var product productReport
		if err := rows.Scan(&product.ID, &product.Name, &product.SKU, &product.TotalQuantity, &product.TotalSales); err != nil {
			serverError(response, err)
			return
		}
		product.TotalSales = money(product.TotalSales)
		products = append(products, product)
	}
	if err := rows.Err(); err != nil {
		serverError(response, err)
		return
	}

	writeJSON(response, http.StatusOK, map[string]any{"success": true, "data": products})
}

func (app *application) monthlySales(response http.ResponseWriter, request *http.Request) {
	year, err := numberParameter(request, "year", time.Now().Year(), 2000, time.Now().Year())
	if err != nil {
		validationError(response, err.Error())
		return
	}

	query := `
		SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
			COUNT(*) AS total_orders, SUM(total) AS total_sales
		FROM orders
		WHERE status = 'Delivered' AND YEAR(created_at) = ?
		GROUP BY DATE_FORMAT(created_at, '%Y-%m')
		ORDER BY month`

	rows, err := app.db.QueryContext(request.Context(), query, year)
	if err != nil {
		serverError(response, err)
		return
	}
	defer rows.Close()

	months := make([]monthlyReport, 0)
	for rows.Next() {
		var month monthlyReport
		if err := rows.Scan(&month.Month, &month.TotalOrders, &month.TotalSales); err != nil {
			serverError(response, err)
			return
		}
		month.TotalSales = money(month.TotalSales)
		months = append(months, month)
	}
	if err := rows.Err(); err != nil {
		serverError(response, err)
		return
	}

	writeJSON(response, http.StatusOK, map[string]any{
		"success": true,
		"data":    map[string]any{"year": year, "months": months},
	})
}

func numberParameter(request *http.Request, name string, defaultValue, minimum, maximum int) (int, error) {
	value := request.URL.Query().Get(name)
	if value == "" {
		return defaultValue, nil
	}

	number, err := strconv.Atoi(value)
	if err != nil || number < minimum || number > maximum {
		return 0, fmt.Errorf("%s must be between %d and %d", name, minimum, maximum)
	}

	return number, nil
}

func writeJSON(response http.ResponseWriter, status int, data any) {
	response.Header().Set("Content-Type", "application/json")
	response.WriteHeader(status)
	if err := json.NewEncoder(response).Encode(data); err != nil {
		log.Printf("encode response: %v", err)
	}
}

func validationError(response http.ResponseWriter, message string) {
	writeJSON(response, http.StatusUnprocessableEntity, map[string]any{
		"success": false,
		"message": message,
	})
}

func serverError(response http.ResponseWriter, err error) {
	log.Printf("database error: %v", err)
	writeJSON(response, http.StatusInternalServerError, map[string]any{
		"success": false,
		"message": "Unable to generate report",
	})
}

func environment(name, defaultValue string) string {
	value := os.Getenv(name)
	if value == "" {
		return defaultValue
	}
	return value
}

func money(value float64) float64 {
	return float64(int64(value*100+0.5)) / 100
}
