<?php
    declare(strict_types = 1);

    function conectar(): PDO {
        static $pdo = null;

        if ($pdo !== null) {
            return $pdo;
        }

        $dsn  = "mysql:host=localhost;dbname=SPORTSCAR;charset=utf8mb4";
        $user = "root";
        $pass = "";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_CASE => PDO::CASE_LOWER
        ]);

        return $pdo;
    }
?>