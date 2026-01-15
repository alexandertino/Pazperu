<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

/*
|--------------------------------------------------------------------------
| PROPS (OBLIGATORIO)
|--------------------------------------------------------------------------
| Cada fondo DEBE traer:
| - saldo_inicial
| - movimientos[] { numero, deudor, acreedor }
*/
const props = defineProps({
    fondos: {
        type: Array,
        required: true,
    },
});

/*
|--------------------------------------------------------------------------
| FORMATO MONEDA (PERÚ)
|--------------------------------------------------------------------------
*/
const formatoDinero = (valor) => {
    const n = Number(valor);
    return isNaN(n)
        ? "0.00"
        : n.toLocaleString("es-PE", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};

/*
|--------------------------------------------------------------------------
| 🔥 ECUACIÓN MATEMÁTICA PURA (NO LEE BD)
|--------------------------------------------------------------------------
| saldo = saldo_inicial
| saldo = saldo + deudor - acreedor
*/
const saldoMatematico = (saldoInicial, movimientos) => {
    let saldo = Number(saldoInicial ?? 0);

    if (!Array.isArray(movimientos)) {
        return saldo;
    }

    // ORDEN CONTABLE REAL (OBLIGATORIO)
    const ordenados = [...movimientos].sort((a, b) => {
        return Number(a.numero ?? 0) - Number(b.numero ?? 0);
    });

    for (const m of ordenados) {
        saldo += Number(m.deudor ?? 0);
        saldo -= Number(m.acreedor ?? 0);
    }

    return saldo;
};

/*
|--------------------------------------------------------------------------
| AGRUPAR FONDOS POR CUENTA PRINCIPAL
|--------------------------------------------------------------------------
*/
const fondosPorCuenta = () => {
    const grupos = {};

    props.fondos.forEach((f) => {
        const cuentaNombre = f.cuenta?.nombre ?? "Sin cuenta";

        if (!grupos[cuentaNombre]) {
            grupos[cuentaNombre] = [];
        }

        grupos[cuentaNombre].push(f);
    });

    return grupos;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Fondos" />

        <div class="p-8 max-w-4xl mx-auto">
            <!-- HEADER -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold dark:text-white">
                    Fondos
                </h1>

                <Link
                    href="/fondos/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
                >
                    ➕ Crear Fondo
                </Link>
            </div>

            <!-- TABLA -->
            <div class="overflow-x-auto shadow rounded-lg">
                <table class="min-w-full border dark:border-gray-600 bg-white dark:bg-gray-800">
                    <thead class="bg-gray-200 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">Nombre</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="(lista, cuentaNombre) in fondosPorCuenta()"
                            :key="cuentaNombre"
                        >
                            <!-- CUENTA -->
                            <tr class="bg-gray-100 dark:bg-gray-900">
                                <td colspan="3" class="px-3 py-2 font-bold">
                                    {{ cuentaNombre }}
                                </td>
                            </tr>

                            <!-- FONDOS -->
                            <tr
                                v-for="f in lista"
                                :key="f.id"
                                class="border-t hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td class="px-3 py-2 font-medium pl-6">
                                    {{ f.nombre }}
                                </td>


                                <td class="px-3 py-2 text-right space-x-3">
                                    <Link
                                        :href="`/fondos/${f.id}/detalle`"
                                        class="text-green-600 hover:underline"
                                    >
                                        Detalle
                                    </Link>

                                    <Link
                                        :href="`/fondos/${f.id}/edit`"
                                        class="text-blue-600 hover:underline"
                                    >
                                        Editar
                                    </Link>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
