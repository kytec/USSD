<?php
$serverName = "DESKTOP-D9KBJEN\SQLEXPRESS";
$database = "USSDServiceDB";
$uid = "loginr";  // Default MSSQL username, change if different
$pwd = "loginr";  // Change to your MSSQL password

try {
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 