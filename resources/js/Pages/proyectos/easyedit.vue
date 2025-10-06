<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg">
      <h2 class="text-2xl font-bold mb-4 dark:text-white">Editar — EASY (ID: {{ acta.id }})</h2>

      <form @submit.prevent="submit" class="space-y-6">
        <div>
          <label class="block text-sm font-medium dark:text-gray-200">Cuenta general</label>
          <input v-model="form.cuenta_general" type="text" class="w-full mt-1 p-2 border rounded" />
          <p v-if="form.errors.cuenta_general" class="text-red-500 text-sm">{{ form.errors.cuenta_general }}</p>
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
            <input v-model.number="form.tipo_cambio" type="number" step="0.00001"
              class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.tipo_cambio" class="text-red-500 text-sm">{{ form.errors.tipo_cambio }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Fecha</label>
            <input v-model="form.fecha" type="date" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.fecha" class="text-red-500 text-sm">{{ form.errors.fecha }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Gasto (local)</label>
            <input v-model.number="form.gasto_moneda_local" type="number" step="0.01"
              class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.gasto_moneda_local" class="text-red-500 text-sm">{{ form.errors.gasto_moneda_local }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Ingreso (local)</label>
            <input v-model.number="form.ingreso_moneda_local" type="number" step="0.01"
              class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.ingreso_moneda_local" class="text-red-500 text-sm">{{ form.errors.ingreso_moneda_local
              }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Débito (gestión)</label>
            <input v-model.number="form.debito_moneda_gestion" type="number" step="0.01"
              class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.debito_moneda_gestion" class="text-red-500 text-sm">{{
              form.errors.debito_moneda_gestion }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Crédito (gestión)</label>
            <input v-model.number="form.credito_moneda_gestion" type="number" step="0.01"
              class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.credito_moneda_gestion" class="text-red-500 text-sm">{{
              form.errors.credito_moneda_gestion }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium dark:text-gray-200">Número / descripción de la pieza</label>
          <input v-model="form.numero_descripcion_pieza" type="text" class="w-full mt-1 p-2 border rounded" />
          <p v-if="form.errors.numero_descripcion_pieza" class="text-red-500 text-sm">{{
            form.errors.numero_descripcion_pieza }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Código presupuestario</label>
            <input v-model="form.codigo_presupuestario" type="text" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.codigo_presupuestario" class="text-red-500 text-sm">{{
              form.errors.codigo_presupuestario }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Naturaleza presupuesto</label>
            <input v-model="form.naturaleza_presupuesto" type="text" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.naturaleza_presupuesto" class="text-red-500 text-sm">{{
              form.errors.naturaleza_presupuesto }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Contrato</label>
            <input v-model="form.contrato" type="text" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.contrato" class="text-red-500 text-sm">{{ form.errors.contrato }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium dark:text-gray-200">Bailleur de fondos</label>
            <input v-model="form.bailleur_fondos" type="text" class="w-full mt-1 p-2 border rounded" />
            <p v-if="form.errors.bailleur_fondos" class="text-red-500 text-sm">{{ form.errors.bailleur_fondos }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="volver" class="px-4 py-2 border rounded">volver</button>
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
import { useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  proyecto: Object,
  acta: Object,
  nombre: String,
  table: String,
});

// NO usamos descripcion — la tabla no la tiene
const form = useForm({
  cuenta_general: props.acta.cuenta_general ?? '',
  fecha: props.acta.fecha ?? '',
  gasto_moneda_local: props.acta.gasto_moneda_local ?? null,
  ingreso_moneda_local: props.acta.ingreso_moneda_local ?? null,
  debito_moneda_gestion: props.acta.debito_moneda_gestion ?? null,
  credito_moneda_gestion: props.acta.credito_moneda_gestion ?? null,
  numero_descripcion_pieza: props.acta.numero_descripcion_pieza ?? '',
  codigo_presupuestario: props.acta.codigo_presupuestario ?? '',
  naturaleza_presupuesto: props.acta.naturaleza_presupuesto ?? '',
  contrato: props.acta.contrato ?? '',
  bailleur_fondos: props.acta.bailleur_fondos ?? '',
  tipo_cambio: props.acta.tipo_cambio ?? null,
  n_acta: props.acta.n_acta ?? null,
});

const monedaLocal = props.acta.moneda_facturacion ?? '—';
const monedaGestion = props.acta.moneda_gestion ?? '—';

const page = usePage();
const flashSuccess = computed(() => page.props.value?.flash?.success ?? null);
const flashError = computed(() => page.props.value?.flash?.error ?? null);

async function submit() {
  // mostrar confirmación
  const result = await Swal.fire({
    title: '¿Guardar cambios?',
    text: 'Se actualizarán los datos del acta.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) {
    return; // si cancela, no hace nada
  }

  // convertir '' a null para numéricos si hace falta
  ['tipo_cambio', 'gasto_moneda_local', 'ingreso_moneda_local', 'debito_moneda_gestion', 'credito_moneda_gestion'].forEach(k => {
    if (form[k] === '') form[k] = null;
  });

  form.put(
    route('proyectos.easy.update', { 
      proyecto: props.proyecto.id, 
      nombre: props.nombre, 
      id: props.acta.id 
    }),
    {
      onSuccess: () => {
        Swal.fire({
          title: 'Guardado',
          text: 'Los cambios se han guardado correctamente.',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Ocurrió un problema al guardar los cambios.',
          icon: 'error',
        });
      },
    }
  );
}

function volver() {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};


</script>