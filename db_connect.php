<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "iba_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Set timezone
date_default_timezone_set('Asia/Manila');

// REMOVE THE getMissingDocuments() FUNCTION FROM HERE
// It should only exist in lackingofdoc_logic.php

// Keep only essential database helper functions here if any
// Remove any duplicate functions that exist in other files
?>