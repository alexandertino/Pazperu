<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import InventarioTable from './InventarioTable.vue';
import InventarioFilters from './InventarioFilters.vue';

const props = defineProps({
    proyecto: Object,
    inventarios: Array,
    user: Object  // Asegurar que user está definido
});

const emit = defineEmits(['agregar-inventario', 'exportar-excel']);

// Estados específicos del inventario
const filtroInventario = ref('');
const filtroCategoria = ref("todos");
const filtroStock = ref(false);
const filtroSolicitadoPor = ref("todos");
const ordenInventarioAsc = ref(true);
const comentarioActivo = ref(null);

// Computed específicos
const categorias = computed(() => 
    [...new Set((props.inventarios || []).map(i => i?.categoria ?? 'Sin categoría'))]
);

const solicitantesUnicos = computed(() => 
    [...new Set((props.inventarios || []).map(i => i?.solicitado_por).filter(Boolean))]
);

const inventarioFiltrado = computed(() => 
    [...(props.inventarios || [])]
        .filter(i => {
            const q = filtroInventario.value.toLowerCase();
            const desc = String(i?.descripcion || '').toLowerCase();
            const cod = String(i?.codigo || '').toLowerCase();
            const cat = String(i?.categoria || '').toLowerCase();
            return desc.includes(q) || cod.includes(q) || cat.includes(q);
        })
        .filter(i => filtroCategoria.value === "todos" || i?.categoria === filtroCategoria.value)
        .filter(i => filtroSolicitadoPor.value === "todos" || i?.solicitado_por === filtroSolicitadoPor.value)
        .filter(i => !filtroStock.value || Number(i?.stock ?? 0) > 0)
        .sort((a, b) =>
            ordenInventarioAsc.value
                ? new Date(a.created_at) - new Date(b.created_at)
                : new Date(b.created_at) - new Date(a.created_at)
        )
);

// Acciones específicas
const refrescarInventario = () => router.reload({ only: ['inventarios'] });
const toggleOrdenInventario = () => ordenInventarioAsc.value = !ordenInventarioAsc.value;
const toggleComentario = (id) => {
    comentarioActivo.value = comentarioActivo.value === id ? null : id;
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">Inventarios</h3>

        <InventarioFilters
            :filtroInventario="filtroInventario"
            :filtroCategoria="filtroCategoria"
            :filtroStock="filtroStock"
            :filtroSolicitadoPor="filtroSolicitadoPor"
            :categorias="categorias"
            :solicitantesUnicos="solicitantesUnicos"
            :ordenInventarioAsc="ordenInventarioAsc"
            :user="user" 
            @update:filtroInventario="filtroInventario = $event"
            @update:filtroCategoria="filtroCategoria = $event"
            @update:filtroStock="filtroStock = $event"
            @update:filtroSolicitadoPor="filtroSolicitadoPor = $event"
            @toggle-orden="toggleOrdenInventario"
            @refrescar="refrescarInventario"
            @agregar="() => emit('agregar-inventario')"
            @exportar-excel="() => emit('exportar-excel')"
        />

        <InventarioTable
            :inventarios="inventarioFiltrado"
            :proyecto="proyecto"
            :user="user" 
            :comentarioActivo="comentarioActivo"
            @toggle-comentario="toggleComentario"
        />
    </div>
</template>