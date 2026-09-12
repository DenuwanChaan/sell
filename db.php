<?php
// ============================================================
// Database connection — InfinityFree deployment settings
// ============================================================
// Fill these in from your InfinityFree control panel:
// Control panel → MySQL Databases → shows hostname, database name, username.
// The password is the one YOU set when you created the database
// (not your InfinityFree account password).

$host   = "sql207.infinityfree.com";      // MySQL hostname from control panel
$user   = "if0_42776628";                 // MySQL username from control panel
$pass   = "YOUR_DATABASE_PASSWORD";       // the password you set for this database
$dbname = "if0_42776628_hamadema";        // database name from control panel

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
