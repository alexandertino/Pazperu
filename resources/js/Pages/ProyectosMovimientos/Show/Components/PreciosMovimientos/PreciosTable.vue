<script setup>
defineProps({
    preciosFiltrados: Array
});

// Helper para formatear números
const formatNumber = (n) => {
    if (n === null || n === undefined) return '0.00';
    const num = Number(n);
    if (isNaN(num)) return n;
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <table class="min-w-full text-sm text-left">
        <thead class="bg-gray-100 dark:bg-gray-700 dark:text-white">
            <tr>
                <th class="p-2">Código</th>
                <th class="p-2">Producto</th>
                <th class="p-2">Categoría</th>
                <th class="p-2">Stock</th>
                <th class="p-2">Precio</th>
                <th class="p-2">Valor total</th>
                <th class="p-2">Solicitado por</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in preciosFiltrados" :key="item.id"
                class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-white">
                <td class="p-2">{{ item.codigo }}</td>
                <td class="p-2">{{ item.descripcion }}</td>
                <td class="p-2">{{ item.categoria }}</td>
                <td class="p-2 text-right">{{ item.stock }}</td>
                <td class="p-2 text-right">S/ {{ formatNumber(item.precio) }}</td>
                <td class="p-2 font-semibold text-right">
                    S/ {{ (Number(item.stock ?? 0) * Number(item.precio ?? 0)).toFixed(2) }}
                </td>
                <td class="p-2">{{ item.solicitado_por ?? '-' }}</td>
            </tr>

            <tr v-if="preciosFiltrados.length === 0">
                <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-400">
                    No hay ítems que coincidan.
                </td>
            </tr>
        </tbody>
    </table>
</template>