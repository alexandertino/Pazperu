<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    proyecto: Object,
    inventario: Object
});

// ⚡ Inicializamos todos los campos incluyendo comentario
const form = useForm({
    fecha: props.inventario.fecha,
    descripcion: props.inventario.descripcion,
    categoria: props.inventario.categoria,
    unidad_medida: props.inventario.unidad_medida,
    entradas: props.inventario.entradas,
    salidas: props.inventario.salidas,
    stock: props.inventario.stock,
    precio: props.inventario.precio,
    solicitado_por: props.inventario.solicitado_por,
    proyecto_lg: props.inventario.proyecto_lg,
    comentario: props.inventario.comentario ?? "" // si está null → lo deja vacío
});

// 🔄 Si quieres que siempre aparezca el comentario, ponlo en true.
// Si prefieres mostrar solo cuando haya algo, hazlo dinámico.
const mostrarComentario = true;

const actualizar = () => {
    if (!form.fecha || !form.descripcion || !form.categoria || !form.unidad_medida) {
        Swal.fire('⚠️ Campos incompletos', 'Por favor, complete todos los campos requeridos.', 'warning');
        return;
    }

    Swal.fire({
        title: '¿Guardar cambios?',
        text: "Se actualizará el inventario",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.put(`/proyectos/${props.proyecto.id}/inventarios/${props.inventario.id}`, {
            onSuccess: () => {
                Swal.fire('✅ Actualizado', 'Inventario actualizado correctamente', 'success')
                    .then(() => {
                        window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
                    });
            },
            onError: (errors) => {
                console.error("❌ Error de validación:", errors);
                Swal.fire('⚠️ Error', 'Revise los datos ingresados.', 'error');
            }
        });
    });
};
</script>


<template>
    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto mt-6 p-6 bg-white dark:bg-gray-800 rounded dark:text-gray-800 shadow">
            <h1 class="text-2xl font-bold mb-6 dark:text-gray-200">Editar Inventario</h1>

            <form @submit.prevent="actualizar" class="space-y-4">
                <!-- Fecha -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Fecha</label>
                    <input v-model="form.fecha" type="date"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Descripción</label>
                    <input v-model="form.descripcion" type="text"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Categoria -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Categoría</label>
                    <input v-model="form.categoria" type="text"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Unidad de Medida -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Unidad de Medida</label>
                    <input v-model="form.unidad_medida" type="text"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Precio -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Precio (S/)</label>
                    <input v-model="form.precio" type="number" step="0.01" min="0"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Solicitado por -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Solicitado por</label>
                    <input v-model="form.solicitado_por" type="text"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <!-- Proyecto -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">A cargo</label>
                    <input v-model="form.proyecto_lg" type="text"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                        required>
                </div>

                <div v-if="mostrarComentario" class="mt-3">
                    <label class="block font-bold mb-1 dark:text-gray-200">Comentario (opcional)</label>
                    <textarea v-model="form.comentario" rows="3"
                        placeholder="Escribe un comentario sobre esta salida..."
                        class="w-full p-2 border rounded resize-none dark:bg-gray-700 dark:text-white"></textarea>
                </div>

                <!-- 🔒 Entradas, Salidas y Stock no editables -->

                <!-- Botones -->
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Guardar Cambios
                    </button>
                    <a :href="`/proyectos/${props.proyecto.id}/inventario-salidas`"
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700">
                        Volver
                    </a>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
