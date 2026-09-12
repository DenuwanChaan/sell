<?php
$host = "sql207.infinityfree.com"; 
$user = "if0_42776628";           
$pass = "YOUR_ACCOUNT_PASSWORD";   // InfinityFree Account Password එක මෙතනට දාන්න
$dbname = "if0_42776628_hamadema"; // ඔයා හදපු Database එකේ නම

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>