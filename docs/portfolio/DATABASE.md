# Database Overview

```mermaid
erDiagram
    USERS ||--o{ ORDERS : creates
    CUSTOMERS ||--o{ ORDERS : places
    WAREHOUSES ||--o{ ORDERS : fulfils
    ORDERS ||--|{ ORDER_ITEMS : contains
    PRODUCTS ||--o{ ORDER_ITEMS : sold_as
    WAREHOUSES ||--o{ WAREHOUSE_INVENTORIES : stores
    PRODUCTS ||--o{ WAREHOUSE_INVENTORIES : balanced_in
    SUPPLIERS ||--o{ PURCHASE_ORDERS : receives
    PURCHASE_ORDERS ||--|{ PURCHASE_ORDER_ITEMS : contains
    PURCHASE_ORDERS ||--o{ GOODS_RECEIPTS : received_as
    ORDERS ||--o{ ORDER_SHIPMENTS : ships_as
    ORDERS ||--o{ ORDER_RETURNS : returned_as
    ORDER_RETURNS ||--|{ ORDER_RETURN_ITEMS : contains
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ NOTIFICATIONS : receives
```

Stock is represented both by total product quantity for compatibility and by warehouse balances for operational accuracy. Every stock-changing workflow updates both values inside a transaction.
