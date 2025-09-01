<script setup>
/* ==========================
📌 IMPORTACIONES Y CONFIGURACIÓN
========================== */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, reactive } from 'vue';
import axios from 'axios';

const user = usePage().props.auth.user;

const props = defineProps({
    proyecto: Object,
    inventarios: Array,
    salidas: Array,
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
const filtroSalidas = ref('');
const filtroCategoria = ref("todos");
const filtroStock = ref(false);
const filtroSolicitadoPor = ref("todos");
const filtroProyectoLg = ref("todos");

const ordenInventarioAsc = ref(true);
const ordenSalidasAsc = ref(true);

const comentarioActivo = ref(null)
const tooltipVisible = ref(false);
const productoTooltip = ref(null);
const mostrarDetalleTotal = ref(false);
const categoriasExpandida = reactive({});
const modalVisible = ref(false);
const salidasProducto = ref([]);

/* ==========================
📌 COMPUTED PROPERTIES (FILTRADO Y CÁLCULOS)
========================== */
const salidasFiltradas = computed(() =>
    [...props.salidas]
        .filter(s => s.nombre.toLowerCase().includes(filtroSalidas.value.toLowerCase()))
        .sort((a, b) =>
            ordenSalidasAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

const totalInventario = computed(() =>
    props.inventarios.reduce((sum, item) => sum + (item.precio * item.stock), 0)
);
const totalEntradas = computed(() =>
    props.inventarios.reduce((sum, item) => sum + (item.precio * item.entradas), 0)
);
const totalSalidas = computed(() =>
    props.inventarios.reduce((sum, item) => sum + (item.precio * item.salidas), 0)
);
const resumenCategorias = computed(() =>
    props.inventarios.reduce((acc, item) => {
        if (!acc[item.categoria]) acc[item.categoria] = 0;
        acc[item.categoria] += item.precio * item.stock;
        return acc;
    }, {})
);

const categorias = computed(() => {
    return [...new Set(props.inventarios.map(i => i.categoria))];
});
const solicitantesUnicos = computed(() => {
    return [...new Set(props.inventarios.map(i => i.solicitado_por).filter(Boolean))];
});
const proyectosLgUnicosFiltrados = computed(() => {
    if (filtroSolicitadoPor.value === "todos") {
        return [];
    }
    return [
        ...new Set(
            props.inventarios
                .filter(i => i.solicitado_por === filtroSolicitadoPor.value)
                .map(i => i.proyecto_lg)
                .filter(Boolean)
        )
    ];
});

const inventarioFiltrado = computed(() =>
    [...props.inventarios]
        .filter(i =>
            i.descripcion.toLowerCase().includes(filtroInventario.value.toLowerCase()) ||
            i.codigo.toLowerCase().includes(filtroInventario.value.toLowerCase()) ||
            i.categoria.toLowerCase().includes(filtroInventario.value.toLowerCase())
        )
        .filter(i => filtroCategoria.value === "todos" || i.categoria === filtroCategoria.value)
        .filter(i => filtroSolicitadoPor.value === "todos" || i.solicitado_por === filtroSolicitadoPor.value)
        .filter(i => filtroProyectoLg.value === "todos" || i.proyecto_lg === filtroProyectoLg.value)
        .filter(i => !filtroStock.value || i.stock > 0)
        .sort((a, b) =>
            ordenInventarioAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

// Resumen por Solicitado por
const resumenSolicitadoPor = computed(() =>
    props.inventarios.reduce((acc, item) => {
        if (!item.solicitado_por) return acc; // evita null o undefined
        acc[item.solicitado_por] = (acc[item.solicitado_por] || 0) + (item.precio * item.stock);
        return acc;
    }, {})
);

// Resumen por Proyecto
const resumenProyecto = computed(() =>
    props.inventarios.reduce((acc, item) => {
        if (!item.proyecto_lg) return acc;
        acc[item.proyecto_lg] = (acc[item.proyecto_lg] || 0) + (item.precio * item.stock);
        return acc;
    }, {})
);

// Estados para expandir/cerrar detalle
const solicitadoExpandido = reactive({});
const proyectoExpandido = reactive({});

const toggleSolicitado = (user) => {
  solicitadoExpandido[user] = !solicitadoExpandido[user];
};
const toggleProyecto = (proj) => {
  proyectoExpandido[proj] = !proyectoExpandido[proj];
};



/* ==========================
📌 ACCIONES (AGREGAR, ELIMINAR, EXPORTAR, SALIDA_DE_PRODUCTO)
========================== */
const agregarInventario = () => router.visit(`/proyectos/${props.proyecto.id}/inventarios/create`);
const agregarSalida = () => router.visit(`/proyectos/${props.proyecto.id}/salidas/create`);

const eliminarRegistro = (id) => {
    if (confirm('¿Eliminar este inventario?')) {
        axios.delete(`/proyectos/${props.proyecto.id}/inventarios/${id}`).then(() => {
            alert('Inventario eliminado');
            window.location.reload();
        });
    }
};

const eliminarSalida = (id) => {
    console.log("🗑️ Intentando eliminar salida con ID:", id);

    if (confirm('¿Eliminar esta salida? Se restaurará el stock en inventario.')) {
        axios.delete(`/proyectos/${props.proyecto.id}/salidas/${id}`)
            .then(() => {
                alert('✅ Salida eliminada y stock restaurado');
                window.location.reload();
            })
            .catch((error) => {
                console.error("❌ Error eliminando salida:", error.response ?? error);
                alert('❌ No se pudo eliminar la salida. Revisa la consola para más detalles.');
            });
    }
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
const toggleCategoria = (cat) => categoriasExpandida[cat] = !categoriasExpandida[cat];

/* ==========================
📌 TOOLTIP DE PRODUCTO
========================== */
const cache = {};
let timeoutId = null;

const mostrarTooltip = (codigo) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(async () => {
        if (cache[codigo]) {
            productoTooltip.value = cache[codigo];
            tooltipVisible.value = true;
            return;
        }
        try {
            const res = await axios.get(`/proyectos/${props.proyecto.id}/buscar-producto/${encodeURIComponent(codigo)}`);
            if (res.data.existe) {
                cache[codigo] = res.data.producto;
                productoTooltip.value = res.data.producto;
                tooltipVisible.value = true;
            }
        } catch {
            productoTooltip.value = null;
            tooltipVisible.value = false;
        }
    }, 300);
};

const ocultarTooltip = () => {
    clearTimeout(timeoutId);
    tooltipVisible.value = false;
    productoTooltip.value = null;
};

/* ==========================
📌 REFRESCAR DATOS
========================== */
const refrescarInventario = () => router.reload({ only: ['inventarios'] });
const refrescarSalida = () => router.reload({ only: ['salidas'] });

/* ==========================
📌 DETALLE DE SALIDAS DE UN PRODUCTO
========================== */
const verSalidas = async (codigo) => {
    try {
        const res = await axios.get(`/proyectos/${props.proyecto.id}/salidas/producto/${codigo}`);
        salidasProducto.value = res.data;
        modalVisible.value = true;
    } catch (err) {
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
        salidasProducto.value = [];
        modalVisible.value = true;
    }
};

</script>


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
                Estado: {{ proyecto.estado }} — Inicio: {{ proyecto.fecha_inicio }} — Fin: {{ proyecto.fecha_fin ??
                    'Pendiente' }}
            </p>
        </template>

        <div v-if="pestañaActiva === 'Precios'"
            class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg mt-6 border border-gray-200 dark:border-gray-700">

            <!-- Título -->
            <h2 class="text-2xl font-extrabold mb-5 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span class="text-indigo-500">📊</span> Resumen de Inventario
            </h2>

            <!-- GRID PRINCIPAL -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Valor total con detalle -->
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl shadow-sm">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Valor Total</p>
                            <p class="text-xl font-bold text-indigo-600 dark:text-indigo-300">
                                S/ {{ totalInventario.toFixed(2) }}
                            </p>
                        </div>
                        <button @click="mostrarDetalleTotal = !mostrarDetalleTotal"
                            class="text-xs px-2 py-1 bg-indigo-200 dark:bg-indigo-700 rounded hover:bg-indigo-300 dark:hover:bg-indigo-600">
                            {{ mostrarDetalleTotal ? 'Ocultar' : 'Ver detalle' }}
                        </button>
                    </div>
                    <!-- Detalle de inventario -->
                    <ul v-if="mostrarDetalleTotal" class="mt-3 text-sm space-y-1 max-h-40 overflow-y-auto pr-2">
                        <li v-for="item in props.inventarios" :key="item.id"
                            class="flex justify-between border-b border-gray-200 dark:text-white dark:border-gray-700 pb-1">
                            <span>{{ item.descripcion }} ({{ item.stock }} × S/ {{ item.precio }})</span>
                            <span class="font-bold">S/ {{ (item.stock * item.precio).toFixed(2) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Entradas -->
                <div class="p-4 bg-green-50 dark:bg-green-900/30 rounded-xl shadow-sm">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Entradas</p>
                    <p class="text-xl font-bold text-green-600 dark:text-green-300">
                        + S/ {{ totalEntradas.toFixed(2) }}
                    </p>
                </div>

                <!-- Salidas -->
                <div class="p-4 bg-red-50 dark:bg-red-900/30 rounded-xl shadow-sm">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Salidas</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-300">
                        - S/ {{ totalSalidas.toFixed(2) }}
                    </p>
                </div>
            </div>

            <!-- Por Categoría -->
            <div class="mt-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-purple-500">📦</span> Por Categoría
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="(valor, cat) in resumenCategorias" :key="cat" class="py-2">
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ cat }}</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">S/ {{ valor.toFixed(2) }}</span>
                        </div>
                        <button @click="toggleCategoria(cat)"
                            class="text-xs text-purple-600 dark:text-purple-300 mt-1 hover:underline">
                            {{ categoriasExpandida[cat] ? 'Ocultar detalle' : 'Ver detalle' }}
                        </button>
                        <ul v-if="categoriasExpandida[cat]"
                            class="mt-2 text-sm ml-4 list-disc space-y-1 dark:text-white">
                            <li v-for="item in props.inventarios.filter(i => i.categoria === cat)" :key="item.id">
                                {{ item.descripcion }}: {{ item.stock }} × S/ {{ item.precio }} =
                                <strong>S/ {{ (item.stock * item.precio).toFixed(2) }}</strong>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Por Solicitado por -->
            <div class="mt-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-500">🙋</span> Por Solicitado por
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="(valor, user) in resumenSolicitadoPor" :key="user" class="py-2">
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ user }}</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">S/ {{ valor.toFixed(2) }}</span>
                        </div>
                        <button @click="toggleSolicitado(user)"
                            class="text-xs text-blue-600 dark:text-blue-300 mt-1 hover:underline">
                            {{ solicitadoExpandido[user] ? 'Ocultar detalle' : 'Ver detalle' }}
                        </button>
                        <ul v-if="solicitadoExpandido[user]"
                            class="mt-2 text-sm ml-4 list-disc space-y-1 dark:text-white">
                            <li v-for="item in props.inventarios.filter(i => i.solicitado_por === user)" :key="item.id">
                                {{ item.descripcion }}: {{ item.stock }} × S/ {{ item.precio }} =
                                <strong>S/ {{ (item.stock * item.precio).toFixed(2) }}</strong>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Por Proyecto -->
            <div class="mt-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-orange-500">🏗️</span> Por Proyecto
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="(valor, proj) in resumenProyecto" :key="proj" class="py-2">
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ proj }}</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">S/ {{ valor.toFixed(2) }}</span>
                        </div>
                        <button @click="toggleProyecto(proj)"
                            class="text-xs text-orange-600 dark:text-orange-300 mt-1 hover:underline">
                            {{ proyectoExpandido[proj] ? 'Ocultar detalle' : 'Ver detalle' }}
                        </button>
                        <ul v-if="proyectoExpandido[proj]"
                            class="mt-2 text-sm ml-4 list-disc space-y-1 dark:text-white">
                            <li v-for="item in props.inventarios.filter(i => i.proyecto_lg === proj)" :key="item.id">
                                {{ item.descripcion }}: {{ item.stock }} × S/ {{ item.precio }} =
                                <strong>S/ {{ (item.stock * item.precio).toFixed(2) }}</strong>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>


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

                        <!-- Proyecto LG -->
                        <div v-if="filtroSolicitadoPor !== 'todos'">
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Proyecto
                                (LG)</label>
                            <select v-model="filtroProyectoLg" class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500
                            dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="todos">Todos</option>
                                <option v-for="proyecto in proyectosLgUnicosFiltrados" :key="proyecto"
                                    :value="proyecto">
                                    {{ proyecto }}
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
                            📅 Ordenar: <span class="font-semibold">{{ ordenInventarioAsc ? 'Antiguos' : 'Recientes'
                            }}</span>
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
                                <th class="p-3">Proyecto</th>
                                <th class="p-3" v-if="user.role === 'admin' || user.role === 'equipo'">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in inventarioFiltrado" :key="item.id"
                                class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <td class="p-3">
                                    <div @click="verSalidas(item.codigo)" title="Ver salidas"
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
                                <td class="p-3">{{ item.proyecto_lg }}</td>
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

                                    <!-- 🔹 Admin y Equipo pueden registrar salida -->
                                    <a v-if="item.stock > 0 && (user.role === 'admin' || user.role === 'equipo')"
                                        :href="`/proyectos/${proyecto.id}/salidas/create?codigo=${item.codigo}`"
                                        title="Registrar salida"
                                        class="flex items-center justify-center w-9 h-9 bg-yellow-400 text-white rounded-lg hover:bg-yellow-500 hover:scale-110 transition">
                                        📦
                                    </a>

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

                            <h2 class="text-lg font-bold mb-4 text-gray-800 dark:text-white">
                                Salidas del producto
                            </h2>

                            <table class="min-w-full text-sm border dark:text-white">
                                <thead class="bg-gray-200 dark:bg-gray-700">
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
                                    <tr v-if="salidasProducto.length === 0">
                                        <td colspan="6" class="text-center py-4 text-gray-500">
                                            No hay salidas para este producto
                                        </td>
                                    </tr>
                                    <tr v-for="s in salidasProducto" :key="s.id" class="border-t dark:border-gray-700">
                                        <td class="p-2">{{ s.n_acta }}</td>
                                        <td class="p-2">{{ s.nombre }}</td>
                                        <td class="p-2">{{ s.lugar }}</td>
                                        <td class="p-2">{{ s.distrito }}</td>
                                        <td class="p-2">{{ s.fecha }}</td>
                                        <td class="p-2">{{ s.cantidad }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="mt-6 flex justify-end">
                                <button @click="modalVisible = false"
                                    class="px-4 py-2 bg-gray-500 text-white rounded-lg shadow hover:bg-gray-600 transition">
                                    Cerrar
                                </button>
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

                <div class="overflow-x-auto max-h-[600px]">
                    <table class="min-w-full text-sm text-left border dark:border-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                            <tr>
                                <th class="p-3">N° Acta</th>
                                <th class="p-3">Nombre</th>
                                <th class="p-3">Lugar</th>
                                <th class="p-3">Distrito</th>
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Código del Producto</th>
                                <th class="p-3">Cantidad</th>
                                <th v-if="user.role === 'admin' || user.role === 'equipo'" class="p-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="salida in salidasFiltradas" :key="salida.id"
                                class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <td class="p-3">{{ salida.n_acta }}</td>
                                <td class="p-3">{{ salida.nombre }}</td>
                                <td class="p-3">{{ salida.lugar }}</td>
                                <td class="p-3">{{ salida.distrito }}</td>
                                <td class="p-3">{{ salida.fecha }}</td>
                                <!-- Celda del producto con tooltip -->
                                <td class="p-3 relative cursor-pointer" @mouseenter="mostrarTooltip(salida.producto)"
                                    @mouseleave="ocultarTooltip">
                                    {{ salida.producto }}
                                    <div v-if="tooltipVisible && productoTooltip?.codigo === salida.producto"
                                        class="absolute z-10 mt-1 p-3 border rounded bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-50 shadow-lg w-64">
                                        <p><strong>Descripción:</strong> {{ productoTooltip.descripcion }}</p>
                                        <p><strong>Categoría:</strong> {{ productoTooltip.categoria }}</p>
                                        <p><strong>Stock:</strong> {{ productoTooltip.stock }}</p>
                                        <p><strong>U.M.:</strong> {{ productoTooltip.unidad_medida }}</p>
                                    </div>
                                </td>
                                <td class="p-3">{{ salida.cantidad }}</td>
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
        </div>
    </AuthenticatedLayout>
</template>
