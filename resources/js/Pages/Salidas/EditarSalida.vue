<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Swal from "sweetalert2";

const props = defineProps({
  proyecto: Object,
  salida: Object,
});

const user = usePage().props.auth.user;

const form = useForm({
  codigo1: props.salida.codigo1,
  codigo2: props.salida.codigo2,
  n_acta: props.salida.n_acta,
  nombre: props.salida.nombre,
  lugar: props.salida.lugar,
  distrito: props.salida.distrito,
  fecha: props.salida.fecha,
  producto: props.salida.producto,
  cantidad: props.salida.cantidad
});


// 📌 Actualizar salida
const actualizar = () => {
  if (!(user.role === 'admin' || user.role === 'equipo')) {
    Swal.fire({
      icon: "error",
      title: "Acceso denegado",
      text: "⛔ No tienes permiso para actualizar salidas.",
      confirmButtonColor: "#d33"
    });
    return;
  }

  if (!form.n_acta || !form.nombre || !form.lugar || !form.distrito || !form.fecha || !form.producto) {
    Swal.fire({
      icon: "warning",
      title: "Campos incompletos",
      text: "⚠️ Por favor, complete todos los campos requeridos.",
      confirmButtonColor: "#f59e0b"
    });
    return;
  }

  form.put(`/proyectos/${props.proyecto.id}/salidas/${props.salida.id}`, {
    onSuccess: () => {
      Swal.fire({
        icon: "success",
        title: "Éxito",
        text: "✅ Salida actualizada correctamente",
        confirmButtonColor: "#3085d6"
      }).then(() => {
        window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
      });
    }
  });
};

</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto mt-6 p-6 bg-white rounded shadow dark:bg-gray-800">
      <h1 class="text-2xl font-bold mb-6 dark:text-gray-200">Editar Salida</h1>

      <form @submit.prevent="actualizar" class="space-y-4">
        <!-- N° Acta -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">N° Acta</label>
          <input v-model="form.n_acta" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Nombre -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Nombre</label>
          <input v-model="form.nombre" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Lugar -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Lugar</label>
          <input v-model="form.lugar" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Distrito -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Distrito</label>
          <input v-model="form.distrito" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Fecha -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Fecha</label>
          <input v-model="form.fecha" type="date" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Producto -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Producto</label>
          <input v-model="form.producto" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required>
        </div>

        <!-- Cantidad -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Cantidad</label>
          <input v-model="form.cantidad" type="number" min="1"
            class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
        </div>
        <!-- Botones -->
        <div class="flex gap-2 mt-4">
          <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            Guardar Cambios
          </button>
          <a :href="`/proyectos/${props.proyecto.id}/inventario-salidas`"
            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            Volver
          </a>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
