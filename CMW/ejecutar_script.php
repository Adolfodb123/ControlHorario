<?php
require_once '../auth_check.php'; // Verificar autenticación

header('Content-Type: application/json; charset=UTF-8');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Ruta del script Python
$pythonExecutable = 'C:\Users\soporteit\AppData\Local\Programs\Python\Python313\python.exe';
$scriptPath = 'C:\Users\soporteit\Desktop\Scripts\SubidaFactorialSQL\Automatizacion.py';

// Verificar que los archivos existan
if (!file_exists($scriptPath)) {
    echo json_encode(['error' => 'Script no encontrado']);
    exit;
}

try {
    // Ejecutar el script en segundo plano
    $command = '"' . $pythonExecutable . '" "' . $scriptPath . '" 2>&1';
    $output = [];
    $returnCode = 0;
    
    // Ejecutar comando
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Script ejecutado correctamente',
            'output' => implode("\n", $output)
        ]);
    } else {
        echo json_encode([
            'error' => 'Error al ejecutar script',
            'output' => implode("\n", $output),
            'code' => $returnCode
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>