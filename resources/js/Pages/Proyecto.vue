<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// Datos de prueba por ahora
const proyectos = ref([
    {
        id: 1,
        nombre: 'Proyecto Alpha',
        estado: 'En Proceso',
        descripcion: 'Primera fase del sistema',
        fecha_inicio: '2024-05-01',
        fecha_fin: '2024-08-01',
    },
    {
        id: 2,
        nombre: 'Proyecto Beta',
        estado: 'Terminado',
        descripcion: 'Plataforma interna',
        fecha_inicio: '2024-01-15',
        fecha_fin: '2024-04-30',
    },
]);

function editarProyecto(id) {
    alert(`Editar proyecto con ID: ${id}`);
}

import { router } from '@inertiajs/vue3';

function irAInventarioSalidas() {
    router.visit('/proyectos/inventario-salidas');
}

</script>

<template>
    <Head title="Proyectos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Lista de Proyectos
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">


                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left border dark:border-gray-700">
                                <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                                    <tr>
                                        <th class="p-3">Nombre</th>
                                        <th class="p-3">Estado</th>
                                        <th class="p-3">Descripción</th>
                                        <th class="p-3">Inicio</th>
                                        <th class="p-3">Fin</th>
                                        <th class="p-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(proyecto, index) in proyectos"
                                        :key="index"
                                        class="border-t dark:border-gray-600"
                                    >
                                    
                                        <td class="p-3">{{ proyecto.nombre }}</td>
                                        <td class="p-3">{{ proyecto.estado }}</td>
                                        <td class="p-3">{{ proyecto.descripcion }}</td>
                                        <td class="p-3">{{ proyecto.fecha_inicio }}</td>
                                        <td class="p-3">{{ proyecto.fecha_fin }}</td>
                                        <td class="p-3 space-x-2">
                                            <button
                                                @click="irAInventarioSalidas"
                                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                            >
                                                Ver Inventario y Salidas
                                            </button>
                                        </td>
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
