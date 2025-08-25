<?php
$serverName = "DESKTOP-GK426B3";
$database = "USSDServiceDB";
$uid = "loginr";  // Default MSSQL username, change if different
$pwd = "loginr";  // Change to your MSSQL password

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$pdo = null;
$conn = null;

// Try SQL Server first (original configuration)
try {
    if (extension_loaded('sqlsrv')) {
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn = $pdo;
    } else {
        throw new Exception("SQL Server extension not loaded");
    }
} catch(Exception $e) {
    // If SQL Server fails, just set to null - menu manager will use fallback
    $pdo = null;
    $conn = null;
}
?> 