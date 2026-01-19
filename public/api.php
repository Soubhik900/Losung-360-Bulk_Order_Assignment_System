<?php
// public/api.php

// Simple REST API Router
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/CourierService.php';
require_once __DIR__ . '/../src/AssignmentService.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Route: /orders
if ($path == 'orders' && $method == 'GET') {
    $orderService = new OrderService($db);
    echo json_encode($orderService->getUnassignedOrders());
    exit;
}

// Route: /couriers
if ($path == 'couriers' && $method == 'GET') {
    $courierService = new CourierService($db);
    echo json_encode($courierService->getAvailableCouriers());
    exit;
}

// Route: /assignment/bulk
if ($path == 'assignment/bulk' && $method == 'POST') {
    $assignmentService = new AssignmentService($db);
    try {
        $result = $assignmentService->runBulkAssignment();
        echo json_encode(["status" => "success", "data" => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Default 404
http_response_code(404);
echo json_encode(["message" => "Endpoint not found"]);
?>