<script setup>
import { ref, computed, watch, onMounted, defineProps } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const user = usePage().props.auth.user;

const props = defineProps({
  proyecto: Object,
  user: Object
});


// 🔹 Estado de carga
const cargando = ref(false);

const form = ref({
  codigo1: '',
  codigo2: '',
  n_acta: '',
  persona_id: null,
  nombre: '',
  lugar: '',
  distrito: '',
  fecha: '',
  producto: '',
  cantidad: 1
});

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  const codigo = params.get("codigo");
  if (codigo) {
    form.value.producto = decodeURIComponent(codigo);
  }
});

const personas = ref([]);
onMounted(async () => {
  try {
    const res = await axios.get('/personas');
    personas.value = res.data;
  } catch (error) {
    console.error('Error cargando personas', error);
  }
});

// -----------------------------
// CÓDIGO ACTA
// -----------------------------
const codigoGenerado = computed(() => {
  const c1 = (form.value.codigo1 || '').padStart(3, '0');
  const c2 = (form.value.codigo2 || '').padStart(3, '0');
  return `AE - ${c1} - ${c2}`;
});

// -----------------------------
// PRODUCTO ENCONTRADO (buscador)
// -----------------------------
const productoEncontrado = ref(null);

watch(
  () => form.value.producto,
  async (nuevoCodigo) => {
    if (nuevoCodigo && nuevoCodigo.length >= 3) {
      try {
        const res = await axios.get(
          `/proyectos/${props.proyecto.id}/buscar-producto/${encodeURIComponent(nuevoCodigo)}`
        );
        productoEncontrado.value = res.data.producto || null;
      } catch (e) {
        productoEncontrado.value = null;
      }
    } else {
      productoEncontrado.value = null;
    }
  }
);

// -----------------------------
// GUARDAR SALIDA (recarga la página)
// -----------------------------
const guardarSalida = async () => {
  // 🔒 Restricción por rol
  if (user.role !== 'admin' && user.role !== 'equipo') {
    Swal.fire('🚫 Permiso denegado', 'No tienes permiso para registrar salidas.', 'error');
    return;
  }

  // Validación mínima de campos
  if (!form.value.nombre || !form.value.lugar || !form.value.distrito || !form.value.fecha || !form.value.producto) {
    Swal.fire('⚠️ Campos incompletos', 'Por favor, complete todos los campos requeridos.', 'warning');
    return;
  }

  // Validación opcional: cantidad vs stock
  if (productoEncontrado.value && Number(form.value.cantidad) > Number(productoEncontrado.value.stock)) {
    Swal.fire('⚠️ Stock insuficiente', 'La cantidad supera el stock disponible.', 'warning');
    return;
  }

  Swal.fire({
    title: '¿Registrar salida?',
    text: 'Se descontará del stock.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar'
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    cargando.value = true;
    try {
      form.value.n_acta = codigoGenerado.value;

      const res = await axios.post(`/proyectos/${props.proyecto.id}/salidas`, form.value);

      if (res.data?.success) {
        Swal.fire('✅ Registrado', 'Salida registrada correctamente. Stock actualizado.', 'success').then(() => {
          // 🔄 recarga la página para limpiar el formulario
          window.location.reload();
        });
      } else {
        Swal.fire('⚠️ Error', res.data?.error || 'Ocurrió un error', 'error');
      }
    } catch (error) {
      console.error('❌ Error en backend:', error.response?.data || error);
      Swal.fire('❌ Error', 'Ocurrió un problema al registrar la salida.', 'error');
    } finally {
      cargando.value = false;
    }
  });
};

// -----------------------------
// GUARDAR “MANTENER” (incrementa código2, no recarga)
// -----------------------------
const guardarSalidaIncrementandoCodigo = async () => {
  // 🔒 Restricción por rol
  if (user.role !== 'admin' && user.role !== 'equipo') {
    Swal.fire('🚫 Permiso denegado', 'No tienes permiso para registrar salidas.', 'error');
    return;
  }

  // Validación mínima de campos
  if (!form.value.nombre || !form.value.lugar || !form.value.distrito || !form.value.fecha || !form.value.producto) {
    Swal.fire('⚠️ Campos incompletos', 'Por favor, complete todos los campos requeridos.', 'warning');
    return;
  }

  // Validación opcional: cantidad vs stock
  if (productoEncontrado.value && Number(form.value.cantidad) > Number(productoEncontrado.value.stock)) {
    Swal.fire('⚠️ Stock insuficiente', 'La cantidad supera el stock disponible.', 'warning');
    return;
  }

  Swal.fire({
    title: '¿Registrar salida y mantener datos?',
    text: 'Se descontará del stock y se incrementará el código.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar'
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    cargando.value = true;
    try {
      form.value.n_acta = codigoGenerado.value;

      const res = await axios.post(`/proyectos/${props.proyecto.id}/salidas`, form.value);

      if (res.data?.success) {
        Swal.fire('✅ Registrado', 'Salida registrada correctamente.', 'success');

        // Mantener: incrementa el código2; limpia campos puntuales
        const codigo2Num = parseInt(form.value.codigo2 || '0', 10) + 1;
        form.value.codigo2 = String(codigo2Num).padStart(3, '0');

        // Limpieza parcial
        form.value.producto = '';
        form.value.cantidad = 1;
      } else {
        Swal.fire('⚠️ Error', res.data?.error || 'Ocurrió un error', 'error');
      }
    } catch (error) {
      console.error('❌ Error en backend:', error.response?.data || error);
      Swal.fire('❌ Error', 'Ocurrió un problema al registrar la salida.', 'error');
    } finally {
      cargando.value = false;
    }
  });
};


// -----------------------------
// UTILIDADES
// -----------------------------
const volverATabla = () => {
  window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};

const autocompletarPersona = () => {
  const persona = personas.value.find((p) => p.nombre === form.value.nombre);
  if (persona) {
    form.value.lugar = persona.lugar;
    form.value.distrito = persona.distrito;
  }
};
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-4xl mx-auto mt-6 bg-white dark:bg-gray-800 p-6 rounded shadow">
      <h1 class="text-2xl font-bold mb-6 text-gray-700 dark:text-gray-200">
        Registrar Salida
      </h1>

      <!-- Usamos guardarSalida en el submit -->
      <form @submit.prevent="guardarSalida" class="space-y-5">
        <!-- Nº Acta -->
        <div>
          <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">N° Acta</label>
          <div class="flex items-center gap-2 p-2 border dark:border-gray-700 rounded">
            <span class="text-gray-500">AE -</span>
            <input v-model="form.codigo1" type="text" maxlength="3"
              class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded" required />
            <span class="text-gray-500">-</span>
            <input v-model="form.codigo2" type="text" maxlength="3"
              class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded" required />
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Código generado: <strong>{{ codigoGenerado }}</strong>
          </p>
        </div>

        <!-- Nombre -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Nombre</label>
          <input v-model="form.nombre" type="text" list="personasList" @change="autocompletarPersona"
            class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required />
          <datalist id="personasList">
            <option v-for="p in personas" :key="p.id" :value="p.nombre" />
          </datalist>
        </div>

        <!-- Lugar -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Lugar</label>
          <input v-model="form.lugar" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required />
        </div>

        <!-- Distrito -->
        <div>
          <label class="block font-bold mb-1 dark:text-gray-200">Distrito</label>
          <input v-model="form.distrito" type="text" class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"
            required />
        </div>

        <!-- Fecha -->
        <div>
          <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Fecha</label>
          <input v-model="form.fecha" type="date" class="dark:bg-gray-700 dark:text-white w-full border rounded p-2"
            required />
        </div>

        <!-- Código de Producto -->
        <div>
          <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Código de Producto</label>
          <input v-model="form.producto" type="text" class="dark:bg-gray-700 dark:text-white w-full border rounded p-2"
            required />

          <!-- Info del producto -->
          <div v-if="productoEncontrado"
            class="mt-3 p-3 border rounded bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-50">
            <p><strong>Descripción:</strong> {{ productoEncontrado.descripcion }}</p>
            <p><strong>Categoría:</strong> {{ productoEncontrado.categoria }}</p>
            <p><strong>Stock:</strong> {{ productoEncontrado.stock }}</p>
            <p><strong>Unidad de medida:</strong> {{ productoEncontrado.um }}</p>
          </div>
        </div>

        <!-- Cantidad -->
        <div>
          <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Cantidad</label>
          <input v-model="form.cantidad" type="number" min="1"
            class="dark:bg-gray-700 dark:text-white w-full border rounded p-2" required />
        </div>

        <!-- Botones -->
        <div class="flex justify-between items-start mt-6">
          <!-- Grupo de acciones principales -->
          <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit" :disabled="cargando"
              class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
              {{ cargando ? 'Guardando...' : 'Guardar' }}
            </button>

            <button type="button" @click="guardarSalidaIncrementandoCodigo"
              class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-700">
              Mantener
            </button>
          </div>

          <!-- Botón volver -->
          <button type="button" @click="volverATabla"
            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700">
            Volver
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
