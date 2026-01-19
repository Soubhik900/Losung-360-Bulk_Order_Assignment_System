# Bulk Order Assignment System

## Assignment Submission

This repository contains the backend service design and implementation for the Bulk Order Assignment System.

### Deliverables

1.  **Design Document**: [design.md](./design.md)
    *   Includes Database Schema Diagram (description), Indexes, Algorithm logic, API Design, and Scalability considerations.
2.  **Database SQL**: [schema.sql](./schema.sql)
    *   MySQL dump file to create `couriers`, `orders`, and `order_assignments` tables.
3.  **Source Code**:
    *   `src/`: Contains the core logic classes (`OrderService`, `CourierService`, `AssignmentService`).
    *   `public/`: Contains the entry point `api.php` for the REST API.
    *   `config/`: Database connection settings.

### How to Run

**Prerequisites:**
*   PHP 7.4 or higher
*   MySQL 5.7 or higher
*   Web Server (Apache/Nginx) or PHP built-in server

**Steps:**
1.  Import `schema.sql` into a MySQL database named `bulk_order_system`.
2.  Configure database credentials in `config/db.php`.
3.  Start the server:
    ```bash
    php -S localhost:8000 -t public
    ```
4.  Access the API via `http://localhost:8000/api.php`

### API Endpoints
*   `GET /api.php?path=orders` - Fetch unassigned orders.
*   `GET /api.php?path=couriers` - Fetch available couriers.
*   `POST /api.php?path=assignment/bulk` - Trigger the bulk assignment algorithm.
