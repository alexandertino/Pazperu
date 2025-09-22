<script setup>
/*
  easycreate.vue - Script corregido
  - IMPORTANTE: importa usePage desde @inertiajs/vue3, no desde 'vue'
  - Añadí campo `fecha` y un cuadro condicional junto a `bailleur_fondos`.
*/

import { reactive, ref, computed, watch, onMounted, defineProps } from 'vue';
import { usePage } from '@inertiajs/vue3';            // <-- CORRECTO
import axios from 'axios';
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // asegúrate que la ruta es correcta

// Props e Inertia page
const props = defineProps({
  proyecto: { type: Object, required: false, default: () => ({ id: 0, nombre: '' }) },
  prefill: { type: Object, default: () => ({}) },
  user: { type: Object, required: false, default: () => (null) }
});

const page = usePage(); // Inertia page (si el servidor puso prefill en page.props lo tomamos)

// CSRF
const tokenMeta = document.querySelector('meta[name="csrf-token"]');
if (tokenMeta) axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
axios.defaults.headers.common['Accept'] = 'application/json';

/* Estados */
const monedaLocal = ref('PEN');
const monedaGestion = ref('EUR');
const tipoCambio = ref(Number(props.prefill.tipo_cambio ?? 0) || 0);

const errors = reactive({});
const loadingPrefill = ref(false);
const submitting = ref(false);

// Preferimos props.prefill (si fue pasado explícitamente), si no usamos page.props.prefill
const serverPrefill = (props.prefill && Object.keys(props.prefill).length > 0)
  ? props.prefill
  : (page.props?.prefill ?? {});

// DEBUG: ver qué llega
console.log('serverPrefill:', serverPrefill);

/* Form */
const todayISO = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
const form = reactive({
  Cuenta_general: serverPrefill.Cuenta_general ?? '',
  gasto_moneda_local: Number(serverPrefill.gasto_moneda_local ?? 0),
  ingreso_moneda_local: Number(serverPrefill.ingreso_moneda_local ?? 0),
  moneda_facturacion: serverPrefill.moneda_facturacion ?? monedaLocal.value,
  debito_moneda_gestion: Number(serverPrefill.debito_moneda_gestion ?? 0),
  credito_moneda_gestion: Number(serverPrefill.credito_moneda_gestion ?? 0),
  moneda_gestion: serverPrefill.moneda_gestion ?? monedaGestion.value,
  numero_descripcion_pieza: serverPrefill.numero_descripcion_pieza ?? serverPrefill.descripcion ?? '',
  codigo_presupuestario: serverPrefill.codigo_presupuestario ?? '',
  naturaleza_presupuesto: serverPrefill.naturaleza_presupuesto ?? '',
  contrato: serverPrefill.contrato ?? '',
  bailleur_fondos: serverPrefill.bailleur_fondos ?? '',
  fecha: serverPrefill.fecha ?? todayISO, // <-- añadido
  anio: serverPrefill.anio ?? String(new Date().getFullYear())
});

/* Helpers y computeds */
function round(value, decimals = 2) {
  const factor = Math.pow(10, decimals);
  return Math.round((Number(value) + Number.EPSILON) * factor) / factor;
}

const gastoGestion = computed(() => {
  const tc = Number(tipoCambio.value) || 1;
  return round((Number(form.gasto_moneda_local) || 0) / tc, 2);
});
const ingresoGestion = computed(() => {
  const tc = Number(tipoCambio.value) || 1;
  return round((Number(form.ingreso_moneda_local) || 0) / tc, 2);
});
const formattedGastoGestion = computed(() => (Number(gastoGestion.value) || 0).toFixed(2));
const formattedIngresoGestion = computed(() => (Number(ingresoGestion.value) || 0).toFixed(2));

// mostrar cuadro junto a bailleur cuando tenga valor
const showBailleurBox = computed(() => {
  return !!(form.bailleur_fondos && String(form.bailleur_fondos).trim() !== '');
});

watch([() => form.gasto_moneda_local, () => form.ingreso_moneda_local, tipoCambio], () => {
  form.debito_moneda_gestion = gastoGestion.value;
  form.credito_moneda_gestion = ingresoGestion.value;
  form.moneda_facturacion = monedaLocal.value;
  form.moneda_gestion = monedaGestion.value;
});

/* onMounted: pedir suggested number / last account si hace falta */
onMounted(async () => {
  const necesitaNumero = !form.numero_descripcion_pieza || String(form.numero_descripcion_pieza).trim() === '';
  const necesitaCuenta = !form.Cuenta_general || String(form.Cuenta_general).trim() === '';

  // si no hace falta nada, aún así intentamos rellenar preferencias (tipo_cambio, moneda) si vienen del serverPrefill
  if (!necesitaNumero && !necesitaCuenta) {
    // si serverPrefill trae tipo_cambio/moneda, aplicarlos
    if (serverPrefill.tipo_cambio) tipoCambio.value = Number(serverPrefill.tipo_cambio) || tipoCambio.value;
    if (serverPrefill.moneda_gestion) form.moneda_gestion = serverPrefill.moneda_gestion;
    if (serverPrefill.Cuenta_general) form.Cuenta_general = serverPrefill.Cuenta_general;
    return;
  }

  loadingPrefill.value = true;
  try {
    const res = await axios.get(`/proyectos/${props.proyecto.id}/easy/last-prefill`, {
      params: {
        nombre: props.proyecto?.nombre ?? null,
        // enviamos la descripcion que el usuario ya haya escrito (o la que vino en prefill)
        descripcion: (form.numero_descripcion_pieza || props.prefill?.descripcion || serverPrefill.descripcion || '').toString().trim() || null
      }
    });

    // aplicar sugerencias del servidor
    if (res?.data) {
      const data = res.data;
      if (data.suggested_numero_full && (!form.numero_descripcion_pieza || String(form.numero_descripcion_pieza).trim() === '')) {
        form.numero_descripcion_pieza = data.suggested_numero_full;
      }
      if (data.last_cuenta && (!form.Cuenta_general || String(form.Cuenta_general).trim() === '')) {
        form.Cuenta_general = data.last_cuenta;
      }
      // si tu backend devuelve tipo_cambio y moneda (vía create/prefill) las aplicamos:
      if (serverPrefill.tipo_cambio) tipoCambio.value = Number(serverPrefill.tipo_cambio) || tipoCambio.value;
      if (serverPrefill.moneda_gestion) form.moneda_gestion = serverPrefill.moneda_gestion;
    }
  } catch (err) {
    console.error('Error cargando last-prefill:', err);
  } finally {
    loadingPrefill.value = false;
  }
});


/* Validación, payload y guardar */
function validarCampos() {
  Object.keys(errors).forEach(k => delete errors[k]);
  if (!form.Cuenta_general || String(form.Cuenta_general).trim().length < 1) errors.Cuenta_general = 'La cuenta general es requerida.';
  if (!form.numero_descripcion_pieza || String(form.numero_descripcion_pieza).trim() === '') errors.numero_descripcion_pieza = 'La numeración/descripción es requerida.';
  if ((Number(form.gasto_moneda_local) > 0 || Number(form.ingreso_moneda_local) > 0) && (!tipoCambio.value || Number(tipoCambio.value) <= 0)) {
    errors.tipo_cambio = 'El tipo de cambio debe ser mayor a 0 para convertir montos.';
  }
  // validar fecha
  if (!form.fecha || String(form.fecha).trim() === '') {
    errors.fecha = 'La fecha es requerida.';
  }
  return Object.keys(errors).length === 0;
}

function crearPayloadPlano() {
  const proyectoId = props.proyecto?.id || '0';
  const pieza = (form.numero_descripcion_pieza || '').toString().replace(/\s+/g, '_').slice(0, 50);
  const codigoDefault = `${proyectoId}-${pieza || Date.now()}`;
  const hoy = new Date();
  const fechaDefault = form.fecha || `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
  const descripcionDefault = form.numero_descripcion_pieza || `Registro EASY ${codigoDefault}`;

  return {
    codigo: codigoDefault,
    fecha: fechaDefault,
    descripcion: descripcionDefault,
    categoria: 'EASY',
    unidad_medida: 'unidad',
    entradas: Number(form.ingreso_moneda_local) ? 1 : 0,
    precio: Number(form.gasto_moneda_local) ? Number(form.gasto_moneda_local) : 0,
    nombre: props.proyecto?.nombre || `proyecto_${props.proyecto?.id || '0'}`,
    Cuenta_general: form.Cuenta_general,
    gasto_moneda_local: Number(form.gasto_moneda_local) || 0,
    ingreso_moneda_local: Number(form.ingreso_moneda_local) || 0,
    moneda_facturacion: form.moneda_facturacion || monedaLocal.value,
    debito_moneda_gestion: Number(form.debito_moneda_gestion) || 0,
    credito_moneda_gestion: Number(form.credito_moneda_gestion) || 0,
    moneda_gestion: form.moneda_gestion || monedaGestion.value,
    numero_descripcion_pieza: form.numero_descripcion_pieza,
    codigo_presupuestario: form.codigo_presupuestario || null,
    naturaleza_presupuesto: form.naturaleza_presupuesto || null,
    contrato: form.contrato || null,
    bailleur_fondos: form.bailleur_fondos || null,
    tipo_cambio: Number(tipoCambio.value) || 0,
    anio: form.anio || String(new Date().getFullYear())
  };
}

function resetForm() {
  form.Cuenta_general = '';
  form.gasto_moneda_local = 0;
  form.ingreso_moneda_local = 0;
  form.moneda_facturacion = monedaLocal.value;
  form.debito_moneda_gestion = 0;
  form.credito_moneda_gestion = 0;
  form.moneda_gestion = monedaGestion.value;
  form.numero_descripcion_pieza = '';
  form.codigo_presupuestario = '';
  form.naturaleza_presupuesto = '';
  form.contrato = '';
  form.bailleur_fondos = '';
  form.fecha = todayISO;
  form.anio = String(new Date().getFullYear());
  Object.keys(errors).forEach(k => delete errors[k]);
}

const guardarNormal = async () => {
  if (!validarCampos()) { Swal.fire('⚠️ Errores', 'Corrige los errores en el formulario.', 'warning'); return; }
  const confirm = await Swal.fire({ title: '¿Guardar EASY?', text: 'Se registrará en la tabla EASY del proyecto.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar' });
  if (!confirm.isConfirmed) return;
  const payload = crearPayloadPlano();
  const url = window.LARAVEL?.storeEasyUrl || `/proyectos/${props.proyecto?.id || '0'}/easy/store`;

  try {
    submitting.value = true;
    const res = await axios.post(url, payload, { headers: { 'Accept': 'application/json' } });
    await Swal.fire('✅ Guardado', res?.data?.message || 'Registro EASY creado correctamente', 'success');
    resetForm();
  } catch (err) {
    console.error('guardar error:', err);
    if (err.response?.data?.errors) {
      const respErrors = err.response.data.errors;
      Object.keys(errors).forEach(k => delete errors[k]);
      for (const k in respErrors) errors[k] = Array.isArray(respErrors[k]) ? respErrors[k].join(' ') : String(respErrors[k]);
      Swal.fire('⚠️ No guardado', 'Corrige los errores.', 'warning');
    } else {
      Swal.fire('❌ Error', err.response?.data?.message ?? 'Error al guardar', 'error');
    }
  } finally {
    submitting.value = false;
  }
};

</script>

<template>
  <AuthenticatedLayout>
    <div>
      <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg transition-colors duration-300">
        <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <span class="inline-block w-2 h-6 bg-blue-600 rounded"></span>
          Crear registro — Inventario / Presupuesto <span class="text-sm text-gray-400">(EASY)</span>
        </h2>

        <!-- Indicador si se está cargando prefill -->
        <div v-if="loadingPrefill" class="mb-4 text-sm text-gray-600 dark:text-gray-300">
          Cargando datos sugeridos... <span class="italic">(sugerencia de número / cuenta)</span>
        </div>

        <!-- Cuenta general -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Cuenta general
          </label>
          <input v-model="form.Cuenta_general" type="text" aria-label="Cuenta general"
            class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
          <p v-if="errors.Cuenta_general" class="text-red-500 text-sm mt-1">
            {{ errors.Cuenta_general }}
          </p>
        </div>

        <form @submit.prevent="guardarNormal" class="space-y-6">
          <!-- Monedas fijas -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Moneda local (fija)
              </label>
              <input type="text" v-model="monedaLocal" readonly aria-readonly="true"
                class="w-full mt-1 p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Moneda gestión (fija)
              </label>
              <input type="text" v-model="monedaGestion" readonly aria-readonly="true"
                class="w-full mt-1 p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            </div>
          </div>

          <!-- Tipo de cambio + Fecha -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Tipo de cambio ({{ monedaLocal }} → {{ monedaGestion }})
              </label>
              <input v-model.number="tipoCambio" step="0.00001" type="number" aria-label="Tipo de cambio"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Usa esto para convertir valores: monedalocal / tipoCambio
              </p>
              <p v-if="errors.tipo_cambio" class="text-red-500 text-sm mt-1">{{ errors.tipo_cambio }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Fecha
              </label>
              <input type="date" v-model="form.fecha" aria-label="Fecha"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p v-if="errors.fecha" class="text-red-500 text-sm mt-1">{{ errors.fecha }}</p>
            </div>
          </div>

          <!-- Gasto / Ingreso en moneda local -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Gasto ({{ monedaLocal }})
              </label>
              <input v-model.number="form.gasto_moneda_local" type="number" step="0.01" aria-label="Gasto moneda local"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p v-if="errors.gasto_moneda_local" class="text-red-500 text-sm mt-1">
                {{ errors.gasto_moneda_local }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Ingreso ({{ monedaLocal }})
              </label>
              <input v-model.number="form.ingreso_moneda_local" type="number" step="0.01"
                aria-label="Ingreso moneda local"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p v-if="errors.ingreso_moneda_local" class="text-red-500 text-sm mt-1">
                {{ errors.ingreso_moneda_local }}
              </p>
            </div>
          </div>

          <!-- Valores convertidos (moneda gestión) -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Gasto ({{ monedaGestion }}) — calculado
              </label>
              <input :value="formattedGastoGestion" readonly aria-readonly="true"
                class="w-full mt-1 p-2 border rounded bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Ingreso ({{ monedaGestion }}) — calculado
              </label>
              <input :value="formattedIngresoGestion" readonly aria-readonly="true"
                class="w-full mt-1 p-2 border rounded bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300" />
            </div>
          </div>

          <!-- Débito / Crédito (editable por si ajustes manuales) -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Débito ({{ monedaGestion }})
              </label>
              <input v-model.number="form.debito_moneda_gestion" type="number" step="0.01"
                aria-label="Débito moneda gestión"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Crédito ({{ monedaGestion }})
              </label>
              <input v-model.number="form.credito_moneda_gestion" type="number" step="0.01"
                aria-label="Crédito moneda gestión"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            </div>
          </div>

          <!-- Número / descripción de la pieza -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Número / descripción de la pieza
            </label>
            <input v-model="form.numero_descripcion_pieza" type="text" aria-label="Número o descripción de la pieza"
              placeholder="Ej: 397-Pract_inst..."
              class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            <p v-if="errors.numero_descripcion_pieza" class="text-red-500 text-sm mt-1">
              {{ errors.numero_descripcion_pieza }}
            </p>
          </div>

          <!-- Código presupuestario / Naturaleza -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font medium text-gray-700 dark:text-gray-300">
                Código presupuestario
              </label>
              <input v-model="form.codigo_presupuestario" type="text" aria-label="Código presupuestario"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p v-if="errors.codigo_presupuestario" class="text-red-500 text-sm mt-1">{{ errors.codigo_presupuestario
              }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Naturaleza presupuesto
              </label>
              <input v-model="form.naturaleza_presupuesto" type="text" aria-label="Naturaleza presupuesto"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
            </div>
          </div>

          <!-- Contrato / Bailleur -->
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Contrato
              </label>
              <input v-model="form.contrato" type="text" aria-label="Contrato" placeholder="Ej: A.00.00"
                class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              <p v-if="errors.contrato" class="text-red-500 text-sm mt-1">{{ errors.contrato }}</p>
            </div>

            <div class="flex items-start gap-3">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Bailleur de fondos
                  <!-- Icono de info con tooltip (no mueve nada) -->
                  <span class="ml-2 inline-block align-middle cursor-default text-gray-500 dark:text-gray-400" title="RAF-WILDER:
                      1 = fonds propres IDP
                      2 = Union européenne
                      3 = DGD
                      4 = MAE Lux
                      5 = FBSA
                      6 = A REPARTIR" tabindex="0" role="img"
                    aria-label="Información sobre códigos RAF-WILDER: 1=fonds propres IDP, 2=Union européenne, 3=DGD, 4=MAE Lux, 5=FBSA, 6=A REPARTIR">
                    ℹ️
                  </span>
                </label>

                <input v-model="form.bailleur_fondos" type="text" aria-label="Bailleur de fondos" 
                  class="w-full mt-1 p-2 border rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" />
              </div>

              <!-- cuadro condicional que aparece cuando bailleur_fondos tiene valor -->
              <div v-if="showBailleurBox"
                class="mt-6 px-3 py-2 text-sm border rounded bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                1 union 2 fondos
              </div>
            </div>

          </div>

          <!-- Acciones -->
          <div class="flex items-center gap-4 pt-4 dark:text-white">
            <button type="submit" :disabled="submitting" :class="[
              'px-5 py-2 rounded-lg text-white',
              submitting ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'
            ]">
              <span v-if="submitting">Guardando...</span>
              <span v-else>Guardar</span>
            </button>

            <button type="button" @click="resetForm"
              class="px-5 py-2 border rounded-lg dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
              Limpiar
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>


<style scoped>
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
