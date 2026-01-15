<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    salidas: Array,
    proyecto: Object,
    user: Object
});

// Estados para tooltip
const tooltipVisible = ref(false);
const productoTooltip = ref(null);
const codigoActivo = ref(null);
const posicionTooltip = ref("abajo");
const timeoutId = ref(null);
const cache = ref({});

// Funciones de tooltip
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

// Función para cambiar estado
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
        const res = await axios.patch(
            `/proyectos/${props.proyecto.id}/salidas/${salida.id}`,
            payload
        );

        const estadoFinal = res.data?.estado ?? nuevoEstado;

        // Actualizar estado en todas las salidas con el mismo n_acta
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
        
        let texto = 'No se pudo actualizar el estado.';
        if (err.response?.status === 403) texto = 'No tienes permiso.';
        else if (err.request && !err.response) texto = 'El servidor no respondió.';
        
        Swal.fire({ icon: 'error', title: 'Error', text: texto });
    }
};

// Función para eliminar salida
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

// Helper para formatear cantidad
const formatearCantidad = (valor) => {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Number(valor);
    if (Number.isNaN(n)) return String(valor);
    if (Number.isInteger(n)) return String(n);
    const f = n.toFixed(2);
    return f.replace(/\.?0+$/, '').replace(/\.(\d)0$/, '.$1');
};
</script>

<template>
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
                    <th v-if="user.role === 'admin' || user.role === 'equipo'" class="p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="salida in salidas" :key="salida.id"
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
                                <p><strong>Descripción:</strong> {{ productoTooltip?.descripcion }}</p>
                                <p><strong>Categoría:</strong> {{ productoTooltip?.categoria }}</p>
                                <p><strong>Stock:</strong> {{ productoTooltip?.stock }}</p>
                                <p><strong>U.M.:</strong> {{ productoTooltip?.unidad_medida }}</p>
                            </div>
                        </transition>
                    </td>

                    <td class="p-3">{{ salida.um }}</td>
                    <td class="p-3">{{ formatearCantidad(salida.cantidad) }}</td>
                    
                    <!-- Acciones -->
                    <td v-if="user.role === 'admin' || user.role === 'equipo'" class="p-3 flex gap-2 items-center">
                        <!-- Editar -->
                        <a 
                            :href="salida.estado === 'aceptado' ? '#' : `/proyectos/${proyecto.id}/salidas/${salida.id}/edit`"
                            :class="[
                                'flex items-center justify-center w-9 h-9 rounded-lg transition',
                                salida.estado === 'aceptado' 
                                    ? 'bg-gray-400 text-gray-200 cursor-not-allowed' 
                                    : 'bg-blue-500 text-white hover:bg-blue-600 hover:scale-110'
                            ]"
                            :title="salida.estado === 'aceptado' ? 'No se puede editar salidas aceptadas' : 'Editar'"
                            @click="salida.estado === 'aceptado' && $event.preventDefault()"
                        >
                            ✏️
                        </a>
                        
                        <!-- Eliminar -->
                        <button    
                            @click="salida.estado === 'aceptado' ? null : eliminarSalida(salida.id)"
                            :class="[
                                'flex items-center justify-center w-9 h-9 rounded-lg transition',
                                salida.estado === 'aceptado' 
                                    ? 'bg-gray-400 text-gray-200 cursor-not-allowed' 
                                    : 'bg-red-500 text-white hover:bg-red-600 hover:scale-110'
                            ]"
                            :title="salida.estado === 'aceptado' ? 'No se puede eliminar salidas aceptadas' : 'Eliminar'"
                            :disabled="salida.estado === 'aceptado'"
                        >
                            🗑️
                        </button>
                    </td>
                </tr>

                <!-- Sin resultados -->
                <tr v-if="salidas.length === 0">
                    <td colspan="11" class="p-3 text-center text-gray-500 dark:text-gray-400">
                        No se encontraron salidas
                    </td>
                </tr>
            </tbody>
        </table>
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