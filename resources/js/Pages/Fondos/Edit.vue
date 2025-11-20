<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    fondo: Object,
    cuentas: Array,
});

const form = useForm({
    cuenta_general_id: props.fondo.cuenta_general_id,
    nombre: props.fondo.nombre,
    descripcion: props.fondo.descripcion,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Editar Fondo" />

        <div class="max-w-3xl mx-auto p-8">
            <h1 class="text-2xl font-bold mb-6 dark:text-gray-200">Editar Fondo</h1>

            <form @submit.prevent="form.put(`/fondos/${fondo.id}`)" class="space-y-6">

                <div>
                    <label class="dark:text-gray-200">Cuenta Principal</label>
                    <select v-model="form.cuenta_general_id"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200">
                        <option v-for="c in cuentas" :value="c.id" :key="c.id">
                            {{ c.nombre }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="dark:text-gray-200">Nombre del Fondo</label>
                    <input v-model="form.nombre" type="text"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" />
                </div>

                <div>
                    <label class="dark:text-gray-200">Descripción</label>
                    <textarea v-model="form.descripcion"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"></textarea>
                </div>

                <div class="flex justify-between">
                    <Link href="/fondos" class="text-gray-600 dark:text-gray-300 hover:underline">
                        Volver
                    </Link>

                    <button
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Actualizar
                    </button>
                </div>

            </form>
        </div>

    </AuthenticatedLayout>
</template>
