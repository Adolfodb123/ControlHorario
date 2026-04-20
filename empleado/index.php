<?php
require_once __DIR__ . '/../auth_check.php';
requerir_rol_empleado();

$nombre    = $_SESSION['user_display_name'] ?? $_SESSION['user'];
$usuarioId = (int)$_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Portal — Control Horario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f7;
            min-height: 100vh;
            color: #333;
        }

        /* ── Header ── */
        .topbar {
            background: #587587;
            color: white;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .topbar h1 { font-size: 1.1rem; font-weight: 600; }
        .topbar .user-info { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; }
        .topbar .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.95rem;
        }
        .btn-logout {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white; padding: 6px 14px; border-radius: 6px;
            text-decoration: none; font-size: 0.82rem;
            transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.25); }

        /* ── Layout ── */
        .main { max-width: 900px; margin: 28px auto; padding: 0 16px; }

        /* ── Fecha ── */
        .date-banner {
            text-align: center;
            color: #587587;
            font-size: 0.9rem;
            margin-bottom: 24px;
            font-weight: 500;
        }

        /* ── Fichaje card ── */
        .fichaje-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 2px 16px rgba(88,117,135,0.12);
            margin-bottom: 20px;
        }
        .fichaje-card .clock-display {
            font-size: 3rem;
            font-weight: 300;
            color: #587587;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .fichaje-card .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .status-dentro  { background: #d4edda; color: #155724; }
        .status-fuera   { background: #f8d7da; color: #721c24; }
        .status-sin     { background: #e9ecef; color: #666; }

        .fichaje-times { display: flex; justify-content: center; gap: 32px; margin-bottom: 28px; }
        .fichaje-times .time-item { text-align: center; }
        .fichaje-times .time-label { font-size: 0.75rem; color: #999; text-transform: uppercase; letter-spacing: 0.05em; }
        .fichaje-times .time-val { font-size: 1.3rem; font-weight: 600; color: #444; margin-top: 2px; }
        .time-val.empty { color: #ccc; }

        .btn-fichar {
            padding: 16px 48px;
            border: none; border-radius: 50px;
            font-size: 1.05rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-entrada { background: #28a745; color: white; box-shadow: 0 4px 15px rgba(40,167,69,0.35); }
        .btn-entrada:hover { background: #218838; transform: translateY(-1px); }
        .btn-salida  { background: #dc3545; color: white; box-shadow: 0 4px 15px rgba(220,53,69,0.35); }
        .btn-salida:hover  { background: #c82333; transform: translateY(-1px); }
        .btn-fichar:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

        /* ── Grid ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 860px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 540px) { .grid-2 { grid-template-columns: 1fr; } }

        /* ── Panels ── */
        .panel {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 16px rgba(88,117,135,0.10);
        }
        .panel h2 {
            font-size: 1rem;
            font-weight: 600;
            color: #587587;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }
        .panel h2 i { font-size: 0.9rem; }

        /* ── Forms ── */
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-size: 0.78rem; font-weight: 600; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.04em; }
        .form-row input, .form-row textarea, .form-row select {
            width: 100%; padding: 9px 12px;
            border: 2px solid #e0e6ea; border-radius: 8px;
            font-size: 0.9rem; outline: none; transition: border-color 0.2s;
            font-family: inherit;
        }
        .form-row input:focus, .form-row textarea:focus { border-color: #587587; }
        .form-row textarea { resize: vertical; min-height: 70px; }

        .btn-submit {
            width: 100%; padding: 11px;
            background: #587587; color: white;
            border: none; border-radius: 8px;
            font-size: 0.92rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #3d5a6b; }

        /* ── Solicitudes list ── */
        .solicitud-item {
            padding: 11px 0;
            border-bottom: 1px solid #f0f4f7;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .solicitud-item:last-child { border-bottom: none; }
        .sol-icon { font-size: 1.2rem; margin-top: 2px; }
        .sol-info { flex: 1; }
        .sol-tipo { font-size: 0.82rem; font-weight: 600; color: #444; }
        .sol-fecha { font-size: 0.78rem; color: #888; }
        .sol-estado {
            font-size: 0.72rem; font-weight: 700;
            padding: 3px 10px; border-radius: 12px;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .estado-pendiente  { background: #fff3cd; color: #856404; }
        .estado-aprobada   { background: #d4edda; color: #155724; }
        .estado-rechazada  { background: #f8d7da; color: #721c24; }

        .empty-state { text-align: center; color: #bbb; font-size: 0.88rem; padding: 20px 0; }

        /* ── Balance días ── */
        .balance-card {
            background: white;
            border-radius: 16px;
            padding: 20px 28px;
            box-shadow: 0 2px 16px rgba(88,117,135,0.10);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
        }
        .balance-card h3 {
            font-size: 0.8rem;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            width: 100%;
            margin-bottom: 14px;
        }
        .balance-item {
            flex: 1;
            min-width: 130px;
            text-align: center;
            padding: 0 16px;
            border-right: 1px solid #f0f4f7;
        }
        .balance-item:last-child { border-right: none; }
        .balance-label {
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 6px;
        }
        .balance-nums {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 4px;
        }
        .balance-restantes {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        .balance-total {
            font-size: 0.82rem;
            color: #bbb;
        }
        .balance-bar {
            width: 100%;
            height: 5px;
            background: #eee;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        .balance-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }
        .color-vac  { color: #587587; }
        .fill-vac   { background: #587587; }
        .color-ld   { color: #7c6fa0; }
        .fill-ld    { background: #7c6fa0; }
        .color-warn { color: #d97706; }
        .color-low  { color: #dc3545; }

        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: #333; color: white; padding: 12px 24px; border-radius: 8px;
            font-size: 0.9rem; z-index: 999;
            opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        .toast.show { opacity: 1; }
        .toast.ok   { background: #28a745; }
        .toast.err  { background: #dc3545; }

        /* ── File upload ── */
        .file-drop {
            border: 2px dashed #b0c4ce;
            border-radius: 8px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
            background: #f8fbfc;
        }
        .file-drop:hover, .file-drop.drag-over {
            border-color: #587587;
            background: #edf4f8;
        }
        .file-drop input[type="file"] {
            position: absolute; inset: 0; width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
        }
        .file-drop .drop-icon { font-size: 1.4rem; color: #587587; margin-bottom: 4px; }
        .file-drop .drop-text { font-size: 0.8rem; color: #888; }
        .file-drop .drop-text strong { color: #587587; }
        .file-selected {
            display: flex; align-items: center; gap: 8px;
            background: #d4edda; border-radius: 6px;
            padding: 7px 10px; margin-top: 8px; font-size: 0.82rem; color: #155724;
        }
        .file-selected .remove-file {
            margin-left: auto; cursor: pointer; color: #721c24;
            font-size: 0.9rem; background: none; border: none; padding: 0;
        }
    </style>
</head>
<body>

<div class="topbar">
    <h1><i class="fas fa-clock"></i> Control Horario</h1>
    <div class="user-info">
        <div class="avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
        <span><?= htmlspecialchars($nombre) ?></span>
        <a href="/ControlHorarioCMW-Test-2/CMW/logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Salir
        </a>
    </div>
</div>

<div class="main">
    <div class="date-banner" id="date-banner"></div>

    <!-- Fichaje -->
    <div class="fichaje-card">
        <div class="clock-display" id="reloj">--:--</div>
        <div class="status-badge status-sin" id="status-badge">Cargando...</div>
        <div class="fichaje-times">
            <div class="time-item">
                <div class="time-label">Entrada</div>
                <div class="time-val empty" id="hora-entrada">—</div>
            </div>
            <div class="time-item">
                <div class="time-label">Salida</div>
                <div class="time-val empty" id="hora-salida">—</div>
            </div>
        </div>
        <button class="btn-fichar btn-entrada" id="btn-fichar" onclick="fichar()">
            <i class="fas fa-sign-in-alt"></i> <span id="btn-text">Registrar Entrada</span>
        </button>
    </div>

    <!-- Balance de días -->
    <div class="balance-card">
        <h3><i class="fas fa-calendar-check"></i> &nbsp;Mis días disponibles — <?= date('Y') ?></h3>

        <div class="balance-item">
            <div class="balance-label">Vacaciones</div>
            <div class="balance-nums">
                <span class="balance-restantes color-vac" id="bal-vac-rest">—</span>
                <span class="balance-total"> / <span id="bal-vac-total">22</span> días</span>
            </div>
            <div style="font-size:0.72rem;color:#aaa;margin-top:2px;"><span id="bal-vac-usados">0</span> usados</div>
            <div class="balance-bar"><div class="balance-bar-fill fill-vac" id="bar-vac" style="width:0%"></div></div>
        </div>

        <div class="balance-item">
            <div class="balance-label">Libre Disposición</div>
            <div class="balance-nums">
                <span class="balance-restantes color-ld" id="bal-ld-rest">—</span>
                <span class="balance-total"> / <span id="bal-ld-total">6</span> días</span>
            </div>
            <div style="font-size:0.72rem;color:#aaa;margin-top:2px;"><span id="bal-ld-usados">0</span> usados</div>
            <div class="balance-bar"><div class="balance-bar-fill fill-ld" id="bar-ld" style="width:0%"></div></div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Solicitar vacaciones -->
        <div class="panel">
            <h2><i class="fas fa-umbrella-beach"></i> Solicitar Vacaciones</h2>
            <form id="form-vacaciones" onsubmit="enviarSolicitud(event, 'vacaciones')">
                <div class="form-row">
                    <label>Desde</label>
                    <input type="date" name="fecha_inicio" required>
                </div>
                <div class="form-row">
                    <label>Hasta</label>
                    <input type="date" name="fecha_fin" required>
                </div>
                <div class="form-row">
                    <label>Motivo (opcional)</label>
                    <textarea name="motivo" placeholder="Vacaciones de verano..."></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Enviar Solicitud</button>
            </form>
        </div>

        <!-- Justificar ausencia -->
        <div class="panel">
            <h2><i class="fas fa-file-medical"></i> Justificar Ausencia</h2>
            <form id="form-justificacion" onsubmit="enviarSolicitud(event, 'justificacion')">
                <div class="form-row">
                    <label>Fecha de la ausencia</label>
                    <input type="date" name="fecha_inicio" required>
                </div>
                <div class="form-row">
                    <label>Motivo <span style="color:red">*</span></label>
                    <textarea name="motivo" placeholder="Baja médica, asuntos personales..." required></textarea>
                </div>
                <div class="form-row">
                    <label>Documento adjunto <span style="color:#888;font-weight:400">(opcional · PDF, JPG, PNG · máx. 5 MB)</span></label>
                    <div class="file-drop" id="file-drop-area">
                        <input type="file" name="documento" id="input-documento" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                        <div class="drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="drop-text">Arrastra aquí o <strong>haz clic para seleccionar</strong></div>
                    </div>
                    <div class="file-selected" id="file-selected-info" style="display:none">
                        <i class="fas fa-paperclip"></i>
                        <span id="file-selected-name"></span>
                        <button type="button" class="remove-file" onclick="quitarArchivo()" title="Quitar archivo">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Enviar Justificación</button>
            </form>
        </div>

        <!-- Libre disposición -->
        <div class="panel">
            <h2><i class="fas fa-calendar-day" style="color:#7c6fa0"></i> Libre Disposición</h2>
            <form id="form-libre" onsubmit="enviarSolicitud(event, 'libre_disposicion')">
                <div class="form-row">
                    <label>Desde</label>
                    <input type="date" name="fecha_inicio" required>
                </div>
                <div class="form-row">
                    <label>Hasta</label>
                    <input type="date" name="fecha_fin" required>
                </div>
                <div class="form-row">
                    <label>Motivo (opcional)</label>
                    <textarea name="motivo" placeholder="Asunto personal..."></textarea>
                </div>
                <button type="submit" class="btn-submit" style="background:#7c6fa0;">
                    <i class="fas fa-paper-plane"></i> Solicitar Día
                </button>
            </form>
        </div>
    </div>

    <!-- Mis solicitudes -->
    <div class="panel">
        <h2><i class="fas fa-list-alt"></i> Mis Solicitudes</h2>
        <div id="lista-solicitudes"><div class="empty-state">Cargando...</div></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Reloj en tiempo real
function actualizarReloj() {
    const now = new Date();
    document.getElementById('reloj').textContent =
        now.getHours().toString().padStart(2,'0') + ':' +
        now.getMinutes().toString().padStart(2,'0');
    const dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    document.getElementById('date-banner').textContent =
        dias[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
}
setInterval(actualizarReloj, 1000);
actualizarReloj();

// Cargar estado inicial
async function cargarEstado() {
    const r = await fetch('api/estado_hoy.php');
    const d = await r.json();
    if (!d.ok) return;

    // Fichaje
    const badge = document.getElementById('status-badge');
    const btnText = document.getElementById('btn-text');
    const btn = document.getElementById('btn-fichar');

    if (d.entrada) {
        document.getElementById('hora-entrada').textContent = d.entrada;
        document.getElementById('hora-entrada').classList.remove('empty');
    }
    if (d.salida) {
        document.getElementById('hora-salida').textContent = d.salida;
        document.getElementById('hora-salida').classList.remove('empty');
    }

    if (d.estado === 'entrada') {
        badge.textContent = 'Dentro'; badge.className = 'status-badge status-dentro';
        btn.className = 'btn-fichar btn-salida';
        btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> <span id="btn-text">Registrar Salida</span>';
    } else if (d.estado === 'salida') {
        badge.textContent = 'Jornada completada'; badge.className = 'status-badge status-fuera';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-check"></i> <span id="btn-text">Jornada Registrada</span>';
    } else {
        badge.textContent = 'Sin fichar hoy'; badge.className = 'status-badge status-sin';
    }

    // Balance de días
    if (d.balance) {
        const b = d.balance;
        const setBalance = (restId, usadosId, barId, restantes, usados, total) => {
            const el = document.getElementById(restId);
            if (el) {
                el.textContent = restantes;
                el.className = el.className.replace(/color-\S+/g, '').trim();
                if (restantes <= 0)     el.classList.add('color-low');
                else if (restantes <= 2) el.classList.add('color-warn');
            }
            const eu = document.getElementById(usadosId);
            if (eu) eu.textContent = usados;
            const bar = document.getElementById(barId);
            if (bar) bar.style.width = Math.min(100, Math.round((usados / total) * 100)) + '%';
        };
        setBalance('bal-vac-rest', 'bal-vac-usados', 'bar-vac', b.vacaciones_restantes, b.vacaciones_usados, b.vacaciones_total);
        setBalance('bal-ld-rest',  'bal-ld-usados',  'bar-ld',  b.ld_restantes,          b.ld_usados,          b.ld_total);
    }

    // Solicitudes
    const lista = document.getElementById('lista-solicitudes');
    if (!d.solicitudes || d.solicitudes.length === 0) {
        lista.innerHTML = '<div class="empty-state">No tienes solicitudes aún</div>';
        return;
    }
    const iconos    = { vacaciones: '🏖️', justificacion: '📄', libre_disposicion: '📅' };
    const etiquetas = { vacaciones: 'Vacaciones', justificacion: 'Justificación', libre_disposicion: 'Libre Disposición' };
    lista.innerHTML = d.solicitudes.map(s => {
        const fecha = s.fecha_fin ? `${s.fecha_inicio} → ${s.fecha_fin}` : s.fecha_inicio;
        const docLink = s.documento
            ? `<a href="/ControlHorarioCMW-Test-2/empleado/uploads/${encodeURIComponent(s.documento)}" target="_blank" style="font-size:0.75rem;color:#587587;text-decoration:none;"><i class="fas fa-paperclip"></i> doc. adjunto</a>`
            : '';
        return `<div class="solicitud-item">
            <span class="sol-icon">${iconos[s.tipo] || '📋'}</span>
            <div class="sol-info">
                <div class="sol-tipo">${etiquetas[s.tipo] || s.tipo}</div>
                <div class="sol-fecha">${fecha}${s.motivo ? ' · ' + s.motivo : ''}</div>
                ${docLink}
            </div>
            <span class="sol-estado estado-${s.estado}">${s.estado}</span>
        </div>`;
    }).join('');
}
cargarEstado();

// Fichar
async function fichar() {
    const btn = document.getElementById('btn-fichar');
    btn.disabled = true;
    const r = await fetch('api/fichar.php', { method: 'POST' });
    const d = await r.json();
    if (d.ok) {
        mostrarToast(d.tipo === 'entrada' ? `✅ Entrada registrada a las ${d.hora}` : `✅ Salida registrada a las ${d.hora}`, 'ok');
        await cargarEstado();
    } else {
        mostrarToast('Error: ' + d.error, 'err');
        btn.disabled = false;
    }
}

// Enviar solicitud
async function enviarSolicitud(e, tipo) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    data.append('tipo', tipo);
    const btn = form.querySelector('.btn-submit');
    btn.disabled = true;
    const r = await fetch('api/solicitar.php', { method: 'POST', body: data });
    const d = await r.json();
    btn.disabled = false;
    if (d.ok) {
        mostrarToast('✅ Solicitud enviada correctamente', 'ok');
        form.reset();
        quitarArchivo();
        await cargarEstado();
    } else {
        mostrarToast('Error: ' + d.error, 'err');
    }
}

// Gestión del archivo adjunto
const inputDoc   = document.getElementById('input-documento');
const dropArea   = document.getElementById('file-drop-area');
const fileInfo   = document.getElementById('file-selected-info');
const fileName   = document.getElementById('file-selected-name');

if (inputDoc) {
    inputDoc.addEventListener('change', () => {
        if (inputDoc.files.length > 0) mostrarArchivoSeleccionado(inputDoc.files[0]);
    });
}
if (dropArea) {
    dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('drag-over'); });
    dropArea.addEventListener('dragleave', () => dropArea.classList.remove('drag-over'));
    dropArea.addEventListener('drop', e => {
        e.preventDefault();
        dropArea.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            // Transferir al input real
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            inputDoc.files = dt.files;
            mostrarArchivoSeleccionado(e.dataTransfer.files[0]);
        }
    });
}

function mostrarArchivoSeleccionado(file) {
    const maxMB = 5;
    if (file.size > maxMB * 1024 * 1024) {
        mostrarToast('El archivo supera los 5 MB', 'err');
        quitarArchivo();
        return;
    }
    fileName.textContent = file.name;
    fileInfo.style.display = 'flex';
    dropArea.style.display = 'none';
}

function quitarArchivo() {
    if (inputDoc) {
        inputDoc.value = '';
        // Limpiar la selección usando DataTransfer
        try { const dt = new DataTransfer(); inputDoc.files = dt.files; } catch(e) {}
    }
    if (fileInfo) fileInfo.style.display = 'none';
    if (dropArea) dropArea.style.display = 'block';
}

// Toast
function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + (tipo || '');
    setTimeout(() => t.className = 'toast', 3000);
}
</script>
</body>
</html>
