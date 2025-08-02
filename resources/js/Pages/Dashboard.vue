<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Panel de de Administrador
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h1 class="text-4xl font-bold mb-6 text-center">Panel</h1>

                        <div class="space-x-4 center">
                            <a href="/CrearProyecto" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Crear Proyecto
                            </a>
                            <a href="/Proyecto" class="inline-block px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                Proyectos
                            </a>
                            <a href="/reportes" class="inline-block px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                                Ver Reportes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

</template>
