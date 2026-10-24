const apiUrl = '/api';
let token = localStorage.getItem('inventory_token');
let currentUser = null;
let dialogSubmitHandler = null;

const loginScreen = document.querySelector('#login-screen');
const application = document.querySelector('#application');
const pageMessage = document.querySelector('#page-message');
const dialog = document.querySelector('#record-dialog');
const dialogForm = document.querySelector('#record-form');
const dialogFields = document.querySelector('#dialog-fields');

async function api(path, options = {}) {
    const response = await fetch(`${apiUrl}${path}`, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, ...options.headers },
    });
    let result;
    try { result = await response.json(); } catch (exception) { result = { message: 'The server returned an invalid response.' }; }
    if (response.status === 401) {
        showLogin();
        throw new Error('Your session has expired. Please sign in again.');
    }
    if (!response.ok) {
        const validation = result.errors ? Object.values(result.errors).flat().join(' ') : '';
        throw new Error(validation || result.message || 'The request could not be completed.');
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
            body: JSON.stringify({ email: document.querySelector('#email').value, password: document.querySelector('#password').value }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Login failed.');
        token = result.data.token;
        localStorage.setItem('inventory_token', token);
        await showApplication(result.data.user);
    } catch (exception) { error.textContent = exception.message; }
});

document.querySelector('#logout-button').addEventListener('click', async () => {
    try { await api('/logout', { method: 'POST' }); } catch (exception) { /* Clear local session anyway. */ }
    showLogin();
});

document.querySelectorAll('.nav-button').forEach((button) => button.addEventListener('click', () => openView(button.dataset.view)));
document.querySelector('#product-search-form').addEventListener('submit', (event) => {
    event.preventDefault();
    loadProducts(document.querySelector('#product-search').value);
});
document.querySelector('#order-status').addEventListener('change', (event) => loadOrders(event.target.value));
document.querySelector('#transfer-status').addEventListener('change', (event) => loadTransfers(event.target.value));
document.querySelector('#purchase-status').addEventListener('change', (event) => loadPurchases(event.target.value));
document.querySelector('#warehouse-inventory-filter').addEventListener('change', (event) => loadWarehouseInventory(event.target.value));
document.querySelector('#return-status').addEventListener('change', (event) => loadReturns(event.target.value));

document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const { action, id } = trigger.dataset;
    const actions = {
        'close-dialog': closeDialog,
        'create-category': () => openCategoryDialog(), 'edit-category': () => editCategory(id),
        'delete-category': () => removeRecord('category', `/categories/${id}`, loadCategories),
        'create-product': () => openProductDialog(), 'edit-product': () => editProduct(id),
        'delete-product': () => removeRecord('product', `/products/${id}`, loadProducts),
        'create-supplier': () => openSupplierDialog(), 'edit-supplier': () => editSupplier(id),
        'delete-supplier': () => removeRecord('supplier', `/suppliers/${id}`, loadSuppliers),
        'create-customer': () => openCustomerDialog(), 'edit-customer': () => editCustomer(id),
        'delete-customer': () => removeRecord('customer', `/customers/${id}`, loadCustomers),
        'create-purchase': openPurchaseDialog, 'view-purchase': () => viewPurchase(id),
        'approve-purchase': () => updatePurchaseStatus(id, 'Approved'),
        'cancel-purchase': () => updatePurchaseStatus(id, 'Cancelled'),
        'receive-purchase': () => openReceiptDialog(id),
        'delete-purchase': () => removeRecord('draft purchase order', `/purchase-orders/${id}`, loadPurchases),
        'create-warehouse': () => openWarehouseDialog(), 'edit-warehouse': () => editWarehouse(id),
        'delete-warehouse': () => removeRecord('warehouse', `/warehouses/${id}`, loadWarehouses),
        'adjust-warehouse-stock': () => openWarehouseStockDialog(trigger.dataset.warehouse, trigger.dataset.product, trigger.dataset.name),
        'create-transfer': openTransferDialog, 'view-transfer': () => viewTransfer(id),
        'advance-transfer': () => updateTransferStatus(id, trigger.dataset.status),
        'cancel-transfer': () => updateTransferStatus(id, 'Cancelled'),
        'delete-transfer': () => removeRecord('draft transfer', `/stock-transfers/${id}`, loadTransfers),
        'adjust-stock': () => openStockDialog(id, trigger.dataset.name, trigger.dataset.mode),
        'view-stock': () => viewStockHistory(id, trigger.dataset.name),
        'create-order': openOrderDialog, 'view-order': () => viewOrder(id),
        'advance-order': () => trigger.dataset.status === 'Shipped' ? openShipmentDialog(id) : updateOrderStatus(id, trigger.dataset.status),
        'cancel-order': () => updateOrderStatus(id, 'Cancelled'),
        'delete-order': () => removeRecord('pending order', `/orders/${id}`, loadOrders),
        'request-return': () => openReturnRequestDialog(id), 'view-return': () => viewReturn(id),
        'approve-return': () => updateReturnStatus(id, 'Approved'),
        'reject-return': () => updateReturnStatus(id, 'Rejected'),
        'receive-return': () => openReturnInspectionDialog(id),
        'refund-return': () => refundReturn(id),
        'page-products': () => loadProducts(document.querySelector('#product-search').value, trigger.dataset.url),
        'page-orders': () => loadOrders(document.querySelector('#order-status').value, trigger.dataset.url),
        'page-inventory': () => loadInventory(trigger.dataset.url),
        'page-warehouse-inventory': () => loadWarehouseInventory(document.querySelector('#warehouse-inventory-filter').value, trigger.dataset.url),
        'page-transfers': () => loadTransfers(document.querySelector('#transfer-status').value, trigger.dataset.url),
        'page-purchases': () => loadPurchases(document.querySelector('#purchase-status').value, trigger.dataset.url),
        'page-returns': () => loadReturns(document.querySelector('#return-status').value, trigger.dataset.url),
        'page-audits': () => loadAudits(trigger.dataset.url),
        'add-order-item': addOrderItemRow,
        'add-purchase-item': addPurchaseItemRow,
        'remove-order-item': () => trigger.closest('.order-item').remove(),
    };
    if (!actions[action]) return;
    try { await actions[action](); } catch (exception) { showMessage(exception.message, 'error'); }
});

dialogForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const error = document.querySelector('#dialog-error');
    const submit = document.querySelector('#dialog-submit');
    error.textContent = '';
    submit.disabled = true;
    submit.textContent = 'Saving…';
    try { await dialogSubmitHandler(new FormData(dialogForm)); closeDialog(); }
    catch (exception) { error.textContent = exception.message; }
    finally { submit.disabled = false; submit.textContent = 'Save'; }
});

async function showApplication(user = null) {
    loginScreen.hidden = true;
    application.hidden = false;
    try {
        currentUser = user || await api('/profile');
        document.querySelector('#user-name').textContent = currentUser.name;
        document.querySelector('#user-role').textContent = currentUser.role;
        document.querySelectorAll('.manager-action').forEach((element) => { element.hidden = !canManage(); });
        await openView('dashboard');
    } catch (exception) { showMessage(exception.message, 'error'); }
}

function showLogin() {
    token = null; currentUser = null;
    localStorage.removeItem('inventory_token');
    application.hidden = true; loginScreen.hidden = false;
}

async function openView(name) {
    document.querySelectorAll('.view').forEach((view) => { view.hidden = true; });
    document.querySelectorAll('.nav-button').forEach((button) => button.classList.toggle('active', button.dataset.view === name));
    document.querySelector(`#${name}-view`).hidden = false;
    document.querySelector('#page-title').textContent = capitalize(name);
    document.querySelector('#page-label').textContent = name === 'dashboard' ? 'Overview' : 'Workspace';
    showMessage('');
    const loaders = { dashboard: loadDashboard, products: loadProducts, categories: loadCategories, suppliers: loadSuppliers, purchases: loadPurchases, customers: loadCustomers, warehouses: loadWarehouses, transfers: loadTransfers, orders: loadOrders, returns: loadReturns, inventory: loadInventory, reports: loadReports, audits: loadAudits };
    try { await loaders[name](); } catch (exception) { showMessage(exception.message, 'error'); }
}

async function loadDashboard() {
    const [summary, lowStock] = await Promise.all([api('/dashboard'), api('/reports/low-stock')]);
    renderCards('#dashboard-cards', [['Products', summary.total_products], ['Customers', summary.total_customers], ['Orders', summary.total_orders], ['Pending orders', summary.pending_orders], ["Today's orders", summary.todays_orders], ['Delivered sales', currency(summary.total_sales)], ['Low-stock products', summary.low_stock_product_count]]);
    renderProductTable('#dashboard-low-stock', lowStock, false);
}

async function loadProducts(search = '', url = null) {
    const result = await api(url ? apiPath(url) : `/products?search=${encodeURIComponent(search)}`);
    renderProductTable('#products-table', result.data, true);
    renderPagination('#products-pagination', result, 'page-products');
}

async function loadCategories() {
    const categories = await api('/categories');
    renderTable('#categories-table', ['Name', 'Description', ...(canManage() ? ['Actions'] : [])], categories.map((category) => {
        const row = [category.name, category.description || '—'];
        if (canManage()) row.push(actionGroup([actionButton('Edit', 'edit-category', category.id), actionButton('Delete', 'delete-category', category.id, true)]));
        return row;
    }));
}

async function loadSuppliers() {
    const suppliers = await api('/suppliers');
    renderTable('#suppliers-table', ['Supplier', 'Email', 'Phone', 'Status', ...(canManage() ? ['Actions'] : [])], suppliers.map((supplier) => {
        const row = [supplier.name, supplier.email, supplier.phone || '—', badge(supplier.status)];
        if (canManage()) row.push(actionGroup([actionButton('Edit', 'edit-supplier', supplier.id), actionButton('Delete', 'delete-supplier', supplier.id, true)]));
        return row;
    }));
}

async function loadPurchases(status = '', url = null) {
    const result = await api(url ? apiPath(url) : `/purchase-orders?status=${encodeURIComponent(status)}`);
    renderTable('#purchases-table', ['Purchase order', 'Supplier', 'Warehouse', 'Status', 'Total', 'Expected', 'Actions'], result.data.map((order) => [
        order.purchase_order_number, order.supplier.name, order.warehouse.name, badge(order.status),
        currency(order.total), order.expected_date ? date(order.expected_date) : '—', purchaseActions(order),
    ]));
    renderPagination('#purchases-pagination', result, 'page-purchases');
}

async function loadCustomers() {
    const customers = await api('/customers');
    renderTable('#customers-table', ['Customer', 'Email', 'Phone', 'Address', ...(canManage() ? ['Actions'] : [])], customers.map((customer) => {
        const row = [customer.name, customer.email, customer.phone || '—', customer.address || '—'];
        if (canManage()) row.push(actionGroup([actionButton('Edit', 'edit-customer', customer.id), actionButton('Delete', 'delete-customer', customer.id, true)]));
        return row;
    }));
}

async function loadWarehouses() {
    const warehouses = await api('/warehouses');
    renderTable('#warehouses-table', ['Warehouse', 'Code', 'Address', 'Default', 'Status', ...(canManage() ? ['Actions'] : [])], warehouses.map((warehouse) => {
        const row = [warehouse.name, warehouse.code, warehouse.address || '—', warehouse.is_default ? 'Yes' : 'No', badge(warehouse.status)];
        if (canManage()) row.push(actionGroup([actionButton('Edit', 'edit-warehouse', warehouse.id), actionButton('Delete', 'delete-warehouse', warehouse.id, true)]));
        return row;
    }));
    const filter = document.querySelector('#warehouse-inventory-filter');
    const selected = filter.value;
    filter.replaceChildren(new Option('All warehouses', ''), ...warehouses.map((warehouse) => new Option(warehouse.name, warehouse.id)));
    filter.value = selected;
    await loadWarehouseInventory(selected);
}

async function loadWarehouseInventory(warehouseId = '', url = null) {
    const result = await api(url ? apiPath(url) : `/warehouse-inventory?warehouse_id=${encodeURIComponent(warehouseId)}`);
    renderTable('#warehouse-inventory-table', ['Warehouse', 'Product', 'On hand', 'Reserved', 'Damaged', 'Available', 'Reorder level', ...(canManage() ? ['Actions'] : [])], result.data.map((balance) => {
        const row = [balance.warehouse.name, balance.product.name, balance.on_hand_quantity, balance.reserved_quantity, balance.damaged_quantity, balance.available_quantity, balance.reorder_level];
        if (canManage()) row.push(actionButton('Adjust', 'adjust-warehouse-stock', null, false, { warehouse: balance.warehouse_id, product: balance.product_id, name: `${balance.product.name} — ${balance.warehouse.name}` }));
        return row;
    }));
    renderPagination('#warehouse-inventory-pagination', result, 'page-warehouse-inventory');
}

async function loadTransfers(status = '', url = null) {
    const result = await api(url ? apiPath(url) : `/stock-transfers?status=${encodeURIComponent(status)}`);
    renderTable('#transfers-table', ['Transfer', 'From', 'To', 'Status', 'Created', 'Actions'], result.data.map((transfer) => [transfer.transfer_number, transfer.source_warehouse.name, transfer.destination_warehouse.name, badge(transfer.status), date(transfer.created_at), transferActions(transfer)]));
    renderPagination('#transfers-pagination', result, 'page-transfers');
}

async function loadOrders(status = '', url = null) {
    const result = await api(url ? apiPath(url) : `/orders?status=${encodeURIComponent(status)}`);
    renderTable('#orders-table', ['Order', 'Customer', 'Warehouse', 'Status', 'Total', 'Created', 'Actions'], result.data.map((order) => [order.order_number, order.customer?.name || '—', order.warehouse?.name || '—', badge(order.status), currency(order.total), date(order.created_at), orderActions(order)]));
    renderPagination('#orders-pagination', result, 'page-orders');
}

async function loadReturns(status = '', url = null) {
    const result = await api(url ? apiPath(url) : `/returns?status=${encodeURIComponent(status)}`);
    renderTable('#returns-table', ['Return', 'Order', 'Customer', 'Reason', 'Status', 'Refund', 'Actions'], result.data.map((item) => [
        item.return_number, item.order.order_number, item.order.customer?.name || '—', item.reason,
        badge(item.status), currency(item.refund_amount), returnActions(item),
    ]));
    renderPagination('#returns-pagination', result, 'page-returns');
}

async function loadInventory(url = null) {
    const result = await api(url ? apiPath(url) : '/inventory');
    renderTable('#inventory-table', ['Product', 'SKU', 'Category', 'Stock', 'Low-stock limit', 'Status', 'Actions'], result.data.map((product) => [product.name, product.sku, product.category?.name || '—', product.stock_quantity, product.low_stock_limit, badge(product.stock_quantity <= product.low_stock_limit ? 'Low stock' : 'Healthy'), inventoryActions(product)]));
    renderPagination('#inventory-pagination', result, 'page-inventory');
}

async function loadReports() {
    const [orders, products, monthly] = await Promise.all([api('/reports/orders-summary'), api('/reports/best-selling-products'), api('/reports/monthly-sales')]);
    renderCards('#order-summary', [['All orders', orders.total_orders], ...orders.orders_by_status.map((item) => [item.status, item.total])]);
    renderTable('#best-sellers-table', ['Product', 'SKU', 'Units', 'Sales'], products.map((product) => [product.name, product.sku, product.total_quantity, currency(product.total_sales)]));
    renderTable('#monthly-sales-table', ['Month', 'Orders', 'Sales'], monthly.months.map((month) => [month.month, month.total_orders, currency(month.total_sales)]));
}

async function loadAudits(url = null) {
    const result = await api(url ? apiPath(url) : '/audit-logs');
    renderTable('#audits-table', ['Time', 'User', 'Action', 'Record', 'Status', 'Reason'], result.data.map((log) => [
        dateTime(log.created_at), log.user?.name || 'Unknown user', log.action,
        log.entity_type ? `${log.entity_type} #${log.entity_id || '—'}` : '—',
        log.response_status, log.reason || '—',
    ]));
    renderPagination('#audits-pagination', result, 'page-audits');
}

async function openCategoryDialog(category = null) {
    openDialog(category ? 'Edit category' : 'Add category', [field('name', 'Name', 'text', category?.name, true), field('description', 'Description', 'textarea', category?.description, false, true)], async (formData) => {
        await api(category ? `/categories/${category.id}` : '/categories', request(category ? 'PUT' : 'POST', formData));
        await loadCategories();
    });
}
async function editCategory(id) { await openCategoryDialog(await api(`/categories/${id}`)); }

async function openProductDialog(product = null) {
    const categories = await api('/categories');
    if (!categories.length) throw new Error('Create a category before adding a product.');
    openDialog(product ? 'Edit product' : 'Add product', [
        field('name', 'Product name', 'text', product?.name, true), field('sku', 'SKU', 'text', product?.sku, true),
        selectField('category_id', 'Category', categories.map((item) => [item.id, item.name]), product?.category_id, true),
        field('price', 'Price', 'number', product?.price ?? 0, true, false, { min: 0, step: '0.01' }),
        field('stock_quantity', 'Stock quantity', 'number', product?.stock_quantity ?? 0, true, false, { min: 0 }),
        field('low_stock_limit', 'Low-stock limit', 'number', product?.low_stock_limit ?? 0, true, false, { min: 0 }),
        selectField('status', 'Status', [['Active', 'Active'], ['Inactive', 'Inactive']], product?.status || 'Active', true),
        field('description', 'Description', 'textarea', product?.description, false, true),
    ], async (formData) => { await api(product ? `/products/${product.id}` : '/products', request(product ? 'PUT' : 'POST', formData)); await loadProducts(); });
}
async function editProduct(id) { await openProductDialog(await api(`/products/${id}`)); }

async function openSupplierDialog(supplier = null) {
    openDialog(supplier ? 'Edit supplier' : 'Add supplier', [field('name', 'Name', 'text', supplier?.name, true), field('email', 'Email', 'email', supplier?.email, true), field('phone', 'Phone', 'text', supplier?.phone), selectField('status', 'Status', [['Active', 'Active'], ['Inactive', 'Inactive']], supplier?.status || 'Active', true), field('address', 'Address', 'textarea', supplier?.address, false, true)], async (formData) => { await api(supplier ? `/suppliers/${supplier.id}` : '/suppliers', request(supplier ? 'PUT' : 'POST', formData)); await loadSuppliers(); });
}
async function editSupplier(id) { await openSupplierDialog(await api(`/suppliers/${id}`)); }

async function openPurchaseDialog() {
    const [suppliers, warehouses, productResult] = await Promise.all([api('/suppliers'), api('/warehouses'), api('/products')]);
    const activeSuppliers = suppliers.filter((supplier) => supplier.status === 'Active');
    const activeWarehouses = warehouses.filter((warehouse) => warehouse.status === 'Active');
    const products = productResult.data.filter((product) => product.status === 'Active');
    if (!activeSuppliers.length) throw new Error('Create an active supplier before creating a purchase order.');
    if (!activeWarehouses.length) throw new Error('Create an active warehouse before creating a purchase order.');
    if (!products.length) throw new Error('Create an active product before creating a purchase order.');

    const items = document.createElement('div'); items.className = 'order-items';
    items.dataset.products = JSON.stringify(products.map(({ id, name, sku }) => ({ id, name, sku })));
    const title = document.createElement('strong'); title.textContent = 'Products to purchase'; items.append(title);
    addPurchaseItemRow(items); items.append(actionButton('Add another product', 'add-purchase-item'));

    openDialog('Create purchase order', [
        selectField('supplier_id', 'Supplier', activeSuppliers.map((supplier) => [supplier.id, supplier.name]), '', true),
        selectField('warehouse_id', 'Receiving warehouse', activeWarehouses.map((warehouse) => [warehouse.id, warehouse.name]), '', true),
        field('expected_date', 'Expected date', 'date'),
        field('notes', 'Notes', 'textarea', '', false, true), items,
    ], async (formData) => {
        const purchaseItems = [...dialogForm.querySelectorAll('.purchase-item')].map((row) => ({
            product_id: Number(row.querySelector('[name="product_id"]').value),
            quantity: Number(row.querySelector('[name="quantity"]').value),
            unit_cost: Number(row.querySelector('[name="unit_cost"]').value),
        }));
        await api('/purchase-orders', { method: 'POST', body: JSON.stringify({
            supplier_id: Number(formData.get('supplier_id')), warehouse_id: Number(formData.get('warehouse_id')),
            expected_date: formData.get('expected_date') || null, notes: formData.get('notes'), items: purchaseItems,
        }) });
        await loadPurchases();
    });
}

function addPurchaseItemRow(container = null) {
    const items = container || dialogFields.querySelector('.order-items');
    const products = JSON.parse(items.dataset.products);
    const row = document.createElement('div'); row.className = 'order-item purchase-item';
    row.append(
        selectField('product_id', 'Product', products.map((product) => [product.id, `${product.name} (${product.sku})`]), '', true),
        field('quantity', 'Quantity', 'number', 1, true, false, { min: 1 }),
        field('unit_cost', 'Unit cost', 'number', 0, true, false, { min: 0, step: '0.01' }),
        actionButton('Remove', 'remove-order-item', null, true),
    );
    const add = items.querySelector('[data-action="add-purchase-item"]'); add ? items.insertBefore(row, add) : items.append(row);
}

async function viewPurchase(id) {
    const order = await api(`/purchase-orders/${id}`);
    const summary = document.createElement('p'); summary.className = 'full';
    summary.textContent = `${order.supplier.name} → ${order.warehouse.name} · ${order.status} · ${currency(order.total)}`;
    const host = document.createElement('div'); host.className = 'table-wrap full';
    openDialog(`Purchase order ${order.purchase_order_number}`, [summary, host], null, true);
    renderTableElement(host, ['Product', 'Ordered', 'Processed', 'Remaining', 'Unit cost', 'Total'], order.items.map((item) => [
        item.product.name, item.quantity, item.received_quantity, item.quantity - item.received_quantity,
        currency(item.unit_cost), currency(item.total_cost),
    ]));
}

async function updatePurchaseStatus(id, status) {
    if (!confirm(`Change this purchase order to ${status}?`)) return;
    await api(`/purchase-orders/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    await loadPurchases(document.querySelector('#purchase-status').value);
}

async function openReceiptDialog(id) {
    const order = await api(`/purchase-orders/${id}`);
    const outstanding = order.items.filter((item) => item.received_quantity < item.quantity);
    if (!outstanding.length) throw new Error('This purchase order has no outstanding items.');

    const receiptItems = document.createElement('div'); receiptItems.className = 'order-items';
    const title = document.createElement('strong'); title.textContent = 'Quantities received'; receiptItems.append(title);
    outstanding.forEach((item) => {
        const row = document.createElement('div'); row.className = 'receipt-item'; row.dataset.itemId = item.id;
        const product = document.createElement('div'); product.className = 'receipt-product';
        product.textContent = `${item.product.name} — ${item.quantity - item.received_quantity} remaining`;
        row.append(product, field('accepted_quantity', 'Accepted', 'number', 0, true, false, { min: 0 }), field('damaged_quantity', 'Damaged', 'number', 0, true, false, { min: 0 }), field('rejected_quantity', 'Rejected', 'number', 0, true, false, { min: 0 }));
        receiptItems.append(row);
    });

    openDialog(`Receive ${order.purchase_order_number}`, [
        field('supplier_invoice_reference', 'Supplier invoice reference', 'text'),
        field('notes', 'Receipt notes', 'textarea', '', false, true), receiptItems,
    ], async (formData) => {
        const items = [...dialogForm.querySelectorAll('.receipt-item')].map((row) => ({
            purchase_order_item_id: Number(row.dataset.itemId),
            accepted_quantity: Number(row.querySelector('[name="accepted_quantity"]').value),
            damaged_quantity: Number(row.querySelector('[name="damaged_quantity"]').value),
            rejected_quantity: Number(row.querySelector('[name="rejected_quantity"]').value),
        })).filter((item) => item.accepted_quantity + item.damaged_quantity + item.rejected_quantity > 0);
        if (!items.length) throw new Error('Enter at least one received quantity.');
        await api(`/purchase-orders/${id}/receive`, { method: 'POST', body: JSON.stringify({
            supplier_invoice_reference: formData.get('supplier_invoice_reference'), notes: formData.get('notes'), items,
        }) });
        await loadPurchases(document.querySelector('#purchase-status').value);
    });
}

async function openCustomerDialog(customer = null) {
    openDialog(customer ? 'Edit customer' : 'Add customer', [field('name', 'Name', 'text', customer?.name, true), field('email', 'Email', 'email', customer?.email, true), field('phone', 'Phone', 'text', customer?.phone), field('address', 'Address', 'textarea', customer?.address, false, true)], async (formData) => { await api(customer ? `/customers/${customer.id}` : '/customers', request(customer ? 'PUT' : 'POST', formData)); await loadCustomers(); });
}
async function editCustomer(id) { await openCustomerDialog(await api(`/customers/${id}`)); }

async function openWarehouseDialog(warehouse = null) {
    openDialog(warehouse ? 'Edit warehouse' : 'Add warehouse', [
        field('name', 'Warehouse name', 'text', warehouse?.name, true),
        field('code', 'Code', 'text', warehouse?.code, true),
        selectField('status', 'Status', [['Active', 'Active'], ['Inactive', 'Inactive']], warehouse?.status || 'Active', true),
        field('address', 'Address', 'textarea', warehouse?.address, false, true),
    ], async (formData) => {
        const payload = Object.fromEntries(formData.entries());
        await api(warehouse ? `/warehouses/${warehouse.id}` : '/warehouses', { method: warehouse ? 'PUT' : 'POST', body: JSON.stringify(payload) });
        await loadWarehouses();
    });
}
async function editWarehouse(id) { await openWarehouseDialog(await api(`/warehouses/${id}`)); }

function openWarehouseStockDialog(warehouseId, productId, name) {
    openDialog(`Adjust stock — ${name}`, [
        selectField('operation', 'Operation', [['add', 'Add stock'], ['remove', 'Remove stock'], ['damage', 'Mark damaged'], ['restore', 'Restore damaged']], 'add', true),
        field('quantity', 'Quantity', 'number', 1, true, false, { min: 1 }),
        field('reference', 'Reason or reference', 'text', '', true, true),
    ], async (formData) => {
        await api(`/warehouse-inventory/${warehouseId}/${productId}/adjust`, request('POST', formData));
        await loadWarehouseInventory(document.querySelector('#warehouse-inventory-filter').value);
    });
}

async function openTransferDialog() {
    const [warehouses, productResult] = await Promise.all([api('/warehouses'), api('/products')]);
    const activeWarehouses = warehouses.filter((warehouse) => warehouse.status === 'Active');
    const products = productResult.data.filter((product) => product.status === 'Active');
    if (activeWarehouses.length < 2) throw new Error('Create at least two active warehouses before making a transfer.');
    const items = document.createElement('div'); items.className = 'order-items';
    items.dataset.products = JSON.stringify(products.map(({ id, name, sku }) => ({ id, name, sku })));
    const title = document.createElement('strong'); title.textContent = 'Transfer items'; items.append(title);
    addOrderItemRow(items); items.append(actionButton('Add another item', 'add-order-item'));
    const options = activeWarehouses.map((warehouse) => [warehouse.id, `${warehouse.name} (${warehouse.code})`]);
    openDialog('Create stock transfer', [
        selectField('source_warehouse_id', 'Source warehouse', options, '', true),
        selectField('destination_warehouse_id', 'Destination warehouse', options, '', true),
        field('notes', 'Notes', 'textarea', '', false, true), items,
    ], async (formData) => {
        const transferItems = [...dialogForm.querySelectorAll('.order-item')].map((row) => ({ product_id: Number(row.querySelector('[name="product_id"]').value), quantity: Number(row.querySelector('[name="quantity"]').value) }));
        await api('/stock-transfers', { method: 'POST', body: JSON.stringify({ source_warehouse_id: Number(formData.get('source_warehouse_id')), destination_warehouse_id: Number(formData.get('destination_warehouse_id')), notes: formData.get('notes'), items: transferItems }) });
        await loadTransfers();
    });
}

async function viewTransfer(id) {
    const transfer = await api(`/stock-transfers/${id}`);
    const summary = document.createElement('p'); summary.className = 'full';
    summary.textContent = `${transfer.source_warehouse.name} → ${transfer.destination_warehouse.name} · ${transfer.status}`;
    const host = document.createElement('div'); host.className = 'table-wrap full';
    openDialog(`Transfer ${transfer.transfer_number}`, [summary, host], null, true);
    renderTableElement(host, ['Product', 'SKU', 'Quantity', 'Received'], transfer.items.map((item) => [item.product.name, item.product.sku, item.quantity, item.received_quantity]));
}

async function updateTransferStatus(id, status) {
    if (!confirm(`Change this transfer to ${status}?`)) return;
    await api(`/stock-transfers/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    await loadTransfers(document.querySelector('#transfer-status').value);
}

function openStockDialog(id, name, mode) {
    openDialog(`${mode === 'add' ? 'Add' : 'Remove'} stock — ${name}`, [field('quantity', 'Quantity', 'number', 1, true, false, { min: 1 }), field('reference', 'Reason or reference', 'text', '', false, true)], async (formData) => { await api(`/inventory/${id}/${mode}`, request('POST', formData)); await loadInventory(); });
}

async function viewStockHistory(id, name) {
    const result = await api(`/inventory/${id}`);
    const host = document.createElement('div'); host.className = 'table-wrap full';
    openDialog(`Stock history — ${name}`, [host], null, true);
    renderTableElement(host, ['Type', 'Quantity', 'Previous', 'New', 'Reference', 'Date'], result.movements.data.map((movement) => [badge(movement.type), movement.quantity, movement.previous_stock, movement.new_stock, movement.reference || '—', dateTime(movement.created_at)]));
}

async function openOrderDialog() {
    const [customers, productResult, warehouses] = await Promise.all([api('/customers'), api('/products'), api('/warehouses')]);
    const products = productResult.data.filter((product) => product.status === 'Active');
    const activeWarehouses = warehouses.filter((warehouse) => warehouse.status === 'Active');
    if (!customers.length) throw new Error('Create a customer before creating an order.');
    if (!products.length) throw new Error('Create an active product before creating an order.');
    if (!activeWarehouses.length) throw new Error('Create an active warehouse before creating an order.');
    const items = document.createElement('div'); items.className = 'order-items';
    items.dataset.products = JSON.stringify(products.map(({ id, name, sku }) => ({ id, name, sku })));
    const title = document.createElement('strong'); title.textContent = 'Order items'; items.append(title);
    addOrderItemRow(items); items.append(actionButton('Add another item', 'add-order-item'));
    openDialog('Create order', [selectField('customer_id', 'Customer', customers.map((item) => [item.id, `${item.name} — ${item.email}`]), '', true), selectField('warehouse_id', 'Fulfilment warehouse', activeWarehouses.map((item) => [item.id, item.name]), '', true), items], async (formData) => {
        const orderItems = [...dialogForm.querySelectorAll('.order-item')].map((row) => ({ product_id: Number(row.querySelector('[name="product_id"]').value), quantity: Number(row.querySelector('[name="quantity"]').value) }));
        await api('/orders', { method: 'POST', body: JSON.stringify({ customer_id: Number(formData.get('customer_id')), warehouse_id: Number(formData.get('warehouse_id')), items: orderItems }) });
        await loadOrders();
    });
}

function addOrderItemRow(container = null) {
    const items = container || dialogFields.querySelector('.order-items');
    const products = JSON.parse(items.dataset.products);
    const row = document.createElement('div'); row.className = 'order-item';
    row.append(selectField('product_id', 'Product', products.map((item) => [item.id, `${item.name} (${item.sku})`]), '', true), field('quantity', 'Quantity', 'number', 1, true, false, { min: 1 }), actionButton('Remove', 'remove-order-item', null, true));
    const add = items.querySelector('[data-action="add-order-item"]'); add ? items.insertBefore(row, add) : items.append(row);
}

async function viewOrder(id) {
    const order = await api(`/orders/${id}`);
    const shipment = order.shipments?.[0];
    const summary = document.createElement('p'); summary.className = 'full'; summary.textContent = `Customer: ${order.customer?.name || '—'} · Warehouse: ${order.warehouse?.name || '—'} · Status: ${order.status} · Total: ${currency(order.total)}${shipment ? ` · ${shipment.carrier || 'Carrier'}: ${shipment.tracking_number || 'No tracking number'}` : ''}`;
    const host = document.createElement('div'); host.className = 'table-wrap full';
    openDialog(`Order ${order.order_number}`, [summary, host], null, true);
    renderTableElement(host, ['Product', 'SKU', 'Quantity', 'Unit price', 'Total'], order.items.map((item) => [item.product?.name || '—', item.product?.sku || '—', item.quantity, currency(item.unit_price), currency(item.total_price)]));
}

async function updateOrderStatus(id, status) {
    if (!confirm(`Change this order to ${status}?`)) return;
    await api(`/orders/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    await loadOrders(document.querySelector('#order-status').value);
}

async function openShipmentDialog(id) {
    const order = await api(`/orders/${id}`);
    openDialog(`Ship ${order.order_number}`, [
        field('carrier', 'Carrier', 'text', '', true),
        field('tracking_number', 'Tracking number', 'text', '', true),
        field('shipping_address', 'Shipping address', 'textarea', order.customer?.address || '', true, true),
        field('notes', 'Shipment notes', 'textarea', '', false, true),
    ], async (formData) => {
        await api(`/orders/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status: 'Shipped', ...Object.fromEntries(formData.entries()) }) });
        await loadOrders(document.querySelector('#order-status').value);
    });
}

async function openReturnRequestDialog(orderId) {
    const order = await api(`/orders/${orderId}`);
    const returnItems = document.createElement('div'); returnItems.className = 'order-items';
    const title = document.createElement('strong'); title.textContent = 'Items to return'; returnItems.append(title);
    order.items.forEach((item) => {
        const row = document.createElement('div'); row.className = 'return-request-item'; row.dataset.itemId = item.id;
        const label = document.createElement('div'); label.className = 'receipt-product';
        label.textContent = `${item.product.name} — ${item.shipped_quantity} delivered`;
        row.append(label, field('quantity', 'Return quantity', 'number', 0, true, false, { min: 0, max: item.shipped_quantity }));
        returnItems.append(row);
    });

    openDialog(`Request return — ${order.order_number}`, [
        field('reason', 'Reason', 'text', '', true),
        field('notes', 'Notes', 'textarea', '', false, true), returnItems,
    ], async (formData) => {
        const items = [...dialogForm.querySelectorAll('.return-request-item')].map((row) => ({
            order_item_id: Number(row.dataset.itemId), quantity: Number(row.querySelector('[name="quantity"]').value),
        })).filter((item) => item.quantity > 0);
        if (!items.length) throw new Error('Enter a return quantity for at least one item.');
        await api('/returns', { method: 'POST', body: JSON.stringify({
            order_id: order.id, reason: formData.get('reason'), notes: formData.get('notes'), items,
        }) });
        await openView('returns');
    });
}

async function viewReturn(id) {
    const item = await api(`/returns/${id}`);
    const summary = document.createElement('p'); summary.className = 'full';
    summary.textContent = `${item.order.order_number} · ${item.reason} · ${item.status} · Refund ${currency(item.refund_amount)}`;
    const host = document.createElement('div'); host.className = 'table-wrap full';
    openDialog(`Return ${item.return_number}`, [summary, host], null, true);
    renderTableElement(host, ['Product', 'Quantity', 'Condition', 'Unit refund', 'Refund'], item.items.map((returnItem) => [
        returnItem.order_item.product.name, returnItem.quantity, returnItem.condition,
        currency(returnItem.unit_refund), currency(returnItem.refund_amount),
    ]));
}

async function updateReturnStatus(id, status) {
    if (!confirm(`${status} this return request?`)) return;
    await api(`/returns/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    await loadReturns(document.querySelector('#return-status').value);
}

async function openReturnInspectionDialog(id) {
    const item = await api(`/returns/${id}`);
    const inspectedItems = document.createElement('div'); inspectedItems.className = 'order-items';
    const title = document.createElement('strong'); title.textContent = 'Inspect every returned item'; inspectedItems.append(title);
    item.items.forEach((returnItem) => {
        const row = document.createElement('div'); row.className = 'return-inspection-item'; row.dataset.itemId = returnItem.id;
        const label = document.createElement('div'); label.className = 'receipt-product';
        label.textContent = `${returnItem.order_item.product.name} — quantity ${returnItem.quantity}`;
        row.append(label, selectField('condition', 'Condition', [['Restock', 'Restock'], ['Damaged', 'Damaged']], 'Restock', true));
        inspectedItems.append(row);
    });
    openDialog(`Receive ${item.return_number}`, [inspectedItems], async () => {
        const items = [...dialogForm.querySelectorAll('.return-inspection-item')].map((row) => ({
            order_return_item_id: Number(row.dataset.itemId), condition: row.querySelector('[name="condition"]').value,
        }));
        await api(`/returns/${id}/receive`, { method: 'POST', body: JSON.stringify({ items }) });
        await loadReturns(document.querySelector('#return-status').value);
    });
}

async function refundReturn(id) {
    if (!confirm('Record this return as refunded? This does not contact a payment gateway.')) return;
    await api(`/returns/${id}/refund`, { method: 'POST' });
    await loadReturns(document.querySelector('#return-status').value);
}

async function removeRecord(label, path, reload) {
    if (!confirm(`Delete this ${label}? This action cannot be undone.`)) return;
    await api(path, { method: 'DELETE' }); await reload();
}

function renderProductTable(selector, products, includeActions) {
    const headings = ['Product', 'SKU', 'Category', 'Price', 'Stock', 'Status']; if (includeActions && canManage()) headings.push('Actions');
    renderTable(selector, headings, products.map((product) => {
        const row = [product.name, product.sku, product.category?.name || '—', currency(product.price), product.stock_quantity, badge(product.status)];
        if (includeActions && canManage()) row.push(actionGroup([actionButton('Edit', 'edit-product', product.id), actionButton('Delete', 'delete-product', product.id, true)])); return row;
    }));
}

function inventoryActions(product) {
    const buttons = [actionButton('History', 'view-stock', product.id, false, { name: product.name })];
    if (canManage()) buttons.unshift(actionButton('+ Add', 'adjust-stock', product.id, false, { name: product.name, mode: 'add' }), actionButton('− Remove', 'adjust-stock', product.id, true, { name: product.name, mode: 'remove' }));
    return actionGroup(buttons);
}

function orderActions(order) {
    const buttons = [actionButton('View', 'view-order', order.id)];
    if (order.status === 'Delivered') buttons.push(actionButton('Request return', 'request-return', order.id));
    if (!canManage()) return actionGroup(buttons);
    const next = { Pending: 'Confirmed', Confirmed: 'Processing', Processing: 'Shipped', Shipped: 'Delivered' }[order.status];
    if (next) buttons.push(actionButton(`Mark ${next}`, 'advance-order', order.id, false, { status: next }));
    if (['Pending', 'Confirmed', 'Processing'].includes(order.status)) buttons.push(actionButton('Cancel', 'cancel-order', order.id, true));
    if (order.status === 'Pending') buttons.push(actionButton('Delete', 'delete-order', order.id, true)); return actionGroup(buttons);
}

function returnActions(item) {
    const buttons = [actionButton('View', 'view-return', item.id)];
    if (!canManage()) return actionGroup(buttons);
    if (item.status === 'Requested') {
        buttons.push(actionButton('Approve', 'approve-return', item.id));
        buttons.push(actionButton('Reject', 'reject-return', item.id, true));
    }
    if (item.status === 'Approved') buttons.push(actionButton('Receive', 'receive-return', item.id));
    if (item.status === 'Received') buttons.push(actionButton('Record refund', 'refund-return', item.id));
    return actionGroup(buttons);
}

function transferActions(transfer) {
    const buttons = [actionButton('View', 'view-transfer', transfer.id)];
    if (!canManage()) return actionGroup(buttons);
    const next = { Draft: 'Approved', Approved: 'In Transit', 'In Transit': 'Received' }[transfer.status];
    if (next) buttons.push(actionButton(`Mark ${next}`, 'advance-transfer', transfer.id, false, { status: next }));
    if (['Draft', 'Approved'].includes(transfer.status)) buttons.push(actionButton('Cancel', 'cancel-transfer', transfer.id, true));
    if (transfer.status === 'Draft') buttons.push(actionButton('Delete', 'delete-transfer', transfer.id, true));
    return actionGroup(buttons);
}

function purchaseActions(order) {
    const buttons = [actionButton('View', 'view-purchase', order.id)];
    if (!canManage()) return actionGroup(buttons);
    if (order.status === 'Draft') {
        buttons.push(actionButton('Approve', 'approve-purchase', order.id));
        buttons.push(actionButton('Delete', 'delete-purchase', order.id, true));
    }
    if (['Approved', 'Partially Received'].includes(order.status)) buttons.push(actionButton('Receive', 'receive-purchase', order.id));
    if (['Draft', 'Approved'].includes(order.status)) buttons.push(actionButton('Cancel', 'cancel-purchase', order.id, true));
    return actionGroup(buttons);
}

function openDialog(title, fields, handler, readOnly = false) {
    document.querySelector('#dialog-title').textContent = title; document.querySelector('#dialog-error').textContent = '';
    document.querySelector('#dialog-submit').hidden = readOnly;
    dialogFields.replaceChildren(...fields); dialogSubmitHandler = handler; dialog.showModal();
}
function closeDialog() { dialog.close(); dialogForm.reset(); dialogFields.replaceChildren(); dialogSubmitHandler = null; document.querySelector('#dialog-submit').hidden = false; }

function field(name, labelText, type = 'text', value = '', required = false, full = false, attributes = {}) {
    const wrapper = document.createElement('label'); wrapper.className = `form-field${full ? ' full' : ''}`;
    const label = document.createElement('span'); label.textContent = labelText;
    const input = document.createElement(type === 'textarea' ? 'textarea' : 'input'); if (type !== 'textarea') input.type = type;
    input.name = name; input.value = value ?? ''; input.required = required; Object.entries(attributes).forEach(([key, val]) => input.setAttribute(key, val));
    wrapper.append(label, input); return wrapper;
}

function selectField(name, labelText, options, selected = '', required = false) {
    const wrapper = document.createElement('label'); wrapper.className = 'form-field'; const label = document.createElement('span'); label.textContent = labelText;
    const select = document.createElement('select'); select.name = name; select.required = required;
    options.forEach(([value, text]) => { const option = document.createElement('option'); option.value = value; option.textContent = text; option.selected = String(value) === String(selected); select.append(option); });
    wrapper.append(label, select); return wrapper;
}

function request(method, formData) { return { method, body: JSON.stringify(Object.fromEntries(formData.entries())) }; }

function renderCards(selector, items) {
    document.querySelector(selector).replaceChildren(...items.map(([label, value]) => { const card = document.createElement('article'); card.className = 'metric-card'; const title = document.createElement('p'); title.textContent = label; const total = document.createElement('strong'); total.textContent = value; card.append(title, total); return card; }));
}
function renderTable(selector, headings, rows) { renderTableElement(document.querySelector(selector), headings, rows); }
function renderTableElement(container, headings, rows) {
    if (!rows.length) { const empty = document.createElement('p'); empty.className = 'empty'; empty.textContent = 'No records found.'; container.replaceChildren(empty); return; }
    const table = document.createElement('table'); const headerRow = document.createElement('tr'); headings.forEach((heading) => { const cell = document.createElement('th'); cell.textContent = heading; headerRow.append(cell); });
    const head = document.createElement('thead'); head.append(headerRow); const body = document.createElement('tbody');
    rows.forEach((row) => { const tableRow = document.createElement('tr'); row.forEach((value) => { const cell = document.createElement('td'); value instanceof Node ? cell.append(value) : cell.textContent = value; tableRow.append(cell); }); body.append(tableRow); });
    table.append(head, body); container.replaceChildren(table);
}

function renderPagination(selector, pagination, action) {
    const container = document.querySelector(selector); if (pagination.last_page <= 1) { container.replaceChildren(); return; }
    const previous = actionButton('Previous', action, null, false, { url: pagination.prev_page_url || '' }); const next = actionButton('Next', action, null, false, { url: pagination.next_page_url || '' }); previous.disabled = !pagination.prev_page_url; next.disabled = !pagination.next_page_url;
    const label = document.createElement('span'); label.textContent = `Page ${pagination.current_page} of ${pagination.last_page}`; container.replaceChildren(previous, label, next);
}

function actionButton(text, action, id = null, danger = false, data = {}) { const button = document.createElement('button'); button.type = 'button'; button.className = `text-button${danger ? ' danger' : ''}`; button.textContent = text; button.dataset.action = action; if (id !== null) button.dataset.id = id; Object.entries(data).forEach(([key, value]) => { button.dataset[key] = value; }); return button; }
function actionGroup(buttons) { const group = document.createElement('div'); group.className = 'button-row'; group.append(...buttons); return group; }
function badge(text) { const element = document.createElement('span'); element.className = 'status'; element.textContent = text; return element; }
function apiPath(url) { const parsed = new URL(url, window.location.origin); return `${parsed.pathname.replace(/^\/api/, '')}${parsed.search}`; }
function canManage() { return ['admin', 'manager'].includes(currentUser?.role); }
function currency(value) { return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value)); }
function date(value) { return new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium' }).format(new Date(value)); }
function dateTime(value) { return new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
function capitalize(value) { return value.charAt(0).toUpperCase() + value.slice(1); }
function showMessage(message, type = '') { pageMessage.textContent = message; pageMessage.className = `page-message${type ? ` ${type}` : ''}`; }

if (token) showApplication();
