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
    if (extension_loaded('pdo_sqlsrv')) {
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn = $pdo;
        
        error_log("DB Connection: SUCCESS with pdo_sqlsrv");
    } elseif (extension_loaded('sqlsrv')) {
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn = $pdo;
        
        error_log("DB Connection: SUCCESS with sqlsrv");
    } else {
        error_log("DB Connection: FAILED - No SQL Server extension found");
        error_log("Available extensions: " . implode(', ', get_loaded_extensions()));
        throw new Exception("SQL Server extension not loaded");
    }
} catch(Exception $e) {
    // If SQL Server fails, just set to null - menu manager will use fallback
    error_log("DB Connection: FAILED - " . $e->getMessage());
    $pdo = null;
    $conn = null;
}
?>