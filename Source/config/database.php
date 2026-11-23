<?php
    $host = "localhost";
    $user = "root";
    $password = "";
    $db = "jobconnect";

    try {
        $pdo = new PDO("mysql:host=$host; dbname=$db; charset=utf8mb4", $user, $password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    catch (PDOException $e) {
        error_log($e->getMessage());
        exit("Не може да се воспостави врска со базата на податоци");
    }
    