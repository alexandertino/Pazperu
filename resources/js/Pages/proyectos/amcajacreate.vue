<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
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
  egresos: null,
  inventario: false,
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

  if (form.value.descripcion && form.value.descripcion.length > 47) {
    errors.value.descripcion = 'Descripción debe tener máximo 47 caracteres.'
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

// ---- CONTROL MUTUO ingresos/egresos ----
const isIngresosDisabled = computed(() => {
  const eg = form.value.egresos
  return eg !== null && String(eg).trim() !== '' && Number(eg) !== 0
})
const isEgresosDisabled = computed(() => {
  const ing = form.value.ingresos
  return ing !== null && String(ing).trim() !== '' && Number(ing) !== 0
})
watch(() => form.value.ingresos, (val) => {
  if (val !== null && String(val).trim() !== '' && Number(val) !== 0) {
    if (form.value.egresos !== null) form.value.egresos = null
  }
  if (errors.value.ingresos) delete errors.value.ingresos
  if (isEgresosDisabled.value && errors.value.egresos) delete errors.value.egresos
})
watch(() => form.value.egresos, (val) => {
  if (val !== null && String(val).trim() !== '' && Number(val) !== 0) {
    if (form.value.ingresos !== null) form.value.ingresos = null
  }
  if (errors.value.egresos) delete errors.value.egresos
  if (isIngresosDisabled.value && errors.value.ingresos) delete errors.value.ingresos
})
// ---------------------------------------------------------------------------

// ----------- NAVEGACIÓN CON TECLADO -------------
const formEl = ref(null)

function handleKeydown(e) {
  const key = e.key
  if (!['Enter', 'ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown'].includes(key)) return

  const container = formEl.value || e.currentTarget
  if (!container) return

  const selector = [
    'input:not([type="hidden"]):not([disabled])',
    'textarea:not([disabled])',
    'select:not([disabled])',
    'button:not([disabled])'
  ].join(',')

  const elems = Array.from(container.querySelectorAll(selector)).filter(el => {
    const style = window.getComputedStyle(el)
    return style.display !== 'none' && style.visibility !== 'hidden' && el.tabIndex !== -1
  })
  if (elems.length === 0) return

  const active = document.activeElement
  let idx = elems.indexOf(active)

  if (idx === -1) {
    if (['Enter', 'ArrowRight', 'ArrowDown'].includes(key)) {
      elems[0].focus()
      e.preventDefault()
    }
    return
  }

  const isNumberInput = active.tagName === 'INPUT' && active.type === 'number'
  if (isNumberInput && (key === 'ArrowUp' || key === 'ArrowDown') && !e.altKey) {
    return
  }

  let destIdx = idx
  if (['Enter', 'ArrowRight', 'ArrowDown'].includes(key)) destIdx = Math.min(elems.length - 1, idx + 1)
  if (['ArrowLeft', 'ArrowUp'].includes(key)) destIdx = Math.max(0, idx - 1)

  if (destIdx === idx) return

  e.preventDefault()
  elems[destIdx].focus()
}

onUnmounted(() => {
  // cleanup if needed later
})
// -----------------------------------------------

/**
 * submitPayload: función que realiza la petición POST.
 * Extraída para poder confirmarla antes de ejecutar.
 */
async function submitPayload(payload) {
  submitError.value = ''
  successMessage.value = ''
  try {
    submitting.value = true
    const res = await axios.post(`/proyectos/${proyectoId}/am/guardar`, payload)

    // comportamiento original al recibir redirect (inventario)
    if (res.data && res.data.redirect) {
      await Swal.fire({
        title: 'Redirigiendo...',
        text: 'Se guardó la acta y ahora se abrirá el formulario de inventario.',
        icon: 'success',
        timer: 900,
        showConfirmButton: false,
        timerProgressBar: true,
      })
      window.location.href = res.data.redirect
      return
    }

    // Mensaje de éxito (igual que antes)
    await Swal.fire({
      title: 'Guardado',
      text: res.data.table
        ? `Registro guardado en: ${res.data.table}`
        : (res.data.message || 'Guardado correctamente.'),
      icon: 'success',
      confirmButtonText: 'OK',
      timer: 2000,
      timerProgressBar: true,
    })

    // reset form (como antes)
    resetForm()

    // EN LUGAR DE hacer window.location.reload(), intentamos:
    // 1) usar fetchDatos() si está disponible (actualiza caja/banco vía AJAX)
    // 2) si falla o no existe, hacemos un reload selectivo de Inertia (solo props necesarios)
    if (typeof fetchDatos === 'function') {
      try {
        await fetchDatos()
        if (typeof fetchMeta === 'function') {
          fetchMeta('c')
          fetchMeta('b')
        }
      } catch (err) {
        // fallback a recarga selectiva si fetchDatos falla
        router.reload({ only: ['caja', 'banco', 'salidas', 'inventarios'] })
      }
    } else {
      // si no hay fetchDatos (este componente depende de props de Inertia),
      // recargamos solo los props importantes sin recargar toda la app
      router.reload({ only: ['caja', 'banco', 'salidas', 'inventarios'] })
    }

    successMessage.value = 'Guardado correctamente y saldos actualizados.'
  } catch (err) {
    console.error("Error submit (entero):", err)
    const resp = err.response?.data
    if (resp) {
      if (resp.message) submitError.value = resp.message
      else if (resp.errors) submitError.value = Object.values(resp.errors).flat().join('; ')
      else submitError.value = JSON.stringify(resp)
    } else {
      submitError.value = err.message || 'Error al guardar'
    }

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


/**
 * onSubmit: valida y solicita confirmación antes de llamar a submitPayload.
 */
async function onSubmit() {
  successMessage.value = ''
  submitError.value = ''
  localError.value = null

  // validar primero
  if (!validateForm()) {
    console.log('validateForm failed', errors.value)
    return
  }

  // determinar prefijo / tabla como antes
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
    ingresos: form.value.ingresos != null ? Number(form.value.ingresos) : 0,
    egresos: form.value.egresos != null ? Number(form.value.egresos) : 0,
    saldo: newSaldo.value,
    tabla: targetTable,
    proyecto_id: proyectoId,
    inventario: (form.value.inventario === true || form.value.inventario === 'true' || form.value.inventario === 1)
  }

  // mostrar confirmación — se puede incluir localError para alertar al usuario
  let confirmText = '¿Deseas guardar este movimiento?'
  if (localError.value) confirmText += `\n\nNota: ${localError.value}`

  const result = await Swal.fire({
    title: 'Confirmar guardado',
    text: confirmText,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    showLoaderOnConfirm: true,
    // evitamos cerrar inmediatamente para mostrar loader si confirm -> pero la petición la manejamos aparte
    preConfirm: () => { return true } // no hace la petición aquí; usaremos result.isConfirmed abajo
  })

  if (result.isConfirmed) {
    // Ejecutar la petición real
    await submitPayload(payload)
  } else {
    // si cancela, no hacemos nada — el formulario queda como está
    // opcional: mostrar aviso
    // await Swal.fire({ title: 'Cancelado', text: 'No se guardó el registro.', icon: 'info', timer: 1200 })
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
    egresos: null,
    inventario: false,
  }
  errors.value = {}
  tableError.value = null
  localError.value = null
}

const submitButtonLabel = computed(() => {
  if (submitting.value) return 'Guardando...';
  return form.value.inventario ? 'Guardar - Inventario' : 'Guardar';
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto p-6">
      <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 shadow rounded">
        <h2 class="text-xl font-bold mb-4 dark:text-white">Registrar Movimiento — AM (C / B)</h2>

        <!-- PANEL resumen C / B -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 ">
          <div class="p-4 border rounded">
            <div class="flex items-center justify-between">
              <div class="dark:text-white">
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
                  class="px-3 py-1 border rounded text-sm dark:text-white ">Actualizar</button>
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
                  class="px-3 py-1 border rounded text-sm dark:text-white">Actualizar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- FORM -->
        <form @submit.prevent="onSubmit" novalidate @keydown.capture="handleKeydown" ref="formEl">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium dark:text-white">N°</label>
              <input v-model="form.n_acta" @input="onNActaInput" type="text"
                class="mt-1 w-full p-2 border rounded dark:text-white dark:bg-gray-700"
                placeholder="Ej: C-000 o B-000" />
              <p class="text-xs text-gray-500 mt-1">Sugerido actual: <strong>{{ currentSuggested }}</strong></p>
              <p v-if="errors.n_acta" class="text-red-500 text-sm mt-1">{{ errors.n_acta }}</p>
              <p v-if="tableError" class="text-red-500 text-sm mt-1">{{ tableError }}</p>
              <p v-if="localError" class="text-yellow-600 text-sm mt-1">{{ localError }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Fecha</label>
              <input v-model="form.fecha" type="date"
                class="mt-1  dark:text-white dark:bg-gray-700 w-full p-2 border rounded" />
              <p v-if="errors.fecha" class="text-red-500 text-sm mt-1">{{ errors.fecha }}</p>
            </div>

            <div class="md:col-span-2">
              <label class=" dark:text-white block text-sm font-medium">
                Descripción <span class="text-xs  text-gray-500">({{ form.descripcion.length }}/47)</span>
              </label>
              <textarea v-model="form.descripcion" maxlength="47"
                class="mt-1 w-full p-2 border rounded dark:text-white dark:bg-gray-700 " rows="3"></textarea>
              <p v-if="errors.descripcion" class="text-red-500 text-sm mt-1">{{ errors.descripcion }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Presupuestario</label>
              <input v-model="form.presupuestario" type="text"
                class="mt-1 w-full p-2 border rounded dark:text-white dark:bg-gray-700" placeholder="0000000" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Actividad</label>
              <input ref="actividadRef" v-model="form.actividad" type="text" @focus="onActividadFocus"
                @click="onActividadClick" @keydown="onActividadKeydown" @input="onActividadInput"
                class="mt-1 w-full p-2 border rounded dark:text-white dark:bg-gray-700" placeholder="A.0.0.00" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Ingresos</label>
              <input v-model="form.ingresos" type="number" step="0.01" min="0" :disabled="isIngresosDisabled" :class="[
                'mt-1 w-full p-2 border rounded',
                'dark:text-white',
                isIngresosDisabled ? 'disabled-input' : 'dark:bg-gray-700'
              ]" />
              <p v-if="errors.ingresos" class="text-red-500 text-sm mt-1">{{ errors.ingresos }}</p>
              <p v-else-if="isIngresosDisabled" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Bloqueado porque existe un egreso distinto de 0. Ponga 0 en Egresos para editar.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Egresos</label>
              <input v-model="form.egresos" type="number" step="0.01" min="0" :disabled="isEgresosDisabled" :class="[
                'mt-1 w-full p-2 border rounded',
                'dark:text-white',
                isEgresosDisabled ? 'disabled-input' : 'dark:bg-gray-700'
              ]" />
              <p v-if="errors.egresos" class="text-red-500 text-sm mt-1">{{ errors.egresos }}</p>
              <p v-else-if="isEgresosDisabled" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Bloqueado porque existe un ingreso distinto de 0. Ponga 0 en Ingresos para editar.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Saldo anterior</label>
              <input :value="previousSaldoFormatted" readonly
                class="mt-1 w-full p-2 border rounded bg-gray-100 dark:text-white dark:bg-gray-700" />
            </div>

            <div>
              <label class="block text-sm font-medium dark:text-white">Nuevo saldo</label>
              <input :value="newSaldoFormatted" readonly
                class="mt-1 w-full p-2 border rounded bg-gray-100 dark:text-white dark:bg-gray-700" />
            </div>

            

            <div class="md:col-span-2 flex flex-col gap-2">
              <!-- Radios Requiere acta (sin bg) -->
              <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 cursor-pointer dark:text-white">
                  <input type="radio" v-model="form.inventario" :value="true" />
                  <span>Sí</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer dark:text-white">
                  <input type="radio" v-model="form.inventario" :value="false" />
                  <span>No</span>
                </label>
                <p class="text-sm text-gray-500 ml-4">Requiere acta / crear inventario después</p>
              </div>

              <!-- Botones -->
              <div class="flex items-center justify-between gap-2">
                <button type="button" @click="volverATabla" class="px-4 py-2 bg-gray-400 rounded dark:bg-gray-600 dark:text-white">Volver</button>
                <button :disabled="submitting" type="submit" class="px-4 py-2 bg-blue-600 text-white  rounded">
                  {{ submitButtonLabel }}
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

<style scoped>
/* opcional: destacar el elemento enfocado para mejor UX de navegación con teclado */
input:focus,
textarea:focus,
select:focus,
button:focus {
  outline: 2px solid rgba(37, 99, 235, 0.6);
  outline-offset: 2px;
}

.disabled-input {
  opacity: 0.6;
  cursor: not-allowed;
  background-color: #f3f4f6;
  pointer-events: none;
  color: rgba(0, 0, 0, 0.6);
  border-color: #e5e7eb;
}

:deep(.dark) .disabled-input,
.dark .disabled-input {
  background-color: #111827;
  color: rgba(255, 255, 255, 0.7);
  border-color: #374151;
}
</style>
