<?php
$serverName = "DESKTOP-GK426B3";
$database = "USSDServiceDB";
$uid = "loginr";  // Default MSSQL username, change if different
$pwd = "loginr";  // Change to your MSSQL password

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 