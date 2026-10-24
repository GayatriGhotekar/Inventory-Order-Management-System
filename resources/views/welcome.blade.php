<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory &amp; Order Management</title>
    <link rel="stylesheet" href="/css/inventory.css?v={{ filemtime(public_path('css/inventory.css')) }}">
    <script src="/js/inventory.js?v={{ filemtime(public_path('js/inventory.js')) }}" defer></script>
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
                <button class="nav-button" data-view="categories">Categories</button>
                <button class="nav-button" data-view="suppliers">Suppliers</button>
                <button class="nav-button" data-view="purchases">Purchases</button>
                <button class="nav-button" data-view="customers">Customers</button>
                <button class="nav-button" data-view="warehouses">Warehouses</button>
                <button class="nav-button" data-view="transfers">Transfers</button>
                <button class="nav-button" data-view="orders">Orders</button>
                <button class="nav-button" data-view="inventory">Inventory</button>
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
                        <div class="heading-actions">
                            <form id="product-search-form" class="inline-form">
                                <input id="product-search" type="search" placeholder="Search name or SKU">
                                <button class="secondary-button">Search</button>
                            </form>
                            <button class="primary-button manager-action" data-action="create-product">Add product</button>
                        </div>
                    </div>
                    <div id="products-table" class="table-wrap"></div>
                    <div id="products-pagination" class="pagination"></div>
                </div>
            </section>

            <section id="categories-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Catalog</p><h2>Categories</h2></div>
                        <button class="primary-button manager-action" data-action="create-category">Add category</button>
                    </div>
                    <div id="categories-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="suppliers-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Purchasing</p><h2>Suppliers</h2></div>
                        <button class="primary-button manager-action" data-action="create-supplier">Add supplier</button>
                    </div>
                    <div id="suppliers-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="customers-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Sales</p><h2>Customers</h2></div>
                        <button class="primary-button manager-action" data-action="create-customer">Add customer</button>
                    </div>
                    <div id="customers-table" class="table-wrap"></div>
                </div>
            </section>

            <section id="purchases-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Purchasing</p><h2>Purchase orders</h2></div>
                        <div class="heading-actions">
                            <select id="purchase-status" aria-label="Filter purchase orders by status">
                                <option value="">All statuses</option><option>Draft</option><option>Approved</option>
                                <option>Partially Received</option><option>Received</option><option>Cancelled</option>
                            </select>
                            <button class="primary-button manager-action" data-action="create-purchase">Create purchase order</button>
                        </div>
                    </div>
                    <div id="purchases-table" class="table-wrap"></div>
                    <div id="purchases-pagination" class="pagination"></div>
                </div>
            </section>

            <section id="warehouses-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Locations</p><h2>Warehouses</h2></div>
                        <button class="primary-button manager-action" data-action="create-warehouse">Add warehouse</button>
                    </div>
                    <div id="warehouses-table" class="table-wrap"></div>
                </div>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Warehouse stock</p><h2>Inventory balances</h2></div>
                        <select id="warehouse-inventory-filter" aria-label="Filter inventory by warehouse"></select>
                    </div>
                    <div id="warehouse-inventory-table" class="table-wrap"></div>
                    <div id="warehouse-inventory-pagination" class="pagination"></div>
                </div>
            </section>

            <section id="transfers-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Stock movement</p><h2>Warehouse transfers</h2></div>
                        <div class="heading-actions">
                            <select id="transfer-status" aria-label="Filter transfers by status">
                                <option value="">All statuses</option><option>Draft</option><option>Approved</option>
                                <option>In Transit</option><option>Received</option><option>Cancelled</option>
                            </select>
                            <button class="primary-button manager-action" data-action="create-transfer">Create transfer</button>
                        </div>
                    </div>
                    <div id="transfers-table" class="table-wrap"></div>
                    <div id="transfers-pagination" class="pagination"></div>
                </div>
            </section>

            <section id="orders-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Sales</p><h2>Orders</h2></div>
                        <div class="heading-actions">
                            <select id="order-status" aria-label="Filter orders by status">
                                <option value="">All statuses</option>
                                <option>Pending</option><option>Confirmed</option><option>Processing</option>
                                <option>Shipped</option><option>Delivered</option><option>Cancelled</option>
                            </select>
                            <button class="primary-button" data-action="create-order">Create order</button>
                        </div>
                    </div>
                    <div id="orders-table" class="table-wrap"></div>
                    <div id="orders-pagination" class="pagination"></div>
                </div>
            </section>

            <section id="inventory-view" class="view" hidden>
                <div class="panel">
                    <div class="panel-heading"><div><p class="eyebrow">Inventory</p><h2>Stock overview</h2></div></div>
                    <div id="inventory-table" class="table-wrap"></div>
                    <div id="inventory-pagination" class="pagination"></div>
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

    <dialog id="record-dialog" class="record-dialog">
        <form id="record-form" class="record-form">
            <div class="dialog-heading">
                <div><p class="eyebrow" id="dialog-label">Manage record</p><h2 id="dialog-title">Record</h2></div>
                <button type="button" class="icon-button" data-action="close-dialog" aria-label="Close dialog">×</button>
            </div>
            <div id="dialog-fields" class="form-grid"></div>
            <p id="dialog-error" class="error" role="alert"></p>
            <div class="dialog-actions">
                <button type="button" class="secondary-button" data-action="close-dialog">Cancel</button>
                <button type="submit" class="primary-button" id="dialog-submit">Save</button>
            </div>
        </form>
    </dialog>
</body>
</html>
