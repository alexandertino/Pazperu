<template>
    <AuthenticatedLayout>

        <Head :title="`Libro - ${cuenta.nombre}`" />

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- HEADER -->
            <div class="mb-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                            {{ cuenta.nombre }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-4 mt-2">
                            <p class="text-gray-600 dark:text-gray-400">
                                Libro de Bancos
                            </p>
                            <span class="text-sm font-semibold px-3 py-1 rounded-full" :class="cuenta.saldo_actual >= 0
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'">
                                Saldo: S/ {{ formatoDinero(cuenta.saldo_actual) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="`/cuentas`"
                            class="px-4 py-2 rounded-lg bg-gray-500 text-white hover:bg-gray-600 transition text-sm font-medium">
                            ← Volver a Cuentas
                        </Link>

                        <button @click="recalcularSaldos" :disabled="recalculando"
                            class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 transition text-sm font-medium flex items-center gap-2">
                            <svg v-if="recalculando" class="animate-spin h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span v-else>🔄</span>
                            {{ recalculando ? 'Recalculando...' : 'Recalcular' }}
                        </button>

                        <Link :href="`/movimientos/create?id=${cuenta.id}`"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition text-sm font-medium">
                            ➕ Nuevo Movimiento
                        </Link>


                    </div>
                </div>
            </div>

            <!-- FILTROS -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Desde
                        </label>
                        <input type="date" v-model="filtroFechaDesde"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm p-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Hasta
                        </label>
                        <input type="date" v-model="filtroFechaHasta"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm p-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Buscar en descripción
                        </label>
                        <div class="relative">
                            <input type="text" v-model="filtroDescripcion" placeholder="Buscar..."
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm p-2 pl-10">
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                🔍
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Fondo
                            </label>
                            <select v-model="filtroFondo"
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm p-2">
                                <option value="">Todos los fondos</option>
                                <option v-for="fondo in fondos" :key="fondo.id" :value="fondo.id">
                                    {{ fondo.nombre }}
                                </option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- BOTONES FILTRO -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <button @click="limpiarFiltros"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm">
                        Limpiar Filtros
                    </button>
                </div>

                <!-- RESUMEN RÁPIDO -->
                <div class="mt-4 pt-4 border-t dark:border-gray-700">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Movimientos: </span>
                            <span class="font-semibold">{{ movimientosFiltrados.length }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Débitos: </span>
                            <span class="font-semibold text-green-600">
                                S/ {{ formatoDinero(resumenMovimientos.totalDeudor) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Créditos: </span>
                            <span class="font-semibold text-red-600">
                                S/ {{ formatoDinero(resumenMovimientos.totalAcreedor) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Diferencia: </span>
                            <span class="font-semibold"
                                :class="resumenMovimientos.diferencia >= 0 ? 'text-green-600' : 'text-red-600'">
                                S/ {{ formatoDinero(resumenMovimientos.diferencia) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLA ÚNICA -->
            <div class="overflow-x-auto max-h-[600px] shadow-lg rounded-lg border dark:border-gray-700">
                <table class="min-w-full text-sm text-left border dark:border-gray-700 bg-white dark:bg-gray-800">
                    <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                        <tr>
                            <th class="p-3">N°</th>
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Medio Pago</th>
                            <th class="p-3">Descripción</th>
                            <th class="p-3">Fondo</th>
                            <th class="p-3 text-right">Deudor</th>
                            <th class="p-3 text-right">Acreedor</th>
                            <th class="p-3 text-right">Saldo</th>
                            <th class="p-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Los movimientos YA VIENEN ORDENADOS POR ID desde el Controller -->
                        <tr v-for="mov in movimientosFiltrados" :key="mov.id"
                            class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">

                            <!-- ID -->
                            <td class="p-3 font-medium text-gray-500 dark:text-gray-400">
                                {{ mov.numero }}
                            </td>

                            <!-- Fecha -->
                            <td class="p-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ formatFechaDisplay(mov.fecha_operacion) }}
                            </td>

                            <!-- Medio Pago -->
                            <td class="p-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ mov.medio_pago || '-' }}
                            </td>

                            <!-- Descripción -->
                            <td class="p-3">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ mov.descripcion }}
                                </div>
                            </td>

                            <!-- Fondo -->
                            <td class="p-3">
                                <Link v-if="mov.subcuenta" :href="`/fondos/${mov.subcuenta.id}/detalle`"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium hover:underline cursor-pointer"
                                    :class="getBadgeColor(mov.subcuenta.nombre)" title="Ver detalle del fondo">
                                    {{ mov.subcuenta.nombre }}
                                </Link>

                                <span v-else class="text-gray-400 text-xs">-</span>
                            </td>


                            <!-- Débito -->
                            <td class="p-3 text-right font-medium whitespace-nowrap">
                                <span v-if="mov.deudor && parseFloat(mov.deudor) > 0" class="text-green-600">
                                    S/ {{ formatoDinero(mov.deudor) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>

                            <!-- Crédito -->
                            <td class="p-3 text-right font-medium whitespace-nowrap">
                                <span v-if="mov.acreedor && parseFloat(mov.acreedor) > 0" class="text-red-600">
                                    S/ {{ formatoDinero(mov.acreedor) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>

                            <!-- Saldo -->
                            <td class="p-3 text-right font-bold whitespace-nowrap">
                                <span :class="parseFloat(mov.saldo) >= 0
                                    ? 'text-green-700 dark:text-green-300'
                                    : 'text-red-700 dark:text-red-300'">
                                    S/ {{ formatoDinero(mov.saldo) }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="p-3">
                                <div class="flex gap-1">
                                    <Link :href="`/movimientos/${mov.id}/edit`"
                                        class="flex items-center justify-center w-8 h-8 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition"
                                        title="Editar">
                                        ✏️
                                    </Link>
                                    <button @click="eliminarMovimiento(mov.id)"
                                        class="flex items-center justify-center w-8 h-8 bg-red-500 text-white rounded-lg hover:bg-red-600 transition"
                                        title="Eliminar">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- SI NO HAY RESULTADOS -->
                        <tr v-if="movimientosFiltrados.length === 0">
                            <td colspan="9" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="text-4xl mb-3">📊</div>
                                <p class="text-lg mb-2">No hay movimientos</p>
                                <p class="text-sm">Intenta cambiar los filtros o crear un nuevo movimiento</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- RESUMEN FINAL -->
            <div class="mt-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Movimientos Filtrados</p>
                        <p class="text-2xl font-bold">{{ movimientosFiltrados.length }}</p>
                        <p class="text-xs text-gray-500 mt-1">de {{ props.movimientos.length }} totales</p>
                    </div>

                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Saldo Inicial</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                            S/ {{ formatoDinero(saldoInicial) }}
                        </p>
                    </div>

                    <div class="text-center p-4 rounded-lg" :class="saldoActual >= 0
                        ? 'bg-green-50 dark:bg-green-900/20'
                        : 'bg-red-50 dark:bg-red-900/20'">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Saldo Actual</p>
                        <p class="text-2xl font-bold" :class="saldoActual >= 0 ? 'text-green-600' : 'text-red-600'">
                            S/ {{ formatoDinero(saldoActual) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";
import Swal from "sweetalert2";

const props = defineProps({
    cuenta: Object,
    movimientos: Array, // Ya viene ordenado por ID desde el Controller
    fondos: Array,
    ultimo_saldo: Number,
});

// Estados reactivos
const filtroFechaDesde = ref("");
const filtroFechaHasta = ref("");
const filtroDescripcion = ref("");
const filtroFondo = ref("");
const recalculando = ref(false);

// Inicializar filtros (último mes por defecto)
onMounted(() => {
    const hoy = new Date();

    filtroFechaDesde.value = '2021-01-01';
    filtroFechaHasta.value = hoy.toISOString().split('T')[0];
});


// Formato dinero
const formatoDinero = (valor) => {
    if (valor === null || valor === undefined || isNaN(valor)) return "0.00";
    const numero = typeof valor === 'string' ? parseFloat(valor) : Number(valor);
    return numero.toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
// Parsear fecha YYYY-MM-DD sin zona horaria
const parseFechaLocal = (fechaString) => {
    if (!fechaString) return null;

    // Nos quedamos solo con YYYY-MM-DD
    const soloFecha = fechaString.substring(0, 10);
    const [year, month, day] = soloFecha.split('-');

    return new Date(
        Number(year),
        Number(month) - 1,
        Number(day)
    );
};

// Formato fecha (DD/MM/YYYY)
const formatFechaDisplay = (fechaString) => {
    if (!fechaString) return "";

    const fecha = parseFechaLocal(fechaString);
    if (!fecha || isNaN(fecha.getTime())) return "";

    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const año = fecha.getFullYear();

    return `${dia}/${mes}/${año}`;
};



// Filtrar movimientos (MANTIENE ORDEN POR ID)
const movimientosFiltrados = computed(() => {
    let filtrados = [...props.movimientos];

    // DESDE
    if (filtroFechaDesde.value) {
        const desde = parseFechaLocal(filtroFechaDesde.value);
        filtrados = filtrados.filter(mov => {
            const fechaMov = parseFechaLocal(mov.fecha_operacion);
            return fechaMov && fechaMov >= desde;
        });
    }

    // HASTA
    if (filtroFechaHasta.value) {
        const hasta = parseFechaLocal(filtroFechaHasta.value);
        filtrados = filtrados.filter(mov => {
            const fechaMov = parseFechaLocal(mov.fecha_operacion);
            return fechaMov && fechaMov <= hasta;
        });
    }

    // DESCRIPCIÓN
    if (filtroDescripcion.value.trim()) {
        const busqueda = filtroDescripcion.value.toLowerCase();
        filtrados = filtrados.filter(mov =>
            (mov.descripcion || '').toLowerCase().includes(busqueda)
        );
    }

    // FONDO
    if (filtroFondo.value) {
        filtrados = filtrados.filter(mov =>
            mov.subcuenta && mov.subcuenta.id == filtroFondo.value
        );
    }

    return filtrados;
});


// Resumen de movimientos
const resumenMovimientos = computed(() => {
    let totalDeudor = 0;
    let totalAcreedor = 0;

    movimientosFiltrados.value.forEach(mov => {
        totalDeudor += parseFloat(mov.deudor || 0);
        totalAcreedor += parseFloat(mov.acreedor || 0);
    });

    return {
        totalDeudor,
        totalAcreedor,
        diferencia: totalDeudor - totalAcreedor,
        movimientosCount: movimientosFiltrados.value.length
    };
});


//una const que ayude a ver los saldos de inicio y fin de cada mez por separado en una pantalla




const saldoInicial = computed(() => {
    if (movimientosFiltrados.value.length === 0) return 0;

    const primerMov = movimientosFiltrados.value[0];

    const deudor = parseFloat(primerMov.deudor || 0);
    const acreedor = parseFloat(primerMov.acreedor || 0);
    const saldo = parseFloat(primerMov.saldo || 0);

    return saldo - deudor + acreedor;
});

const saldoActual = computed(() => {
    if (movimientosFiltrados.value.length === 0) {
        return props.cuenta.saldo_actual || 0;
    }

    const ultimoMov = movimientosFiltrados.value[movimientosFiltrados.value.length - 1];
    return parseFloat(ultimoMov.saldo || 0);
});


// RECALCULAR SALDOS - FUNCIÓN CORREGIDA
const recalcularSaldos = () => {
    Swal.fire({
        title: '¿Recalcular saldos?',
        html: `<div class="text-left">
                <p>Se recalcularán <strong>TODOS</strong> los saldos de la cuenta:</p>
                <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                    <p class="font-bold text-amber-800 dark:text-amber-300">${props.cuenta.nombre}</p>
                    <p class="mt-1">Saldo actual: <span class="font-bold">S/ ${formatoDinero(props.cuenta.saldo_actual)}</span></p>
                </div>
                <p class="text-sm text-amber-600 mt-2">✓ Verificará cada movimiento</p>
                <p class="text-sm text-amber-600">✓ Actualizará saldos acumulados</p>
                <p class="text-sm text-amber-600 mt-2">⏱️ Esta operación puede tomar unos segundos</p>
              </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            recalculando.value = true;

            // Usar Inertia con useForm (maneja CSRF automáticamente)
            const form = useForm({});

            form.post(`/cuentas/${props.cuenta.id}/recalcular`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (response) => {
                    // Obtener datos de la respuesta
                    const flashData = response.props.flash?.data;
                    const successMessage = response.props.flash?.success;

                    if (flashData && flashData.success) {
                        // Mostrar resultado detallado
                        Swal.fire({
                            title: '¡Recálculo completado!',
                            html: `<div class="text-left">
                                    <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                        <p class="font-bold text-lg text-green-800 dark:text-green-300">${flashData.cuenta}</p>
                                        <p class="text-sm mt-1">${flashData.message}</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Nuevo saldo:</p>
                                            <p class="text-lg font-bold text-green-700 dark:text-green-300">
                                                S/ ${formatoDinero(flashData.nuevo_saldo)}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        📅 ${flashData.fecha}
                                    </p>
                                   </div>`,
                            icon: 'success',
                            confirmButtonText: 'Actualizar página',
                            confirmButtonColor: '#10b981',
                            width: '550px'
                        }).then(() => {
                            router.reload({ preserveScroll: true });
                        });
                    } else if (successMessage) {
                        // Mensaje simple de éxito
                        Swal.fire({
                            title: '¡Éxito!',
                            text: successMessage,
                            icon: 'success',
                            confirmButtonText: 'Actualizar'
                        }).then(() => {
                            router.reload({ preserveScroll: true });
                        });
                    } else {
                        // Mensaje genérico
                        Swal.fire({
                            title: '¡Listo!',
                            text: 'Saldos recalculados correctamente',
                            icon: 'success',
                            confirmButtonText: 'Actualizar'
                        }).then(() => {
                            router.reload({ preserveScroll: true });
                        });
                    }
                },
                onError: (errors) => {
                    let mensajeError = 'Ocurrió un error al recalcular los saldos';

                    if (errors.error) {
                        mensajeError = errors.error;
                    } else if (errors.message) {
                        mensajeError = errors.message;
                    }

                    Swal.fire({
                        title: 'Error',
                        html: `<div class="text-left">
                                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="font-bold text-lg text-red-800 dark:text-red-300">Error en recálculo</p>
                                    <p class="mt-2">${mensajeError}</p>
                                </div>
                               </div>`,
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#ef4444',
                    });
                },
                onFinish: () => {
                    recalculando.value = false;
                }
            });
        }
    });
};

// Eliminar movimiento
const eliminarMovimiento = (id) => {
    Swal.fire({
        title: "¿Eliminar movimiento?",
        text: "Esta acción no se puede deshacer",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6b7280",
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).delete(`/movimientos/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: "Eliminado",
                        text: "El movimiento se eliminó correctamente",
                        icon: "success",
                        timer: 1500,
                    });
                },
                onError: () => {
                    Swal.fire({
                        title: "Error",
                        text: "No se pudo eliminar el movimiento",
                        icon: "error",
                    });
                }
            });
        }
    });
};

// Limpiar filtros
const limpiarFiltros = () => {
    const hoy = new Date();
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

    filtroFechaDesde.value = primerDiaMes.toISOString().split('T')[0];
    filtroFechaHasta.value = hoy.toISOString().split('T')[0];
    filtroDescripcion.value = "";
};


// Paleta de colores para badges
const colorPalettes = [
    "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
    "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300",
    "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300",
    "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300",
    "bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300",
    "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
    "bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300",
    "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300",
    "bg-lime-100 text-lime-800 dark:bg-lime-900/30 dark:text-lime-300",
    "bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300",
    "bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300",
    "bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300",
    "bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300",
    "bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300",
    "bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/30 dark:text-fuchsia-300",
    "bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300",
    "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300",
    "bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300",
    "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300",
];

const getBadgeColor = (name) => {
    if (!name) return colorPalettes[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colorPalettes[Math.abs(hash) % colorPalettes.length];
};

// Validar fechas
watch([filtroFechaDesde, filtroFechaHasta], ([desde, hasta]) => {
    if (desde && hasta && new Date(desde) > new Date(hasta)) {
        Swal.fire({
            title: 'Fecha inválida',
            text: 'La fecha "Desde" no puede ser mayor que la fecha "Hasta"',
            icon: 'warning',
            timer: 3000,
            toast: true,
            position: 'top-end',
            showConfirmButton: false
        });
        filtroFechaDesde.value = "";
    }
});
</script>