<?php
//ESTO ES Index.php REFACTORIZADO - JavaScript del menú movido a archivo separado

// Index.php mi pagina principal
require_once '../auth_check.php'; 

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
    $conn->set_charset("utf8");
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
            clock_in,
            clock_out,
            '' AS role_name,
            Equipo,
            CASE WHEN DAYOFWEEK(date) IN (1,7) THEN 0 ELSE COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60) END AS minutos_trabajados,
            CASE WHEN DAYOFWEEK(date) IN (1,7) THEN 0 ELSE GREATEST(0, COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60) - 480) END AS exceso_min,
            CASE WHEN DAYOFWEEK(date) IN (1,7) THEN 0 ELSE GREATEST(0, 480 - COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 60, COALESCE(horas, 0) * 60)) END AS faltantes_min,
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
        // ===== EXPORTACIÓN MÍNIMA DESDE TUS DATOS (todo TEXTO) =====
        case 'export_excel':
            try {
                // FORZAR 'ALL' para exportación completa
                $limite  = 'ALL';  // En lugar de $_GET['limite'] ?? 'ALL';
                $filtros = isset($_GET['filtros']) ? json_decode($_GET['filtros'], true) : [];

                // Obtener datos de vista general
                $datosGeneral = obtenerDatosFiltrados($filtros, $limite);
                if (isset($datosGeneral['error']) && $datosGeneral['error']) {
                    throw new Exception($datosGeneral['message']);
                }

                // Obtener datos de resumen mensual
                $datosResumen = obtenerDatosResumenMensual($filtros, $limite);
                if (isset($datosResumen['error']) && $datosResumen['error']) {
                    throw new Exception($datosResumen['message']);
                }

                if (ob_get_length()) { ob_end_clean(); }
                require_once dirname(__DIR__) . '/vendor/autoload.php';

                // Crear libro con dos hojas
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

                // ===== HOJA 1: VISTA GENERAL =====
                $sheetGeneral = $spreadsheet->getActiveSheet();
                $sheetGeneral->setTitle('Vista General');

                // Definición de columnas para vista general - NUEVO ORDEN
                $headersGeneral = [
                    'Nombre Completo' => 'full_name',
                    'Fecha'           => 'date',
                    'Día Semana'      => 'dia_semana',
                    'Entrada'         => 'clock_in',
                    'Salida'          => 'clock_out',
                    'Rol'             => 'role_name',
                    'Equipo'          => 'Equipo',
                    'H. Trabajados'   => 'minutos_trabajados',  // Posición 7
                    'Exceso H.'       => 'exceso_min',         // Posición 8
                    'Faltantes H.'    => 'faltantes_min',      // Posición 9
                    'Festivo'         => 'Festivo',            // Posición 10
                    'Justificado'     => 'Justificado',        // Posición 11
                ];

                // Helpers compartidos
                $toMinutes = function($v) {
                    if ($v === null || $v === '') return 0.0;
                    if (is_numeric($v)) return (float)$v;
                    $s = trim((string)$v);
                    if (preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $s, $m)) {
                        $h = (int)$m[1];
                        $m2 = (int)$m[2];
                        $sec = isset($m[3]) ? (int)$m[3] : 0;
                        return $h * 60 + $m2 + ($sec / 60.0);
                    }
                    return 0.0;
                };
                $minToExcel = function($mins) {
                    $m = is_numeric($mins) ? (float)$mins : 0.0;
                    return $m / 1440.0;
                };
                $boolSiNo = function($v) {
                    $s = strtolower(trim((string)$v));
                    return in_array($s, ['1','si','sí','yes','true','y','t']) ? 'Sí' : 'No';
                };
                $hhmmToExcel = function($v) {
                    if (empty($v)) return 0.0;
                    $s = trim((string)$v);
                    if (preg_match('/^(\d+):(\d{2})$/', $s, $m)) {
                        $h = (int)$m[1];
                        $min = (int)$m[2];
                        return ($h * 60 + $min) / 1440.0;
                    }
                    return 0.0;
                };

                // Cabeceras vista general
                $c = 1;
                foreach (array_keys($headersGeneral) as $h) {
                    $sheetGeneral->setCellValueByColumnAndRow($c++, 1, $h);
                }

                // Datos vista general
                $r = 2;
                // Y luego en la parte donde llenas los datos:
                foreach ($datosGeneral as $fila) {
                    $c = 1;
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Nombre Completo']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    
                    $dateStr = $fila[$headersGeneral['Fecha']] ?? '';
                    if ($dateStr) {
                        $dt = new \DateTime(substr((string)$dateStr, 0, 10));
                        $sheetGeneral->setCellValueByColumnAndRow($c, $r, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt));
                    } else {
                        $sheetGeneral->setCellValueByColumnAndRow($c, $r, null);
                    }
                    $c++;

                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Día Semana']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Entrada']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Salida']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Rol']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersGeneral['Equipo']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    
                    // HORAS AGRUPADAS: Trabajados, Exceso, Faltantes
                    $sheetGeneral->setCellValueByColumnAndRow($c++, $r, $minToExcel($toMinutes($fila[$headersGeneral['H. Trabajados']] ?? 0)));
                    $sheetGeneral->setCellValueByColumnAndRow($c++, $r, $minToExcel($toMinutes($fila[$headersGeneral['Exceso H.']] ?? 0)));
                    $sheetGeneral->setCellValueByColumnAndRow($c++, $r, $minToExcel($toMinutes($fila[$headersGeneral['Faltantes H.']] ?? 0)));
                    
                    // Y al final: Festivo y Justificado
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, $boolSiNo($fila[$headersGeneral['Festivo']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetGeneral->setCellValueExplicitByColumnAndRow($c++, $r, $boolSiNo($fila[$headersGeneral['Justificado']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $r++;
                }


                // También actualizar el formateo de columnas:
                $lastRowGeneral = max($r - 1, 2);
                $lastColGeneralLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headersGeneral));

                $sheetGeneral->getStyle("B2:B{$lastRowGeneral}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                // HORAS TRABAJADAS, EXCESO Y FALTANTES AHORA EN COLUMNAS H, I, J
                $sheetGeneral->getStyle("H2:H{$lastRowGeneral}")->getNumberFormat()->setFormatCode('[h]:mm'); // H. Trabajados
                $sheetGeneral->getStyle("I2:I{$lastRowGeneral}")->getNumberFormat()->setFormatCode('[h]:mm'); // Exceso
                $sheetGeneral->getStyle("J2:J{$lastRowGeneral}")->getNumberFormat()->setFormatCode('[h]:mm'); // Faltantes
                // FESTIVO Y JUSTIFICADO AHORA EN K Y L
                $sheetGeneral->getStyle("K2:K{$lastRowGeneral}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheetGeneral->getStyle("L2:L{$lastRowGeneral}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                // Cabeceras vista general
                $sheetGeneral->getStyle("A1:{$lastColGeneralLetter}1")->getFont()->setBold(true);
                $sheetGeneral->getStyle("A1:{$lastColGeneralLetter}1")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFEAEFF5');

                // Autosize columnas vista general
                for ($i = 1; $i <= count($headersGeneral); $i++) {
                    $sheetGeneral->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }

                $sheetGeneral->freezePane('A2');
                $sheetGeneral->setAutoFilter("A1:{$lastColGeneralLetter}{$lastRowGeneral}");

                // ===== HOJA 2: RESUMEN MENSUAL =====
                $sheetResumen = $spreadsheet->createSheet();
                $sheetResumen->setTitle('Resumen Mensual');

                // Definición de columnas para resumen mensual
                $headersResumen = [
                    'Empleado'             => 'nombre_empleado',
                    'Equipo'               => 'Equipo',
                    'Mes'                  => 'nombre_mes',
                    'Total días'           => 'total_dias_mes',
                    'Días trabajados'      => 'dias_trabajados',
                    'Laborables teóricos'  => 'dias_laborables_teoricos',
                    'Festivos'            => 'dias_festivos',
                    'Permisos'            => 'dias_permiso',
                    'H. Trabajadas'       => 'horas_trabajadas_hhMM',
                    'H. Exceso'           => 'horas_exceso_hhMM',
                    'H. Faltantes'        => 'horas_faltantes_hhMM',
                ];

                // Cabeceras resumen mensual
                $c = 1;
                foreach (array_keys($headersResumen) as $h) {
                    $sheetResumen->setCellValueByColumnAndRow($c++, 1, $h);
                }

                // Datos resumen mensual
                $r = 2;
                foreach ($datosResumen as $fila) {
                    $c = 1;
                    $sheetResumen->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersResumen['Empleado']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetResumen->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersResumen['Equipo']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetResumen->setCellValueExplicitByColumnAndRow($c++, $r, (string)($fila[$headersResumen['Mes']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, (int)($fila[$headersResumen['Total días']] ?? 0));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, (int)($fila[$headersResumen['Días trabajados']] ?? 0));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, (int)($fila[$headersResumen['Laborables teóricos']] ?? 0));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, (int)($fila[$headersResumen['Festivos']] ?? 0));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, (int)($fila[$headersResumen['Permisos']] ?? 0));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, $hhmmToExcel($fila[$headersResumen['H. Trabajadas']] ?? ''));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, $hhmmToExcel($fila[$headersResumen['H. Exceso']] ?? ''));
                    $sheetResumen->setCellValueByColumnAndRow($c++, $r, $hhmmToExcel($fila[$headersResumen['H. Faltantes']] ?? ''));
                    $r++;
                }

                // Formatear hoja resumen
                $lastRowResumen = max($r - 1, 2);
                $lastColResumenLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headersResumen));
                
                $sheetResumen->getStyle("I2:I{$lastRowResumen}")->getNumberFormat()->setFormatCode('[h]:mm');
                $sheetResumen->getStyle("J2:J{$lastRowResumen}")->getNumberFormat()->setFormatCode('[h]:mm');
                $sheetResumen->getStyle("K2:K{$lastRowResumen}")->getNumberFormat()->setFormatCode('[h]:mm');
                
                // Cabeceras resumen
                $sheetResumen->getStyle("A1:{$lastColResumenLetter}1")->getFont()->setBold(true);
                $sheetResumen->getStyle("A1:{$lastColResumenLetter}1")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8F4FD');

                // Autosize columnas resumen
                for ($i = 1; $i <= count($headersResumen); $i++) {
                    $sheetResumen->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }

                $sheetResumen->freezePane('A2');
                $sheetResumen->setAutoFilter("A1:{$lastColResumenLetter}{$lastRowResumen}");

                // Activar la primera hoja por defecto
                $spreadsheet->setActiveSheetIndex(0);

                // Enviar al navegador
                $filename = 'control_horario_completo_' . date('Y-m-d_H-i-s') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="'.$filename.'"');
                header('Cache-Control: max-age=0');

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
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
    <script src="js/Filtros.js"></script>
    <!-- Script de menu -->
    <script src="js/user-menu.js"></script>
    <!-- NUEVO: Script del gestor de vistas -->
    <script src="js/ViewManager.js"></script>
    <!-- NUEVO: Script de totales AL FINAL -->
    <script src="js/totals-row.js"></script>

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
</body>
</html>