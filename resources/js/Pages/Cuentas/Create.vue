<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const form = reactive({
    nombre: '',
    descripcion: '',
    saldo_inicial: 0
});

const submit = () => {
    router.post('/cuentas', form);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Nueva Cuenta" />

        <div class="py-8 max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 border dark:border-gray-700">

                <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100">
                    Crear Nueva Cuenta
                </h2>

                <form @submit.prevent="submit" class="space-y-5">

                    <div>
                        <label class="block mb-1 text-gray-700 dark:text-gray-300">Nombre</label>
                        <input
                            v-model="form.nombre"
                            type="text"
                            required
                            class="w-full px-4 py-2 rounded-lg border dark:border-gray-600 bg-gray-50 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block mb-1 text-gray-700 dark:text-gray-300">Descripción</label>
                        <textarea
                            v-model="form.descripcion"
                            class="w-full px-4 py-2 rounded-lg border dark:border-gray-600 bg-gray-50 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block mb-1 text-gray-700 dark:text-gray-300">Saldo Inicial</label>
                        <input
                            v-model="form.saldo_inicial"
                            type="number"
                            step="0.01"
                            required
                            class="w-full px-4 py-2 rounded-lg border dark:border-gray-600 bg-gray-50 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div class="flex justify-between mt-6">
                        <Link
                            href="/cuentas"
                            class="px-4 py-2 rounded-lg bg-gray-500 text-white hover:bg-gray-600 transition"
                        >
                            Cancelar
                        </Link>

                        <button
                            type="submit"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                        >
                            Guardar
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
