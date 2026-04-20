// ViewManager.js - Sistema de gestión de vistas (General/Resumen) - ACTUALIZADO

class ViewManager {
    constructor() {
        this.currentView = 'general'; // 'general' o 'resumen'
        this.tablaEmpleados = null;
        this.datosGlobales = [];
        
        // Configuraciones de columnas para cada vista
        this.viewConfigs = {
            general: {
                columnCount: 12,
                columnDefs: [
                    { targets: 1, className: 'col-date-small', width: 100,
                      render: (d,t) => (t==='sort'||t==='type') ? this.isoFromSqlDate(d) : this.displayDMY(d) },
                    { targets: 3, className: 'col-time-small', width: 85,
                      render: (d,t) => (t==='sort'||t==='type') ? this.timeHHMMSS(d) : this.renderTimeBadge(d, 'entrada') },
                    { targets: 4, className: 'col-time-small', width: 85,
                      render: (d,t) => (t==='sort'||t==='type') ? this.timeHHMMSS(d) : this.renderTimeBadge(d, 'salida') },
                    // COLUMNAS DE HORAS AGRUPADAS (7, 8, 9)
                    { targets: [7,8,9], className: 'col-time-small', width: 75,
                      render: (d,t) => { const n=Number(d)||0; return (t==='sort'||t==='type')? n : this.minutosAHHMM(n); } },
                    { targets: 2, className: 'col-status', width: 60 },
                    // FESTIVO AHORA EN POSICIÓN 10
                    { targets: 10, className: 'col-status', width: 60,
                      render: (d,t) => { const v=this.toBool01(d); return (t==='sort'||t==='type')? v : (v?'Sí':'No'); } },
                    // JUSTIFICADO AHORA EN POSICIÓN 11
                    { targets: 11, className: 'col-status', width: 60,
                      render: (d,t) => { const v=this.toBool01(d); return (t==='sort'||t==='type')? v : (v?'Sí':'No'); } }
                ]
            },
            resumen: {
                columnCount: 11,
                columnDefs: [
                    { targets: 0, className: 'col-name',  width: 180 },  // Empleado
                    { targets: 1, className: 'col-team',  width: 120 },  // Equipo
                    { targets: 2, className: 'col-month', width: 110 },  // Mes
                    { targets: [3,4,5,6,7], className: 'col-num', width: 95 }, // días
                    { targets: [8,9,10], className: 'col-time-small', width: 110 } // hh:MM
                ]
            }
        };
    }

    // Inicializar el gestor de vistas
    init(tablaEmpleados) {
        this.tablaEmpleados = tablaEmpleados;
        this.createViewButtons();
        this.bindEvents();
        console.log('ViewManager inicializado');
    }

    // Crear los botones de vista
    createViewButtons() {
        const viewButtonsHtml = `
            <div class="view-buttons" id="view-buttons">
                <button id="btn-vista-general" class="view-btn active" data-view="general">
                    <i class="fas fa-table"></i> Vista General
                </button>
            </div>
        `;
        
        // Insertar los botones en el header, después del título
        $('.header-container h1').after(viewButtonsHtml);
    }

    // Vincular eventos de los botones
    bindEvents() {
        $(document).on('click', '.view-btn', (e) => {
            const targetView = $(e.currentTarget).data('view');
            this.switchView(targetView);
        });
    }

    // Cambiar entre vistas

    switchView(newView) {
        if (this.currentView === newView) return;

        console.log(`Cambiando vista de ${this.currentView} a ${newView}`);

        // Estado visual de botones
        $('.view-btn').removeClass('active');
        $(`.view-btn[data-view="${newView}"]`).addClass('active');

        // NUEVO: Limpiar filtros específicos de la vista anterior
        if (this.currentView === 'general' && newView === 'resumen') {
            // Al cambiar a resumen, limpiar filtros no aplicables
            if (typeof window.seleccionDias !== 'undefined') {
                window.seleccionDias.clear();
            }
            if (typeof window.seleccionFestivos !== 'undefined') {
                window.seleccionFestivos.clear();
            }
            if (typeof window.seleccionJustificados !== 'undefined') {
                window.seleccionJustificados.clear();
            }
            
            // Desmarcar checkboxes de filtros no aplicables
            $('#list-dias input[type=checkbox], #list-festivos input[type=checkbox], #list-justificados input[type=checkbox]')
                .prop('checked', false);
                
            // Actualizar contadores
            if (typeof window.updateBadges === 'function') {
                window.updateBadges();
            }
        }

        // Cambiar vista actual y recrear la tabla
        this.currentView = newView;
        this.recreateTable();

        // MEJORADO: Cargar datos según la vista CON manejo de filtros de seguridad
        if (this.currentView === 'resumen') {
            // Para resumen, usar la función que aplica filtros de seguridad
            if (typeof window.cargarDatosResumen === 'function') {
                window.cargarDatosResumen();
            } else {
                console.warn('cargarDatosResumen no está definido');
            }
        } else {
            // Para general, usar la función estándar que ya aplica filtros de seguridad
            if (typeof window.cargarDatosIniciales === 'function') {
                window.cargarDatosIniciales();
            } else {
                console.warn('cargarDatosIniciales no está definido');
            }
        }
        
        console.log(`Vista ${newView} activada con filtros de seguridad aplicados`);
    }

    // Crear los headers HTML según la vista - ORDEN ACTUALIZADO
    getHeadersHtml(viewType) {
        if (viewType === 'general') {
            return `
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
            `;
        } else {
            return `
                <th>Empleado</th>
                <th>Equipo</th>
                <th>Mes</th>
                <th>Total días</th>
                <th>Días trabajados</th>
                <th>Laborables teóricos</th>
                <th>Festivos</th>
                <th>Permisos</th>
                <th>H. Trabajadas</th>
                <th>H. Exceso</th>
                <th>H. Faltantes</th>
            `;
        }
    }

    // Recrear la tabla con la configuración de la vista actual
    recreateTable() {
        if (!this.tablaEmpleados) return;
        
        // Limpiar elementos de paginación existentes antes de destruir
        $('.dataTables_info').remove();
        $('.dataTables_paginate').remove();
        $('.dataTables_wrapper .dataTables_paginate').remove();
        $('.dataTables_wrapper .dataTables_info').remove();
        
        // CRÍTICO: Limpiar datos antes de destruir para evitar conflictos
        this.tablaEmpleados.clear();
        this.tablaEmpleados.draw();
        
        // Destruir tabla actual completamente
        this.tablaEmpleados.destroy(true); // true para remover del DOM
        
        // Obtener configuración de la vista actual
        const config = this.viewConfigs[this.currentView];
        
        // Recrear la tabla en el contenedor original
        const headerRow = this.getHeadersHtml(this.currentView);
        const tableContainer = $('.table-scroll-wrapper');
        tableContainer.html(`
            <table id="tabla-empleados" class="display responsive nowrap" style="width:100%">
                <thead><tr>${headerRow}</tr></thead>
                <tbody></tbody>
            </table>
        `);
        
        // Configuración base común
        const baseConfig = {
            responsive: false,
            autoWidth: false,
            scrollX: false,
            pageLength: 100,
            lengthMenu: [[10,25,50,100,-1],[10,25,50,100,"Todos"]],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            dom: '<"table-top"Brt><"table-bottom"ip>',
            buttons: ['print'],
            
            drawCallback: function () {
                // Usar setTimeout para ejecutar después de que DataTables termine completamente
                setTimeout(() => {
                    // Solo mover si los elementos existen y no están ya en su lugar correcto
                    const $info = $('.dataTables_info').not('.bottom-left-controls .dataTables_info');
                    const $paginate = $('.dataTables_paginate').not('#table-pagination .dataTables_paginate');
                    
                    if ($info.length > 0) {
                        $('.bottom-left-controls .dataTables_info').remove();
                        $info.appendTo('.bottom-left-controls');
                    }
                    
                    if ($paginate.length > 0) {
                        $('#table-pagination .dataTables_paginate').remove();
                        $paginate.appendTo('#table-pagination');
                    }
                }, 10);
            }
        };
        
        // Agregar configuración específica de la vista
        if (this.currentView === 'general') {
            baseConfig.columnDefs = config.columnDefs;
            baseConfig.rowCallback = (row, data) => {
                $(row).removeClass('exceso faltante festivo');
                // ACTUALIZAR ÍNDICES: Exceso ahora en posición 8, faltantes en 9, festivo en 10
                const exceso = Number(data[8]) || 0;
                const faltantes = Number(data[9]) || 0;
                const festivo = this.toBool01(data[10]);
                
                if (exceso > 0) $(row).addClass('exceso');
                else if (faltantes > 0) $(row).addClass('faltante');
                else if (festivo === 1) $(row).addClass('festivo');
            };
        } else {
            // Para vista resumen
            baseConfig.columnDefs = config.columnDefs;
        }

        // Recrear DataTable con nueva configuración
        this.tablaEmpleados = $('#tabla-empleados').DataTable(baseConfig);
        
        // IMPORTANTE: No recargar datos aquí - se hará desde switchView()
        
        console.log(`Vista ${this.currentView} aplicada correctamente`);
    }

    // Actualizar datos en la tabla actual - ORDEN ACTUALIZADO
    updateTableData(datos) {
        if (!this.tablaEmpleados) return;
        
        this.datosGlobales = datos;
        
        // Limpiar tabla
        this.tablaEmpleados.clear();
        
        const processedData = datos;

        // Agregar datos
        processedData.forEach(fila => {
            if (this.currentView === 'resumen') {
                this.tablaEmpleados.row.add([
                    fila.nombre_empleado,
                    fila.Equipo,
                    fila.nombre_mes,
                    fila.total_dias_mes,
                    fila.dias_trabajados,
                    fila.dias_laborables_teoricos,
                    fila.dias_festivos,
                    fila.dias_permiso,
                    fila.horas_trabajadas_hhMM,
                    fila.horas_exceso_hhMM,
                    fila.horas_faltantes_hhMM
                ]);
            } else {
                // NUEVO: Función helper para valores seguros
                const safe = (v) => (v === null || v === undefined ? '' : v);

                // NUEVO ORDEN: H.Trabajados, Exceso, Faltantes juntas, luego Festivo y Justificado
                this.tablaEmpleados.row.add([
                    safe(fila.full_name),           // 0
                    safe(fila.date),                // 1
                    safe(fila.dia_semana),          // 2
                    safe(fila.clock_in),            // 3
                    safe(fila.clock_out),           // 4
                    safe(fila.role_name),           // 5
                    safe(fila.Equipo),              // 6
                    safe(fila.minutos_trabajados),  // 7 - H. Trabajados
                    safe(fila.exceso_min),          // 8 - Exceso H.
                    safe(fila.faltantes_min),       // 9 - Faltantes H.
                    safe(fila.Festivo),             // 10 - Festivo
                    safe(fila.Justificado)          // 11 - Justificado
                ]);
            }
        });
        
        this.tablaEmpleados.draw();
    }

    // === FUNCIONES UTILIDAD ===
    minutosAHHMM(minutos) {
        if (minutos === null || minutos === undefined || minutos === 0) {
            return '00:00';
        }
        
        const total = Math.round(Math.abs(minutos));
        const horas = Math.floor(total / 60);
        const mins = total % 60;
        const signo = minutos < 0 ? '-' : '';

        return signo + horas.toString().padStart(2, '0') + ':' + mins.toString().padStart(2, '0');
    }

    isoFromSqlDate(val) {
        if (!val) return '';
        return String(val).slice(0, 10);
    }

    displayDMY(val) {
        const iso = this.isoFromSqlDate(val);
        if (!iso) return '';
        const [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    }

    timeHHMMSS(val) {
        if (!val) return '00:00:00';
        const t = String(val).includes(' ') ? String(val).split(' ')[1] : String(val);
        return t.length === 5 ? (t + ':00') : t;
    }

    // Convierte "HH:MM:SS" o "HH:MM" a minutos desde medianoche
    timeToMinutes(val) {
        if (!val) return null;
        const t = String(val).trim().split(':');
        if (t.length < 2) return null;
        return parseInt(t[0], 10) * 60 + parseInt(t[1], 10);
    }

    // Renderiza un badge de color según si la hora es puntual, tarde o con horas extra
    // Referencia horario: entrada 07:00, salida 15:00
    // Tolerancia: ±15 min considerada "normal"
    renderTimeBadge(val, tipo) {
        const hhmm = this.timeHHMMSS(val).substring(0, 5);
        if (!val || hhmm === '00:00') return '<span class="time-empty">—</span>';

        const min = this.timeToMinutes(hhmm);
        let cls = 'time-ok';

        if (tipo === 'entrada') {
            // Tarde si llega después de 07:15 (7*60+15 = 435)
            if (min > 435) cls = 'time-late';
        } else {
            // Salida anticipada si sale antes de 14:45 (14*60+45 = 885)
            if (min < 885) cls = 'time-late';
            // Horas extra si sale después de 15:30 (15*60+30 = 930)
            else if (min > 930) cls = 'time-overtime';
        }

        return `<span class="time-badge ${cls}">${hhmm}</span>`;
    }

    toBool01(x) {
        const s = String(x).trim().toLowerCase();
        return (x === 1 || x === true || s === '1' || s === 'si' || s === 'sí' || s === 'yes') ? 1 : 0;
    }

    // Getters para integración con código existente
    getCurrentView() {
        return this.currentView;
    }

    getDataTable() {
        return this.tablaEmpleados;
    }
}

// Instancia global del gestor de vistas
window.viewManager = new ViewManager();