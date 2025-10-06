<script setup>
import Authenticated from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router, usePage} from '@inertiajs/vue3';
import {ref, computed, reactive} from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Proyecto from '../Proyecto.vue';

const user = usePage().props.auth.user;

const props = defineProps({
    Proyecto : Object,
    inventarios: Array,
    salidas: Array,
    caja: Array,
    banco: Array,
    easy: Array,
    user: Object
});

const toggleComentario = (id) => {
    comentarioActivo.value = comentarioActivo.value === id ? null : id
}


</script>

<template>
    <Authenticated>
        <h1>hola</h1>
    </Authenticated>
</template>