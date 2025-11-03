<?php
function initiateInvestment($userId, $amount, $product, $reference) {
    $url = 'https://investmentcompany.com/api/invest';
    $payload = [
        'user_id' => $userId,
        'amount' => $amount,
        'product' => $product,
        'reference' => $reference
    ];
    return callInvestmentAPI($url, $payload);
}

function callInvestmentAPI($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}