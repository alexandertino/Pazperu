<script setup>
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    salida: Object,
    proyecto: Object,
    user: Object
});

const emit = defineEmits(['estado-cambiado']);

const cambiarEstado = async () => {
    const nuevoEstado = props.salida.estado === 'pendiente' ? 'aceptado' : 'pendiente';

    const confirm = await Swal.fire({
        title: '¿Cambiar estado?',
        text: `Se actualizará a "${nuevoEstado}" para todas las salidas con N° Acta "${props.salida.n_acta}".`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!confirm.isConfirmed) return;

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const payload = { estado: nuevoEstado };
        const res = await axios.patch(
            `/proyectos/${props.proyecto.id}/salidas/${props.salida.id}`,
            payload
        );

        const estadoFinal = res.data?.estado ?? nuevoEstado;
        emit('estado-cambiado', estadoFinal);

        Swal.fire({
            icon: 'success',
            title: 'Estado actualizado',
            text: `Todas las salidas con N° Acta "${props.salida.n_acta}" ahora están en "${estadoFinal}".`
        });
    } catch (err) {
        console.error('Error al actualizar salida', props.salida.id, err.response ?? err);
        
        let texto = 'No se pudo actualizar el estado.';
        if (err.response?.status === 403) texto = 'No tienes permiso.';
        else if (err.request && !err.response) texto = 'El servidor no respondió.';
        
        Swal.fire({ icon: 'error', title: 'Error', text: texto });
    }
};
</script>

<template>
    <button 
        @click="cambiarEstado"
        :disabled="user.role !== 'admin'" 
        :class="[
            'px-3 py-1 rounded-lg font-semibold text-white text-xs shadow transition',
            salida.estado === 'pendiente'
                ? 'bg-red-500 hover:bg-red-600'
                : 'bg-green-500 hover:bg-green-600',
            user.role !== 'admin' ? 'opacity-50 cursor-not-allowed' : ''
        ]"
    >
        {{ salida.estado }}
    </button>
</template>