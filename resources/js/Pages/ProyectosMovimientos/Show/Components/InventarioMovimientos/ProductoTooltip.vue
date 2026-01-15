<script setup>
defineProps({
    productoTooltip: Object,
    tooltipVisible: Boolean,
    codigoActivo: [String, Number],
    posicionTooltip: String
});
</script>

<template>
    <transition name="fade">
        <div v-if="tooltipVisible && productoTooltip"
            :class="[
                'absolute left-1/2 -translate-x-1/2 z-50 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-50 shadow-xl w-64',
                posicionTooltip === 'abajo' ? 'top-full mt-2' : 'bottom-full mb-2'
            ]">
            <p><strong>Descripción:</strong> {{ productoTooltip.descripcion }}</p>
            <p><strong>Categoría:</strong> {{ productoTooltip.categoria }}</p>
            <p><strong>Stock:</strong> {{ productoTooltip.stock }}</p>
            <p><strong>U.M.:</strong> {{ productoTooltip.unidad_medida }}</p>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
</style>