<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CajaTable from './CajaTable.vue';
import BancoTable from './BancoTable.vue';
import EasyTable from './EasyTable.vue';
import ContabilidadFilters from './ContabilidadFilters.vue';

const props = defineProps({
    proyecto: Object,
    caja: Array,
    banco: Array,
    easy: Array,
    user: Object
});

const emit = defineEmits(['agregar-am', 'agregar-easy']);

// Estados específicos de contabilidad
const tablaVisible = ref('caja');
const mesActivo = ref(new Date().getMonth() + 1);
const anioActivo = ref(new Date().getFullYear());

// Computed para filtros por mes
const actasCajaFiltradas = computed(() =>
    (props.caja || []).filter(item => {
        const fecha = item.fecha;
        if (!fecha) return false;
        const mes = parseInt(fecha.substring(5, 7));
        const anio = parseInt(fecha.substring(0, 4));
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

const actasBancoFiltradas = computed(() =>
    (props.banco || []).filter(item => {
        const fecha = item.fecha;
        if (!fecha) return false;
        const mes = parseInt(fecha.substring(5, 7));
        const anio = parseInt(fecha.substring(0, 4));
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

const itemsEasyFiltrados = computed(() =>
    (props.easy || []).filter(item => {
        const fecha = item.fecha || item.created_at;
        if (!fecha) return false;
        const mes = parseInt(fecha.substring(5, 7));
        const anio = parseInt(fecha.substring(0, 4));
        return mes === mesActivo.value && anio === anioActivo.value;
    })
);

// Funciones de navegación
const prevMonth = () => {
    if (mesActivo.value === 1) { 
        mesActivo.value = 12; 
        anioActivo.value--; 
    } else { 
        mesActivo.value--; 
    }
};

const nextMonth = () => {
    if (mesActivo.value === 12) { 
        mesActivo.value = 1; 
        anioActivo.value++; 
    } else { 
        mesActivo.value++; 
    }
};

const setTabla = (tipo) => {
    tablaVisible.value = tipo;
};

const exportarExcel = () => {
    const url = `/proyectos/${props.proyecto.id}/exportar-contabilidad-multiples?mes=${mesActivo.value}&anio=${anioActivo.value}`;
    window.location.href = url;
};

// Funciones para recalcular
const recalcularCaja = async () => {
    if (!confirm('¿Recalcular saldos de Caja? Esto puede tomar unos momentos.')) return;
    
    try {
        const response = await fetch(`/proyectos/${props.proyecto.id}/am/recalcular`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ tabla: 'caja' })
        });
        
        if (response.ok) {
            alert('Caja recalculada correctamente');
            router.reload();
        } else {
            alert('Error al recalcular caja');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al recalcular caja');
    }
};

const recalcularBanco = async () => {
    if (!confirm('¿Recalcular saldos de Banco? Esto puede tomar unos momentos.')) return;
    
    try {
        const response = await fetch(`/proyectos/${props.proyecto.id}/am/recalcular`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ tabla: 'banco' })
        });
        
        if (response.ok) {
            alert('Banco recalculado correctamente');
            router.reload();
        } else {
            alert('Error al recalcular banco');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al recalcular banco');
    }
};
</script>

<template>
    <div class="p-5 rounded-2xl shadow-md mt-6 border bg-white dark:bg-gray-800">
        <!-- Header con filtros -->
        <ContabilidadFilters
            :tablaVisible="tablaVisible"
            :mesActivo="mesActivo"
            :anioActivo="anioActivo"
            :user="user"
            @set-tabla="setTabla"
            @prev-month="prevMonth"
            @next-month="nextMonth"
            @agregar-am="() => emit('agregar-am')"
            @agregar-easy="() => emit('agregar-easy')"
            @exportar-excel="exportarExcel"
            @recalcular-caja="recalcularCaja"
            @recalcular-banco="recalcularBanco"
        />

        <!-- Tablas -->
        <div>
            <!-- Caja -->
            <CajaTable 
                v-if="tablaVisible === 'caja'"
                :proyecto="proyecto"
                :actas="actasCajaFiltradas"
                :mesActivo="mesActivo"
                :anioActivo="anioActivo"
                :user="user"
            />

            <!-- Banco -->
            <BancoTable 
                v-if="tablaVisible === 'banco'"
                :proyecto="proyecto"
                :actas="actasBancoFiltradas"
                :mesActivo="mesActivo"
                :anioActivo="anioActivo"
                :user="user"
            />

            <!-- Easy -->
            <EasyTable 
                v-if="tablaVisible === 'easy'"
                :proyecto="proyecto"
                :items="itemsEasyFiltrados"
                :mesActivo="mesActivo"
                :anioActivo="anioActivo"
                :user="user"
            />
        </div>
    </div>
</template>