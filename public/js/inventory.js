const apiUrl = '/api';
let token = localStorage.getItem('inventory_token');

const loginScreen = document.querySelector('#login-screen');
const application = document.querySelector('#application');
const pageMessage = document.querySelector('#page-message');

async function api(path, options = {}) {
    const response = await fetch(`${apiUrl}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
            ...options.headers,
        },
    });

    const result = await response.json();
    if (response.status === 401) {
        showLogin();
        throw new Error('Your session has expired. Please sign in again.');
    }
    if (!response.ok) {
        throw new Error(result.message || 'The request could not be completed.');
    }
    return result.data;
}

document.querySelector('#login-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const error = document.querySelector('#login-error');
    error.textContent = '';

    try {
        const response = await fetch(`${apiUrl}/login`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: document.querySelector('#email').value,
                password: document.querySelector('#password').value,
            }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Login failed.');

        token = result.data.token;
        localStorage.setItem('inventory_token', token);
        showApplication(result.data.user);
    } catch (exception) {
        error.textContent = exception.message;
    }
});

document.querySelector('#logout-button').addEventListener('click', async () => {
    try { await api('/logout', { method: 'POST' }); } catch (exception) { /* Clear local session anyway. */ }
    showLogin();
});

document.querySelectorAll('.nav-button').forEach((button) => {
    button.addEventListener('click', () => openView(button.dataset.view));
});

document.querySelector('#product-search-form').addEventListener('submit', (event) => {
    event.preventDefault();
    loadProducts(document.querySelector('#product-search').value);
});

document.querySelector('#order-status').addEventListener('change', (event) => loadOrders(event.target.value));

async function showApplication(user = null) {
    loginScreen.hidden = true;
    application.hidden = false;

    try {
        const currentUser = user || await api('/profile');
        document.querySelector('#user-name').textContent = currentUser.name;
        document.querySelector('#user-role').textContent = currentUser.role;
        openView('dashboard');
    } catch (exception) {
        showMessage(exception.message);
    }
}

function showLogin() {
    token = null;
    localStorage.removeItem('inventory_token');
    application.hidden = true;
    loginScreen.hidden = false;
}

async function openView(name) {
    document.querySelectorAll('.view').forEach((view) => { view.hidden = true; });
    document.querySelectorAll('.nav-button').forEach((button) => button.classList.toggle('active', button.dataset.view === name));
    document.querySelector(`#${name}-view`).hidden = false;
    document.querySelector('#page-title').textContent = name === 'inventory' ? 'Low stock' : capitalize(name);
    document.querySelector('#page-label').textContent = name === 'dashboard' ? 'Overview' : 'Workspace';
    showMessage('');

    const loaders = { dashboard: loadDashboard, products: loadProducts, orders: loadOrders, inventory: loadInventory, reports: loadReports };
    try { await loaders[name](); } catch (exception) { showMessage(exception.message); }
}

async function loadDashboard() {
    const [summary, lowStock] = await Promise.all([api('/dashboard'), api('/reports/low-stock')]);
    renderCards('#dashboard-cards', [
        ['Products', summary.total_products], ['Customers', summary.total_customers],
        ['Orders', summary.total_orders], ['Pending orders', summary.pending_orders],
        ["Today's orders", summary.todays_orders], ['Delivered sales', currency(summary.total_sales)],
        ['Low-stock products', summary.low_stock_product_count],
    ]);
    renderProductTable('#dashboard-low-stock', lowStock);
}

async function loadProducts(search = '') {
    const result = await api(`/products?search=${encodeURIComponent(search)}`);
    renderProductTable('#products-table', result.data);
}

async function loadOrders(status = '') {
    const result = await api(`/orders?status=${encodeURIComponent(status)}`);
    renderTable('#orders-table', ['Order', 'Customer', 'Status', 'Total', 'Created'], result.data.map((order) => [
        order.order_number, order.customer?.name || '—', badge(order.status), currency(order.total), date(order.created_at),
    ]));
}

async function loadInventory() {
    renderProductTable('#inventory-table', await api('/reports/low-stock'));
}

async function loadReports() {
    const [orders, products, monthly] = await Promise.all([
        api('/reports/orders-summary'), api('/reports/best-selling-products'), api('/reports/monthly-sales'),
    ]);
    renderCards('#order-summary', [['All orders', orders.total_orders], ...orders.orders_by_status.map((item) => [item.status, item.total])]);
    renderTable('#best-sellers-table', ['Product', 'SKU', 'Units', 'Sales'], products.map((product) => [
        product.name, product.sku, product.total_quantity, currency(product.total_sales),
    ]));
    renderTable('#monthly-sales-table', ['Month', 'Orders', 'Sales'], monthly.months.map((month) => [
        month.month, month.total_orders, currency(month.total_sales),
    ]));
}

function renderProductTable(selector, products) {
    renderTable(selector, ['Product', 'SKU', 'Category', 'Price', 'Stock', 'Status'], products.map((product) => [
        product.name, product.sku, product.category?.name || '—', currency(product.price), product.stock_quantity, badge(product.status),
    ]));
}

function renderCards(selector, items) {
    const container = document.querySelector(selector);
    container.replaceChildren(...items.map(([label, value]) => {
        const card = document.createElement('article');
        card.className = 'metric-card';
        const title = document.createElement('p');
        title.textContent = label;
        const total = document.createElement('strong');
        total.textContent = value;
        card.append(title, total);
        return card;
    }));
}

function renderTable(selector, headings, rows) {
    const container = document.querySelector(selector);
    if (!rows.length) {
        const empty = document.createElement('p');
        empty.className = 'empty';
        empty.textContent = 'No records found.';
        container.replaceChildren(empty);
        return;
    }

    const table = document.createElement('table');
    const headerRow = document.createElement('tr');
    headings.forEach((heading) => { const cell = document.createElement('th'); cell.textContent = heading; headerRow.append(cell); });
    const head = document.createElement('thead');
    head.append(headerRow);
    const body = document.createElement('tbody');
    rows.forEach((row) => {
        const tableRow = document.createElement('tr');
        row.forEach((value) => {
            const cell = document.createElement('td');
            value instanceof Node ? cell.append(value) : cell.textContent = value;
            tableRow.append(cell);
        });
        body.append(tableRow);
    });
    table.append(head, body);
    container.replaceChildren(table);
}

function badge(text) {
    const element = document.createElement('span');
    element.className = 'status';
    element.textContent = text;
    return element;
}

function currency(value) { return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value)); }
function date(value) { return new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium' }).format(new Date(value)); }
function capitalize(value) { return value.charAt(0).toUpperCase() + value.slice(1); }
function showMessage(message) { pageMessage.textContent = message; }

if (token) showApplication();
