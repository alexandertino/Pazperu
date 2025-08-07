    <script setup>
    import { ref, computed } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

    // Simulación de pestaña activa
    const proyecto = ref({ pestaña: 'acta' });

    // Estado del formulario
    const form = ref({
        acta: '',
        nombre: '',
        lugar: '',
        distrito: '',
        fecha: '',
        producto: '',
        cantidad: 0,
        codigo1: '',   // Agregar estos campos
        codigo2: '',   // Agregar estos campos
    });

    // Generar el código del producto automáticamente
    const codigoProducto = computed(() => {
        return `${form.value.producto.toUpperCase()}-${form.value.cantidad}`;
    });

    const codigoGenerado = computed(() => {
        const codigo1 = form.value.codigo1.padStart(3, '0');
        const codigo2 = form.value.codigo2.padStart(3, '0');
        return `AE - ${codigo1} - ${codigo2}`;
    });

    // Función vacía para guardar
    const submit = () => {
        alert('Formulario guardado (simulado)');
    };

    const lugares = [  "UCHPAS",  "CHINCHAYPARAG",  "CHULLAY",  "GASGO",
    "AGUA CRISTAL",  "URURUPA",  "IDMA",  "PUQUIO",  "MIRAFLORES",  "ANDAS CHICO",  "MANUEL GARCIA",  "PIZPANGA",
    "CHILLIAN",  "TRES MANANTIALES",  "PAMPAMARCA",  "HUILLY",  "COCHATUNAN",  "CHOQUECANCHA",  "SALVIA",  "CHILIAN",
    "PIZPAMPA",  "LLACON",  "HUANUCO",  "CHINCHAYPARA",  "VISAG",
    "SANJAPAMPA",  "GORAMARCA",  "CHACHASPATA",  "MANTACOCHA",  "CHINCHAYPARAJ",
    "ZANJAPAMPA",  "AGUA GRISTAL",  "LLACÓN",  "VISAC",
    "UPCHAS",  "RAYANCANCHA",  "UMARI",
    "HUARIACO",  "PAUCARBAMBA",  "PACAPUCRO",  "CHULLQUI",  "SAN PEDRO DE CANI"
    ];

    const distritos = [  "CAYRAN",  "YARUMAYO",  "YACUS",  "IDMA",  "S.M.V.",
    "HUANUCO",  "UMARI",  "PACHITEA",  "AMARILIS",  "MARGOS",  "CHURUBAMBA",  "QUISKI"];

    const lugarOtro = ref(false);
    const distritoOtro = ref(false);

    const productosDisponibles = [
    { codigo: 'ABC123', nombre: 'Producto A', descripcion: 'Este es el producto A', unidad: 'kg', stock: 20 },
    { codigo: 'DEF456', nombre: 'Producto B', descripcion: 'Producto B de ejemplo', unidad: 'litros', stock: 50 },
    { codigo: 'GHI789', nombre: 'Producto C', descripcion: 'Producto C especial', unidad: 'unidades', stock: 100 },
    ];

    const productoSeleccionado = computed(() => {
    return productosDisponibles.find(p => p.codigo.toLowerCase() === form.value.producto.toLowerCase()) || null;
    });

    </script>


<template>
  <AuthenticatedLayout>
    <div v-if="proyecto.pestaña === 'acta'" class="flex flex-col md:flex-row gap-6 max-w-6xl mx-auto mt-6">
      <!-- 📝 Formulario -->
      <div class="flex-1 bg-white dark:bg-gray-800 p-6 shadow rounded">
        <h1 class="text-2xl font-bold mb-6 text-gray-700 dark:text-gray-200">Crear Acta</h1>
        <form @submit.prevent="submit" class="space-y-4">
          <!-- N° Acta -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">N° Acta</label>
            <div class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white">
              <span class="text-gray-500">AE - </span>
              <input v-model="form.codigo1" type="text" maxlength="3" placeholder="XXX"
                class="w-14 text-center border rounded px-2 py-1 mx-1 dark:bg-gray-700 dark:text-white" required>
              <span class="text-gray-500"> - </span>
              <input v-model="form.codigo2" type="text" maxlength="3" placeholder="XXX"
                class="w-14 text-center border rounded px-2 py-1 mx-1 dark:bg-gray-700 dark:text-white" required>
            </div>
            <p class="text-sm text-gray-500 mt-1">
              Código generado: <strong>{{ codigoGenerado }}</strong>
            </p>
          </div>

          <!-- Nombre -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Nombre</label>
            <input v-model="form.nombre" type="text"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          </div>

          <!-- Lugar -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Lugar</label>
            <select v-model="form.lugar" @change="lugarOtro = form.lugar === 'Otro'"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
              <option disabled value="">Seleccione un lugar</option>
              <option v-for="l in lugares" :key="l" :value="l">{{ l }}</option>
              <option value="Otro">Otro</option>
            </select>
            <div v-if="lugarOtro" class="mt-2">
              <input v-model="form.lugar" placeholder="Ingrese nuevo lugar"
                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" />
            </div>
          </div>

          <!-- Distrito -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Distrito</label>
            <select v-model="form.distrito" @change="distritoOtro = form.distrito === 'Otro'"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
              <option disabled value="">Seleccione un distrito</option>
              <option v-for="d in distritos" :key="d" :value="d">{{ d }}</option>
              <option value="Otro">Otro</option>
            </select>
            <div v-if="distritoOtro" class="mt-2">
              <input v-model="form.distrito" placeholder="Ingrese nuevo distrito"
                class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" />
            </div>
          </div>

          <!-- Fecha -->
          <div>
            <label class="block mb-1 font-bold text-gray-700 dark:text-gray-200">Fecha</label>
            <input v-model="form.fecha" type="date"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          </div>

          <!-- Código de Producto -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Código de Producto</label>
            <input v-model="form.producto" type="text"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          </div>

          <!-- Cantidad -->
          <div>
            <label class="block font-bold mb-1 text-gray-700 dark:text-gray-200">Cantidad</label>
            <input v-model="form.cantidad" type="number" min="1"
              class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
          </div>

          <!-- Botón -->
          <button type="submit"
            class="bg-blue-500 text-gray-700 dark:text-gray-200 px-4 py-2 rounded hover:bg-blue-700">
            Guardar
          </button>
        </form>
      </div>
      <!-- 📦 Vista previa del producto -->
      <div v-if="productoSeleccionado"
        class="w-full md:w-1/3 bg-white dark:bg-gray-800 p-4 shadow rounded h-fit">
        <h2 class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-4">Datos del Producto</h2>
        <p class="text-gray-700 dark:text-gray-200"><strong>Código:</strong> {{ productoSeleccionado.codigo }}</p>
        <p class="text-gray-700 dark:text-gray-200"><strong>Nombre:</strong> {{ productoSeleccionado.nombre }}</p>
        <p class="text-gray-700 dark:text-gray-200"><strong>Descripción:</strong> {{ productoSeleccionado.descripcion }}</p>
        <p class="text-gray-700 dark:text-gray-200"><strong>Unidad:</strong> {{ productoSeleccionado.unidad }}</p>
        <p class="text-gray-700 dark:text-gray-200"><strong>Stock disponible:</strong> {{ productoSeleccionado.stock }}</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
