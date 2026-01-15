<script setup>
defineProps({
    filtroPrecioBuscar: String,
    filtroPrecioMin: String,
    filtroPrecioMax: String,
    filtroSolicitantePrecio: String,
    solicitantesUnicos: Array,
    preciosSolicitanteStats: Object
});

defineEmits([
    'update:filtroPrecioBuscar',
    'update:filtroPrecioMin',
    'update:filtroPrecioMax',
    'update:filtroSolicitantePrecio',
    'limpiar-filtros'
]);
</script>

<template>
    <div>
        <!-- Filtros principales -->
        <form @submit.prevent class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-6">
            <div>
                <label for="buscar" class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                    Buscar (código / producto)
                </label>
                <input 
                    id="buscar" 
                    :value="filtroPrecioBuscar" 
                    @input="$emit('update:filtroPrecioBuscar', $event.target.value)"
                    type="search" 
                    placeholder="Buscar..."
                    class="w-full p-2 border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-700" 
                />
            </div>

            <div class="flex gap-2 items-end">
                <div>
                    <label for="precio-min" class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                        Precio min
                    </label>
                    <input 
                        id="precio-min" 
                        :value="filtroPrecioMin" 
                        @input="$emit('update:filtroPrecioMin', $event.target.value)"
                        type="number" 
                        min="0" 
                        step="0.01"
                        class="p-2 border rounded-lg w-36 dark:bg-gray-700 dark:text-white" 
                    />
                </div>
                <div>
                    <label for="precio-max" class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                        Precio max
                    </label>
                    <input 
                        id="precio-max" 
                        :value="filtroPrecioMax" 
                        @input="$emit('update:filtroPrecioMax', $event.target.value)"
                        type="number" 
                        min="0" 
                        step="0.01"
                        class="p-2 border rounded-lg w-36 dark:bg-gray-700 dark:text-white" 
                    />
                </div>
            </div>

            <div class="flex justify-end md:justify-start">
                <button 
                    @click="$emit('limpiar-filtros')"
                    class="px-3 py-2 border rounded-md text-sm text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    Limpiar filtros
                </button>
            </div>
        </form>

        <!-- Filtros extra y estadísticas por solicitante -->
        <div class="mb-4 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="min-w-[220px]">
                <label for="solicitante" class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                    Filtrar por solicitante
                </label>
                <select 
                    id="solicitante" 
                    :value="filtroSolicitantePrecio"
                    @change="$emit('update:filtroSolicitantePrecio', $event.target.value)"
                    class="w-full p-2 border rounded-lg dark:bg-gray-700 dark:text-white"
                >
                    <option value="todos">Todos</option>
                    <option v-for="s in solicitantesUnicos" :key="s" :value="s">
                        {{ s }}
                    </option>
                </select>
            </div>

            <!-- Estadísticas del solicitante -->
            <div v-if="filtroSolicitantePrecio !== 'todos'" class="flex gap-3 ml-0 md:ml-4">
                <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg shadow-sm min-w-[110px]">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Productos</p>
                    <p class="font-bold text-gray-800 dark:text-white text-lg">
                        {{ preciosSolicitanteStats.count }}
                    </p>
                </div>
                <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg shadow-sm min-w-[140px]">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Valor total</p>
                    <p class="font-bold text-gray-800 dark:text-white text-lg">
                        S/ {{ Number(preciosSolicitanteStats.totalValue).toFixed(2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>