<?php
// columnas_ocultas.php - Definición centralizada de columnas a ocultar
// ✅ ACTUALIZADO con los nombres reales de SQL Server

function getColumnasOcultar() {
    return array(
        'Language',                                    // Era 'Idioma'
        'Ofertable',                                  // Mantenido
        'formula_id',                                 // Mantenido
        'AX_Semi',                                    // Mantenido
        'Version',                                    // Mantenido
        'Fórmula_AX',                                // Era 'Cargar_datos_AX'
        'Count',                                      // Nuevo campo encontrado
        'Technical_Sheet',                            // Era 'Ficha'
        'Ficha_Actualizada',                         // Mantenido
        'Revisión_de_activos',                       // Nuevo campo
        'Comentarios_a_la_revisión_de_Fichas',      // Nuevo campo largo
        'Revisión_Ficha_Mkt',
        'Client'                        // Nuevo campo
    );
}

// ✅ NUEVA FUNCIÓN: Obtener mapeo de nombres amigables para mostrar
function getNombresAmigables() {
    return array(
        'formula_id' => 'ID Fórmula',
        'Cost' => 'Costo',
        'Status' => 'Estado',
        'Ficha_Actualizada' => 'Ficha Actualizada',
        'Revisión_de_activos' => 'Revisión de Activos',
        'Count' => 'Contador',
        'Perfume' => 'Perfume',
        'Título' => 'Título',
        'Client' => 'Cliente',
        'Campaign' => 'Campaña',
        'Naturality_%' => 'Naturalidad (%)',
        'Technical_Sheet' => 'Ficha Técnica',
        'Version' => 'Versión',
        'SPF' => 'SPF',
        'Fórmula' => 'Fórmula',
        'AX_Semi' => 'AX Semi',
        'Main_Functional_Line' => 'Línea Funcional Principal',
        'URL_Carpeta' => 'URL Carpeta',
        'Vegan' => 'Vegano',
        'Language' => 'Idioma',
        'Compare_to' => 'Comparar con',
        'Fórmula_AX' => 'Fórmula AX',
        'Ofertable' => 'Ofertable',
        'Comentarios_a_la_revisión_de_Fichas' => 'Comentarios Revisión',
        'Revisión_Ficha_Mkt' => 'Revisión Ficha Marketing',
        'SharePoint_URL_Ficha'=> 'Campaing URL'
    );
}
?>