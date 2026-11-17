<?php
/**
 * Database Configuration
 * 
 * This file creates a connection to the MySQL database.
 * It uses MySQLi (MySQL Improved) which is built into PHP.
 * 
 * How it works:
 * - We set the database credentials (host, username, password, database name).
 * - We create a new MySQLi connection object.
 * - If the connection fails, the script stops and shows an error.
 * - We set the character set to utf8mb4 for proper text handling.
 * - Other files include this file to use the $conn variable.
 */

// Database credentials – these match XAMPP's default MySQL settings
$db_host = 'localhost';       // Server name (localhost for XAMPP)
$db_user = 'root';            // Default MySQL username in XAMPP
$db_pass = '';                 // Default MySQL password in XAMPP (empty)
$db_name = 'ctu_registrar_system';  // Our database name

// Create connection if not already provided (supports test mocking)
if (!isset($conn)) {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check if connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set character encoding to handle special characters properly
    $conn->set_charset("utf8mb4");
}
