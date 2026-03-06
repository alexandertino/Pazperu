<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    proyecto: Object,
    actas: Array,
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

const nombreMes = (m) => {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return meses[(m - 1 + 12) % 12] ?? m;
};

// Función para eliminar acta de caja
const eliminarCaja = async (id) => {
    const conf = await Swal.fire({
        title: 'Eliminar registro',
        text: '¿Seguro que deseas eliminar esta acta de Caja?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar'
    });
    
    if (!conf.isConfirmed) return;

    try {
        await axios.delete(`/proyectos/${props.proyecto.id}/amcaja/${id}`);
        Swal.fire('Eliminado', 'Registro de caja eliminado correctamente.', 'success');
        router.reload();
    } catch (err) {
        console.error('Error al eliminar caja:', err);
        Swal.fire('Error', err.response?.data?.message || 'No se pudo eliminar.', 'error');
    }
};

const saldoApertura = computed(() => {

    if (!props.actas || props.actas.length === 0) return 0;

    const ordenadas = [...props.actas].sort(
        (a, b) => new Date(a.fecha) - new Date(b.fecha)
    );

    const primer = ordenadas[0];

    const ingreso = parseFloat(primer.ingresos || 0);
    const egreso = parseFloat(primer.egresos || 0);
    const saldo = parseFloat(primer.saldo || 0);

    return saldo - ingreso + egreso;

});

</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border dark:border-gray-700">
            <thead class="sticky top-0 z-10 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100">
                <tr>
                    <th class="p-3">N° Acta</th>
                    <th class="p-3">Fecha</th>
                    <th class="p-3">Descripción</th>
                    <th class="p-3">Presupuestario</th>
                    <th class="p-3">Actividad</th>
                    <th class="p-3">Ingresos</th>
                    <th class="p-3">Egresos</th>
                    <th class="p-3">Saldo</th>
                    <th v-if="user.role === 'admin'" class="p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Saldo apertura -->
                <tr class="bg-gray-50 dark:bg-gray-800 text-sm dark:text-white">
                    <td class="p-3" colspan="5">
                        Saldo apertura — {{ nombreMes(mesActivo === 1 ? 12 : mesActivo - 1) }}
                        {{ mesActivo === 1 ? (anioActivo - 1) : anioActivo }}
                    </td>

                    <td class="p-3"></td>
                    <td class="p-3"></td>

                    <td class="p-3 font-semibold">
                        {{ saldoApertura.toFixed(2) }}
                    </td>

                    <td v-if="user.role === 'admin'" class="p-3"></td>
                </tr>

                <!-- Filas de actas -->
                <tr v-for="acta in actas" :key="acta.id"
                    class="border-t dark:text-white dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <td class="p-3">{{ acta.n_acta }}</td>
                    <td class="p-3">{{ acta.fecha }}</td>
                    <td class="p-3">{{ acta.descripcion }}</td>
                    <td class="p-3">{{ acta.presupuestario }}</td>
                    <td class="p-3">{{ acta.actividad }}</td>
                    <td class="p-3 text-green-600 font-semibold">{{ formatNumber(acta.ingresos) }}</td>
                    <td class="p-3 text-red-600 font-semibold">{{ formatNumber(acta.egresos) }}</td>
                    <td class="p-3 font-bold">{{ formatNumber(acta.saldo) }}</td>
                    
                    <td v-if="user.role === 'admin'" class="p-3 flex gap-2 items-center">
                        <a :href="`/proyectos/${proyecto.id}/amcaja/${acta.id}/edit`"
                            class="flex items-center justify-center w-9 h-9 bg-blue-500 text-white rounded-lg">✏️</a>
                        <button @click="eliminarCaja(acta.id)" title="Eliminar"
                            class="flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded-lg">🗑️
                        </button>
                    </td>
                </tr>

                <tr v-if="actas.length === 0">
                    <td class="p-3 text-gray-500 italic" colspan="9">No hay registros en Caja.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>