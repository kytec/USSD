<?php
// Mock callback endpoint for transaction status updates
file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . ' - Callback received: ' . print_r($_POST, true) . "\n", FILE_APPEND);
echo json_encode(['status' => 'received', 'message' => 'Callback processed (mock)']);
