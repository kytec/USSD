<?php
function validateMTNRecipient($number) {
    // Example MTN API call
    $url = 'https://mtn.example.com/api/validate';
    $payload = ['number' => $number];
    return callAPI($url, $payload);
}

function validateTelecelRecipient($number) {
    $url = 'https://telecel.example.com/api/validate';
    $payload = ['number' => $number];
    return callAPI($url, $payload);
}

function validateAirtelTigoRecipient($number) {
    $url = 'https://airteltigo.example.com/api/validate';
    $payload = ['number' => $number];
    return callAPI($url, $payload);
}

function validateGCBAccount($account) {
    $url = 'https://gcb.example.com/api/validate';
    $payload = ['account' => $account];
    return callAPI($url, $payload);
}

// Generic API caller
function callAPI($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}