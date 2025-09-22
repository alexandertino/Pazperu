<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 dark:text-white">Editar Descripción — ID: {{ acta.id }}</h2>

      <form @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium dark:text-white">Descripción</label>
          <textarea v-model="form.descripcion" maxlength="50" class="mt-1 w-full p-2 border rounded" rows="3"></textarea>
          <p v-if="form.errors.descripcion" class="text-red-500 text-sm">{{ form.errors.descripcion }}</p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="volver" class="px-4 py-2 border rounded">Cancelar</button>
          <button :disabled="form.processing" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
            {{ form.processing ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </form>

      <div v-if="flashSuccess" class="mt-4 p-3 bg-green-100 text-green-800 rounded">{{ flashSuccess }}</div>
      <div v-if="flashError" class="mt-4 p-3 bg-red-100 text-red-800 rounded">{{ flashError }}</div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  proyecto: Object,
  acta: Object,
  tabla: String,
});

// form con sólo descripcion
const form = useForm({
  descripcion: props.acta.descripcion || '',
});

// usar usePage() correctamente (importado arriba)
const page = usePage();
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null);
const flashError   = computed(() => page.props.value?.flash?.error ?? null);

function submit() {
  form.put(route('proyectos.am.update', { proyecto: props.proyecto.id, id: props.acta.id }), {
    onSuccess: () => {
      // la redirección del servidor actualizará los flash messages
    },
    onError: () => {
      // errores de validación quedan en form.errors
    }
  });
}

function volver() {
  window.history.back();
}
</script>
