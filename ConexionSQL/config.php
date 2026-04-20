<?php
// config.php - Configuración de base de datos

class DatabaseConfig {
    public static function getConnectionParams() {
        return array(
            "host" => "localhost",
            "user" => "root",
            "password" => "",
            "database" => "controlhorario_cmw",
            "charset" => "utf8mb4"
        );
    }

    public static function connect() {
        $config = self::getConnectionParams();
        $conn = new mysqli($config["host"], $config["user"], $config["password"], $config["database"]);

        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }

        $conn->set_charset($config["charset"]);
        return $conn;
    }
}
?>