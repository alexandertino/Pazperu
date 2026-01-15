<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    proyecto: Object,
    items: Array,
    mesActivo: Number,
    anioActivo: Number,
    user: Object
});

// Helper functions
const formatNumber = (n) => {
    if (n === null || n === undefined) return '0.00';
    const num = Number(n);
    if (isNaN(num)) return n;
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Función para eliminar item Easy
const eliminarEasy = async (id) => {
    const conf = await Swal.fire({
        title: 'Eliminar registro',
        text: '¿Seguro que deseas eliminar este registro Easy?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar'
    });
    
    if (!conf.isConfirmed) return;

    try {
        await axios.delete(`/proyectos/${props.proyecto.id}/easy/${id}`);
        Swal.fire('Eliminado', 'Registro Easy eliminado correctamente.', 'success');
        router.reload();
    } catch (err) {
        console.error('Error al eliminar Easy:', err);
        Swal.fire('Error', err.response?.data?.message || 'No se pudo eliminar.', 'error');
    }
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full table-auto text-sm text-left border dark:border-gray-700">
            <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                <tr>
                    <th class="p-2">Codigo general</th>
                    <th class="p-2">Gasto (PEN)</th>
                    <th class="p-2">Receta (PEN)</th>
                    <th class="p-2">Moneda de facturación</th>
                    <th class="p-2">Débito (EUR)</th>
                    <th class="p-2">Crédito (EUR)</th>
                    <th class="p-2">Moneda de gestión</th>
                    <th class="p-2">Numeración y descripción</th>
                    <th class="p-2">código de presupuesto</th>
                    <th class="p-2">Naturaleza</th>
                    <th class="p-2">Contrato</th>
                    <th class="p-2">Donantes</th>
                    <th class="p-2">Fecha</th>
                    <th v-if="user.role === 'admin'" class="p-2">Acciones</th>
                </tr>
            </thead>

            <tbody>
                <!-- Filas de items -->
                <tr v-for="item in items" :key="item.id"
                    class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <td class="p-2">{{ item.Cuenta_general }}</td>
                    <td class="p-2">{{ item.gasto_moneda_local }}</td>
                    <td class="p-2">{{ item.ingreso_moneda_local }}</td>
                    <td class="p-2">{{ item.moneda_facturacion }}</td>
                    <td class="p-2">{{ item.debito_moneda_gestion }}</td>
                    <td class="p-2">{{ item.credito_moneda_gestion }}</td>
                    <td class="p-2">{{ item.moneda_gestion }}</td>
                    <td class="p-2">{{ item.numero_descripcion_pieza }}</td>
                    <td class="p-2">{{ item.codigo_presupuestario }}</td>
                    <td class="p-2">{{ item.naturaleza_presupuesto }}</td>
                    <td class="p-2">{{ item.contrato }}</td>
                    <td class="p-2">{{ item.bailleur_fondos }}</td>
                    <td class="p-2">{{ item.fecha }}</td>
                    
                    <td v-if="user.role === 'admin'" class="p-3 flex gap-2 items-center">
                        <a :href="`/proyectos/${proyecto.id}/easy/${item.id}/edit`"
                            class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg">✏️</a>
                        <button @click="eliminarEasy(item.id)"
                            class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg">🗑️</button>
                    </td>
                </tr>

                <!-- Sin registros -->
                <tr v-if="items.length === 0">
                    <td class="p-2 italic text-gray-500" colspan="14">No hay registros Easy.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>