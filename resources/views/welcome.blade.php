<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory &amp; Order Management</title>
    <link rel="stylesheet" href="/css/inventory.css">
    <script src="/js/inventory.js" defer></script>
</head>
<body>
    <main id="login-screen" class="login-screen">
        <form id="login-form" class="login-card">
            <p class="eyebrow">Inventory workspace</p>
            <h1>Welcome back</h1>
            <p class="muted">Sign in to view products, orders, inventory, and reports.</p>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="admin@example.com" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Enter your password" required>

            <p id="login-error" class="error" role="alert"></p>
            <button type="submit" class="primary-button">Sign in</button>
            <small>Development account: admin@example.com / password</small>
        </form>
    </main>

    <div id="application" class="application" hidden>
        <aside class="sidebar">
            <div>
                <p class="eyebrow">Operations</p>
                <h2>StockFlow</h2>
            </div>

            <nav aria-label="Main navigation">
                <button class="nav-button active" data-view="dashboard">Dashboard</button>
                <button class="nav-button" data-view="products">Products</button>
                <button class="nav-button" data-view="orders">Orders</button>
                <button class="nav-button" data-view="inventory">Low stock</button>
                <button class="nav-button" data-view="reports">Reports</button>
            </nav>

            <button id="logout-button" class="logout-button">Sign out</button>
        </aside>

        <div class="page">
            <header class="topbar">
                <div>
                    <p class="eyebrow" id="page-label">Overview</p>
                    <h1 id="page-title">Dashboard</h1>
                </div>
                <div class="user-chip">
                    <span id="user-name">User</span>
                    <small id="user-role">staff</small>
                </div>
            </header>

            <p id="page-message" class="page-message" role="status"></p>

            <section id="dashboard-view" class="view">
                <div id="dashboard-cards" class="card-grid"></div>
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Attention needed</p>
                            <h2>Low-stock products</h2>
                        </div>
                    </div>
                    <div id="dashboard-low-stock" class="table-wrap"></div>
                </div>
            </section>

            <section id="products-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Catalog</p><h2>Products</h2></div>
                        <form id="product-search-form" class="inline-form">
                            <input id="product-search" type="search" placeholder="Search name or SKU">
                            <button class="secondary-button">Search</button>
                        </form>
                    </div>
                    <div id="products-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="orders-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Sales</p><h2>Orders</h2></div>
                        <select id="order-status" aria-label="Filter orders by status">
                            <option value="">All statuses</option>
                            <option>Pending</option><option>Confirmed</option><option>Processing</option>
                            <option>Shipped</option><option>Delivered</option><option>Cancelled</option>
                        </select>
                    </div>
                    <div id="orders-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="inventory-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading"><div><p class="eyebrow">Inventory</p><h2>Low-stock products</h2></div></div>
                    <div id="inventory-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="reports-view" class="view" hidden>
                <div id="order-summary" class="card-grid compact"></div>
                <div class="report-grid">
                    <div class="panel">
                        <div class="panel-heading"><div><p class="eyebrow">Performance</p><h2>Best sellers</h2></div></div>
                        <div id="best-sellers-table" class="table-wrap"></div>
                    </div>
                    <div class="panel">
                        <div class="panel-heading"><div><p class="eyebrow">This year</p><h2>Monthly sales</h2></div></div>
                        <div id="monthly-sales-table" class="table-wrap"></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
