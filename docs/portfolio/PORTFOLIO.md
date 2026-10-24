# Portfolio Guide

## Verified project summary

Production-style multi-warehouse inventory and order fulfilment system built with Laravel 12, MySQL 8.4, Go 1.24, Docker, Nginx, REST APIs, and Vanilla JavaScript.

## Resume bullets

- Built a multi-warehouse inventory platform with purchasing, stock transfers, reservations, shipments, returns, refunds, and role-based permissions.
- Protected stock workflows using database transactions and row locking, with audit logs for successful data changes.
- Added queued notifications, scheduled jobs, operational analytics, Swagger documentation, Docker services, and automated CI checks.
- Verified the application with 80 automated tests and 423 assertions across authentication, inventory, orders, purchasing, returns, permissions, notifications, reports, and security.

## Known limitations

- Refunds are recorded internally; no payment gateway is connected.
- Inventory value uses selling price because product cost is not stored.
- Email uses the log driver until SMTP credentials are configured.
- HTTPS and uptime depend on the selected hosting provider.
- MySQL concurrency and load-test measurements should be added before claiming performance numbers.
- The current notification automation focuses on low stock.

## Recruiter demonstration

1. Sign in as admin and show the dashboard.
2. Create a purchase order and receive stock.
3. Create an order, confirm it, ship it, and show tracking.
4. Process a return and refund.
5. Show warehouse inventory, audit logs, notifications, analytics, Swagger, and the passing CI workflow.
