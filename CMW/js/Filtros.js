// === VARIABLES GLOBALES ===
let tablaEmpleados;
let datosGlobales = [];
let valoresFiltros = {
    empleados: [],
    equipos: [],
    meses: [],
    dias: [],
    festivos: [],
    justificados: []
};

const seleccionEmps = new Set();
const seleccionTeams = new Set();
const seleccionMeses = new Set();
const seleccionDias = new Set();
const seleccionFestivos = new Set();
const seleccionJustificados = new Set();

// Variable global para controlar cuando viene de burbujas
let updatingFromBubble = false;

// === FUNCIONES UTILIDAD ===
function minutosAHHMM(minutos) {
    if (minutos === null || minutos === undefined || minutos === 0) {
        return '00:00';
    }
    
    const total = Math.round(Math.abs(minutos));
    const horas = Math.floor(total / 60);
    const mins = total % 60;
    const signo = minutos < 0 ? '-' : '';

    return signo + horas.toString().padStart(2, '0') + ':' + mins.toString().padStart(2, '0');
}

// Orden correcto para meses y días
const MESES_ORDEN = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const DIAS_ORDEN = ['lunes','martes','miércoles','jueves','viernes','sábado','domingo'];

function normalizaMes(txt) {
    if (!txt) return '';
    return String(txt).trim().toLowerCase();
}

function normalizaDia(txt) {
    if (!txt) return '';
    return String(txt).trim().toLowerCase();
}

function ordenaMeses(lista) {
    return [...lista].sort((a,b) => MESES_ORDEN.indexOf(normalizaMes(a)) - MESES_ORDEN.indexOf(normalizaMes(b)));
}

function ordenaDias(lista) {
    return [...lista].sort((a,b) => DIAS_ORDEN.indexOf(normalizaDia(a)) - DIAS_ORDEN.indexOf(normalizaDia(b)));
}

function isoFromSqlDate(val) {
    if (!val) return '';
    return String(val).slice(0, 10);
}

function displayDMY(val) {
    const iso = isoFromSqlDate(val);
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function timeHHMMSS(val) {
    if (!val) return '00:00:00';
    const t = String(val).includes(' ') ? String(val).split(' ')[1] : String(val);
    return t.length === 5 ? (t + ':00') : t;
}

function toBool01(x) {
    const s = String(x).trim().toLowerCase();
    return (x === 1 || x === true || s === '1' || s === 'si' || s === 'sí' || s === 'yes') ? 1 : 0;
}

function renderChecklist(listId, items, selectedSet) {
    const cont = document.getElementById(listId);
    cont.innerHTML = items.map(v => {
        const safe = String(v).replace(/"/g, '&quot;');
        const checked = selectedSet.has(v) ? 'checked' : '';
        return `<label class="dd-item">
                <input type="checkbox" value="${safe}" ${checked}>
                <span>${safe}</span>
                </label>`;
    }).join('');
}

// Helper para nombre de empleado en ambas vistas
function getNombreEmpleado(f) {
    return f.full_name || f.nombre_empleado || '';
}

function togglePanel(panelId) {
    $('.dd-panel').not(`#${panelId}`).removeClass('open');
    $(`#${panelId}`).toggleClass('open');
}

function closePanelsOnOutsideClick(e) {
    // No cerrar si el clic es en burbujas o dentro de un multi-dd
    if ($(e.target).closest('.multi-dd, .filter-tags').length === 0) {
        $('.dd-panel').removeClass('open');
    }
}

// === FUNCIONES DE CARGA Y FILTROS ===

// Cargar valores de filtros desde el servidor (solo una vez)
function cargarValoresFiltros() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { ajax: '1', action: 'filtros' },
            dataType: 'json',
            success: function(datos) {
                if (datos.error) {
                    console.error('Error del servidor:', datos.message);
                    reject(datos.message);
                    return;
                }
                valoresFiltros = datos;
                poblarFiltrosDesdeServidor();
                resolve(datos);
            },
            error: function(xhr, status, error) {
                console.error('Error cargando valores de filtros:', error);
                console.error('Response text:', xhr.responseText);
                reject(error);
            }
        });
    });
}

// Poblar filtros con datos del servidor
function poblarFiltrosDesdeServidor() {
    renderChecklist('list-emps', valoresFiltros.empleados, seleccionEmps);
    renderChecklist('list-teams', valoresFiltros.equipos, seleccionTeams);
    
    // Ordenar meses y días correctamente
    const mesesOrdenados = ordenaMeses(valoresFiltros.meses);
    const diasOrdenados = ordenaDias(valoresFiltros.dias);
    
    renderChecklist('list-mes', mesesOrdenados, seleccionMeses);
    renderChecklist('list-dias', diasOrdenados, seleccionDias);
    renderChecklist('list-festivos', valoresFiltros.festivos, seleccionFestivos);
    renderChecklist('list-justificados', valoresFiltros.justificados, seleccionJustificados);
    
    updateBadges();
}

// Poblar filtros inteligentes basados en datosGlobales y selecciones actuales
function poblarFiltrosInteligentes() {
    // Determinar la vista actual
    const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
        ? window.viewManager.getCurrentView()
        : 'general';

    // 1) Equipos únicos (filtrados por empleados seleccionados si los hay)
    const selEmps = Array.from(seleccionEmps);
    const fuenteEquipos = selEmps.length === 0 
        ? datosGlobales 
        : datosGlobales.filter(x => selEmps.includes(getNombreEmpleado(x)));
    const equipos = [...new Set(fuenteEquipos.map(x => x.Equipo))].filter(Boolean).sort();
    
    // Mantener solo equipos seleccionados que aún existen
    const nuevaSeleccionTeams = [...seleccionTeams].filter(v => equipos.includes(v));
    seleccionTeams.clear();
    nuevaSeleccionTeams.forEach(v => seleccionTeams.add(v));
    renderChecklist('list-teams', equipos, seleccionTeams);

    // 2) Empleados únicos (filtrados por equipos seleccionados si los hay)
    const selEqs = Array.from(seleccionTeams);
    const fuenteEmpleados = selEqs.length === 0 
        ? datosGlobales 
        : datosGlobales.filter(x => selEqs.includes(x.Equipo));
    const empleados = [...new Set(fuenteEmpleados.map(getNombreEmpleado))].filter(Boolean).sort();
    
    // Mantener solo empleados seleccionados que aún existen
    const nuevaSeleccionEmps = [...seleccionEmps].filter(v => empleados.includes(v));
    seleccionEmps.clear();
    nuevaSeleccionEmps.forEach(v => seleccionEmps.add(v));
    renderChecklist('list-emps', empleados, seleccionEmps);

    // 3) Meses - siempre desde valoresFiltros (no cambian)
    const mesesOrdenados = ordenaMeses(valoresFiltros.meses);
    renderChecklist('list-mes', mesesOrdenados, seleccionMeses);

    // 4) SOLO PARA VISTA GENERAL: días, festivos y justificados
    if (vista === 'general') {
        const diasOrdenados = ordenaDias(valoresFiltros.dias);
        renderChecklist('list-dias', diasOrdenados, seleccionDias);
        renderChecklist('list-festivos', valoresFiltros.festivos, seleccionFestivos);
        renderChecklist('list-justificados', valoresFiltros.justificados, seleccionJustificados);
    }
    // En vista resumen, no actualizamos estos filtros porque están ocultos
    
    updateBadges();
}

// Cargar datos iniciales (sin filtros aplicados)
function cargarDatosIniciales() {
    const limite = $('#limite-registros').val();
    
    showPageLoading('Cargando datos iniciales...');
    $('#error-container').hide();

    $.ajax({
        url: window.location.href,
        method: 'GET',
        data: { ajax: '1', limite: limite },
        dataType: 'json',
        success: function(datos) {
            if (datos.error) {
                console.error('Error del servidor:', datos.message);
                hidePageLoading();
                $('#error-container').html('<div class="error">Error del servidor: ' + datos.message + '</div>').show();
                return;
            }
            
            datosGlobales = datos;
            actualizarTabla(datos);
            poblarFiltrosInteligentes(); // Usar filtros inteligentes
            
            console.log(`${datos.length} registros cargados inicialmente`);
            
            setTimeout(() => {
                hidePageLoading();
            }, 200);
        },
        error: function(xhr, status, error) {
            hidePageLoading();
            $('#error-container').html('<div class="error">Error al cargar datos iniciales: ' + error + '</div>').show();
        }
    });
}

// REEMPLAZA la función cargarDatosResumen() en tu Filtros.js con esta versión corregida:

function cargarDatosResumen() {
    showPageLoading('Cargando resumen mensual...');
    $('#error-container').hide();

    // AGREGAR: Obtener filtros activos actuales para aplicar restricciones desde el principio
    const filtrosActivos = {};
    if (seleccionEmps.size > 0)  filtrosActivos.empleados = Array.from(seleccionEmps);
    if (seleccionTeams.size > 0) filtrosActivos.equipos   = Array.from(seleccionTeams);  
    if (seleccionMeses.size > 0) filtrosActivos.meses     = Array.from(seleccionMeses);

    // MODIFICAR: Usar el endpoint con filtros si hay filtros activos
    const endpoint = Object.keys(filtrosActivos).length > 0 
        ? 'filtrar_resumen_mensual'  // Endpoint que aplica filtros de usuario + filtros seleccionados
        : 'datos_resumen_mensual';   // Endpoint base que solo aplica filtros de usuario

    const ajaxData = {
        ajax: '1',
        action: endpoint
    };

    // Si hay filtros activos, agregarlos
    if (Object.keys(filtrosActivos).length > 0) {
        ajaxData.limite = 'ALL';
        ajaxData.filtros = JSON.stringify(filtrosActivos);
    }

    console.log('DEBUG cargarDatosResumen - usando endpoint:', endpoint, 'con datos:', ajaxData);

    $.ajax({
        url: window.location.href,
        method: 'GET',
        data: ajaxData,
        dataType: 'json',
        success: function(datos) {
            if (datos.error) {
                hidePageLoading();
                $('#error-container').html('<div class="error">Error del servidor: ' + (datos.message||'') + '</div>').show();
                return;
            }
            datosGlobales = datos;
            if (window.viewManager) {
                window.viewManager.updateTableData(datos);
            } else if (tablaEmpleados) {
                // Fallback básico (si no usas ViewManager por alguna razón)
                tablaEmpleados.clear();
                datos.forEach(f => {
                    tablaEmpleados.row.add([
                        f.employee_id, f.nombre_empleado, f.Equipo, f.nombre_mes,
                        f.total_dias_mes, f.dias_trabajados, f.dias_laborables_teoricos,
                        f.dias_festivos, f.dias_permiso,
                        f.horas_trabajadas_hhMM, f.horas_exceso_hhMM, f.horas_faltantes_hhMM
                    ]);
                });
                tablaEmpleados.draw();
            }
            
            // AGREGAR: Actualizar filtros inteligentes después de cargar datos
            poblarFiltrosInteligentes();
            
            console.log(`Resumen cargado: ${datos.length} registros`);
            setTimeout(hidePageLoading, 150);
        },
        error: function(xhr, status, error) {
            hidePageLoading();
            $('#error-container').html('<div class="error">Error al cargar resumen: ' + error + '</div>').show();
        }
    });
}

function aplicarFiltros() {
        const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
            ? window.viewManager.getCurrentView()
            : 'general';

        if (vista === 'resumen') {
            return aplicarFiltrosResumen(); // 👉 en resumen, usar el endpoint/resumen
        }

        // ====== Vista GENERAL (tu código actual) ======
        const limite = $('#limite-registros').val();

        showPageLoading('Aplicando filtros...');
        $('#error-container').hide();

        // Construir objeto de filtros activos
        const filtrosActivos = {};
        if (seleccionEmps.size > 0)        filtrosActivos.empleados     = Array.from(seleccionEmps);
        if (seleccionTeams.size > 0)       filtrosActivos.equipos       = Array.from(seleccionTeams);
        if (seleccionMeses.size > 0)       filtrosActivos.meses         = Array.from(seleccionMeses);
        if (seleccionDias.size > 0)        filtrosActivos.dias          = Array.from(seleccionDias);
        if (seleccionFestivos.size > 0)    filtrosActivos.festivos      = Array.from(seleccionFestivos);
        if (seleccionJustificados.size > 0)filtrosActivos.justificados  = Array.from(seleccionJustificados);

        const numFiltros = Object.keys(filtrosActivos).length;
        console.log('[GENERAL] Aplicando filtros:', filtrosActivos, 'n=', numFiltros);

        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { 
                ajax: '1', 
                limite: limite,
                filtros: JSON.stringify(filtrosActivos)
            },
            dataType: 'json',
            success: function(datos) {
                if (datos.error) {
                    hidePageLoading();
                    $('#error-container').html('<div class="error">Error del servidor: ' + datos.message + '</div>').show();
                    return;
                }
                datosGlobales = datos;
                actualizarTabla(datos);

                const mensaje = numFiltros > 0 
                    ? `${datos.length} registros encontrados con ${numFiltros} filtro(s) aplicado(s)`
                    : `${datos.length} registros cargados sin filtros`;
                console.log(mensaje);

                setTimeout(hidePageLoading, 200);
            },
            error: function(xhr, status, error) {
                hidePageLoading();
                $('#error-container').html('<div class="error">Error al aplicar filtros: ' + error + '</div>').show();
            }
        });
}


// Aplicar filtros para la vista RESUMEN (empleados, equipos, meses)
function aplicarFiltrosResumen() {
    const limite = $('#limite-registros').val();

    showPageLoading('Aplicando filtros (resumen)…');
    $('#error-container').hide();

    const filtrosActivos = {};
    if (seleccionEmps.size  > 0) filtrosActivos.empleados = Array.from(seleccionEmps);
    if (seleccionTeams.size > 0) filtrosActivos.equipos   = Array.from(seleccionTeams);
    if (seleccionMeses.size > 0) filtrosActivos.meses     = Array.from(seleccionMeses);

    const numFiltros = Object.keys(filtrosActivos).length;
    console.log('[RESUMEN] Aplicando filtros:', filtrosActivos, 'n=', numFiltros);

    $.ajax({
        url: window.location.href,
        method: 'GET',
        data: {
            ajax: '1',
            action: 'filtrar_resumen_mensual', // <-- endpoint resumen
            limite: limite,
            filtros: JSON.stringify(filtrosActivos)
        },
        dataType: 'json',
        success: function(datos) {
            if (datos.error) {
                hidePageLoading();
                $('#error-container').html('<div class="error">Error del servidor: ' + (datos.message||'') + '</div>').show();
                return;
            }
            datosGlobales = datos;

            if (window.viewManager) {
                window.viewManager.updateTableData(datos);
            } else {
                actualizarTabla(datos);
            }

            const msg = numFiltros > 0
                ? `${datos.length} registros (resumen) con ${numFiltros} filtro(s)`
                : `${datos.length} registros (resumen) sin filtros`;
            console.log(msg);

            setTimeout(hidePageLoading, 150);
        },
        error: function(xhr, status, error) {
            hidePageLoading();
            $('#error-container').html('<div class="error">Error al filtrar resumen: ' + error + '</div>').show();
        }
    });
}

// Limpiar filtros con loading
async function limpiarFiltros() { 
    await showPageLoading('Limpiando filtros…');

    setTimeout(() => {
        try {
            // 1) Vaciar selecciones internas
            seleccionEmps.clear();
            seleccionTeams.clear();
            seleccionMeses.clear();
            seleccionDias.clear();
            seleccionFestivos.clear();
            seleccionJustificados.clear();

            // 2) Desmarcar todos los checkboxes visibles en los paneles
            $('#list-emps input[type=checkbox],\
               #list-teams input[type=checkbox],\
               #list-mes input[type=checkbox],\
               #list-dias input[type=checkbox],\
               #list-festivos input[type=checkbox],\
               #list-justificados input[type=checkbox]').prop('checked', false);

            // 3) Actualizar contadores de los botones
            updateBadges();

            // 4) Recargar datos según la vista actual
            const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
                ? window.viewManager.getCurrentView()
                : 'general';

            if (vista === 'resumen') {
                cargarDatosResumen();
            } else {
                cargarDatosIniciales();
            }
        } finally {
            hidePageLoading();
        }
    }, 100);
}


// MODIFICADA: Actualizar tabla con datos - Integración con ViewManager
function actualizarTabla(datos) {
    // Si existe ViewManager, usar su método de actualización
    if (window.viewManager && window.viewManager.getDataTable()) {
        window.viewManager.updateTableData(datos);
        return;
    }
    
    // Fallback al método tradicional si ViewManager no está disponible
    if (!tablaEmpleados) {
        console.warn('tablaEmpleados no está definida');
        return;
    }
    
    tablaEmpleados.clear();
    
    const safe = (v) => (v === null || v === undefined ? '' : v);

    datos.forEach(function(fila) {
        const filaArray = [
            safe(fila.full_name),           // 0 - Nombre Completo
            safe(fila.date),                // 1 - Fecha
            safe(fila.dia_semana),          // 2 - Día Semana
            safe(fila.clock_in),            // 3 - Entrada
            safe(fila.clock_out),           // 4 - Salida
            safe(fila.role_name),           // 5 - Rol
            safe(fila.Equipo),              // 6 - Equipo
            safe(fila.minutos_trabajados),  // 7 - H. Trabajados ✓
            safe(fila.exceso_min),          // 8 - Exceso H. ✓
            safe(fila.faltantes_min),       // 9 - Faltantes H. ✓ (CORREGIDO)
            safe(fila.Festivo),             // 10 - Festivo (MOVIDO)
            safe(fila.Justificado)          // 11 - Justificado (MOVIDO)
        ];
        tablaEmpleados.row.add(filaArray);
    });
    
    tablaEmpleados.draw();
}

// === FUNCIONES DE LOADING ===
function showPageLoading(msg = 'Procesando…') {
    $('#page-loading-text').text(msg);
    $('#page-loading').addClass('show');
    $('.blur-target').addClass('blur');
    return new Promise(requestAnimationFrame);
}

function hidePageLoading() {
    $('#page-loading').removeClass('show');
    $('.blur-target').removeClass('blur');
}

function actualizarUIporVista() {
    const vista = (window.viewManager && typeof window.viewManager.getCurrentView === 'function')
        ? window.viewManager.getCurrentView()
        : 'general';

    const $grpDias         = $('#dd-dias').closest('.filter-group');
    const $grpFestivos     = $('#dd-festivos').closest('.filter-group');
    const $grpJustificados = $('#dd-justificados').closest('.filter-group');

    if (vista === 'resumen') {
        // En RESUMEN ocultamos los filtros no aplicables
        $grpDias.hide();
        $grpFestivos.hide();
        $grpJustificados.hide();
    } else {
        // En GENERAL mostramos todo
        $grpDias.show();
        $grpFestivos.show();
        $grpJustificados.show();
    }
}

// Función para actualizar tags/burbujas
function updateFilterTags() {
    updateTagsForFilter('tags-empleados', seleccionEmps, 'empleado');
    updateTagsForFilter('tags-equipos', seleccionTeams, 'equipo');
    updateTagsForFilter('tags-meses', seleccionMeses, 'mes');
    updateTagsForFilter('tags-dias', seleccionDias, 'dia');
    updateTagsForFilter('tags-festivos', seleccionFestivos, 'festivo');
    updateTagsForFilter('tags-justificados', seleccionJustificados, 'justificado');
}

// Función helper para crear tags de un filtro específico
function updateTagsForFilter(containerId, selectionSet, filterType) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    Array.from(selectionSet).forEach(value => {
        const tag = document.createElement('div');
        tag.className = 'filter-tag';
        tag.innerHTML = `
            <span class="filter-tag-text" title="${value}">${value}</span>
            <span class="filter-tag-remove" data-filter="${filterType}" data-value="${value}">×</span>
        `;
        container.appendChild(tag);
    });
}

// Event handler para remover tags - SIN FLASH
$(document).on('click', '.filter-tag-remove', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const filterType = $(this).data('filter');
    const value = $(this).data('value');
    const $panel = $(this).closest('.dd-panel');
    const $tag = $(this).closest('.filter-tag'); // La burbuja a eliminar
    
    // Marcar que estamos actualizando desde burbuja
    updatingFromBubble = true;
    
    // Remover de la selección
    switch(filterType) {
        case 'empleado': seleccionEmps.delete(value); break;
        case 'equipo': seleccionTeams.delete(value); break;
        case 'mes': seleccionMeses.delete(value); break;
        case 'dia': seleccionDias.delete(value); break;
        case 'festivo': seleccionFestivos.delete(value); break;
        case 'justificado': seleccionJustificados.delete(value); break;
    }
    
    // Actualizar checkbox
    const checkboxSelector = getCheckboxSelector(filterType, value);
    $(checkboxSelector).prop('checked', false);
    
    // ELIMINAR CON ANIMACIÓN SUAVE
    $tag.addClass('removing');
    setTimeout(() => $tag.remove(), 200);
        
    // Actualizar solo los contadores de los botones (sin recrear burbujas)
    updateBadges();
    
    // Resetear variable y mantener panel abierto
    setTimeout(() => {
        updatingFromBubble = false;
        $panel.addClass('open');
    }, 10);
});

// Helper para obtener el selector del checkbox
function getCheckboxSelector(filterType, value) {
    const listMap = {
        'empleado': '#list-emps',
        'equipo': '#list-teams', 
        'mes': '#list-mes',
        'dia': '#list-dias',
        'festivo': '#list-festivos',
        'justificado': '#list-justificados'
    };
    
    const listId = listMap[filterType];
    return `${listId} input[value="${value.replace(/"/g, '\\"')}"]`;
}

function updateBadges() {
    $('#btn-dd-emps').text(`Seleccionar (${seleccionEmps.size})`);
    $('#btn-dd-teams').text(`Seleccionar (${seleccionTeams.size})`);
    $('#btn-dd-mes').text(`Seleccionar (${seleccionMeses.size})`);
    $('#btn-dd-dias').text(`Seleccionar (${seleccionDias.size})`);
    $('#btn-dd-festivos').text(`Seleccionar (${seleccionFestivos.size})`);
    $('#btn-dd-justificados').text(`Seleccionar (${seleccionJustificados.size})`);
    
    // Actualizar tags también - SOLO si no viene de burbujas
    if (!updatingFromBubble) {
        updateFilterTags();
    }
}

// Evitar que clicks en el área de tags cierren el panel
$(document).on('click', '.filter-tags', function(e) {
    e.stopPropagation();
});

// === INICIALIZACIÓN ===
$(document).ready(function () {
    // Crear tabla inicial básica (será recreada por ViewManager si está disponible)
    tablaEmpleados = $('#tabla-empleados').DataTable({
        responsive: false,
        autoWidth: false,
        scrollX: false,
        pageLength: 100,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,"Todos"]],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        dom: '<"table-top"Brt><"table-bottom"ip>',
        buttons: ['print'],

        drawCallback: function () {
            $('.dataTables_info').appendTo('.bottom-left-controls');
            $('.dataTables_paginate').appendTo('#table-pagination');
        },

        columnDefs: [
            { targets: 1, className: 'col-date-small',  width: 100,
            render: (d,t)=> (t==='sort'||t==='type') ? isoFromSqlDate(d) : displayDMY(d) },

            { targets: [3,4], className: 'col-time-small', width: 75,
            render: (d,t)=> (t==='sort'||t==='type') ? timeHHMMSS(d) : (timeHHMMSS(d).substring(0,5)||'00:00') },

            // CORREGIDO: columnas 7, 8, 9 para horas
            { targets: [7,8,9], className: 'col-time-small', width: 75,
            render: (d,t)=> { const n=Number(d)||0; return (t==='sort'||t==='type')? n : minutosAHHMM(n); } },

            { targets: 2, className: 'col-status', width: 60 },

            // CORREGIDO: Festivo ahora en columna 10
            { targets: 10, className: 'col-status', width: 60,
            render: (d,t)=> { const v=toBool01(d); return (t==='sort'||t==='type')? v : (v?'Sí':'No'); } },

            // CORREGIDO: Justificado ahora en columna 11
            { targets: 11, className: 'col-status', width: 60,
            render: (d,t)=> { const v=toBool01(d); return (t==='sort'||t==='type')? v : (v?'Sí':'No'); } },
        ],

        rowCallback: function (row, data) {
            $(row).removeClass('exceso faltante festivo permiso');
            const exceso     = Number(data[8])||0;  // CORREGIDO: posición 8
            const faltantes  = Number(data[9])||0;  // CORREGIDO: posición 9
            const festivo    = toBool01(data[10]);   // CORREGIDO: posición 10
            const justificado = toBool01(data[11]);  // CORREGIDO: posición 11
            
            // Lógica de prioridad de colores
            if (exceso > 0) {
                $(row).addClass('exceso');
            } else if (faltantes > 0) {
                $(row).addClass('faltante');
            } else if (festivo === 1) {
                $(row).addClass('festivo');
            } else if (justificado === 1) {
                $(row).addClass('permiso');
            }
        }
    });

    // NUEVO: Inicializar el ViewManager si está disponible
    if (window.viewManager) {
        window.viewManager.init(tablaEmpleados);
        console.log('ViewManager inicializado correctamente');
    } else {
        console.warn('ViewManager no está disponible - funcionando en modo clásico');
    }

    // Event handlers para abrir/cerrar paneles
    $('#btn-dd-emps').on('click', () => togglePanel('panel-dd-emps'));
    $('#btn-dd-teams').on('click', () => togglePanel('panel-dd-teams'));
    $('#btn-dd-mes').on('click', () => togglePanel('panel-dd-mes'));
    $('#btn-dd-dias').on('click', () => togglePanel('panel-dd-dias'));
    $('#btn-dd-festivos').on('click', () => togglePanel('panel-dd-festivos'));
    $('#btn-dd-justificados').on('click', () => togglePanel('panel-dd-justificados'));

    // Event handler para limpiar búsqueda al perder foco (con retraso)
    $('#search-emps, #search-teams, #search-mes, #search-dias').on('blur', function () {
        const input = this;
        // Retraso de 150ms para permitir que se complete el clic en checkbox
        setTimeout(function() {
            input.value = '';
            const searchId = input.id;
            const listId = searchId.replace('search-', 'list-');
            $(`#${listId} .dd-item`).show();
        }, 150);
    });

    // Event handlers para checkboxes - CON CONTROL DE BURBUJAS
    $(document).on('change', '#list-emps input[type=checkbox]', function () {
        if (updatingFromBubble) return; // No ejecutar si viene de burbuja
        
        const v = this.value;
        this.checked ? seleccionEmps.add(v) : seleccionEmps.delete(v);
        poblarFiltrosInteligentes();
        updateBadges();
    });

    $(document).on('change', '#list-teams input[type=checkbox]', function () {
        if (updatingFromBubble) return; // No ejecutar si viene de burbuja
        
        const v = this.value;
        this.checked ? seleccionTeams.add(v) : seleccionTeams.delete(v);
        poblarFiltrosInteligentes();
        updateBadges();
    });

    $(document).on('change', '#list-mes input[type=checkbox]', function () {
        if (updatingFromBubble) return;
        
        const v = normalizaMes(this.value);
        this.checked ? seleccionMeses.add(v) : seleccionMeses.delete(v);
        updateBadges();
    });

$(document).on('change', '#list-dias input[type=checkbox]', function () {
        if (updatingFromBubble) return;
        
        const v = normalizaDia(this.value);
        this.checked ? seleccionDias.add(v) : seleccionDias.delete(v);
        updateBadges();
    });

    $(document).on('change', '#list-festivos input[type=checkbox]', function () {
        if (updatingFromBubble) return;
        
        const v = this.value;
        this.checked ? seleccionFestivos.add(v) : seleccionFestivos.delete(v);
        updateBadges();
    });

    $(document).on('change', '#list-justificados input[type=checkbox]', function () {
        if (updatingFromBubble) return;
        
        const v = this.value;
        this.checked ? seleccionJustificados.add(v) : seleccionJustificados.delete(v);
        updateBadges();
    });

    // Event handlers para búsqueda en filtros
    $('#search-emps').on('input', function () {
        const q = this.value.toLowerCase();
        $('#list-emps .dd-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    $('#search-teams').on('input', function () {
        const q = this.value.toLowerCase();
        $('#list-teams .dd-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    $('#search-mes').on('input', function () {
        const q = this.value.toLowerCase();
        $('#list-mes .dd-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    $('#search-dias').on('input', function () {
        const q = this.value.toLowerCase();
        $('#list-dias .dd-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    // Event handlers para seleccionar todos/ninguno - CON CONTROL DE BURBUJAS
    $('#all-emps').on('click', function () {
        updatingFromBubble = true;
        $('#list-emps input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionEmps.add(this.value); 
        });
        setTimeout(() => {
            updatingFromBubble = false;
            poblarFiltrosInteligentes();
            updateBadges();
        }, 10);
    });
    
    $('#none-emps').on('click', function () {
        updatingFromBubble = true;
        $('#list-emps input[type=checkbox]').each(function () { this.checked = false; });
        seleccionEmps.clear();
        setTimeout(() => {
            updatingFromBubble = false;
            poblarFiltrosInteligentes();
            updateBadges();
        }, 10);
    });

    $('#all-teams').on('click', function () {
        updatingFromBubble = true;
        $('#list-teams input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionTeams.add(this.value); 
        });
        setTimeout(() => {
            updatingFromBubble = false;
            poblarFiltrosInteligentes();
            updateBadges();
        }, 10);
    });
    
    $('#none-teams').on('click', function () {
        updatingFromBubble = true;
        $('#list-teams input[type=checkbox]').each(function () { this.checked = false; });
        seleccionTeams.clear();
        setTimeout(() => {
            updatingFromBubble = false;
            poblarFiltrosInteligentes();
            updateBadges();
        }, 10);
    });

    $('#all-mes').on('click', function () {
        $('#list-mes input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionMeses.add(normalizaMes(this.value)); 
        });
        updateBadges();
    });
    $('#none-mes').on('click', function () {
        $('#list-mes input[type=checkbox]').each(function () { this.checked = false; });
        seleccionMeses.clear(); 
        updateBadges();
    });

    $('#all-dias').on('click', function () {
        $('#list-dias input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionDias.add(normalizaDia(this.value)); 
        });
        updateBadges();
    });
    $('#none-dias').on('click', function () {
        $('#list-dias input[type=checkbox]').each(function () { this.checked = false; });
        seleccionDias.clear(); 
        updateBadges();
    });

    $('#all-festivos').on('click', function () {
        $('#list-festivos input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionFestivos.add(this.value); 
        });
        updateBadges();
    });
    $('#none-festivos').on('click', function () {
        $('#list-festivos input[type=checkbox]').each(function () { this.checked = false; });
        seleccionFestivos.clear(); 
        updateBadges();
    });

    $('#all-justificados').on('click', function () {
        $('#list-justificados input[type=checkbox]').each(function () { 
            this.checked = true; 
            seleccionJustificados.add(this.value); 
        });
        updateBadges();
    });
    $('#none-justificados').on('click', function () {
        $('#list-justificados input[type=checkbox]').each(function () { this.checked = false; });
        seleccionJustificados.clear(); 
        updateBadges();
    });

    // Botón Aplicar filtros
    $('#btn-aplicar').on('click', function () {
        aplicarFiltros();
        $('.dd-panel').removeClass('open'); // Cerrar paneles
    });

    // Event listener para clicks fuera de paneles
    document.addEventListener('click', closePanelsOnOutsideClick);

    // INICIALIZACIÓN: Cargar filtros y datos iniciales
    Promise.all([
        cargarValoresFiltros(),    // Cargar valores para filtros
        cargarDatosIniciales()     // Cargar datos iniciales (vista general por defecto)
    ]).then(() => {
        console.log('Aplicación inicializada con filtros inteligentes');
        if (window.viewManager) {
            console.log('Sistema de vistas activo');
        }
        // Actualiza qué filtros se muestran según la vista actual (general/resumen)
        actualizarUIporVista();
    }).catch(error => {
        console.error('Error en inicialización:', error);
        $('#error-container').html('<div class="error">Error al inicializar la aplicación: ' + error + '</div>').show();
    });

    // Al cambiar de vista, refrescar la UI de filtros (ocultar/mostrar grupos)
    $(document).on('click', '.view-btn', function () {
        // Deja que ViewManager recree la tabla y luego ajusta la UI
        setTimeout(actualizarUIporVista, 0);
    });

});

window.cargarDatosIniciales = cargarDatosIniciales;
window.cargarDatosResumen   = cargarDatosResumen;