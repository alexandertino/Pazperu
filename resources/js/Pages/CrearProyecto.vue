<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Formulario reactivo
const form = reactive({
    nombre: '',
    estado: 'En Proceso',
    descripcion: '',
    fecha_inicio: '',
})

// Enviar formulario
function handleSubmit() {
    router.post('/crear', form)
}
</script>

<template>
    <Head title="Crear Proyecto" />

    <AuthenticatedLayout>
        <!-- Título -->
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Crear Proyecto
            </h2>
        </template>

        <!-- Contenedor principal con altura completa -->
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center">
                <div class="w-full max-w-2xl bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Campo: Nombre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Nombre
                            </label>
                            <input
                                v-model="form.nombre"
                                type="text"
                                required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Campo: Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Estado
                            </label>
                            <select
                                v-model="form.estado"
                                required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
                            >
                                <option value="En Proceso">En Proceso</option>
                                <option value="Pausado">Pausado</option>
                                <option value="Terminado">Terminado</option>
                            </select>
                        </div>

                        <!-- Campo: Descripción -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Descripción
                            </label>
                            <textarea
                                v-model="form.descripcion"
                                rows="4"
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
                            ></textarea>
                        </div>

                        <!-- Campo: Fecha de Inicio -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Fecha de Inicio
                            </label>
                            <input
                                v-model="form.fecha_inicio"
                                type="date"
                                required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Botón: Guardar -->
                        <div class="text-right">
                            <button
                                type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
