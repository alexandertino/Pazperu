<script setup>
defineProps({
    user: Object,
    proyecto: Object
});

defineEmits(['exportar-excel']);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button 
            v-if="user.role === 'admin'" 
            @click="$emit('exportar-excel')"
            class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors duration-200"
        >
            📊 Exportar
        </button>
    </div>
</template>