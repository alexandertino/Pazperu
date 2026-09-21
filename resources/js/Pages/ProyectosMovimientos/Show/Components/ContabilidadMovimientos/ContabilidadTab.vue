<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CajaTable from './CajaTable.vue';
import BancoTable from './BancoTable.vue';
import EasyTable from './EasyTable.vue';
import ContabilidadFilters from './ContabilidadFilters.vue';
import Swal from "sweetalert2";

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

const tablaCaja = computed(() => {
    const base = props.proyecto.nombre
        .toLowerCase()
        .replace(/\s+/g, '_');

    return `am_caja_proyecto_${base}`;
});

const tablaBanco = computed(() => {
    const base = props.proyecto.nombre
        .toLowerCase()
        .replace(/\s+/g, '_');

    return `am_banco_proyecto_${base}`;
});
// Computed para filtros por mes
const actasCajaFiltradas = computed(() => {
    return (props.caja || [])
        .filter(item => {
            const fecha = item.fecha;
            if (!fecha) return false;

            const mes = parseInt(fecha.substring(5, 7));
            const anio = parseInt(fecha.substring(0, 4));

            return mes === mesActivo.value && anio === anioActivo.value;
        })
        .sort((a, b) => {

            // primero ordenar por fecha
            if (a.fecha !== b.fecha) {
                return a.fecha.localeCompare(b.fecha);
            }

            // luego ordenar por numero de acta
            const numA = parseInt(a.n_acta?.match(/\d+/)?.[0] || 0);
            const numB = parseInt(b.n_acta?.match(/\d+/)?.[0] || 0);

            return numA - numB;
        });
});

const actasBancoFiltradas = computed(() => {
    return (props.banco || [])
        .filter(item => {
            const fecha = item.fecha;
            if (!fecha) return false;

            const mes = parseInt(fecha.substring(5, 7));
            const anio = parseInt(fecha.substring(0, 4));

            return mes === mesActivo.value && anio === anioActivo.value;
        })
        .sort((a, b) => {

            if (a.fecha !== b.fecha) {
                return a.fecha.localeCompare(b.fecha);
            }

            const numA = parseInt(a.n_acta?.match(/\d+/)?.[0] || 0);
            const numB = parseInt(b.n_acta?.match(/\d+/)?.[0] || 0);

            return numA - numB;
        });
});

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

const recalcularCaja = async () => {

    const confirm = await Swal.fire({
        title: 'Recalcular saldos',
        text: 'Se recalcularán los movimientos de caja',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {

        const nombreTabla = props.proyecto.nombre
            .toLowerCase()
            .replace(/\s+/g, '_');

        await axios.post(`/proyectos/${props.proyecto.id}/recalcular`, {
            tabla: `am_caja_proyecto_${nombreTabla}`
        });

        await Swal.fire(
            'Correcto',
            'Saldos recalculados correctamente',
            'success'
        );

        router.reload();

    } catch (error) {

        console.error(error.response?.data);

        Swal.fire(
            'Error',
            error.response?.data?.message || 'No se pudo recalcular',
            'error'
        );
    }
};

const recalcularBanco = async () => {

    const confirm = await Swal.fire({
        title: 'Recalcular saldos',
        text: 'Se recalcularán los movimientos de banco',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {

        const nombreTabla = props.proyecto.nombre
            .toLowerCase()
            .replace(/\s+/g, '_');

        await axios.post(`/proyectos/${props.proyecto.id}/recalcular`, {
            tabla: `am_banco_proyecto_${nombreTabla}`
        });

        await Swal.fire(
            'Correcto',
            'Saldos de banco recalculados correctamente',
            'success'
        );

        router.reload();

    } catch (error) {

        console.error(error.response?.data);

        Swal.fire(
            'Error',
            'No se pudo recalcular banco',
            'error'
        );
    }
};

const saldoAperturaCaja = computed(() => {

    if (!props.caja || props.caja.length === 0) return 0;

    const mesAnterior = mesActivo.value === 1 ? 12 : mesActivo.value - 1;
    const anioAnterior = mesActivo.value === 1 
        ? anioActivo.value - 1 
        : anioActivo.value;

    const movimientosMesAnterior = props.caja
        .filter(item => {
            if (!item.fecha) return false;

            const mes = parseInt(item.fecha.substring(5,7));
            const anio = parseInt(item.fecha.substring(0,4));

            return mes === mesAnterior && anio === anioAnterior;
        })
        .sort((a,b) => a.fecha.localeCompare(b.fecha));

    if (movimientosMesAnterior.length === 0) return 0;

    const ultimo = movimientosMesAnterior[movimientosMesAnterior.length - 1];

    return parseFloat(ultimo.saldo) || 0;

});

const irAOrden = () => {
    router.visit('/orden')
}

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
            @ir-a-orden="irAOrden"
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
                :saldoApertura="saldoAperturaCaja"
                :user="user"
                :tabla="tablaCaja"
            />

            <!-- Banco -->
            <BancoTable 
                v-if="tablaVisible === 'banco'"
                :proyecto="proyecto"
                :actas="actasBancoFiltradas"
                :mesActivo="mesActivo"
                :anioActivo="anioActivo"
                :user="user"
                :tabla="tablaBanco"
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