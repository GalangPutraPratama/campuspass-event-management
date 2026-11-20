<?php

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'db_campuspass';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die('Koneksi database gagal.');
}

$conn->set_charset('utf8mb4');
