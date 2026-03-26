<?php
// Script para RECUPERAR la base de datos corrompida
// Recrear tabla empleados_anual

$mysqli = new mysqli('localhost', 'root', '', 'controlhorario_cmw');
if ($mysqli->connect_error) {
    die("❌ Error de conexión: " . $mysqli->connect_error);
}

echo "<h2>RECUPERACIÓN DE BASE DE DATOS</h2>";

// 1. Eliminar tabla si existe
echo "<h3>1. Eliminando tabla anterior (si existe)...</h3>";
if ($mysqli->query("DROP TABLE IF EXISTS empleados_anual")) {
    echo "<p style='color:green'>✓ Tabla anterior eliminada</p>";
} else {
    echo "<p style='color:red'>❌ Error: " . $mysqli->error . "</p>";
}

// 2. Crear tabla con la estructura correcta
echo "<h3>2. Creando nueva tabla empleados_anual...</h3>";
$createTableSQL = "
CREATE TABLE empleados_anual (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    Equipo VARCHAR(255),
    nombre_mes VARCHAR(50),
    dia_semana VARCHAR(20),
    Festivo VARCHAR(50),
    Justificado VARCHAR(255),
    date DATE,
    horas DECIMAL(5, 2),
    clock_in TIME,
    clock_out TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_full_name (full_name),
    INDEX idx_date (date),
    INDEX idx_equipo (Equipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($mysqli->query($createTableSQL)) {
    echo "<p style='color:green'>✓ Tabla creada correctamente</p>";
} else {
    echo "<p style='color:red'>❌ Error: " . $mysqli->error . "</p>";
}

// 3. Verificar tabla
echo "<h3>3. Verificando tabla creada...</h3>";
$result = $mysqli->query("DESCRIBE empleados_anual");
if ($result) {
    echo "<p style='color:green'>✓ Estructura de tabla:</p>";
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>".$row['Field']."</td><td>".$row['Type']."</td><td>".$row['Null']."</td><td>".$row['Key']."</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ Error: " . $mysqli->error . "</p>";
}

$mysqli->close();

echo "<hr>";
echo "<p><strong>✓ Base de datos recuperada.</strong></p>";
echo "<p><strong>Próximo paso:</strong> Ve a <code>import_excel_to_db.php</code> para importar los datos desde el Excel.</p>";
?>
