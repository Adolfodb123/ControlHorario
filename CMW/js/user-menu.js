// user-menu.js - Menú de usuario (versión robusta con Exportar Excel)

document.addEventListener('DOMContentLoaded', function () {
    // ===== Cache de elementos (con tolerancia si faltan) =====
    const menuTrigger     = document.getElementById('user-menu-trigger');
    const menuDropdown    = document.getElementById('user-menu-dropdown');
    const menuOverlay     = document.getElementById('menu-overlay');
    const updateDataBtn   = document.getElementById('update-data-btn');
    const logoutBtn       = document.getElementById('logout-btn');
    const exportExcelBtn  = document.getElementById('export-excel-btn'); // <- AHORA DENTRO

    // ===== Notificaciones =====
    function mostrarNotificacion(mensaje, tipo = 'info') {
        const n = document.createElement('div');
        n.className = `notification notification-${tipo}`;
        const iconClass = tipo === 'success' ? 'fas fa-check-circle'
                        : tipo === 'error'   ? 'fas fa-exclamation-triangle'
                        : 'fas fa-info-circle';
        n.innerHTML = `
            <div class="notification-content">
              <span class="notification-icon"><i class="${iconClass}"></i></span>
              <span class="notification-message">${mensaje}</span>
            </div>`;
        Object.assign(n.style, {
            position:'fixed', top:'20px', right:'20px', zIndex:'9999',
            padding:'15px 20px', borderRadius:'8px', color:'#fff',
            fontWeight:'500', fontSize:'14px',
            boxShadow:'0 4px 12px rgba(0,0,0,.15)',
            transform:'translateX(400px)', transition:'all .3s ease',
            maxWidth:'400px',
            background: tipo==='success'
              ? 'linear-gradient(135deg,#27ae60,#2ecc71)'
              : tipo==='error'
              ? 'linear-gradient(135deg,#e74c3c,#c0392b)'
              : 'linear-gradient(135deg,#667a8a,#8b9dc3)'
        });
        n.querySelector('.notification-content').style.cssText = 'display:flex;align-items:center;gap:10px;';
        document.body.appendChild(n);
        setTimeout(()=>{ n.style.transform='translateX(0)'; },100);
        setTimeout(()=>{ n.style.transform='translateX(400px)'; setTimeout(()=>n.remove(),300); },4000);
        n.addEventListener('click', ()=>{ n.style.transform='translateX(400px)'; setTimeout(()=>n.remove(),300); });
    }

    // ===== Menú (open/close/toggle) =====
    function openMenu() {
        if (!menuDropdown || !menuOverlay || !menuTrigger) return;
        menuDropdown.classList.add('show');
        menuOverlay.classList.add('show');
        menuTrigger.classList.add('active');
        const items = menuDropdown.querySelectorAll('.menu-item');
        items.forEach((it,i)=>{
            it.style.opacity='0'; it.style.transform='translateX(10px)';
            setTimeout(()=>{ it.style.transition='all .2s ease'; it.style.opacity='1'; it.style.transform='translateX(0)'; }, i*50);
        });
    }
    function closeMenu() {
        if (!menuDropdown || !menuOverlay || !menuTrigger) return;
        menuDropdown.classList.remove('show');
        menuOverlay.classList.remove('show');
        menuTrigger.classList.remove('active');
    }
    function toggleMenu() {
        if (!menuDropdown) return;
        menuDropdown.classList.contains('show') ? closeMenu() : openMenu();
    }

    // Listeners del menú (con null-check)
    if (menuTrigger) {
        menuTrigger.addEventListener('click', (e)=>{ e.stopPropagation(); toggleMenu(); });
    }
    if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);
    document.addEventListener('click', (e)=>{
        if (!menuTrigger || !menuDropdown) return;
        if (!menuTrigger.contains(e.target) && !menuDropdown.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeMenu(); });

    // ===== Utilidades de botón en carga =====
    function setLoadingState(btn, text='Procesando...') {
        if (!btn) return { restore: ()=>{} };
        const iconEl = btn.querySelector('.menu-item-icon i');
        const textEl = btn.querySelector('.menu-item-text');
        const snapshot = {
            classList: new Set(btn.classList),
            iconClass: iconEl ? iconEl.className : '',
            text:      textEl ? textEl.textContent : ''
        };
        btn.classList.add('loading');
        if (iconEl) iconEl.className = 'fas fa-spinner';
        if (textEl) textEl.textContent = text;
        return {
            restore: (delay=1000)=>{
                setTimeout(()=>{
                    btn.className = Array.from(snapshot.classList).join(' ');
                    if (iconEl) iconEl.className = snapshot.iconClass;
                    if (textEl) textEl.textContent = snapshot.text;
                }, delay);
            }
        };
    }

    // ===== Acciones (Actualizar datos / Logout) =====
    async function actualizarDatos() {
        const state = setLoadingState(updateDataBtn, 'Actualizando...');
        try {
            await fetch('api/sync_dias.php');
            if (typeof window.cargarDatosIniciales === 'function') {
                await window.cargarDatosIniciales();
            } else {
                window.location.reload();
                return;
            }
            if (typeof window.actualizarBadgeSolicitudes === 'function') {
                await window.actualizarBadgeSolicitudes();
            }
            mostrarNotificacion('Datos actualizados correctamente', 'success');
        } catch(err) {
            console.error(err);
            mostrarNotificacion('Error de conexión al actualizar datos', 'error');
        } finally {
            state.restore();
        }
    }
    function logout() {
        if (!logoutBtn) { window.location.href='logout.php'; return; }
        if (confirm('¿Está seguro de que desea cerrar sesión?')) {
            const state = setLoadingState(logoutBtn, 'Cerrando sesión...');
            window.location.href = 'logout.php';
        }
    }

    // ===== Exportar a Excel =====
    function leerFiltrosActivos() {
        const f = {};
        try {
            if (typeof seleccionEmps        !== 'undefined' && seleccionEmps.size        > 0) f.empleados    = Array.from(seleccionEmps);
            if (typeof seleccionTeams       !== 'undefined' && seleccionTeams.size       > 0) f.equipos      = Array.from(seleccionTeams);
            if (typeof seleccionMeses       !== 'undefined' && seleccionMeses.size       > 0) f.meses        = Array.from(seleccionMeses);
            if (typeof seleccionDias        !== 'undefined' && seleccionDias.size        > 0) f.dias         = Array.from(seleccionDias);
            if (typeof seleccionFestivos    !== 'undefined' && seleccionFestivos.size    > 0) f.festivos     = Array.from(seleccionFestivos);
            if (typeof seleccionJustificados!== 'undefined' && seleccionJustificados.size> 0) f.justificados = Array.from(seleccionJustificados);
        } catch(e){ console.warn('No se pudieron leer algunos filtros:', e); }
        return f;
    }
function obtenerExportUrlBase() {
  // 1º: si hay data-export-url, úsala
  if (exportExcelBtn && exportExcelBtn.dataset.exportUrl) {
    // Por si te pasan ruta relativa, la normalizamos
    const u = new URL(exportExcelBtn.dataset.exportUrl, window.location.origin);
    return u.pathname + u.search; // solo path+query
  }
  // 2º: por defecto, la página actual (index.php)
  return window.location.pathname;
}

async function exportarAExcel() {
  if (!exportExcelBtn) {
    mostrarNotificacion('No se encontró el botón de exportación','error');
    return;
  }
  const state = setLoadingState(exportExcelBtn, 'Exportando...');
  try {
    const filtrosActivos = leerFiltrosActivos();
    const limite = document.getElementById('limite-registros')
      ? (document.getElementById('limite-registros').value || 'ALL')
      : 'ALL';

    const base = obtenerExportUrlBase();
    const qs = new URLSearchParams({ ajax:'1', action:'export_excel', limite:String(limite) });
    if (Object.keys(filtrosActivos).length > 0) qs.append('filtros', JSON.stringify(filtrosActivos));
    const url = base + (base.includes('?') ? '&' : '?') + qs.toString();

    const resp = await fetch(url, { method:'GET', credentials:'same-origin' });
    if (!resp.ok) {
      mostrarNotificacion(`Error ${resp.status} en la exportación`, 'error');
      return;
    }

    const ct = (resp.headers.get('content-type') || '').toLowerCase();

    // Solo descargamos si realmente es Excel/XML
    const isXlsx = ct.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    const isXls  = ct.includes('application/vnd.ms-excel');
    const isXml  = ct.includes('application/xml') || ct.includes('text/xml');

    if (!isXlsx && !isXls && !isXml) {
      const txt = await resp.text(); // probablemente HTML/JSON (login, error, etc.)
      console.error('Respuesta no-Excel:', ct, txt.slice(0, 500));
      mostrarNotificacion('La exportación devolvió HTML/JSON. Revisa la URL o la sesión.', 'error');
      return;
    }

    const blob = await resp.blob();
    if (blob.size === 0) {
      mostrarNotificacion('Archivo vacío recibido', 'error');
      return;
    }

    // Nombre desde cabecera si viene; si no, elegimos extensión según content-type
    let filename = `control_horario_${new Date().toISOString().slice(0,10)}${isXlsx ? '.xlsx' : (isXml || isXls ? '.xls' : '')}`;
    const cd = resp.headers.get('content-disposition') || resp.headers.get('Content-Disposition');
    if (cd && cd.includes('filename=')) {
      const m = cd.match(/filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i);
      if (m && m[1]) filename = decodeURIComponent(m[1]);
    }

    const urlBlob = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = urlBlob;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(urlBlob);

    const numFiltros = Object.keys(filtrosActivos).length;
    mostrarNotificacion(
      numFiltros > 0 ? `Excel exportado con ${numFiltros} filtro(s)` : 'Excel exportado con todos los datos',
      'success'
    );

  } catch (err) {
    console.error('Error en exportación:', err);
    mostrarNotificacion('Error al exportar Excel', 'error');
  } finally {
    state.restore(800);
    closeMenu();
  }
}

    // ===== Listeners =====
    if (updateDataBtn) updateDataBtn.addEventListener('click', (e)=>{ e.preventDefault(); actualizarDatos(); closeMenu(); });
    if (logoutBtn)     logoutBtn    .addEventListener('click', (e)=>{ e.preventDefault(); logout(); });
    if (exportExcelBtn)exportExcelBtn.addEventListener('click', (e)=>{ e.preventDefault(); exportarAExcel(); });

    // ===== API global segura =====
    window.userMenuActions = { actualizarDatos, exportarAExcel, mostrarNotificacion, closeMenu };
});
