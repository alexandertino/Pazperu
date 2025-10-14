<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, defineProps } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ proyecto: Object, user: Object });
const user = usePage().props.auth?.user || null;

/* ---------- Estado ---------- */
const form = reactive({
  codigo1: '',
  codigo2: String(new Date().getFullYear()),
  persona_id: null,
  nombre: '',
  lugar: '',
  distrito: '',
  fecha: new Date().toISOString().slice(0, 10),
  producto_code: '',
  producto_label: '',
  cantidad: '',
  um: ''
});

const personas = ref([]);
const productos = ref([]);
const draft = ref([]);
const productoEncontrado = ref(null);

/* Inventario modal + filtros reducidos (sin Orden / Mostrar / Stock≥) */
const showInventoryModal = ref(false);
const inventoryFilter = reactive({
  q: '',
  solicitado_por: '',
  categoria: '',
  onlyAvailable: true
});
const inventory = ref([]);
const inventoryLoading = ref(false);
const inventoryError = ref(null);

/* opciones derivadas (para selects) */
const inventoryCategorias = ref([]);
const inventorySolicitantes = ref([]);

/* UI predictivos */
const mostrarPersonas = ref(false);
const mostrarSugerencias = ref(false);

/* ---------- util / helpers ---------- */
const normalizeProduct = (p = {}) => ({
  code: (p.code || p.codigo || p.id || '')?.toString(),
  producto: (p.producto || p.descripcion || p.name || '')?.toString(),
  descripcion: (p.descripcion || p.producto || p.name || '')?.toString(),
  um: (p.um || p.unidad || p.unidad_medida || p.uom || '')?.toString(),
  stock: (typeof p.stock !== 'undefined')
    ? Number(p.stock)
    : (typeof p.cantidad !== 'undefined' ? Number(p.cantidad) : null),
  solicitado_por: (p.solicitado_por || p.solicitante || p.requested_by || '')?.toString(),
  categoria: (p.categoria || p.categoria_id || p.category || '')?.toString(),
  raw: p
});

const volverATabla = () => {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};

const codigoGenerado = computed(() => {
  const c1 = String(form.codigo1 || '').padStart(3, '0');
  const c2 = String(form.codigo2 || '').padStart(4, '0');
  return `AE - ${c1} - ${c2}`;
});

const draftTotals = computed(() => {
  const lines = draft.value.length;
  const totalQty = draft.value.reduce((s, it) => s + Number(it.cantidad || 0), 0);
  return { lines, totalQty };
});

const uid = () => Date.now().toString(36) + Math.floor(Math.random() * 10000).toString(36);

/* ---------- axios interceptors (sin logs) ---------- */
axios.interceptors.request.use((config) => config, (err) => Promise.reject(err));
axios.interceptors.response.use((res) => res, (err) => Promise.reject(err));

/* ---------- carga inicial (no asignamos nombre por defecto) ---------- */
onMounted(async () => {
  try {
    // Personas
    const res = await axios.get('/personas');
    personas.value = Array.isArray(res.data) ? res.data : (res.data?.personas || []);
  } catch (e) {
    personas.value = [];
  }

  try {
    // Productos del proyecto
    const r2 = await axios.get(`/proyectos/${props.proyecto.id}/productos`);
    productos.value = Array.isArray(r2.data) ? r2.data.map(normalizeProduct) : [];
  } catch (e) {
    productos.value = [];
  }

  // 🔹 Nombre del usuario autenticado
  if (user && user.name) {
    form.nombre = user.name;
  }

  // 🔹 Código 2 = año actual (por defecto)
  if (!form.codigo2 || String(form.codigo2).trim() === '') {
    form.codigo2 = String(new Date().getFullYear());
  }

  // 🔹 Obtener siguiente código autoincrementado
  try {
    const { data } = await axios.get('/salidas/ultimo-codigo');
    form.codigo1 = data.siguiente || 1;
  } catch (e) {
    form.codigo1 = 1; // Fallback si falla
  }
});



/* ---------- Personas predictivo ---------- */
const personasFiltradas = computed(() => {
  const q = String(form.nombre || '').toLowerCase().trim();
  if (!q) return personas.value.slice(0, 12);
  return personas.value.filter(p =>
    ((p.nombre || '') + ' ' + (p.distrito || '') + ' ' + (p.lugar || '')).toLowerCase().includes(q)
  ).slice(0, 12);
});

const onPersonaInput = (e) => {
  form.nombre = e?.target?.value ?? form.nombre;
  form.persona_id = null;
  mostrarPersonas.value = String(form.nombre || '').trim().length > 0;
};

const seleccionarPersona = (p) => {
  if (!p) return;
  form.persona_id = p.id ?? null;
  form.nombre = p.nombre || '';
  form.lugar = p.lugar || '';
  form.distrito = p.distrito || '';
  mostrarPersonas.value = false;
  nextTick(() => {
    const el = document.querySelector('#producto_code');
    if (el) el.focus();
  });
};

/* ---------- Productos predictivo (local) ---------- */
const productosFiltrados = computed(() => {
  const q = String(form.producto_code || '').toLowerCase().trim();
  if (!q) return [];
  return productos.value.filter(p =>
    ((p.code || '') + ' ' + (p.producto || '') + ' ' + (p.descripcion || '')).toLowerCase().includes(q)
  ).slice(0, 12);
});

const onProductoInput = (e) => {
  form.producto_code = e?.target?.value ?? form.producto_code;
  productoEncontrado.value = null;
  mostrarSugerencias.value = String(form.producto_code || '').trim().length > 0;
};

const seleccionarProducto = (p) => {
  if (!p) return;
  const normalized = (p && p.code) ? p : normalizeProduct(p);
  form.producto_code = normalized.code || normalized.producto || '';
  form.producto_label = normalized.producto || normalized.descripcion || form.producto_label;
  form.um = normalized.um || form.um;
  productoEncontrado.value = normalized;
  mostrarSugerencias.value = false;
  nextTick(() => {
    const el = document.querySelector('#producto_code');
    if (el) el.focus();
  });
};

/* ---------- Draft helpers ---------- */
const resetProductFields = () => { form.producto_code = ''; form.producto_label = ''; form.cantidad = 1; form.um = ''; productoEncontrado.value = null; };

const findProductByCode = (code) => {
  if (!code) return undefined;
  const c = String(code).toLowerCase().trim();
  let found = productos.value.find(p => (p.code || '').toLowerCase() === c);
  if (found) return found;
  found = productos.value.find(p => (p.producto || '').toLowerCase() === c);
  if (found) return found;
  found = productos.value.find(p => (p.code || '').toLowerCase().includes(c) || (p.producto || '').toLowerCase().includes(c) || (p.descripcion || '').toLowerCase().includes(c));
  return found;
};

const addToDraft = () => {
  if (!form.producto_code && !form.producto_label) {
    Swal.fire('Falta producto', 'Selecciona o escribe un producto.', 'warning');
    return;
  }
  if (!form.cantidad || Number(form.cantidad) <= 0) {
    Swal.fire('Cantidad inválida', 'La cantidad debe ser mayor a 0.', 'warning');
    return;
  }

  // buscar producto conocido (si fue seleccionado del inventario)
  const prodFound = findProductByCode(form.producto_code || form.producto_label);
  const prod = prodFound || (productoEncontrado.value ? productoEncontrado.value : null);
  const prodStock = prod ? (typeof prod.stock === 'number' ? Number(prod.stock) : null) : null;

  // calcular cantidad ya en borrador para este producto+um
  const unit = (prod && prod.um) ? prod.um : (form.um || 'UNIDAD');
  const prodCodeKey = (prod && prod.code) ? String(prod.code) : String(form.producto_code || form.producto_label);
  const existing = draft.value.find(d => String(d.producto_code) === prodCodeKey && d.um === unit);
  const cantidadEnDraft = existing ? Number(existing.cantidad || 0) : 0;
  const nuevoTotal = cantidadEnDraft + Number(form.cantidad);

  // si hay stock conocido, validar
  if (prodStock !== null && !Number.isNaN(prodStock)) {
    if (nuevoTotal > prodStock) {
      Swal.fire('Stock insuficiente', `Disponibles: ${prodStock - cantidadEnDraft} (stock total ${prodStock}). No puedes agregar ${form.cantidad} más.`, 'warning');
      return;
    }
  }

  // proceder a agregar (mismo comportamiento que antes)
  if (existing) existing.cantidad = Number(existing.cantidad) + Number(form.cantidad);
  else draft.value.push({
    id: uid(),
    producto_code: prod ? (prod.code || prod.producto) : (form.producto_code || form.producto_label),
    producto: prod ? (prod.producto || prod.descripcion || prod.code) : (form.producto_label || form.producto_code || '—'),
    producto_label: form.producto_label || (prod ? (prod.producto || prod.descripcion) : ''),
    descripcion: prod ? (prod.descripcion || '') : '',
    um: unit,
    cantidad: Number(form.cantidad)
  });

  Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Agregado: ${form.producto_label || (prod && prod.producto) || ''} — ${form.cantidad} ${unit}`, timer: 1200, showConfirmButton: false });
  resetProductFields();
  nextTick(() => { const el = document.querySelector('#producto_code'); if (el) el.focus(); });
};


const editDraftItem = (id) => {
  const it = draft.value.find(d => d.id === id);
  if (!it) return;

  // intentar localizar producto en inventario para conocer stock
  const found = findProductByCode(it.producto_code);
  const prodStock = found ? (typeof found.stock === 'number' ? Number(found.stock) : null) : null;

  Swal.fire({
    title: `Editar ${it.producto}`,
    html: `<label class="swal2-label">Cantidad</label><input id="swal-cant" type="number" min="0.0001" step="0.0001" value="${it.cantidad}" class="swal2-input"><label class="swal2-label">Unidad (UM)</label><input id="swal-um" type="text" value="${it.um}" class="swal2-input">`,
    preConfirm: () => {
      const v = Number(document.getElementById('swal-cant').value || 0);
      const um = document.getElementById('swal-um').value || 'UNIDAD';
      if (!v || v <= 0) Swal.showValidationMessage('Cantidad inválida');

      // calcular cantidad total en draft para este producto (sumando otros items excepto el que editamos)
      const cantidadOtros = draft.value.reduce((s, d) => {
        if (d.id === id) return s;
        if (String(d.producto_code) === String(it.producto_code) && d.um === um) return s + Number(d.cantidad || 0);
        return s;
      }, 0);
      const totalPropuesto = cantidadOtros + v;

      if (prodStock !== null && !Number.isNaN(prodStock) && totalPropuesto > prodStock) {
        Swal.showValidationMessage(`Stock insuficiente. Disponibles: ${prodStock - cantidadOtros} (stock total ${prodStock}).`);
      }

      return { v, um };
    }
  }).then(res => {
    if (res.isConfirmed) {
      it.cantidad = Number(res.value.v);
      it.um = res.value.um;
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Actualizado', timer: 1000, showConfirmButton: false });
    }
  });
};

const removeDraftItem = (id) => {
  Swal.fire({ title: '¿Eliminar item?', text: 'Se quitará del borrador.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then(r => { if (r.isConfirmed) { draft.value = draft.value.filter(d => d.id !== id); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Eliminado', timer: 900, showConfirmButton: false }); } });
};

const moveDraftItem = (id, dir) => {
  const idx = draft.value.findIndex(i => i.id === id); if (idx === -1) return; const newIdx = idx + (dir === 'up' ? -1 : 1); if (newIdx < 0 || newIdx >= draft.value.length) return; const arr = draft.value; const [item] = arr.splice(idx, 1); arr.splice(newIdx, 0, item); draft.value = [...arr];
};

const clearDraft = () => { draft.value = []; resetProductFields(); };

/* ---------- Finalizar guardado ---------- */
const showValidationErrors = (errs) => {
  const items = Object.keys(errs || {}).map(f => errs[f].map(m => `<li><strong>${f}:</strong> ${m}</li>`).join('')).join('');
  const html = `<ul style="text-align:left; margin:0; padding-left:1em;">${items}</ul>`;
  Swal.fire({ title: 'Errores de validación', html, icon: 'error' });
};

const finalizeSave = async (options = { maintain: false }) => {
  if (!draft.value.length) { Swal.fire('Borrador vacío', 'Agrega al menos un item antes de finalizar.', 'warning'); return; }
  if (!form.nombre || !form.lugar || !form.distrito) { Swal.fire('Falta información', 'Completa nombre, lugar y distrito.', 'warning'); return; }

  const nActaValue = codigoGenerado.value;
  const records = draft.value.map(it => ({
    n_acta: nActaValue,
    nombre: form.nombre,
    lugar: form.lugar,
    distrito: form.distrito,
    fecha: form.fecha,
    producto_code: it.producto_code,
    producto: it.producto,
    producto_label: it.producto_label || it.producto,
    um: it.um,
    cantidad: it.cantidad
  }));

  const confirmed = await Swal.fire({ title: options.maintain ? 'Guardar y mantener?' : 'Finalizar y guardar?', text: options.maintain ? 'Se guardará la acta y se te entregará un nuevo código.' : 'Se guardará la acta (registrada).', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar' });
  if (!confirmed.isConfirmed) return;

  try {
    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await axios.post(`/proyectos/${props.proyecto.id}/salidas`, { items: records, persona_id: form.persona_id, nombre: form.nombre, lugar: form.lugar, distrito: form.distrito });
    Swal.close();
    if (res.data?.success) {
      Swal.fire({ icon: 'success', title: 'Guardado', html: `Acta: <strong>${nActaValue}</strong>` });
      if (options.maintain) {
        draft.value = [];
        resetProductFields();
        incrementCombinedCode2(1);
      } else {
        draft.value = [];
        form.persona_id = null; form.nombre = ''; form.lugar = ''; form.distrito = ''; form.fecha = new Date().toISOString().slice(0, 10);
        resetProductFields();
        incrementCombinedCode2(1);
      }
    } else {
      Swal.fire('Error', res.data?.error || 'La petición no devolvió success=true', 'error');
    }
  } catch (error) {
    Swal.close();
    if (error.response?.status === 422 && error.response.data?.errors) showValidationErrors(error.response.data.errors);
    else Swal.fire('Error', 'Ocurrió un problema guardando el borrador. Revisa la consola.', 'error');
  }
};

/* ---------- Código acta helpers (números) ---------- */
const codeParts2 = ['codigo1', 'codigo2'];
const codeSizes2 = [3, 4];
const totalCodeLength2 = codeSizes2.reduce((a, b) => a + b, 0);
const getCombinedCodeString2 = () => codeParts2.map((p, i) => String(form[p] || '').padStart(codeSizes2[i], '0')).join('');
const setCombinedCodeFromNumber2 = (num) => {
  const s = String(num).padStart(totalCodeLength2, '0'); let pos = 0;
  codeParts2.forEach((p, i) => { const len = codeSizes2[i]; form[p] = s.slice(pos, pos + len); pos += len; });
};
const incrementCombinedCode2 = (delta = 1) => {
  if (codeSizes2.length === 2 && codeSizes2[1] === 4) {
    const cur1 = parseInt(String(form.codigo1 || '0').replace(/\D/g, ''), 10) || 0;
    let nxt1 = cur1 + delta;
    if (nxt1 < 0) nxt1 = 0;
    const limit1 = Math.pow(10, codeSizes2[0]) - 1;
    if (nxt1 > limit1) nxt1 = limit1;
    form.codigo1 = String(nxt1).padStart(codeSizes2[0], '0');
    return;
  }
  const cur = parseInt(getCombinedCodeString2().replace(/\D/g, ''), 10) || 0;
  const nxt = cur + delta;
  setCombinedCodeFromNumber2(nxt);
};

/* ---------- Inventario helpers ---------- */
const debounce = (fn, wait = 300) => {
  let t = null;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
};

const fetchInventory = async (query = '') => {
  inventoryLoading.value = true;
  inventoryError.value = null;
  try {
    const url = `/proyectos/${props.proyecto.id}/productos${query ? ('?q=' + encodeURIComponent(query)) : ''}`;
    const res = await axios.get(url);
    inventory.value = Array.isArray(res.data) ? res.data.map(normalizeProduct) : [];

    // poblar opciones de filtros (sin duplicados)
    const cats = new Set();
    const sols = new Set();
    inventory.value.forEach(i => { if (i.categoria) cats.add(String(i.categoria)); if (i.solicitado_por) sols.add(String(i.solicitado_por)); });
    inventoryCategorias.value = Array.from(cats).sort();
    inventorySolicitantes.value = Array.from(sols).sort();
  } catch (err) {
    inventoryError.value = err?.response?.data?.message || err?.message || 'Error';
    inventory.value = [];
    inventoryCategorias.value = [];
    inventorySolicitantes.value = [];
  } finally {
    inventoryLoading.value = false;
  }
};

const debouncedFetchInventory = debounce((q) => { fetchInventory(q); }, 300);
const openInventory = async () => { showInventoryModal.value = true; inventoryFilter.q = ''; await nextTick(); fetchInventory(); };
const closeInventory = () => { showInventoryModal.value = false; };
const selectInventoryProduct = (p) => {
  const normalized = (p && p.code) ? p : normalizeProduct(p);
  form.producto_code = normalized.code || normalized.id || '';
  form.producto_label = normalized.producto || normalized.descripcion || '';
  form.um = normalized.um || form.um;
  productoEncontrado.value = normalized;
  showInventoryModal.value = false;
  nextTick(() => { const el = document.querySelector('#producto_code'); if (el) el.focus(); });
};

// filtros robustos: evita bugs cuando categoria/solicitante son null/undefined
const filteredInventory = computed(() => {
  const q = String(inventoryFilter.q || '').toLowerCase().trim();
  const cat = String(inventoryFilter.categoria || '').toLowerCase().trim();
  const sol = String(inventoryFilter.solicitado_por || '').toLowerCase().trim();
  const onlyAvailable = Boolean(inventoryFilter.onlyAvailable);

  let arr = inventory.value.filter(item => {
    // Normalizar campos defensivamente
    const stockVal = (typeof item.stock !== 'undefined' && item.stock !== null) ? Number(item.stock) : null;

    if (onlyAvailable && stockVal !== null && stockVal <= 0) return false; // excluir cuando stock 0

    if (cat && !(String(item.categoria || '').toLowerCase().includes(cat))) return false;
    if (sol && !(String(item.solicitado_por || '').toLowerCase().includes(sol))) return false;

    if (!q) return true;
    const hay = (
      (item.producto || '') + ' ' +
      (item.descripcion || '') + ' ' +
      (String(item.code || item.id || '') || '') + ' ' +
      (String(item.categoria || '') || '') + ' ' +
      (String(item.solicitado_por || '') || '')
    ).toLowerCase();
    return hay.includes(q);
  });

  return arr;
});

watch(() => inventoryFilter.q, (q) => { debouncedFetchInventory(String(q || '').trim()); });

const scheduleHidePersonas = () => {
  window.setTimeout(() => {
    mostrarPersonas.value = false;
  }, 180);
};

const scheduleHideSugerencias = () => {
  window.setTimeout(() => {
    mostrarSugerencias.value = false;
  }, 180);
};


function actaPdf_escapeHtml(unsafe) {
  if (unsafe === null || typeof unsafe === 'undefined') return '';
  return String(unsafe)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

/* ----------------- acta PDF -------------------------*/
const html = actaPdf2_generateActaHTML({
  logoData: '/images/logo.png'
});

function actaPdf2_generateActaHTML({ nActa, nombre, lugar, distrito, fecha, items, proyectoNombre, logoData = null }) {
  const rows = (items || []).map((it) => {
    const productoLabel = actaPdf_escapeHtml(it.producto_label || it.producto || it.producto_code || '—');
    const um = actaPdf_escapeHtml(it.um || '—');
    const qty = actaPdf_escapeHtml(Number(it.cantidad || 0));
    return `<tr>
      <td style="padding:6px;vertical-align:top">${productoLabel}</td>
      <td class="unit-col" style="padding:6px;text-align:center;vertical-align:top">${um}</td>
      <td class="qty-col" style="padding:6px;text-align:center;vertical-align:top">${qty}</td>
    </tr>`;
  }).join('') || `<tr><td colspan="3" style="padding:8px;text-align:center;color:#666">No hay items</td></tr>`;

  const css = `
    html,body{margin:0;padding:0;background:#f2f2f2}
    body{display:flex;justify-content:center;padding:10mm 0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif}
    .paper{width:210mm;min-height:297mm;background:#fff;padding:18mm;box-shadow:0 6px 18px rgba(0,0,0,0.08);border-radius:4px;border:1px solid #e2e6ef;box-sizing:border-box;color:#222}
    header{display:flex;justify-content:space-between;margin-bottom:6px;align-items:flex-start}
    .left-meta{display:flex;align-items:flex-start;gap:12px}
    .logo {width: 60px;height: 60px;border-radius: 6px;flex-shrink: 0;overflow: hidden;}
    .org-data{font-size:12px;color:#333}
    .org-title{font-weight:bold;font-size:14px;margin-bottom:4px}
    .right-meta{text-align:right;color:#555;font-size:12px}
    h1{text-align:center;color:#1e4ea8;margin:6px 0 12px 0;font-size:18px;letter-spacing:0.4px}
    .intro{font-size:12px;color:#333;line-height:1.45;margin-bottom:8px}
    .checkboxes{margin:8px 0 12px 0;font-size:12px;color:#2b2b2b}
    .doc-table{width:100%;border-collapse:collapse;margin-top:6px;margin-bottom:10px;font-size:12px;table-layout:fixed;line-height:1.1}
    .doc-table th,.doc-table td{border:1px solid #333;padding:6px 8px;vertical-align:top;text-align:left;word-break:break-word;overflow-wrap:break-word}
    .doc-table th{background:#e6eefc;text-align:left;font-weight:600}
    .unit-col,.qty-col{text-align:center;width:80px}
    .sign-row{display:flex;gap:18px;justify-content:space-between;margin-top:18px;flex-wrap:wrap;align-items:flex-start}
    .sign-box{flex:1 1 44%;max-width:44%;min-width:200px;background:transparent;padding-top:36px;padding-bottom:6px;padding-left:8px;padding-right:8px;box-sizing:border-box;position:relative}
    .sign-line{width:60%;margin:0 auto 6px auto;border-top:1.25px solid #000;height:0;box-sizing:border-box}
    .sign-box .sign-meta { text-align:center !important; margin:0 0 6px 0; font-size:0.75rem; line-height:1.05; padding:0; font-weight:700; letter-spacing:0.6px; }
    .field-row{display:flex;align-items:center;gap:12px;margin:8px 0}
    .field-label{width:36%;min-width:100px;font-weight:600;font-size:0.86rem;text-align:left;color:#333}
    .field-value{flex:1;min-height:20px;border-bottom:1px solid #000;box-sizing:border-box;padding:4px 6px;white-space:nowrap;overflow:hidden}
    .smallnote{font-size:11px;color:#666;margin-top:6px}
    tbody tr td{page-break-inside:avoid}
    @media print{body{padding:0}.paper{box-shadow:none;border:none}@page{size:A4;margin:10mm}.doc-table{font-size:10px}.doc-table th,.doc-table td{padding:4px 6px}}
  `;

  const introText = `En la localidad de <strong>${actaPdf_escapeHtml(lugar)}</strong>, distrito de <strong>${actaPdf_escapeHtml(distrito)}</strong>, siendo las <strong>.............</strong> horas del día <strong>06 de octubre de 2025</strong>, se procede a la entrega y recepción de materiales/implementos al Sr (a) <strong>${actaPdf_escapeHtml(nombre)}</strong>, los cuales se detallan en la siguiente relación, en el marco de las actividades del proyecto antes mencionado.`;


  const checksHtml = `
  <div style="font-family: Arial, sans-serif; font-size:13px; line-height:1.5; margin-top:10px; max-width:700px;">
    <p>Los bienes entregados serán utilizados únicamente para los fines y actividades del proyecto, bajo responsabilidad del receptor, quien se compromete a hacer un uso adecuado y responsable de los mismos.</p>
    <p>Asimismo, el material podrá ser empleado en acciones de promotoría, asesoría, capacitación, actividades comunitarias y para actividades operativas del personal, según corresponda.</p>
  </div>
  `;


  const Htmlmarca = `
    <div style="font-family: Arial, sans-serif; font-size:13px; margin-top:12px; max-width:700px;">
      <p>Marque con una “X” la opción correspondiente:</p>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <div>La entrega - recepción es a título personal/familiar.</div>
        <div style="width:18px; height:18px; border:2px solid #2a4d9b; text-align:center; line-height:14px; display:flex; align-items:center; justify-content:center;">&nbsp;</div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <div>La entrega - recepción es para uso de varias familias y/o comunidad.</div>
        <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <div>La entrega - recepción es para la realización de trabajos en promotoría / asesoría / capacitación.</div>
        <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>La entrega - recepción es para actividades operativas del personal.</div>
        <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
      </div>
    </div>
  `;



  return `<!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Acta de Entrega - Recepción - ${actaPdf_escapeHtml(nActa || '')}</title>
    <style>${css}</style>
  </head>
  <body>
    <div class="paper">
      <header>
        <div class="left-meta">
          <div class="logo">
            <img src="/images/logo.png" alt="logo">
          </div>
          <div class="org-data">
            <div class="org-title">Islas de Paz Perú</div>
            <div style="font-size:12px;color:#444">RUC: <strong>20600630769</strong></div>
            <div style="font-size:12px;color:#444">Organización no Gubernamental</div>
            <div style="font-size:12px;color:#444">Dirección Jr. Faustino Sánchez Carrión N° 117, Amarilis - Huánuco</div>
          </div>
        </div>
        <div class="right-meta">
          <div><strong>Número:</strong> ${actaPdf_escapeHtml(nActa || '')}</div>
          <div><strong>Proyecto:</strong> ${actaPdf_escapeHtml(proyectoNombre || '')}</div>
        </div>
      </header>

      <h1>ACTA DE ENTREGA - RECEPCIÓN</h1>

      <div class="intro">${introText}</div>

      ${checksHtml}
      ${Htmlmarca}

      <table class="doc-table">
        <thead>
          <tr>
            <th>DETALLE DE LA ENTREGA-RECEPCIÓN</th>
            <th class="unit-col">UNIDAD DE MEDIDA</th>
            <th class="qty-col">CANTIDAD</th>
          </tr>
        </thead>
        <tbody>
          ${rows}
        </tbody>
      </table>

      <div class="smallnote">En señal de conformidad con la entrega y recepción de los bienes antes descritos, firman los presentes:</div>

      <div class="sign-row" style="margin-top: 80px;">
        <div class="sign-box" id="sign-entrego-box">
          <div class="sign-line"></div>
          <div class="sign-meta"><strong>ENTREGÓ CONFORME</strong></div>
          <div class="field-row"><div class="field-label">NOMBRES:</div><div class="field-value"></div></div>
          <div class="field-row"><div class="field-label">APELLIDOS:</div><div class="field-value"></div></div>
          <div class="field-row"><div class="field-label">DNI:</div><div class="field-value"></div></div>
        </div>

        <div class="sign-box" id="sign-recibio-box">
          <div class="sign-line"></div>
          <div class="sign-meta"><strong>RECIBIÓ CONFORME</strong></div>
          <div class="field-row"><div class="field-label">NOMBRES:</div><div class="field-value"></div></div>
          <div class="field-row"><div class="field-label">APELLIDOS:</div><div class="field-value"></div></div>
          <div class="field-row"><div class="field-label">DNI:</div><div class="field-value"></div></div>
        </div>
      </div>
    </div>
  </body>
  </html>`;
}

function actaPdf2_onGenerateActaImmediate() {
  const w = window.open('', '_blank', 'noopener,noreferrer');
  if (!w) { Swal.fire('Error', 'Permite popups y vuelve a intentarlo.', 'error'); return; }
  try { w.document.open(); w.document.write('<!doctype html><html><head><meta charset="utf-8"/><title>Generando acta...</title></head><body><p style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif;padding:16px;">Generando acta, espera...</p></body></html>'); w.document.close(); w.focus(); } catch (err) { }
  if (!form.nombre || !form.lugar || !form.distrito) { try { w.close(); } catch (e) { }; Swal.fire('Falta información', 'Completa nombre, lugar y distrito antes de generar el acta.', 'warning'); return; }
  if (!draft.value.length) { try { w.close(); } catch (e) { }; Swal.fire('Borrador vacío', 'Agrega al menos un item al borrador antes de generar el acta.', 'warning'); return; }

  const nActaValue = codigoGenerado.value;
  const html = actaPdf2_generateActaHTML({
    nActa: nActaValue,
    nombre: form.nombre,
    lugar: form.lugar,
    distrito: form.distrito,
    fecha: form.fecha,
    items: draft.value,
    proyectoNombre: props.proyecto?.nombre || ''
  });

  try {
    w.document.open();
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(() => { try { w.print(); } catch (err) { } }, 300);
  } catch (err) {
    try { w.close(); } catch (e) { }
    Swal.fire('Error', 'No se pudo generar la vista imprimible.', 'error');
  }
}

function actaPdf2_downloadHtmlFile() {
  if (!form.nombre || !form.lugar || !form.distrito) { Swal.fire('Falta información', 'Completa nombre, lugar y distrito.', 'warning'); return; }
  if (!draft.value.length) { Swal.fire('Borrador vacío', 'Agrega items antes de generar el acta.', 'warning'); return; }

  const nActaValue = codigoGenerado.value;
  const html = actaPdf2_generateActaHTML({
    nActa: nActaValue,
    nombre: form.nombre,
    lugar: form.lugar,
    distrito: form.distrito,
    fecha: form.fecha,
    items: draft.value,
    proyectoNombre: props.proyecto?.nombre || ''
  });

  const blob = new Blob([html], { type: 'text/html' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${nActaValue}.html`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function actaPdf2_downloadPdfFile() {
  // validaciones (igual que antes)
  if (!form.nombre || !form.lugar || !form.distrito) { Swal.fire('Falta información', 'Completa nombre, lugar y distrito.', 'warning'); return; }
  if (!draft.value.length) { Swal.fire('Borrador vacío', 'Agrega items antes de generar el acta.', 'warning'); return; }

  const nActaValue = codigoGenerado.value || 'acta';
  const html = actaPdf2_generateActaHTML({
    nActa: nActaValue,
    nombre: form.nombre, lugar: form.lugar, distrito: form.distrito,
    fecha: form.fecha, items: draft.value, proyectoNombre: props.proyecto?.nombre || ''
  });

  // convertir mm->px a 96dpi
  const mmToPx = (mm, dpi = 96) => Math.round(mm * (dpi / 25.4));
  const a4WidthPx = mmToPx(210, 96); // ~794px
  const container = document.createElement('div');
  container.style.position = 'fixed';
  container.style.left = '-10000px';
  container.style.top = '0';
  container.style.width = `${a4WidthPx}px`;
  container.style.boxSizing = 'border-box';
  container.innerHTML = html;
  document.body.appendChild(container);

  const elementToPdf = container.querySelector('.paper') || container;
  elementToPdf.style.margin = '0 auto';
  elementToPdf.style.boxSizing = 'border-box';
  elementToPdf.style.width = `${a4WidthPx}px`;

  // esperar recursos (img + fonts)
  await (async function waitResources(root, timeout = 10000) {
    const imgs = Array.from(root.querySelectorAll('img'));
    const imgPromises = imgs.map(img => new Promise(res => {
      if (!img.src) return res();
      if (img.complete && img.naturalWidth !== 0) return res();
      const done = () => { img.removeEventListener('load', done); img.removeEventListener('error', done); res(); };
      img.addEventListener('load', done); img.addEventListener('error', done);
      setTimeout(done, 5000);
    }));
    const fontPromise = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    await Promise.race([Promise.all([...imgPromises, fontPromise]), new Promise(r => setTimeout(r, timeout))]);
    await new Promise(r => setTimeout(r, 120)); // espera extra
  })(container);

  // cargar html2pdf
  await (async function loadHtml2Pdf() {
    if (window.html2pdf) return;
    await new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
      s.onload = () => setTimeout(() => window.html2pdf ? resolve() : reject(new Error('html2pdf no inicializó')), 150);
      s.onerror = () => reject(new Error('No se pudo cargar html2pdf'));
      document.head.appendChild(s);
      setTimeout(() => { if (!window.html2pdf) reject(new Error('Timeout cargando html2pdf')); }, 10000);
    });
  })();
  try {
    // escala alta para más nitidez. Si falla por memoria reduce a 2.
    const scale = 3; // <- subir para mayor nitidez (2-3). OJO memoria.
    const filename = `${String(nActaValue).replace(/[\\\/:*?"<>|]/g, '_')}.pdf`;
    const opt = {
      margin: 8,
      filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale,
        useCORS: true,
        allowTaint: false,
        logging: false,
        width: a4WidthPx,
        windowWidth: a4WidthPx,
        dpi: 300
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'] }
    };

    // Render "toPdf" y luego "save" (más control)
    await new Promise((resolve, reject) => {
      try {
        window.html2pdf().set(opt).from(elementToPdf).toPdf().get('pdf').then(() => {
          window.html2pdf().set(opt).from(elementToPdf).save(filename, () => resolve());
        }).catch(err => reject(err));
      } catch (err) {
        reject(err);
      }
    });

  } catch (err) {
    console.error('Error generando PDF cliente:', err);
    Swal.fire('Error', 'No se pudo generar el PDF en el navegador. Intentando abrir vista imprimible...', 'error');
    // fallback: abrir html en nueva ventana (vectorial si el usuario imprime)
    try {
      const w = window.open('', '_blank', 'noopener,noreferrer');
      if (w) { w.document.open(); w.document.write(html); w.document.close(); w.focus(); setTimeout(() => { try { w.print(); } catch (e) { } }, 300); }
      else Swal.fire('Error', 'Permite popups e inténtalo nuevamente.', 'error');
    } catch (e) { console.error(e); }
  } finally {
    try { document.body.removeChild(container); } catch (e) { }
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto p-6">
      <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Registrar Salida</h2>

      <div class="grid grid-cols-12 gap-6">
        <!-- Formulario principal -->
        <section class="col-span-8 bg-white dark:bg-gray-800 p-6 rounded shadow">
          <!-- N° Acta -->
          <div class="mb-4">
            <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">N° Acta</label>
            <div class="flex items-center gap-2">
              <span class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded">AE -</span>

              <input v-model="form.codigo1" maxlength="3" aria-label="Código 1"
                class="px-3 py-2 rounded w-24 text-center bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300" />

              <span class="px-2 text-gray-700 dark:text-gray-300">-</span>

              <input v-model="form.codigo2" maxlength="4" aria-label="Código 2"
                class="px-3 py-2 rounded w-28 text-center bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300" />

              <div class="ml-4 text-sm text-gray-600 dark:text-gray-300">
                Código generado: <strong class="text-gray-900 dark:text-gray-100">{{ codigoGenerado }}</strong>
              </div>
            </div>
          </div>
          <!-- Encargado -->
          <div class="mt-5">
            <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">
              Nombre del encargado
            </label>
            <input v-model="form.nombre_encargado" type="text" placeholder="Ingresa el nombre completo del encargado"
              class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 
           border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            <div class="mt-5"></div>
          </div>

          <!-- Persona (predictivo) -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">Nombre / Persona</label>

              <div class="relative">
                <div class="flex gap-2">
                  <input id="persona_nombre" v-model="form.nombre" @input="onPersonaInput"
                    @focus="mostrarPersonas = String(form.nombre || '').trim().length > 0" @blur="scheduleHidePersonas"
                    placeholder="Escribe nombre de persona..."
                    class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none"
                    autocomplete="off" />

                  <button type="button" @click="mostrarPersonas = !mostrarPersonas"
                    class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    aria-label="Mostrar personas">
                    Personas
                  </button>
                </div>

                <!-- Predictivo personas -->
                <ul v-if="mostrarPersonas && personasFiltradas.length > 0"
                  class="absolute z-10 dark:text-white bg-white dark:bg-gray-700 border rounded w-full mt-1 max-h-40 overflow-auto shadow-lg">
                  <li v-for="p in personasFiltradas" :key="p.id" @mousedown.prevent="seleccionarPersona(p)"
                    class="p-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600">
                    {{ p.nombre }} <span class="text-xs text-gray-500 dark:text-gray-400">— {{ p.distrito || p.lugar ||
                      '' }}</span>
                  </li>
                </ul>
              </div>

              <!-- hidden persona_id (se queda vacío si es persona nueva) -->
              <input type="hidden" :value="form.persona_id" />
            </div>

            <!-- Lugar y distrito -->
            <div>
              <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">Lugar</label>
              <input v-model="form.lugar"
                class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none" />

              <label class="block font-semibold mt-3 mb-1 text-gray-700 dark:text-gray-200">Distrito</label>
              <input v-model="form.distrito"
                class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none" />
            </div>
          </div>

          <!-- Fecha -->
          <div class="mt-4">
            <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">Fecha</label>
            <input v-model="form.fecha" type="date"
              class="p-2 rounded bg-white dark:bg-gray-700 w-full text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none" />
          </div>

          <hr class="my-4 border-gray-200 dark:border-gray-700" />

          <!-- Producto -->
          <div class="grid grid-cols-4 gap-3 items-end">
            <div class="col-span-2">
              <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">Código / Producto</label>
              <div class="flex gap-2 relative">
                <input id="producto_code" v-model="form.producto_code" @input="onProductoInput"
                  @focus="mostrarSugerencias = String(form.producto_code || '').trim().length > 0"
                  @blur="scheduleHideSugerencias" placeholder="Escribe código o nombre..."
                  class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none" />
                <button type="button" @click="openInventory"
                  class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded focus:outline-none focus:ring-2 focus:ring-indigo-300">
                  Inventario
                </button>

                <!-- Predictivo productos (local) -->
                <ul v-if="mostrarSugerencias && productosFiltrados.length > 0"
                  class="absolute z-10 dark:text-white bg-white dark:bg-gray-700 border rounded w-full mt-1 max-h-40 overflow-auto shadow-lg">
                  <li v-for="p in productosFiltrados" :key="p.code || p.raw?.id"
                    @mousedown.prevent="seleccionarProducto(p)"
                    class="p-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600">
                    {{ p.producto || p.descripcion || p.code }}
                  </li>
                </ul>
              </div>

              <!-- Resumen del producto elegido -->
              <div v-if="form.producto_label" class="col-span-4">
                <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">
                  Producto seleccionado
                </label>
                <div class="p-3 rounded bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                  <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                    {{ form.producto_label }}
                  </div>
                  <div class="text-sm text-gray-600 dark:text-gray-300">
                    Código: <strong>{{ form.producto_code }}</strong> • UM: <strong>{{ form.um || '—' }}</strong>
                  </div>
                </div>
              </div>

            </div>

            <!-- Cantidad -->
            <div>
              <label class="block font-semibold mb-1 text-gray-700 dark:text-gray-200">Cantidad</label>
              <p class="text-gray-700 dark:text-gray-300">
                Principal: <span class="font-bold text-indigo-600">{{ form.um }}</span>
              </p>

              <div class="flex gap-2 items-center">
                <input type="number" min="0.0001" step="0.0001" v-model.number="form.cantidad"
                  class="w-full p-2 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 focus:outline-none" />
              </div>
            </div>

            <!-- UM y acciones -->
            <div>
              <div class="mt-2 flex gap-2">
                <button type="button" @click="addToDraft"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-300">
                  Agregar
                </button>
              </div>
            </div>
          </div>

          <!-- Botones principales -->
          <div class="mt-6 flex gap-3">
            <button @click="finalizeSave({ maintain: false })"
              class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-300">
              Finalizar y Guardar
            </button>

            <button @click="clearDraft"
              class="bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 px-4 py-2 rounded text-gray-800 dark:text-gray-100 focus:outline-none">
              Limpiar borrador
            </button>

            <button type="button" @click="volverATabla"
              class="ml-auto bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700 focusable">
              ↩ Volver
            </button>

          </div>
        </section>

        <!-- Panel derecho (borrador) -->
        <aside class="col-span-4">
          <div class="bg-white dark:bg-gray-900 p-4 rounded shadow mb-4">
            <h3 class="font-bold mb-2 text-gray-900 dark:text-gray-100">Borrador de Acta</h3>

            <div class="text-sm text-gray-600 dark:text-gray-300 mb-3">
              Acta provisional: <strong class="text-gray-900 dark:text-gray-100">{{ codigoGenerado }}</strong>
            </div>

            <div v-if="!draft.length"
              class="p-4 text-sm text-gray-500 dark:text-gray-400 border rounded bg-gray-50 dark:bg-gray-800">
              Aún no hay items en el borrador.
            </div>

            <ul v-else class="space-y-2 max-h-56 overflow-auto">
              <li v-for="(it, idx) in draft" :key="it.id"
                class="flex items-center justify-between p-2 border rounded bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700">
                <div>
                  <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ idx + 1 }}. {{ it.producto }} <span class="text-xs text-gray-500 dark:text-gray-400">({{ it.um
                      }})</span>
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    Código: {{ it.producto_code }} — Cant: <strong>{{ it.cantidad }}</strong>
                  </div>
                </div>

                <div class="flex flex-col items-end gap-1">
                  <div class="flex gap-1">
                    <button @click="moveDraftItem(it.id, 'up')"
                      class="px-2 py-1 text-xs border rounded bg-white dark:bg-gray-700">↑</button>
                    <button @click="moveDraftItem(it.id, 'down')"
                      class="px-2 py-1 text-xs border rounded bg-white dark:bg-gray-700">↓</button>
                    <button @click="editDraftItem(it.id)"
                      class="px-2 py-1 text-xs border rounded bg-white dark:bg-gray-700">✏️</button>
                    <button @click="removeDraftItem(it.id)"
                      class="px-2 py-1 text-xs border rounded text-red-600 bg-white dark:bg-gray-700">🗑</button>
                  </div>
                </div>
              </li>
            </ul>

            <div class="mt-3 border-t pt-3 border-gray-200 dark:border-gray-700">
              <div class="text-sm text-gray-600 dark:text-gray-300">Líneas: <strong>{{ draftTotals.lines }}</strong>
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-300">Total cantidad (suma numérica): <strong>{{
                draftTotals.totalQty }}</strong>
              </div>
              <div class="mt-2">
                <button @click="actaPdf2_downloadPdfFile"
                  class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                  Generar Acta (Plantilla)
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <!-- Modal Inventario (mejorado: tabla y filtros reducidos) -->
      <div v-if="showInventoryModal" class="fixed inset-0 z-50 flex items-start justify-center p-6" role="dialog"
        aria-modal="true" aria-label="Modal de Inventario">
        <div class="absolute inset-0 bg-black/40" @click="closeInventory"></div>

        <div class="relative w-full max-w-5xl bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
          <header class="p-4 border-b dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Buscar en Inventario — {{
                proyecto.nombre }}</h3>
              <span v-if="inventoryLoading" class="text-sm text-gray-500 dark:text-gray-400">Cargando…</span>
            </div>
            <button @click="closeInventory"
              class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100">Cerrar</button>
          </header>

          <div class="p-4">
            <!-- filtros reducidos: q | solicitante | categoria | disponible -->
            <div class="grid grid-cols-12 gap-3 items-end mb-4">
              <div class="col-span-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Buscar</label>
                <input v-model="inventoryFilter.q" placeholder="Código, nombre, descripción, solicitante..."
                  class="w-full p-2 border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
              </div>

              <div class="col-span-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Solicitado por</label>
                <select v-model="inventoryFilter.solicitado_por"
                  class="w-full p-2 border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                  <option value="">— Todos —</option>
                  <option v-for="s in inventorySolicitantes" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>

              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Categoría</label>
                <select v-model="inventoryFilter.categoria"
                  class="w-full p-2 border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                  <option value="">— Todas —</option>
                  <option v-for="c in inventoryCategorias" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>

              <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Disponible</label>
                <input type="checkbox" v-model="inventoryFilter.onlyAvailable" class="mt-2" />
              </div>
            </div>

            <div v-if="inventoryError" class="text-sm text-red-600 mb-2">{{ inventoryError }}</div>
            <div v-if="inventoryLoading" class="text-sm  mb-2 text-gray-700 dark:text-gray-300">Cargando inventario…
            </div>

            <!-- tabla -->
            <div class="overflow-auto border rounded" style="max-height:420px;">
              <table class="min-w-full divide-y divide-gray-200 text-base">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th class="px-4 py-2 text-left">Producto</th>
                    <th class="px-4 py-2 text-left">Código</th>
                    <th class="px-4 py-2 text-left">UM</th>
                    <th class="px-4 py-2 text-left">Stock</th>
                    <th class="px-4 py-2 text-left">Solicitado por</th>
                    <th class="px-4 py-2 text-left">Categoría</th>
                    <th class="px-4 py-2 text-left">Acción</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                  <tr v-for="item in filteredInventory" :key="item.code || item.raw?.id"
                    class="hover:bg-gray-100 dark:hover:bg-gray-700">
                    <td class="px-4 py-3 align-top text-sm font-medium text-gray-900 dark:text-gray-100">{{
                      item.producto || item.descripcion || '—' }}</td>
                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ item.code ||
                      item.raw?.id || '—' }}</td>
                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ item.um || '—' }}</td>
                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ item.stock ?? '—' }}
                    </td>
                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ item.solicitado_por ||
                      '—' }}</td>
                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ item.categoria || '—' }}
                    </td>
                    <td class="px-4 py-3 align-top">
                      <button @click="selectInventoryProduct(item)"
                        class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm">Seleccionar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="!inventoryLoading && filteredInventory.length === 0"
              class="p-4 text-sm text-gray-500 dark:text-gray-400 border rounded bg-gray-50 dark:bg-gray-800 mt-3">No
              hay coincidencias en el inventario.</div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
