<script setup>
defineProps({
    proyecto: Object,
    pestañaActiva: String
});

defineEmits(['cambiar-pestana']);
</script>

<template>
    <div class="flex justify-between items-center mb-2">
        <!-- Título -->
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Proyecto: {{ proyecto.nombre }}
        </h2>

        <!-- Botones alineados a la derecha -->
        <div class="flex space-x-4">
            <button 
                @click="$emit('cambiar-pestana', 'Precios')" 
                :class="[
                    'px-4 py-2 rounded transition font-medium',
                    pestañaActiva === 'Precios'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                ]"
            >
                Precios
            </button>
            <button 
                @click="$emit('cambiar-pestana', 'Contabilidad')" 
                :class="[
                    'px-4 py-2 rounded transition font-medium',
                    pestañaActiva === 'Contabilidad'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                ]"
            >
                Contabilidad
            </button>
            <button 
                @click="$emit('cambiar-pestana', 'inventario')" 
                :class="[
                    'px-4 py-2 rounded transition font-medium',
                    pestañaActiva === 'inventario'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                ]"
            >
                Inventario
            </button>
            <button 
                @click="$emit('cambiar-pestana', 'salidas')" 
                :class="[
                    'px-4 py-2 rounded transition font-medium',
                    pestañaActiva === 'salidas'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                ]"
            >
                Salidas
            </button>
        </div>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Estado: {{ proyecto.estado }} — Inicio: {{ proyecto.fecha_inicio }} — Fin: {{ proyecto.fecha_fin ?? 'Pendiente' }}
    </p>
</template>