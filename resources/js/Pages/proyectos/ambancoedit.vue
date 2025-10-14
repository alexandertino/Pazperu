<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 dark:text-white">
        Editar Banco — ID: {{ acta.id }}
      </h2>

      <form @submit.prevent="confirmSubmit">
        <div class="grid gap-4">

          <!-- Descripción -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Descripción</label>
            <textarea v-model="form.descripcion" class="mt-1 w-full p-2 border rounded" rows="2"></textarea>
            <p v-if="form.errors.descripcion" class="text-red-500 text-sm">{{ form.errors.descripcion }}</p>
          </div>

          <!-- Presupuestario -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Presupuestario</label>
            <input v-model="form.presupuestario" type="text" class="mt-1 w-full p-2 border rounded" />
            <p v-if="form.errors.presupuestario" class="text-red-500 text-sm">{{ form.errors.presupuestario }}</p>
          </div>

          <!-- Actividad -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Actividad</label>
            <input v-model="form.actividad" type="text" class="mt-1 w-full p-2 border rounded" />
            <p v-if="form.errors.actividad" class="text-red-500 text-sm">{{ form.errors.actividad }}</p>
          </div>

          <!-- Acción -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Acción</label>
            <select v-model="form.accion" class="mt-1 w-full p-2 border rounded">
              <option value="">-- (ninguna) --</option>
              <option value="transf">Transf</option>
              <option value="sueldo">Sueldo</option>
              <option value="gb">GB</option>
              <option value="ch">CH</option>
              <option value="ingreso">Ingreso</option>
            </select>
            <p v-if="form.errors.accion" class="text-red-500 text-sm">{{ form.errors.accion }}</p>
          </div>

        </div>

        <!-- Botones -->
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="volver" class="px-4 py-2 border rounded">Volver</button>
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
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  proyecto: Object,
  acta: Object,
  tabla: String,
})

const form = useForm({
  descripcion: props.acta.descripcion ?? '',
  presupuestario: props.acta.presupuestario ?? '',
  actividad: props.acta.actividad ?? '',
  accion: props.acta.accion ?? '',
})

const page = usePage()
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null)
const flashError   = computed(() => page.props.value?.flash?.error ?? null)

function confirmSubmit() {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Se guardarán los cambios realizados en este banco.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
  }).then((result) => {
    if (result.isConfirmed) {
      submit()
    }
  })
}

function submit() {
  form.put(route('proyectos.ambanco.update', { proyecto: props.proyecto.id, id: props.acta.id }))
}

function volver() {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
}
</script>
