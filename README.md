# Inventory & Order Management System

A backend-focused portfolio project built with Laravel, MySQL, Go, Docker, Nginx, REST APIs, and Vanilla JavaScript.

The application manages products, customers, suppliers, inventory, stock movements, and orders. It includes role-based authentication, transaction-safe stock handling, dashboards, reports, a separate Go reporting service, a small browser interface, automated tests, and Swagger documentation.

## Technology

- PHP 8.4 and Laravel 12
- MySQL 8.4
- Go 1.24
- Laravel Sanctum
- Nginx
- Docker Compose
- Vanilla JavaScript and CSS
- OpenAPI 3 and Swagger UI

## Architecture

```text
Browser / API client
        |
        v
Nginx :8088 ---> Laravel API ---> MySQL
                         ^           ^
                         |           |
                  Sanctum tokens     |
                                     |
API client ---> Go reporting :8090 -+
```

Laravel owns authentication, validation, CRUD operations, inventory changes, and order workflows. The Go service has one responsibility: read reporting data from MySQL and return JSON.

## Main features

- Register, login, logout, and authenticated profile
- Admin, manager, and staff roles
- Category and product CRUD
- Supplier and customer CRUD
- Product search, filtering, and pagination
- Stock additions, removals, and movement history
- Low-stock detection
- Order creation and status filtering
- Transaction-safe order confirmation and cancellation
- Dashboard and sales reports
- Separate Go reporting API
- Responsive Vanilla JavaScript interface
- Swagger/OpenAPI documentation
- Isolated automated test suite

## Requirements

Only Docker and Docker Compose v2 are required. Local PHP, Composer, Go, MySQL, and Nginx installations are not needed.

## Quick start

From the project directory:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

The first two commands are only needed when `.env` does not already exist.

Open:

- Application: <http://localhost:8088>
- Swagger UI: <http://localhost:8088/api/documentation>
- Laravel health: <http://localhost:8088/api/health>
- Go health: <http://localhost:8090/health>

## Seeded development accounts

All seeded accounts use the password `password`.

| Role | Email |
|---|---|
| Admin | `admin@example.com` |
| Manager | `manager@example.com` |
| Staff | `staff@example.com` |

Public registration always creates a staff user. A user cannot register themselves as an administrator or manager.

## Authentication

Login:

```bash
curl -X POST http://localhost:8088/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Copy `data.token` from the response. Send it with protected requests:

```bash
curl http://localhost:8088/api/profile \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Public endpoints:

```text
POST /api/register
POST /api/login
GET  /api/health
```

All other Laravel API endpoints require a Sanctum bearer token.

## Roles and permissions

| Action | Admin | Manager | Staff |
|---|:---:|:---:|:---:|
| View catalog, contacts, inventory, orders, dashboard, and reports | Yes | Yes | Yes |
| Create pending orders | Yes | Yes | Yes |
| Manage categories, products, suppliers, and customers | Yes | Yes | No |
| Add or remove stock | Yes | Yes | No |
| Change order status or delete a pending order | Yes | Yes | No |

Laravel middleware enforces these permissions on the server. Hiding a button in a client is never used as authorization.

## API overview

### Catalog and contacts

```text
GET|POST          /api/categories
GET|PUT|DELETE    /api/categories/{id}

GET|POST          /api/products
GET|PUT|DELETE    /api/products/{id}

GET|POST          /api/suppliers
GET|PUT|DELETE    /api/suppliers/{id}

GET|POST          /api/customers
GET|PUT|DELETE    /api/customers/{id}
```

Product listing accepts `search`, `category_id`, and `page` query parameters.

### Inventory

```text
GET  /api/inventory
GET  /api/inventory/low-stock
GET  /api/inventory/{product_id}
POST /api/inventory/{product_id}/add
POST /api/inventory/{product_id}/remove
```

Example stock addition:

```bash
curl -X POST http://localhost:8088/api/inventory/1/add \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"quantity":10,"reference":"Supplier delivery"}'
```

Stock cannot become negative. Every change creates a movement with its type, quantity, previous stock, new stock, reference, user, and timestamp.

Movement types are `IN`, `OUT`, `ORDER`, `ADJUSTMENT`, and `RETURN`.

### Orders

```text
GET    /api/orders
POST   /api/orders
GET    /api/orders/{id}
PATCH  /api/orders/{id}/status
DELETE /api/orders/{id}
```

Create an order:

```bash
curl -X POST http://localhost:8088/api/orders \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"customer_id":1,"items":[{"product_id":1,"quantity":2}]}'
```

The server reads prices from the database and calculates every item total. Client-provided prices are not trusted.

The normal status sequence is:

```text
Pending -> Confirmed -> Processing -> Shipped -> Delivered
```

Eligible orders may also become `Cancelled`.

Confirming an order locks the order and product rows, checks all available stock, deducts stock, creates `ORDER` movements, and updates the status inside one database transaction. If any product has insufficient stock, every change is rolled back.

Cancelling a confirmed or processing order restores stock and creates `RETURN` movements. Status rules prevent the same order from deducting or restoring stock twice.

### Dashboard and Laravel reports

```text
GET /api/dashboard
GET /api/reports/daily-sales?date=2026-10-01
GET /api/reports/monthly-sales?year=2026
GET /api/reports/orders-summary
GET /api/reports/best-selling-products?limit=10
GET /api/reports/inventory?page=1
GET /api/reports/low-stock
```

Only `Delivered` orders contribute to sales and best-selling-product totals. A product is low on stock when `stock_quantity <= low_stock_limit`.

## Go reporting service

The read-only Go service is exposed on port `8090`:

```text
GET /health
GET /reports/sales-summary
GET /reports/top-products?limit=10
GET /reports/monthly-sales?year=2026
```

Examples:

```bash
curl http://localhost:8090/reports/sales-summary
curl "http://localhost:8090/reports/top-products?limit=5"
curl "http://localhost:8090/reports/monthly-sales?year=2026"
```

The service uses `net/http`, `database/sql`, the MySQL driver, JSON, and environment variables. It does not duplicate Laravel's order or inventory logic.

## Swagger documentation

Open <http://localhost:8088/api/documentation>. The raw OpenAPI document is available at <http://localhost:8088/docs/openapi.json>.

To call protected endpoints in Swagger:

1. Run `POST /api/login`.
2. Copy `data.token` from the response.
3. Select **Authorize**.
4. Enter only the token. Swagger adds `Bearer` automatically.

Swagger UI assets are loaded from a CDN, so the documentation page needs internet access. The raw OpenAPI JSON remains available without it.

## Automated tests

Run the Laravel suite:

```bash
docker compose exec app php artisan test
```

Tests use an in-memory SQLite database configured in `tests/TestCase.php`. They do not modify development data stored in MySQL.

Coverage includes:

- Authentication and role authorization
- Validation and unique fields
- CRUD behavior and filtering
- Stock movements and negative-stock prevention
- Order totals and database prices
- Confirmation, rollback, cancellation, and status rules
- Dashboard and report calculations
- Complete creation-to-delivery workflow
- Swagger page and OpenAPI JSON integrity

The Go unit tests run automatically while its Docker image is built. They can also be run locally when Go is installed:

```bash
cd go-reporting
go test ./...
```

## Useful commands

```bash
# Service status
docker compose ps

# Laravel logs
docker compose logs app nginx

# Go service logs
docker compose logs reporting

# Migration status
docker compose exec app php artisan migrate:status

# Rebuild the Go service
docker compose up -d --build reporting

# Stop containers and keep database data
docker compose down

# Stop containers and deliberately delete volumes/data
docker compose down -v
```

MySQL is intentionally not published to the host. For direct development access:

```bash
docker compose exec mysql mysql \
  -uinventory_user -pinventory_password inventory_management
```

## Project structure

```text
app/Http/Controllers/       Laravel API controllers
app/Http/Middleware/        Role authorization middleware
app/Models/                 Eloquent models and relationships
database/migrations/        Database schema
database/seeders/           Development accounts and sample data
go-reporting/               Standalone Go reporting service
public/docs/openapi.json    OpenAPI specification
public/js/                  Vanilla JavaScript interface
public/css/                 Interface styles
resources/views/            Application and Swagger pages
routes/                     Web and API routes
tests/Feature/              Laravel feature and integration tests
docker/                     Nginx and PHP container configuration
compose.yaml                Docker services and private network
```

## Important implementation choices

- Controllers and Eloquent models are used directly to keep the project understandable.
- Database transactions protect multi-table stock and order changes.
- `lockForUpdate()` prevents concurrent confirmation or cancellation from changing the same rows twice.
- Sanctum tokens protect the API, while role middleware handles authorization.
- Reports use direct Eloquent or SQL aggregation queries.
- The Go service remains small and read-only.
- API data is rendered with DOM methods instead of inserting untrusted HTML.
- Docker Compose keeps service setup reproducible.

## Final verification

```bash
docker compose config --quiet
docker compose exec app php artisan test
docker compose build reporting
curl http://localhost:8088/api/health
curl http://localhost:8090/health
```

Final Verification:
PHP formatting: 67 files passed
Laravel tests: 49 passed, 218 assertions
Go tests: passed during Docker build
Docker Compose configuration: valid
Laravel health: working
Go health: working
Swagger: HTTP 200
Application: HTTP 200
MySQL: healthy



Project URLs:
Application:  http://localhost:8088
Swagger:      http://localhost:8088/api/documentation
Laravel API:  http://localhost:8088/api/health
Go service:   http://localhost:8090/health



# View service status
docker compose ps

# Follow logs
docker compose logs -f

# Run tests
docker compose exec app php artisan test

# Stop the project
docker compose down

# Restart it later
docker compose up -d