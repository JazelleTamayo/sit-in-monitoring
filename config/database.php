<?php
// sit-in-monitoring/config/database.php
$host     = 'localhost';
$dbname   = 'ccs_sitin_db';
$username = 'root';
$password = ''; // XAMPP default is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,          PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false); // Fix LIMIT/OFFSET on XAMPP
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>