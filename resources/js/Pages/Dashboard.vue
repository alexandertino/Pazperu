<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3'
const user = usePage().props.auth.user
</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Panel de Administrador
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- Título -->
                        <h1 class="text-4xl font-bold mb-8 text-center">Panel</h1>

                        <!-- Contenedor de botones -->
                        <div class="flex justify-center">
                            <div class="flex flex-wrap gap-6">
                                <a href="/CrearProyecto"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700 transition"
                                    v-if="user.role === 'admin'">
                                    Crear Proyecto
                                </a>

                                <a href="/proyectos"
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 transition">
                                    Proyectos
                                </a>

                                <Link :href="route('reportes.index')"
                                    class="px-6 py-3 bg-purple-600 text-white rounded-lg shadow-md hover:bg-purple-700 transition"
                                    v-if="user.role === 'admin'">
                                Ver Reportes
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
