<?php
// src/AssignmentService.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/OrderService.php';
require_once __DIR__ . '/CourierService.php';

class AssignmentService
{
    private $conn;
    private $db;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->db = new Database(); // Re-instantiating if needed or pass dependency
    }

    public function runBulkAssignment()
    {
        $orderService = new OrderService($this->conn);
        $courierService = new CourierService($this->conn);

        // 1. Fetch Orders (Processing in batches of 100 for example, to handle scale)
        $orders = $orderService->getUnassignedOrders(500);
        $couriers = $courierService->getAvailableCouriers();

        // 2. Index Couriers by Serviceable Locations for fast lookup
        // Structure: ['nyc' => [CourierA, CourierB], 'la' => [CourierC]]
        $courierMap = [];
        foreach ($couriers as $courier) {
            $locations = json_decode($courier['serviceable_locations'], true);
            if (is_array($locations)) {
                foreach ($locations as $loc) {
                    $courierMap[$loc][] = $courier;
                }
            }
        }

        $results = [
            'total_orders' => count($orders),
            'assigned' => 0,
            'failed' => 0,
            'details' => []
        ];

        // 3. Assignment Logic
        foreach ($orders as $order) {
            $loc = $order['delivery_location'];
            $assigned = false;

            if (isset($courierMap[$loc]) && !empty($courierMap[$loc])) {
                // Find best courier (Simple Strategy: First available with capacity)
                // Better heuristic: Sort by (daily_capacity - current_assigned_count) DESC

                // Shuffle to load balance randomly or sort
                // shuffle($courierMap[$loc]); 

                foreach ($courierMap[$loc] as $key => $courierData) {
                    $cId = $courierData['id'];

                    // Attempt to reserve capacity atomically in DB
                    // This creates a lock/check at the DB level to prevent race conditions
                    if ($courierService->incrementAssignmentCount($cId)) {

                        // Commit Assignment
                        $this->createAssignmentRecord($order['order_id'], $cId);

                        // Update Order Status
                        $orderService->updateStatus($order['order_id'], 'ASSIGNED');

                        // Update local tracking to avoid unnecessary DB hits in this loop
                        $courierMap[$loc][$key]['current_assigned_count']++;

                        // Check if full locally to avoid next attempt failure
                        if ($courierMap[$loc][$key]['current_assigned_count'] >= $courierMap[$loc][$key]['daily_capacity']) {
                            unset($courierMap[$loc][$key]); // Remove from pool
                        }

                        $assigned = true;
                        $results['assigned']++;
                        $results['details'][] = "Order {$order['order_id']} assigned to Courier {$cId}";
                        break; // Move to next order
                    }
                }
            }

            if (!$assigned) {
                // Determine reason
                $reason = isset($courierMap[$loc]) ? "Couriers full" : "No courier for location";
                // Optionally mark order as UNASSIGNED explicitly if it was NEW
                $orderService->updateStatus($order['order_id'], 'UNASSIGNED');
                $results['failed']++;
                $results['details'][] = "Order {$order['order_id']} failed: $reason";
            }
        }

        return $results;
    }

    private function createAssignmentRecord($orderId, $courierId)
    {
        $query = "INSERT INTO order_assignments (order_id, courier_id) VALUES (:oid, :cid)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':oid', $orderId);
        $stmt->bindParam(':cid', $courierId);
        $stmt->execute();
    }
}
?>