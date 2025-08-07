<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Simulación de pestaña activa
const proyecto = ref({ pestaña: 'inventario' });

// Estado del formulario
const form = ref({
    anio: '',
    numero: '',
    codigo_final: '',
    fecha: '',
    descripcion: '',
    unidad_medida: '',
    entradas: 0,
    salidas: 0,
    stock: 0,
});

// Generar el código del producto automáticamente
const codigoCompleto = computed(() => {
    const anio = form.value.anio.padStart(4, '0');
    const numero = form.value.numero.padStart(3, '0');
    const codigoFinal = form.value.codigo_final.padStart(3, '0');
    return `IDPP/${anio}_${numero}_C${codigoFinal}`;
});

// Función vacía para guardar
const submit = () => {
    form.value.stock = form.value.entradas;
    form.value.salidas = 0;
    alert('Formulario guardado (simulado)');
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Formulario SIEMPRE visible -->
        <div v-if="proyecto.pestaña === 'inventario'" class="bg-white dark:bg-gray-800 max-w-3xl mx-auto mt-6 p-6 shadow rounded">
            <h1 class="text-2xl font-bold mb-6 text-gray-700 dark:text-gray-200">Crear Inventario</h1>
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Código Producto -->
                <div>
                    <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Código Producto</label>
                    <div class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
                        <span class="text-gray-500">IDPP/</span>
                        <input v-model="form.anio" type="text" maxlength="4" placeholder="XXXX" class="w-20 text-center border rounded px-2 py-1 mx-1 dark:bg-gray-700 dark:text-white" required>
                        <span class="text-gray-500">_</span>
                        <input v-model="form.numero" type="text" maxlength="3" placeholder="XXX" class="w-14 text-center border rounded px-2 py-1 mx-1 dark:bg-gray-700 dark:text-white" required>
                            <span class="text-gray-500">_C</span>
                            <input v-model="form.codigo_final" type="text" maxlength="3" placeholder="XXX" class="w-14 text-center border rounded px-2 py-1 mx-1 dark:bg-gray-700 dark:text-white" required>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            Código generado: <strong>{{ codigoCompleto }}</strong>
                        </p>
                    </div>

                    <!-- Fecha -->
                    <div>
                        <label class="block mb-1 font-bold text-gray-700 dark:text-gray-200">Fecha</label>
                        <input v-model="form.fecha" type="date" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Descripción</label>
                        <input v-model="form.descripcion" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                    </div>

                    <!-- Unidad de Medida -->
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Unidad de Medida</label>
                        <input v-model="form.unidad_medida" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                    </div>

                    <!-- Entradas -->
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Entradas</label>
                        <input v-model="form.entradas" type="number" min="1" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                    </div>

                    <!-- Botón guardar -->
                    <button type="submit" class="bg-blue-500 text-gray-700 dark:text-gray-200 px-4 py-2 rounded hover:bg-blue-700">
                        Guardar
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
</template>
