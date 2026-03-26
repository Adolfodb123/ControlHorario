<?php
// Diagnóstico de la Base de Datos

echo "<h2>DIAGNÓSTICO DE BASE DE DATOS</h2>";

// 1. Intentar conectar
echo "<h3>1. Verificando conexión...</h3>";
$mysqli = new mysqli('localhost', 'root', '', 'controlhorario_cmw');

if ($mysqli->connect_error) {
    die("<p style='color:red'><strong>❌ Error de conexión:</strong> " . $mysqli->connect_error . "</p>");
}
echo "<p style='color:green'>✓ Conexión exitosa</p>";

// 2. Verificar tablas
echo "<h3>2. Tablas en la base de datos:</h3>";
$result = $mysqli->query("SHOW TABLES");
if (!$result) {
    echo "<p style='color:red'>❌ Error al listar tablas: " . $mysqli->error . "</p>";
} else {
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    if (empty($tables)) {
        echo "<p style='color:red'>⚠️ No hay tablas en la base de datos</p>";
    } else {
        echo "<p style='color:green'>Tablas encontradas: " . implode(", ", $tables) . "</p>";
    }
}

// 3. Verificar tabla empleados_anual
echo "<h3>3. Verificando tabla 'empleados_anual':</h3>";
$result = $mysqli->query("SELECT COUNT(*) as count FROM empleados_anual");
if (!$result) {
    echo "<p style='color:red'>❌ Error: " . $mysqli->error . "</p>";
} else {
    $row = $result->fetch_assoc();
    echo "<p style='color:green'>✓ Registros en empleados_anual: " . $row['count'] . "</p>";
}

// 4. Verificar integridad
echo "<h3>4. Integridad de tablas:</h3>";
$result = $mysqli->query("CHECK TABLE empleados_anual");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['Msg_type'];
        $color = ($status === 'status') ? 'green' : 'orange';
        echo "<p style='color:$color'>" . $row['Table'] . ": " . $row['Msg_text'] . "</p>";
    }
}

$mysqli->close();
echo "<hr><p><strong>Script completado.</strong></p>";
?>
