// === SISTEMA DE FILA DE TOTALES === - CORREGIDO CON FECHA Y FORMATO

// Variables globales para totales
let datosGlobalesCompletos = [];

// === FUNCIONES UTILIDAD ===
function hhmmAMinutos(hhMM) {
    if (!hhMM || hhMM === '0:00') return 0;
    const partes = String(hhMM).split(':');
    if (partes.length !== 2) return 0;
    const horas = parseInt(partes[0]) || 0;
    const minutos = parseInt(partes[1]) || 0;
    return (horas * 60) + minutos;
}

// NUEVO: Función para verificar si una fecha es hasta hoy
function esFechaHastaHoy(fechaString) {
    if (!fechaString) return true; // Si no hay fecha, incluir por defecto
    
    try {
        // Convertir la fecha del registro a Date object
        const fechaRegistro = new Date(fechaString);
        const hoy = new Date();
        
        // Establecer hora a medianoche para comparar solo fechas
        hoy.setHours(0, 0, 0, 0);
        fechaRegistro.setHours(0, 0, 0, 0);
        
        // Solo incluir si la fecha es hoy o anterior
        return fechaRegistro <= hoy;
    } catch (error) {
        console.warn('Error procesando fecha:', fechaString, error);
        return true; // En caso de error, incluir por defecto
    }
}

// NUEVO: Función para formatear números con separadores de miles
function formatearNumeroConSeparadores(numero) {
    if (!numero || numero === 0) return '00:00';
    
    // Convertir minutos a HH:MM
    const total = Math.round(numero);
    const horas = Math.floor(total / 60);
    const minutos = total % 60;

    // Formatear horas manualmente con puntos como separadores de miles
    const horasFormateadas = horas.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const minutosFormateados = minutos.toString().padStart(2, '0');
    
    return `${horasFormateadas}:${minutosFormateados}`;
}

function calcularTotales(datos, vista) {
    if (!datos || datos.length === 0) {
        return { horasTrabajadas: 0, exceso: 0, faltantes: 0 };
    }

    let totalMinutosTrabajados = 0;
    let totalMinutosExceso = 0;
    let totalMinutosFaltantes = 0;

    if (vista === 'resumen') {
        // Vista resumen: los datos vienen en formato HH:MM
        datos.forEach(fila => {
            // Para trabajadas y exceso, sumar todos los registros
            totalMinutosTrabajados += hhmmAMinutos(fila.horas_trabajadas_hhMM || '0:00');
            totalMinutosExceso += hhmmAMinutos(fila.horas_exceso_hhMM || '0:00');
            
            // Para faltantes, solo sumar hasta hoy
            if (esFechaHastaHoy(fila.fecha)) {
                totalMinutosFaltantes += hhmmAMinutos(fila.horas_faltantes_hhMM || '0:00');
            }
        });
    } else {
        // Vista general: los datos vienen en minutos
        datos.forEach(fila => {
            // Para trabajadas y exceso, sumar todos los registros
            totalMinutosTrabajados += Number(fila.minutos_trabajados) || 0;
            totalMinutosExceso += Number(fila.exceso_min) || 0;
            
            // Para faltantes, solo sumar hasta hoy
            if (esFechaHastaHoy(fila.fecha)) {
                totalMinutosFaltantes += Number(fila.faltantes_min) || 0;
            }
        });
    }

    return {
        horasTrabajadas: totalMinutosTrabajados,
        exceso: totalMinutosExceso,
        faltantes: totalMinutosFaltantes
    };
}

// === FUNCIONES PRINCIPALES ===
function crearFilaTotales() {
    // No crear si ya existe
    if (document.getElementById('totals-row')) {
        return;
    }

    const filaHTML = `
        <div id="totals-row" class="totals-row" style="display: none;">
            <div class="totals-container">
                <div class="totals-label">TOTALES:</div>
                <div class="totals-item">
                    <span class="totals-item-label">H. Trabajadas:</span>
                    <span class="totals-value" id="total-trabajadas">00:00</span>
                </div>
                <div class="totals-item">
                    <span class="totals-item-label">Exceso:</span>
                    <span class="totals-value exceso" id="total-exceso">00:00</span>
                </div>
                <div class="totals-item">
                    <span class="totals-item-label">Faltantes (hasta hoy):</span>
                    <span class="totals-value faltante" id="total-faltantes">00:00</span>
                </div>
                <button class="totals-toggle" id="totals-toggle" title="Ocultar totales">
                    <i class="fas fa-eye-slash"></i>
                </button>
            </div>
        </div>
    `;
    
    // Insertar encima de los controles de paginación
    $('.table-controls-bottom').before(filaHTML);
}

function cargarDatosCompletosParaTotales() {
    return new Promise((resolve, reject) => {
        const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
            ? window.viewManager.getCurrentView()
            : 'general';

        const ajaxData = { 
            ajax: '1', 
            limite: 'ALL'
        };

        // Añadir action para vista resumen
        if (vista === 'resumen') {
            ajaxData.action = 'datos_resumen_mensual';
        }

        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: function(datos) {
                if (datos.error) {
                    console.error('Error cargando datos completos:', datos.message);
                    reject(datos.message);
                    return;
                }
                datosGlobalesCompletos = datos;
                resolve(datos);
            },
            error: function(xhr, status, error) {
                console.error('Error Ajax cargando datos completos:', error);
                reject(error);
            }
        });
    });
}

function actualizarTotales() {
    const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
        ? window.viewManager.getCurrentView()
        : 'general';
    
    // OBTENER DATOS DIRECTAMENTE DE LA TABLA DATATABLES (solo datos visibles/filtrados)
    let datosParaCalcular;
    
    try {
        const tabla = $('#tabla-empleados').DataTable();
        const datosTabla = tabla.rows({search: 'applied'}).data().toArray();
        
        if (datosTabla.length === 0) {
            // Si no hay datos en la tabla, ocultar totales
            $('#totals-row').hide();
            return;
        }
        
        let totalMinutosTrabajados = 0;
        let totalMinutosExceso = 0;
        let totalMinutosFaltantes = 0;
        
        if (vista === 'resumen') {
            // Vista resumen: columnas [8,9,10] son las horas en formato HH:MM
            datosTabla.forEach(fila => {
                totalMinutosTrabajados += hhmmAMinutos(fila[8] || '0:00');
                totalMinutosExceso += hhmmAMinutos(fila[9] || '0:00');
                
                // NUEVO: Solo sumar faltantes si la fecha es hasta hoy
                // Asumiendo que la fecha está en la columna [0] o [1]
                const fechaRegistro = fila[0] || fila[1]; // Ajustar según tu estructura
                if (esFechaHastaHoy(fechaRegistro)) {
                    totalMinutosFaltantes += hhmmAMinutos(fila[10] || '0:00');
                }
            });
        } else {
            // Vista general con nuevo orden de columnas
            // [7] = H. Trabajados, [8] = Exceso, [9] = Faltantes
            datosTabla.forEach(fila => {
                totalMinutosTrabajados += Number(fila[7]) || 0;
                totalMinutosExceso += Number(fila[8]) || 0;
                
                // NUEVO: Solo sumar faltantes si la fecha es hasta hoy
                // La fecha está en la columna [1] (segunda columna)
                const fechaRegistro = fila[1];
                if (esFechaHastaHoy(fechaRegistro)) {
                    totalMinutosFaltantes += Number(fila[9]) || 0;
                }
            });
        }
        
        // NUEVO: Actualizar valores con el nuevo formato
        $('#total-trabajadas').text(formatearNumeroConSeparadores(totalMinutosTrabajados));
        $('#total-exceso').text(formatearNumeroConSeparadores(totalMinutosExceso));
        $('#total-faltantes').text(formatearNumeroConSeparadores(totalMinutosFaltantes));
        
        // Mostrar la fila de totales
        $('#totals-row').show();
        
        console.log('Totales actualizados desde tabla DataTables:', {
            vista: vista,
            registros: datosTabla.length,
            fuente: 'DataTables (solo datos visibles)',
            trabajadas: formatearNumeroConSeparadores(totalMinutosTrabajados),
            exceso: formatearNumeroConSeparadores(totalMinutosExceso),
            faltantes: formatearNumeroConSeparadores(totalMinutosFaltantes),
            nota: 'Faltantes calculados solo hasta hoy'
        });
        
    } catch (error) {
        console.warn('Error accediendo a DataTables, usando fallback:', error);
        
        // FALLBACK: usar window.datosGlobales si DataTables no está disponible
        if (window.datosGlobales && window.datosGlobales.length > 0) {
            const totales = calcularTotales(window.datosGlobales, vista);
            
            $('#total-trabajadas').text(formatearNumeroConSeparadores(totales.horasTrabajadas));
            $('#total-exceso').text(formatearNumeroConSeparadores(totales.exceso));
            $('#total-faltantes').text(formatearNumeroConSeparadores(totales.faltantes));
            
            $('#totals-row').show();
            
            console.log('Totales actualizados (fallback):', {
                vista: vista,
                registros: window.datosGlobales.length,
                fuente: 'window.datosGlobales (fallback)',
                nota: 'Faltantes calculados solo hasta hoy'
            });
        } else {
            $('#totals-row').hide();
        }
    }
}

// === INTEGRACIÓN NO INVASIVA ===

// Función para observar cambios en la tabla sin modificar funciones existentes
function observarCambiosTabla() {
    // Observer para detectar cuando se actualiza la tabla
    const targetNode = document.getElementById('tabla-empleados');
    if (targetNode) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.target.tagName === 'TBODY') {
                    // La tabla se ha actualizado, actualizar totales después de un breve delay
                    setTimeout(actualizarTotales, 200);
                }
            });
        });

        observer.observe(targetNode, {
            childList: true,
            subtree: true
        });
    }
}

// === EVENTOS Y INICIALIZACIÓN ===
$(document).ready(function() {
    // Esperar a que otros sistemas se inicialicen
    setTimeout(() => {
        crearFilaTotales();
        
        // Cargar datos completos iniciales
        setTimeout(() => {
            cargarDatosCompletosParaTotales().then(() => {
                actualizarTotales();
            }).catch(err => {
                console.warn('No se pudieron cargar datos iniciales para totales:', err);
            });
        }, 1000);
        
        // Inicializar observador de cambios en tabla
        observarCambiosTabla();
        
    }, 500);
    
    // Event handler específico para el botón de toggle de totales
    $(document).on('click', '#totals-toggle', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const $row = $('#totals-row');
        const $icon = $(this).find('i');
        
        if ($row.hasClass('hidden')) {
            $row.removeClass('hidden');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            $(this).attr('title', 'Ocultar totales');
        } else {
            $row.addClass('hidden');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            $(this).attr('title', 'Mostrar totales');
        }
    });
    
    // Escuchar eventos específicos del sistema
    
    // 1. Cambios de vista
    $(document).on('click', '.view-btn', function() {
        setTimeout(() => {
            // Limpiar datos completos para recargar según nueva vista
            datosGlobalesCompletos = [];
            cargarDatosCompletosParaTotales().then(() => {
                actualizarTotales();
            }).catch(err => {
                console.warn('Error recargando totales tras cambio de vista:', err);
            });
        }, 800);
    });
    
    // 2. Aplicación de filtros - escuchar DESPUÉS de que se procesen
    $(document).on('ajax:success', function() {
        setTimeout(actualizarTotales, 300);
    });
    
    // También escuchar completado de DataTables draw
    $(document).on('draw.dt', '#tabla-empleados', function() {
        setTimeout(actualizarTotales, 200);
    });
    
    // Escuchar específicamente el procesamiento de filtros
    const originalAjaxSuccess = $.ajaxSetup().success;
    $(document).ajaxSuccess(function(event, xhr, settings) {
        // Solo si es una petición de datos de la tabla
        if (settings.url && settings.url.includes(window.location.pathname) && 
            settings.data && (settings.data.includes('ajax=1') || settings.data.ajax === '1')) {
            setTimeout(actualizarTotales, 300);
        }
    });
    
    // 3. Cambio de límite de registros
    $(document).on('change', '#limite-registros', function() {
        setTimeout(actualizarTotales, 400);
    });
    
    // 4. Limpiar filtros
    $(document).on('click', '[onclick*="limpiarFiltros"]', function() {
        setTimeout(actualizarTotales, 600);
    });
});

// Hacer funciones disponibles globalmente para debugging
window.TotalsRow = {
    actualizarTotales,
    calcularTotales,
    cargarDatosCompletosParaTotales,
    crearFilaTotales,
    esFechaHastaHoy,
    formatearNumeroConSeparadores
};