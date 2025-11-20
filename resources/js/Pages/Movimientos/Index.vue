<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    movimientos: Array
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Movimientos" />

        <div class="py-8 max-w-6xl mx-auto">

            <div class="mb-6 flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                    Movimientos Bancarios
                </h1>

                <Link
                    href="/movimientos/create"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                >
                    Registrar Movimiento
                </Link>
            </div>

            <div class="overflow-x-auto shadow-lg rounded-lg border dark:border-gray-700">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 border dark:border-gray-600">ID</th>
                            <th class="px-4 py-3 border dark:border-gray-600">Fecha</th>
                            <th class="px-4 py-3 border dark:border-gray-600">Medio de Pago</th>
                            <th class="px-4 py-3 border dark:border-gray-600">Descripción</th>
                            <th class="px-4 py-3 border dark:border-gray-600 text-right">Deudor</th>
                            <th class="px-4 py-3 border dark:border-gray-600 text-right">Acreedor</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr
                            v-for="m in movimientos"
                            :key="m.id"
                            class="hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200"
                        >
                            <td class="border px-4 py-2 dark:border-gray-700">{{ m.id }}</td>
                            <td class="border px-4 py-2 dark:border-gray-700">{{ m.fecha_operacion }}</td>
                            <td class="border px-4 py-2 dark:border-gray-700">{{ m.medio_pago }}</td>
                            <td class="border px-4 py-2 dark:border-gray-700">{{ m.descripcion }}</td>
                            <td class="border px-4 py-2 dark:border-gray-700 text-right">
                                {{ m.deudor ? `S/ ${Number(m.deudor).toFixed(2)}` : "" }}
                            </td>
                            <td class="border px-4 py-2 dark:border-gray-700 text-right">
                                {{ m.acreedor ? `S/ ${Number(m.acreedor).toFixed(2)}` : "" }}
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
