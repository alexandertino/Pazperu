<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    subcuenta: Object,
    movimientos: Array,
});

// Formato dinero
const formatoDinero = (n) =>
    Number(n).toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// Formato fecha
const formatoFecha = (f) => new Date(f).toISOString().split("T")[0];

// Calcular nuevos movimientos
const movimientosCalculados = computed(() => {
    let lista = [];

    // 🔹 1) Agregar la fila del saldo inicial
    lista.push({
        id: "saldo-inicial",
        numero: 0,
        fecha: formatoFecha(props.subcuenta.created_at),
        descripcion: "Saldo Inicial",
        deudor: null,
        acreedor: null,
        saldo: Number(props.subcuenta.saldo_inicial ?? 0),
    });

    // 🔹 2) Calcular movimientos reales
    let saldo = Number(props.subcuenta.saldo_inicial ?? 0);

    props.movimientos.forEach((m, i) => {
        const d = Number(m.deudor ?? 0);
        const a = Number(m.acreedor ?? 0);

        saldo = saldo + d - a;

        lista.push({
            ...m,
            numero: i + 1,
            fecha: formatoFecha(m.fecha_operacion),
            saldo,
        });
    });

    return lista;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Fondo - ${subcuenta.nombre}`" />

        <div class="p-8 flex justify-center">

            <div class="w-full max-w-5xl">
                
                <h1 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-200 text-center">
                    Movimientos del Fondo: {{ subcuenta.nombre }}
                </h1>

                <div class="overflow-x-auto shadow-lg rounded-lg">
                    <table class="w-full border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                        <thead class="bg-purple-600 text-white">
                            <tr>
                                <th class="p-2">Nº</th>
                                <th class="p-2">Fecha</th>
                                <th class="p-2">Descripción</th>
                                <th class="p-2">Deudor</th>
                                <th class="p-2">Acreedor</th>
                                <th class="p-2">Saldo</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="m in movimientosCalculados" :key="m.id"
                                class="border dark:border-gray-700 bg-white dark:bg-gray-800">
                                
                                <td class="p-2 text-center">{{ m.numero }}</td>
                                <td class="p-2 text-center">{{ m.fecha }}</td>
                                <td class="p-2 font-semibold" :class="m.numero === 0 ? 'text-purple-700 dark:text-purple-300' : ''">
                                    {{ m.descripcion }}
                                </td>

                                <td class="p-2 text-green-600 dark:text-green-400 text-right">
                                    <span v-if="m.deudor">S/ {{ formatoDinero(m.deudor) }}</span>
                                </td>

                                <td class="p-2 text-red-600 dark:text-red-400 text-right">
                                    <span v-if="m.acreedor">S/ {{ formatoDinero(m.acreedor) }}</span>
                                </td>

                                <td class="p-2 font-bold text-right dark:text-gray-200">
                                    S/ {{ formatoDinero(m.saldo) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
