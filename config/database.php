<?php

$dbname = "gestion_documentos";
$dbuser = "root";
$dbpass = "";

try {
    $conn = new PDO("mysql:host=localhost" . ";dbname=" . $dbname, $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    die();
}
?> 