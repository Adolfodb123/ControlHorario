<?php
// user-permissions.php - Configuración de permisos por usuario

// Configuración de permisos por usuario

$USER_PERMISSIONS = require __DIR__ . '/PermisosVisualWeb.php';

/**
 * Obtiene los permisos de un usuario específico
 * 
 * @param string $nombreUsuario - Nombre del usuario
 * @return array|null - Array con permisos o null si no tiene restricciones
 */
function obtenerPermisosUsuario($nombreUsuario) {
    global $USER_PERMISSIONS;
    
    return $USER_PERMISSIONS[$nombreUsuario] ?? null;
}

/**
 * Verifica si un usuario tiene restricciones
 * 
 * @param string $nombreUsuario - Nombre del usuario
 * @return bool - True si tiene restricciones, false si acceso total
 */
function usuarioTieneRestricciones($nombreUsuario) {
    $permisos = obtenerPermisosUsuario($nombreUsuario);
    
    if ($permisos === null) {
        return false; // Sin restricciones si no está en la lista
    }
    
    return $permisos['tipo'] !== 'sin_restriccion';
}

/**
 * Aplica filtros de seguridad a una consulta SQL según permisos del usuario
 * 
 * @param string $nombreUsuario - Nombre del usuario actual
 * @param array $whereClausesExistentes - WHERE clauses ya aplicados
 * @return array - WHERE clauses modificados con restricciones de seguridad
 */
function aplicarFiltrosSeguridad($nombreUsuario, $whereClausesExistentes = []) {
    $permisos = obtenerPermisosUsuario($nombreUsuario);
    
    // Si no tiene restricciones, devolver los filtros existentes sin modificar
    if ($permisos === null || $permisos['tipo'] === 'sin_restriccion') {
        return $whereClausesExistentes;
    }
    
    // Aplicar restricción según el tipo
    switch ($permisos['tipo']) {
        case 'equipo':
            $equiposPermitidos = array_map('addslashes', $permisos['valores']);
            $equiposIn = "'" . implode("', '", $equiposPermitidos) . "'";
            $whereClausesExistentes[] = "Equipo IN ($equiposIn)";
            break;
            
        case 'empleados':
            $empleadosPermitidos = array_map('addslashes', $permisos['valores']);
            $empleadosIn = "'" . implode("', '", $empleadosPermitidos) . "'";
            // Para vista general
            $whereClausesExistentes[] = "full_name IN ($empleadosIn)";
            break;
    }
    
    return $whereClausesExistentes;
}

/**
 * Aplica filtros de seguridad específicos para vista resumen mensual
 * 
 * @param string $nombreUsuario - Nombre del usuario actual
 * @param string $whereClause - WHERE clause existente
 * @return string - WHERE clause modificado con restricciones de seguridad
 */
function aplicarFiltrosSeguridadResumen($nombreUsuario, $whereClause = "WHERE 1=1") {
    $permisos = obtenerPermisosUsuario($nombreUsuario);
    
    // Si no tiene restricciones, devolver el WHERE sin modificar
    if ($permisos === null || $permisos['tipo'] === 'sin_restriccion') {
        return $whereClause;
    }
    
    // Aplicar restricción según el tipo
    switch ($permisos['tipo']) {
        case 'equipo':
            $equiposPermitidos = array_map('addslashes', $permisos['valores']);
            $equiposIn = "'" . implode("', '", $equiposPermitidos) . "'";
            $whereClause .= " AND Equipo IN ($equiposIn)";
            break;
            
        case 'empleados':
            $empleadosPermitidos = array_map('addslashes', $permisos['valores']);
            $empleadosIn = "'" . implode("', '", $empleadosPermitidos) . "'";
            // Para vista resumen usa nombre_empleado
            $whereClause .= " AND nombre_empleado IN ($empleadosIn)";
            break;
    }
    
    return $whereClause;
}

/**
 * Filtra los valores de filtros según permisos del usuario
 * Solo muestra equipos/empleados que el usuario puede ver
 * 
 * @param string $nombreUsuario - Nombre del usuario actual
 * @param array $valoresFiltros - Array de valores de filtros completos
 * @return array - Array filtrado según permisos del usuario
 */
function filtrarValoresPorPermisos($nombreUsuario, $valoresFiltros) {
    $permisos = obtenerPermisosUsuario($nombreUsuario);
    
    // Si no tiene restricciones, devolver todos los valores
    if ($permisos === null || $permisos['tipo'] === 'sin_restriccion') {
        return $valoresFiltros;
    }
    
    // Filtrar según el tipo de restricción
    switch ($permisos['tipo']) {
        case 'equipo':
            // Solo mostrar los equipos permitidos
            $valoresFiltros['equipos'] = array_intersect($valoresFiltros['equipos'], $permisos['valores']);
            break;
            
        case 'empleados':
            // Solo mostrar los empleados permitidos
            $valoresFiltros['empleados'] = array_intersect($valoresFiltros['empleados'], $permisos['valores']);
            break;
    }
    
    return $valoresFiltros;
}

?>