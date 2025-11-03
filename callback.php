<?php
// Investment Callback Handler

// Placeholder: Set your endpoint URL here
// Example: https://yourdomain.com/USSD2/USSD/callback.php

// Receive callback data (assuming POST)
$data = json_decode(file_get_contents('php://input'), true);

// Example expected fields: reference, status, message
$reference = $data['reference'] ?? null;
$status = $data['status'] ?? null;
$message = $data['message'] ?? null;

// Connect to DB
require_once 'db_connect.php';

if ($reference) {
    // Update transaction status and callback message
    $stmt = $pdo->prepare("UPDATE transactions SET status = ?, callback_message = ? WHERE reference = ?");
    $stmt->execute([$status, $message, $reference]);
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing reference']);
}