<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


// 🔴 MySQL strict error mode (VERY IMPORTANT)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 🔵 Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$db   = "safetrack";

// 🔴 Create connection
try {
    $conn = new mysqli($host, $user, $pass, $db);

    // 🔴 Set charset (important for UTF-8 / Tamil)
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>