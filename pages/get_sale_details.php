<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/pos_functions.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$sale = getSaleDetails($connect2db, $id);

if (!$sale) {
    echo json_encode(['error' => 'Sale not found']);
    exit;
}

echo json_encode([
    'id'             => $sale['id'],
    'total_amount'   => $sale['total_amount'],
    'payment_method' => ucfirst($sale['payment_method']),
    'customer_name'  => $sale['customer_name'] ?? null,
    'notes'          => $sale['notes'] ?? null,
    'cashier'        => htmlspecialchars($sale['firstname'] . ' ' . $sale['lastname']),
    'created_at'     => date('M j, Y g:i A', strtotime($sale['created_at'])),
    'items'          => array_map(fn($i) => [
        'sku'        => $i['sku'],
        'name'       => $i['name'],
        'quantity'   => $i['quantity'],
        'unit_price' => $i['unit_price'],
        'total_price'=> $i['total_price'],
    ], $sale['items'] ?? []),
]);
