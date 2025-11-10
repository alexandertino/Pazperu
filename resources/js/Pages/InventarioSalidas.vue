<script setup>
/* ==========================
📌 IMPORTACIONES Y CONFIGURACIÓN
========================== */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from "sweetalert2";


const user = usePage().props.auth.user;

const props = defineProps({
    proyecto: Object,
    inventarios: Array,
    salidas: Array,
    caja: Array,
    banco: Array,
    easy: Array,
    user: Object
});


const toggleComentario = (id) => {
    comentarioActivo.value = comentarioActivo.value === id ? null : id
}
/* ==========================
📌 ESTADOS Y VARIABLES REACTIVAS
========================== */
const pestañaActiva = ref('inventario');
const filtroInventario = ref('');
const tablaVisible = ref('caja');
const filtroSalidas = ref('');
const filtroCategoria = ref("todos");
const filtroStock = ref(false);
const filtroSolicitadoPor = ref("todos");
const filtroProyectoLg = ref("todos");

const ordenInventarioAsc = ref(true);
const ordenSalidasAsc = ref(true);

const comentarioActivo = ref(null);
const tooltipVisible = ref(false);
const productoTooltip = ref(null);
const mostrarDetalleTotal = ref(false);
const categoriasExpandida = reactive({});

const page = usePage();
const authUser = (page.props && page.props.auth && page.props.auth.user) ? page.props.auth.user : (props.user || {});
const actasCaja = computed(() => props.caja || []);
const actasBanco = computed(() => props.banco || []);
const itemsEasy = computed(() => props.easy || []);
const tablaVisibleLabel = computed(() => {
    if (tablaVisible.value === 'caja') return 'Caja'
    if (tablaVisible.value === 'banco') return 'Banco'
    if (tablaVisible.value === 'easy') return 'Easy'
    return ''
})


// Helpers (robustos para formatos:
// "YYYY-MM-DD", "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DDTHH:MM:SSZ", etc.)
function getDateParts(fecha) {
  const d = parseFecha(fecha);
  if (!d) return { mes: null, anio: null };
  return { mes: d.getMonth() + 1, anio: d.getFullYear() };
}

// Filtros (reemplaza/añade donde tengas tus computed)
const actasCajaFiltradas = computed(() =>
    (props.caja || []).filter(item => {
        const { mes, anio } = getDateParts(item.fecha);
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

const actasBancoFiltradas = computed(() =>
    (props.banco || []).filter(item => {
        const { mes, anio } = getDateParts(item.fecha);
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

const itemsEasyFiltrados = computed(() =>
    (props.easy || []).filter(item => {
        // si el campo easy usa created_at o fecha, ajusta aquí: item.fecha o item.created_at
        const { mes, anio } = getDateParts(item.fecha);
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

const hoy = new Date()
const mesActivo = ref(hoy.getMonth() + 1)
const anioActivo = ref(hoy.getFullYear())

// ✅ Función para sacar mes/año de un string YYYY-MM-DD
function getMes(fecha) {
    return parseInt(fecha.substring(5, 7))
}
function getAnio(fecha) {
    return parseInt(fecha.substring(0, 4))
}

function prevMonth() {
    if (mesActivo.value === 1) { mesActivo.value = 12; anioActivo.value--; }
    else mesActivo.value--;
}
function nextMonth() {
    if (mesActivo.value === 12) { mesActivo.value = 1; anioActivo.value++; }
    else mesActivo.value++;
}
function nombreMes(m) {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return meses[(m - 1 + 12) % 12] ?? m;
}

// formateador simple de números
function formatNumber(n) {
    if (n === null || n === undefined) return '0.00';
    const num = Number(n);
    if (isNaN(num)) return n;
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const tipoCambio = ref(0);

function toGestion(value, itemTc = null) {
    const tc = Number(itemTc ?? tipoCambio.value) || 1;
    return Number(value || 0) / tc;
}

const tabClass = "bg-gray-100 text-gray-700"
const activeTabClass = "bg-blue-600 text-white"
const badgeClass = computed(() => {
    if (tablaVisible.value === 'caja') return 'bg-green-100 text-green-800';
    if (tablaVisible.value === 'banco') return 'bg-blue-100 text-blue-800';
    return 'bg-yellow-100 text-yellow-800';
});

const setTabla = (tipo) => {
    tablaVisible.value = tipo
}
/* ==========================
📌 COMPUTED / ESTADOS SIMPLIFICADOS PARA "PRECIOS"
========================== */

// --- FILTROS / ESTADOS para Pestaña Precios (simplificado) ---
const filtroPrecioBuscar = ref('');
const filtroPrecioMin = ref('');
const filtroPrecioMax = ref('');
const topN = ref(10);

// Nuevo: filtro por solicitante (para la vista Precios)
const filtroSolicitantePrecio = ref('todos');
const filtroEncargado = ref('todos');

const conteoPorEstado = computed(() => {
    // Solo calcular si se seleccionó un encargado específico
    if (filtroEncargado.value === 'todos') return null;

    const filtradas = salidasFiltradas.value;

    const pendiente = filtradas.filter(s => s.estado?.toLowerCase() === 'pendiente').length;
    const aceptado = filtradas.filter(s => s.estado?.toLowerCase() === 'aceptado').length;

    return { pendiente, aceptado };
});


const encargadosUnicos = computed(() => {
    const nombres = props.salidas?.map(s => s?.nombre_encargado)?.filter(Boolean) || [];
    return ['todos', ...new Set(nombres)];
});

const salidasFiltradas = computed(() =>
    [...(props.salidas || [])]
        .filter(s => {
            const q = (filtroSalidas.value || '').toString().toLowerCase().trim();
            const nombre = String(s?.nombre || '').toLowerCase();
            const nActa = String(s?.n_acta || '').toLowerCase();
            const lugar = String(s?.lugar || '').toLowerCase();
            const productoCode = String(s?.producto_code || s?.producto || '').toLowerCase();
            const encargado = String(s?.nombre_encargado || '').toLowerCase();

            const matchTexto =
                !q || nombre.includes(q) || nActa.includes(q) || lugar.includes(q) || productoCode.includes(q);

            const matchEstado =
                filtroEstado.value === 'todos' ||
                (String(s?.estado || '').toLowerCase() === String(filtroEstado.value || '').toLowerCase());

            const matchEncargado =
                filtroEncargado.value === 'todos' ||
                encargado === String(filtroEncargado.value || '').toLowerCase();

            return matchTexto && matchEstado && matchEncargado;
        })
        .sort((a, b) =>
            ordenSalidasAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

// totales con defensas
const totalInventario = computed(() =>
    (props.inventarios || []).reduce((sum, item) =>
        sum + (Number(item?.precio ?? 0) * Number(item?.stock ?? 0)), 0)
);
const totalEntradas = computed(() =>
    (props.inventarios || []).reduce((sum, item) =>
        sum + (Number(item?.precio ?? 0) * Number(item?.entradas ?? 0)), 0)
);
const totalSalidas = computed(() =>
    (props.inventarios || []).reduce((sum, item) =>
        sum + (Number(item?.precio ?? 0) * Number(item?.salidas ?? 0)), 0)
);

// resumenCategorias seguro
const resumenCategorias = computed(() =>
    (props.inventarios || []).reduce((acc, item) => {
        const cat = item?.categoria ?? 'Sin categoría';
        const valor = Number(item?.precio ?? 0) * Number(item?.stock ?? 0);
        acc[cat] = (acc[cat] || 0) + valor;
        return acc;
    }, {})
);

// listas
const categorias = computed(() =>
    [...new Set((props.inventarios || []).map(i => i?.categoria ?? 'Sin categoría'))]
);
const solicitantesUnicos = computed(() =>
    [...new Set((props.inventarios || []).map(i => i?.solicitado_por).filter(Boolean))]
);

// inventarioFiltrado (sin cambios funcionales)
const inventarioFiltrado = computed(() =>
    [...(props.inventarios || [])]
        .filter(i => {
            const q = filtroInventario.value.toLowerCase();
            const desc = String(i?.descripcion || '').toLowerCase();
            const cod = String(i?.codigo || '').toLowerCase();
            const cat = String(i?.categoria || '').toLowerCase();
            return desc.includes(q) || cod.includes(q) || cat.includes(q);
        })
        .filter(i => filtroCategoria.value === "todos" || i?.categoria === filtroCategoria.value)
        .filter(i => filtroSolicitadoPor.value === "todos" || i?.solicitado_por === filtroSolicitadoPor.value)
        .filter(i => filtroProyectoLg.value === "todos" || i?.proyecto_lg === filtroProyectoLg.value)
        .filter(i => !filtroStock.value || Number(i?.stock ?? 0) > 0)
        .sort((a, b) =>
            ordenInventarioAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

// resumen por solicitante
const resumenSolicitadoPor = computed(() =>
    (props.inventarios || []).reduce((acc, item) => {
        if (!item?.solicitado_por) return acc;
        const valor = Number(item?.precio ?? 0) * Number(item?.stock ?? 0);
        acc[item.solicitado_por] = (acc[item.solicitado_por] || 0) + valor;
        return acc;
    }, {})
);

// expandir detalle
const solicitadoExpandido = reactive({});
const toggleSolicitado = (user) => {
    solicitadoExpandido[user] = !solicitadoExpandido[user];
};

// extras para Precios
const maxValorItem = computed(() => {
    const valores = (props.inventarios || []).map(i => Number(i?.stock ?? 0) * Number(i?.precio ?? 0));
    const max = valores.length ? Math.max(...valores) : 0;
    return Number.isFinite(max) ? max : 0;
});

const categoriasResumenArray = computed(() =>
    Object.entries(resumenCategorias.value || {}).map(([cat, val]) => ({
        cat,
        val: Number(val) || 0
    })).sort((a, b) => b.val - a.val)
);

// preciosFiltrados: filtro por búsqueda, rango precio y solicitante; orden por valor descendente (más valiosos arriba)
const preciosFiltrados = computed(() => {
    return [...(props.inventarios || [])]
        .filter(i => {
            const q = filtroPrecioBuscar.value.trim().toLowerCase();
            if (!q) return true;
            return String(i?.descripcion || '').toLowerCase().includes(q) || String(i?.codigo || '').toLowerCase().includes(q);
        })
        .filter(i => filtroPrecioMin.value === '' || Number(i?.precio ?? 0) >= Number(filtroPrecioMin.value))
        .filter(i => filtroPrecioMax.value === '' || Number(i?.precio ?? 0) <= Number(filtroPrecioMax.value))
        .filter(i => filtroSolicitantePrecio.value === 'todos' || String(i?.solicitado_por ?? '') === String(filtroSolicitantePrecio.value))
        .sort((a, b) => (Number(b?.stock ?? 0) * Number(b?.precio ?? 0)) - (Number(a?.stock ?? 0) * Number(a?.precio ?? 0)));
});

const topItems = computed(() => preciosFiltrados.value.slice(0, Number(topN.value)));

// estadísticas del solicitante seleccionado
const preciosSolicitanteStats = computed(() => {
    if (filtroSolicitantePrecio.value === 'todos') return { count: 0, totalValue: 0, avgPrice: 0 };
    const items = (props.inventarios || []).filter(i => String(i?.solicitado_por ?? '') === String(filtroSolicitantePrecio.value));
    const count = items.length;
    const totalValue = items.reduce((s, it) => s + (Number(it?.stock ?? 0) * Number(it?.precio ?? 0)), 0);
    const avgPrice = items.reduce((s, it) => s + Number(it?.precio ?? 0), 0) / (count || 1);
    return { count, totalValue: Number(totalValue), avgPrice: Number.isFinite(avgPrice) ? avgPrice : 0 };
});



/* ==========================
📌 ACCIONES (AGREGAR, ELIMINAR, EXPORTAR, SALIDA_DE_PRODUCTO)
========================== */
const agregarInventario = () => router.visit(`/proyectos/${props.proyecto.id}/inventarios/create`);
const agregarSalida = () => router.visit(`/proyectos/${props.proyecto.id}/salidas/create`);
const agregarAM = () => router.visit(`/proyectos/${props.proyecto.id}/am/create`)
const agregarEASY = () => router.visit(`/proyectos/${props.proyecto.id}/easy/create`)


const eliminarRegistro = (id) => {
    Swal.fire({
        title: '¿Eliminar inventario?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            axios.delete(`/proyectos/${props.proyecto.id}/inventarios/${id}`)
                .then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Inventario eliminado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch((error) => {
                    console.error("❌ Error eliminando inventario:", error.response ?? error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo eliminar el inventario'
                    });
                });
        }
    });
};

const eliminarSalida = (id) => {
    Swal.fire({
        title: '¿Eliminar salida?',
        text: 'Se restaurará el stock en inventario.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            axios.delete(`/proyectos/${props.proyecto.id}/salidas/${id}`)
                .then((res) => {
                    // Aunque sea 204, lo tratamos como éxito
                    if (res.status === 200 || res.status === 204) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Salida eliminada',
                            text: 'El stock fue restaurado',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    }
                })
                .catch((error) => {
                    console.error("❌ Error eliminando salida:", error.response ?? error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo eliminar la salida'
                    });
                });
        }
    });
};

const exportarExcelProyecto = (proyecto) => {
    window.location.href = `/proyecto/${proyecto}/exportar`;
};




/* ==========================
📌 FUNCIONES DE CONTROL DE VISTA
========================== */
const cambiarPestana = (tab) => pestañaActiva.value = tab;
const toggleOrdenInventario = () => ordenInventarioAsc.value = !ordenInventarioAsc.value;
const toggleOrdenSalidas = () => ordenSalidasAsc.value = !ordenSalidasAsc.value;

/* ==========================
📌 TOOLTIP DE PRODUCTO
========================== */
const cache = {};
let timeoutId = null;

const codigoActivo = ref(null);
const posicionTooltip = ref("abajo"); // puede ser "abajo" o "arriba"

const mostrarTooltip = (codigo, event) => {
  if (!codigo) return;

  clearTimeout(timeoutId);
  codigoActivo.value = codigo;

  // Detectar si la celda está cerca del final de la ventana
  const rect = event.target.getBoundingClientRect();
  const espacioAbajo = window.innerHeight - rect.bottom;
  posicionTooltip.value = espacioAbajo < 200 ? "arriba" : "abajo";

  timeoutId = setTimeout(async () => {
    if (cache[codigo]) {
      productoTooltip.value = cache[codigo];
      tooltipVisible.value = true;
      return;
    }

    try {
      const res = await axios.get(
        `/proyectos/${props.proyecto.id}/buscar-producto/${encodeURIComponent(codigo)}`
      );

      if (res.data.existe && res.data.producto) {
        cache[codigo] = res.data.producto;
        productoTooltip.value = res.data.producto;
        tooltipVisible.value = true;
      }
    } catch (err) {
      console.error("❌ Error cargando tooltip:", err);
      tooltipVisible.value = false;
      productoTooltip.value = null;
    }
  }, 300);
};

const ocultarTooltip = () => {
  clearTimeout(timeoutId);
  tooltipVisible.value = false;
  productoTooltip.value = null;
  codigoActivo.value = null;
};


/* ==========================
📌 REFRESCAR DATOS
========================== */
const refrescarInventario = () => router.reload({ only: ['inventarios'] });
const refrescarSalida = () => router.reload({ only: ['salidas'] });

/* ==========================
📌 DETALLE DE SALIDAS DE UN PRODUCTO
========================== */

const salidasProducto = ref([]);
let modalVisible = ref(false);
let currentCodigo = ref(null);
let currentProducto = ref({ nombre: null, codigo: null, descripcion: null, stock: null, solicitado_por: null });
let total = ref(null);


const formatFecha = (f) => {
    if (!f) return '—';
    try {
        // intenta formatear 'YYYY-MM-DD' o datetime
        const d = new Date(f);
        if (isNaN(d)) return f;
        return d.toLocaleDateString();
    } catch (e) {
        return f;
    }
};

const verSalidas = async (itemOrCodigo) => {
    let item = null;
    let codigo = null;

    if (!itemOrCodigo) return;
    if (typeof itemOrCodigo === 'object') {
        item = itemOrCodigo;
        codigo = item.codigo ?? item.producto_code ?? item.code ?? item.id ?? null;
    } else {
        codigo = itemOrCodigo;
    }

    if (!codigo) {
        alert('No se pudo determinar el código del producto.');
        return;
    }

    try {
        const url = `/proyectos/${props.proyecto.id}/salidas/producto/${encodeURIComponent(codigo)}`;
        const res = await axios.get(url, { headers: { Accept: 'application/json' } });

        // la respuesta puede ser array o { salidas: [...] }
        const datos = res.data.salidas ?? res.data ?? [];

        salidasProducto.value = Array.isArray(datos) ? datos : [];
        currentCodigo.value = codigo;

        // si recibimos info del producto en el item (cuando llamas con item)
        currentProducto.value = {
            nombre: item?.descripcion ?? item?.producto_label ?? item?.producto_name ?? null,
            codigo: codigo,
            descripcion: item?.descripcion ?? item?.desc ?? null,
            stock: item?.stock ?? (item?.inventario ?? null),
            solicitado_por: item?.solicitado_por ?? null
        };

        // si no teníamos nombre, intentamos extraerlo de la primera fila de salidas
        if (!currentProducto.value.nombre && salidasProducto.value.length > 0) {
            const first = salidasProducto.value[0];
            currentProducto.value.nombre = first.producto_label ?? first.producto ?? first.producto_name ?? first.solicitante ?? null;
        }

        // calcular total si hay columna cantidad
        const posibleCantidad = (r) => r.cantidad ?? r.qty ?? r.cant ?? r.cantidad_salida ?? null;
        if (salidasProducto.value.length > 0) {
            let suma = 0;
            let tiene = false;
            for (const row of salidasProducto.value) {
                const v = posibleCantidad(row);
                const n = Number(v);
                if (!isNaN(n)) { suma += n; tiene = true; }
            }
            total.value = tiene ? suma : null;
        } else {
            total.value = null;
        }

        modalVisible.value = true;
    } catch (err) {
        console.error('verSalidas error:', err);
        // intenta asignar modal vacío para inspección
        salidasProducto.value = [];
        currentCodigo.value = codigo;
        modalVisible.value = true;

        let mensaje = "Error al obtener salidas.";
        if (err.response) {
            mensaje += `\nCódigo: ${err.response.status} - ${err.response.statusText}`;
            if (err.response.data?.message) mensaje += `\nDetalle: ${err.response.data.message}`;
        } else if (err.request) {
            mensaje += "\nEl servidor no respondió.";
        } else {
            mensaje += `\n${err.message}`;
        }
        alert(mensaje);
    }
};

//pdf reportes de salidas
const salidasPdf_generateHTML = ({ producto = {}, salidas = [], proyectoNombre = '', companyName = '', logoUrl = '', fecha = null }) => {
    const escapeHtml = (s) => {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };
    const fmtDate = (d) => {
        if (!d) return '';
        const dt = (d instanceof Date) ? d : new Date(d);
        if (isNaN(dt)) return escapeHtml(d);
        return dt.toLocaleString();
    };

    const rowsHtml = salidas.map((r, i) => {
        const cantidad = r.cantidad ?? r.qty ?? r.cant ?? r.cantidad_salida ?? '';
        const cantidadFmt = formatearCantidad(cantidad) || '—';
        const fechaSalida = r.fecha ?? r.fecha_salida ?? r.created_at ?? '';
        const solicitante = r.solicitado_por ?? r.solicitante ?? r.persona ?? r.persona_nombre ?? '';
        const nActa = r.n_acta ?? r.nacta ?? r.acta ?? '';
        const lugar = r.lugar ?? r.site ?? '';
        const distrito = r.distrito ?? r.district ?? '';
        return `
    <tr>
      <td style="padding:6px 8px;text-align:right">${i + 1}</td>
      <td style="padding:6px 8px">${escapeHtml(String(nActa || '—'))}</td>
      <td style="padding:6px 8px">${escapeHtml(String(r.nombre ?? solicitante ?? '—'))}</td>
      <td style="padding:6px 8px">${escapeHtml(lugar || '—')}</td>
      <td style="padding:6px 8px">${escapeHtml(distrito || '—')}</td>
      <td style="padding:6px 8px">${escapeHtml(fmtDate(fechaSalida) || '—')}</td>
      <td style="padding:6px 8px;text-align:right">${escapeHtml(cantidadFmt)}</td>
    </tr>`;
    }).join('');


    const nombre = producto.nombre ?? producto.producto_label ?? producto.producto_name ?? '';
    const codigo = producto.codigo ?? producto.code ?? '';
    const descripcion = producto.descripcion ?? producto.desc ?? '';
    const stock = (producto.stock !== undefined && producto.stock !== null) ? producto.stock : '';
    const solicitadoPor = producto.solicitado_por ?? '';

    const fechaGeneracion = fecha ? fmtDate(fecha) : fmtDate(new Date());

    return `
  <div class="paper" style="font-family: Arial, Helvetica, sans-serif; box-sizing:border-box; padding:18px; color:#222;">
    <style>
      .header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
      .brand { display:flex; align-items:center; gap:12px; }
      .logo-wrap { width:86px; height:86px; display:flex; align-items:center; justify-content:center; border-radius:8px; overflow:hidden; background:#fff; border:1px solid #eee; }
      .logo-wrap img { max-width:100%; max-height:100%; display:block; }
      .company { font-size:16px; font-weight:700; }
      .meta { font-size:12px; color:#444; }
      .title { font-size:16px; font-weight:700; margin-bottom:6px; }
      .product-info { border:1px solid #e6e6e6; padding:10px; border-radius:6px; margin-bottom:12px; font-size:13px; background: #fafafa; }
      table { width:100%; border-collapse:collapse; font-size:12px; }
      th, td { border:1px solid #e6e6e6; padding:6px 8px; }
      th { background:#f4f4f4; text-align:left; font-weight:700; }
      .small { font-size:11px; color:#666; }
      .right { text-align:right; }
      @media print { .paper { padding: 12mm; } }
    </style>

    <div class="header">
      <div class="brand">
        <div class="logo-wrap">
            <img src="/images/logo.png" alt="Logo" />
        </div>
        <div>
          <div class="company">Islas De Paz Peru</div>
          <div class="meta">Proyecto: ${escapeHtml(proyectoNombre || '')}</div>
        </div>
      </div>
      <div class="small right">Generado: ${escapeHtml(fechaGeneracion)}</div>
    </div>

    <div class="title">Salidas del producto — ${escapeHtml(nombre || codigo)}</div>

    <div class="product-info">
      <div><strong>Producto:</strong> ${escapeHtml(nombre)}</div>
      <div><strong>Código:</strong> ${escapeHtml(codigo)}</div>
      <div><strong>Descripción:</strong> ${escapeHtml(descripcion)}</div>
      <div><strong>Stock Actual:</strong> ${escapeHtml(String(stock))}</div>
      <div><strong>Solicitado por:</strong> ${escapeHtml(solicitadoPor)}</div>
    </div>

    <div style="margin-top:6px; margin-bottom:6px; font-weight:600">Detalle de salidas</div>

    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th style="width:90px">N° Acta</th>
          <th style="min-width:140px">Nombre</th>
          <th style="width:120px">Lugar</th>
          <th style="width:120px">Distrito</th>
          <th style="width:140px">Fecha</th>
          <th style="width:90px">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        ${rowsHtml}
      </tbody>
    </table>

    <div style="margin-top:18px; font-size:12px; color:#333; display:flex; justify-content:space-between; align-items:center;">
      <div><strong>Total filas:</strong> ${salidas.length}</div>
    </div>
  </div>
  `;
};

// Descargar PDF (usa tu misma librería html2pdf.js)
const salidasPdf_downloadPdfFile = async ({ producto = {}, salidas = [], proyectoNombre = '', companyName = '', logoUrl = '', filename = null }) => {
    if (!producto || (!producto.codigo && !producto.nombre)) {
        Swal.fire('Falta información', 'El producto debe tener nombre o código para generar el PDF.', 'warning');
        return;
    }

    const nFile = filename || `${String(producto.nombre ?? producto.codigo ?? 'salidas')}.pdf`.replace(/[\\\/:*?"<>|]/g, '_');
    const html = salidasPdf_generateHTML({
        producto,
        salidas,
        proyectoNombre,
        companyName,
        logoUrl,
        fecha: new Date()
    });

    // convertir mm->px a 96dpi
    const mmToPx = (mm, dpi = 96) => Math.round(mm * (dpi / 25.4));
    const a4WidthPx = mmToPx(210, 96);
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

    // esperar recursos (imgs + fonts)
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
        await new Promise(r => setTimeout(r, 120));
    })(container);

    // cargar html2pdf si no existe
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
        const scale = 2;
        const opt = {
            margin: 8,
            filename: nFile,
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

        await new Promise((resolve, reject) => {
            try {
                window.html2pdf().set(opt).from(elementToPdf).toPdf().get('pdf').then(() => {
                    window.html2pdf().set(opt).from(elementToPdf).save(nFile, () => resolve());
                }).catch(err => reject(err));
            } catch (err) {
                reject(err);
            }
        });
    } catch (err) {
        console.error('Error generando PDF salidas:', err);
        Swal.fire('Error', 'No se pudo generar el PDF en el navegador. Abriendo vista imprimible...', 'error');
        try {
            const w = window.open('', '_blank', 'noopener,noreferrer');
            if (w) { w.document.open(); w.document.write(html); w.document.close(); w.focus(); setTimeout(() => { try { w.print(); } catch (e) { } }, 300); }
            else Swal.fire('Error', 'Permite popups e inténtalo nuevamente.', 'error');
        } catch (e) { console.error(e); }
    } finally {
        try { document.body.removeChild(container); } catch (e) { }
    }
};


const descargarSalidasPdf = () => {
    if (!currentProducto.value || !salidasProducto.value?.length) {
        Swal.fire('Sin datos', 'No hay salidas para exportar.', 'warning');
        return;
    }

    salidasPdf_downloadPdfFile({
        producto: currentProducto.value,
        salidas: salidasProducto.value,
        proyectoNombre: props.proyecto?.nombre || 'Proyecto sin nombre'
    });
};

// =====================
// 🔹 Refs / Estado local
// =====================
const filtroEstado = ref('todos');
const modalAceptarVisible = ref(false);
const actaCodigoInput = ref('');

// =====================
// 🔹 Helpers
// =====================
const normalizarCodigo = (str) =>
    (str || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();

// =====================
// 🔹 Acciones
// =====================

// Cambiar estado de UNA sola salida
const cambiarEstado = async (salida) => {
    const nuevoEstado = salida.estado === 'pendiente' ? 'aceptado' : 'pendiente';

    const confirm = await Swal.fire({
        title: '¿Cambiar estado?',
        text: `Se actualizará a "${nuevoEstado}" para todas las salidas con N° Acta "${salida.n_acta}".`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const payload = { estado: nuevoEstado };

        // Actualizamos SOLO la salida seleccionada en el backend
        const res = await axios.patch(
            `/proyectos/${props.proyecto.id}/salidas/${salida.id}`,
            payload
        );

        const estadoFinal = res.data?.estado ?? nuevoEstado;

        // 🔹 Reflejar en TODAS las salidas con el mismo n_acta en el frontend
        props.salidas.forEach(s => {
            if (s.n_acta === salida.n_acta) {
                s.estado = estadoFinal;
            }
        });

        Swal.fire({
            icon: 'success',
            title: 'Estado actualizado',
            text: `Todas las salidas con N° Acta "${salida.n_acta}" ahora están en "${estadoFinal}".`
        });
    } catch (err) {
        console.error('Error al actualizar salida', salida.id, err.response ?? err);

        if (err.response?.status === 422) {
            const data = err.response.data || {};
            const errors = data.errors || data;
            const msg = Object.values(errors).flat().join('\n') || JSON.stringify(errors);

            Swal.fire({
                icon: 'error',
                title: 'No se pudo actualizar (422)',
                text: msg
            });
        } else {
            let texto = 'No se pudo actualizar el estado.';
            if (err.response?.status === 403) texto = 'No tienes permiso.';
            else if (err.request && !err.response) texto = 'El servidor no respondió.';
            Swal.fire({ icon: 'error', title: 'Error', text: texto });
        }
    }
};

// ========================
/* CONTABILIDAD TEST */
// =======================
// Constante con la URL base hacia crear acta
const URL_CREAR_ACTA = (proyectoId) => `/proyectos/${proyectoId}/actas/create`

function abrirCrear(proyectoId) {
    window.location.href = URL_CREAR_ACTA(proyectoId)
}


/* ---------- parseNumero (robusto) ---------- */
const parseNumero = (val) => {
  if (val === null || val === undefined || val === '') return 0;
  if (typeof val === 'number') return val;
  let s = String(val).trim();
  // quitar símbolos no numéricos salvo , . y -
  s = s.replace(/[^\d\-,\.]/g, '');
  // casos: "1.234,56" (usualmente ES) -> "1234.56"
  if (s.indexOf('.') !== -1 && s.indexOf(',') !== -1 && s.lastIndexOf(',') > s.lastIndexOf('.')) {
    s = s.replace(/\./g, '').replace(',', '.');
  } else if (s.indexOf(',') !== -1 && s.indexOf('.') === -1) {
    s = s.replace(',', '.');
  } else {
    s = s.replace(/,/g, '');
  }
  const n = parseFloat(s);
  return isNaN(n) ? 0 : n;
};


/* ---------- parseFecha (normaliza siempre a medianoche LOCAL) ---------- */
const parseFecha = (fechaStr) => {
  if (!fechaStr && fechaStr !== 0) return null;
  try {
    const s = String(fechaStr).trim();

    // dd/mm/yyyy
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s)) {
      const [d, m, y] = s.split('/').map(Number);
      const dt = new Date(y, m - 1, d);
      dt.setHours(0, 0, 0, 0);
      return dt;
    }

    // yyyy-mm-dd
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
      const [y, m, d] = s.split('-').map(Number);
      const dt = new Date(y, m - 1, d);
      dt.setHours(0, 0, 0, 0);
      return dt;
    }

    // timestamp (segundos o ms) o ISO completo
    if (/^\d+$/.test(s)) {
      const n = Number(s);
      // segundos
      if (n < 10000000000) return new Date(n * 1000);
      return new Date(n);
    }

    const d0 = new Date(s);
    if (!isNaN(d0.getTime())) {
      // normalizar a medianoche local (evita offsets)
      const y = d0.getFullYear(), m = d0.getMonth(), day = d0.getDate();
      const d = new Date(y, m, day);
      d.setHours(0,0,0,0);
      return d;
    }
  } catch (e) {
    // ignore
  }
  return null;
};


/* ---------- util: mes/anio anterior ---------- */
function getPrevMesAnio(mes, anio) {
  mes = Number(mes);
  anio = Number(anio);
  if (mes === 1) return { mes: 12, anio: anio - 1 };
  return { mes: mes - 1, anio };
}

/* ---------- util: chequear fecha contra mes/anio dado ---------- */
const esDelMesCon = (fechaRaw, mes, anio) => {
  const f = parseFecha(fechaRaw);
  if (!f) return false;
  return (f.getMonth() + 1) === Number(mes) && f.getFullYear() === Number(anio);
};


/* ---------- util: último saldo del mes (intenta campo 'saldo', si no fallback a ingresos-egresos) ---------- */
const ultimoSaldoDelMes = (items = [], fechaCampo = 'fecha') => {
  if (!Array.isArray(items) || items.length === 0) return 0;

  const lista = items
    .map(i => ({ raw: i, fechaObj: parseFecha(i[fechaCampo] ?? i.fecha ?? i.created_at) }))
    .filter(x => x.fechaObj instanceof Date && !isNaN(x.fechaObj.getTime()))
    .sort((a, b) => a.fechaObj - b.fechaObj);

  if (!lista.length) return 0;

  // 1) si último registro tiene .saldo válido, devolverlo
  const posibleUltimo = lista[lista.length - 1].raw;
  const sRaw = parseNumero(posibleUltimo.saldo ?? posibleUltimo.Saldo ?? posibleUltimo.saldo_final);
  if (!isNaN(sRaw) && sRaw !== 0) return sRaw;

  // 2) recalcular por ingresos/egresos
  let running = 0;
  let any = false;
  for (const entry of lista) {
    const r = entry.raw;
    const ing = parseNumero(r.ingresos ?? r.ingreso ?? r.monto_ingreso ?? 0);
    const eg = parseNumero(r.egresos ?? r.egreso ?? r.monto_egreso ?? 0);
    if (ing !== 0 || eg !== 0) any = true;
    running = running + ing - eg;
  }
  if (any) return running;

  // fallback
  return 0;
};


/* ==========================
  Computeds filtrados por mes
========================== */

/* Filtrados actuales por mesActivo/anioActivo */

/* Totales del mes (ingresos / egresos / movimientos) - CAJA */
const    ingresosCajaTotal = computed(() =>
    (actasCajaFiltradas.value || []).reduce((s, a) => s + parseNumero(a.ingresos), 0)
);
const egresosCajaTotal = computed(() =>
    (actasCajaFiltradas.value || []).reduce((s, a) => s + parseNumero(a.egresos), 0)
);
const movimientosCaja = computed(() => ingresosCajaTotal.value - egresosCajaTotal.value);

/* Totales del mes - BANCO */
const ingresosBancoTotal = computed(() =>
    (actasBancoFiltradas.value || []).reduce((s, a) => s + parseNumero(a.ingresos), 0)
);
const egresosBancoTotal = computed(() =>
    (actasBancoFiltradas.value || []).reduce((s, a) => s + parseNumero(a.egresos), 0)
);
const movimientosBanco = computed(() => ingresosBancoTotal.value - egresosBancoTotal.value);

/* Totales del mes - EASY */
const ingresosEasyTotal = computed(() =>
    (itemsEasyFiltrados.value || []).reduce((s, i) => s + parseNumero(i.ingreso_moneda_local), 0)
);
const egresosEasyTotal = computed(() =>
    (itemsEasyFiltrados.value || []).reduce((s, i) => s + parseNumero(i.gasto_moneda_local), 0)
);

const egresosgestiónEasyTotal = computed(() =>
    (itemsEasyFiltrados.value || []).reduce((s, i) => s + parseNumero(i.debito_moneda_gestion), 0)
);
const movimientosEasy = computed(() => ingresosEasyTotal.value - egresosEasyTotal.value);
//nuevos


/* ==========================
  Apertura (último saldo del mes anterior) y Cierre (apertura + movimientos)
========================== */

const actasCajaDelMesAnterior = computed(() => {
  const prev = getPrevMesAnio(mesActivo.value, anioActivo.value);
  return (actasCaja?.value || []).filter(a => esDelMesCon(a.fecha, prev.mes, prev.anio));
});

const cierreCaja = computed(() => (aperturaCajaSnapshot.value ?? aperturaCaja.value ?? 0) + movimientosCaja.value);

/* BANCO: mes anterior */
const actasBancoDelMesAnterior = computed(() => {
  const prev = getPrevMesAnio(mesActivo.value, anioActivo.value);
  return (actasBanco?.value || []).filter(a => esDelMesCon(a.fecha, prev.mes, prev.anio));
});
const aperturaBanco = computed(() => ultimoSaldoDelMes(actasBancoDelMesAnterior.value, 'fecha'));
const cierreBanco = computed(() => (aperturaBancoSnapshot.value ?? aperturaBanco.value ?? 0) + movimientosBanco.value);

/* EASY: mes anterior (usando created_at si aplica) */
const itemsEasyDelMesAnterior = computed(() => {
    const prev = getPrevMesAnio(mesActivo.value, anioActivo.value);
    return (itemsEasy?.value || []).filter(i => {
        const fecha = i.created_at ?? i.fecha ?? i.fecha_movimiento ?? null;
        return esDelMesCon(fecha, prev.mes, prev.anio);
    });
});
const aperturaEasy = computed(() => ultimoSaldoDelMes(itemsEasyDelMesAnterior.value, 'created_at'));
const cierreEasy = computed(() => aperturaEasy.value + movimientosEasy.value);

/* ==========================
  FIN BLOQUE Contabilidad
========================== */
const exportarExcel = (mes, anio) => {
    const url = `/proyectos/${props.proyecto.id}/exportar-contabilidad-multiples?mes=${mes}&anio=${anio}`;
    window.location.href = url;
};

const ultimoSaldo = computed(() => {
    const actas = actasCajaFiltradas.value || [];

    // 1️⃣ Si no hay actas en el mes actual, usar la apertura (saldo del mes anterior)
    if (!actas || actas.length === 0) {
        return aperturaCaja?.value ?? 0;
    }

    // 2️⃣ Intentar calcular el último saldo del mes actual
    const saldoMes = ultimoSaldoDelMes(actas, 'fecha');
    if (!isNaN(saldoMes) && saldoMes !== null) {
        return saldoMes;
    }

    // 3️⃣ Si el resultado no es válido, intentar con todas las actas (por seguridad global)
    const todasActas = actasCaja?.value || [];
    const saldoGlobal = ultimoSaldoDelMes(todasActas, 'fecha');
    if (!isNaN(saldoGlobal)) {
        return saldoGlobal;
    }
    const ultimoSaldoForce = ref(0);

watch(
  () => actasCajaFiltradas.value,
  (nuevasActas) => {
    const saldo = ultimoSaldoDelMes(nuevasActas, 'fecha');
    ultimoSaldoForce.value = isNaN(saldo) ? 0 : saldo;
  },
  { deep: true }
);


    // 4️⃣ Último fallback: saldo apertura del mes anterior
    return aperturaCaja?.value ?? 0;
    
});


const ultimoSaldoBanco = computed(() => {
    const actas = actasBancoFiltradas.value || [];
    if (!actas || actas.length === 0) return aperturaBanco?.value ?? 0;
    return ultimoSaldoDelMes(actas, 'fecha');
    
});

const submitting = ref(false);
const monedaLocal = ref('PEN');
const monedaGestion = ref('EUR');

const form = reactive({
    Cuenta_general: '',
    gasto_moneda_local: 0,
    ingreso_moneda_local: 0,
    moneda_facturacion: monedaLocal.value,
    debito_moneda_gestion: 0,
    credito_moneda_gestion: 0,
    moneda_gestion: monedaGestion.value,
    numero_descripcion_pieza: '',
    codigo_presupuestario: '',
    naturaleza_presupuesto: '',
    contrato: '',
    bailleur_fondos: '',
});

function abrirCrearEasyConActa(acta = {}) {
    const sanitizeTextKeepAccents = (s, maxLen = 200) => {
        if (s == null) return '';
        let t = String(s).normalize('NFKC');
        t = t.replace(/[\r\n]+/g, ' ');
        t = t.replace(/\s+/g, ' ').trim();
        if (t.length > maxLen) t = t.slice(0, maxLen);
        return t;
    };

    const descripcionLimpia = sanitizeTextKeepAccents(acta.descripcion ?? '');

    const payload = {
        Cuenta_general: acta.cuenta_general ?? '',
        gasto_moneda_local: acta.egresos ?? 0,
        ingreso_moneda_local: acta.ingresos ?? 0,
        descripcion: descripcionLimpia,
        codigo_presupuestario: acta.presupuestario ?? '',
        actividad: acta.actividad ?? '',
        n_acta: acta.n_acta ?? ''
    };

    router.visit(`/proyectos/${props.proyecto.id}/easy/create`, {
        method: 'get',
        data: payload
    });
}



function eliminarActa(id) {
    if (!confirm('¿Seguro que quieres eliminar este registro?')) return;

    // ajusta el nombre de la ruta si es distinto
    router.delete(route('proyectos.easy.destroy', { proyecto: props.proyecto.id, id }));
}
//hahahahaha
// Estado visible en template
const modalVinculacionVisible = ref(false)
const currentActa = ref(null)
const vinculacionesActuales = ref([])

// Mapa local: key = am_row_id (string) => array de vinculaciones
const vinculacionMap = ref(new Map())

// Construir nombre de am_table igual que en backend
const buildAmTable = (tipo = 'caja') => {
    const base = String(props.proyecto?.nombre ?? '')
        .toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')
    return tipo === 'caja' ? `am_caja_proyecto_${base}` : `am_banco_proyecto_${base}`
}

// Retorna true si existe al menos 1 vinculación para la acta
const isVinculado = (acta, tipo = 'caja') => {
    if (!acta) return false
    const id = String(acta.id ?? acta.am_row_id ?? '')
    if (!id) return false

    // Si mantienes un solo map:
    const arr = vinculacionMap.value.get(id)
    return Array.isArray(arr) && arr.length > 0

    // Si tienes mapas separados (recomendado), usa:
    // const arr = (tipo === 'banco' ? vinculacionMapBanco.value : vinculacionMapCaja.value).get(id)
}

// Batch load: carga vinculaciones para todas las actas visibles
const loadVinculacionesBatch = async (tipo = 'caja') => {
    try {
        const actas = (tipo === 'caja') ? actasCajaFiltradas.value : actasBancoFiltradas.value
        const ids = (actas || []).map(a => a.id ?? a.am_row_id).filter(Boolean).map(String)
        if (!ids.length) {
            vinculacionMap.value = new Map()
            return
        }
        const amTable = buildAmTable(tipo)
        const url = `/proyectos/${props.proyecto.id}/vinculaciones/batch`
        const res = await axios.get(url, { params: { am_table: amTable, rows: ids }, headers: { Accept: 'application/json' } })
        const payload = res.data.vinculaciones ?? {}
        const map = new Map()
        for (const id of ids) map.set(String(id), payload[String(id)] ?? [])
        for (const k of Object.keys(payload)) map.set(String(k), payload[k] ?? [])
        vinculacionMap.value = map
    } catch (err) {
        console.error('Error loadVinculacionesBatch:', err)
        vinculacionMap.value = new Map()
    }
}

// Mostrar modal con vinculaciones (usa mapa si ya cargado)
const verVinculacion = async (acta, tipo = 'caja') => {
    if (!acta) return
    currentActa.value = acta
    const id = String(acta.id ?? acta.am_row_id ?? '')
    if (!id) { alert('No se identificó la acta'); return }

    // Si estás usando un único vinculacionMap, simplemente comprueba:
    if (vinculacionMap.value.has(id)) {
        vinculacionesActuales.value = vinculacionMap.value.get(id) ?? []
        modalVinculacionVisible.value = true
        return
    }

    // Si quieres evitar conflictos entre caja/banco, puedes mantener mapas separados.
    // Ejemplo para mapas separados (descomenta si los usas):
    // const mapRef = tipo === 'banco' ? vinculacionMapBanco.value : vinculacionMapCaja.value
    // if (mapRef.has(id)) { vinculacionesActuales.value = mapRef.get(id) ?? []; modalVinculacionVisible.value = true; return }

    try {
        const amTable = buildAmTable(tipo === 'banco' ? 'banco' : 'caja')
        const url = `/proyectos/${props.proyecto.id}/vinculaciones/one`
        const res = await axios.get(url, { params: { am_table: amTable, am_row_id: id }, headers: { Accept: 'application/json' } })
        const data = res.data.vinculaciones ?? []

        // Guardar en el map (si es único):
        vinculacionMap.value.set(id, data)

        // Si usas mapas separados:
        // if (tipo === 'banco') vinculacionMapBanco.value.set(id, data)
        // else vinculacionMapCaja.value.set(id, data)

        vinculacionesActuales.value = data
        modalVinculacionVisible.value = true
    } catch (err) {
        console.error('verVinculacion error:', err)
        vinculacionesActuales.value = []
        modalVinculacionVisible.value = true
        alert('Error al obtener datos de vinculación.')
    }
}

// montar y watchers
onMounted(() => loadVinculacionesBatch(tablaVisible.value === 'caja' ? 'caja' : 'banco'))

watch(tablaVisible, (nv) => {
    const tipo = nv === 'caja' ? 'caja' : 'banco'
    loadVinculacionesBatch(tipo)
})

watch(() => actasCajaFiltradas.value, () => {
    if (tablaVisible.value === 'caja') loadVinculacionesBatch('caja')
}, { deep: true })

watch(() => actasBancoFiltradas.value, () => {
    if (tablaVisible.value === 'banco') loadVinculacionesBatch('banco')
}, { deep: true })


const enviarActaAEasy = (acta, origen = 'banco') => {
    const sanitizeTextKeepAccents = (s, maxLen = 200) => {
        if (s == null) return '';
        let t = String(s).normalize('NFKC');
        t = t.replace(/[\r\n]+/g, ' ');
        t = t.replace(/\s+/g, ' ').trim();
        if (t.length > maxLen) t = t.slice(0, maxLen);
        return t;
    };

    const payload = {
        Cuenta_general: acta.cuenta_general ?? '',
        gasto_moneda_local: acta.egresos ?? 0,
        ingreso_moneda_local: acta.ingresos ?? 0,
        descripcion: sanitizeTextKeepAccents(acta.descripcion ?? ''), // 🚀 mandamos tal cual
        codigo_presupuestario: acta.presupuestario ?? '',
        actividad: acta.actividad ?? '',
        n_acta: acta.n_acta ?? '',
        origen: origen
    };

    router.visit(`/proyectos/${props.proyecto.id}/easy/create`, {
        method: 'get',
        data: payload
    });
};

const formatearCantidad = (valor) => {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Number(valor);
    if (Number.isNaN(n)) return String(valor);
    if (Number.isInteger(n)) return String(n);
    const f = n.toFixed(2);
    return f.replace(/\.?0+$/, '').replace(/\.(\d)0$/, '.$1');
};

const totalFormateado = ref('');

if (salidasProducto.value.length > 0) {
    let suma = 0;
    let tiene = false;
    for (const row of salidasProducto.value) {
        const v = posibleCantidad(row);
        const n = Number(v);
        if (!isNaN(n)) { suma += n; tiene = true; }
    }
    total.value = tiene ? suma : null;
    totalFormateado.value = tiene ? formatearCantidad(suma) : '';
} else {
    total.value = null;
    totalFormateado.value = '';
}

async function eliminarCaja(id) {
  const conf = await Swal.fire({
    title: 'Eliminar registro',
    text: '¿Seguro que deseas eliminar esta acta de Caja?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar'
  });
  if (!conf.isConfirmed) return;

  try {
    // 🔹 Usa props.proyecto.id (NO proyecto.id)
    await axios.delete(`/proyectos/${props.proyecto.id}/amcaja/${id}`);

    // 🔹 Refresca datos y recalcula totales
    if (typeof fetchDatos === 'function') await fetchDatos();
    if (typeof fetchMeta === 'function') {
      fetchMeta('c');
      fetchMeta('b');
    }

    Swal.fire('Eliminado', 'Registro de caja eliminado y saldos actualizados.', 'success');
  } catch (err) {
    console.error('Error al eliminar caja:', err);
    Swal.fire('Error', err.response?.data?.message || 'No se pudo eliminar.', 'error');
  }
}

async function eliminarBanco(id) {
    const conf = await Swal.fire({
        title: 'Eliminar registro',
        text: '¿Seguro que deseas eliminar esta acta de Banco?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar'
    });
    if (!conf.isConfirmed) return;

    try {
        const url = `/proyectos/${props.proyecto.id}/ambanco/${id}`;
        await axios.delete(url);

        
        Swal.fire('Eliminado', 'Registro de banco eliminado correctamente.', 'success');
    } catch (err) {
        console.error('❗ Error al eliminarBanco:', err);
        Swal.fire('Error', err.response?.data?.message || 'No se pudo eliminar.', 'error');
    }
}



async function recalcularTablaFromUI(tabla) {
    const conf = await Swal.fire({
        title: 'Recalcular saldos',
        text: `¿Deseas recalcular todos los saldos de la tabla "${tabla}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar'
    });
    if (!conf.isConfirmed) return;

    try {
        Swal.fire({ title: 'Recalculando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const url = `/proyectos/${props.proyecto.id}/am/recalcular`;
        const res = await axios.post(url, { tabla });

        Swal.close();

        if (res.data?.ok) {
            await Swal.fire('Listo', res.data.message || 'Recalculado correctamente.', 'success');

            // actualizar la UI: si tienes fetchDatos() lo usamos; si no, recargamos props necesarios
            if (typeof fetchDatos === 'function') {
                await fetchDatos();
                if (typeof fetchMeta === 'function') { fetchMeta('c'); fetchMeta('b'); }
            } else {
                router.reload({ only: ['caja', 'banco', 'salidas', 'inventarios'] });
            }
        } else {
            Swal.fire('Error', res.data?.message || 'No se pudo recalcular', 'error');
        }
    } catch (err) {
        Swal.close();
        console.error('Error recalcularTabla:', err);
        const msg = err.response?.data?.message || err.message || 'Error al llamar al servidor';
        Swal.fire('Error', msg, 'error');
    }
}

// Helpers para botones (usa buildAmTable que ya definiste)
function onRecalcularCaja() {
    const tabla = buildAmTable('caja'); // produce am_caja_proyecto_xxx
    recalcularTablaFromUI(tabla);
}

function onRecalcularBanco() {
    const tabla = buildAmTable('banco'); // produce am_banco_proyecto_xxx
    recalcularTablaFromUI(tabla);
}

// 🔹 FORZAR RECALCULO: usar ref en lugar de computed para poder actualizar manualmente
const aperturaCajaSnapshot = ref(0);
const aperturaBancoSnapshot = ref(0);

const aperturaCaja = computed(() => {
    const prev = getPrevMesAnio(mesActivo.value, anioActivo.value);
    const actasPrevias = (actasCaja?.value || []).filter(a =>
        esDelMesCon(a.fecha, prev.mes, prev.anio)
    );


    // Si hay actas en el mes anterior, usamos el saldo final de ese mes
    if (actasPrevias.length > 0) {
        const saldo = ultimoSaldoDelMes(actasPrevias, 'fecha');
        aperturaCajaSnapshot.value = saldo;
        return saldo;
    }


    // Si NO hay actas en el mes anterior, buscar la última acta global ANTES del mes actual
    const todas = actasCaja?.value || [];
    const fechaLimite = new Date(anioActivo.value, mesActivo.value - 1, 1);


    const anteriores = todas
        .map(a => {
            const f = parseFecha(a.fecha);
            return { raw: a, fechaObj: f };
        })
        .filter(x => {
            const valido = x.fechaObj && x.fechaObj < fechaLimite;
            return valido;
        })
        .sort((a, b) => a.fechaObj - b.fechaObj);

    if (anteriores.length > 0) {
        console.log('Últimas 3 actas:', anteriores.slice(-3).map(x => ({
            fecha: x.fechaObj.toLocaleDateString(),
            nActa: x.raw.n_acta,
            saldo: x.raw.saldo
        })));
    }

    if (anteriores.length > 0) {
        const ultimo = anteriores[anteriores.length - 1].raw;
        const s = parseNumero(ultimo.saldo);
        if (!isNaN(s)) {
            aperturaCajaSnapshot.value = s;
            return s;
        }
    }

    // fallback final
    aperturaCajaSnapshot.value = 0;
    return 0;
});

const saldoFinal = computed(() => {
    return ultimoSaldo.value;
});

const saldoFinalBanco = computed(() => {
    return ultimoSaldoBanco.value;
});

const enviarAInventario = () => {
  if (!itemsEasyFiltrados.value?.length) {
    Swal.fire({
      icon: 'info',
      title: 'No hay datos',
      text: 'No hay registros para enviar al Inventario.',
    });
    return;
  }

  // aquí puedes elegir qué registro enviar
  // en este ejemplo envío el PRIMERO
  const item = itemsEasyFiltrados.value[0]; // o el que selecciones manualmente

  // ejemplo: prepara los datos que necesita Inventario
  const params = new URLSearchParams({
    descripcion: item.numero_descripcion_pieza || '',
    categoria: item.categoria || '',
    unidad_medida: item.unidad_medida || '',
    cantidad: item.cantidad || 1,
    precio: item.precio_unitario || 0,
    easy_id: item.id,
    proyecto_id: props.proyecto.id,
    origen: 'EASY'
  });

  const url = `/proyectos/${props.proyecto.id}/inventarios/create?${params.toString()}`;

  Swal.fire({
    title: '¿Enviar a Inventario?',
    text: `Se enviará el registro EASY "${item.numero_descripcion_pieza}" al Inventario.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, enviar',
    cancelButtonText: 'Cancelar',
  }).then((r) => {
    if (r.isConfirmed) {
      window.location.href = url;
    }
  });
};



</script>x|


<template>

    <Head :title="`Proyecto: ${proyecto.nombre}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center mb-2">
                <!-- Título -->
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Proyecto: {{ proyecto.nombre }}
                </h2>

                <!-- Botones alineados a la derecha -->

                <div class="flex space-x-4">
                    <button @click="cambiarPestana('Precios')" :class="[
                        'px-4 py-2 rounded transition font-medium',
                        pestañaActiva === 'Precios'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                    ]">
                        Precios
                    </button>
                    <button v-if="user.role === 'admin'" @click="cambiarPestana('Contabilidad')" :class="[
                        'px-4 py-2 rounded transition font-medium',
                        pestañaActiva === 'Contabilidad'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                    ]">
                        Contabilidad
                    </button>
                    <button @click="cambiarPestana('inventario')" :class="[
                        'px-4 py-2 rounded transition font-medium',
                        pestañaActiva === 'inventario'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                    ]">
                        Inventario
                    </button>
                    <button @click="cambiarPestana('salidas')" :class="[
                        'px-4 py-2 rounded transition font-medium',
                        pestañaActiva === 'salidas'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                    ]">
                        Salidas
                    </button>
                </div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Estado: {{ proyecto.estado }} — Inicio: {{ proyecto.fecha_inicio }} — Fin: {{ proyecto.fecha_fin ?? 'Pendiente' }}
            </p>
        </template>




        <div class="py-12 space-y-10">

            <!-- Tabla Inventarios -->
            <div v-if="pestañaActiva === 'inventario'" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">Inventarios</h3>

                <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <!-- Buscar -->
                        <div class="w-64">
                            <label
                                class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Buscar</label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-300 pointer-events-none">🔍</span>
                                <input v-model="filtroInventario" type="text" placeholder="Código o producto..." class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm
                            focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400
                            dark:bg-gray-700 dark:text-white" />
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                                Categoría
                            </label>
                            <select v-model="filtroCategoria" class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500
                            dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <!-- Opción general -->
                                <option value="todos">Todas</option>

                                <!-- Opciones dinámicas -->
                                <option v-for="categoria in categorias" :key="categoria" :value="categoria">
                                    {{ categoria }}
                                </option>
                            </select>
                        </div>

                        <!-- Solicitado por -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Solicitado
                                por</label>
                            <select v-model="filtroSolicitadoPor" class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500
                            dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="todos">Todos</option>
                                <option v-for="solicitante in solicitantesUnicos" :key="solicitante"
                                    :value="solicitante">
                                    {{ solicitante }}
                                </option>
                            </select>
                        </div>

                        <!-- Checkbox stock -->
                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" v-model="filtroStock" class="w-4 h-4 accent-indigo-600" />
                            Solo con stock
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="toggleOrdenInventario"
                            class="px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-300">
                            📅 Ordenar: <span class="font-semibold">{{ ordenInventarioAsc ? 'Antiguos' : 'Recientes' }}</span>
                        </button>
                        <button @click="refrescarInventario"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors duration-200">
                            🔄 Refrescar
                        </button>
                        <button v-if="user.role === 'admin'" @click="agregarInventario"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                            ➕ Agregar
                        </button>
                        <button v-if="user.role === 'admin'" @click="exportarExcelProyecto(proyecto.nombre)"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                            📊 Exportar
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[600px]">
                    <table class="min-w-full text-sm text-left border dark:border-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                            <tr>
                                <th class="p-3"></th>
                                <th class="p-3">Código</th>
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Producto/Bien</th>
                                <th class="p-3">Categoria</th>
                                <th class="p-3">U.M.</th>
                                <th class="p-3">Entradas</th>
                                <th class="p-3">Salidas</th>
                                <th class="p-3">Stock</th>
                                <th class="p-3">Precio</th>
                                <th class="p-3">Solicitado por</th>
                                <th class="p-3" v-if="user.role === 'admin'">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in inventarioFiltrado" :key="item.id"
                                class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <td class="p-3">
                                    <div @click="verSalidas(item)" title="Ver salidas"
                                        class="w-4 h-4 rounded-full cursor-pointer"
                                        :class="item.stock === 0 ? 'bg-green-500' : 'bg-red-500'"></div>
                                </td>
                                <td class="p-3">{{ item.codigo }}</td>
                                <td class="p-3">{{ item.fecha }}</td>
                                <td class="p-3">{{ item.descripcion }}</td>
                                <td class="p-3">{{ item.categoria }}</td>
                                <td class="p-3">{{ item.unidad_medida }}</td>
                                <td class="p-3">{{ item.entradas }}</td>
                                <td class="p-3">{{ item.salidas }}</td>
                                <td class="p-3">{{ item.stock }}</td>
                                <td class="p-3">S/ {{ Number(item.precio ?? 0).toFixed(2) }}</td>
                                <td class="p-3">{{ item.solicitado_por }}</td>
                                <td class="p-3 flex gap-2">
                                    <!-- 🔹 Solo admin -->
                                    <template v-if="user.role === 'admin'">
                                        <a :href="`/proyectos/${proyecto.id}/inventarios/${item.id}/edit`"
                                            title="Editar"
                                            class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg hover:bg-blue-600 hover:scale-110 transition">
                                            ✏️
                                        </a>
                                        <button @click="eliminarRegistro(item.id)" title="Eliminar"
                                            class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg hover:bg-red-600 hover:scale-110 transition">
                                            🗑️
                                        </button>
                                    </template>


                                    <!-- 🔹 Comentario (si existe) visible para todos -->
                                    <button v-if="item.comentario && (user.role === 'admin' || user.role === 'equipo')"
                                        @click="toggleComentario(item.id)" title="Ver comentario"
                                        class="flex items-center justify-center w-9 h-9 bg-purple-500 text-white rounded-lg hover:bg-purple-600 hover:scale-110 transition">
                                        💬
                                    </button>

                                </td>
                                <div v-if="comentarioActivo === item.id"
                                    class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-md text-sm text-gray-700 dark:text-gray-200">
                                    <p class="whitespace-pre-line">{{ item.comentario }}</p>
                                </div>
                            </tr>

                        </tbody>
                    </table>

                    <!-- Modal Salidas -->
                    <div v-if="modalVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-11/12 md:w-2/3 shadow-lg">

                            <div class="flex items-start justify-between mb-4">
                                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Salidas del producto
                                </h2>
                                <div class="flex items-center gap-2">
                                    <button v-if="['equipo', 'admin'].includes(user.role)"
                                        class="px-3 py-1 rounded-md bg-green-500 text-white hover:bg-green-600 transition"
                                        @click="descargarSalidasPdf">
                                        📄 Exportar Salidas en PDF
                                    </button>

                                    <button @click="() => { modalVisible = false }"
                                        class="px-3 py-1 rounded-md bg-gray-500 text-white hover:bg-gray-600 transition"
                                        title="Cerrar">Cerrar</button>
                                </div>
                            </div>

                            <!-- Info producto (encima de la tabla) -->
                            <div class="mb-4 text-sm text-gray-700 dark:text-gray-200">
                                <div><strong>Producto:</strong> {{ currentProducto.nombre ?? (salidasProducto[0]?.producto_label ?? salidasProducto[0]?.producto ?? '—') }}</div>
                                <div><strong>Código:</strong> {{ currentProducto.codigo ?? currentCodigo ?? (salidasProducto[0]?.producto_code ?? salidasProducto[0]?.codigo ?? '—') }}
                                </div>
                                <div v-if="currentProducto.stock !== null"><strong>Stock:</strong>
                                    {{ currentProducto.stock }}
                                </div>
                                <div>
                                    <strong>Solucitado:</strong>{{ currentProducto.solicitado_por }}
                                </div>
                            </div>

                            <div class="overflow-x-auto max-h-[60vh]">
                                <table class="min-w-full text-sm border dark:text-white">
                                    <thead class="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th class="p-2 text-left">N° Acta</th>
                                            <th class="p-2 text-left">Nombre</th>
                                            <th class="p-2 text-left">Lugar</th>
                                            <th class="p-2 text-left">Distrito</th>
                                            <th class="p-2 text-left">Fecha</th>
                                            <th class="p-2 text-left">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!salidasProducto || salidasProducto.length === 0">
                                            <td colspan="6" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                No hay salidas para este producto
                                            </td>
                                        </tr>
                                        <tr v-for="s in salidasProducto" :key="s.id"
                                            class="border-t dark:border-gray-700">
                                            <td class="p-2">{{ s.n_acta ?? s.nacta ?? '—' }}</td>
                                            <td class="p-2">{{ s.nombre ?? s.persona ?? s.persona_nombre ?? '—' }}</td>
                                            <td class="p-2">{{ s.lugar ?? s.site ?? '—' }}</td>
                                            <td class="p-2">{{ s.distrito ?? s.district ?? '—' }}</td>
                                            <td class="p-2">{{ formatFecha(s.fecha) }}</td>
                                            <td class="p-2">{{ formatearCantidad(s.cantidad ?? s.qty ?? s.cant) || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- footer (si quieres) -->
                            <div class="mt-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                <span v-if="total !== null"><strong>Total:</strong> {{ total }}</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Tabla Salidas -->
            <div v-if="pestañaActiva === 'salidas'" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">Gestión de Salidas</h3>

                <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="relative w-64">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Buscar</label>
                            <span
                                class="absolute bottom-2.5 left-3 text-gray-500 dark:text-gray-300 pointer-events-none">🔍</span>
                            <input v-model="filtroSalidas" type="text" aria-label="Buscar salidas por categorías"
                                placeholder="Buscar salidas..."
                                class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-700 dark:text-white" />
                        </div>

                        <div class="flex flex-col" v-if="pestañaActiva === 'sss'">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Categoría</label>
                            <select v-model="filtroCategoriaSalidas"
                                class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="todos">Todas</option>
                                <option value="Herramientas">Herramientas</option>
                                <option value="Materiales">Materiales</option>
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Estado</label>
                            <select v-model="filtroEstado" class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500
                            dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="todos">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="aceptado">Aceptado</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Encargado</label>
                            <select v-model="filtroEncargado"
                                class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option v-for="encargado in encargadosUnicos" :key="encargado"
                                    :value="encargado.toLowerCase()">
                                    {{ encargado }}
                                </option>
                            </select>
                        </div>
                        <div v-if="filtroEncargado !== 'todos' && conteoPorEstado"
                            class="flex flex-col mt-4 p-3 border rounded-lg bg-gray-50 dark:bg-gray-800">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                Conteo por estado de {{ filtroEncargado }}
                            </h3>
                            <div class="flex gap-4 text-sm">
                                <span class="text-blue-600 dark:text-blue-400">Pendientes: {{ conteoPorEstado.pendiente}}</span>
                                <span class="text-green-600 dark:text-green-400">Aceptados: {{ conteoPorEstado.aceptado}}</span>
                            </div>
                        </div>

                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="toggleOrdenSalidas" aria-label="Ordenar Salidas"
                            class="px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-300">
                            📅 Ordenar:
                            <span class="font-semibold">
                                {{ ordenSalidasAsc ? 'Antiguos' : 'Recientes' }}
                            </span>
                        </button>

                        <button @click="refrescarSalida" aria-label="Refrescar tabla de Salidas"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors duration-200">
                            🔄 Refrescar
                        </button>

                        <button v-if="user.role === 'admin' || user.role === 'equipo'" @click="agregarSalida"
                            aria-label="Agregar Salida"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                            ➕ Agregar
                        </button>

                        <button v-if="user.role === 'admin'" @click="exportarExcelProyecto(proyecto.nombre)"
                            aria-label="Exportar datos a Excel"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                            📊 Exportar
                        </button>
                    </div>
                </div>

                <!-- Tabla (igual a la tuya, la mantengo) -->
                <div class="overflow-x-auto max-h-[600px]">
                    <table class="min-w-full text-sm text-left border dark:border-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                            <tr>
                                <th class="p-3">Estado</th>
                                <th class="p-3">Generado por</th>
                                <th class="p-3">N° Acta</th>
                                <th class="p-3">Nombre</th>
                                <th class="p-3">Lugar</th>
                                <th class="p-3">Distrito</th>
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Código del Producto</th>
                                <th class="p-3">Unidad de medida</th>
                                <th class="p-3">Cantidad</th>
                                <th v-if="user.role === 'admin' || user.role === 'equipo'" class="p-3">Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="salida in salidasFiltradas" :key="salida.id"
                                class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">

                                <!-- Columna estado -->
                                <td class="p-3">
                                    <button @click="user.role === 'admin' && cambiarEstado(salida)"
                                        :disabled="user.role !== 'admin'" :class="[
                                            'px-3 py-1 rounded-lg font-semibold text-white text-xs shadow transition',
                                            salida.estado === 'pendiente'
                                                ? 'bg-red-500 hover:bg-red-600'
                                                : 'bg-green-500 hover:bg-green-600',
                                            user.role !== 'admin' ? 'opacity-50 cursor-not-allowed' : ''
                                        ]">
                                        {{ salida.estado }}
                                    </button>
                                </td>

                                <td class="p-3">{{ salida.nombre_encargado }}</td>
                                <td class="p-3">{{ salida.n_acta }}</td>
                                <td class="p-3">{{ salida.nombre }}</td>
                                <td class="p-3">{{ salida.lugar }}</td>
                                <td class="p-3">{{ salida.distrito }}</td>
                                <td class="p-3">{{ salida.fecha }}</td>
                                <!-- Celda del producto con tooltip -->
                                <td class="p-3 relative cursor-pointer"
                                    @mouseenter="mostrarTooltip(salida.producto_code, $event)"
                                    @mouseleave="ocultarTooltip">

                                    {{ salida.producto_code }}

                                <!-- Tooltip -->
                                    <transition name="fade">
                                        <div v-if="tooltipVisible && codigoActivo === salida.producto_code"
                                            :class="[
                                                'absolute left-1/2 -translate-x-1/2 z-50 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-50 shadow-xl w-64',
                                                posicionTooltip === 'abajo' ? 'top-full mt-2' : 'bottom-full mb-2'
                                            ]">

                                        <p><strong>Descripción:</strong> {{ productoTooltip.descripcion }}</p>
                                        <p><strong>Categoría:</strong> {{ productoTooltip.categoria }}</p>
                                        <p><strong>Stock:</strong> {{ productoTooltip.stock }}</p>
                                        <p><strong>U.M.:</strong> {{ productoTooltip.unidad_medida }}</p>
                                        </div>
                                    </transition>
                                </td>

                                <td class="p-3"> {{ salida.um }}</td>
                                <td class="p-3">{{ formatearCantidad(salida.cantidad) }}</td>
                                <td v-if="user.role === 'admin' || user.role === 'equipo'"
                                    class="p-3 flex gap-2 items-center">
                                    <a :href="`/proyectos/${proyecto.id}/salidas/${salida.id}/edit`" title="Editar"
                                        class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg hover:bg-blue-600 hover:scale-110 transition">
                                        ✏️
                                    </a>
                                    <button @click="eliminarSalida(salida.id)" title="Eliminar"
                                        class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg hover:bg-red-600 hover:scale-110 transition">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Resumen de Inventario — Precios (mejorado) -->
            <section v-if="pestañaActiva === 'Precios'"
                class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg mt-6 border border-gray-200 dark:border-gray-700 transition">
                <header class="flex items-start justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <!-- Icono SVG profesional -->
                        <svg class="w-8 h-8 text-indigo-500 dark:text-indigo-300" viewBox="0 0 24 24" fill="none"
                            aria-hidden>
                            <path d="M3 13h4v8H3zM10 8h4v13h-4zM17 3h4v18h-4z" fill="currentColor" opacity="0.9" />
                        </svg>
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">Resumen de Inventario —
                                Precios
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Filtros rápidos, estadísticas y
                                lista
                                de
                                productos por valor.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="refrescarInventario"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <path d="M21 12a9 9 0 1 0-1.46 4.9" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Refrescar
                        </button>
                    </div>
                </header>

                <!-- filtros -->
                <form @submit.prevent class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-6">
                    <div>
                        <label for="buscar"
                            class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Buscar
                            (código /
                            producto)</label>
                        <input id="buscar" v-model="filtroPrecioBuscar" type="search" placeholder="Buscar..."
                            class="w-full p-2 border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-700" />
                    </div>

                    <div class="flex gap-2 items-end">
                        <div>
                            <label for="precio-min"
                                class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Precio
                                min</label>
                            <input id="precio-min" v-model.number="filtroPrecioMin" type="number" min="0" step="0.01"
                                class="p-2 border rounded-lg w-36 dark:bg-gray-700 dark:text-white" />
                        </div>
                        <div>
                            <label for="precio-max"
                                class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Precio
                                max</label>
                            <input id="precio-max" v-model.number="filtroPrecioMax" type="number" min="0" step="0.01"
                                class="p-2 border rounded-lg w-36 dark:bg-gray-700 dark:text-white" />
                        </div>
                    </div>

                    <div class="flex justify-end md:justify-start">
                        <button @click="limpiarFiltrosPrecio"
                            class="px-3 py-2 border rounded-md text-sm text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800">
                            Limpiar filtros
                        </button>
                    </div>
                </form>

                <!-- filtros extra y estadísticas por solicitante -->
                <div class="mb-4 flex flex-col md:flex-row items-start md:items-center gap-4">
                    <div class="min-w-[220px]">
                        <label for="solicitante"
                            class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Filtrar
                            por
                            solicitante</label>
                        <select id="solicitante" v-model="filtroSolicitantePrecio"
                            class="w-full p-2 border rounded-lg dark:bg-gray-700 dark:text-white">
                            <option value="todos">Todos</option>
                            <option v-for="s in solicitantesUnicos" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div v-if="filtroSolicitantePrecio !== 'todos'" class="flex gap-3 ml-0 md:ml-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg shadow-sm min-w-[110px]">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Productos</p>
                            <p class="font-bold text-gray-800 dark:text-white text-lg">{{ preciosSolicitanteStats.count }}
                            </p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg shadow-sm min-w-[140px]">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Valor total</p>
                            <p class="font-bold text-gray-800 dark:text-white text-lg">S/
                                {{ Number(preciosSolicitanteStats.totalValue).toFixed(2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- tarjetas resumen -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl shadow-sm">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Valor Total</p>
                                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-300">S/
                                    {{ Number(totalInventario).toFixed(2) }}</p>
                            </div>
                            <button @click="mostrarDetalleTotal = !mostrarDetalleTotal" aria-pressed="false"
                                class="text-xs px-2 py-1 bg-indigo-200 dark:bg-indigo-700 rounded">
                                {{ mostrarDetalleTotal ? 'Ocultar' : 'Ver detalle' }}
                            </button>
                        </div>

                        <ul v-if="mostrarDetalleTotal" class="mt-3 text-sm max-h-40 overflow-y-auto pr-2">
                            <li v-for="item in props.inventarios" :key="item.id" class="flex justify-between py-1">
                                <span class="truncate max-w-[70%]">{{ item.descripcion }} ({{ item.stock }} × S/
                                    {{ Number(item.precio ?? 0).toFixed(2) }})</span>
                                <span class="font-semibold">S/ {{ (Number(item.stock ?? 0) * Number(item.precio ?? 0)).toFixed(2) }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="p-4 bg-green-50 dark:bg-green-900/30 rounded-xl shadow-sm">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Entradas</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-300">+ S/
                            {{ Number(totalEntradas).toFixed(2) }}
                        </p>
                    </div>

                    <div class="p-4 bg-red-50 dark:bg-red-900/30 rounded-xl shadow-sm">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Salidas</p>
                        <p class="text-xl font-bold text-red-600 dark:text-red-300">- S/
                            {{ Number(totalSalidas).toFixed(2) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- lateral: Top N -->
                    <aside class="lg:col-span-1 p-4 border rounded-lg dark:border-gray-700">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">Top {{ topN }} (por valor)</h4>
                            <select v-model.number="topN" class="p-1 border rounded dark:bg-gray-700 dark:text-white">
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="20">20</option>
                            </select>
                        </div>

                        <ol
                            class="list-decimal ml-5 space-y-2 text-sm text-gray-700 dark:text-white max-h-72 overflow-y-auto">
                            <li v-for="it in topItems" :key="it.id" class="flex justify-between items-center">
                                <div class="truncate max-w-[60%]">{{ it.descripcion }}</div>
                                <div class="text-sm font-semibold">S/ {{ (Number(it.stock ?? 0) * Number(it.precio ?? 0)).toFixed(2) }}</div>
                            </li>
                        </ol>
                    </aside>

                    <!-- tabla principal -->
                    <div class="lg:col-span-2 p-4 border rounded-lg dark:border-gray-700 overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="bg-gray-100 dark:bg-gray-700 dark:text-white">
                                <tr>
                                    <th class="p-2">Código</th>
                                    <th class="p-2">Producto</th>
                                    <th class="p-2">Categoría</th>
                                    <th class="p-2">Stock</th>
                                    <th class="p-2">Precio</th>
                                    <th class="p-2">Valor total</th>
                                    <th class="p-2">Solicitado por</th>
                                </tr>
                            </thead>
                            <!-- --- dentro de la tabla (reemplaza la sección <tbody> por esta) --- -->
                            <tbody>
                                <tr v-for="item in preciosFiltrados" :key="item.id"
                                    class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-white">
                                    <td class="p-2">{{ item.codigo }}</td>
                                    <td class="p-2">{{ item.descripcion }}</td>
                                    <td class="p-2">{{ item.categoria }}</td>
                                    <td class="p-2 text-right">{{ item.stock }}</td>
                                    <td class="p-2 text-right">S/ {{ Number(item.precio ?? 0).toFixed(2) }}</td>
                                    <td class="p-2 font-semibold text-right">S/ {{ (Number(item.stock ?? 0) * Number(item.precio ?? 0)).toFixed(2) }}</td>
                                    <td class="p-2">{{ item.solicitado_por ?? '-' }}</td>
                                </tr>

                                <tr v-if="preciosFiltrados.length === 0">
                                    <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-400">No hay
                                        ítems
                                        que
                                        coincidan.</td>
                                </tr>
                            </tbody>

                            <!-- --- MODAL: Salidas del producto --- -->
                            <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                <!-- backdrop -->
                                <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="modalVisible = false"
                                    aria-hidden></div>

                                <!-- modal panel -->
                                <div role="dialog" aria-modal="true"
                                    class="relative z-10 w-full max-w-3xl bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">
                                    <!-- header -->
                                    <header class="flex items-start justify-between p-4 border-b dark:border-gray-700">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                Salidas — {{ currentProducto.nombre ?? currentCodigo ?? 'Producto' }}
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Código: <span class="font-medium text-gray-700 dark:text-gray-200">{{ currentProducto.codigo ?? currentCodigo }}</span>
                                                <span v-if="currentProducto.stock !== null"> • Stock:
                                                    <strong>{{ currentProducto.stock }}</strong></span>
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button @click="modalVisible = false"
                                                class="text-sm px-3 py-1 border rounded-md text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800">
                                                Cerrar
                                            </button>
                                            <!-- ejemplo de exportar (implementa su método si quieres) -->
                                            <button @click="() => { /* exportarLogica(currentCodigo) */ }"
                                                class="text-sm px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                                Exportar
                                            </button>
                                        </div>
                                    </header>

                                    <!-- cuerpo: lista de salidas -->
                                    <div class="p-4 max-h-[60vh] overflow-y-auto">
                                        <template v-if="salidasProducto && salidasProducto.length > 0">
                                            <div class="mb-3 flex items-center justify-between gap-4">
                                                <div class="text-sm text-gray-600 dark:text-gray-300">Se encontraron
                                                    <strong>{{ salidasProducto.length }}</strong> registro(s).
                                                </div>
                                                <div class="text-sm text-gray-600 dark:text-gray-300">Total (cantidad):
                                                    <strong>{{ total ?? '-' }}</strong>
                                                </div>
                                            </div>

                                            <table class="w-full text-sm text-left">
                                                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0 z-10">
                                                    <tr>
                                                        <!-- columnas más comunes: ajusta según tu API -->
                                                        <th class="p-2">Fecha</th>
                                                        <th class="p-2">Nombre</th>
                                                        <th class="p-2">Cantidad</th>
                                                        <th class="p-2">Unidad de medida</th>
                                                        <th class="p-2">Cantidad</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(s, idx) in salidasProducto" :key="s.id ?? idx"
                                                        class="border-t dark:border-gray-700">
                                                        <td class="p-2">
                                                            <!-- intentamos formatear fecha si existe -->
                                                            <span>{{ formatFecha ? formatFecha(s.fecha ?? s.created_at ?? s.date) : (s.fecha ?? s.created_at ?? '-') }}</span>
                                                        </td>
                                                        <td class="p-2">{{ s.nombre ?? s.tipo ?? '-' }}</td>
                                                        <td class="p-2 font-medium">{{ s.cantidad ?? s.qty ?? s.cant ?? s.cantidad_salida ?? '-' }}</td>
                                                        <td class="p-2">{{ s.um ?? s.solicitado_por ?? s.usuario ?? '-' }}</td>
                                                        <td class="p-2">{{ s.cantidad ?? s.descripcion ?? '-' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </template>

                                        <template v-else>
                                            <div class="py-8 text-center text-gray-600 dark:text-gray-400">
                                                No se encontraron registros de salidas para este producto.
                                            </div>
                                        </template>
                                    </div>

                                    <!-- footer: acciones rápidas -->
                                    <footer class="p-4 border-t dark:border-gray-700 flex items-center justify-between">
                                        <div class="text-sm text-gray-600 dark:text-gray-300">
                                            <strong>Total cantidad:</strong> {{ total ?? '—' }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="modalVisible = false"
                                                class="px-3 py-2 border rounded-md text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100">Cerrar</button>
                                            <button @click="() => { /* imprimirSalidas(currentCodigo) */ }"
                                                class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Imprimir</button>
                                        </div>
                                    </footer>
                                </div>
                            </div>

                        </table>
                    </div>
                </div>
            </section>

            <div v-if="pestañaActiva === 'Contabilidad'"
                class="p-5 rounded-2xl shadow-md mt-6 border bg-white dark:bg-gray-800">
                <div class="p-5 rounded-2xl shadow-md mt-6 border bg-white dark:bg-gray-800">
                    <!-- Header con pestañas y controles -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">Libro de Contabilidad</h2>
                            <span :class="badgeClass" class="text-sm px-3 py-1 rounded-full font-medium">
                                {{ tablaVisibleLabel }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">— ventana activa</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <!-- Pestañas -->
                            <div class="inline-flex rounded-md shadow-sm" role="tablist" aria-label="Tipo de tabla">
                                <button @click="setTabla('caja')"
                                    :class="tablaVisible === 'caja' ? activeTabClass : tabClass"
                                    class="px-3 py-1.5 rounded-l">Caja</button>
                                <button @click="setTabla('banco')"
                                    :class="tablaVisible === 'banco' ? activeTabClass : tabClass"
                                    class="px-3 py-1.5">Banco</button>
                                <button @click="setTabla('easy')"
                                    :class="tablaVisible === 'easy' ? activeTabClass : tabClass"
                                    class="px-3 py-1.5 rounded-r">Easy</button>
                            </div>

                            <!-- Selector mes/año simple -->
                            <div
                                class="flex items-center gap-2 p-2 border rounded bg-gray-50 dark:text-white dark:bg-gray-900">
                                <button @click="prevMonth"
                                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">‹</button>
                                <div class="px-4 text-center">
                                    <div class="text-sm text-gray-500">Mes / Año</div>
                                    <div class="text-base font-medium">{{ nombreMes(mesActivo) }} {{ anioActivo }}</div>
                                </div>
                                <button @click="nextMonth"
                                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">›</button>

                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center gap-2">
                                <button v-if="authUser && (authUser.role === 'admin')" @click="agregarAM"
                                    class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                                    Agregar
                                </button>

                                <button v-if="authUser && (authUser.role === 'admin')" @click="agregarEASY"
                                    class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200">
                                    Agregar Easy
                                </button>

                                <button @click="exportarExcel(mesActivo, anioActivo)"
                                    class="px-3 py-1.5 rounded bg-yellow-600 text-white">Exportar a Excel
                                </button>
                                <!-- botón para recalcular caja/banco (solo admin) -->
                                <button v-if="authUser && authUser.role === 'admin'" @click="onRecalcularCaja"
                                    class="px-3 py-1.5 rounded bg-red-600 text-white">
                                    Recalcular Caja
                                </button>

                                <button v-if="authUser && authUser.role === 'admin'" @click="onRecalcularBanco"
                                    class="px-3 py-1.5 rounded bg-red-600 text-white">
                                    Recalcular Banco
                                </button>

                            </div>
                        </div>
                    </div>

                    <!-- Línea informativa -->
                    <div class="text-sm text-gray-500 mb-2">
                        Mostrando: <strong>{{ tablaVisibleLabel }}</strong> — {{ nombreMes(mesActivo) }} {{ anioActivo }}
                    </div>

                    <!-- Tablas -->
                    <div>
                        <!-- ================= CAJA ================= -->
                        <div v-if="tablaVisible === 'caja'">
                            <table class="min-w-full text-sm text-left border dark:border-gray-700">
                                <thead
                                    class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                                    <tr>
                                        <th></th>
                                        <th class="p-3">N° Acta</th>
                                        <th class="p-3">Fecha</th>
                                        <th class="p-3">Descripción</th>
                                        <th class="p-3">Presupuestario</th>
                                        <th class="p-3">Actividad</th>
                                        <th class="p-3">Ingresos</th>
                                        <th class="p-3">Egresos</th>
                                        <th class="p-3">Saldo</th>
                                        <th v-if="authUser && (authUser.role === 'admin' || authUser.role === 'equipo')"
                                            class="p-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- SALDO APERTURA (mes anterior) - siempre al inicio -->
                                    <tr class="bg-gray-50 dark:bg-gray-800 text-sm dark:text-white">
                                        <td class="p-3" colspan="5">
                                            Saldo apertura — {{ nombreMes(mesActivo === 1 ? 12 : mesActivo - 1) }}
                                            {{ mesActivo === 1 ? (anioActivo - 1) : anioActivo }}
                                        </td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                        <td class="p-3 font-semibold">{{ formatNumber(aperturaCaja) }}</td>
                                        <td v-if="authUser && (authUser.role === 'admin' || authUser.role === 'equipo')"
                                            class="p-3"></td>
                                    </tr>

                                    <!-- FILAS DE ACTAS (sí muestran saldo) -->
                                    <tr v-for="acta in actasCajaFiltradas" :key="acta.id"
                                        class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <td class="p-3">
                                            <div @click="verVinculacion(acta)" title="Ver vinculación"
                                                class="w-4 h-4 rounded-full cursor-pointer"
                                                :class="isVinculado(acta) ? 'bg-green-500' : ''">
                                            </div>
                                        </td>
                                        <td class="p-3">{{ acta.n_acta }}</td>
                                        <td class="p-3">{{ acta.fecha }}</td>
                                        <td class="p-3">{{ acta.descripcion }}</td>
                                        <td class="p-3">{{ acta.presupuestario }}</td>
                                        <td class="p-3">{{ acta.actividad }}</td>
                                        <td class="p-3 text-green-600 font-semibold">{{ formatNumber(acta.ingresos) }}
                                        </td>
                                        <td class="p-3 text-red-600 font-semibold">{{ formatNumber(acta.egresos) }}</td>
                                        <td class="p-3 font-bold">{{ formatNumber(acta.saldo) }}</td>

                                        <!-- dentro de la celda de acciones de CAJA -->
                                        <td v-if="authUser && (authUser.role === 'admin' || authUser.role === 'equipo')"
                                            class="p-3 flex gap-2 items-center">
                                            <a :href="`/proyectos/${proyecto.id}/amcaja/${acta.id}/edit`"
                                                class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg">✏️</a>

                                            <button @click="eliminarCaja(acta.id)" title="Eliminar"
                                                class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg">🗑️
                                            </button>


                                            <button @click="abrirCrearEasyConActa(acta)"
                                                title="Abrir Crear EASY (pre-llenado)"
                                                class="flex items-center justify-center w-9 h-9 bg-indigo-600 text-white rounded-lg">
                                                ➡️
                                            </button>
                                        </td>

                                    </tr>

                                    <tr v-if="!actasCajaFiltradas || actasCajaFiltradas.length === 0">
                                        <td class="p-3 text-gray-500 italic" colspan="9">No hay registros en Caja.</td>
                                    </tr>

                                    <!-- ÚLTIMO SALDO REGISTRADO -->
                                    <tr class="bg-gray-50 dark:bg-gray-800 text-sm dark:text-white">
                                    <td class="p-3" colspan="5">Último saldo registrado</td>
                                    <td class="p-3"></td>
                                    <td class="p-3"></td>
                                    <td class="p-3 font-semibold">{{ formatNumber(ultimoSaldo) }}</td>
                                    <td v-if="authUser && (authUser.role === 'admin' || authUser.role === 'equipo')" class="p-3"></td>
                                    </tr>

                                    <!-- SALDO FINAL DEL MES -->
                                    <tr class="border-t bg-gray-200 dark:bg-gray-700 font-bold dark:text-white">
                                    <td class="p-3" colspan="5">💰 Saldo final — {{ nombreMes(mesActivo) }} {{ anioActivo }}</td>
                                    <td class="p-3"></td>
                                    <td class="p-3"></td>
                                    <td class="p-3 text-blue-700 dark:text-blue-400">{{ formatNumber(saldoFinal) }}</td>
                                    <td v-if="authUser && (authUser.role === 'admin' || authUser.role === 'equipo')" class="p-3"></td>
                                    </tr>
                                </tbody>
                            </table>
                            <!-- Modal Vinculaciones (mostrar datos de inventario) -->
                            <div v-if="modalVinculacionVisible"
                                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-11/12 md:w-2/3 shadow-lg">
                                    <div class="flex items-start justify-between mb-4">
                                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Vinculaciones del
                                            Acta
                                        </h2>
                                        <div class="flex items-center gap-2">
                                            <button @click="() => { modalVinculacionVisible = false }"
                                                class="px-3 py-1 rounded-md bg-gray-500 text-white hover:bg-gray-600 transition"
                                                title="Cerrar">Cerrar</button>
                                        </div>
                                    </div>

                                    <div class="mb-4 text-sm text-gray-700 dark:text-gray-200">
                                        <div><strong>N° Acta:</strong> {{ currentActa?.n_acta ?? currentActa?.id ?? '—' }}
                                        </div>
                                        <div><strong>Fecha:</strong> {{ formatFecha(currentActa?.fecha) }}</div>
                                        <div><strong>Descripción:</strong> {{ currentActa?.descripcion ?? '—' }}</div>
                                    </div>

                                    <div class="overflow-x-auto max-h-[60vh]">
                                        <table class="min-w-full text-sm border dark:text-white">
                                            <thead class="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                                <tr>
                                                    <th class="p-2 text-left">Inventario</th>
                                                    <th class="p-2 text-left">Código / Fila</th>
                                                    <th class="p-2 text-left">Cantidad</th>
                                                    <th class="p-2 text-left">Detalles</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr v-if="!vinculacionesActuales || vinculacionesActuales.length === 0">
                                                    <td colspan="5"
                                                        class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                        No hay vinculaciones para este acta.
                                                    </td>
                                                </tr>

                                                <tr v-for="(v, i) in vinculacionesActuales" :key="i"
                                                    class="border-t dark:border-gray-700">
                                                    <!-- Nombre / categoría -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            <div class="font-semibold">{{ v.inventario.descripcion ?? v.inventario.codigo ?? '—' }}</div>
                                                            <div class="text-xs text-gray-500">{{ v.inventario.categoria ?? '—' }}
                                                            </div>
                                                        </div>
                                                        <div v-else>
                                                            {{ v.descripcion ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Código / fila -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            {{ v.inventario.codigo ?? v.inventario.id ?? '—' }}
                                                        </div>
                                                        <div v-else>
                                                            {{ v.inventario_row_id ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Cantidad / entradas / stock -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            {{ v.inventario.entradas ?? v.inventario.stock ?? v.cantidad ?? '—' }}
                                                        </div>
                                                        <div v-else>
                                                            {{ v.cantidad ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Detalles: precio, fecha, stock -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            <div>Precio: <strong>{{ typeof v.inventario.precio !== 'undefined' ? formatNumber(v.inventario.precio) : '—' }}</strong>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Fecha:
                                                                {{ v.inventario.fecha ?  formatFecha(v.inventario.fecha) : '—' }}</div>
                                                            <div class="text-xs text-gray-500">Stock:
                                                                {{ v.inventario.stock ?? '—' }}</div>
                                                        </div>
                                                        <div v-else>
                                                            <div class="text-xs">{{ v.meta ? (typeof v.meta === 'object' ? JSON.stringify(v.meta) : v.meta) : '' }}</div>
                                                        </div>
                                                    </td>


                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                        <span><strong>Total vinculaciones:</strong> {{ vinculacionesActuales?.length ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>


                        </div>

                        <!-- ================= BANCO ================= -->
                        <div v-if="tablaVisible === 'banco'">
                            <table class="min-w-full text-sm text-left border dark:border-gray-700">
                                <thead
                                    class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                                    <tr>
                                        <th></th>
                                        <th class="p-3">N° Acta</th>
                                        <th class="p-3">Fecha</th>
                                        <th class="p-3">Descripción</th>
                                        <th class="p-3">Presupuestario</th>
                                        <th class="p-3">Actividad</th>
                                        <th class="p-3">Ingresos</th>
                                        <th class="p-3">Egresos</th>
                                        <th class="p-3">Saldo</th>
                                        <th class="p-3">Acciones</th>
                                        <th class="p-3">funsiones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- SALDO APERTURA BANCO (siempre al inicio) -->
                                    <tr class="bg-gray-50 dark:bg-gray-800 text-sm dark:text-white">
                                        <td class="p-3" colspan="5">
                                            Saldo apertura — {{ nombreMes(mesActivo === 1 ? 12 : mesActivo - 1) }}
                                            {{ mesActivo === 1 ? (anioActivo - 1) : anioActivo }}
                                        </td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                        <td class="p-3 font-semibold">{{ formatNumber(aperturaBanco) }}</td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                    </tr>

                                    <!-- FILAS DE ACTAS (muestran su propio saldo) -->
                                    <tr v-for="acta in actasBancoFiltradas" :key="acta.id"
                                        class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <td class="p-3">
                                            <div @click="verVinculacion(acta, 'banco')" title="Ver vinculación"
                                                class="w-4 h-4 rounded-full cursor-pointer"
                                                :class="isVinculado(acta, 'banco') ? 'bg-green-500' : ''">
                                            </div>
                                        </td>



                                        <td class="p-3">{{ acta.n_acta }}</td>
                                        <td class="p-3">{{ acta.fecha }}</td>
                                        <td class="p-3">{{ acta.descripcion }}</td>
                                        <td class="p-3">{{ acta.presupuestario }}</td>
                                        <td class="p-3">{{ acta.actividad }}</td>
                                        <td class="p-3 text-green-600 font-semibold">{{ formatNumber(acta.ingresos) }}
                                        </td>
                                        <td class="p-3 text-red-600 font-semibold">{{ formatNumber(acta.egresos) }}</td>
                                        <td class="p-3 font-bold">{{ formatNumber(acta.saldo) }}</td>
                                        <td class="p-3">{{ acta.accion }}</td>
                                        <td class="p-3 flex gap-2 items-center">
                                            <a :href="`/proyectos/${proyecto.id}/ambanco/${acta.id}/edit`"
                                                class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg">✏️</a>
                                            <button @click="eliminarBanco(acta.id)" title="Eliminar"
                                                class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg">🗑️
                                            </button>

                                            <!-- BOTÓN NUEVO: enviar a EASY -->
                                            <button @click="enviarActaAEasy(acta, 'banco')" title="Enviar a Easy"
                                                class="flex items-center justify-center w-9 h-9 bg-indigo-600 text-white rounded-lg">➡️</button>
                                        </td>


                                    </tr>

                                    <tr v-if="!actasBancoFiltradas || actasBancoFiltradas.length === 0">
                                        <td class="p-3 text-gray-500 italic" colspan="10">No hay registros en Banco.
                                        </td>
                                    </tr>

                                    <!-- ÚLTIMO SALDO REGISTRADO (solo uno) -->
                                    <tr class="bg-gray-50 dark:bg-gray-800 text-sm dark:text-white">
                                        <td class="p-3" colspan="5">Último saldo registrado</td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                        <td class="p-3 font-semibold">{{ formatNumber(ultimoSaldoBanco) }}</td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                    </tr>

                                    <!-- TOTALES BANCO (final) -->
                                    <tr class="border-t bg-gray-50 dark:bg-gray-800 font-semibold dark:text-white">
                                        <td class="p-3" colspan="5">Totales del mes — Movimientos</td>
                                        <td class="p-3 text-green-700">{{ formatNumber(ingresosBancoTotal) }}</td>
                                        <td class="p-3 text-red-600">{{ formatNumber(egresosBancoTotal) }}</td>
                                        <td class="p-3">{{ formatNumber(movimientosBanco) }}</td>
                                        <td class="p-3"></td>
                                        <td class="p-3"></td>
                                    </tr>
                                </tbody>

                            </table>
                            <div v-if="modalVinculacionVisible"
                                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-11/12 md:w-2/3 shadow-lg">
                                    <div class="flex items-start justify-between mb-4">
                                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Vinculaciones del
                                            Acta
                                        </h2>
                                        <div class="flex items-center gap-2">
                                            <button @click="() => { modalVinculacionVisible = false }"
                                                class="px-3 py-1 rounded-md bg-gray-500 text-white hover:bg-gray-600 transition"
                                                title="Cerrar">Cerrar</button>
                                        </div>
                                    </div>

                                    <div class="mb-4 text-sm text-gray-700 dark:text-gray-200">
                                        <div><strong>N° Acta:</strong> {{ currentActa?.n_acta ?? currentActa?.id ?? '—' }}
                                        </div>
                                        <div><strong>Fecha:</strong> {{ formatFecha(currentActa?.fecha) }}</div>
                                        <div><strong>Descripción:</strong> {{ currentActa?.descripcion ?? '—' }}</div>
                                    </div>

                                    <div class="overflow-x-auto max-h-[60vh]">
                                        <table class="min-w-full text-sm border dark:text-white">
                                            <thead class="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                                <tr>
                                                    <th class="p-2 text-left">Inventario</th>
                                                    <th class="p-2 text-left">Código / Fila</th>
                                                    <th class="p-2 text-left">Cantidad</th>
                                                    <th class="p-2 text-left">Detalles</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr v-if="!vinculacionesActuales || vinculacionesActuales.length === 0">
                                                    <td colspan="5"
                                                        class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                        No hay vinculaciones para este acta.
                                                    </td>
                                                </tr>

                                                <tr v-for="(v, i) in vinculacionesActuales" :key="i"
                                                    class="border-t dark:border-gray-700">
                                                    <!-- Nombre / categoría -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            <div class="font-semibold">{{ v.inventario.descripcion ?? v.inventario.codigo ?? '—' }}</div>
                                                            <div class="text-xs text-gray-500">{{ v.inventario.categoria ?? '—' }}
                                                            </div>
                                                        </div>
                                                        <div v-else>
                                                            {{ v.descripcion ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Código / fila -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            {{ v.inventario.codigo ?? v.inventario.id ?? '—' }}
                                                        </div>
                                                        <div v-else>
                                                            {{ v.inventario_row_id ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Cantidad / entradas / stock -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            {{ v.inventario.entradas ?? v.inventario.stock ?? v.cantidad ?? '—' }}
                                                        </div>
                                                        <div v-else>
                                                            {{ v.cantidad ?? '—' }}
                                                        </div>
                                                    </td>

                                                    <!-- Detalles: precio, fecha, stock -->
                                                    <td class="p-2">
                                                        <div v-if="v.inventario">
                                                            <div>Precio: <strong>{{ typeof v.inventario.precio !== 'undefined' ? formatNumber(v.inventario.precio) : '—' }}</strong>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Fecha:
                                                                {{ v.inventario.fecha ? formatFecha(v.inventario.fecha) : '—' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500">Stock:
                                                                {{ v.inventario.stock ?? '—' }}</div>
                                                        </div>
                                                        <div v-else>
                                                            <div class="text-xs">{{ v.meta ? (typeof v.meta === 'object' ? JSON.stringify(v.meta) : v.meta) : '' }}</div>
                                                        </div>
                                                    </td>


                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                        <span><strong>Total vinculaciones:</strong> {{ vinculacionesActuales?.length ??  0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= EASY ================= -->
                        <div v-if="tablaVisible === 'easy'">
                            <div class="overflow-x-auto">
                                <table class="w-full table-auto text-sm text-left border dark:border-gray-700">
                                    <thead
                                        class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                                        <tr>
                                            <!-- Columnas tipo Excel (ordenadas para lectura contable) -->
                                             <th></th>
                                            <th class="p-2">Codigo general</th>
                                            <th class="p-2">Gasto (PEN)</th>
                                            <th class="p-2">Receta (PEN)</th>
                                            <th class="p-2">Moneda de facturación</th>
                                            <th class="p-2">Débito (EUR)</th>
                                            <th class="p-2">Crédito (EUR)</th>
                                            <th class="p-2">Moneda de gestión</th>
                                            <th class="p-2">Numeración y descripción</th>
                                            <th class="p-2">código de presupuesto</th>
                                            <th class="p-2">Naturaleza</th>
                                            <th class="p-2">Contrato</th>
                                            <th class="p-2">Donantes</th>
                                            <th class="p-2">Fecha</th>
                                            <th class="p-2">Acciones</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <!-- FILAS DE ITEMS -->
                                        <tr v-for="(item, idx) in itemsEasyFiltrados" :key="item.id"
                                            class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                            <td class="p-3 text-center">
                                                <div
                                                    @click="verVinculacion(item, 'easy')"
                                                    :title="isVinculado(item, 'easy') ? 'Ver vinculación' : 'Sin vinculación'"
                                                    class="w-4 h-4 rounded-full cursor-pointer mx-auto"
                                                    :class="isVinculado(item, 'easy') ? 'bg-green-500 hover:bg-green-600' : ''"
                                                ></div>
                                            </td>
                                            <td class="p-2">{{ item.Cuenta_general }}</td>
                                            <td class="p-2">{{ item.gasto_moneda_local }}</td>
                                            <td class="p-2">{{ item.ingreso_moneda_local }}</td>
                                            <td class="p-2">{{ item.moneda_facturacion }}</td>
                                            <td class="p-2">{{ item.debito_moneda_gestion }}</td>
                                            <td class="p-2">{{ item.credito_moneda_gestion }}</td>
                                            <td class="p-2">{{ item.moneda_gestion }}</td>
                                            <td class="p-2">{{ item.numero_descripcion_pieza }}</td>
                                            <td class="p-2">{{ item.codigo_presupuestario }}</td>
                                            <td class="p-2">{{ item.naturaleza_presupuesto }}</td>
                                            <td class="p-2">{{ item.contrato }}</td>
                                            <td class="p-2">{{ item.bailleur_fondos }}</td>
                                            <td class="p-2">{{ item.fecha }}</td>
                                            <td class="p-3 flex gap-2 items-center">
                                                <!-- simple: solo proyecto.id y item.id -->
                                                <a :href="`/proyectos/${proyecto.id}/easy/${item.id}/edit`"
                                                    class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg">✏️</a>

                                                <button @click="eliminarActa(item.id)"
                                                    class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg">🗑️</button>
                                                <button
                                                    @click="enviarAInventario"
                                                    class="flex items-center justify-center w-9 h-9 bg-indigo-600 text-white rounded-lg">
                                                    ➡️
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- SIN REGISTROS -->
                                        <tr v-if="!itemsEasyFiltrados || itemsEasyFiltrados.length === 0">
                                            <td class="p-2 italic text-gray-500" colspan="14">No hay registros Easy.
                                            </td>
                                        </tr>

                                        <!-- TOTALES EASY -->
                                        <tr class="border-t bg-gray-50 dark:bg-gray-800 font-semibold dark:text-white">
                                            <td class="p-2" colspan="2">Totales del mes — Movimientos</td>
                                            <td class="p-2 text-black-700 dark:text-white">Total (Moneda local):
                                                {{ formatNumber(egresosEasyTotal) }}</td>
                                            <td></td>
                                            <td class="p-2 text-black-700 dark:text-white">Total (Moneda gestión):
                                                {{ formatNumber(egresosgestiónEasyTotal) }}</td>
                                            <td colspan="9"></td>
                                        </tr>
                                    </tbody>

                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

table th,
table td {
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
</style>