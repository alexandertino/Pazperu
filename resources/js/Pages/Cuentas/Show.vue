<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import Swal from "sweetalert2";

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
});

// Formato dinero
const formatoDinero = (n) =>
    Number(n).toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// Formato fecha
const formatoFecha = (f) => {
    if (!f) return "";
    return new Date(f).toISOString().split("T")[0];
};

// Cálculo del saldo acumulado
const movimientosCalculados = computed(() => {
    let saldo = Number(props.cuenta.saldo_inicial);
    let lista = [];

    // Agregar fila del saldo inicial
    lista.push({
        id: "saldo_inicial",
        esInicial: true,
        numero: "",
        fecha: "",
        medio_pago: "",
        descripcion: "Saldo Inicial",
        deudor: "",
        acreedor: "",
        saldo: saldo,
    });

    // Agregar movimientos reales
    props.movimientos.forEach((m, index) => {
        const deudor = Number(m.deudor ?? 0);
        const acreedor = Number(m.acreedor ?? 0);

        saldo = saldo + deudor - acreedor;

        lista.push({
            ...m,
            esInicial: false,
            numero: index + 1,
            fecha: formatoFecha(m.fecha_operacion),
            saldo: saldo,
        });
    });

    return lista;
});

// Eliminar
const eliminar = (id) => {
    Swal.fire({
        title: "¿Eliminar movimiento?",
        text: "Esta acción no se puede deshacer",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).delete(`/movimientos/${id}`);
        }
    });
};

const colorPalettes = [
    "bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900/50 dark:text-purple-300 dark:hover:bg-purple-800",
    "bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-300 dark:hover:bg-blue-800",
    "bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-300 dark:hover:bg-emerald-800",
    "bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/50 dark:text-amber-300 dark:hover:bg-amber-800",
    "bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-800",
    "bg-cyan-100 text-cyan-800 hover:bg-cyan-200 dark:bg-cyan-900/50 dark:text-cyan-300 dark:hover:bg-cyan-800",
];

const getBadgeColor = (name) => {
    if (!name) return colorPalettes[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colorPalettes[Math.abs(hash) % colorPalettes.length];
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Libro - ${cuenta.nombre}`" />

        <div class="py-10 max-w-7xl mx-auto">

            <!-- TÍTULO -->
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {{ cuenta.nombre }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        Libro de Bancos - Cuenta Corriente
                    </p>
                </div>

                <div class="flex space-x-3">
                    <Link
                        :href="`/cuentas`"
                        class="bg-gray-500 text-white px-3 py-2 rounded-lg shadow hover:bg-gray-600 transition">
                        Atrás
                    </Link>

                    <Link
                        :href="`/cuentas/${cuenta.id}/fondos`"
                        class="bg-green-600 text-white px-3 py-2 rounded-lg shadow hover:bg-green-700 transition">
                        Ver Fondos
                    </Link>

                    <Link
                        :href="`/movimientos/create?cuenta=${cuenta.id}`"
                        class="bg-blue-600 text-white px-3 py-2 rounded-lg shadow hover:bg-blue-700 transition font-semibold">
                        Nuevo Movimiento
                    </Link>
                </div>
            </div>

            <!-- TABLA -->
            <div class="overflow-x-auto shadow-lg rounded-lg border dark:border-gray-700">
                <table class="min-w-full border-collapse border border-gray-300 dark:border-gray-700">

                    <!-- ENCABEZADOS CORREGIDOS -->
                    <thead class="bg-blue-500 text-white">
                        <tr>
                            <th rowspan="2" class="border px-4 py-2">Nº</th>
                            <th rowspan="2" class="border px-4 py-2">FECHA</th>
                            <th colspan="2" class="border px-4 py-2">OPERACIONES BANCARIAS</th>

                            <!-- 👇 AQUÍ SE ARREGLÓ colspan="4" -->
                            <th colspan="4" class="border px-4 py-2">SALDOS Y MOVIMIENTOS</th>
                        </tr>

                        <tr>
                            <th class="border px-4 py-2">MEDIO DE PAGO</th>
                            <th class="border px-4 py-2">DESCRIPCIÓN</th>
                            <th class="border px-4 py-2">DEUDOR</th>
                            <th class="border px-4 py-2">ACREEDOR</th>
                            <th class="border px-4 py-2">SALDOS</th>
                            <th class="border px-4 py-2">ACCIONES</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="mov in movimientosCalculados"
                            :key="mov.numero + mov.id"
                            :class="{
                                'bg-gray-50 dark:bg-gray-800': mov.esInicial,
                                'hover:bg-gray-100 dark:hover:bg-gray-700': !mov.esInicial
                            }"
                            class="dark:text-gray-200">

                            <td class="border px-4 py-2 text-center">
                                {{ mov.esInicial ? '' : mov.numero }}
                            </td>

                            <td class="border px-4 py-2 text-center">
                                {{ mov.fecha }}
                            </td>

                            <td class="border px-4 py-2 text-center">
                                {{ mov.medio_pago ?? '' }}
                            </td>

                            <td class="border px-4 py-3">
                                <div class="text-sm font-medium">
                                    {{ mov.descripcion }}
                                </div>

                                <div v-if="mov.subcuenta" class="mt-1.5">
                                    <Link
                                        :href="`/fondos/${mov.subcuenta_id}/detalle`"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getBadgeColor(mov.subcuenta.nombre)">
                                        <span class="opacity-75 mr-1">Fondo:</span>
                                        <span>{{ mov.subcuenta?.nombre }}</span>
                                    </Link>
                                </div>
                            </td>

                            <td class="border px-4 py-2 text-green-600 font-semibold">
                                <span v-if="mov.deudor">S/ {{ formatoDinero(mov.deudor) }}</span>
                            </td>

                            <td class="border px-4 py-2 text-red-600 font-semibold">
                                <span v-if="mov.acreedor">S/ {{ formatoDinero(mov.acreedor) }}</span>
                            </td>

                            <td class="border px-4 py-2 font-bold">
                                S/ {{ formatoDinero(mov.saldo) }}
                            </td>

                            <td class="border px-4 py-2 text-center flex items-center justify-center gap-2">

                                <Link
                                    v-if="!mov.esInicial"
                                    :href="`/movimientos/${mov.id}/edit`"
                                    class="w-8 h-8 flex items-center justify-center rounded-md bg-gray-600 text-white hover:bg-gray-700"
                                    title="Editar">
                                    ✏️
                                </Link>

                                <button
                                    v-if="!mov.esInicial"
                                    @click="eliminar(mov.id)"
                                    class="w-8 h-8 flex items-center justify-center rounded-md bg-red-600 text-white hover:bg-red-700"
                                    title="Eliminar">
                                    🗑️
                                </button>

                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
