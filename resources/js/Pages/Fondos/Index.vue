<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, Head } from "@inertiajs/vue3";

const props = defineProps({
    fondos: Array,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Fondos" />

        <div class="p-8 max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold dark:text-white">Fondos</h1>
                <div class="flex space-x-3">
                    <Link
                        :href="`/proyectos`"
                        class="bg-gray-500 text-white px-3 py-2 rounded-lg shadow hover:bg-gray-600 transition duration-150 ease-in-out text-sm"
                        >
                        Atrás
                    </Link>
                    <Link
                        href="/fondos/create"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Crear Fondo
                    </Link>
                </div>
            </div>

            <table class="min-w-full border dark:border-gray-600">
                <thead class="bg-gray-200 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left dark:text-gray-200">Nombre</th>
                        <th class="px-3 py-2 text-left dark:text-gray-200">Cuenta Principal</th>
                        <th class="px-3 py-2 text-right dark:text-gray-200">Saldo Actual</th>
                        <th class="px-3 py-2 dark:text-gray-200"></th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="f in fondos" :key="f.id" class="border-t dark:border-gray-700">
                        <td class="px-3 py-2 dark:text-gray-300">{{ f.nombre }}</td>
                        <td class="px-3 py-2 dark:text-gray-300">{{ f.cuenta?.nombre ?? 'Sin cuenta' }}</td>
                        <td class="px-3 py-2 text-right dark:text-gray-300">
                            S/ {{ Number(f.saldo_inicial).toFixed(2) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="`/fondos/${f.id}/edit`"
                                class="text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Editar
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
