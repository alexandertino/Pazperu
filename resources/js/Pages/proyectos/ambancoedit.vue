<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  proyecto: Object,
  acta: Object,
  tabla: String,
})

const form = useForm({
  n_acta: props.acta?.n_acta ?? '',
  fecha: props.acta?.fecha ? String(props.acta.fecha).slice(0, 10) : '',
  descripcion: props.acta?.descripcion ?? '',
  presupuestario: props.acta?.presupuestario ?? '',
  actividad: props.acta?.actividad ?? '',
  ingresos: props.acta?.ingresos ?? 0,
  egresos: props.acta?.egresos ?? 0,
})

// Estado local para el radio (no se envía)
const inventarioLocal = ref(false)

// Flash messages
const page = usePage()
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null)
const flashError = computed(() => page.props.value?.flash?.error ?? null)

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
    if (result.isConfirmed) submit()
  })
}

function submit() {
  form.put(route('proyectos.ambanco.update', { proyecto: props.proyecto.id, id: props.acta.id }), {
    onSuccess: () => {
      if (inventarioLocal.value) {
        const am_table = props.tabla || `am_banco_proyecto_${String(props.proyecto?.nombre || '').toLowerCase().replace(/\s+/g, '_')}`
        const numero = props.acta?.numero ?? props.acta?.n_acta ?? ''
        const url =
          `/proyectos/${props.proyecto.id}/inventarios/create` +
          `?am_row_id=${encodeURIComponent(props.acta.id)}` +
          `&am_table=${encodeURIComponent(am_table)}` +
          `&descripcion=${encodeURIComponent(form.descripcion || '')}` +
          `&numero=${encodeURIComponent(numero)}`
        window.location.href = url
        return
      }

      Swal.fire({
        title: 'Guardado',
        text: 'Los cambios se guardaron correctamente.',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        timerProgressBar: true,
      })
    },
    onError: () => {
      Swal.fire({
        title: 'Error',
        text: 'Ocurrió un error al guardar. Revisa los campos.',
        icon: 'error',
      })
    },
  })
}

function volver() {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 dark:text-white">
        Editar Banco — ID: {{ acta.id }}
      </h2>

      <form @submit.prevent="confirmSubmit">
        <div class="grid gap-4">

          <div>
            <label class="block text-sm font-medium dark:text-white">N° Acta</label>
            <input v-model="form.n_acta" type="text" maxlength="50"
              class="mt-1 w-full p-2 border rounded bg-gray-100 text-gray-500 cursor-not-allowed" disabled />

            <p v-if="form.errors.n_acta" class="text-red-500 text-sm">{{ form.errors.n_acta }}</p>
          </div>
          <!-- Fecha -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Fecha</label>
            <input v-model="form.fecha" type="date" class="mt-1 w-full p-2 border rounded" />
            <p v-if="form.errors.fecha" class="text-red-500 text-sm">{{ form.errors.fecha }}</p>
          </div>

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

          <!-- Ingresos -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Ingresos</label>
            <input v-model.number="form.ingresos" type="number" step="0.01" min="0"
              class="mt-1 w-full p-2 border rounded" />
            <p v-if="form.errors.ingresos" class="text-red-500 text-sm">{{ form.errors.ingresos }}</p>
          </div>

          <!-- Egresos -->
          <div>
            <label class="block text-sm font-medium dark:text-white">Egresos</label>
            <input v-model.number="form.egresos" type="number" step="0.01" min="0"
              class="mt-1 w-full p-2 border rounded" />
            <p v-if="form.errors.egresos" class="text-red-500 text-sm">{{ form.errors.egresos }}</p>
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

      <!-- Mensajes flash -->
      <div v-if="flashSuccess" class="mt-4 p-3 bg-green-100 text-green-800 rounded">{{ flashSuccess }}</div>
      <div v-if="flashError" class="mt-4 p-3 bg-red-100 text-red-800 rounded">{{ flashError }}</div>
    </div>
  </AuthenticatedLayout>
</template>
