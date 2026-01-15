<script setup>
defineProps({
    totalInventario: Number,
    totalEntradas: Number,
    totalSalidas: Number,
    inventarios: Array,
    mostrarDetalleTotal: Boolean,
    topN: Number,
    topItems: Array
});

defineEmits(['update:topN', 'update:mostrarDetalleTotal']);
</script>

<template>
    <div>
        <!-- Tarjetas resumen -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Valor Total</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-300">
                            S/ {{ Number(totalInventario).toFixed(2) }}
                        </p>
                    </div>
                    <button 
                        @click="$emit('update:mostrarDetalleTotal', !mostrarDetalleTotal)" 
                        aria-pressed="false"
                        class="text-xs px-2 py-1 bg-indigo-200 dark:bg-indigo-700 rounded"
                    >
                        {{ mostrarDetalleTotal ? 'Ocultar' : 'Ver detalle' }}
                    </button>
                </div>

                <ul v-if="mostrarDetalleTotal" class="mt-3 text-sm max-h-40 overflow-y-auto pr-2">
                    <li v-for="item in inventarios" :key="item.id" class="flex justify-between py-1">
                        <span class="truncate max-w-[70%]">
                            {{ item.descripcion }} ({{ item.stock }} × S/ {{ Number(item.precio ?? 0).toFixed(2) }})
                        </span>
                        <span class="font-semibold">
                            S/ {{ (Number(item.stock ?? 0) * Number(item.precio ?? 0)).toFixed(2) }}
                        </span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-green-50 dark:bg-green-900/30 rounded-xl shadow-sm">
                <p class="text-sm text-gray-600 dark:text-gray-400">Entradas</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-300">
                    + S/ {{ Number(totalEntradas).toFixed(2) }}
                </p>
            </div>

            <div class="p-4 bg-red-50 dark:bg-red-900/30 rounded-xl shadow-sm">
                <p class="text-sm text-gray-600 dark:text-gray-400">Salidas</p>
                <p class="text-xl font-bold text-red-600 dark:text-red-300">
                    - S/ {{ Number(totalSalidas).toFixed(2) }}
                </p>
            </div>
        </div>

        <!-- Top N y tabla principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Lateral: Top N -->
            <aside class="lg:col-span-1 p-4 border rounded-lg dark:border-gray-700">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200">Top {{ topN }} (por valor)</h4>
                    <select 
                        :value="topN" 
                        @change="$emit('update:topN', Number($event.target.value))"
                        class="p-1 border rounded dark:bg-gray-700 dark:text-white"
                    >
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                    </select>
                </div>

                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700 dark:text-white max-h-72 overflow-y-auto">
                    <li v-for="it in topItems" :key="it.id" class="flex justify-between items-center">
                        <div class="truncate max-w-[60%]">{{ it.descripcion }}</div>
                        <div class="text-sm font-semibold">
                            S/ {{ (Number(it.stock ?? 0) * Number(it.precio ?? 0)).toFixed(2) }}
                        </div>
                    </li>
                </ol>
            </aside>

            <!-- Tabla principal -->
            <div class="lg:col-span-2 p-4 border rounded-lg dark:border-gray-700 overflow-x-auto">
                <PreciosTable :preciosFiltrados="preciosFiltrados" />
            </div>
        </div>
    </div>
</template>