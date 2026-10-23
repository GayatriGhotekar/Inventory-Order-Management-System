# Advanced Inventory and Order Management System Plan

## 1. Project Goal

Transform the existing application into a production-style, multi-warehouse inventory, purchasing, and order fulfilment platform suitable for a strong placement portfolio.

The final business workflow will be:

```text
Supplier
   ↓
Purchase Order
   ↓
Goods Receipt
   ↓
Warehouse Inventory
   ↓
Customer Order
   ↓
Stock Reservation
   ↓
Packing → Shipping → Delivery
   ↓
Return → Refund
```

The application will retain its existing Laravel, MySQL, Go, Docker, Nginx, REST API, Swagger, and Vanilla JavaScript technology stack unless a change provides a clear technical benefit.

---

## 2. Current Foundation

The existing project already provides:

- Laravel REST APIs
- Laravel Sanctum authentication
- Admin, manager, and staff roles
- Category, product, supplier, and customer management
- Inventory additions, removals, and movement history
- Low-stock detection
- Transaction-safe order confirmation and cancellation
- Order lifecycle management
- Dashboard and sales reports
- A separate read-only Go reporting service
- Docker and Nginx configuration
- Swagger/OpenAPI documentation
- Automated Laravel and Go tests

The advanced implementation must preserve these working capabilities while extending them incrementally.

---

## Phase 0: Safety and Project Baseline

### Objective

Establish a stable baseline before changing the database or business logic.

### Tasks

- Inspect Git status and preserve all existing work.
- Create a dedicated upgrade branch.
- Run the complete Laravel test suite.
- Run the Go test suite.
- Verify that all Docker services start successfully.
- Test the existing browser interface and important API endpoints.
- Record current API behavior and test results.
- Back up the current development database.
- Verify that `.env`, credentials, cache files, and generated files are not committed.
- Fix any existing failures before adding new functionality.

### Completion Criteria

- The original application starts successfully.
- All existing Laravel and Go tests pass.
- The current behavior is documented.
- A safe database backup exists.

---

## Phase 1: Complete Browser Interface

### Objective

Allow recruiters and business users to perform the primary workflows through the browser without depending on Swagger.

### Pages

- Login and user profile
- Dashboard
- Categories
- Products
- Suppliers
- Customers
- Warehouses
- Inventory
- Orders
- Purchase orders
- Shipments
- Returns
- Reports
- Audit logs
- User and role management

### User Interface Features

- Create, view, edit, and delete forms
- Search, filtering, sorting, and pagination
- Client-side and server-side validation messages
- Loading, empty, error, and success states
- Confirmation dialogs for sensitive operations
- Toast notifications
- Role-based navigation and action buttons
- Responsive desktop and mobile layout
- Accessible labels, keyboard navigation, and color contrast

### Technical Approach

- Retain Laravel Blade and Vanilla JavaScript initially.
- Create reusable modal, form, table, pagination, and notification components.
- Continue enforcing authorization on the server; hidden buttons will not be treated as security.

### Completion Criteria

- Every existing core API operation is available through the browser.
- Users see only the actions allowed for their role.
- The primary workflows work on desktop and mobile screens.

---

## Phase 2: Multi-Warehouse Inventory

### Objective

Replace the single product-level stock quantity with inventory maintained separately for each warehouse.

### New Modules

- Warehouses
- Warehouse storage locations
- Warehouse inventory balances
- Stock adjustments
- Warehouse-to-warehouse transfers
- Warehouse-specific reorder levels

### Inventory Quantities

Each warehouse and product combination will track:

- On-hand quantity
- Reserved quantity
- Available quantity
- Damaged quantity

```text
Available quantity = On-hand quantity - Reserved quantity
```

### Stock Transfer Workflow

```text
Draft → Approved → In Transit → Received
                  ↘ Cancelled
```

### Database Changes

Add or enhance the following tables:

- `warehouses`
- `warehouse_locations`
- `warehouse_inventories`
- `stock_transfers`
- `stock_transfer_items`
- `stock_movements`

The migration must create a default warehouse and move every existing `products.stock_quantity` value into it without losing inventory.

### Business Rules

- Stock cannot become negative.
- Reserved quantity cannot exceed on-hand quantity.
- Transfer approval requires sufficient stock at the source warehouse.
- Receiving a transfer atomically updates both warehouses.
- Every adjustment and transfer creates an immutable stock movement.
- Database transactions and row locks protect concurrent changes.

### Completion Criteria

- Products can hold stock in multiple warehouses.
- Stock can be transferred safely between warehouses.
- Existing stock is preserved during migration.
- Every inventory change is traceable.

---

## Phase 3: Supplier Purchasing

### Objective

Make purchasing and goods receipt the standard method for receiving stock from suppliers.

### Features

- Purchase-order creation
- Multiple items in one purchase order
- Supplier-specific prices
- Expected delivery date
- Purchase approval
- Partial goods receipt
- Goods Received Notes (GRN)
- Accepted, rejected, and damaged quantities
- Supplier invoice reference
- Purchase-order PDF generation
- Supplier purchase history

### Workflow

```text
Draft → Submitted → Approved → Ordered
                               ↓
                  Partially Received → Received
                               ↘ Cancelled
```

### Business Rules

- Creating or approving a purchase order does not increase stock.
- Inventory increases only when goods are received.
- Received quantities cannot exceed remaining ordered quantities.
- Each receipt creates stock movements.
- Completed or cancelled purchase orders cannot be edited improperly.

### Completion Criteria

- Purchase orders can be created, approved, and received.
- Full and partial receipts correctly update warehouse stock.
- Purchasing history is available for suppliers and products.

---

## Phase 4: Advanced Customer-Order Processing

### Objective

Upgrade the existing order workflow into a reliable fulfilment process.

### Workflow

```text
Pending → Confirmed → Packed → Shipped → Delivered
              ↓
           Cancelled
```

### Features

- Fulfilment warehouse selection
- Stock reservation during confirmation
- Order status history
- Packing records
- Multiple and partial shipments
- Shipping carrier and tracking number
- Delivery information
- Order notes
- Taxes and discounts
- Invoice generation
- Idempotency protection for repeated API requests

### Business Rules

- Confirmation reserves stock atomically.
- Available stock excludes reserved stock.
- Packing and shipping cannot exceed the reserved quantity.
- Cancellation releases unshipped reservations.
- The same stock cannot be deducted twice.
- Invalid status transitions are rejected.
- Prices are always read and calculated by the server.

### Completion Criteria

- Orders can be reserved, packed, shipped, and delivered.
- Partial fulfilment is supported.
- Duplicate requests do not create duplicate stock changes.
- Concurrent orders cannot oversell inventory.

---

## Phase 5: Returns and Refunds

### Objective

Handle complete and partial product returns after delivery.

### Workflow

```text
Requested → Approved → Received → Inspected
                                  ↓
                       Restocked / Damaged
                                  ↓
                               Refunded
```

### Features

- Full and partial returns
- Return reasons
- Return approval
- Received-item inspection
- Restock or damaged-stock decision
- Refund amount calculation
- Refund status tracking
- Return notes and history
- Stock movements for returned items

### Business Rules

- Only delivered quantities can be returned.
- The return quantity cannot exceed the remaining returnable quantity.
- Restockable items return to available inventory.
- Damaged items are recorded separately.
- Refund records cannot exceed the value of returned items.

Real payment-gateway refunds are optional; the system can track the business refund process internally.

### Completion Criteria

- Full and partial returns work correctly.
- Inventory reflects the inspection decision.
- Refund history is traceable and protected from duplicate processing.

---

## Phase 6: Permissions and Audit Logging

### Objective

Replace broad role-only authorization with granular permissions and record sensitive activity.

### Suggested Roles

- Administrator
- Inventory manager
- Purchase manager
- Sales employee
- Warehouse employee
- Auditor

### Example Permissions

- `products.manage`
- `inventory.view`
- `inventory.adjust`
- `transfers.create`
- `transfers.approve`
- `purchases.create`
- `purchases.approve`
- `orders.create`
- `orders.confirm`
- `orders.fulfil`
- `returns.process`
- `reports.view`
- `users.manage`

### Audit Information

Record:

- User
- Action
- Entity type and ID
- Old and new values
- Date and time
- IP address
- Optional reason

Sensitive inventory adjustments, approvals, cancellations, and refunds must require a reason.

### Completion Criteria

- Every protected action checks a specific permission on the server.
- Sensitive changes are searchable in the audit log.
- Users cannot grant permissions beyond their authority.

---

## Phase 7: Notifications and Background Jobs

### Objective

Move slow or scheduled operations into reliable asynchronous processing.

### Notifications

- Low-stock alerts
- Purchase-order approval alerts
- Goods-receipt notifications
- Delayed shipment alerts
- Order status notifications
- Return and refund updates
- Daily sales summaries

### Technical Tasks

- Configure Laravel queue workers.
- Add retry and failed-job handling.
- Add scheduled commands.
- Build an in-app notification center.
- Add optional email notifications.
- Store notification preferences per user.
- Ensure repeated jobs do not send duplicate notifications.

### Completion Criteria

- Slow notification work runs outside web requests.
- Failed jobs can be inspected and retried.
- Scheduled alerts execute automatically.

---

## Phase 8: Business Analytics

### Objective

Provide operational and management insights from transactional data.

### Reports

- Daily and monthly revenue
- Estimated profit
- Best-selling products
- Slow-moving products
- Inventory valuation
- Inventory turnover
- Dead stock
- Current and projected low stock
- Warehouse performance
- Average order fulfilment time
- Return rate
- Supplier lead time
- Supplier fulfilment accuracy
- Customer order history

### Go Reporting Service

- Keep the service read-only.
- Add warehouse, purchasing, fulfilment, and return reports.
- Add consistent filters and date ranges.
- Add query timeouts and safe database settings.
- Avoid duplicating Laravel transactional business logic.

### Optional Future Feature

Demand forecasting may be added only after the reporting data is reliable. It should include forecast accuracy and should never silently create purchase orders.

### Completion Criteria

- Reports are calculated from real transactional data.
- Date and warehouse filters work correctly.
- Automated tests verify important report totals.

---

## Phase 9: Testing, Security, and Reliability

### Objective

Provide measurable evidence that important business processes are correct and secure.

### Automated Test Coverage

- Authentication and token revocation
- Every role and permission
- Product and warehouse management
- Inventory adjustments
- Warehouse transfers
- Purchase approvals and receipts
- Partial goods receipts
- Stock reservations
- Concurrent ordering of the final item
- Reservation release after cancellation
- Partial packing and shipping
- Returns and refunds
- Invalid status transitions
- Duplicate-request prevention
- Audit logging
- Queue jobs and notifications
- Laravel report accuracy
- Go reporting endpoints

### Quality and Security Checks

- Laravel code formatting
- PHP static analysis
- Go formatting, tests, and vetting
- OpenAPI validation
- Database migration testing
- API rate limiting
- Safe validation and error responses
- Token expiry and revocation
- Secure headers and CORS configuration
- Mass-assignment protection
- Secret-management verification
- Dependency vulnerability checks
- Load testing for order confirmation and reports

Concurrency tests must run against MySQL because SQLite does not accurately reproduce MySQL row-locking behavior.

### Completion Criteria

- All automated tests pass in CI.
- Important rollback and concurrency cases are tested.
- Security checks do not report unresolved critical findings.
- Performance results are measured and documented honestly.

---

## Phase 10: Deployment and Portfolio Preparation

### Objective

Make the project easy for recruiters to open, understand, and evaluate.

### Deployment

- Production Docker configuration
- HTTPS
- Laravel queue worker
- Laravel scheduler
- Database migrations during deployment
- Application and service health checks
- Structured logs
- Error monitoring
- Database backup and restoration procedure
- CI/CD pipeline
- Safe demo accounts and sample data

### Portfolio Documentation

- Professional README
- Application screenshots
- Short demonstration video
- System architecture diagram
- Database ER diagram
- Order, purchasing, and return workflow diagrams
- API documentation
- Local and production setup instructions
- Engineering decisions and trade-offs
- Test and coverage results
- Measured API performance
- Known limitations
- Future improvements

### Completion Criteria

- The application is publicly accessible over HTTPS.
- A recruiter can use a safe demo account.
- The README clearly explains installation and business workflows.
- The project includes evidence of testing and engineering decisions.

---

## 3. Recommended Implementation Order

| Milestone | Main Outcome |
|---|---|
| 1 | Baseline verification and complete browser CRUD |
| 2 | Multi-warehouse inventory |
| 3 | Purchase orders and goods receipts |
| 4 | Stock reservation and advanced fulfilment |
| 5 | Returns, refunds, invoices, and shipments |
| 6 | Granular permissions, audit logs, and notifications |
| 7 | Analytics, comprehensive testing, and CI |
| 8 | Deployment and portfolio documentation |

---

## 4. Essential Placement Scope

The following features should be completed before optional additions:

1. Complete browser interface
2. Multi-warehouse inventory
3. Purchase-order and goods-receipt workflow
4. Transaction-safe stock reservation
5. Shipment and return processing
6. Granular permissions and audit logs
7. Strong automated tests, including concurrency tests
8. Docker deployment and CI/CD
9. Portfolio documentation and demonstration video

The following features are optional and should not delay completion of the core system:

- Demand forecasting
- Barcode or QR scanning
- Real payment-gateway integration
- Mobile application
- Complex microservice expansion

---

## 5. Working Rules During Implementation

- Preserve all existing working functionality.
- Implement one milestone at a time.
- Use additive database migrations; never rewrite old production migrations casually.
- Write tests with each feature instead of postponing them.
- Run regression tests after every meaningful change.
- Use database transactions for multi-record business operations.
- Use row locking where concurrent stock changes are possible.
- Keep inventory movements immutable and traceable.
- Enforce permissions on the server.
- Update Swagger and the README whenever an API or workflow changes.
- Do not claim performance, coverage, or scale numbers until they are measured.
- Keep the application runnable at the end of every milestone.

---

## 6. Final Resume Description

After all essential phases are complete, the project can be described as:

> Developed a production-style multi-warehouse inventory and order fulfilment platform using Laravel, MySQL, Go, Docker, and REST APIs, featuring transactional stock reservation, purchasing, warehouse transfers, shipment and return workflows, role-based permissions, audit trails, reporting, and automated tests.

The final resume bullet points should include only verified measurements such as automated test count, coverage, API response time, concurrent order handling, or deployment uptime.
