<?php
// importar_datos_simple.php - Script simplificado para importar datos del Excel

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Importar Datos</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; background: #efe; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .error { color: red; background: #fee; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .info { color: blue; background: #eef; padding: 10px; border-radius: 3px; margin: 10px 0; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Importar Datos del Excel</h1>
    
    <?php
    $xlsx = __DIR__ . '/ControlHorario.xlsx';
    
    if (!file_exists($xlsx)) {
        echo "<div class='error'>❌ No se encontró el archivo ControlHorario.xlsx en: $xlsx</div>";
        echo "<p>Por favor, asegúrate de que el archivo esté en la carpeta raíz del proyecto.</p>";
        exit;
    }
    
    echo "<div class='info'>✓ Archivo Excel encontrado</div>";
    
    // Conexión
    $mysqli = new mysqli('localhost', 'root', '', 'controlhorario_cmw');
    if ($mysqli->connect_error) {
        echo "<div class='error'>❌ Error de conexión: " . $mysqli->connect_error . "</div>";
        exit;
    }
    
    // Verificar tabla
    $result = $mysqli->query("SELECT COUNT(*) as count FROM empleados_anual");
    $row = $result->fetch_assoc();
    echo "<div class='info'>Registros actuales en tabla: " . $row['count'] . "</div>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'clear') {
                $mysqli->query("TRUNCATE TABLE empleados_anual");
                echo "<div class='success'>✓ Tabla vaciada</div>";
            } elseif ($_POST['action'] === 'import') {
                echo "<h3>Importando datos...</h3>";
                
                // Leer Excel
                $zip = new ZipArchive();
                if ($zip->open($xlsx) !== true) {
                    echo "<div class='error'>Error al abrir el archivo Excel</div>";
                    exit;
                }
                
                // Leer strings compartidos
                $sharedStrings = [];
                if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
                    $xml = simplexml_load_string($zip->getFromIndex($idx));
                    foreach ($xml->si as $si) {
                        $t = '';
                        if (isset($si->t)) {
                            $t = (string)$si->t;
                        } else {
                            foreach ($si->r as $r) {
                                $t .= (string)$r->t;
                            }
                        }
                        $sharedStrings[] = $t;
                    }
                }
                
                // Leer hoja
                $sheetPath = 'xl/worksheets/sheet1.xml';
                if (($idx = $zip->locateName($sheetPath)) === false) {
                    echo "<div class='error'>No se encontró la hoja en el Excel</div>";
                    exit;
                }
                
                $sheetXml = simplexml_load_string($zip->getFromIndex($idx));
                $rows = $sheetXml->sheetData->row;
                
                function getCellValue($c, $sharedStrings) {
                    $t = (string)$c['t'];
                    if ($t === 's') {
                        $v = (string)$c->v;
                        $idx = intval($v);
                        return $sharedStrings[$idx] ?? '';
                    }
                    if ($t === 'inlineStr') {
                        return isset($c->is->t) ? (string)$c->is->t : '';
                    }
                    return isset($c->v) ? (string)$c->v : '';
                }
                
                $insertSql = "INSERT INTO empleados_anual (full_name, Equipo, nombre_mes, dia_semana, Festivo, Justificado, `date`, horas, clock_in, clock_out) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($insertSql);
                if (!$stmt) {
                    echo "<div class='error'>Error preparando statement: " . $mysqli->error . "</div>";
                    exit;
                }
                
                $inserted = 0;
                $skipped = 0;
                
                foreach ($rows as $idx => $row) {
                    if ($idx === 0) continue; // Skip header
                    
                    $cells = [];
                    foreach ($row->c as $c) {
                        $ref = (string)$c['r'];
                        $cells[$ref] = getCellValue($c, $sharedStrings);
                    }
                    
                    // Mapeo de columnas (ajusta según tu Excel)
                    $full_name = $cells['A' . ($idx + 1)] ?? null;
                    $equipo = $cells['B' . ($idx + 1)] ?? null;
                    $nombre_mes = $cells['C' . ($idx + 1)] ?? null;
                    $dia_semana = $cells['D' . ($idx + 1)] ?? null;
                    $festivo = $cells['E' . ($idx + 1)] ?? null;
                    $justificado = $cells['F' . ($idx + 1)] ?? null;
                    $date = $cells['G' . ($idx + 1)] ?? null;
                    $horas = $cells['H' . ($idx + 1)] ?? null;
                    $clock_in = $cells['I' . ($idx + 1)] ?? null;
                    $clock_out = $cells['J' . ($idx + 1)] ?? null;
                    
                    if (empty($full_name)) {
                        $skipped++;
                        continue;
                    }
                    
                    $stmt->bind_param("ssssssssss", $full_name, $equipo, $nombre_mes, $dia_semana, $festivo, $justificado, $date, $horas, $clock_in, $clock_out);
                    
                    if ($stmt->execute()) {
                        $inserted++;
                    }
                }
                
                $stmt->close();
                $zip->close();
                
                echo "<div class='success'>✓ Importación completada: $inserted registros insertados, $skipped saltados</div>";
            }
        }
    }
    
    // Mostrar status actual
    $result = $mysqli->query("SELECT COUNT(*) as count FROM empleados_anual");
    $row = $result->fetch_assoc();
    echo "<div class='info'><strong>Registros en tabla ahora:</strong> " . $row['count'] . "</div>";
    
    $mysqli->close();
    ?>
    
    <hr>
    <form method="POST">
        <button type="submit" name="action" value="import" onclick="return confirm('¿Importar datos? Esto agregará los registros del Excel.')">Importar Datos del Excel</button>
        <button type="submit" name="action" value="clear" onclick="return confirm('¿Limpiar tabla? Esto eliminará todos los registros.')">Limpiar Tabla</button>
    </form>
    
    <hr>
    <p><a href="/">Volver al Control Horario</a></p>
</body>
</html>
