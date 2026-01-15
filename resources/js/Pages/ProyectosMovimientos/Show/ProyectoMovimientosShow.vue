<script setup>
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

// Componentes modulares
import HeaderTabs from './Components/SharedMovimientos/HeaderTabs.vue';
import InventarioTab from './Components/InventarioMovimientos/InventarioTab.vue';
import SalidasTab from './Components/SalidasMovimientos/SalidasTab.vue';
import PreciosTab from './Components/PreciosMovimientos/PreciosTab.vue';
import ContabilidadTab from './Components/ContabilidadMovimientos/ContabilidadTab.vue';

const props = defineProps({
    proyecto: Object,
    inventarios: Array,
    salidas: Array,
    caja: Array,
    banco: Array,
    easy: Array,
    user: Object, // Asegurar que user está definido
    auth: Object  // Inertia suele pasar auth en lugar de user directamente
});

// Usar auth.user si existe, de lo contrario usar user
const currentUser = props.auth?.user || props.user;

const pestañaActiva = ref('inventario');

const cambiarPestana = (tab) => {
    pestañaActiva.value = tab;
};

// Funciones de navegación
const agregarInventario = () => router.visit(`/proyectos/${props.proyecto.id}/inventarios/create`);
const agregarSalida = () => router.visit(`/proyectos/${props.proyecto.id}/salidas/create`);
const agregarAM = () => router.visit(`/proyectos/${props.proyecto.id}/am/create`);
const agregarEASY = () => router.visit(`/proyectos/${props.proyecto.id}/easy/create`);

const exportarExcelProyecto = (proyecto) => {
    window.location.href = `/proyecto/${proyecto}/exportar`;
};

</script>

<template>
    <Head :title="`Proyecto: ${proyecto.nombre}`" />
    <AuthenticatedLayout>
        <template #header>
            <HeaderTabs 
                :proyecto="proyecto"
                :pestañaActiva="pestañaActiva"
                @cambiar-pestana="cambiarPestana"
            />
        </template>

        <div class="py-12">
            <!-- Inventario -->
            <InventarioTab 
                v-if="pestañaActiva === 'inventario'"
                :proyecto="proyecto"
                :inventarios="inventarios"
                :user="currentUser"  
                @agregar-inventario="agregarInventario"
                @exportar-excel="exportarExcelProyecto(proyecto.nombre)"
            />

            <!-- Salidas -->
            <SalidasTab 
                v-if="pestañaActiva === 'salidas'"
                :proyecto="proyecto"
                :salidas="salidas"
                :user="currentUser"  
                @agregar-salida="agregarSalida"
                @exportar-excel="exportarExcelProyecto(proyecto.nombre)"
            />

            <!-- Precios -->
            <PreciosTab 
                v-if="pestañaActiva === 'Precios'"
                :proyecto="proyecto"
                :inventarios="inventarios"
                :user="currentUser"  
            />

            <!-- Contabilidad -->
            <ContabilidadTab 
                v-if="pestañaActiva === 'Contabilidad'"
                :proyecto="proyecto"
                :caja="caja"
                :banco="banco"
                :easy="easy"
                :user="currentUser" 
                @agregar-am="agregarAM"
                @agregar-easy="agregarEASY"
            />
        </div>
    </AuthenticatedLayout>
</template>