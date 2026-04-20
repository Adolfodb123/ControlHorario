<?php
//ESTO ES Index.php REFACTORIZADO - JavaScript del menú movido a archivo separado

// Index.php mi pagina principal
require_once '../auth_check.php';
requerir_rol_admin();

// Cargar configuración de permisos
require_once 'user-permissions.php';

// Obtener el usuario actual desde la sesión
$usuarioActual = $_SESSION['user_display_name'] ?? $_SESSION['user'] ?? 'Usuario';

// Log para debugging
error_log("DEBUG: Usuario actual: " . $usuarioActual);
$permisosUsuario = obtenerPermisosUsuario($usuarioActual);
if ($permisosUsuario) {
    error_log("DEBUG: Permisos encontrados para $usuarioActual: " . json_encode($permisosUsuario));
}

?>

<?php
header('Content-Type: text/html; charset=UTF-8');

// Configuración de conexión a MySQL
$host = "localhost";
$user = "root";
$password = "";
$database = "controlhorario_cmw";

// Función para conectar a la base de datos
function conectarBD() {
    global $host, $user, $password, $database;
    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        error_log("Error de conexión MySQL: " . $conn->connect_error);
        throw new Exception("Error de conexión a la base de datos");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Función auxiliar para escapar strings en MySQL
function mysql_escape_string($value) {
    return addslashes($value);
}

// Función para obtener valores únicos para los filtros
function obtenerValoresFiltros() {
    try {
        global $usuarioActual; // Variable global definida al inicio
        
        error_log("DEBUG: Iniciando obtenerValoresFiltros() para usuario: " . $usuarioActual);
        $conn = conectarBD();
        
        $queries = [
            'empleados' => "SELECT DISTINCT full_name FROM empleados_anual WHERE full_name IS NOT NULL ORDER BY full_name",
            'equipos' => "SELECT DISTINCT Equipo FROM empleados_anual WHERE Equipo IS NOT NULL ORDER BY Equipo",
            'meses' => "SELECT DISTINCT nombre_mes FROM empleados_anual WHERE nombre_mes IS NOT NULL",
            'dias' => "SELECT DISTINCT dia_semana FROM empleados_anual WHERE dia_semana IS NOT NULL",
            'festivos' => "SELECT DISTINCT Festivo FROM empleados_anual WHERE Festivo IS NOT NULL ORDER BY Festivo",
            'justificados' => "SELECT DISTINCT Justificado FROM empleados_anual WHERE Justificado IS NOT NULL ORDER BY Justificado"
        ];
        
        $resultado = [];
        
        foreach ($queries as $tipo => $query) {
            // APLICAR FILTROS DE SEGURIDAD A LA CONSULTA
            if (usuarioTieneRestricciones($usuarioActual)) {
                $whereSeguridad = aplicarFiltrosSeguridad($usuarioActual, []);
                if (!empty($whereSeguridad)) {
                    // Agregar restricciones de seguridad a la consulta
                    $query = str_replace(" ORDER BY", " AND " . implode(" AND ", $whereSeguridad) . " ORDER BY", $query);
                    if (!strpos($query, " ORDER BY")) {
                        $query = str_replace(" WHERE ", " WHERE " . implode(" AND ", $whereSeguridad) . " AND ", $query);
                    }
                }
            }
            
            error_log("DEBUG: Query para $tipo: " . $query);
            
            $stmt = mysqli_query($conn, $query);
            if ($stmt === false) {
                error_log("Error en consulta de filtros ($tipo): " . mysqli_error($conn));
                throw new Exception("Error al obtener valores para filtros");
            }
            
            $valores = [];
            while ($row = mysqli_fetch_array($stmt, MYSQLI_NUM)) {
                $valores[] = $row[0];
            }
            mysqli_free_result($stmt);
            
            $resultado[$tipo] = $valores;
        }
        
        mysqli_close($conn);
        
        // APLICAR FILTROS ADICIONALES POR PERMISOS DE USUARIO
        $resultado = filtrarValoresPorPermisos($usuarioActual, $resultado);
        
        error_log("DEBUG: Valores filtrados para usuario $usuarioActual: " . json_encode(array_map('count', $resultado)));
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error en obtenerValoresFiltros: " . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Error al cargar valores de filtros'
        ];
    }
}

// Función para obtener datos con filtros aplicados en SQL
function obtenerDatosFiltrados($filtros = [], $limite = 1000) {
    try {
        global $usuarioActual; // Variable global definida al inicio
        
        error_log("DEBUG: Iniciando obtenerDatosFiltrados() para usuario: $usuarioActual con límite: $limite");
        error_log("DEBUG: Filtros recibidos: " . json_encode($filtros));
        
        $conn = conectarBD();
        
        // Query base ordenada por fecha
        // Se seleccionan columnas esperadas por el frontend (incluye campos "ficticios" para mantener compatibilidad)
        $query = "SELECT 
            full_name,
            date,
            dia_semana,
            COALESCE(clock_in,  IF(horas > 0 AND DAYOFWEEK(date) NOT IN (1,7), '07:00:00', NULL)) AS clock_in,
            COALESCE(clock_out, IF(horas > 0 AND DAYOFWEEK(date) NOT IN (1,7), SEC_TO_TIME(25200 + ROUND(horas * 3600)), NULL)) AS clock_out,
            '' AS role_name,
            Equipo,
            CASE WHEN DAYOFWEEK(date) IN (1,7) THEN 0
                 ELSE COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60)
            END AS minutos_trabajados,
            CASE WHEN DAYOFWEEK(date) IN (1,7) OR Festivo = 'Sí' THEN 0
                 ELSE GREATEST(0, COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60) - 480)
            END AS exceso_min,
            CASE WHEN DAYOFWEEK(date) IN (1,7) OR Festivo = 'Sí' OR Justificado = 'Sí' THEN 0
                 ELSE GREATEST(0, 480 - COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60))
            END AS faltantes_min,
            Festivo,
            Justificado
        FROM empleados_anual
        WHERE date IS NOT NULL";
        
        // Construir WHERE clauses dinámicamente
        $whereClauses = [];
        
        if (!empty($filtros['empleados'])) {
            $empleadosIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['empleados'])) . "'";
            $whereClauses[] = "full_name IN ($empleadosIn)";
        }
        
        if (!empty($filtros['equipos'])) {
            $equiposIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['equipos'])) . "'";
            $whereClauses[] = "Equipo IN ($equiposIn)";
        }
        
        if (!empty($filtros['meses'])) {
            $mesesIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['meses'])) . "'";
            $whereClauses[] = "nombre_mes IN ($mesesIn)";
        }
        
        if (!empty($filtros['dias'])) {
            $diasIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['dias'])) . "'";
            $whereClauses[] = "dia_semana IN ($diasIn)";
        }
        
        if (!empty($filtros['festivos'])) {
            $festivosIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['festivos'])) . "'";
            $whereClauses[] = "Festivo IN ($festivosIn)";
        }
        
        if (!empty($filtros['justificados'])) {
            $justificadosIn = "'" . implode("', '", array_map('mysql_escape_string', $filtros['justificados'])) . "'";
            $whereClauses[] = "Justificado IN ($justificadosIn)";
        }
        
        // APLICAR FILTROS DE SEGURIDAD POR USUARIO
        $whereClauses = aplicarFiltrosSeguridad($usuarioActual, $whereClauses);
        
        // Agregar WHERE clauses si existen
        if (!empty($whereClauses)) {
            $query .= " AND " . implode(" AND ", $whereClauses);
        }
        
        // Ordenar por fecha
        $query .= " ORDER BY date DESC, full_name";
        
        // Aplicar límite si no es 'ALL'
        if ($limite !== 'ALL' && $limite !== 0 && $limite !== '0' && $limite !== null) {
            $limite = intval($limite);
            $query .= " LIMIT $limite";
        }
        
        error_log("DEBUG: Query final con filtros de seguridad: " . $query);
        
        $stmt = mysqli_query($conn, $query);
        if ($stmt === false) {
            error_log("ERROR: Falló la consulta de datos: " . mysqli_error($conn));
            throw new Exception("Error al obtener datos filtrados: " . mysqli_error($conn));
        }
        
        $datos = array();
        $count = 0;
        while ($row = mysqli_fetch_assoc($stmt)) {
            $datos[] = $row;
            $count++;
            if ($count % 1000 == 0) {
                error_log("DEBUG: Procesados $count registros...");
            }
        }
        
        error_log("DEBUG: Total registros obtenidos para usuario $usuarioActual: " . count($datos));
        
        mysqli_free_result($stmt);
        mysqli_close($conn);
        
        return $datos;
        
    } catch (Exception $e) {
        error_log("ERROR: Error en obtenerDatosFiltrados: " . $e->getMessage());
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

// Función para obtener datos de resumen mensual con filtros
function obtenerDatosResumenMensual($filtros = [], $limite = 'ALL') {
    try {
        global $usuarioActual; // Variable global definida al inicio
        
        error_log("DEBUG: Iniciando obtenerDatosResumenMensual() para usuario: $usuarioActual con límite: $limite");
        error_log("DEBUG: Filtros recibidos: " . json_encode($filtros));
        
        $conn = conectarBD();
        
        // Construir la consulta base
        $whereClause = "WHERE 1=1";
        
        if (!empty($filtros['empleados'])) {
            $empleadosIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['empleados'])) . "'";
            $whereClause .= " AND nombre_empleado IN ($empleadosIn)";
        }
        
        if (!empty($filtros['equipos'])) {
            $equiposIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['equipos'])) . "'";
            $whereClause .= " AND Equipo IN ($equiposIn)";
        }
        
        if (!empty($filtros['meses'])) {
            $mesesIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['meses'])) . "'";
            $whereClause .= " AND nombre_mes IN ($mesesIn)";
        }

        // APLICAR FILTROS DE SEGURIDAD POR USUARIO PARA VISTA RESUMEN
        $whereClause = aplicarFiltrosSeguridadResumen($usuarioActual, $whereClause);

        // Definir el ORDER BY
        $orderClause = "ORDER BY 
                        nombre_empleado,
                        CASE LOWER(nombre_mes)
                            WHEN 'enero' THEN 1
                            WHEN 'febrero' THEN 2
                            WHEN 'marzo' THEN 3
                            WHEN 'abril' THEN 4
                            WHEN 'mayo' THEN 5
                            WHEN 'junio' THEN 6
                            WHEN 'julio' THEN 7
                            WHEN 'agosto' THEN 8
                            WHEN 'septiembre' THEN 9
                            WHEN 'octubre' THEN 10
                            WHEN 'noviembre' THEN 11
                            WHEN 'diciembre' THEN 12
                            ELSE 13
                        END";

        // Construir consulta final dependiendo del límite
        if ($limite !== 'ALL' && $limite !== '0' && $limite !== 0 && $limite !== null) {
            $limite = intval($limite);
            
            $sql = "
                SELECT TOP ($limite)
                    [employee_id],
                    [nombre_empleado],
                    [Equipo],
                    [nombre_mes],
                    [total_dias_mes],
                    [dias_trabajados],
                    [dias_laborables_teoricos],
                    [dias_festivos],
                    [dias_permiso],
                    [horas_trabajadas_hhMM],
                    [horas_exceso_hhMM],
                    [horas_faltantes_hhMM]
                FROM empleados_resumen
                $whereClause
                $orderClause
            ";
        } else {
            $sql = "
                SELECT
                    [employee_id],
                    [nombre_empleado],
                    [Equipo],
                    [nombre_mes],
                    [total_dias_mes],
                    [dias_trabajados],
                    [dias_laborables_teoricos],
                    [dias_festivos],
                    [dias_permiso],
                    [horas_trabajadas_hhMM],
                    [horas_exceso_hhMM],
                    [horas_faltantes_hhMM]
                FROM empleados_resumen
                $whereClause
                $orderClause
            ";
        }
        
        error_log("DEBUG: Query final resumen con filtros de seguridad para usuario $usuarioActual: " . $sql);
        
        $stmt = mysqli_query($conn, $sql);
        if ($stmt === false) {
            error_log("ERROR: Falló la consulta de resumen: " . mysqli_error($conn));
            throw new Exception("Error al obtener datos de resumen: " . mysqli_error($conn));
        }
        
        $datos = array();
        $count = 0;
        while ($row = mysqli_fetch_assoc($stmt)) {
            // Normalización consistente
            $datos[] = [
                'employee_id'              => $row['employee_id'],
                'nombre_empleado'          => $row['nombre_empleado'],
                'Equipo'                   => $row['Equipo'],
                'nombre_mes'               => $row['nombre_mes'],
                'total_dias_mes'           => (int)$row['total_dias_mes'],
                'dias_trabajados'          => (int)$row['dias_trabajados'],
                'dias_laborables_teoricos' => (int)$row['dias_laborables_teoricos'],
                'dias_festivos'            => (int)$row['dias_festivos'],
                'dias_permiso'             => (int)$row['dias_permiso'],
                'horas_trabajadas_hhMM'    => (string)$row['horas_trabajadas_hhMM'],
                'horas_exceso_hhMM'        => (string)$row['horas_exceso_hhMM'],
                'horas_faltantes_hhMM'     => (string)$row['horas_faltantes_hhMM'],
            ];
            $count++;
        }
        
        error_log("DEBUG: Total registros resumen obtenidos para usuario $usuarioActual: " . count($datos));
        
        mysqli_free_result($stmt);
        mysqli_close($conn);
        
        return $datos;
        
    } catch (Exception $e) {
        error_log("ERROR: Error en obtenerDatosResumenMensual: " . $e->getMessage());
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

// Manejo de peticiones AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $action = $_GET['action'] ?? '';

    switch ($action) {

        // ===== PRUEBA MUY BÁSICA (sin BD) =====
        case 'export_excel_ping':
            try {
                if (ob_get_length()) { ob_end_clean(); }
                require_once dirname(__DIR__) . '/vendor/autoload.php'; // vendor está 1 nivel arriba de CMW/

                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Ping');
                $sheet->setCellValue('A1', 'Funciona');
                $sheet->setCellValue('A2', date('Y-m-d H:i:s'));

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="ping.xlsx"');
                header('Cache-Control: max-age=0');

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;

            } catch (\Throwable $e) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        // Y también actualizar el caso datos_resumen_mensual:
        case 'datos_resumen_mensual':
            try {
                global $usuarioActual; // AGREGAR esta línea
                
                $conn = conectarBD();

                // Construir WHERE clause base
                $whereClause = "WHERE 1=1";
                
                // APLICAR FILTROS DE SEGURIDAD POR USUARIO - AGREGAR estas líneas
                $whereClause = aplicarFiltrosSeguridadResumen($usuarioActual, $whereClause);

                $sql = "
                    SELECT
                        [employee_id],
                        [nombre_empleado],
                        [Equipo],
                        [nombre_mes],
                        [total_dias_mes],
                        [dias_trabajados],
                        [dias_laborables_teoricos],
                        [dias_festivos],
                        [dias_permiso],
                        [horas_trabajadas_hhMM],
                        [horas_exceso_hhMM],
                        [horas_faltantes_hhMM]
                    FROM empleados_resumen
                    $whereClause
                    ORDER BY 
                        nombre_empleado,
                        CASE LOWER(nombre_mes)
                            WHEN 'enero' THEN 1
                            WHEN 'febrero' THEN 2
                            WHEN 'marzo' THEN 3
                            WHEN 'abril' THEN 4
                            WHEN 'mayo' THEN 5
                            WHEN 'junio' THEN 6
                            WHEN 'julio' THEN 7
                            WHEN 'agosto' THEN 8
                            WHEN 'septiembre' THEN 9
                            WHEN 'octubre' THEN 10
                            WHEN 'noviembre' THEN 11
                            WHEN 'diciembre' THEN 12
                            ELSE 13
                        END
                ";

                error_log("DEBUG datos_resumen_mensual - SQL con filtros de seguridad para usuario $usuarioActual: " . $sql);

                $stmt = mysqli_query($conn, $sql);
                if ($stmt === false) {
                    $err = mysqli_error($conn);
                    error_log("SQL ERROR datos_resumen_mensual: " . $err);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => true, 'message' => 'Error al ejecutar la consulta de resumen mensual']);
                    exit;
                }

                $rows = [];
                while ($r = mysqli_fetch_assoc($stmt)) {
                    $rows[] = [
                        'employee_id'              => $r['employee_id'],
                        'nombre_empleado'          => $r['nombre_empleado'],
                        'Equipo'                   => $r['Equipo'],
                        'nombre_mes'               => $r['nombre_mes'],
                        'total_dias_mes'           => (int)$r['total_dias_mes'],
                        'dias_trabajados'          => (int)$r['dias_trabajados'],
                        'dias_laborables_teoricos' => (int)$r['dias_laborables_teoricos'],
                        'dias_festivos'            => (int)$r['dias_festivos'],
                        'dias_permiso'             => (int)$r['dias_permiso'],
                        'horas_trabajadas_hhMM'    => (string)$r['horas_trabajadas_hhMM'],
                        'horas_exceso_hhMM'        => (string)$r['horas_exceso_hhMM'],
                        'horas_faltantes_hhMM'     => (string)$r['horas_faltantes_hhMM'],
                    ];
                }

                error_log("DEBUG datos_resumen_mensual - Registros obtenidos para usuario $usuarioActual: " . count($rows));

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($rows);
                exit;

            } catch (Throwable $e) {
                error_log("EX datos_resumen_mensual: " . $e->getMessage());
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => true, 'message' => 'Excepción al obtener el resumen mensual']);
                exit;
            }
        // ===== EXPORTACIÓN SpreadsheetML (compatible PHP 8.0) =====
        case 'export_excel':
            try {
                $filtros = isset($_GET['filtros']) ? json_decode($_GET['filtros'], true) : [];
                $datos   = obtenerDatosFiltrados($filtros, 'ALL');
                if (isset($datos['error']) && $datos['error']) {
                    throw new Exception($datos['message']);
                }

                if (ob_get_length()) ob_end_clean();

                $minToHHMM = function($v) {
                    $m = is_numeric($v) ? (int)round((float)$v) : 0;
                    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                };
                $boolSiNo = function($v) {
                    $s = strtolower(trim((string)$v));
                    return in_array($s, ['1','si','sí','yes','true']) ? 'Sí' : 'No';
                };
                $timeHHMM = function($v) {
                    if (!$v) return '';
                    return substr((string)$v, 0, 5);
                };
                $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');

                $filename = 'control_horario_' . date('Y-m-d_H-i-s') . '.xls';
                header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
                echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
                echo ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
                echo '<Styles>' . "\n";
                echo '<Style ss:ID="header">';
                echo '<Font ss:Bold="1"/>';
                echo '<Interior ss:Color="#EAEFF5" ss:Pattern="Solid"/>';
                echo '</Style>' . "\n";
                echo '</Styles>' . "\n";
                echo '<Worksheet ss:Name="Control Horario"><Table>' . "\n";

                $headers = [
                    'Nombre Completo','Fecha','Día Semana','Entrada','Salida',
                    'Rol','Equipo','H. Trabajados','Exceso H.','Faltantes H.',
                    'Festivo','Justificado'
                ];
                echo '<Row>';
                foreach ($headers as $h) {
                    echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>';
                }
                echo '</Row>' . "\n";

                foreach ($datos as $f) {
                    echo '<Row>';
                    echo '<Cell><Data ss:Type="String">' . $esc($f['full_name']        ?? '') . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc(substr($f['date'] ?? '', 0, 10)) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($f['dia_semana']       ?? '') . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($timeHHMM($f['clock_in']  ?? '')) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($timeHHMM($f['clock_out'] ?? '')) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($f['role_name']        ?? '') . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($f['Equipo']           ?? '') . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($minToHHMM($f['minutos_trabajados'] ?? 0)) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($minToHHMM($f['exceso_min']         ?? 0)) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($minToHHMM($f['faltantes_min']      ?? 0)) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($boolSiNo($f['Festivo']    ?? '')) . '</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">' . $esc($boolSiNo($f['Justificado'] ?? '')) . '</Data></Cell>';
                    echo '</Row>' . "\n";
                }

                echo '</Table></Worksheet></Workbook>';
                exit;

            } catch (\Throwable $e) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        // ===== Filtros (tu rama original) =====
        case 'filtros':
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(obtenerValoresFiltros(), JSON_UNESCAPED_UNICODE);
            exit;

        //==== Filtros resumen mensual ====
        case 'filtrar_resumen_mensual':
            try {
                global $usuarioActual; // Agregar esta línea
                
                $conn = conectarBD();
                $limite  = $_GET['limite'] ?? 'ALL';
                $filtros = isset($_GET['filtros']) ? json_decode($_GET['filtros'], true) : [];

                error_log("DEBUG filtrar_resumen_mensual para usuario: $usuarioActual - Filtros: " . json_encode($filtros));

                // Construir la consulta base
                $whereClause = "WHERE 1=1";
                
                if (!empty($filtros['empleados'])) {
                    $empleadosIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['empleados'])) . "'";
                    $whereClause .= " AND nombre_empleado IN ($empleadosIn)";
                }
                
                if (!empty($filtros['equipos'])) {
                    $equiposIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['equipos'])) . "'";
                    $whereClause .= " AND Equipo IN ($equiposIn)";
                }
                
                if (!empty($filtros['meses'])) {
                    $mesesIn = "'" . implode("', '", array_map('sqlsrv_escape_string', $filtros['meses'])) . "'";
                    $whereClause .= " AND nombre_mes IN ($mesesIn)";
                }

                // APLICAR FILTROS DE SEGURIDAD POR USUARIO - AGREGAR ESTA LÍNEA
                $whereClause = aplicarFiltrosSeguridadResumen($usuarioActual, $whereClause);

                // Definir el ORDER BY
                $orderClause = "ORDER BY 
                                nombre_empleado,
                                CASE LOWER(nombre_mes)
                                    WHEN 'enero' THEN 1
                                    WHEN 'febrero' THEN 2
                                    WHEN 'marzo' THEN 3
                                    WHEN 'abril' THEN 4
                                    WHEN 'mayo' THEN 5
                                    WHEN 'junio' THEN 6
                                    WHEN 'julio' THEN 7
                                    WHEN 'agosto' THEN 8
                                    WHEN 'septiembre' THEN 9
                                    WHEN 'octubre' THEN 10
                                    WHEN 'noviembre' THEN 11
                                    WHEN 'diciembre' THEN 12
                                    ELSE 13
                                END";

                // Construir consulta final dependiendo del límite
                if ($limite !== 'ALL' && $limite !== '0' && $limite !== 0 && $limite !== null) {
                    $limite = intval($limite);
                    
                    // OPCIÓN 1: Usar TOP directamente en la consulta principal
                    $sql = "
                        SELECT TOP ($limite)
                            [employee_id],
                            [nombre_empleado],
                            [Equipo],
                            [nombre_mes],
                            [total_dias_mes],
                            [dias_trabajados],
                            [dias_laborables_teoricos],
                            [dias_festivos],
                            [dias_permiso],
                            [horas_trabajadas_hhMM],
                            [horas_exceso_hhMM],
                            [horas_faltantes_hhMM]
                        FROM empleados_resumen
                        $whereClause
                        $orderClause
                    ";
                } else {
                    // Sin límite: consulta normal
                    $sql = "
                        SELECT
                            [employee_id],
                            [nombre_empleado],
                            [Equipo],
                            [nombre_mes],
                            [total_dias_mes],
                            [dias_trabajados],
                            [dias_laborables_teoricos],
                            [dias_festivos],
                            [dias_permiso],
                            [horas_trabajadas_hhMM],
                            [horas_exceso_hhMM],
                            [horas_faltantes_hhMM]
                        FROM empleados_resumen
                        $whereClause
                        $orderClause
                    ";
                }

                error_log("DEBUG filtrar_resumen_mensual - SQL final: " . $sql);

                $stmt = mysqli_query($conn, $sql);
                if ($stmt === false) {
                    $err = mysqli_error($conn);
                    error_log("SQL ERROR filtrar_resumen_mensual: " . $err);
                    throw new Exception("Error en consulta SQL: " . $err);
                }

                $rows = [];
                while ($r = mysqli_fetch_assoc($stmt)) {
                    $rows[] = [
                        'employee_id'              => $r['employee_id'],
                        'nombre_empleado'          => $r['nombre_empleado'],
                        'Equipo'                   => $r['Equipo'],
                        'nombre_mes'               => $r['nombre_mes'],
                        'total_dias_mes'           => (int)$r['total_dias_mes'],
                        'dias_trabajados'          => (int)$r['dias_trabajados'],
                        'dias_laborables_teoricos' => (int)$r['dias_laborables_teoricos'],
                        'dias_festivos'            => (int)$r['dias_festivos'],
                        'dias_permiso'             => (int)$r['dias_permiso'],
                        'horas_trabajadas_hhMM'    => (string)$r['horas_trabajadas_hhMM'],
                        'horas_exceso_hhMM'        => (string)$r['horas_exceso_hhMM'],
                        'horas_faltantes_hhMM'     => (string)$r['horas_faltantes_hhMM'],
                    ];
                }

                error_log("DEBUG filtrar_resumen_mensual - Registros encontrados: " . count($rows));

                sqlsrv_free_stmt($stmt);
                sqlsrv_close($conn);

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($rows);
                exit;

            } catch (Throwable $e) {
                error_log("EX filtrar_resumen_mensual: " . $e->getMessage());
                error_log("EX filtrar_resumen_mensual - Stack trace: " . $e->getTraceAsString());
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => true, 'message' => 'Excepción al filtrar el resumen mensual: ' . $e->getMessage()]);
                exit;
            }
        // ===== DEFAULT: devuelve datos en JSON =====
        default:
            $limite = $_GET['limite'] ?? 1000;
            $filtros = isset($_GET['filtros']) ? json_decode($_GET['filtros'], true) : [];
            $datos = obtenerDatosFiltrados($filtros, $limite);

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($datos, JSON_UNESCAPED_UNICODE);
            exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Horario - Empleados</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Personalizado -->
    <link rel="stylesheet" type="text/css" href="css/global.css">
    <link rel="stylesheet" type="text/css" href="css/stickyheaders.css">
    <link rel="stylesheet" type="text/css" href="css/user-menu.css">
    <link rel="stylesheet" type="text/css" href="css/pageloading.css">
    <!-- NUEVO: CSS para botones de vista -->
    <link rel="stylesheet" type="text/css" href="css/view-buttons.css">
    <!-- NUEVO: CSS de totales -->
    <link rel="stylesheet" type="text/css" href="css/totals-row.css">
    <!-- NUEVO: CSS de checkboxes -->
    <link rel="stylesheet" type="text/css" href="css/checkboxes.css">
        
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- Script de filtros -->
    <script src="js/Filtros.js?v=<?= filemtime(__DIR__.'/js/Filtros.js') ?>"></script>
    <!-- Script de menu -->
    <script src="js/user-menu.js?v=<?= filemtime(__DIR__.'/js/user-menu.js') ?>"></script>
    <!-- NUEVO: Script del gestor de vistas -->
    <script src="js/ViewManager.js?v=<?= filemtime(__DIR__.'/js/ViewManager.js') ?>"></script>
    <!-- NUEVO: Script de totales AL FINAL -->
    <script src="js/totals-row.js?v=<?= filemtime(__DIR__.'/js/totals-row.js') ?>"></script>

</head>
<body>

    <div id="page-loading" class="page-loading" aria-hidden="true">
        <div class="loader-box">
            <div class="spinner"></div>
            <div class="text" id="page-loading-text">Procesando…</div>
        </div>
    </div>

    <div class="container blur-target">
        <!-- Header con título y menú de usuario -->
        <div class="header-container">
            <h1>Control Horario</h1>

            <!-- Botones admin -->
            <div style="display:flex;gap:8px;align-items:center;">
                <button id="btn-solicitudes" onclick="abrirModalSolicitudes()" style="position:relative;background:#587587;color:white;border:none;border-radius:8px;padding:8px 14px;font-size:0.82rem;cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-inbox"></i> Solicitudes
                    <span id="badge-solicitudes" style="display:none;position:absolute;top:-6px;right:-6px;background:#dc3545;color:white;border-radius:50%;width:18px;height:18px;font-size:0.65rem;display:none;align-items:center;justify-content:center;font-weight:700;"></span>
                </button>
                <button onclick="abrirModalUsuarios()" style="background:#587587;color:white;border:none;border-radius:8px;padding:8px 14px;font-size:0.82rem;cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-users"></i> Usuarios
                </button>
            </div>

            <!-- Menú de usuario -->
            <div class="user-menu-container">
                <button class="user-menu-trigger" id="user-menu-trigger" title="Menú de usuario"></button>
                
                <div class="user-menu-dropdown" id="user-menu-dropdown">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['user'] ?? 'Usuario'); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_group'] ?? 'Usuario'); ?></div>
                    </div>
                    
                    <div class="menu-items">
                        <!-- Botón Exportar a Excel -->
                        <button id="export-excel-btn" class="menu-item info"
                                data-export-url="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <span class="menu-item-icon"><i class="fas fa-file-excel"></i></span>
                        <span class="menu-item-text">Exportar a Excel</span>
                        </button>
                        
                        <button class="menu-item warning" id="update-data-btn">
                            <span class="menu-item-icon"><i class="fas fa-sync-alt"></i></span>
                            <span class="menu-item-text">Actualizar Datos</span>
                        </button>
                        
                        <div class="menu-separator"></div>
                        
                        <button class="menu-item danger" id="logout-btn">
                            <span class="menu-item-icon"><i class="fas fa-sign-out-alt"></i></span>
                            <span class="menu-item-text">Cerrar Sesión</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overlay para cerrar menú -->
        <div class="menu-overlay" id="menu-overlay"></div>
        
        <div class="controls">
            <div class="controls-row filters-row">
                <!-- Empleados -->
                <div class="filter-group">
                    <label>Empleados:</label>
                    <div class="multi-dd" id="dd-emps">
                        <button type="button" class="btn-dd" id="btn-dd-emps">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-emps">
                            <div class="dd-tools">
                                <input type="text" id="search-emps" placeholder="Buscar empleado...">
                                <div class="spacer"></div>
                                <button type="button" class="mini" id="all-emps">Todos</button>
                                <button type="button" class="mini" id="none-emps">Ninguno</button>
                            </div>
                            <!-- AQUÍ añades el contenedor de tags -->
                            <div class="filter-tags" id="tags-empleados"></div>
                            <div class="dd-list" id="list-emps"></div>

                        </div>
                    </div>
                </div>

                <!-- Equipos -->
                <div class="filter-group">
                    <label>Equipos:</label>
                    <div class="multi-dd" id="dd-teams">
                        <button type="button" class="btn-dd" id="btn-dd-teams">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-teams">
                            <div class="dd-tools">
                                <input type="text" id="search-teams" placeholder="Buscar equipo...">
                                <div class="spacer"></div>
                                <button type="button" class="mini" id="all-teams">Todos</button>
                                <button type="button" class="mini" id="none-teams">Ninguno</button>
                            </div>
                            <!-- Para equipos -->
                            <div class="filter-tags" id="tags-equipos"></div> 
                            <div class="dd-list" id="list-teams"></div>

                        </div>
                    </div>
                </div>

                <!-- Meses -->
                <div class="filter-group">
                    <label>Meses:</label>
                    <div class="multi-dd" id="dd-mes">
                        <button type="button" class="btn-dd" id="btn-dd-mes">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-mes">
                            <div class="dd-tools">
                                <input type="text" id="search-mes" placeholder="Buscar mes...">
                                <div class="spacer"></div>
                                <button type="button" class="mini" id="all-mes">Todos</button>
                                <button type="button" class="mini" id="none-mes">Ninguno</button>
                            </div>
                            <!-- Para meses -->
                            <div class="filter-tags" id="tags-meses"></div>
                            <div class="dd-list" id="list-mes"></div>

                        </div>
                    </div>
                </div>

                <!-- Días de la Semana -->
                <div class="filter-group">
                    <label>Días:</label>
                    <div class="multi-dd" id="dd-dias">
                        <button type="button" class="btn-dd" id="btn-dd-dias">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-dias">
                            <div class="dd-tools">
                                <input type="text" id="search-dias" placeholder="Buscar día...">
                                <div class="spacer"></div>
                                <button type="button" class="mini" id="all-dias">Todos</button>
                                <button type="button" class="mini" id="none-dias">Ninguno</button>
                            </div>
                            <div class="dd-list" id="list-dias"></div>
                        </div>
                    </div>
                </div>

                <!-- Festivos -->
                <div class="filter-group">
                    <label>Festivos:</label>
                    <div class="multi-dd" id="dd-festivos">
                        <button type="button" class="btn-dd" id="btn-dd-festivos">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-festivos">
                            <div class="dd-list" id="list-festivos"></div>
                        </div>
                    </div>
                </div>

                <!-- Justificados -->
                <div class="filter-group">
                    <label>Justificados:</label>
                    <div class="multi-dd" id="dd-justificados">
                        <button type="button" class="btn-dd" id="btn-dd-justificados">Seleccionar (0)</button>
                        <div class="dd-panel" id="panel-dd-justificados">
                            <div class="dd-list" id="list-justificados"></div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <button onclick="limpiarFiltros()" class="btn-secondary" type="button">Limpiar Filtros</button>
                <button id="btn-aplicar" class="btn-primary" type="button">Aplicar Filtros</button>
            </div>
        </div>
        
        <div id="error-container" style="display: none;"></div>
        
        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table id="tabla-empleados" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th class="col-name">Nombre Completo</th>
                            <th class="col-date-small">Fecha</th>
                            <th class="col-status">Día Semana</th>
                            <th class="col-time-small">Entrada</th>
                            <th class="col-time-small">Salida</th>
                            <th class="col-name">Rol</th>
                            <th class="col-name">Equipo</th>
                            <th class="col-time-small">H. Trabajados</th>
                            <th class="col-time-small">Exceso H.</th>
                            <th class="col-time-small">Faltantes H.</th>
                            <th class="col-status">Festivo</th>
                            <th class="col-status">Justificado</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Controles de paginación e información fuera del scroll -->
        <div class="table-controls-bottom">
            <div class="bottom-left-controls">
                <div class="filter-group">
                    <label>Límite de registros:</label>
                    <select id="limite-registros" onchange="cargarDatosIniciales()">
                        <option value="500">500</option>
                        <option value="1000" selected>1000</option>
                        <option value="2000">2000</option>
                        <option value="5000">5000</option>
                        <option value="10000">10000</option>
                        <option value="ALL">TOTAL</option>
                    </select>
                </div>
                <div id="table-info"></div>
            </div>
            <div id="table-pagination"></div>
        </div>
    </div>

<!-- =================== MODAL USUARIOS =================== -->
<div id="modal-usuarios" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;overflow-y:auto;">
  <div style="background:white;border-radius:16px;max-width:820px;margin:40px auto;padding:32px;position:relative;">
    <button onclick="cerrarModal('modal-usuarios')" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;">&times;</button>
    <h2 style="color:#587587;margin-bottom:24px;font-size:1.2rem;"><i class="fas fa-users"></i> Gestión de Usuarios</h2>

    <!-- Formulario crear usuario -->
    <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin-bottom:24px;">
      <h3 style="font-size:0.9rem;color:#555;margin-bottom:14px;text-transform:uppercase;letter-spacing:0.05em;">Crear nuevo usuario</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;" id="form-crear-usuario">
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Usuario *</label><input id="nu-username" type="text" placeholder="nombre.usuario" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;"></div>
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Nombre completo *</label><input id="nu-nombre" type="text" placeholder="Nombre Apellido" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;"></div>
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Contraseña *</label><input id="nu-password" type="password" placeholder="Mín. 6 caracteres" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;"></div>
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Email</label><input id="nu-email" type="email" placeholder="correo@empresa.com" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;"></div>
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Equipo</label><input id="nu-equipo" type="text" placeholder="Departamento" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;"></div>
        <div><label style="font-size:0.75rem;color:#666;display:block;margin-bottom:4px;">Rol</label>
          <select id="nu-role" style="width:100%;padding:8px 10px;border:2px solid #ddd;border-radius:6px;font-size:0.88rem;">
            <option value="empleado">Empleado</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <button onclick="crearUsuario()" style="margin-top:14px;background:#587587;color:white;border:none;border-radius:8px;padding:9px 20px;font-size:0.88rem;cursor:pointer;font-weight:600;">
        <i class="fas fa-plus"></i> Crear Usuario
      </button>
      <span id="msg-crear" style="margin-left:12px;font-size:0.82rem;"></span>
    </div>

    <!-- Lista de usuarios -->
    <div id="tabla-usuarios-container">
      <p style="color:#aaa;font-size:0.88rem;">Cargando usuarios...</p>
    </div>
  </div>
</div>

<!-- =================== MODAL SOLICITUDES =================== -->
<div id="modal-solicitudes" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;overflow-y:auto;">
  <div style="background:white;border-radius:16px;max-width:900px;margin:40px auto;padding:32px;position:relative;">
    <button onclick="cerrarModal('modal-solicitudes')" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;">&times;</button>
    <h2 style="color:#587587;margin-bottom:20px;font-size:1.2rem;"><i class="fas fa-inbox"></i> Solicitudes de Empleados</h2>

    <div style="display:flex;gap:8px;margin-bottom:20px;">
      <button class="sol-tab active" onclick="cargarSolicitudes('pendiente',this)" style="padding:7px 16px;border-radius:6px;border:2px solid #587587;background:#587587;color:white;cursor:pointer;font-size:0.82rem;font-weight:600;">Pendientes</button>
      <button class="sol-tab" onclick="cargarSolicitudes('aprobada',this)" style="padding:7px 16px;border-radius:6px;border:2px solid #ddd;background:white;color:#555;cursor:pointer;font-size:0.82rem;">Aprobadas</button>
      <button class="sol-tab" onclick="cargarSolicitudes('rechazada',this)" style="padding:7px 16px;border-radius:6px;border:2px solid #ddd;background:white;color:#555;cursor:pointer;font-size:0.82rem;">Rechazadas</button>
      <button class="sol-tab" onclick="cargarSolicitudes('todas',this)" style="padding:7px 16px;border-radius:6px;border:2px solid #ddd;background:white;color:#555;cursor:pointer;font-size:0.82rem;">Todas</button>
    </div>

    <div id="lista-solicitudes-admin"><p style="color:#aaa;font-size:0.88rem;">Cargando...</p></div>
  </div>
</div>

<script>
// ============ MODALES ============
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarModal('modal-usuarios'); cerrarModal('modal-solicitudes'); } });

// Cerrar al clic en overlay
['modal-usuarios','modal-solicitudes'].forEach(id => {
  document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) cerrarModal(id); });
});

// ============ BADGE SOLICITUDES PENDIENTES ============
async function actualizarBadgeSolicitudes() {
  try {
    const r = await fetch('api/solicitudes_admin.php?accion=pendientes_count');
    const d = await r.json();
    const badge = document.getElementById('badge-solicitudes');
    if (d.ok && d.count > 0) {
      badge.textContent = d.count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  } catch(e) {}
}
actualizarBadgeSolicitudes();

// ============ MODAL USUARIOS ============
function abrirModalUsuarios() {
  document.getElementById('modal-usuarios').style.display = 'block';
  cargarUsuarios();
}

async function cargarUsuarios() {
  const r = await fetch('api/usuarios_admin.php?accion=listar');
  const d = await r.json();
  if (!d.ok) return;
  const cont = document.getElementById('tabla-usuarios-container');
  if (!d.usuarios.length) { cont.innerHTML = '<p style="color:#aaa;font-size:0.88rem;">No hay usuarios</p>'; return; }

  const roleColor = { admin: '#587587', empleado: '#28a745' };
  const rows = d.usuarios.map(u => `
    <tr style="border-bottom:1px solid #f0f4f7;">
      <td style="padding:10px 8px;font-size:0.88rem;font-weight:600;">${esc(u.nombre_completo)}</td>
      <td style="padding:10px 8px;font-size:0.82rem;color:#666;">@${esc(u.username)}</td>
      <td style="padding:10px 8px;font-size:0.82rem;color:#666;">${esc(u.equipo||'—')}</td>
      <td style="padding:10px 8px;"><span style="background:${roleColor[u.role]||'#999'};color:white;padding:2px 10px;border-radius:10px;font-size:0.72rem;font-weight:700;">${u.role}</span></td>
      <td style="padding:10px 8px;"><span style="color:${u.activo?'#28a745':'#dc3545'};font-size:0.82rem;font-weight:600;">${u.activo?'Activo':'Inactivo'}</span></td>
      <td style="padding:10px 8px;">
        <button onclick="editarUsuario(${u.id},'${esc(u.nombre_completo)}','${esc(u.email||'')}','${esc(u.equipo||'')}','${u.role}',${u.activo})"
          style="background:#587587;color:white;border:none;border-radius:5px;padding:4px 10px;font-size:0.75rem;cursor:pointer;margin-right:4px;">
          <i class="fas fa-edit"></i>
        </button>
        ${u.username !== 'admin' ? `<button onclick="eliminarUsuario(${u.id},'${esc(u.nombre_completo)}')"
          style="background:#dc3545;color:white;border:none;border-radius:5px;padding:4px 10px;font-size:0.75rem;cursor:pointer;">
          <i class="fas fa-user-slash"></i></button>` : ''}
      </td>
    </tr>`).join('');

  cont.innerHTML = `<table style="width:100%;border-collapse:collapse;">
    <thead><tr style="background:#f8f9fa;">
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;text-transform:uppercase;">Nombre</th>
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;">Usuario</th>
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;">Equipo</th>
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;">Rol</th>
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;">Estado</th>
      <th style="padding:8px;font-size:0.78rem;color:#666;text-align:left;">Acciones</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>`;
}

async function crearUsuario() {
  const data = new FormData();
  data.append('accion',   'crear');
  data.append('username', document.getElementById('nu-username').value.trim());
  data.append('nombre',   document.getElementById('nu-nombre').value.trim());
  data.append('password', document.getElementById('nu-password').value);
  data.append('email',    document.getElementById('nu-email').value.trim());
  data.append('equipo',   document.getElementById('nu-equipo').value.trim());
  data.append('role',     document.getElementById('nu-role').value);

  const msg = document.getElementById('msg-crear');
  const r = await fetch('api/usuarios_admin.php', { method:'POST', body:data });
  const d = await r.json();
  if (d.ok) {
    msg.style.color = '#28a745'; msg.textContent = '✅ Usuario creado';
    document.querySelectorAll('#form-crear-usuario input, #form-crear-usuario select').forEach(el => el.value = '');
    await cargarUsuarios();
  } else {
    msg.style.color = '#dc3545'; msg.textContent = '❌ ' + d.error;
  }
  setTimeout(() => msg.textContent = '', 3000);
}

function editarUsuario(id, nombre, email, equipo, role, activo) {
  const nuevoNombre = prompt('Nombre completo:', nombre);
  if (nuevoNombre === null) return;
  const nuevoEquipo = prompt('Equipo:', equipo);
  if (nuevoEquipo === null) return;
  const nuevoRole = prompt('Rol (admin/empleado):', role);
  if (nuevoRole === null || !['admin','empleado'].includes(nuevoRole)) { alert('Rol inválido'); return; }
  const nuevoActivo = confirm('¿Usuario activo?') ? 1 : 0;

  const data = new FormData();
  data.append('accion', 'editar');
  data.append('id', id);
  data.append('nombre', nuevoNombre);
  data.append('email', email);
  data.append('equipo', nuevoEquipo);
  data.append('role', nuevoRole);
  data.append('activo', nuevoActivo);

  fetch('api/usuarios_admin.php', { method:'POST', body:data })
    .then(r => r.json()).then(d => {
      if (d.ok) cargarUsuarios();
      else alert('Error: ' + d.error);
    });
}

function eliminarUsuario(id, nombre) {
  if (!confirm(`¿Desactivar al usuario "${nombre}"?`)) return;
  const data = new FormData();
  data.append('accion', 'eliminar');
  data.append('id', id);
  fetch('api/usuarios_admin.php', { method:'POST', body:data })
    .then(r => r.json()).then(d => {
      if (d.ok) cargarUsuarios();
      else alert('Error: ' + d.error);
    });
}

// ============ MODAL SOLICITUDES ============
function abrirModalSolicitudes() {
  document.getElementById('modal-solicitudes').style.display = 'block';
  cargarSolicitudes('pendiente', document.querySelector('.sol-tab.active'));
}

async function cargarSolicitudes(estado, btn) {
  document.querySelectorAll('.sol-tab').forEach(b => {
    b.style.background = 'white'; b.style.color = '#555'; b.style.borderColor = '#ddd'; b.classList.remove('active');
  });
  if (btn) { btn.style.background = '#587587'; btn.style.color = 'white'; btn.style.borderColor = '#587587'; btn.classList.add('active'); }

  const r = await fetch(`api/solicitudes_admin.php?accion=listar&estado=${estado}`);
  const d = await r.json();
  const cont = document.getElementById('lista-solicitudes-admin');
  if (!d.ok || !d.solicitudes.length) {
    cont.innerHTML = '<p style="color:#aaa;font-size:0.88rem;padding:20px 0;">No hay solicitudes en este estado</p>';
    return;
  }

  const iconos   = { vacaciones:'🏖️', justificacion:'📄', libre_disposicion:'📅' };
  const etiq     = { vacaciones:'Vacaciones', justificacion:'Justificación', libre_disposicion:'Libre Disposición' };
  const estColor = { pendiente:'#856404,#fff3cd', aprobada:'#155724,#d4edda', rechazada:'#721c24,#f8d7da' };

  cont.innerHTML = d.solicitudes.map(s => {
    const [tc, bg] = (estColor[s.estado] || '').split(',');
    const fecha = s.fecha_fin ? `${s.fecha_inicio} → ${s.fecha_fin}` : s.fecha_inicio;
    const acciones = s.estado === 'pendiente' ? `
      <div style="display:flex;gap:6px;margin-top:8px;">
        <input type="text" placeholder="Nota (opcional)" id="nota-${s.id}" style="flex:1;padding:5px 8px;border:1px solid #ddd;border-radius:5px;font-size:0.78rem;">
        <button onclick="resolverSolicitud(${s.id},'aprobada')" style="background:#28a745;color:white;border:none;border-radius:5px;padding:5px 12px;font-size:0.78rem;cursor:pointer;">✅ Aprobar</button>
        <button onclick="resolverSolicitud(${s.id},'rechazada')" style="background:#dc3545;color:white;border:none;border-radius:5px;padding:5px 12px;font-size:0.78rem;cursor:pointer;">❌ Rechazar</button>
      </div>` : s.admin_nota ? `<div style="font-size:0.78rem;color:#888;margin-top:4px;">Nota: ${esc(s.admin_nota)}</div>` : '';

    return `<div style="padding:14px;border:1px solid #e9ecef;border-radius:8px;margin-bottom:10px;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:1.3rem;">${iconos[s.tipo]||'📋'}</span>
          <div>
            <div style="font-size:0.88rem;font-weight:600;">${esc(s.nombre_completo)} <span style="color:#888;font-weight:400;">(${esc(s.equipo||'—')})</span></div>
            <div style="font-size:0.8rem;color:#666;">${etiq[s.tipo]||s.tipo} · ${fecha}</div>
            ${s.motivo ? `<div style="font-size:0.78rem;color:#888;margin-top:2px;">${esc(s.motivo)}</div>` : ''}
            ${s.documento ? `<a href="api/descargar_documento.php?f=${encodeURIComponent(s.documento)}" target="_blank" style="display:inline-flex;align-items:center;gap:5px;margin-top:4px;font-size:0.78rem;color:#587587;text-decoration:none;font-weight:600;"><i class="fas fa-paperclip"></i> Ver documento adjunto</a>` : ''}
          </div>
        </div>
        <span style="color:${tc};background:${bg};padding:3px 12px;border-radius:12px;font-size:0.72rem;font-weight:700;text-transform:uppercase;">${s.estado}</span>
      </div>
      ${acciones}
    </div>`;
  }).join('');
}

async function resolverSolicitud(id, decision) {
  const nota = document.getElementById('nota-'+id)?.value || '';
  const data = new FormData();
  data.append('accion', 'resolver');
  data.append('id', id);
  data.append('decision', decision);
  data.append('nota', nota);
  const r = await fetch('api/solicitudes_admin.php', { method:'POST', body:data });
  const d = await r.json();
  if (d.ok) {
    await actualizarBadgeSolicitudes();
    await cargarSolicitudes(document.querySelector('.sol-tab.active')?.dataset?.estado || 'pendiente', null);
    // recargar con tab activo
    const tab = document.querySelector('.sol-tab.active');
    cargarSolicitudes('pendiente', document.querySelector('.sol-tab'));
  } else alert('Error: ' + d.error);
}

// Utilidad escape HTML
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

</script>

</body>
</html>