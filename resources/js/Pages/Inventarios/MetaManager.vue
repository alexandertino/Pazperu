<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, defineProps } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const page = usePage();
const user = usePage().props.auth.user;
const props = defineProps({
  categorias: { type: Array, default: () => [] },
  UnidadMedida: { type: Array, default: () => [] },
  solicitantes: { type: Array, default: () => [] },
  personas: { type: Array, default: () => [] },
  proyectos: { type: Array, default: () => [] },
  usuarios: { type: Array, default: () => [] }, 
});

const activeTab = ref('categorias');
const categorias = ref([]);
const unidades = ref([]);
const solicitantes = ref([]);
const personas = ref([]);
const proyectos = ref([]);
const usuarios = ref([]); // nuevo array para usuarios
const query = ref('');

const newItem = ref({ nombre: '', lugar: '', distrito: '' });
const newProyecto = ref({
  nombre: '',
  estado: 'pendiente',
  descripcion: '',
  fecha_inicio: '',
  fecha_fin: ''
});

const editing = ref({}); // { type, id, ... }

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
  proyectos.value = (props.proyectos || []).map(p => ({
    id: p.id,
    nombre: p.nombre ?? '',
    estado: p.estado ?? '',
    descripcion: p.descripcion ?? '',
    fecha_inicio: p.fecha_inicio ?? '',
    fecha_fin: p.fecha_fin ?? ''
  }));
  usuarios.value = (props.usuarios || []).map(u => ({
    id: u.id,
    name: u.name ?? '',
    email: u.email ?? '',
    role: u.role ?? ''
  }));
  verificarDatos();
});

const endpointBase = `/inventario/meta`;

// reset campos generales (categorias/solicitantes/etc)
const resetNewItem = () => {
  newItem.value = { nombre: '', lugar: '', distrito: '' };
};

// reset campos proyecto
const resetNewProyecto = () => {
  newProyecto.value = { nombre: '', estado: 'pendiente', descripcion: '', fecha_inicio: '', fecha_fin: '' };
};

const addItem = async () => {
  // detecta tipo
  const type = activeTab.value === 'UnidadMedida' ? 'UnidadMedida' : activeTab.value;

  // No permitimos crear usuarios desde aquí
  if (type === 'usuarios') {
    return Swal.fire('Atención', 'La creación de usuarios se gestiona desde el módulo de usuarios del sistema.', 'info');
  }

  // validaciones básicas
  if (type === 'personas') {
    if (!newItem.value.nombre.trim()) return Swal.fire('Error', 'Escribe el nombre', 'warning');
  } else if (type === 'proyectos') {
    if (!newProyecto.value.nombre || !String(newProyecto.value.nombre).trim()) return Swal.fire('Error', 'Escribe el nombre del proyecto', 'warning');
    // opcional: validar fechas aquí
  } else {
    if (!newItem.value.nombre || !String(newItem.value.nombre).trim()) return Swal.fire('Error', 'Escribe un nombre', 'warning');
  }

  try {
    let payload = { type };

    if (type === 'personas') {
      payload = { ...payload, nombre: newItem.value.nombre, lugar: newItem.value.lugar, distrito: newItem.value.distrito };
    } else if (type === 'proyectos') {
      payload = {
        ...payload,
        nombre: newProyecto.value.nombre,
        estado: newProyecto.value.estado,
        descripcion: newProyecto.value.descripcion,
        fecha_inicio: newProyecto.value.fecha_inicio,
        fecha_fin: newProyecto.value.fecha_fin
      };
    } else {
      payload = { ...payload, nombre: newItem.value.nombre };
    }

    const res = await axios.post(endpointBase, payload);

    // manejar respuesta según tipo
    if (type === 'personas') {
      const item = {
        id: res.data.id,
        nombre: res.data.persona?.nombre ?? newItem.value.nombre,
        lugar: res.data.persona?.lugar ?? newItem.value.lugar ?? '',
        distrito: res.data.persona?.distrito ?? newItem.value.distrito ?? ''
      };
      personas.value.unshift(item);
      resetNewItem();
    } else if (type === 'categorias') {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      categorias.value.unshift({ id: res.data.id, nombre });
      resetNewItem();
    } else if (type === 'UnidadMedida') {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      unidades.value.unshift({ id: res.data.id, nombre });
      resetNewItem();
    } else if (type === 'solicitantes') {
      const nombre = res.data.item?.nombre ?? newItem.value.nombre;
      solicitantes.value.unshift({ id: res.data.id, nombre });
      resetNewItem();
    } else if (type === 'proyectos') {
      const p = res.data.proyecto ?? {};
      const item = {
        id: res.data.id ?? p.id,
        nombre: p.nombre ?? newProyecto.value.nombre,
        estado: p.estado ?? newProyecto.value.estado,
        descripcion: p.descripcion ?? newProyecto.value.descripcion,
        fecha_inicio: p.fecha_inicio ?? newProyecto.value.fecha_inicio,
        fecha_fin: p.fecha_fin ?? newProyecto.value.fecha_fin
      };
      proyectos.value.unshift(item);
      resetNewProyecto();
    }

    Swal.fire('OK', 'Creado', 'success');
  } catch (err) {
    console.error(err);
    Swal.fire('Error', 'No se pudo crear', 'error');
  }
};

const startEdit = (type, item) => {
  if (type === 'personas') {
    editing.value = { type, id: item.id, nombre: item.nombre ?? '', lugar: item.lugar ?? '', distrito: item.distrito ?? '' };
  } else if (type === 'proyectos') {
    editing.value = {
      type,
      id: item.id,
      nombre: item.nombre ?? '',
      estado: item.estado ?? '',
      descripcion: item.descripcion ?? '',
      fecha_inicio: item.fecha_inicio ?? '',
      fecha_fin: item.fecha_fin ?? ''
    };
  } else if (type === 'usuarios') {
    // Solo permitimos editar role
    editing.value = { type, id: item.id, role: item.role ?? '' };
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
    } else if (type === 'proyectos') {
      await axios.put(`${endpointBase}/${type}/${editing.value.id}`, {
        nombre: editing.value.nombre,
        estado: editing.value.estado,
        descripcion: editing.value.descripcion,
        fecha_inicio: editing.value.fecha_inicio,
        fecha_fin: editing.value.fecha_fin
      });

      const idx = proyectos.value.findIndex(x => x.id === editing.value.id);
      if (idx !== -1) {
        proyectos.value[idx].nombre = editing.value.nombre;
        proyectos.value[idx].estado = editing.value.estado;
        proyectos.value[idx].descripcion = editing.value.descripcion;
        proyectos.value[idx].fecha_inicio = editing.value.fecha_inicio;
        proyectos.value[idx].fecha_fin = editing.value.fecha_fin;
      }
    } else if (type === 'usuarios') {
      // Solo actualizar role
      await axios.put(`${endpointBase}/${type}/${editing.value.id}`, {
        role: editing.value.role
      });

      const idx = usuarios.value.findIndex(x => x.id === editing.value.id);
      if (idx !== -1) {
        usuarios.value[idx].role = editing.value.role;
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
  // Por seguridad no habilitamos borrar usuarios desde aquí por defecto
  if (type === 'usuarios') {
    return Swal.fire('Prohibido', 'Eliminar usuarios desde este panel no está permitido.', 'info');
  }

  const label = type === 'personas' ? (item.nombre + ' - ' + (item.lugar ?? '')) : item.nombre;
  const ok = await Swal.fire({
    title: 'Confirmar borrado',
    text: `Eliminar "${label}"?`,
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
    else if (type === 'proyectos') proyectos.value = proyectos.value.filter(x => x.id !== item.id);

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
        : tab === 'personas' ? personas.value
          : tab === 'proyectos' ? proyectos.value
            : usuarios.value;

  if (!q) return list;
  return list.filter(it =>
    ((it.nombre || '') + ' ' + (it.descripcion || '') + ' ' + (it.lugar || '') + ' ' + (it.distrito || '') + ' ' + (it.estado || '') + ' ' + (it.fecha_inicio || '') + ' ' + (it.fecha_fin || '') + ' ' + (it.name || '') + ' ' + (it.email || '') + ' ' + (it.role || ''))
      .toString().toLowerCase().includes(q)
  );
};




// Función para refrescar solo usuarios desde el servidor (útil después de cambios)
const refreshUsuarios = async () => {
  try {
    const { data } = await axios.get(endpointBase);
    if (Array.isArray(data.usuarios)) {
      usuarios.value = data.usuarios.map(u => ({ id: u.id, name: u.name ?? '', email: u.email ?? '', role: u.role ?? '' }));
      Swal.fire('OK', 'Usuarios actualizados', 'success');
    } else {
      Swal.fire('Info', 'No se encontraron usuarios en la respuesta del servidor', 'info');
    }
  } catch (err) {
    console.error(err);
    Swal.fire('Error', 'No se pudo actualizar usuarios', 'error');
  }
};

// mostrarBoton siempre true (botón siempre visible)
const mostrarBoton = ref(true);

// Verificar datos: dejamos la verificación pero NO cambiamos mostrarBoton
const verificarDatos = async () => {
  try {
    const { data } = await axios.get(endpointBase);

    // Opcional: puedes seguir usando esta info para sincronizar arrays
    if (Array.isArray(data.usuarios)) {
      usuarios.value = data.usuarios.map(u => ({ id: u.id, name: u.name ?? '', email: u.email ?? '', role: u.role ?? '' }));
    }

    // NO modificamos mostrarBoton — el botón debe seguir visible
  } catch (error) {
    console.error('Error al verificar datos iniciales:', error);
    // mostramos advertencia tipo toast pero dejamos el botón visible
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'warning',
      title: 'No se pudo verificar si existen datos. Puedes intentar cargar igual.',
      showConfirmButton: false,
      timer: 3000
    });
  }
};
// agrega esto arriba en tu <script setup>
const inserting = ref(false);

//daaaa
const insertarDatosIniciales = async () => {
  if (inserting.value) return; // evita reentradas
  inserting.value = true;

  try {
    const res = await axios.post(`${endpointBase}/insert-initial`);
    console.log('Datos insertados:', res.data);

    // Intentamos obtener el payload con tolerancia a distintas claves
    const payload = res.data?.result || res.data?.inserted || res.data || null;

    // Si viene payload con arrays, los añadimos localmente evitando duplicados simples
    if (payload) {
      // categorias
      if (Array.isArray(payload.categorias)) {
        for (const c of payload.categorias) {
          const exists = categorias.value.some(x => x.id === c.id || (x.nombre && x.nombre === c.nombre));
          if (!exists && !c.skipped) categorias.value.unshift({ id: c.id, nombre: c.nombre });
        }
      }

      // unidades (varias posibles claves)
      const unidadesKey = payload.unidades_medida || payload.unidades || payload.UnidadMedida;
      if (Array.isArray(unidadesKey)) {
        for (const u of unidadesKey) {
          const exists = unidades.value.some(x => x.id === u.id || (x.nombre && x.nombre === u.nombre));
          if (!exists && !u.skipped) unidades.value.unshift({ id: u.id, nombre: u.nombre });
        }
      }

      // solicitantes
      if (Array.isArray(payload.solicitantes)) {
        for (const s of payload.solicitantes) {
          const exists = solicitantes.value.some(x => x.id === s.id || (x.nombre && x.nombre === s.nombre));
          if (!exists && !s.skipped) solicitantes.value.unshift({ id: s.id, nombre: s.nombre });
        }
      }

      // proyectos
      if (Array.isArray(payload.proyectos)) {
        for (const p of payload.proyectos) {
          const exists = proyectos.value.some(x => x.id === p.id || (x.nombre && x.nombre === p.nombre));
          if (!exists && !p.skipped) {
            proyectos.value.unshift({
              id: p.id,
              nombre: p.nombre,
              estado: p.estado ?? '',
              descripcion: p.descripcion ?? '',
              fecha_inicio: p.fecha_inicio ?? '',
              fecha_fin: p.fecha_fin ?? ''
            });
          }
        }
      }

      // usuarios
      if (Array.isArray(payload.usuarios)) {
        for (const u of payload.usuarios) {
          const exists = usuarios.value.some(x => x.id === u.id || (x.email && x.email === u.email));
          if (!exists && !u.skipped) usuarios.value.unshift({ id: u.id, name: u.name ?? '', email: u.email ?? '', role: u.role ?? '' });
        }
      }
    }

    // Toast success (si el backend trae un mensaje lo mostramos)
    const successMsg = res.data?.message || (payload ? '✅ Datos iniciales cargados correctamente' : '✅ Insertado');
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: successMsg,
      showConfirmButton: false,
      timer: 2500
    });

    // opcional: refrescar datos desde servidor
    // await verificarDatos();
  } catch (error) {
    console.error('Error al insertar datos iniciales:', error);

    // Intentamos extraer mensaje útil
    const serverMsg = error?.response?.data?.message || error?.response?.data || error.message || 'Error al insertar datos iniciales';
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: `❌ ${serverMsg}`,
      showConfirmButton: false,
      timer: 4000
    });
  } finally {
    inserting.value = false;
  }
};

</script>

<template>
  <AuthenticatedLayout>
    <div v-if="user.role === 'admin'" class="mt-10">
      <div class="max-w-6xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-gray-800 dark:text-white">
        <header class="flex items-center gap-4 mb-6">
          <div class="flex items-center gap-3">
            <div>
              <h1 class="text-2xl font-semibold">Gestionar tablas meta (global)</h1>
              <div>
              </div>
              <p class="text-sm text-muted-foreground dark:text-gray-400">
                Categorías, Unidades, Solicitantes, Personas, Proyectos y Usuarios — administración rápida y segura
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

          <button @click="activeTab = 'proyectos'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'proyectos'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Proyectos
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ proyectos.length }}
            </span>
          </button>

          <button @click="activeTab = 'usuarios'" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === 'usuarios'
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow'
              : 'bg-white border dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:dark:bg-gray-700'
          ]">
            Usuarios
            <span class="ml-2 text-xs text-muted-foreground dark:text-gray-400">
              {{ usuarios.length }}
            </span>
          </button>
        </nav>

        <!-- form -->
        <section class="mb-6 bg-white border rounded-lg p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
          <div v-if="activeTab === 'proyectos'">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <!-- aquí podrías colocar inputs específicos para proyectos si quieres -->
            </div>
          </div>

          <div v-else-if="activeTab === 'usuarios'">
            <div class="p-3 rounded bg-yellow-50 dark:bg-yellow-900/20 border mb-3">
              <p class="text-sm">La creación y eliminación de usuarios se gestiona desde el módulo de usuarios del sistema. Aquí solo puedes editar el <strong>role</strong> de cada usuario.</p>
            </div>
            <div class="flex justify-end gap-2">
              <button @click="refreshUsuarios" class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">🔄 Refrescar usuarios</button>
            </div>
          </div>

          <div v-else>
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
          </div>
        </section>

        <!-- table -->
        <div class="bg-white border rounded-lg overflow-hidden dark:bg-gray-800 dark:border-gray-700">
          <table class="min-w-full divide-y dark:divide-gray-700 table-fixed">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-12">#</th>

                <!-- columnas para usuarios -->
                <th v-if="activeTab === 'usuarios'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300">Nombre</th>
                <th v-if="activeTab === 'usuarios'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300">Email</th>
                <th v-if="activeTab === 'usuarios'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-40">Role</th>

                <!-- columnas para otros tipos -->
                <th v-if="!['usuarios'].includes(activeTab)" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300">Nombre</th>

                <th v-if="activeTab === 'personas'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">Lugar</th>
                <th v-if="activeTab === 'personas'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">Distrito</th>

                <th v-if="activeTab === 'proyectos'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-28">Estado</th>
                <th v-if="activeTab === 'proyectos'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300">Descripción</th>
                <th v-if="activeTab === 'proyectos'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">Inicio</th>
                <th v-if="activeTab === 'proyectos'" class="px-4 py-3 text-left text-sm font-medium text-muted-foreground dark:text-gray-300 w-32">Fin</th>

                <th class="px-4 py-3 text-right text-sm font-medium text-muted-foreground dark:text-gray-300 w-28">Acciones</th>
              </tr>
            </thead>

            <tbody class="bg-white divide-y dark:bg-gray-800 dark:divide-gray-700">
              <tr v-for="(item, idx) in filteredList(activeTab)" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-4 py-3 text-sm w-12">{{ idx + 1 }}</td>

                <!-- Usuarios row -->
                <template v-if="activeTab === 'usuarios'">
                  <td class="px-4 py-3 text-sm">
                    <div class="truncate">{{ item.name }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <div class="truncate">{{ item.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm w-40">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <select v-model="editing.role" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <option value="">-- Sin role --</option>
                        <option value="admin">admin</option>
                        <option value="equipo">equipo</option>
                        <option value="miembro">miembro</option>
                        <!-- agrega/ajusta roles según tu aplicación -->
                      </select>
                    </div>
                    <div v-else class="truncate">{{ item.role }}</div>
                  </td>
                </template>

                <!-- Otro tipo row -->
                <template v-else>
                  <td class="px-4 py-3 text-sm">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.nombre" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.nombre }}</div>
                  </td>

                  <td v-if="activeTab === 'personas'" class="px-4 py-3 text-sm w-32">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.lugar" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.lugar }}</div>
                  </td>

                  <td v-if="activeTab === 'personas'" class="px-4 py-3 text-sm w-32">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.distrito" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.distrito }}</div>
                  </td>

                  <td v-if="activeTab === 'proyectos'" class="px-4 py-3 text-sm w-28">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <select v-model="editing.estado" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <option value="pendiente">Pendiente</option>
                        <option value="activo">Activo</option>
                        <option value="completado">Completado</option>
                        <option value="cancelado">Cancelado</option>
                      </select>
                    </div>
                    <div v-else class="truncate">{{ item.estado }}</div>
                  </td>

                  <td v-if="activeTab === 'proyectos'" class="px-4 py-3 text-sm">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.descripcion" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.descripcion }}</div>
                  </td>

                  <td v-if="activeTab === 'proyectos'" class="px-4 py-3 text-sm w-32">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.fecha_inicio" type="date" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.fecha_inicio }}</div>
                  </td>

                  <td v-if="activeTab === 'proyectos'" class="px-4 py-3 text-sm w-32">
                    <div v-if="editing.id === item.id && editing.type === activeTab">
                      <input v-model="editing.fecha_fin" type="date" class="p-2 border rounded-lg w-full dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100" />
                    </div>
                    <div v-else class="truncate">{{ item.fecha_fin }}</div>
                  </td>
                </template>

                <!-- Acciones -->
                <td class="px-4 py-3 text-right text-sm w-28">
                  <div v-if="editing.id === item.id && editing.type === activeTab" class="flex justify-end gap-2">
                    <button @click="saveEdit" class="px-3 py-1 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Guardar</button>
                    <button @click="cancelEdit" class="px-3 py-1 rounded-lg border dark:border-gray-600 dark:text-gray-200 hover:dark:bg-gray-700">Cancelar</button>
                  </div>

                  <div v-else class="flex justify-end gap-2">
                    <button @click="startEdit(activeTab, item)" class="px-3 py-1 rounded-lg bg-yellow-400 hover:bg-yellow-500">Editar</button>
                    <!-- eliminar solo para tabs no-usuarios -->
                    <button v-if="activeTab !== 'usuarios'" @click="removeItem(activeTab, item)" class="px-3 py-1 rounded-lg bg-red-600 text-white hover:bg-red-700">Eliminar</button>
                  </div>
                </td>
              </tr>

              <tr v-if="(filteredList(activeTab) || []).length === 0">
                <td :colspan="activeTab === 'usuarios' ? 4 : 8" class="px-4 py-6 text-center text-sm text-muted-foreground dark:text-gray-400">
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
