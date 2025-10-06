<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, defineProps } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  categorias: { type: Array, default: () => [] },
  UnidadMedida: { type: Array, default: () => [] },
  solicitantes: { type: Array, default: () => [] },
  personas: { type: Array, default: () => [] },
});

const activeTab = ref('categorias');
const categorias = ref([]);
const unidades = ref([]);
const solicitantes = ref([]);
const personas = ref([]);
const query = ref('');

const newItem = ref({ nombre: '', lugar: '', distrito: '' });
const editing = ref({}); // { type, id, nombre, lugar?, distrito? }

onMounted(() => {
  categorias.value = (props.categorias || []).map(x => ({ ...x }));
  unidades.value = (props.UnidadMedida || []).map(x => ({ ...x }));
  solicitantes.value = (props.solicitantes || []).map(x => ({ ...x }));
  personas.value = (props.personas || []).map(x => ({
    id: x.id,
    nombre: x.nombre ?? (x.nombre_completo ?? ''),
    lugar: x.lugar ?? '',
    distrito: x.distrito ?? ''
  }));
  verificarDatos();
});

const endpointBase = `/inventario/meta`;

const resetNewItem = () => {
  newItem.value = { nombre: '', lugar: '', distrito: '' };
};

const addItem = async () => {
  const type = activeTab.value === 'UnidadMedida' ? 'UnidadMedida' : activeTab.value;

  if (type === 'personas') {
    if (!newItem.value.nombre.trim()) return Swal.fire('Error', 'Escribe el nombre', 'warning');
  } else {
    if (!newItem.value.nombre || !String(newItem.value.nombre).trim()) return Swal.fire('Error', 'Escribe un nombre', 'warning');
  }

  try {
    let payload = { type };
    if (type === 'personas') {
      payload = { ...payload, nombre: newItem.value.nombre, lugar: newItem.value.lugar, distrito: newItem.value.distrito };
    } else {
      payload = { ...payload, nombre: newItem.value.nombre };
    }

    const res = await axios.post(endpointBase, payload);

    // Respuesta del store en tu controlador: { id, item/persona } o similar.
    if (type === 'personas') {
      const item = {
        id: res.data.id,
        nombre: res.data.persona?.nombre ?? newItem.value.nombre,
        lugar: res.data.persona?.lugar ?? newItem.value.lugar ?? '',
        distrito: res.data.persona?.distrito ?? newItem.value.distrito ?? ''
      };
      personas.value.unshift(item);
    } else if (type === 'categorias') {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      categorias.value.unshift({ id: res.data.id, nombre });
    } else if (type === 'UnidadMedida') {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      unidades.value.unshift({ id: res.data.id, nombre });
    } else {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      solicitantes.value.unshift({ id: res.data.id, nombre });
    }

    resetNewItem();
    Swal.fire('OK', 'Creado', 'success');
  } catch (err) {
    console.error(err);
    Swal.fire('Error', 'No se pudo crear', 'error');
  }
};

const startEdit = (type, item) => {
  if (type === 'personas') {
    editing.value = { type, id: item.id, nombre: item.nombre ?? '', lugar: item.lugar ?? '', distrito: item.distrito ?? '' };
  } else {
    editing.value = { type, id: item.id, nombre: item.nombre ?? '' };
  }
};

const saveEdit = async () => {
  if (!editing.value || !editing.value.id) return;
  const type = editing.value.type;

  try {
    if (type === 'personas') {
      await axios.put(`${endpointBase}/${type}/${editing.value.id}`, {
        nombre: editing.value.nombre,
        lugar: editing.value.lugar,
        distrito: editing.value.distrito
      });

      const idx = personas.value.findIndex(x => x.id === editing.value.id);
      if (idx !== -1) {
        personas.value[idx].nombre = editing.value.nombre;
        personas.value[idx].lugar = editing.value.lugar;
        personas.value[idx].distrito = editing.value.distrito;
      }
    } else {
      await axios.put(`${endpointBase}/${type}/${editing.value.id}`, {
        nombre: editing.value.nombre
      });

      const list = type === 'categorias' ? categorias.value
        : type === 'UnidadMedida' ? unidades.value
          : solicitantes.value;

      const idx = list.findIndex(x => x.id === editing.value.id);
      if (idx !== -1) list[idx].nombre = editing.value.nombre;
    }

    editing.value = {};
    Swal.fire('OK', 'Actualizado', 'success');
  } catch (err) {
    console.error(err);
    Swal.fire('Error', 'No se pudo actualizar', 'error');
  }
};

const cancelEdit = () => editing.value = {};

const removeItem = async (type, item) => {
  const ok = await Swal.fire({
    title: 'Confirmar borrado',
    text: `Eliminar "${type === 'personas' ? item.nombre + ' - ' + (item.lugar ?? '') : item.nombre}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar'
  });
  if (!ok.isConfirmed) return;

  try {
    await axios.delete(`${endpointBase}/${type}/${item.id}`);

    if (type === 'categorias') categorias.value = categorias.value.filter(x => x.id !== item.id);
    else if (type === 'UnidadMedida') unidades.value = unidades.value.filter(x => x.id !== item.id);
    else if (type === 'solicitantes') solicitantes.value = solicitantes.value.filter(x => x.id !== item.id);
    else if (type === 'personas') personas.value = personas.value.filter(x => x.id !== item.id);

    Swal.fire('OK', 'Eliminado', 'success');
  } catch (err) {
    console.error(err);
    Swal.fire('Error', 'No se pudo eliminar', 'error');
  }
};

// helper para filtrar (no es computed para mantener simplicidad)
const filteredList = (tab) => {
  const q = String(query.value || '').toLowerCase().trim();
  const list = tab === 'categorias' ? categorias.value
    : tab === 'UnidadMedida' ? unidades.value
      : tab === 'solicitantes' ? solicitantes.value
        : personas.value;

  if (!q) return list;
  return list.filter(it => (it.nombre || '').toString().toLowerCase().includes(q) || (it.lugar || '').toString().toLowerCase().includes(q) || (it.distrito || '').toString().toLowerCase().includes(q));
};

const mostrarBoton = ref(false);

// Verificar si ya existen datos iniciales en la base
const verificarDatos = async () => {
  try {
    // Usamos endpointBase para consistencia con el resto del componente
    const { data } = await axios.get(endpointBase);

    const tieneCategorias = data.categorias?.length > 0;
    const tieneUnidades = data.UnidadMedida?.length > 0;
    const tieneSolicitantes = data.solicitantes?.length > 0;

    mostrarBoton.value = !(tieneCategorias || tieneUnidades || tieneSolicitantes);
  } catch (error) {
    console.error('Error al verificar datos iniciales:', error);
    mostrarBoton.value = false;
  }
};

// Insertar datos iniciales (llamado una vez desde el botón)
const insertarDatosIniciales = async () => {
  try {
    const res = await axios.post(`${endpointBase}/insert-initial`);
    console.log('Datos insertados:', res.data);

    // Si el backend devuelve un objeto 'result' con arrays { categorias, unidades_medida, solicitantes }
    const result = res.data.result || res.data.inserted || null;

    if (result) {
      // categorias
      if (Array.isArray(result.categorias)) {
        for (const c of result.categorias) {
          // c puede ser { id, nombre, skipped }
          const exists = categorias.value.some(x => x.id === c.id || (x.nombre && x.nombre === c.nombre));
          if (!exists && !c.skipped) {
            categorias.value.unshift({ id: c.id, nombre: c.nombre });
          }
        }
      }

      // unidades_medida (puede venir en result.unidades_medida)
      const unidadesKey = result.unidades_medida || result.unidades || result.UnidadMedida;
      if (Array.isArray(unidadesKey)) {
        for (const u of unidadesKey) {
          const exists = unidades.value.some(x => x.id === u.id || (x.nombre && x.nombre === u.nombre));
          if (!exists && !u.skipped) {
            unidades.value.unshift({ id: u.id, nombre: u.nombre });
          }
        }
      }

      // solicitantes
      if (Array.isArray(result.solicitantes)) {
        for (const s of result.solicitantes) {
          const exists = solicitantes.value.some(x => x.id === s.id || (x.nombre && x.nombre === s.nombre));
          if (!exists && !s.skipped) {
            solicitantes.value.unshift({ id: s.id, nombre: s.nombre });
          }
        }
      }
    }

    mostrarBoton.value = false;
    Swal.fire('OK', '✅ Datos iniciales cargados correctamente', 'success');
  } catch (error) {
    console.error('Error al insertar datos iniciales:', error);
    Swal.fire('Error', '❌ Error al insertar datos iniciales', 'error');
  }
};
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-10">
      <div
        class="max-w-6xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-gray-800 dark:text-white">
        <header class="flex items-center gap-4 mb-6">
          <div class="flex items-center gap-3">
            <div>
              <h1 class="text-2xl font-semibold">Gestionar tablas meta (global)</h1>
              <div>
                <button v-if="mostrarBoton" @click="insertarDatosIniciales"
                  class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                  📌 Cargar datos iniciales
                </button>

                <p v-else class="text-gray-600 mt-2">
                  ✅ Los datos iniciales ya están cargados.
                </p>
              </div>
              <p class="text-sm text-muted-foreground dark:text-gray-400">
                Categorías, Unidades, Solicitantes y Personas — administración rápida y segura
              </p>
            </div>
          </div>

          <div class="ml-auto flex items-center gap-3">
            <input v-model="query" placeholder="Buscar..." class="px-3 py-2 border rounded-lg w-64 shadow-sm
                 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100
                 dark:placeholder-gray-400" />
            <a href="/proyectos" class="px-4 py-2 text-sm rounded-lg border bg-white hover:bg-gray-50
                 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700 dark:text-gray-100">
              Cerrar
            </a>
          </div>
        </header>

        <!-- tabs -->
        <nav class="flex gap-2 mb-4 overflow-auto">
          <button @click="activeTab = 'categorias'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'categorias'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Categorías
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ categorias.length }}
            </span>
          </button>

          <button @click="activeTab = 'UnidadMedida'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'UnidadMedida'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Unidades de medida
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ unidades.length }}
            </span>
          </button>

          <button @click="activeTab = 'solicitantes'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'solicitantes'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Solicitantes
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ solicitantes.length }}
            </span>
          </button>

          <button @click="activeTab = 'personas'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'personas'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Personas
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ personas.length }}
            </span>
          </button>
        </nav>

        <!-- form -->
        <section class="mb-6 bg-white border rounded-lg p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div>
              <label class="text-xs text-muted-foreground dark:text-gray-400">Nombre</label>
              <input v-model="newItem.nombre" placeholder="Nombre..." class="mt-1 p-2 border rounded-lg w-full
                   dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100
                   dark:placeholder-gray-400" />
            </div>

            <div v-if="activeTab === 'personas'">
              <label class="text-xs text-muted-foreground dark:text-gray-400">Lugar</label>
              <input v-model="newItem.lugar" placeholder="Lugar..." class="mt-1 p-2 border rounded-lg w-full
                   dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100
                   dark:placeholder-gray-400" />
            </div>

            <div v-if="activeTab === 'personas'">
              <label class="text-xs text-muted-foreground dark:text-gray-400">Distrito</label>
              <input v-model="newItem.distrito" placeholder="Distrito..." class="mt-1 p-2 border rounded-lg w-full
                   dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100
                   dark:placeholder-gray-400" />
            </div>

            <div class="flex gap-2 sm:col-span-3 justify-end">
              <button @click="addItem"
                class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium shadow hover:bg-green-700">
                Agregar
              </button>
              <button v-if="activeTab === 'personas'" @click="resetNewItem"
                class="px-4 py-2 rounded-lg border dark:border-gray-600 dark:text-gray-200 hover:dark:bg-gray-700">
                Limpiar
              </button>
            </div>
          </div>
        </section>

        <!-- table -->
        <div class="bg-white border rounded-lg overflow-hidden dark:bg-gray-800 dark:border-gray-700">
          <table class="min-w-full divide-y dark:divide-gray-700 table-fixed">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-12">
                  #
                </th>
                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-1/2">
                  Nombre
                </th>
                <th v-if="activeTab === 'personas'"
                  class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">
                  Lugar
                </th>
                <th v-if="activeTab === 'personas'"
                  class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">
                  Distrito
                </th>
                <th class="px-4 py-3 text-right text-sm font-medium text-muted-foreground dark:text-gray-300 w-28">
                  Acciones
                </th>
              </tr>
            </thead>

            <tbody class="bg-white divide-y dark:bg-gray-800 dark:divide-gray-700">
              <tr v-for="(item, idx) in filteredList(activeTab)" :key="item.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-4 py-3 text-sm w-12">{{ idx + 1 }}</td>

                <!-- Nombre -->
                <td class="px-4 py-3 text-sm w-1/2">
                  <div v-if="editing.id === item.id && editing.type === activeTab">
                    <input v-model="editing.nombre"
                      class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                  </div>
                  <div v-else class="truncate">{{ item.nombre }}</div>
                </td>

                <!-- Lugar -->
                <td v-if="activeTab === 'personas'" class="px-4 py-3 text-sm w-32">
                  <div v-if="editing.id === item.id && editing.type === activeTab">
                    <input v-model="editing.lugar"
                      class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                  </div>
                  <div v-else class="truncate">{{ item.lugar }}</div>
                </td>

                <!-- Distrito -->
                <td v-if="activeTab === 'personas'" class="px-4 py-3 text-sm w-32">
                  <div v-if="editing.id === item.id && editing.type === activeTab">
                    <input v-model="editing.distrito"
                      class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                  </div>
                  <div v-else class="truncate">{{ item.distrito }}</div>
                </td>

                <!-- Acciones -->
                <td class="px-4 py-3 text-right text-sm w-28">
                  <div v-if="editing.id === item.id && editing.type === activeTab" class="flex justify-end gap-2">
                    <button @click="saveEdit" class="px-3 py-1 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                      Guardar
                    </button>
                    <button @click="cancelEdit"
                      class="px-3 py-1 rounded-lg border dark:border-gray-600 dark:text-gray-200 hover:dark:bg-gray-700">
                      Cancelar
                    </button>
                  </div>

                  <div v-else class="flex justify-end gap-2">
                    <button @click="startEdit(activeTab, item)"
                      class="px-3 py-1 rounded-lg bg-yellow-400 hover:bg-yellow-500">
                      Editar
                    </button>
                    <button @click="removeItem(activeTab, item)"
                      class="px-3 py-1 rounded-lg bg-red-600 text-white hover:bg-red-700">
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="(filteredList(activeTab) || []).length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-muted-foreground dark:text-gray-400">
                  No hay registros
                </td>
              </tr>
            </tbody>
          </table>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.bg-surface {
  background-color: #f7fafc;
}

.text-muted-foreground {
  color: #6b7280;
}
</style>
