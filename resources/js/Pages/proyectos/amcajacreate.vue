<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto p-6">
      <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 shadow rounded">
        <h2 class="text-xl font-bold mb-4 dark:text-white">Registrar Movimiento — AM (C / B)</h2>

        <!-- PANEL resumen C / B -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 ">
          <div class="p-4 border rounded">
            <div class="flex items-center justify-between">
              <div  class="dark:text-white" >
                <div class="text-sm text-gray-600">Caja — <strong>C</strong></div>
                <div class="text-lg font-medium">{{ metas.c.last_n_acta || '—' }}</div>
                <div class="text-xs text-gray-500">Siguiente: <strong>{{ metas.c.next_n_acta || '—' }}</strong></div>
                <div class="text-xs text-gray-500">Saldo anterior: <strong>{{ formatCurrency(metas.c.previous_saldo)
                    }}</strong></div>
              </div>
              <div class="flex flex-col gap-2">
                <button @click="useSuggested('c')" type="button"
                  class="px-3 py-1 bg-blue-500 text-white rounded text-sm">Usar Sugerido</button>
                <button @click="refreshMeta('c')" type="button"
                  class="px-3 py-1 border rounded text-sm">Actualizar</button>
              </div>
            </div>
          </div>

          <div class="p-4 border rounded">
            <div class="flex items-center justify-between">
              <div class="dark:text-white">
                <div class="text-sm text-gray-600">Banco — <strong>B</strong></div>
                <div class="text-lg font-medium">{{ metas.b.last_n_acta || '—' }}</div>
                <div class="text-xs text-gray-500">Siguiente: <strong>{{ metas.b.next_n_acta || '—' }}</strong></div>
                <div class="text-xs text-gray-500">Saldo anterior: <strong>{{ formatCurrency(metas.b.previous_saldo)
                    }}</strong></div>
              </div>
              <div class="flex flex-col gap-2">
                <button @click="useSuggested('b')" type="button"
                  class="px-3 py-1 bg-blue-500 text-white rounded text-sm">Usar Sugerido</button>
                <button @click="refreshMeta('b')" type="button"
                  class="px-3 py-1 border rounded text-sm">Actualizar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- FORM -->
        <form @submit.prevent="onSubmit" novalidate>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium dark:text-white">N° Acta</label>
              <input v-model="form.n_acta" @input="onNActaInput" type="text" class="mt-1 w-full p-2 border rounded"
                placeholder="Ej: C-001 o B-2025 (opcional)" />
              <p class="text-xs text-gray-500 mt-1">Sugerido actual: <strong>{{ currentSuggested }}</strong></p>
              <p v-if="errors.n_acta" class="text-red-500 text-sm mt-1">{{ errors.n_acta }}</p>
              <p v-if="tableError" class="text-red-500 text-sm mt-1">{{ tableError }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Fecha</label>
              <input v-model="form.fecha" type="date" class="mt-1 w-full p-2 border rounded" />
              <p v-if="errors.fecha" class="text-red-500 text-sm mt-1">{{ errors.fecha }}</p>
            </div>

            <div class="md:col-span-2">
              <label class=" dark:text-white block text-sm font-medium">
                Descripción <span class="text-xs  text-gray-500">({{ form.descripcion.length }}/50)</span>
              </label>
              <textarea v-model="form.descripcion" maxlength="50" class="mt-1 w-full p-2 border rounded"
                rows="3"></textarea>
              <p v-if="errors.descripcion" class="text-red-500 text-sm mt-1">{{ errors.descripcion }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Presupuestario</label>
              <input v-model="form.presupuestario" type="text" class="mt-1 w-full p-2 border rounded"
                placeholder="----------" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Actividad</label>
              <input v-model="form.actividad" type="text" class="mt-1 w-full p-2 border rounded"
                placeholder="----------" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Ingresos</label>
              <input v-model="form.ingresos" type="number" step="0.01" class="mt-1 w-full p-2 border rounded"
                placeholder="Opcional" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Egresos</label>
              <input v-model="form.egresos" type="number" step="0.01" class="mt-1 w-full p-2 border rounded" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Saldo anterior</label>
              <input :value="previousSaldoFormatted" readonly class="mt-1 w-full p-2 border rounded bg-gray-100" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Nuevo saldo</label>
              <input :value="newSaldoFormatted" readonly class="mt-1 w-full p-2 border rounded bg-gray-100" />
            </div>

            <div v-if="currentType === 'b'" class="md:col-span-2 p-4 border rounded bg-gray-50">
              <label class="block text-sm font-medium mb-2">Acción (solo para B)</label>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in accionOptions" :key="opt.value"
                  class="inline-flex items-center gap-2 p-2 border rounded cursor-pointer">
                  <input type="radio" v-model="form.accion" :value="opt.value" />
                  <span class="text-sm">{{ opt.label }}</span>
                </label>
              </div>
              <p v-if="errors.accion" class="text-red-500 text-sm mt-1">{{ errors.accion }}</p>
            </div>

            <div class="md:col-span-2 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm dark:text-white">Tabla destino sugerida: <strong class="capitalize">{{ suggestedTableFullName
                }}</strong>
                </p>
                <p v-if="localError" class="text-yellow-700 text-sm mt-1">{{ localError }}</p>
              </div>

              <div class="flex gap-2">
                <button type="button" @click="volverATabla" class="px-4 py-2 border rounded">Volver</button>
                <button :disabled="submitting" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                  {{ submitting ? 'Guardando...' : 'Guardar' }}
                </button>

              </div>
            </div>
          </div>
        </form>

        <div v-if="successMessage" class="mt-4 p-3 bg-green-100 text-green-800 rounded">
          {{ successMessage }}
        </div>

        <div v-if="submitError" class="mt-4 p-3 bg-red-100 text-red-800 rounded">
          {{ submitError }}
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'


const props = defineProps({
  proyecto: { type: Object, required: true }
})

const proyectoId = props.proyecto.id

const caja = ref([])
const banco = ref([])

async function fetchDatos() {
  try {
    const res = await axios.get(`/proyectos/${props.proyecto.id}/am/datos`)
    if (res.data.ok) {
      caja.value = res.data.caja || []
      banco.value = res.data.banco || []
    }
  } catch (err) {
    console.error("Error cargando datos AM:", err)
  }
}

onMounted(() => {
  fetchDatos()
  fetchMeta('c')
  fetchMeta('b')
})


const form = ref({
  n_acta: '',
  fecha: '',
  descripcion: '',
  presupuestario: '',
  actividad: '',
  ingresos: null,
  egresos: 0,
  accion: null,
})
const metaData = ref({ saldo_anterior: null, ultimo_acta: null })
const metas = ref({
  c: { next_n_acta: null, last_n_acta: null, previous_saldo: 0, tabla: null },
  b: { next_n_acta: null, last_n_acta: null, previous_saldo: 0, tabla: null },
})

const errors = ref({})
const tableError = ref(null)
const localError = ref(null)
const submitting = ref(false)
const successMessage = ref('')
const submitError = ref('')

const accionOptions = [
  { value: 'transf', label: 'Transferencia' },
  { value: 'sueldo', label: 'Sueldo' },
  { value: 'gb', label: 'GB' },
  { value: 'ch', label: 'CH' },
  { value: 'ingreso', label: 'Ingreso' },
]

function formatCurrency(v) {
  const n = Number(v || 0)
  return n.toFixed(2)
}

const previousSaldo = computed(() => {
  const t = currentType.value || 'c'
  const datos = t === 'c' ? caja.value : banco.value
  if (datos.length > 0) {
    return Number(datos[datos.length - 1].saldo || 0)
  }
  return Number(metas.value[t].previous_saldo || 0)
})
const previousSaldoFormatted = computed(() => formatCurrency(previousSaldo.value))

const newSaldo = computed(() => {
  const ing = Number(form.value.ingresos) || 0
  const eg = Number(form.value.egresos) || 0
  return previousSaldo.value + ing - eg
})
const newSaldoFormatted = computed(() => formatCurrency(newSaldo.value))

function proyectoBase() {
  return String(props.proyecto.nombre || '').toLowerCase().replace(/\s+/g, '_')
}

function tablaCompletaPara(type) {
  const base = proyectoBase()
  if (type === 'c') return `am_caja_proyecto_${base}`
  if (type === 'b') return `am_banco_proyecto_${base}`
  return 'desconocida'
}

function determineTargetTypeFromNActa(nActa) {
  if (!nActa) return { ok: false, error: null }
  const first = nActa.trim().charAt(0).toLowerCase()
  if (first === 'c') return { ok: true, type: 'c', label: 'C → caja' }
  if (first === 'b') return { ok: true, type: 'b', label: 'B → banco' }
  return { ok: false, error: 'Prefijo inválido. Debe empezar con C o B.' }
}

const suggestedTableFullName = computed(() => {
  const source = form.value.n_acta || metas.value.c.next_n_acta || metas.value.b.next_n_acta || ''
  const res = determineTargetTypeFromNActa(source)
  if (!res.ok) return '—'
  return tablaCompletaPara(res.type)
})

const currentType = computed(() => {
  const source = form.value.n_acta || metas.value.c.next_n_acta || metas.value.b.next_n_acta || ''
  const res = determineTargetTypeFromNActa(source)
  return res.ok ? res.type : null
})

const currentSuggested = computed(() => {
  const t = currentType.value || 'c'
  return metas.value[t].next_n_acta || '—'
})

const hasFieldErrors = computed(() => Object.keys(errors.value).length > 0)
const canSubmit = computed(() => {
  if (submitting.value) return false
  if (hasFieldErrors.value) return false
  if (tableError.value && (form.value.n_acta && form.value.n_acta.trim().length > 0)) return false
  return true
})

async function fetchMeta(prefix) {
  tableError.value = null
  try {
    const url = `/proyectos/${proyectoId}/am/meta?prefix=${encodeURIComponent(prefix)}`
    const res = await fetch(url)
    const json = await res.json().catch(() => ({}))
    if (!res.ok) {
      tableError.value = (json && json.message) || 'Error al obtener meta'
      metas.value[prefix] = { next_n_acta: null, last_n_acta: null, previous_saldo: 0, tabla: null }
      return
    }
    metas.value[prefix] = {
      next_n_acta: json.next_n_acta,
      last_n_acta: json.last_n_acta || null,
      previous_saldo: Number(json.previous_saldo || 0),
      tabla: json.tabla || null
    }
  } catch (err) {
    tableError.value = err.message || 'Error de red'
    metas.value[prefix] = { next_n_acta: null, last_n_acta: null, previous_saldo: 0, tabla: null }
  }
}

function useSuggested(prefix) {
  if (metas.value[prefix] && metas.value[prefix].next_n_acta) {
    form.value.n_acta = metas.value[prefix].next_n_acta
  }
}

function refreshMeta(prefix) {
  fetchMeta(prefix)
}

function onNActaInput() {
  if (errors.value.n_acta) delete errors.value.n_acta
  tableError.value = null
  localError.value = null

  const trimmed = (form.value.n_acta || '').trim()
  if (trimmed.length === 0) return

  const res = determineTargetTypeFromNActa(trimmed)
  if (!res.ok) {
    tableError.value = res.error
  } else {
    fetchMeta(res.type)
  }
}

watch(() => form.value.n_acta, (v) => {
  if (!v || v.trim().length === 0) {
    tableError.value = null
  }
})

function validateForm() {
  errors.value = {}
  tableError.value = null
  localError.value = null

  if (!form.value.fecha) errors.value.fecha = 'Fecha es requerida.'

  const res = determineTargetTypeFromNActa(form.value.n_acta)
  if (form.value.n_acta && !res.ok) {
    errors.value.n_acta = res.error || 'N° Acta inválido.'
  }

  if (form.value.descripcion && form.value.descripcion.length > 50) {
    errors.value.descripcion = 'Descripción debe tener máximo 50 caracteres.'
  }

  if ((res.ok && res.type === 'b') && form.value.accion && !accionOptions.some(o => o.value === form.value.accion)) {
    errors.value.accion = 'Acción inválida.'
  }

  return Object.keys(errors.value).length === 0
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const csrfMeta = document.querySelector('meta[name="csrf-token"]')
if (csrfMeta?.content) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content
}

const volverATabla = () => {
    window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};

async function onSubmit() {
  successMessage.value = ''
  submitError.value = ''
  localError.value = null

  if (!validateForm()) {
    console.log('validateForm failed', errors.value)
    return
  }

  let prefixToSend = null
  if (!form.value.n_acta || form.value.n_acta.trim() === '') {
    prefixToSend = currentType.value || 'c'
    localError.value = 'N° Acta vacío — se usará prefijo "' + prefixToSend.toUpperCase() + '" por defecto. Corrígelo si quieres otro.'
  } else {
    const res = determineTargetTypeFromNActa(form.value.n_acta)
    if (!res.ok) {
      tableError.value = res.error || 'Prefijo inválido.'
      return
    }
    prefixToSend = res.type
  }

  const targetTable = tablaCompletaPara(prefixToSend)
  if (!targetTable) {
    tableError.value = 'No se pudo determinar tabla destino.'
    return
  }

  const payload = {
    n_acta: form.value.n_acta || metas.value[prefixToSend].next_n_acta,
    fecha: form.value.fecha,
    descripcion: form.value.descripcion || '',
    presupuestario: form.value.presupuestario || '----------',
    actividad: form.value.actividad || '----------',
    ingresos: form.value.ingresos ? Number(form.value.ingresos) : 0,
    egresos: form.value.egresos ? Number(form.value.egresos) : 0,
    saldo: newSaldo.value,
    accion: prefixToSend === 'b' ? (form.value.accion || null) : null,
    tabla: targetTable,
    proyecto_id: proyectoId
  }

  console.log('Enviando payload a /am/guardar:', payload)

  try {
    submitting.value = true
    const res = await axios.post(`/proyectos/${proyectoId}/am/guardar`, payload)
    console.log('respuesta del servidor:', res.data)
    // Mostrar SweetAlert2 con la info del server y reiniciar la página al confirmar
    await Swal.fire({
      title: 'Guardado',
      text: res.data.table
        ? `Registro guardado en: ${res.data.table}`
        : (res.data.message || 'Guardado correctamente.'),
      icon: 'success',
      confirmButtonText: 'OK',
      timer: 2500,
      timerProgressBar: true,
    })
    // opcional: limpiar formulario (aunque se recargará la página)
    resetForm()
    // recargar la página para ver los cambios
    window.location.reload()
  } catch (err) {
    console.error("Error submit (entero):", err)
    console.error("err.response:", err.response)
    console.error("err.response.data:", err.response?.data)
    console.error("err.response.status:", err.response?.status)
    console.error("err.response.headers:", err.response?.headers)

    const resp = err.response?.data
    if (resp) {
      if (resp.message) submitError.value = resp.message
      else if (resp.errors) submitError.value = Object.values(resp.errors).flat().join('; ')
      else submitError.value = JSON.stringify(resp)
    } else {
      submitError.value = err.message || 'Error al guardar'
    }

    // Mostrar SweetAlert2 en caso de error (opcional)
    Swal.fire({
      title: 'Error',
      text: submitError.value || 'No se pudo guardar.',
      icon: 'error',
      confirmButtonText: 'Ok'
    })
  } finally {
    submitting.value = false
  }

}


function resetForm() {
  form.value = {
    n_acta: '',
    fecha: '',
    descripcion: '',
    presupuestario: '',
    actividad: '',
    ingresos: null,
    egresos: 0,
    accion: null,
  }
  errors.value = {}
  tableError.value = null
  localError.value = null
}
</script>
