<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
const user = usePage().props.auth.user;
const { props } = usePage();
const proyectos = props.proyectos || [];

function irAInventarioSalidas(id) {
    if (!id) return;
    router.visit(`/proyectos/${id}/inventario-salidas`);
}
// en tu <script setup>
const irGestionMeta = () => {
    window.location.href = `/inventario/meta/manage`;
};

</script>

<template>

    <Head title="Proyectos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 tracking-wide">
                        📂 Lista de Proyectos
                    </h2>
                </div>
                <div>
                    <button  v-if="user.role === 'admin'" type="button" @click="irGestionMeta"
                        class="px-4 py-2 bg-yellow-400 text-white rounded-lg shadow hover:bg-yellow-600 focusable">
                        ⚙️  
                    </button>
                </div>
            </div>

        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white dark:bg-gray-900 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left border-collapse rounded-lg overflow-hidden">
                                <thead>
                                    <tr class="bg-gradient-to-r text-white">
                                        <th class="px-4 py-3 font-semibold">Nombre</th>
                                        <th class="px-4 py-3 font-semibold">Estado</th>
                                        <th class="px-4 py-3 font-semibold">Descripción</th>
                                        <th class="px-4 py-3 font-semibold">Inicio</th>
                                        <th class="px-4 py-3 font-semibold">Fin</th>
                                        <th class="px-4 py-3 font-semibold text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Si no hay proyectos mostramos mensaje -->
                                    <tr v-if="!proyectos || proyectos.length === 0" class="border-b last:border-0">
                                        <td class="px-4 py-6 text-center text-gray-600 dark:text-gray-300" :colspan="6">
                                            Aún no se crearon proyectos
                                        </td>
                                    </tr>

                                    <!-- Filas de proyectos -->
                                    <tr v-else v-for="(proyecto) in proyectos" :key="proyecto.id ?? proyecto.nombre"
                                        class="border-b last:border-0 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                        @click="irAInventarioSalidas(proyecto.id)"
                                        @keydown.enter.prevent="irAInventarioSalidas(proyecto.id)" tabindex="0">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-200">
                                            {{ proyecto.nombre }}
                                        </td>
                                        <td>
                                            <span class="px-3 py-1 text-xs font-medium rounded-full" :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200': proyecto.estado === 'Activo',
                                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200': proyecto.estado === 'En Progreso',
                                                'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200': proyecto.estado === 'Finalizado',
                                            }">
                                                {{ proyecto.estado }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                            {{ proyecto.descripcion }}
                                        </td>
                                        <td class="px-4 py-3">{{ proyecto.fecha_inicio }}</td>
                                        <td class="px-4 py-3">{{ proyecto.fecha_fin }}</td>
                                        <td class="px-4 py-3 flex justify-center">
                                            <!-- Evitamos que el click del botón burbujee al <tr> -->
                                            <button @click.stop="irAInventarioSalidas(proyecto.id)"
                                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 active:scale-95 transition">
                                                Ver Proyecto
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
