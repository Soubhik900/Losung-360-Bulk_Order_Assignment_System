<?php
// src/OrderService.php
require_once __DIR__ . '/../config/db.php';

class OrderService
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getUnassignedOrders($limit = 1000)
    {
        $query = "SELECT * FROM orders WHERE status IN ('NEW', 'UNASSIGNED') ORDER BY created_at ASC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createOrder($location, $value)
    {
        $query = "INSERT INTO orders (delivery_location, order_value, status) VALUES (:loc, :val, 'NEW')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':loc', $location);
        $stmt->bindParam(':val', $value);
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateStatus($orderId, $status)
    {
        $query = "UPDATE orders SET status = :status WHERE order_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);
        return $stmt->execute();
    }
}
?>