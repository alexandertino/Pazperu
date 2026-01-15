<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SalidasTable from './SalidasTable.vue';
import SalidasFilters from './SalidasFilters.vue';

const props = defineProps({
    proyecto: Object,
    salidas: Array,
    user: Object
});

const emit = defineEmits(['agregar-salida', 'exportar-excel']);

// Estados específicos de salidas
const filtroSalidas = ref('');
const filtroEstado = ref('todos');
const filtroEncargado = ref('todos');
const ordenSalidasAsc = ref(true);

// Computed específicos
const encargadosUnicos = computed(() => {
    const nombres = props.salidas?.map(s => s?.nombre_encargado)?.filter(Boolean) || [];
    return ['todos', ...new Set(nombres)];
});

const salidasFiltradas = computed(() =>
    [...(props.salidas || [])]
        .filter(s => {
            const q = (filtroSalidas.value || '').toString().toLowerCase().trim();
            const nombre = String(s?.nombre || '').toLowerCase();
            const nActa = String(s?.n_acta || '').toLowerCase();
            const lugar = String(s?.lugar || '').toLowerCase();
            const productoCode = String(s?.producto_code || s?.producto || '').toLowerCase();
            const encargado = String(s?.nombre_encargado || '').toLowerCase();

            const matchTexto =
                !q || nombre.includes(q) || nActa.includes(q) || lugar.includes(q) || productoCode.includes(q);

            const matchEstado =
                filtroEstado.value === 'todos' ||
                (String(s?.estado || '').toLowerCase() === String(filtroEstado.value || '').toLowerCase());

            const matchEncargado =
                filtroEncargado.value === 'todos' ||
                encargado === String(filtroEncargado.value || '').toLowerCase();

            return matchTexto && matchEstado && matchEncargado;
        })
        .sort((a, b) =>
            ordenSalidasAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

const conteoPorEstado = computed(() => {
    if (filtroEncargado.value === 'todos') return null;

    const filtradas = salidasFiltradas.value;
    const pendiente = filtradas.filter(s => s.estado?.toLowerCase() === 'pendiente').length;
    const aceptado = filtradas.filter(s => s.estado?.toLowerCase() === 'aceptado').length;

    return { pendiente, aceptado };
});

// Acciones específicas
const refrescarSalida = () => router.reload({ only: ['salidas'] });
const toggleOrdenSalidas = () => ordenSalidasAsc.value = !ordenSalidasAsc.value;
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">Gestión de Salidas</h3>

        <SalidasFilters
            :filtroSalidas="filtroSalidas"
            :filtroEstado="filtroEstado"
            :filtroEncargado="filtroEncargado"
            :encargadosUnicos="encargadosUnicos"
            :conteoPorEstado="conteoPorEstado"
            :ordenSalidasAsc="ordenSalidasAsc"
            @update:filtroSalidas="filtroSalidas = $event"
            @update:filtroEstado="filtroEstado = $event"
            @update:filtroEncargado="filtroEncargado = $event"
            @toggle-orden="toggleOrdenSalidas"
            @refrescar="refrescarSalida"
            @agregar="() => emit('agregar-salida')"
            @exportar-excel="() => emit('exportar-excel')"
            :user="user"
        />

        <SalidasTable
            :salidas="salidasFiltradas"
            :proyecto="proyecto"
            :user="user"
        />
    </div>
</template>