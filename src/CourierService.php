<?php
// src/CourierService.php
require_once __DIR__ . '/../config/db.php';

class CourierService
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAvailableCouriers()
    {
        // Fetch couriers who have capacity
        $query = "SELECT * FROM couriers WHERE current_assigned_count < daily_capacity";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementAssignmentCount($courierId)
    {
        // Optimistic locking / atomic update
        $query = "UPDATE couriers SET current_assigned_count = current_assigned_count + 1 
                  WHERE id = :id AND current_assigned_count < daily_capacity";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $courierId);
        $stmt->execute();
        return $stmt->rowCount() > 0; // Returns true if update actually happened
    }

    public function resetDailyCapacity()
    {
        // Run via cron at midnight
        $query = "UPDATE couriers SET current_assigned_count = 0";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>