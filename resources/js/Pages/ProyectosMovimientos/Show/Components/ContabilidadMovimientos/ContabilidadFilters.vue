<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    tablaVisible: String,
    mesActivo: Number,
    anioActivo: Number,
    user: Object
});

const emit = defineEmits([
    'set-tabla',
    'prev-month',
    'next-month',
    'agregar-am',
    'agregar-easy',
    'exportar-excel',
    'recalcular-caja',
    'recalcular-banco'
]);

// Computed para labels y clases
const tablaVisibleLabel = computed(() => {
    if (props.tablaVisible === 'caja') return 'Caja';
    if (props.tablaVisible === 'banco') return 'Banco';
    if (props.tablaVisible === 'easy') return 'Easy';
    return '';
});

const badgeClass = computed(() => {
    if (props.tablaVisible === 'caja') return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    if (props.tablaVisible === 'banco') return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
});

// Helper para nombre del mes
const nombreMes = (m) => {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return meses[(m - 1 + 12) % 12] ?? m;
};
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">Libro de Contabilidad</h2>
            <span :class="badgeClass" class="text-sm px-3 py-1 rounded-full font-medium">
                {{ tablaVisibleLabel }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">— ventana activa</span>
        </div>

        <div class="flex items-center gap-3">
            <!-- Pestañas -->
            <div class="inline-flex rounded-md shadow-sm" role="tablist" aria-label="Tipo de tabla">
                <button 
                    @click="emit('set-tabla', 'caja')"
                    :class="tablaVisible === 'caja' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    class="px-3 py-1.5 rounded-l border border-gray-300 dark:border-gray-600 transition-colors duration-200 hover:bg-blue-500 hover:text-white"
                >
                    Caja
                </button>
                <button 
                    @click="emit('set-tabla', 'banco')"
                    :class="tablaVisible === 'banco' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    class="px-3 py-1.5 border-y border-gray-300 dark:border-gray-600 transition-colors duration-200 hover:bg-blue-500 hover:text-white"
                >
                    Banco
                </button>
                <button 
                    @click="emit('set-tabla', 'easy')"
                    :class="tablaVisible === 'easy' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    class="px-3 py-1.5 rounded-r border border-gray-300 dark:border-gray-600 transition-colors duration-200 hover:bg-blue-500 hover:text-white"
                >
                    Easy
                </button>
            </div>

            <!-- Selector mes/año -->
            <div class="flex items-center gap-2 p-2 border rounded bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <button 
                    @click="emit('prev-month')"
                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200"
                    title="Mes anterior"
                >
                    ‹
                </button>
                <div class="px-4 text-center">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Mes / Año</div>
                    <div class="text-base font-medium text-gray-800 dark:text-gray-200">
                        {{ nombreMes(mesActivo) }} {{ anioActivo }}
                    </div>
                </div>
                <button 
                    @click="emit('next-month')"
                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200"
                    title="Mes siguiente"
                >
                    ›
                </button>
            </div>

            <!-- Acciones -->
            <div class="flex items-center gap-2">
                <button 
                    v-if="user.role === 'admin'" 
                    @click="emit('agregar-am')"
                    class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
                >
                    Agregar
                </button>

                <button 
                    v-if="user.role === 'admin'" 
                    @click="emit('agregar-easy')"
                    class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
                >
                    Agregar Easy
                </button>

                <button 
                    @click="emit('exportar-excel')"
                    class="px-3 py-1.5 rounded bg-yellow-600 text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-400 transition-colors duration-200"
                >
                    Exportar a Excel
                </button>

                <!-- Botones para recalcular (solo admin) -->
                <button 
                    v-if="user.role === 'admin' && tablaVisible === 'caja'" 
                    @click="emit('recalcular-caja')"
                    class="px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors duration-200"
                >
                    Recalcular Caja
                </button>

                <button 
                    v-if="user.role === 'admin' && tablaVisible === 'banco'" 
                    @click="emit('recalcular-banco')"
                    class="px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors duration-200"
                >
                    Recalcular Banco
                </button>
            </div>
        </div>
    </div>

    <!-- Línea informativa -->
    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
        Mostrando: <strong class="text-gray-700 dark:text-gray-300">{{ tablaVisibleLabel }}</strong> — 
        {{ nombreMes(mesActivo) }} {{ anioActivo }}
    </div>
</template>