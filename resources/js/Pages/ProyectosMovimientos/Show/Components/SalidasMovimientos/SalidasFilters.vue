<script setup>
defineProps({
    filtroSalidas: String,
    filtroEstado: String,
    filtroEncargado: String,
    encargadosUnicos: Array,
    conteoPorEstado: Object,
    ordenSalidasAsc: Boolean,
    user: Object
});

defineEmits([
    'update:filtroSalidas',
    'update:filtroEstado',
    'update:filtroEncargado',
    'toggle-orden',
    'refrescar',
    'agregar',
    'exportar-excel'
]);
</script>

<template>
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
        <div class="flex flex-wrap items-end gap-4">
            <!-- Buscar -->
            <div class="relative w-64">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Buscar</label>
                <span class="absolute bottom-2.5 left-3 text-gray-500 dark:text-gray-300 pointer-events-none">🔍</span>
                <input 
                    :value="filtroSalidas" 
                    @input="$emit('update:filtroSalidas', $event.target.value)"
                    type="text" 
                    aria-label="Buscar salidas por categorías"
                    placeholder="Buscar salidas..."
                    class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-700 dark:text-white" 
                />
            </div>

            <!-- Estado -->
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Estado</label>
                <select 
                    :value="filtroEstado"
                    @change="$emit('update:filtroEstado', $event.target.value)"
                    class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
                >
                    <option value="todos">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aceptado">Aceptado</option>
                </select>
            </div>

            <!-- Encargado -->
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Encargado</label>
                <select 
                    :value="filtroEncargado"
                    @change="$emit('update:filtroEncargado', $event.target.value)"
                    class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
                >
                    <option v-for="encargado in encargadosUnicos" :key="encargado" :value="encargado">
                        {{ encargado }}
                    </option>
                </select>
            </div>

            <!-- Conteo por estado -->
            <div v-if="filtroEncargado !== 'todos' && conteoPorEstado" 
                 class="flex flex-col mt-4 p-3 border rounded-lg bg-gray-50 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                    Conteo por estado de {{ filtroEncargado }}
                </h3>
                <div class="flex gap-4 text-sm">
                    <span class="text-blue-600 dark:text-blue-400">Pendientes: {{ conteoPorEstado.pendiente }}</span>
                    <span class="text-green-600 dark:text-green-400">Aceptados: {{ conteoPorEstado.aceptado }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button 
                @click="$emit('toggle-orden')"
                class="px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-300"
            >
                📅 Ordenar: <span class="font-semibold">{{ ordenSalidasAsc ? 'Antiguos' : 'Recientes' }}</span>
            </button>

            <button 
                @click="$emit('refrescar')"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors duration-200"
            >
                🔄 Refrescar
            </button>

            <button 
                v-if="user.role === 'admin' || user.role === 'equipo'" 
                @click="$emit('agregar')"
                class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
            >
                ➕ Agregar
            </button>

            <button 
                v-if="user.role === 'admin'" 
                @click="$emit('exportar-excel')"
                class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
            >
                📊 Exportar
            </button>
        </div>
    </div>
</template>