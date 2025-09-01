<script setup>
defineProps({
  logs: Array
})
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Reportes de Actividad
            </h2>
        </template>

        <div class="p-6">
            <table class="min-w-full border border-gray-300">
            <thead class="bg-gray-100">
                <tr>
                <th class="px-4 py-2">Usuario</th>
                <th class="px-4 py-2">Acción</th>
                <th class="px-4 py-2">Modelo</th>
                <th class="px-4 py-2">Cambios</th>
                <th class="px-4 py-2">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logs" :key="log.id" class="border-t dark:text-white">
                <td class="px-4 py-2">{{ log.user?.name }}</td>
                <td class="px-4 py-2">{{ log.action }}</td>
                <td class="px-4 py-2">{{ log.model }}</td>
                <td class="px-4 py-2">
                    <pre class="text-xs">{{ log.changes }}</pre>
                </td>
                <td class="px-4 py-2">{{ new Date(log.created_at).toLocaleString() }}</td>
                </tr>
            </tbody>
            </table>
        </div>
  </AuthenticatedLayout>
</template>
