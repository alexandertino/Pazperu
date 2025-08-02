<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// Lista de tareas (datos de ejemplo)
const tareas = ref([
    {
        id: 1,
        titulo: 'Diseñar interfaz',
        estado: 'En proceso',
        creado_por: 'juan.perez@example.com',
        fecha_creacion: '2025-08-01',
    },
    {
        id: 2,
        titulo: 'Revisar documentos',
        estado: 'Completada',
        creado_por: 'maria.lopez@example.com',
        fecha_creacion: '2025-07-25',
    },
    {
        id: 3,
        titulo: 'Preparar reporte mensual',
        estado: 'Pendiente',
        creado_por: 'carlos.gomez@example.com',
        fecha_creacion: '2025-07-28',
    },
]);
</script>

<template>
    <Head title="Tareas" />

    <AuthenticatedLayout>
        <!-- Encabezado principal -->
        <template #header>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                📋 Lista de Tareas
            </h2>
        </template>

        <!-- Fondo de la página -->
        <div class="py-12 bg-gray-100 dark:bg-gray-900 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-md sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- Subtítulo -->
                        <h3 class="text-lg font-semibold mb-4">
                            Tareas registradas en el sistema
                        </h3>

                        <!-- Tabla de tareas -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left border dark:border-gray-700">
                                <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                                    <tr>
                                        <th class="p-3">Título</th>
                                        <th class="p-3">Estado</th>
                                        <th class="p-3">Creado por</th>
                                        <th class="p-3">Fecha de creación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="tarea in tareas"
                                        :key="tarea.id"
                                        class="border-t dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <td class="p-3">{{ tarea.titulo }}</td>
                                        <td class="p-3">
                                            <span
                                                class="px-2 py-1 rounded"
                                                :class="{
                                                    'bg-blue-100 text-blue-700': tarea.estado === 'En proceso',
                                                    'bg-green-100 text-green-700': tarea.estado === 'Completada',
                                                    'bg-yellow-100 text-yellow-700': tarea.estado === 'Pendiente'
                                                }"
                                            >
                                                {{ tarea.estado }}
                                            </span>
                                        </td>
                                        <td class="p-3">{{ tarea.creado_por }}</td>
                                        <td class="p-3">{{ tarea.fecha_creacion }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
