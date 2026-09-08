<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'admin');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS laravel');
    echo 'success';
} catch (PDOException $e) {
    echo 'error: ' . $e->getMessage();
}
