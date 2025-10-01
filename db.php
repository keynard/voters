<?php
// db.php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = ""; // set your mysql root password if any
$DB_NAME = "voting_system";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    throw new Exception("DB Connect failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
