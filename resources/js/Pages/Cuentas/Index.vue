<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    cuentas: Array
});

const eliminarCuenta = (id) => {
    if (!confirm("¿Seguro que deseas eliminar esta cuenta?")) return;

    router.delete(`/cuentas/${id}`);
};

const verfondos = (cuentaId) => {
    router.visit(`/cuentas/${cuentaId}/fondos`);
};  

</script>

<template>
    <AuthenticatedLayout>

        <Head title="Cuentas Generales" />

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Título y botón -->
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                        Cuentas Generales
                    </h1>

                    <div class="flex space-x-3">
                        <Link :href="`/proyectos`"
                            class="bg-gray-500 text-white px-3 py-2 rounded-lg shadow hover:bg-gray-600 transition duration-150 ease-in-out text-sm">
                        Atrás
                        </Link>

                        <Link href="/cuentas/create"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition">
                        + Nueva Cuenta
                        </Link>
                    </div>
                </div>

                <!-- Tabla / Contenedor -->
                <div
                    class="overflow-x-auto bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4 border dark:border-gray-700">

                    <!-- Si no hay cuentas -->
                    <div v-if="cuentas.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-300">
                        No hay cuentas registradas.
                    </div>

                    <!-- Tabla de cuentas -->
                    <table v-else class="min-w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                <th class="px-4 py-3 text-left">Nombre</th>
                                <th class="px-4 py-3 text-left">Descripción</th>
                                <th class="px-4 py-3 text-left">Saldo Inicial</th>
                                <th class="px-4 py-3 text-left">Último Saldo</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="cuenta in cuentas" :key="cuenta.id"
                                class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-4 py-3 font-medium dark:text-gray-100">
                                    {{ cuenta.nombre }}
                                </td>

                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ cuenta.descripcion ?? '—' }}
                                </td>

                                <td class="px-4 py-3 font-semibold dark:text-gray-200">
                                    S/ {{ Number(cuenta.saldo_inicial).toFixed(2) }}
                                </td>

                                <td class="px-4 py-3 font-semibold dark:text-gray-200">
                                    S/ {{ Number(cuenta.saldo_actual ?? 0).toFixed(2) }}
                                </td>

                                <!-- Acciones -->
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">

                                    <!-- Ver libro -->
                                    <Link :href="`/cuentas/${cuenta.id}`"
                                        class="px-3 py-2 rounded bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition">
                                    Libro
                                    </Link>

                                    <!-- Ver fondos -->
                                    <Link :href="`/cuentas/${cuenta.id}/fondos`"
                                        class="px-3 py-2 rounded bg-yellow-500 text-white text-sm font-medium hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-700 transition">
                                    Fondos
                                    </Link>

                                    <!-- Editar -->
                                    <Link :href="`/cuentas/${cuenta.id}/edit`"
                                        class="px-3 py-2 rounded bg-gray-600 text-white text-sm font-medium hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600 transition">
                                    Editar
                                    </Link>

                                    <!-- Eliminar -->
                                    <button @click="eliminarCuenta(cuenta.id)"
                                        class="px-3 py-2 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 transition">
                                        Eliminar
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
