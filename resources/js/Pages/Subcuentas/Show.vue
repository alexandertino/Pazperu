<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    subcuenta: Object,
    movimientos: Array,
});

const mesSeleccionado = ref("");
const ordenInvertido = ref(false);

// 🔹 Formato dinero
const formatoDinero = (n) =>
    Number(n).toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// 🔹 Formato fecha
const formatoFecha = (f) => new Date(f).toISOString().split("T")[0];

// 🔹 Invertir array sin mutar
const invertirArray = (arr) => [...arr].reverse();

/**
 * ✅ ORDEN CONTABLE REAL
 * 👉 POR NUMERO DE CUENTA PRINCIPAL
 */
const movimientosOrdenBase = computed(() => {
    return [...props.movimientos].sort((a, b) => {
        const na = Number(a.numero ?? 0);
        const nb = Number(b.numero ?? 0);
        return na - nb;
    });
});

/**
 * ✅ MOVIMIENTOS CALCULADOS
 */
const movimientosCalculados = computed(() => {
    let lista = [];
    let saldo = Number(props.subcuenta.saldo_inicial ?? 0);

    // Saldo inicial
    lista.push({
        id: "saldo-inicial",
        numero_fondo: 0,
        numero_principal: "-",
        fecha: formatoFecha(props.subcuenta.created_at),
        descripcion: "Saldo Inicial",
        deudor: 0,
        acreedor: 0,
        saldo,
    });

    movimientosOrdenBase.value.forEach((m, i) => {
        const d = Number(m.deudor ?? 0);
        const a = Number(m.acreedor ?? 0);

        saldo = saldo + d - a;

        lista.push({
            ...m,
            numero_fondo: i + 1,          // Nº dentro del fondo
            numero_principal: m.numero,  // Nº REAL de cuenta principal
            fecha: formatoFecha(m.fecha_operacion),
            saldo,
        });
    });

    return lista;
});

// 🔹 Filtro por mes
const movimientosFiltrados = computed(() => {
    if (!mesSeleccionado.value) return movimientosCalculados.value;

    return movimientosCalculados.value.filter((m) =>
        m.fecha?.startsWith(mesSeleccionado.value)
    );
});

// 🔹 Orden normal / invertido
const movimientosOrdenados = computed(() => {
    return ordenInvertido.value
        ? invertirArray(movimientosFiltrados.value)
        : movimientosFiltrados.value;
});

// 🔹 Saldo actual
const saldoActual = computed(() => {
    const lista = movimientosCalculados.value;
    return lista.length ? lista[lista.length - 1].saldo : 0;
});

// 🔹 NUEVO: Total Deudor
const totalDeudor = computed(() => {
    return movimientosCalculados.value.reduce((sum, m) => {
        return sum + Number(m.deudor || 0);
    }, 0);
});

// 🔹 NUEVO: Total Acreedor
const totalAcreedor = computed(() => {
    return movimientosCalculados.value.reduce((sum, m) => {
        return sum + Number(m.acreedor || 0);
    }, 0);
});

// 🔹 NUEVO: Diferencia (Deudor - Acreedor)
const diferenciaTotales = computed(() => {
    return totalDeudor.value - totalAcreedor.value;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Fondo - ${subcuenta.nombre}`" />

        <div class="p-8 flex justify-center">
            <div class="w-full max-w-5xl">
                <h1 class="text-2xl font-bold mb-4 text-center text-gray-800 dark:text-gray-200">
                    Movimientos del Fondo: {{ subcuenta.nombre }}
                </h1>

                <!-- TARJETAS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-purple-600">
                        <h2 class="text-sm text-gray-500 dark:text-gray-400">
                            Saldo Inicial
                        </h2>
                        <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">
                            S/ {{ formatoDinero(subcuenta.saldo_inicial ?? 0) }}
                        </p>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-green-600">
                        <h2 class="text-sm text-gray-500 dark:text-gray-400">
                            Saldo Actual
                        </h2>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                            S/ {{ formatoDinero(saldoActual) }}
                        </p>
                    </div>

                    <!-- NUEVOS CUADROS -->
                    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-blue-600">
                        <h2 class="text-sm text-gray-500 dark:text-gray-400">
                            Total Deudor
                        </h2>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            S/ {{ formatoDinero(totalDeudor) }}
                        </p>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-red-600">
                        <h2 class="text-sm text-gray-500 dark:text-gray-400">
                            Total Acreedor
                        </h2>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            S/ {{ formatoDinero(totalAcreedor) }}
                        </p>
                    </div>
                </div>

                <!-- CONTROLES -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Filtrar por mes:
                        </label>

                        <input
                            v-model="mesSeleccionado"
                            type="month"
                            class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                        />

                        <button
                            v-if="mesSeleccionado"
                            @click="mesSeleccionado = ''"
                            class="px-3 py-2 text-sm bg-gray-500 text-white rounded hover:bg-gray-600"
                        >
                            Limpiar filtro
                        </button>
                    </div>

                    <button
                        @click="ordenInvertido = !ordenInvertido"
                        class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700"
                    >
                        {{ ordenInvertido ? "Orden normal" : "Invertir orden" }}
                    </button>
                </div>

                <!-- TABLA -->
                <div class="overflow-x-auto shadow-lg rounded-lg mb-6">
                    <table class="w-full border border-gray-300 dark:border-gray-700">
                        <thead class="bg-purple-600 text-white">
                            <tr>
                                <th class="p-2">N° Cuenta</th>
                                <th class="p-2">N° Fondo</th>
                                <th class="p-2">Fecha</th>
                                <th class="p-2">Descripción</th>
                                <th class="p-2">Deudor</th>
                                <th class="p-2">Acreedor</th>
                                <th class="p-2">Saldo</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="m in movimientosOrdenados"
                                :key="m.id"
                                class="border dark:border-gray-700 bg-white dark:bg-gray-800"
                            >
                                <td class="p-2 text-center text-gray-600 dark:text-gray-400">
                                    {{ m.numero_principal }}
                                </td>

                                <td class="p-2 text-center font-semibold">
                                    {{ m.numero_fondo }}
                                </td>

                                <td class="p-2 text-center">{{ m.fecha }}</td>

                                <td
                                    class="p-2 font-semibold"
                                    :class="m.numero_fondo === 0 ? 'text-purple-700 dark:text-purple-300' : ''"
                                >
                                    {{ m.descripcion }}
                                </td>

                                <td class="p-2 text-right text-green-600 dark:text-green-400">
                                    <span v-if="Number(m.deudor) > 0">
                                        S/ {{ formatoDinero(m.deudor) }}
                                    </span>
                                    <span v-else>-</span>
                                </td>

                                <td class="p-2 text-right text-red-600 dark:text-red-400">
                                    <span v-if="Number(m.acreedor) > 0">
                                        S/ {{ formatoDinero(m.acreedor) }}
                                    </span>
                                    <span v-else>-</span>
                                </td>

                                <td class="p-2 text-right font-bold dark:text-gray-200">
                                    S/ {{ formatoDinero(m.saldo) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- NUEVA SECCIÓN: RESUMEN DE TOTALES -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg shadow border">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                            Resumen de Totales
                        </h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-blue-600 dark:text-blue-400">Total Deudor:</span>
                                <span class="font-bold text-blue-600 dark:text-blue-400">
                                    S/ {{ formatoDinero(totalDeudor) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-red-600 dark:text-red-400">Total Acreedor:</span>
                                <span class="font-bold text-red-600 dark:text-red-400">
                                    S/ {{ formatoDinero(totalAcreedor) }}
                                </span>
                            </div>
                            <div class="pt-2 border-t dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Diferencia:</span>
                                    <span 
                                        class="font-bold"
                                        :class="diferenciaTotales >= 0 ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400'"
                                    >
                                        S/ {{ formatoDinero(diferenciaTotales) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>