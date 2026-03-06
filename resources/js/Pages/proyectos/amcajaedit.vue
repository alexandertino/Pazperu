<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 dark:text-white">
        Editar Caja — ID: {{ acta.id }}
      </h2>

      <form @submit.prevent="confirmSubmit" class="grid grid-cols-1 gap-4">
        <div>
          <label class="block text-sm font-medium dark:text-white">N° Acta</label>
          <input v-model="form.n_acta" type="text" maxlength="50"
            class="mt-1 w-full p-2 border rounded bg-gray-100 text-gray-500 cursor-not-allowed" disabled />

          <p v-if="form.errors.n_acta" class="text-red-500 text-sm">{{ form.errors.n_acta }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-white">Fecha</label>
          <input v-model="form.fecha" type="date" class="mt-1 w-full p-2 border rounded" />
          <p v-if="form.errors.fecha" class="text-red-500 text-sm">{{ form.errors.fecha }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-white">Descripción</label>
          <textarea v-model="form.descripcion" maxlength="255" class="mt-1 w-full p-2 border rounded"
            rows="3"></textarea>
          <p v-if="form.errors.descripcion" class="text-red-500 text-sm">{{ form.errors.descripcion }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-white">Presupuestario</label>
          <input v-model="form.presupuestario" type="text" maxlength="100" class="mt-1 w-full p-2 border rounded" />
          <p v-if="form.errors.presupuestario" class="text-red-500 text-sm">{{ form.errors.presupuestario }}</p>
        </div>


        <div>
          <label class="block text-sm font-medium dark:text-white">Actividad</label>
          <input v-model="form.actividad" type="text" maxlength="10" class="mt-1 w-full p-2 border rounded" />
          <p v-if="form.errors.actividad" class="text-red-500 text-sm">{{ form.errors.actividad }}</p>
        </div>

        <!-- CONTROL LOCAL: ¿Crear vinculación de inventario después? -->
        <div>
          <label class="block text-sm font-medium dark:text-white mb-1">¿Crear vinculación de inventario?</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-1">
              <input type="radio" v-model="inventarioLocal" :value="true" /> Sí
            </label>
            <label class="flex items-center gap-1">
              <input type="radio" v-model="inventarioLocal" :value="false" /> No
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium dark:text-white">Ingresos (S/.)</label>
          <input v-model.number="form.ingresos" type="number" step="0.01" min="0"
            class="mt-1 w-full p-2 border rounded" />
          <p v-if="form.errors.ingresos" class="text-red-500 text-sm">{{ form.errors.ingresos }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-white">Egresos (S/.)</label>
          <input v-model.number="form.egresos" type="number" step="0.01" min="0"
            class="mt-1 w-full p-2 border rounded" />
          <p v-if="form.errors.egresos" class="text-red-500 text-sm">{{ form.errors.egresos }}</p>
        </div>



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
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  proyecto: Object,
  acta: Object,
  tabla: String,
})

// Form (NO incluimos inventario aquí porque no debe enviarse)
const form = useForm({
  n_acta: props.acta?.n_acta ?? '',
  fecha: props.acta?.fecha ? String(props.acta.fecha).slice(0, 10) : '',
  descripcion: props.acta?.descripcion ?? '',
  presupuestario: props.acta?.presupuestario ?? '',
  actividad: props.acta?.actividad ?? 'A.',
  ingresos: props.acta?.ingresos ?? 0,
  egresos: props.acta?.egresos ?? 0,
});


// Estado local para la opción Sí/No — NO se envía al servidor
const inventarioLocal = ref(false) // por defecto 'No'; el usuario puede elegir 'Sí'

// Flash messages
const page = usePage()
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null)
const flashError = computed(() => page.props.value?.flash?.error ?? null)

// Confirmación con Swal
function confirmSubmit() {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Se guardarán los cambios realizados en esta caja.',
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

// Enviar PUT usando useForm; NO enviamos inventarioLocal.
// Usamos opciones onSuccess / onError para actuar después.
function submit() {
  form.put(route('proyectos.amcaja.update', {
    proyecto: props.proyecto.id,
    id: props.acta.id
  }), {
    onSuccess: () => {
      if (inventarioLocal.value) {
        // Construimos la URL de vinculación igual que la original:
        const url = `/proyectos/${props.proyecto.id}/inventarios/create` +
          `?am_row_id=${props.acta.id}` +
          `&am_table=${props.tabla}` + // cambia si el nombre de la tabla varía
          `&descripcion=${encodeURIComponent(form.descripcion)}` +
          `&numero=${encodeURIComponent(form.n_acta)}`

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
    }
  })
}


function volver() {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
}
</script>
