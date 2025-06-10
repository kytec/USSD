<?php
$serverName = "DESKTOP-D9KBJEN\SQLEXPRESS"; // Corrected: Removed "localhost " prefix
// Or, if you prefer to be explicit about the server:
// $serverName = "localhost\SQLEXPRESS"; 
// $serverName = "127.0.0.1\SQLEXPRESS"; 

$connectionOptions = array(
    "Database" => "USSDServiceDB", 
    "Uid" => "loginr",             
    "PWD" => "loginr"              
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    echo "Connection failed: ";
    // Use json_encode for better readability of errors, especially in a browser
    die(json_encode(sqlsrv_errors(), JSON_PRETTY_PRINT)); 
} else {
    echo "Connected successfully with login!";
    // You can also add a message indicating the server and database connected to
    // echo "Connected successfully to {$connectionOptions['Database']} on {$serverName}!";
}

// Don't forget to close the connection when you're done!
// sqlsrv_close($conn); 
?>