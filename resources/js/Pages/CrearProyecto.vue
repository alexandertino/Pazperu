<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Swal from 'sweetalert2'

const form = reactive({
    nombre: '',
    estado: 'En Proceso',
    descripcion: '',
    fecha_inicio: '',
    fecha_fin: ''
})

function resetForm() {
    form.nombre = ''
    form.estado = 'En Proceso'
    form.descripcion = ''
    form.fecha_inicio = ''
    form.fecha_fin = ''
}

function handleSubmit() {
    router.post('/crear', form, {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Proyecto creado',
                text: '✅ El proyecto fue creado correctamente',
                confirmButtonColor: '#2563eb'
            })
            resetForm()
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '❌ No se pudo crear el proyecto. Revisa los campos.',
                confirmButtonColor: '#dc2626'
            })
        }
    })
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

        <!-- Contenedor principal -->
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center">
                <div class="w-full max-w-2xl bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <form @submit.prevent="handleSubmit" class="space-y-6">

                        <!-- Campo: Nombre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Nombre
                            </label>
                            <input v-model="form.nombre" type="text" required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Campo: Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Estado
                            </label>
                            <select v-model="form.estado" required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500">
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
                            <textarea v-model="form.descripcion" rows="4"
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <!-- Campo: Fecha de Inicio -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Fecha de Inicio
                            </label>
                            <input v-model="form.fecha_inicio" type="date" required
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Campo: Fecha de Fin -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Fecha de Fin
                            </label>
                            <input v-model="form.fecha_fin" type="date"
                                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Botón: Guardar -->
                        <!-- Botones -->
                        <div class="flex justify-between items-center">
                            <!-- Botón volver -->
                            <button type="button" @click="router.visit('/proyectos')"
                                class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition">
                                ← Volver
                            </button>

                            <!-- Botón guardar -->
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                Guardar
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
