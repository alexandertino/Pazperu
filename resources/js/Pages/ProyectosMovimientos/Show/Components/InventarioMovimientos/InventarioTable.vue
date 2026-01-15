<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePdfGenerator } from '@/Composables/usePdfGenerator.js';

const props = defineProps({
    inventarios: Array,
    proyecto: Object,
    user: Object,
    comentarioActivo: [String, Number]
});



const generarPdf = () => {
    descargarPdfSalidas({
        producto: currentProducto.value,
        salidas: salidasProducto.value,
        proyectoNombre: props.proyecto.nombre ?? 'Proyecto'
    });
};

const { generandoPdf, descargarPdfSalidas } = usePdfGenerator();

const emit = defineEmits(['toggle-comentario']);

// Estados para tooltip y modal
const tooltipVisible = ref(false);
const productoTooltip = ref(null);
const codigoActivo = ref(null);
const posicionTooltip = ref("abajo");
const timeoutId = ref(null);
const cache = ref({});

// Estados para modal de salidas
const salidasProducto = ref([]);
const modalVisible = ref(false);
const currentCodigo = ref(null);
const currentProducto = ref({ nombre: null, codigo: null, descripcion: null, stock: null, solicitado_por: null });

// Funciones de tooltip (copiadas de tu código original)
const mostrarTooltip = (codigo, event) => {
    if (!codigo) return;

    clearTimeout(timeoutId.value);
    codigoActivo.value = codigo;

    const rect = event.target.getBoundingClientRect();
    const espacioAbajo = window.innerHeight - rect.bottom;
    posicionTooltip.value = espacioAbajo < 200 ? "arriba" : "abajo";

    timeoutId.value = setTimeout(async () => {
        if (cache.value[codigo]) {
            productoTooltip.value = cache.value[codigo];
            tooltipVisible.value = true;
            return;
        }

        try {
            const res = await axios.get(
                `/proyectos/${props.proyecto.id}/buscar-producto/${encodeURIComponent(codigo)}`
            );

            if (res.data.existe && res.data.producto) {
                cache.value[codigo] = res.data.producto;
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
    clearTimeout(timeoutId.value);
    tooltipVisible.value = false;
    productoTooltip.value = null;
    codigoActivo.value = null;
};

// Función para ver salidas del producto
const verSalidas = async (item) => {
    const codigo = item.codigo ?? item.producto_code ?? item.code ?? item.id ?? null;

    if (!codigo) {
        alert('No se pudo determinar el código del producto.');
        return;
    }

    try {
        const url = `/proyectos/${props.proyecto.id}/salidas/producto/${encodeURIComponent(codigo)}`;
        const res = await axios.get(url, { headers: { Accept: 'application/json' } });

        const datos = res.data.salidas ?? res.data ?? [];
        salidasProducto.value = Array.isArray(datos) ? datos : [];
        currentCodigo.value = codigo;

        currentProducto.value = {
            nombre: item?.descripcion ?? item?.producto_label ?? item?.producto_name ?? null,
            codigo: codigo,
            descripcion: item?.descripcion ?? item?.desc ?? null,
            stock: item?.stock ?? (item?.inventario ?? null),
            solicitado_por: item?.solicitado_por ?? null
        };

        if (!currentProducto.value.nombre && salidasProducto.value.length > 0) {
            const first = salidasProducto.value[0];
            currentProducto.value.nombre = first.producto_label ?? first.producto ?? first.producto_name ?? first.solicitante ?? null;
        }

        modalVisible.value = true;
    } catch (err) {
        console.error('verSalidas error:', err);
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

// Función para eliminar inventario
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

// Helper para formatear fecha
const formatFecha = (f) => {
    if (!f) return '—';
    try {
        const d = new Date(f);
        if (isNaN(d)) return f;
        return d.toLocaleDateString();
    } catch (e) {
        return f;
    }
};

</script>

<template>
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
                    <th class="p-3" v-if="user.role === 'admin'">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in inventarios" :key="item.id"
                    class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">

                    <!-- Indicador de salidas -->
                    <td class="p-3">
                        <div @click="verSalidas(item)" title="Ver salidas" class="w-4 h-4 rounded-full cursor-pointer"
                            :class="item.stock === 0 ? 'bg-green-500' : 'bg-red-500'">
                        </div>
                    </td>

                    <!-- Código con tooltip -->
                    <td class="p-3 relative cursor-pointer" @mouseenter="mostrarTooltip(item.codigo, $event)"
                        @mouseleave="ocultarTooltip">
                        {{ item.codigo }}

                        <!-- Tooltip -->
                        <transition name="fade">
                            <div v-if="tooltipVisible && codigoActivo === item.codigo" :class="[
                                'absolute left-1/2 -translate-x-1/2 z-50 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-50 shadow-xl w-64',
                                posicionTooltip === 'abajo' ? 'top-full mt-2' : 'bottom-full mb-2'
                            ]">
                                <p><strong>Descripción:</strong> {{ productoTooltip?.descripcion }}</p>
                                <p><strong>Categoría:</strong> {{ productoTooltip?.categoria }}</p>
                                <p><strong>Stock:</strong> {{ productoTooltip?.stock }}</p>
                                <p><strong>U.M.:</strong> {{ productoTooltip?.unidad_medida }}</p>
                            </div>
                        </transition>
                    </td>

                    <td class="p-3">{{ formatFecha(item.fecha) }}</td>
                    <td class="p-3">{{ item.descripcion }}</td>
                    <td class="p-3">{{ item.categoria }}</td>
                    <td class="p-3">{{ item.unidad_medida }}</td>
                    <td class="p-3">{{ item.entradas }}</td>
                    <td class="p-3">{{ item.salidas }}</td>
                    <td class="p-3">{{ item.stock }}</td>
                    <td class="p-3">S/ {{ Number(item.precio ?? 0).toFixed(2) }}</td>
                    <td class="p-3">{{ item.solicitado_por }}</td>

                    <!-- Acciones -->
                    <td class="p-3 flex gap-2" v-if="user.role === 'admin'">
                        <!-- Editar -->
                        <a :href="`/proyectos/${proyecto.id}/inventarios/${item.id}/edit`" title="Editar"
                            class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg hover:bg-blue-600 hover:scale-110 transition">
                            ✏️
                        </a>

                        <!-- Eliminar -->
                        <button @click="eliminarRegistro(item.id)" title="Eliminar"
                            class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg hover:bg-red-600 hover:scale-110 transition">
                            🗑️
                        </button>

                        <!-- Comentario (si existe) -->
                        <button v-if="item.comentario" @click="$emit('toggle-comentario', item.id)"
                            title="Ver comentario"
                            class="flex items-center justify-center w-9 h-9 bg-purple-500 text-white rounded-lg hover:bg-purple-600 hover:scale-110 transition">
                            💬
                        </button>
                    </td>
                </tr>

                <!-- Comentario expandido -->
                <tr v-if="comentarioActivo">
                    <td colspan="12" class="p-3 bg-gray-50 dark:bg-gray-800">
                        <div v-for="item in inventarios" :key="item.id">
                            <div v-if="comentarioActivo === item.id && item.comentario"
                                class="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg shadow-md text-sm text-gray-700 dark:text-gray-200">
                                <p class="whitespace-pre-line">{{ item.comentario }}</p>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Sin resultados -->
                <tr v-if="inventarios.length === 0">
                    <td colspan="12" class="p-3 text-center text-gray-500 dark:text-gray-400">
                        No se encontraron inventarios
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Modal de Salidas -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-11/12 md:w-2/3 shadow-lg">
                <div class="flex items-start justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">
                        Salidas del producto
                    </h2>
                    <div class="flex items-center space-x-2">
                        <button @click="modalVisible = false"
                            class="px-3 py-1 rounded-md bg-gray-500 text-white hover:bg-gray-600 transition"
                            title="Cerrar">
                            Cerrar
                        </button>

                        <button @click="generarPdf"
                            class="px-3 py-1 rounded-md bg-blue-500 text-white hover:bg-blue-600 transition"
                            :disabled="generandoPdf">
                            {{ generandoPdf ? 'Generando...' : 'PDF' }}
                        </button>
                    </div>
                </div>


                <!-- Info producto -->
                <div class="mb-4 text-sm text-gray-700 dark:text-gray-200">
                    <div><strong>Producto:</strong> {{ currentProducto.nombre ?? '—' }}</div>
                    <div><strong>Código:</strong> {{ currentProducto.codigo ?? '—' }}</div>
                    <div v-if="currentProducto.stock !== null"><strong>Stock:</strong> {{ currentProducto.stock }}</div>
                    <div><strong>Solicitado:</strong> {{ currentProducto.solicitado_por ?? '—' }}</div>
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
                            <tr v-if="salidasProducto.length === 0">
                                <td colspan="6" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                    No hay salidas para este producto
                                </td>
                            </tr>
                            <tr v-for="s in salidasProducto" :key="s.id" class="border-t dark:border-gray-700">
                                <td class="p-2">{{ s.n_acta ?? s.nacta ?? '—' }}</td>
                                <td class="p-2">{{ s.nombre ?? s.persona ?? s.persona_nombre ?? '—' }}</td>
                                <td class="p-2">{{ s.lugar ?? s.site ?? '—' }}</td>
                                <td class="p-2">{{ s.distrito ?? s.district ?? '—' }}</td>
                                <td class="p-2">{{ formatFecha(s.fecha) }}</td>
                                <td class="p-2">{{ s.cantidad ?? s.qty ?? s.cant ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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