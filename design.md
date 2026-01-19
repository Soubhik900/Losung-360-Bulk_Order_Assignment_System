# Bulk Order Assignment System - Design Document

## 1. Database Design

### Entities & Schema

We will use a relational database (MySQL) to ensure data integrity and support complex queries.

#### Tables

1.  **couriers**
    *   `id` (INT, PK, Auto Increment)
    *   `name` (VARCHAR)
    *   `serviceable_locations` (JSON) - Stores list of zones/cities.
    *   `daily_capacity` (INT)
    *   `current_assigned_count` (INT) - Default 0.
    *   `created_at` (TIMESTAMP)

2.  **orders**
    *   `order_id` (INT, PK, Auto Increment)
    *   `order_date` (DATETIME)
    *   `delivery_location` (VARCHAR) - City/Zone.
    *   `order_value` (DECIMAL)
    *   `status` (ENUM: 'NEW', 'ASSIGNED', 'UNASSIGNED') - Default 'NEW'.
    *   `created_at` (TIMESTAMP)

3.  **order_assignments**
    *   `assignment_id` (INT, PK, Auto Increment)
    *   `order_id` (INT, FK -> orders.order_id)
    *   `courier_id` (INT, FK -> couriers.id)
    *   `assignment_date` (DATETIME)
    *   `status` (ENUM: 'SUCCESS', 'FAILED')

#### Indexes
*   **orders(status, delivery_location)**: Critical for fetching unassigned orders filtering by location efficiently during the bulk assignment process.
*   **couriers(current_assigned_count, daily_capacity)**: To quickly filter couriers who still have capacity.
*   **order_assignments(order_id)**: Fast lookup for assignment history of an order.
*   **order_assignments(courier_id)**: To track performance or load of a courier.

## 2. Bulk Assignment Logic

### Algorithm: Greedy Location-Based Matching
Since we have 1000-10000 orders, a simple greedy approach with locking or atomic updates is efficient enough.

1.  **Fetch Context**:
    *   Get all `NEW` or `UNASSIGNED` orders.
    *   Get all active `Couriers` with `current_assigned_count < daily_capacity`.
2.  **Grouping**:
    *   Group orders by `delivery_location`.
    *   Group couriers by `serviceable_locations`.
3.  **Matching**:
    *   Iterate through order groups (locations).
    *   For each location, find available couriers who service this location.
    *   Sort couriers by explicit heuristic if needed (e.g., most available capacity).
    *   Assign orders to the chosen courier until courier capacity is full or orders are exhausted.
    *   Update `courier.current_assigned_count` and `order.status`.
4.  **Transaction Management**:
    *   Perform assignments in batches (e.g., 100 orders per transaction) to avoid locking table for too long.
    *   Use Optimistic Locking (or database constraints) to ensure `current_assigned_count` does not exceed `daily_capacity`.

### Handling Failures
*   **Internal Errors**: Wrap batch insert/updates in a DB transaction. Rollback on exception.
*   **3rd Party Limits**: If an external API call to notify courier fails, mark assignment locally as 'PENDING_SYNC' or similar, but for this scope, we assume internal assignment.
*   **Capacity Overflow**: Database check `WHERE current_assigned_count < daily_capacity` prevents this.

## 3. API Design

All APIs use standard HTTP verbs and JSON.

1.  **Fetch Unassigned Orders**
    *   **GET** `/api/orders?status=UNASSIGNED`
    *   Response: `[ { "order_id": 1, "location": "NYC", ... } ]`

2.  **Fetch Available Couriers**
    *   **GET** `/api/couriers?available=true`
    *   Response: `[ { "id": 101, "capacity": 50, "assigned": 10, ... } ]`

3.  **Bulk Assign Orders**
    *   **POST** `/api/assignments/bulk`
    *   Payload: `(Optional: explicit filters like date range)` or empty to trigger auto-assignment of pending orders.
    *   Response: `{ "assigned": 500, "failed": 2, "errors": [...] }`

4.  **View Assignment Results**
    *   **GET** `/api/assignments`
    *   Response: `[ { "order_id": 1, "courier_id": 101, "status": "SUCCESS" } ]`

## 4. Performance & Scalability

*   **Optimization**:
    *   Fetch only necessary fields.
    *   **Batch processing**: Don't load 10,000 objects into PHP memory. Process in pages of 500.
*   **Concurrency**:
    *   Use `SELECT ... FOR UPDATE` when fetching specific orders to lock them during assignment if running multiple assignment workers.
    *   Ideally, use a **single producer** (job scheduler) that queues chunks of orders for assignment workers to avoid complex race conditions.
*   **Database**:
    *   Use database constraints (CHECK constraints) for capacity.

## 5. Edge Cases
*   **No Couriers**: Mark orders as `UNASSIGNED` with a reason code if possible, or leave as `NEW`. Log alert.
*   **Partial Assignment**: The system is designed to assign what it can. Use a response summary to indicate e.g., "450/500 assigned".
*   **Capacity Updates**: If capacity drops below current assigned, stop new assignments. (Existing ones remain valid unless manual intervention).
*   **Duplicates**: `order_assignments` table has value in idempotency. Ensure generic Unique Constraint on `(order_id, assignment_date)` or just `order_id` if re-assignment requires clearing old one.

## 6. Error & Retry
*   **Retry**: Use a cron job to re-trigger assignment for `UNASSIGNED` orders every X minutes.
*   **Monitoring**: Log all bulk operations. Alert if unassigned count > threshold.
