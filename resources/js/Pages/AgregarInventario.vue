
<script setup>
import { ref, computed } from 'vue';

// Simulación de pestaña activa
const proyecto = ref({ pestaña: 'inventario' });

// Mostrar formulario solo si se hace clic en el botón
const mostrarFormulario = ref(false);

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
    <!-- Botón para mostrar formulario -->
    <button
        v-if="proyecto.pestaña === 'inventario' && !mostrarFormulario"
        @click="mostrarFormulario = true"
        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded"
    >
        Agregar Inventario
    </button>

    <!-- Formulario visible al hacer clic -->
    <div v-if="mostrarFormulario" class="bg-white max-w-3xl mx-auto mt-6 p-6 shadow rounded">
        <h1 class="text-2xl font-bold mb-6">Crear Inventario</h1>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Código Producto -->
            <div>
                <label class="block font-bold mb-1">Código Producto</label>
                <div class="flex items-center border rounded px-3 py-2 bg-gray-100">
                    <span class="text-gray-500">IDPP/</span>
                    <input v-model="form.anio" type="text" maxlength="4" placeholder="XXXX" class="w-20 text-center border rounded px-2 py-1 mx-1" required>
                    <span class="text-gray-500">_</span>
                    <input v-model="form.numero" type="text" maxlength="3" placeholder="XXX" class="w-14 text-center border rounded px-2 py-1 mx-1" required>
                    <span class="text-gray-500">_C</span>
                    <input v-model="form.codigo_final" type="text" maxlength="3" placeholder="XXX" class="w-14 text-center border rounded px-2 py-1 mx-1" required>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    Código generado: <strong>{{ codigoCompleto }}</strong>
                </p>
            </div>

            <!-- Fecha -->
            <div>
                <label class="block mb-1 font-bold">Fecha</label>
                <input v-model="form.fecha" type="date" class="w-full border rounded px-3 py-2" required>
            </div>

            <!-- Descripción -->
            <div>
                <label class="block font-bold mb-1">Descripción</label>
                <input v-model="form.descripcion" type="text" class="w-full border rounded px-3 py-2" required>
            </div>

            <!-- Unidad de Medida -->
            <div>
                <label class="block font-bold mb-1">Unidad de Medida</label>
                <input v-model="form.unidad_medida" type="text" class="w-full border rounded px-3 py-2" required>
            </div>

            <!-- Entradas -->
            <div>
                <label class="block font-bold mb-1">Entradas</label>
                <input v-model="form.entradas" type="number" min="1" class="w-full border rounded px-3 py-2" required>
            </div>

            <!-- Botón guardar -->
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
                Guardar
            </button>
        </form>
    </div>
</template>
