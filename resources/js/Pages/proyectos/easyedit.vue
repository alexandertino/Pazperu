<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg">
      <h2 class="text-2xl font-bold mb-4 dark:text-white">Editar — EASY (ID: {{ acta.id }})</h2>

      <form @submit.prevent="submit" class="space-y-6">
        <div>
          <label class="block text-sm font-medium dark:text-gray-200">Cuenta general</label>
          <input v-model="form.Cuenta_general" type="text" class="w-full mt-1 p-2 border rounded" />
          <p v-if="form.errors.Cuenta_general" class="text-red-500 text-sm">{{ form.errors.Cuenta_general }}</p>
        </div>

        <!-- Monedas NO editables -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Moneda local</label>
            <input :value="monedaLocal" readonly class="w-full mt-1 p-2 border rounded bg-gray-100" />
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Moneda gestión</label>
            <input :value="monedaGestion" readonly class="w-full mt-1 p-2 border rounded bg-gray-100" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Tipo de cambio</label>
            <input v-model.number="form.tipo_cambio" type="number" step="0.00001" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.tipo_cambio" class="text-red-500 text-sm">{{ form.errors.tipo_cambio }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Fecha</label>
            <input v-model="form.fecha" type="date" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.fecha" class="text-red-500 text-sm">{{ form.errors.fecha }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-gray-200">Descripción</label>
          <textarea v-model="form.descripcion" rows="3" class="w-full mt-1 p-2 border rounded"></textarea>
          <p v-if="form.errors.descripcion" class="text-red-500 text-sm">{{ form.errors.descripcion }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Gasto (local)</label>
            <input v-model.number="form.gasto_moneda_local" type="number" step="0.01" class="w-full mt-1 p-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Ingreso (local)</label>
            <input v-model.number="form.ingreso_moneda_local" type="number" step="0.01" class="w-full mt-1 p-2 border rounded" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Débito (gestión)</label>
            <input v-model.number="form.debito_moneda_gestion" type="number" step="0.01" class="w-full mt-1 p-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Crédito (gestión)</label>
            <input v-model.number="form.credito_moneda_gestion" type="number" step="0.01" class="w-full mt-1 p-2 border rounded" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-gray-200">Número / descripción de la pieza</label>
          <input v-model="form.numero_descripcion_pieza" type="text" class="w-full mt-1 p-2 border rounded" />
          <p v-if="form.errors.numero_descripcion_pieza" class="text-red-500 text-sm">{{ form.errors.numero_descripcion_pieza }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Código presupuestario</label>
            <input v-model="form.codigo_presupuestario" type="text" class="w-full mt-1 p-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Naturaleza presupuesto</label>
            <input v-model="form.naturaleza_presupuesto" type="text" class="w-full mt-1 p-2 border rounded" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Contrato</label>
            <input v-model="form.contrato" type="text" class="w-full mt-1 p-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Bailleur de fondos</label>
            <input v-model="form.bailleur_fondos" type="text" class="w-full mt-1 p-2 border rounded" />
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="volver" class="px-4 py-2 border rounded">Cancelar</button>
          <button :disabled="form.processing" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
            {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
          </button>
        </div>

        <div v-if="flashSuccess" class="mt-4 p-3 bg-green-100 text-green-800 rounded">{{ flashSuccess }}</div>
        <div v-if="flashError" class="mt-4 p-3 bg-red-100 text-red-800 rounded">{{ flashError }}</div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  proyecto: Object,
  acta: Object,
  nombre: String,
  table: String,
});

// formulario inicial con los campos editables (no incluir monedas como editable)
const form = useForm({
  descripcion: props.acta.descripcion ?? '',
  fecha: props.acta.fecha ?? '',
  gasto_moneda_local: props.acta.gasto_moneda_local ?? 0,
  ingreso_moneda_local: props.acta.ingreso_moneda_local ?? 0,
  debito_moneda_gestion: props.acta.debito_moneda_gestion ?? 0,
  credito_moneda_gestion: props.acta.credito_moneda_gestion ?? 0,
  numero_descripcion_pieza: props.acta.numero_descripcion_pieza ?? '',
  codigo_presupuestario: props.acta.codigo_presupuestario ?? '',
  naturaleza_presupuesto: props.acta.naturaleza_presupuesto ?? '',
  contrato: props.acta.contrato ?? '',
  bailleur_fondos: props.acta.bailleur_fondos ?? '',
  tipo_cambio: props.acta.tipo_cambio ?? null,
  Cuenta_general: props.acta.cuenta_general ?? '',
  n_acta: props.acta.n_acta ?? null,
});

// monedas (solo para mostrar)
const monedaLocal = props.acta.moneda_facturacion ?? props.acta.moneda_facturacion ?? '—';
const monedaGestion = props.acta.moneda_gestion ?? '—';

const page = usePage();
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null);
const flashError = computed(() => page.props.value?.flash?.error ?? null);

function submit() {
  // usar ruta nombrada; si no usas Ziggy cambia por la url string
  form.put(route('proyectos.easy.update', { proyecto: props.proyecto.id, nombre: props.nombre, id: props.acta.id }));
}

function volver() {
  window.history.back();
}
</script>
