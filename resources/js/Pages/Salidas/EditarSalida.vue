<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Swal from "sweetalert2";
import { ref } from 'vue';

const props = defineProps({
  proyecto: Object,
  salida: Object,
});

const user = usePage().props.auth.user;

const form = useForm({
  // Asegúrate de incluir los nombres que el backend espera
  producto_code: props.salida.producto_code ?? props.salida.producto ?? '',
  producto: props.salida.producto ?? '',
  producto_label: props.salida.producto_label ?? '',
  um: props.salida.um ?? '',
  persona_id: props.salida.persona_id ?? null,
  n_acta: props.salida.n_acta ?? '',
  nombre: props.salida.nombre ?? '',
  lugar: props.salida.lugar ?? '',
  distrito: props.salida.distrito ?? '',
  fecha: props.salida.fecha ? props.salida.fecha.split(' ')[0] : '',
  cantidad: props.salida.cantidad ?? 0,
});

// transformar antes de enviar: cantidad a número (useForm tiene transform)
form.transform(data => {
  return {
    ...data,
    cantidad: parseFloat(data.cantidad) || 0,
  };
});

const submitting = ref(false);

// Muestra errores de validación inline
const showValidationErrors = (errors) => {
  const messages = Object.values(errors).flat();
  Swal.fire({
    icon: 'warning',
    title: 'Errores de validación',
    html: `<ul style="text-align:left">${messages.map(m => `<li>${m}</li>`).join('')}</ul>`,
    confirmButtonColor: '#f59e0b'
  });
};

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

  // Validación rápida cliente (evitar peticiones innecesarias)
  if (!form.n_acta || !form.nombre || !form.lugar || !form.distrito || !form.fecha) {
    Swal.fire({
      icon: "warning",
      title: "Campos incompletos",
      text: "⚠️ Por favor, complete todos los campos requeridos.",
      confirmButtonColor: "#f59e0b"
    });
    return;
  }

  // comprobar cantidad mínima coherente con backend (0.0001)
  if (parseFloat(form.cantidad) <= 0) {
    Swal.fire({
      icon: "warning",
      title: "Cantidad inválida",
      text: "La cantidad debe ser mayor a 0.",
      confirmButtonColor: "#f59e0b"
    });
    return;
  }

  // ⚠️ Nueva advertencia antes de proceder
  Swal.fire({
    icon: "question",
    title: "¿Estás seguro?",
    text: "Esta acción actualizará la información de la salida. ¿Deseas continuar?",
    showCancelButton: true,
    confirmButtonText: "Sí, actualizar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33"
  }).then((result) => {
    if (result.isConfirmed) {
      submitting.value = true;

      form.put(`/proyectos/${props.proyecto.id}/salidas/${props.salida.id}`, {
        preserveState: false,
        onSuccess: () => {
          submitting.value = false;
          Swal.fire({
            icon: "success",
            title: "Éxito",
            text: "✅ Salida actualizada correctamente",
            confirmButtonColor: "#3085d6"
          }).then(() => {
            window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
          });
        },
        onError: (errors) => {
          submitting.value = false;
          showValidationErrors(errors);
        },
        onFinish: () => {
          submitting.value = false;
        }
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
          <input v-model="form.n_acta" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.n_acta" class="text-red-600 text-sm">{{ form.errors.n_acta[0] }}</p>
        </div>

        <!-- Nombre -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Nombre</label>
          <input v-model="form.nombre" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.nombre" class="text-red-600 text-sm">{{ form.errors.nombre[0] }}</p>
        </div>

        <!-- Lugar -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Lugar</label>
          <input v-model="form.lugar" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.lugar" class="text-red-600 text-sm">{{ form.errors.lugar[0] }}</p>
        </div>

        <!-- Distrito -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Distrito</label>
          <input v-model="form.distrito" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.distrito" class="text-red-600 text-sm">{{ form.errors.distrito[0] }}</p>
        </div>

        <!-- Fecha -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Fecha</label>
          <input v-model="form.fecha" type="date" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.fecha" class="text-red-600 text-sm">{{ form.errors.fecha[0] }}</p>
        </div>

        <!-- Cantidad (permitir decimales pequeños si el backend los soporta) -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Cantidad</label>
          <input v-model="form.cantidad" type="number" :step="0.0001" min="0.0001"
            class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          <p v-if="form.errors.cantidad" class="text-red-600 text-sm">{{ form.errors.cantidad[0] }}</p>
        </div>

        <!-- Campos extra que el backend podría usar -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Código producto</label>
          <input v-model="form.producto_code" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
        </div>

        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Unidad (UM)</label>
          <input v-model="form.um" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Botones -->
        <div class="flex gap-2 mt-4">
          <button :disabled="submitting" type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <span v-if="!submitting">Guardar Cambios</span>
            <span v-else>Guardando...</span>
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
