// ================= Agroverso Craft - app.js =================

// --- 1) Detección de ruta base (auto) ---
function detectApiBase() {
  const path = window.location.pathname;
  const m = path.match(/^\/([^/]+)/);
  const root = m ? `/${m[1]}` : '';
  return `${root}/api`;
}

// --- 2) Configuración general ---
const API_BASE = detectApiBase();

//const PREDIO_ID = 1;  // Predio fijo para demo / pruebas



// Lee ?predio_id=NN de la URL
function detectPredioId() {
  const u = new URL(window.location.href);
  const id = parseInt(u.searchParams.get('predio_id') || '0', 10);
  return Number.isFinite(id) && id > 0 ? id : 1; // fallback a 1
}




// --- Predio activo desde la URL (?predio_id=N), fallback a 1 ---
function getPredioIdFromURL() {
  const p = new URLSearchParams(window.location.search).get('predio_id');
  const id = parseInt(p, 10);
  return Number.isFinite(id) && id > 0 ? id : null;
}

//const PREDIO_ID = getPredioIdFromURL() ?? 1;


const PREDIO_ID = detectPredioId();



// --- 3) Helpers de UI y utilidades ---
function nowHHMMSS() {
  const d = new Date();
  return d.toLocaleTimeString();
}

function setStatus(msg, ok = true) {
  const el = document.getElementById('fx-status');
  if (!el) return;
  el.textContent = (ok ? '✔ ' : '✖ ') + msg;
  el.style.color = ok ? '#9f6' : '#ff9a9a';
}

function setLastUpdate(extra = '') {
  const el = document.getElementById('last-update');
  if (!el) return;
  el.textContent = `Última acción: ${nowHHMMSS()} ${extra}`;
}

async function fetchJSON(url, opts = {}) {
  const res = await fetch(url, opts);
  const ct = res.headers.get('content-type') || '';
  if (!ct.includes('application/json')) {
    const text = await res.text();
    throw new Error(`Respuesta no JSON (${res.status}) en ${url}\n${text.slice(0,200)}`);
  }
  const data = await res.json();
  return { res, data };
}

// --- 4) Estado global para informes ---
let LAST_RECS = [];
let LAST_CLIMA = '';

let LAST_CLIMATE_OBJ = null;   // ⬅️ nuevo: clima en objeto para cálculos




// --- 5) Clima ---
async function refreshClimate(predioId = 1) {
  const btn = document.getElementById('btn-climate');
  const box = document.getElementById('climate-info');
  if (!btn) return;

  const oldText = btn.textContent;
  btn.disabled = true; btn.textContent = 'Actualizando…';
  setStatus('Llamando /api/climate/refresh.php…');

  try {
    const { data } = await fetchJSON(`${API_BASE}/climate/refresh.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ predio_id: predioId })
    });

    if (!data.ok) {
      setStatus('Error clima: ' + (data.error || 'desconocido'), false);
      setLastUpdate('(clima error)');
      box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
      return;
    }

    const c = data.data;

    LAST_CLIMATE_OBJ = c; // guarda objeto con {avg_tmin, avg_tmax, total_prcp_mm, frost_days_est, ...}


    const el = document.getElementById('climate-data');
    if (el) {
      el.innerHTML =
        `Tmin ${c.avg_tmin}°C&nbsp;|&nbsp;Tmax ${c.avg_tmax}°C&nbsp;|&nbsp;Lluvia ${c.total_prcp_mm} mm&nbsp;|&nbsp;Heladas ${c.frost_days_est} <small>(${c.source} · ${c.period})</small>`;
      LAST_CLIMA = el.innerHTML;
    }
    setStatus('Clima actualizado');
    setLastUpdate('(clima OK)');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);

  } catch (err) {
    console.error(err);
    setStatus('Fallo conexión / ruta API (clima)', false);
    setLastUpdate('(clima fallo)');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  } finally {
    btn.disabled = false; btn.textContent = oldText;
  }
}

// --- 6) Recomendaciones ---
async function loadRecommendations(predioId = 1) {
  const list = document.getElementById('recs-list');
  const box = document.getElementById('recs-box');
  if (!list) return;

  list.textContent = 'Calculando…';
  setStatus('Llamando /api/recommendations/run.php…');

  try {
    const { data } = await fetchJSON(`${API_BASE}/recommendations/run.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ predio_id: predioId })
    });

    if (!data.ok) {
      list.innerHTML = '<p>Error: ' + (data.error || 'sin datos') + '</p>';
      setStatus('Error recomendaciones', false);
      setLastUpdate('(recs error)');
      box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
      return;
    }

    LAST_RECS = Array.isArray(data.data) ? data.data : [];

    if (!LAST_RECS.length) {
      list.innerHTML = '<p>No se requieren acciones significativas.</p>';
    } else {
      list.innerHTML = LAST_RECS.map(r => `
        <div class="recommendation">
          <strong>${r.title}</strong><br/>
          <small>${r.rationale || ''}</small><br/>
          <em>Acciones:</em> ${(r.actions || []).join(', ')}<br/>
          <small>Costo ≈ ${r.cost_range_clp || 'N/D'}</small>
        </div>
      `).join('');
    }

    setStatus(`Recomendaciones listas (${LAST_RECS.length})`);
    setLastUpdate('(recs OK)');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);

  } catch (err) {
    console.error(err);
    list.innerHTML = '<p>Error en red/JS.</p>';
    setStatus('Fallo conexión / ruta API (recs)', false);
    setLastUpdate('(recs fallo)');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  }
}




async function loadRecommendationsFromGrid(grid, climate, predioId = PREDIO_ID) {
  const list = document.getElementById('recs-list');
  const box = document.getElementById('recs-box');
  if (!list) return;

  try {
    setStatus('Llamando /api/recommendations/from_grid.php…');
    list.textContent = 'Calculando…';

    const { data } = await fetchJSON(`${API_BASE}/recommendations/from_grid.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ grid, climate, predio_id: predioId })
    });

    if (!data.ok) {
      list.innerHTML = '<p>Error: ' + (data.error || 'sin datos') + '</p>';
      setStatus('Error recomendaciones (grid)', false);
      setLastUpdate('(recs-grid error)');
      box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
      return;
    }

    LAST_RECS = Array.isArray(data.data) ? data.data : [];
    if (!LAST_RECS.length) {
      list.innerHTML = '<p>No se requieren acciones significativas.</p>';
    } else {
      list.innerHTML = LAST_RECS.map(r => `
        <div class="recommendation">
          <strong>${r.title}</strong><br/>
          <small>${r.rationale || ''}</small><br/>
          <em>Acciones:</em> ${(r.actions || []).join(', ')}<br/>
          <small>Costo ≈ ${r.cost_range_clp || 'N/D'} · Prioridad: ${r.priority || 'N/D'}</small>
        </div>
      `).join('');
    }

    setStatus(`Recomendaciones (grid) listas (${LAST_RECS.length})`);
    setLastUpdate('(recs-grid OK)');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);

  } catch (err) {
    console.error(err);
    list.innerHTML = '<p>Error en red/JS.</p>';
    setStatus('Fallo conexión / ruta API (recs-grid)', false);
    setLastUpdate('(recs-grid fallo)');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  }
}




// ====== SIMULACIÓN HEURÍSTICA (grid + clima) ======

// 2.1 Clima seguro (defaults si aún no hay clima cargado)
function getClimateSafe() {
  if (LAST_CLIMATE_OBJ) return LAST_CLIMATE_OBJ;
  // Defaults “valdivianos” razonables
  return {
    avg_tmin: 5.0,
    avg_tmax: 19.0,
    total_prcp_mm: 1200,
    frost_days_est: 18,
    source: 'default',
    period: 'promedio local'
  };
}

// 2.2 Lee estadísticas del mundo
function readWorldStats() {
  const W = window.world;
  if (!Array.isArray(W) || !Array.isArray(W[0])) {
    throw new Error('world no disponible aún');
  }
  const h = W.length, w = W[0].length;
  let soil=0, water=0, hort=0, apple=0, berries=0, greenhouse=0, irrigation=0, slope=0, biodiversity=0, sensor=0;
  for (let y=0; y<h; y++) for (let x=0; x<w; x++) {
    const t = W[y][x];
    if (t==='soil') soil++;
    else if (t==='water') water++;
    else if (t==='crop_hort') hort++;
    else if (t==='crop_apple') apple++;
    else if (t==='crop_berries') berries++;
    else if (t==='greenhouse') greenhouse++;
    else if (t==='irrigation') irrigation++;
    else if (t==='slope') slope++;
    else if (t==='biodiversity') biodiversity++;
    else if (t==='sensor') sensor++;
  }
  const crops = hort + apple + berries;
  const total = w * h;
  const arable = total - water;
  return { total, arable, water, soil, hort, apple, berries, crops, greenhouse, irrigation, slope, biodiversity, sensor };
}



// 2.3 Heurística de puntajes (0–100)

function computeMetricsFromWorld(stats, climate) {
  const clamp = (v,min,max)=>Math.max(min, Math.min(max, v));
  const pct = (num,den)=> den>0 ? Math.round((num/den)*100) : 0;

  // Base producción
  const arable = stats.arable;
  const cropsPct = pct(stats.crops, arable);
  let prodBase = arable > 0 ? (stats.crops / arable) * 100 : 0;

  // Infraestructura
  const ghRatio = arable > 0 ? stats.greenhouse / arable : 0;
  const irrRatio = arable > 0 ? stats.irrigation / arable : 0;
  const slopeRatio = arable > 0 ? stats.slope / arable : 0;
  const biodivRatio = arable > 0 ? stats.biodiversity / arable : 0;
  const fruitRatio = arable > 0 ? (stats.apple + stats.berries) / arable : 0;

  const prodGreenhouseBonus = clamp(ghRatio * 2000, 0, 20);   // 0–20
  const prodIrrBonus        = clamp(irrRatio * 1200, 0, 12);  // 0–12
  const prodSlopePenalty    = clamp(slopeRatio * 1500, 0, 15);// 0–15

  // Clima
  let prodClimateAdj = 0;
  if (climate.total_prcp_mm < 600) prodClimateAdj -= 10;
  else if (climate.total_prcp_mm < 900) prodClimateAdj -= 5;
  else if (climate.total_prcp_mm > 1600) prodClimateAdj -= 2;

  const frostFactor = clamp((climate.frost_days_est - 5) / (40 - 5), 0, 1);
  const prodFrostPenalty = fruitRatio * frostFactor * 15; // hasta -15

  let produccion = prodBase + prodGreenhouseBonus + prodIrrBonus + prodClimateAdj - prodSlopePenalty - prodFrostPenalty;
  produccion = clamp(Math.round(produccion), 0, 100);

  // Eficiencia hídrica
  const aguaBase = 50;
  const aguaIrr = clamp(irrRatio * 4000, 0, 40);  // 0–40
  const aguaBiodiv = clamp(biodivRatio * 1000, 0, 10); // 0–10
  const aguaRainAdj = climate.total_prcp_mm < 700 ? -10 : (climate.total_prcp_mm < 900 ? -5 : 0);
  let agua = aguaBase + aguaIrr + aguaBiodiv + aguaRainAdj - (slopeRatio*10);
  agua = clamp(Math.round(agua), 0, 100);

  // Riesgo de heladas
  let hel = Math.round(frostFactor * 100);
  hel -= Math.round(ghRatio * 50);
  hel -= Math.round(biodivRatio * 20);
  hel = clamp(hel, 0, 100);

  // Textos de explicación
  const explProd = [
    `Base por cultivos: ~${Math.round(prodBase)} (cultivos ≈ ${cropsPct}% del suelo arable)`,
    ghRatio>0 ? `+ Invernaderos: +${Math.round(prodGreenhouseBonus)} (GH ≈ ${Math.round(ghRatio*100)}%)` : null,
    irrRatio>0 ? `+ Riego: +${Math.round(prodIrrBonus)} (Riego ≈ ${Math.round(irrRatio*100)}%)` : null,
    slopeRatio>0 ? `− Pendiente: −${Math.round(prodSlopePenalty)} (Pendiente ≈ ${Math.round(slopeRatio*100)}%)` : null,
    prodClimateAdj!==0 ? `${prodClimateAdj>0?'+':'−'} Clima/lluvia: ${prodClimateAdj}` : null,
    fruitRatio>0 && frostFactor>0 ? `− Heladas sobre frutales: −${Math.round(prodFrostPenalty)} (frutales ≈ ${Math.round(fruitRatio*100)}%, días helada ≈ ${climate.frost_days_est})` : null
  ].filter(Boolean).join('\n');

  const explAgua = [
    `Base: ${aguaBase}`,
    irrRatio>0 ? `+ Riego: +${Math.round(aguaIrr)} (Riego ≈ ${Math.round(irrRatio*100)}%)` : null,
    biodivRatio>0 ? `+ Biodiversidad: +${Math.round(aguaBiodiv)} (Biodiv ≈ ${Math.round(biodivRatio*100)}%)` : null,
    aguaRainAdj!==0 ? `${aguaRainAdj>0?'+':'−'} Lluvia: ${aguaRainAdj} (precip. ${climate.total_prcp_mm} mm)` : null,
    slopeRatio>0 ? `− Pendiente: −${Math.round(slopeRatio*10)} (≈ ${Math.round(slopeRatio*100)}%)` : null
  ].filter(Boolean).join('\n');

  const explHel = [
    `Base por heladas: ${Math.round(frostFactor*100)}% (días ≈ ${climate.frost_days_est})`,
    ghRatio>0 ? `− Mitigación por invernaderos: −${Math.round(ghRatio*50)} pts (GH ≈ ${Math.round(ghRatio*100)}%)` : null,
    biodivRatio>0 ? `− Mitigación por biodiversidad: −${Math.round(biodivRatio*20)} pts (Biodiv ≈ ${Math.round(biodivRatio*100)}%)` : null
  ].filter(Boolean).join('\n');

  return {
    produccion, agua, hel,
    _explain: {
      produccion: explProd,
      agua: explAgua,
      hel: explHel
    }
  };
}



// 2.4 Aplica a las barras

function applyBars(m) {
  const bProd = document.getElementById('bProd');
  const bAgua = document.getElementById('bAgua');
  const bHel  = document.getElementById('bHel');
  if (bProd) { bProd.style.width = m.produccion + '%'; bProd.dataset.explain = m._explain.produccion; }
  if (bAgua) { bAgua.style.width = m.agua + '%';        bAgua.dataset.explain = m._explain.agua; }
  if (bHel)  { bHel.style.width  = m.hel  + '%';        bHel.dataset.explain = m._explain.hel; }
}




// ====== Modal de explicaciones ======
function openExplainModal(title, text) {
  const modal = document.getElementById('ac-modal');
  if (!modal) return;
  modal.setAttribute('aria-hidden', 'false');
  document.getElementById('ac-modal-title').textContent = title || 'Detalle de cálculo';
  document.getElementById('ac-modal-content').textContent = text || '(sin datos)';
  // Foco para accesibilidad
  const closeBtn = modal.querySelector('[data-close]');
  closeBtn?.focus();
  // Cerrar con Esc
  const onEsc = (ev)=>{ if (ev.key==='Escape') { closeExplainModal(); window.removeEventListener('keydown', onEsc); } };
  window.addEventListener('keydown', onEsc);
}

function closeExplainModal() {
  const modal = document.getElementById('ac-modal');
  if (!modal) return;
  modal.setAttribute('aria-hidden','true');
}

function initExplainModalWiring() {
  const modal = document.getElementById('ac-modal');
  if (!modal) return;
  modal.querySelectorAll('[data-close]').forEach(el=>{
    el.addEventListener('click', closeExplainModal);
  });

}


// ====== Tooltip ======
let __tooltipEl = null;

function ensureTooltip() {
  if (__tooltipEl) return __tooltipEl;
  const el = document.createElement('div');
  el.id = 'ac-tooltip';
  el.style.position = 'fixed';
  el.style.zIndex = '9999';
  el.style.maxWidth = '360px';
  el.style.pointerEvents = 'none';
  el.style.padding = '8px 10px';
  el.style.font = '12px/1.35 system-ui, Segoe UI, Roboto, Arial';
  el.style.whiteSpace = 'pre-wrap';
  el.style.borderRadius = '10px';
  el.style.border = '1px solid #31439a';
  el.style.background = '#0e1430';
  el.style.color = '#e8f0ff';
  el.style.boxShadow = '0 6px 16px rgba(0,0,0,.35)';
  el.style.display = 'none';
  document.body.appendChild(el);
  __tooltipEl = el;
  return el;
}
function showTooltip(e, text) {
  const el = ensureTooltip();
  el.textContent = text || '';
  el.style.display = text ? 'block' : 'none';
  positionTooltip(e);
}
function positionTooltip(e) {
  const el = ensureTooltip();
  const pad = 12;
  let x = e.clientX + pad, y = e.clientY + pad;
  const w = el.offsetWidth, h = el.offsetHeight;
  const vw = window.innerWidth, vh = window.innerHeight;
  if (x + w > vw - 8) x = e.clientX - w - pad;
  if (y + h > vh - 8) y = e.clientY - h - pad;
  el.style.left = x + 'px';
  el.style.top  = y + 'px';
}
function hideTooltip() {
  const el = ensureTooltip();
  el.style.display = 'none';
}



let __hintShown = false;
function maybeShowHint() {
  if (__hintShown) return;
  __hintShown = true;
  setStatus('Tip: pasa el mouse sobre las barras para ver cómo se calculó.');
}





// 2.5 Simular (leer mundo + clima y mover barras) + pedir recomendaciones API

// --- Simulación (lee mundo+clima, mueve barras y pide recs al backend) ---
async function simulateFromWorld(predioId = PREDIO_ID) {
  try {
    setStatus('Simulando…');

    // 1) Métricas locales (heurística en el front, mueve barras)
    const stats   = readWorldStats();
    const climate = getClimateSafe();
    const m = computeMetricsFromWorld(stats, climate);
    applyBars(m);
    maybeShowHint();
    setLastUpdate(`(sim OK: P${m.produccion}% / A${m.agua}% / H${m.hel}%)`);

    // 2) Recomendaciones reales desde el backend usando GRID + CLIMA actuales
    await loadRecommendationsFromGrid(getWorldGrid(), climate, predioId);

    setStatus('Simulación completada');
  } catch (e) {
    console.error(e);
    setStatus('Error en simulación', false);
    setLastUpdate('(sim error)');
  }
}

// --- Recomendaciones desde GRID+CLIMA (backend) ---
async function loadRecommendationsFromGrid(grid, climate, predioId = PREDIO_ID) {
  const list = document.getElementById('recs-list');
  const box  = document.getElementById('recs-box');
  if (list) list.textContent = 'Calculando…';
  setStatus('Llamando /api/recommendations/from_grid.php…');

  try {
    const { data } = await fetchJSON(`${API_BASE}/recommendations/from_grid.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        grid,
        climate,
        predio_id: predioId   // opcional: por si el backend quiere usar fallback a DB
      })
    });

    if (!data.ok) {
      if (list) list.innerHTML = '<p>Error: ' + (data.error || 'sin datos') + '</p>';
      setStatus('Error recomendaciones (from_grid)', false);
      setLastUpdate('(recs error)');
      box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
      return;
    }

    // Guarda y pinta
    LAST_RECS = Array.isArray(data.data) ? data.data : [];
    if (!LAST_RECS.length) {
      if (list) list.innerHTML = '<p>No se requieren acciones significativas.</p>';
    } else {
      if (list) {
        list.innerHTML = LAST_RECS.map(r => `
          <div class="recommendation">
            <strong>${r.title}</strong><br/>
            <small>${r.rationale || ''}</small><br/>
            <em>Acciones:</em> ${(r.actions || []).join(', ')}<br/>
            <small>Costo ≈ ${r.cost_range_clp || 'N/D'}</small>
          </div>
        `).join('');
      }
    }

    setStatus(`Recomendaciones listas (${LAST_RECS.length})`);
    setLastUpdate('(recs OK)');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);

  } catch (err) {
    console.error(err);
    if (list) list.innerHTML = '<p>Error en red/JS.</p>';
    setStatus('Fallo conexión / ruta API (from_grid)', false);
    setLastUpdate('(recs fallo)');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  }
}














maybeShowHint();





// --- 7) Guardar / Cargar mundo ---
function getWorldGrid() {
  if (!window.world) throw new Error('world no está disponible');
  return window.world;
}


function setWorldGrid(grid) {
  try { if (typeof grid === 'string') grid = JSON.parse(grid); } catch(_) {}
  if (!Array.isArray(grid) || !grid.length) throw new Error('grid inválido');
  window.world = grid;
  if (typeof window.drawGrid === 'function') window.drawGrid();
}



async function saveWorld(predioId = 1) {
  try {
    const grid = getWorldGrid();
    setStatus('Guardando mundo…');
    const { data } = await fetchJSON(`${API_BASE}/predios/save_world.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ predio_id: predioId, grid })
    });
    if (!data.ok) throw new Error(data.error || 'No se pudo guardar');
    setStatus('Mundo guardado');
    setLastUpdate('(save OK)');
    const box = document.getElementById('recs-box') || document.getElementById('climate-info');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);
  } catch (err) {
    console.error(err);
    setStatus('Error al guardar el mundo', false);
    setLastUpdate('(save error)');
    const box = document.getElementById('recs-box') || document.getElementById('climate-info');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  }
}

async function loadWorld(predioId = 1) {
  try {
    setStatus('Cargando mundo…');
    const { data } = await fetchJSON(`${API_BASE}/predios/load_world.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ predio_id: predioId })
    });
    if (!data.ok) throw new Error(data.error || 'No se pudo cargar');
    const grid = data.data;
    setWorldGrid(grid);
    setStatus('Mundo cargado');
    setLastUpdate('(load OK)');
    const box = document.getElementById('recs-box') || document.getElementById('climate-info');
    box?.classList.add('flash-ok'); setTimeout(()=>box?.classList.remove('flash-ok'), 600);
  } catch (err) {
    console.error(err);
    setStatus('Error al cargar el mundo', false);
    setLastUpdate('(load error)');
    const box = document.getElementById('recs-box') || document.getElementById('climate-info');
    box?.classList.add('flash-err'); setTimeout(()=>box?.classList.remove('flash-err'), 700);
  }
}





// --- 8) Reporte PDF (con Anexo técnico) ---
async function printReport() {
  try {
    // 1) Captura visual del sandbox
    const canvas = document.getElementById('world');
    if (!canvas) return alert('Canvas no encontrado');
    const dataUrl = canvas.toDataURL('image/png');

    // 2) Clima visible + objeto clima
    const climaEl = document.getElementById('climate-data');
    const climaHTML = (climaEl && climaEl.innerHTML) ? climaEl.innerHTML : (LAST_CLIMA || 'Sin datos');
    const CLIM = getClimateSafe(); // usa el clima efectivo de la simulación

    // 3) Porcentajes mostrados (barras)
    const pct = (id) => {
      const el = document.getElementById(id);
      const w = el && el.style.width ? el.style.width : '0%';
      return w.replace('%','');
    };
    const mProd = pct('bProd');
    const mAgua = pct('bAgua');
    const mHel  = pct('bHel');

    // 4) Desglose (igual que tooltip/modal)
    const getExplain = (id, fallback) => {
      const el = document.getElementById(id);
      return (el && el.dataset && el.dataset.explain) ? el.dataset.explain : (fallback || 'Sin detalles aún. Pulse “Simular”.');
    };
    const expProd = getExplain('bProd', '');
    const expAgua = getExplain('bAgua', '');
    const expHel  = getExplain('bHel',  '');

    // 5) Stats del mundo (para Anexo B)
    let statsSafe = {};
    try { statsSafe = readWorldStats(); } catch(_) {
      statsSafe = {
        total: 0, arable: 0, water: 0, soil: 0, crop_hort: 0, crop_apple: 0, crop_berries: 0,
        greenhouse: 0, irrigation: 0, slope: 0, biodiversity: 0, sensor: 0, crops: 0
      };
    }
    const ratio = (num, den) => (den>0 ? `${Math.round((num/den)*100)}%` : '0%');

    // 6) Recomendaciones (si no hay en memoria, intenta pedir por predio_id)
    let recs = LAST_RECS;
    if (!recs || !recs.length) {
      try {
        const { data } = await fetchJSON(`${API_BASE}/recommendations/run.php`, {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ predio_id: PREDIO_ID })
        });
        if (data.ok) recs = data.data || [];
      } catch(e){}
    }

    const fecha = new Date().toLocaleString();

    const recsRows = (recs || []).map(r => `
      <tr>
        <td><strong>${r.title}</strong><br><small>${r.rationale || ''}</small></td>
        <td>${(r.actions || []).join(', ')}</td>
        <td>${r.cost_range_clp || 'N/D'}</td>
      </tr>
    `).join('') || `<tr><td colspan="3">Sin recomendaciones disponibles.</td></tr>`;

    // 7) HTML del reporte con anexos
    const reportHTML = `
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Informe Agroverso Craft</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
  body{font:14px/1.45 system-ui,Segoe UI,Roboto,Helvetica,Arial;background:#fff;color:#000;margin:28px;}
  h1,h2,h3{margin:0 0 8px 0}
  h1{font-size:20px} h2{font-size:16px} h3{font-size:14px}
  .row{display:flex;gap:18px;flex-wrap:wrap;margin:12px 0;}
  .col{flex:1;min-width:260px;}
  .imgbox{border:1px solid #ccc;border-radius:8px;padding:8px;display:inline-block;}
  .imgbox img{max-width:100%;height:auto;display:block;border-radius:6px;}
  .metrics{margin:10px 0;}
  .metric-row{display:flex;gap:10px;margin:6px 0;align-items:center}
  .metric-row .name{width:160px;}
  .bar{flex:1;height:10px;background:#eee;border:1px solid #bbb;border-radius:6px;position:relative}
  .bar > span{position:absolute;left:0;top:0;bottom:0;background:#4caf50;border-radius:6px}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
  .note{color:#444;font-size:11px;margin-top:8px}
  .foot{margin-top:18px;font-size:12px;color:#333}
  .muted{color:#444}
  .anexo pre{background:#f7f7f7;border:1px solid #ddd;border-radius:8px;padding:10px;white-space:pre-wrap;word-break:break-word;font:12px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;}
  .kvtbl{width:100%;border-collapse:collapse;margin:8px 0}
  .kvtbl th,.kvtbl td{border:1px solid #ddd;padding:6px}
  .sect{margin-top:20px}
</style>
</head>
<body>
  <h1>Agroverso Craft — Informe de simulación</h1>
  <div class="muted">Fecha: ${fecha}</div>

  <div class="row">
    <div class="col">
      <h2>Clima y predio</h2>
      <div>${climaHTML}</div>
      <div class="metrics">
        <div class="metric-row"><div class="name">Producción</div><div class="bar"><span style="width:${mProd}%"></span></div> ${mProd}%</div>
        <div class="metric-row"><div class="name">Efic. hídrica</div><div class="bar"><span style="width:${mAgua}%"></span></div> ${mAgua}%</div>
        <div class="metric-row"><div class="name">Riesgo heladas</div><div class="bar"><span style="width:${mHel}%"></span></div> ${mHel}%</div>
      </div>
    </div>
    <div class="col">
      <h2>Vista del predio (sandbox)</h2>
      <div class="imgbox"><img src="${dataUrl}" alt="Predio Agroverso Craft"/></div>
      <div class="note">Imagen corresponde al estado actual del canvas (20×20 tiles).</div>
    </div>
  </div>

  <h2>Recomendaciones técnicas</h2>
  <table>
    <thead><tr><th>Medida sugerida</th><th>Acciones</th><th>Rango costo (CLP)</th></tr></thead>
    <tbody>${recsRows}</tbody>
  </table>

  <div class="sect anexo">
    <h2>Anexo A — Desglose de métricas</h2>
    <h3>Producción (${mProd}%)</h3>
    <pre>${expProd || 'Sin detalles.'}</pre>
    <h3>Eficiencia hídrica (${mAgua}%)</h3>
    <pre>${expAgua || 'Sin detalles.'}</pre>
    <h3>Riesgo de heladas (${mHel}%)</h3>
    <pre>${expHel || 'Sin detalles.'}</pre>
  </div>

  <div class="sect anexo">
    <h2>Anexo B — Resumen del mundo (20×20)</h2>
    <table class="kvtbl">
      <tbody>
        <tr><th>Total tiles</th><td>${statsSafe.total}</td><th>Arable</th><td>${statsSafe.arable}</td><th>Agua (tiles)</th><td>${statsSafe.water}</td></tr>
        <tr><th>Suelo</th><td>${statsSafe.soil}</td><th>Hortalizas</th><td>${statsSafe.crop_hort}</td><th>Manzanas</th><td>${statsSafe.crop_apple}</td></tr>
        <tr><th>Berries</th><td>${statsSafe.crop_berries}</td><th>Invernadero</th><td>${statsSafe.greenhouse}</td><th>Riego</th><td>${statsSafe.irrigation}</td></tr>
        <tr><th>Pendiente</th><td>${statsSafe.slope}</td><th>Biodiversidad</th><td>${statsSafe.biodiversity}</td><th>Sensores</th><td>${statsSafe.sensor}</td></tr>
        <tr><th>Cultivos/Arable</th><td>${ratio(statsSafe.crops, statsSafe.arable)}</td><th>Riego/Arable</th><td>${ratio(statsSafe.irrigation, statsSafe.arable)}</td><th>GH/Arable</th><td>${ratio(statsSafe.greenhouse, statsSafe.arable)}</td></tr>
        <tr><th>Biodiv/Arable</th><td>${ratio(statsSafe.biodiversity, statsSafe.arable)}</td><th>Pendiente/Arable</th><td>${ratio(statsSafe.slope, statsSafe.arable)}</td><th>Agua/Total</th><td>${ratio(statsSafe.water, statsSafe.total)}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="sect anexo">
    <h2>Anexo C — Parámetros climáticos usados</h2>
    <table class="kvtbl">
      <tbody>
        <tr><th>T° mínima media</th><td>${CLIM.avg_tmin} °C</td><th>T° máxima media</th><td>${CLIM.avg_tmax} °C</td></tr>
        <tr><th>Precipitación anual</th><td>${CLIM.total_prcp_mm} mm</td><th>Días con heladas</th><td>${CLIM.frost_days_est} días/año</td></tr>
        <tr><th>Fuente</th><td colspan="3">${CLIM.source || 'N/D'} — ${CLIM.period || ''}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="foot">
    Fuente climática: Open-Meteo / ERA5 • Herramienta: Agroverso Craft (PHP + JS) • Demo hackathon
  </div>

  <script>window.onload = () => { window.print(); };</script>
</body>
</html>`;

    // 8) Abrir para imprimir
    const w = window.open('', '_blank');
    if (!w) return alert('Bloqueador de ventanas: permite popups para imprimir.');
    w.document.open();
    w.document.write(reportHTML);
    w.document.close();

  } catch (err) {
    console.error(err);
    alert('No se pudo generar el informe: ' + err.message);
  }
}





// --- 9) Carga inicial (boot) ---
async function bootLoad() {
  try {
    setStatus('Cargando mundo inicial…');
    await loadWorld(PREDIO_ID);
    setStatus('Mundo inicial listo');
    setLastUpdate('(auto-load OK)');
  } catch (e) {
    console.warn('Auto-load world falló:', e);
    setStatus('No se pudo cargar mundo inicial', false);
    setLastUpdate('(auto-load error)');
  }
  try {
    await refreshClimate(PREDIO_ID);
  } catch (e) {
    console.warn('Auto-refresh clima falló:', e);
  }

try {
  const { data } = await fetchJSON(`${API_BASE}/predios/get.php?id=${PREDIO_ID}`);
  if (data.ok && data.data) {
    document.getElementById('inp-dir')?.setAttribute('value', data.data.direccion || '');
    document.getElementById('inp-lat')?.setAttribute('value', data.data.lat ?? '');
    document.getElementById('inp-lng')?.setAttribute('value', data.data.lng ?? '');
  }
} catch(_) {}


}




async function setLocation(predioId = 1) {
  const inDir = document.getElementById('inp-dir');
  const inLat = document.getElementById('inp-lat');
  const inLng = document.getElementById('inp-lng');
  const btn   = document.getElementById('btn-set-loc');

  if (!inLat || !inLng || !btn) return;

  // Permite “lat,lng” en un solo input (si el usuario lo pegó así en lat)
  let lat = (inLat.value || '').trim();
  let lng = (inLng.value || '').trim();

  if (lat.includes(',') && !lng) {
    const parts = lat.split(',');
    if (parts.length >= 2) {
      lat = parts[0].trim();
      lng = parts[1].trim();
      inLat.value = lat;
      inLng.value = lng;
    }
  }

  if (!lat || !lng) {
    setStatus('Ingresa lat y lng', false);
    return;
  }

  const dir = (inDir?.value || '').trim();
  const old = btn.textContent;
  btn.disabled = true; btn.textContent = 'Guardando…';
  setStatus('Fijando ubicación…');

  try {
    const { data } = await fetchJSON(`${API_BASE}/predios/set_location.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ predio_id: predioId, lat: lat, lng: lng, direccion: dir })
    });

    if (!data.ok) {
      setStatus('Error al fijar ubicación: ' + (data.error || 'desconocido'), false);
      setLastUpdate('(ubicación error)');
      return;
    }

    setStatus('Ubicación guardada');
    setLastUpdate('(ubicación OK)');

    // Lanza refresh de clima de inmediato
    await refreshClimate(predioId);

  } catch (err) {
    console.error(err);
    setStatus('Fallo conexión / ruta API (ubicación)', false);
    setLastUpdate('(ubicación fallo)');
  } finally {
    btn.disabled = false; btn.textContent = old;
  }
}









// --- 10) Wiring y autosaves ---
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-climate')?.addEventListener('click', () => refreshClimate(PREDIO_ID));
  
  //document.getElementById('btn-simular')?.addEventListener('click', () => loadRecommendations(PREDIO_ID));
  document.getElementById('btn-simular')?.addEventListener('click', () => simulateFromWorld(PREDIO_ID));

  
  document.getElementById('btn-report')?.addEventListener('click', () => printReport());
  document.getElementById('btn-save-world')?.addEventListener('click', () => saveWorld(PREDIO_ID));
  document.getElementById('btn-load-world')?.addEventListener('click', () => loadWorld(PREDIO_ID));

  document.getElementById('btn-set-loc')?.addEventListener('click', () => setLocation(PREDIO_ID));



  // Auto-load inicial
  bootLoad();

  // Ping API
  fetch(`${API_BASE}/recommendations/ping.php`)
    .then(r => console.log('PING API:', r.status, API_BASE))
    .catch(err => console.warn('PING fallo:', err.message));

  // Autosave cada 120 s
  setInterval(() => {
    if (window.world) saveWorld(PREDIO_ID).catch(()=>{});
  }, 120000);

  // Autosave “debounced” si se pintó (cada 3 s)
  setInterval(() => {
    if (window.__worldDirty) {
      window.__worldDirty = false;
      saveWorld(PREDIO_ID).catch(()=>{});
    }
  }, 3000);


// Tooltips para barras
['bProd','bAgua','bHel'].forEach(id=>{
  const el = document.getElementById(id);
  if (!el) return;
  el.style.cursor = 'help';
  el.addEventListener('mouseenter', (e)=> showTooltip(e, el.dataset.explain || ''));
  el.addEventListener('mousemove', positionTooltip);
  el.addEventListener('mouseleave', hideTooltip);
});



initExplainModalWiring();

// Botones “?” (usan data-explain que setea applyBars)
document.querySelectorAll('.btn-help').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const forId = btn.getAttribute('data-help-for'); // bProd, bAgua, bHel
    const target = document.getElementById(forId);
    const text = (target && target.dataset.explain) ? target.dataset.explain : 'Aún no hay simulación.\nPulsa “Simular” para calcular.';
    const title =
      forId==='bProd' ? 'Producción – detalle' :
      forId==='bAgua' ? 'Eficiencia hídrica – detalle' :
      forId==='bHel'  ? 'Riesgo de heladas – detalle' : 'Detalle de cálculo';
    openExplainModal(title, text);
  });
});


});



// --- 11) Captura de errores globales ---
window.addEventListener('error', (e) => {
  setStatus('Error JS: ' + (e.message || 'desconocido'), false);
  setLastUpdate('(runtime error)');
});
window.addEventListener('unhandledrejection', (e) => {
  const msg = (e && e.reason && e.reason.message) ? e.reason.message : 'Promesa rechazada';
  setStatus('Error promesa: ' + msg, false);
  setLastUpdate('(promise error)');
});



