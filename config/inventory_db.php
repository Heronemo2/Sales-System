<?php

// Direct connection to the Inventory team's database.
// Sales and Inventory remain separate databases on the same MySQL/MariaDB server.

$inventory_db_host = 'localhost';
$inventory_db_name = 'inventory_system';
$inventory_db_user = 'root';
$inventory_db_pass = '';

function getInventoryDB() {
    $host = 'localhost';
    $dbname = 'inventory_system';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        die("Could not connect to the Inventory database: " . $e->getMessage());
    }
}
