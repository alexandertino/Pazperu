<script setup>
defineProps({
    filtroInventario: String,
    filtroCategoria: String,
    filtroStock: Boolean,
    filtroSolicitadoPor: String,
    categorias: Array,
    solicitantesUnicos: Array,
    ordenInventarioAsc: Boolean,
    user: {
        type: Object,
        default: () => ({ role: 'guest' })  // Valor por defecto seguro
    }
});

defineEmits([
    'update:filtroInventario',
    'update:filtroCategoria', 
    'update:filtroStock',
    'update:filtroSolicitadoPor',
    'toggle-orden',
    'refrescar',
    'agregar',
    'exportar-excel'
]);

// Helper para verificar permisos de forma segura
const hasRole = (role) => {
    return props.user?.role === role;
};
</script>

<template>
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
        <div class="flex flex-wrap items-end gap-4">
            <!-- Buscar -->
            <div class="w-64">
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-300 pointer-events-none">🔍</span>
                    <input 
                        :value="filtroInventario" 
                        @input="$emit('update:filtroInventario', $event.target.value)"
                        type="text" 
                        placeholder="Código o producto..." 
                        class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 dark:bg-gray-700 dark:text-white" 
                    />
                </div>
            </div>

            <!-- Categoría -->
            <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Categoría</label>
                <select 
                    :value="filtroCategoria"
                    @change="$emit('update:filtroCategoria', $event.target.value)"
                    class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
                >
                    <option value="todos">Todas</option>
                    <option v-for="categoria in categorias" :key="categoria" :value="categoria">
                        {{ categoria }}
                    </option>
                </select>
            </div>

            <!-- Solicitado por -->
            <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Solicitado por</label>
                <select 
                    :value="filtroSolicitadoPor"
                    @change="$emit('update:filtroSolicitadoPor', $event.target.value)"
                    class="w-48 border rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
                >
                    <option value="todos">Todos</option>
                    <option v-for="solicitante in solicitantesUnicos" :key="solicitante" :value="solicitante">
                        {{ solicitante }}
                    </option>
                </select>
            </div>

            <!-- Checkbox stock -->
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-300">
                <input 
                    type="checkbox" 
                    :checked="filtroStock"
                    @change="$emit('update:filtroStock', $event.target.checked)"
                    class="w-4 h-4 accent-indigo-600" 
                />
                Solo con stock
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button 
                @click="$emit('toggle-orden')"
                class="px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-300"
            >
                📅 Ordenar: <span class="font-semibold">{{ ordenInventarioAsc ? 'Antiguos' : 'Recientes' }}</span>
            </button>
            
            <button 
                @click="$emit('refrescar')"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors duration-200"
            >
                🔄 Refrescar
            </button>
            
            <!-- Botones condicionales con verificación segura -->
            <button 
                v-if="user?.role === 'admin'" 
                @click="$emit('agregar')"
                class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
            >
                ➕ Agregar
            </button>

            <button 
                v-if="user?.role === 'admin'" 
                @click="$emit('exportar-excel')"
                class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 
                focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
            >
                📊 Exportar
            </button>
        </div>
    </div>
</template>