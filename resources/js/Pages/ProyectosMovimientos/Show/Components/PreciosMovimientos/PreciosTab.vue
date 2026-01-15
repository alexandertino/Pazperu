<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    proyecto: Object,
    inventarios: Array,
    user: Object
});

// Estados
const filtroBuscar = ref('');
const filtroPrecioMin = ref('');
const filtroPrecioMax = ref('');
const filtroSolicitante = ref('todos');
const mostrarDetalle = ref(false);
const topN = ref(10);

// Computed
const solicitantesUnicos = computed(() => {
    const solicitantes = [...new Set(props.inventarios?.map(i => i?.solicitado_por).filter(Boolean))];
    return ['todos', ...solicitantes];
});

const inventariosFiltrados = computed(() => {
    let filtered = [...(props.inventarios || [])];
    
    if (filtroBuscar.value) {
        const query = filtroBuscar.value.toLowerCase();
        filtered = filtered.filter(i => 
            String(i?.descripcion || '').toLowerCase().includes(query) ||
            String(i?.codigo || '').toLowerCase().includes(query)
        );
    }
    
    if (filtroPrecioMin.value) {
        filtered = filtered.filter(i => Number(i?.precio ?? 0) >= Number(filtroPrecioMin.value));
    }
    
    if (filtroPrecioMax.value) {
        filtered = filtered.filter(i => Number(i?.precio ?? 0) <= Number(filtroPrecioMax.value));
    }
    
    if (filtroSolicitante.value !== 'todos') {
        filtered = filtered.filter(i => i?.solicitado_por === filtroSolicitante.value);
    }
    
    return filtered.sort((a, b) => {
        const valorA = (Number(a?.stock ?? 0) * Number(a?.precio ?? 0));
        const valorB = (Number(b?.stock ?? 0) * Number(b?.precio ?? 0));
        return valorB - valorA;
    });
});

const topItems = computed(() => inventariosFiltrados.value.slice(0, topN.value));

const totalInventario = computed(() => 
    inventariosFiltrados.value.reduce((sum, item) => 
        sum + (Number(item?.precio ?? 0) * Number(item?.stock ?? 0)), 0)
);

const totalEntradas = computed(() => 
    inventariosFiltrados.value.reduce((sum, item) => 
        sum + (Number(item?.precio ?? 0) * Number(item?.entradas ?? 0)), 0)
);

const totalSalidas = computed(() => 
    inventariosFiltrados.value.reduce((sum, item) => 
        sum + (Number(item?.precio ?? 0) * Number(item?.salidas ?? 0)), 0)
);

const statsSolicitante = computed(() => {
    if (filtroSolicitante.value === 'todos') return null;
    
    const items = inventariosFiltrados.value.filter(i => i?.solicitado_por === filtroSolicitante.value);
    const count = items.length;
    const totalValor = items.reduce((sum, item) => 
        sum + (Number(item?.precio ?? 0) * Number(item?.stock ?? 0)), 0);
    const precioPromedio = count > 0 ? items.reduce((sum, item) => sum + Number(item?.precio ?? 0), 0) / count : 0;
    
    return { count, totalValor, precioPromedio };
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
    }).format(amount);
};

const limpiarFiltros = () => {
    filtroBuscar.value = '';
    filtroPrecioMin.value = '';
    filtroPrecioMax.value = '';
    filtroSolicitante.value = 'todos';
};

const refrescarDatos = () => {
    router.reload({ only: ['inventarios'] });
};
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header Compacto -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900 rounded-lg">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Análisis de Precios</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ inventariosFiltrados.length }} productos</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="limpiarFiltros" 
                        class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Limpiar
                </button>
                <button @click="refrescarDatos" 
                        class="px-3 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Actualizar
                </button>
            </div>
        </div>

        <!-- Filtros Compactos -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar</label>
                <input v-model="filtroBuscar" 
                       type="text" 
                       placeholder="Código o producto..."
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Precio unitario</label>
                <div class="flex gap-2">
                    <input v-model="filtroPrecioMin" 
                           type="number" 
                           placeholder="Mín"
                           class="w-1/2 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <input v-model="filtroPrecioMax" 
                           type="number" 
                           placeholder="Máx"
                           class="w-1/2 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Solicitante</label>
                <select v-model="filtroSolicitante" 
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option v-for="solicitante in solicitantesUnicos" :key="solicitante" :value="solicitante">
                        {{ solicitante }}
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Mostrar Top</label>
                <select v-model="topN" 
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="5">Top 5</option>
                    <option value="10">Top 10</option>
                    <option value="20">Top 20</option>
                    <option value="50">Top 50</option>
                </select>
            </div>
        </div>

        <!-- Estadísticas del Solicitante (Compacto) -->
        <div v-if="statsSolicitante" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                    {{ filtroSolicitante }}
                </h3>
                <div class="flex gap-4 text-xs">
                    <div class="text-center">
                        <p class="text-blue-600 dark:text-blue-400">Productos</p>
                        <p class="font-bold text-blue-900 dark:text-blue-100">{{ statsSolicitante.count }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-blue-600 dark:text-blue-400">Valor Total</p>
                        <p class="font-bold text-blue-900 dark:text-blue-100">{{ formatCurrency(statsSolicitante.totalValor) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-blue-600 dark:text-blue-400">Precio Prom.</p>
                        <p class="font-bold text-blue-900 dark:text-blue-100">{{ formatCurrency(statsSolicitante.precioPromedio) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen Compactas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Valor Total -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Valor Total</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(totalInventario) }}</p>
                    </div>
                    <button @click="mostrarDetalle = !mostrarDetalle" 
                            class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  :d="mostrarDetalle ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                        </svg>
                    </button>
                </div>
                <div v-if="mostrarDetalle" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <div class="space-y-2 max-h-32 overflow-y-auto">
                        <div v-for="item in inventariosFiltrados.slice(0, 5)" :key="item.id" 
                             class="flex justify-between items-center text-xs">
                            <span class="text-gray-600 dark:text-gray-400 truncate flex-1">{{ item.descripcion }}</span>
                            <span class="text-gray-900 dark:text-white font-medium ml-2 whitespace-nowrap">
                                {{ formatCurrency(item.stock * item.precio) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entradas -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Entradas</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400 mt-1">{{ formatCurrency(totalEntradas) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Acumulado histórico</p>
            </div>

            <!-- Salidas -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Salidas</p>
                <p class="text-xl font-bold text-red-600 dark:text-red-400 mt-1">{{ formatCurrency(totalSalidas) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Acumulado histórico</p>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar Top N -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 sticky top-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Top {{ topN }}</h3>
                        <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                            Por valor
                        </span>
                    </div>
                    
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div v-for="(item, index) in topItems" :key="item.id" 
                             class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded border text-sm">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white text-xs rounded flex items-center justify-center font-bold">
                                {{ index + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white truncate text-xs">
                                    {{ item.descripcion }}
                                </p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">
                                    {{ item.stock }} unid.
                                </p>
                            </div>
                            <span class="text-blue-600 dark:text-blue-400 font-semibold text-xs whitespace-nowrap">
                                {{ formatCurrency(item.stock * item.precio) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal -->
            <div class="lg:col-span-3">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Producto
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Categoría
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Stock
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Precio
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="item in inventariosFiltrados" :key="item.id" 
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <td class="px-3 py-2">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white text-sm">{{ item.descripcion }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ item.codigo }}</p>
                                            <p v-if="item.solicitado_por" class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ item.solicitado_por }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-sm">
                                        {{ item.categoria || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-900 dark:text-white font-medium text-sm">
                                        {{ item.stock }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-900 dark:text-white text-sm">
                                        {{ formatCurrency(item.precio) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="font-bold text-blue-600 dark:text-blue-400 text-sm">
                                            {{ formatCurrency(item.stock * item.precio) }}
                                        </span>
                                    </td>
                                </tr>
                                
                                <!-- Estado vacío -->
                                <tr v-if="inventariosFiltrados.length === 0">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
                                        </svg>
                                        <p class="text-sm">No se encontraron productos</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación o contador -->
                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Mostrando {{ inventariosFiltrados.length }} de {{ props.inventarios?.length || 0 }} productos
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>