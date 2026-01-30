<?php
$db_host = 'localhost';
$db_name = 'supremexpansion';
$db_user = 'root';
$db_pass = 'Filipe200718'; // ajusta ao teu ambiente
            // "daverty2007"
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

$pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, $options);
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

?>