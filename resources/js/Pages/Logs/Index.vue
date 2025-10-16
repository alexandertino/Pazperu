<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
  logs: { type: Array, default: () => [] }
});

// filtros
const searchUser = ref('');
const searchAction = ref('');
const searchModel = ref('');
const projectFilter = ref('');
const tableTypeFilter = ref('');
const nActaSearch = ref(''); // ejemplo: "AE - 125 - 2025"

// parsea safe cualquier log.changes (string JSON o objeto)
function parseChanges(changes) {
  if (!changes) return null;
  if (typeof changes === 'string') {
    try {
      return JSON.parse(changes);
    } catch {
      return changes;
    }
  }
  return changes;
}

function displayValue(v) {
  if (v === null) return '—';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}

// construye filas para updates (old vs new)
function getComparisonRows(changesObj) {
  const oldObj = changesObj?.old ?? null;
  const newObj = changesObj?.new ?? null;
  if (!oldObj && !newObj) return [];

  const keys = new Set();
  if (oldObj && typeof oldObj === 'object') Object.keys(oldObj).forEach(k => keys.add(k));
  if (newObj && typeof newObj === 'object') Object.keys(newObj).forEach(k => keys.add(k));

  const rows = [];
  for (const key of Array.from(keys)) {
    const oldVal = oldObj ? oldObj[key] : undefined;
    const newVal = newObj ? newObj[key] : undefined;
    const changed = JSON.stringify(oldVal) !== JSON.stringify(newVal);
    rows.push({
      key,
      oldVal,
      newVal,
      changed
    });
  }
  return rows;
}

/* --- extracción metadata del model --- */
function extractModelMeta(model) {
  const raw = (model || '').trim();
  if (!raw) return { tableType: '', projectName: '' };
  const parts = raw.split('_').filter(Boolean);
  const tableType = parts[0] || '';

  let projectName = '';
  const proyectoIdx = parts.findIndex(p => p.toLowerCase() === 'proyecto');
  if (proyectoIdx >= 0 && proyectoIdx + 1 < parts.length) {
    projectName = parts[proyectoIdx + 1];
  } else if (parts.length >= 2) {
    projectName = parts[parts.length - 1];
  } else {
    projectName = '';
  }

  return { tableType: tableType.toLowerCase(), projectName: projectName.toLowerCase() };
}

/* --- búsqueda recursiva por clave/valor dentro de un objeto --- */
function searchKeyValue(obj, keyName, searchVal) {
  if (!obj || !keyName || !searchVal) return null;
  const needle = String(searchVal).toLowerCase();

  function recurse(target) {
    if (target === null || target === undefined) return null;
    if (typeof target !== 'object') return null;

    if (Array.isArray(target)) {
      for (const item of target) {
        const r = recurse(item);
        if (r) return r;
      }
      return null;
    }

    for (const k of Object.keys(target)) {
      const v = target[k];

      if (String(k).toLowerCase() === keyName.toLowerCase()) {
        if (v === null || v === undefined) continue;
        if (String(v).toLowerCase().includes(needle)) return { key: k, value: v };
      }

      if (typeof v === 'object') {
        const found = recurse(v);
        if (found) return found;
      } else {
        if (String(v).toLowerCase().includes(needle)) {
          return { key: k, value: v };
        }
      }
    }

    return null;
  }

  return recurse(obj);
}

/* --- busca n_acta dentro del parsedChanges del log --- */
function findNActaInLog(parsedChanges, nActa) {
  if (!nActa) return null;
  if (!parsedChanges) return null;

  if (typeof parsedChanges === 'string') {
    if (parsedChanges.toLowerCase().includes(nActa.toLowerCase())) {
      return { key: null, value: parsedChanges };
    }
    return null;
  }

  return searchKeyValue(parsedChanges, 'n_acta', nActa);
}

/* --- logs enriquecidos --- */
const logsWithMeta = computed(() => {
  return props.logs.map(log => {
    const model = log.model || '';
    const meta = extractModelMeta(model);
    return {
      ...log,
      __modelMeta: meta,
      __parsedChanges: parseChanges(log.changes)
    };
  });
});

/* --- sets únicos para selects --- */
const uniqueProjects = computed(() => {
  const set = new Set();
  for (const l of logsWithMeta.value) {
    const p = l.__modelMeta.projectName;
    if (p) set.add(p);
  }
  return Array.from(set).sort();
});

const uniqueTableTypes = computed(() => {
  const set = new Set();
  for (const l of logsWithMeta.value) {
    const t = l.__modelMeta.tableType;
    if (t) set.add(t);
  }
  return Array.from(set).sort();
});

/* --- filteredEntries: devuelve array de { log, matchesActa } --- */
const filteredEntries = computed(() => {
  const entries = [];

  for (const log of logsWithMeta.value) {
    // filtros base
    if (searchUser.value) {
      const name = (log.user?.name || '').toLowerCase();
      if (!name.includes(searchUser.value.toLowerCase())) continue;
    }
    if (searchAction.value) {
      if (log.action !== searchAction.value) continue;
    }
    if (searchModel.value) {
      if (!((log.model || '').toLowerCase().includes(searchModel.value.toLowerCase()))) continue;
    }
    if (projectFilter.value) {
      const proj = log.__modelMeta.projectName || '';
      if (!proj.includes(projectFilter.value.toLowerCase())) continue;
    }
    if (tableTypeFilter.value) {
      const type = log.__modelMeta.tableType || '';
      if (type !== tableTypeFilter.value.toLowerCase()) continue;
    }

    // búsqueda por n_acta
    let matchesActa = null;
    if (nActaSearch.value && nActaSearch.value.trim() !== '') {
      // <-- POR DEFECTO: sólo buscamos en 'salidas' -->
      if (log.__modelMeta.tableType === 'salidas') {
        matchesActa = findNActaInLog(log.__parsedChanges, nActaSearch.value);
      } else {
        matchesActa = null;
      }

      // Si quieres que n_acta busque en cualquier tabla, sustituye las dos líneas anteriores por:
      // matchesActa = findNActaInLog(log.__parsedChanges, nActaSearch.value);

      if (!matchesActa) continue; // si no encontró, saltamos este log
    }

    entries.push({ log, matchesActa });
  }

  // ordenar por fecha (desc)
  entries.sort((a, b) => new Date(b.log.created_at) - new Date(a.log.created_at));
  return entries;
});
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
        Reportes de Actividad
      </h2>
    </template>

    <div class="p-6 space-y-4">
      <!-- Filtros -->
      <div class="flex gap-4 flex-wrap">
        <input v-model="searchUser" placeholder="Filtrar por usuario"
          class="px-3 py-2 border rounded-lg w-48 dark:bg-gray-800 dark:text-white" />

        <select v-model="searchAction" class="px-3 py-2 border rounded-lg dark:bg-gray-800 dark:text-white">
          <option value="">Todas las acciones</option>
          <option value="create">Creado</option>
          <option value="update">Cambios</option>
          <option value="delete">Elimino</option>
        </select>

        <select v-model="projectFilter" class="px-3 py-2 border rounded-lg dark:bg-gray-800 dark:text-white">
          <option value="">Todos los proyectos</option>
          <option v-for="p in uniqueProjects" :key="p" :value="p">{{ p }}</option>
        </select>

        <select v-model="tableTypeFilter" class="px-3 py-2 border rounded-lg dark:bg-gray-800 dark:text-white">
          <option value="">Todos los tipos</option>
          <option v-for="t in uniqueTableTypes" :key="t" :value="t">{{ t }}</option>
        </select>

        <!-- Input n_acta: por defecto solo aplica a logs cuyo tipo sea 'salidas' -->
        <input v-model="nActaSearch"
          placeholder="Buscar n_acta (ej. AE - 125 - 2025) — busca solo en Salidas por defecto"
          class="px-3 py-2 border rounded-lg w-64 dark:bg-gray-800 dark:text-white" />
      </div>

      <!-- Tabla principal -->
      <table class="min-w-full border border-gray-300 dark:border-white dark:text-white">
        <thead class="bg-gray-100 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-2 text-left">Usuario</th>
            <th class="px-4 py-2 text-left">Acción</th>
            <th class="px-4 py-2 text-left">Modelo</th>
            <th class="px-4 py-2 text-left">Cambios</th>
            <th class="px-4 py-2 text-left">Fecha</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="entry in filteredEntries" :key="entry.log.id" :class="[
            'border-t dark:border-white-700 dark:text-white align-top',
            entry.matchesActa ? 'bg-green-50 dark:bg-green-900/20' : ''
          ]">
            <td class="px-4 py-3">{{ entry.log.user?.name ?? '—' }}</td>

            <td class="px-4 py-3">
              <span :class="{
                'text-green-600 font-semibold': entry.log.action === 'create',
                'text-red-600 font-semibold': entry.log.action === 'delete',
                'text-yellow-600 font-semibold': entry.log.action === 'update'
              }">
                {{ entry.log.action }}
              </span>
            </td>

            <td class="px-4 py-3">
              {{ entry.log.model ?? '—' }}
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ entry.log.__modelMeta.tableType }} / {{ entry.log.__modelMeta.projectName }}
              </div>
            </td>

            <td class="px-4 py-3 w-1/2">
              <details class="group">
                <summary class="cursor-pointer text-blue-600 dark:text-blue-400 flex items-center gap-2">
                  Ver cambios
                  <span v-if="entry.matchesActa"
                    class="text-sm px-2 py-0.5 rounded bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                    n_acta: {{ entry.matchesActa.value }}
                  </span>
                </summary>

                <div class="mt-2 text-xs">
                  <template v-if="typeof entry.log.__parsedChanges === 'object'">
                    <template v-if="entry.log.__parsedChanges.deleted && !entry.log.__parsedChanges.old">
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <tbody>
                          <tr v-for="(value, key) in entry.log.__parsedChanges.deleted" :key="key"
                            class="border-t even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ key }}</td>
                            <td class="px-2 py-1">{{ displayValue(value) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else-if="entry.log.__parsedChanges.new && !entry.log.__parsedChanges.old">
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <tbody>
                          <tr v-for="(value, key) in entry.log.__parsedChanges.new" :key="key"
                            class="border-t even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ key }}</td>
                            <td class="px-2 py-1">{{ displayValue(value) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else-if="entry.log.__parsedChanges.old && entry.log.__parsedChanges.new">
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th class="px-2 py-1">Campo</th>
                            <th class="px-2 py-1">Antes</th>
                            <th class="px-2 py-1">Después</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="row in getComparisonRows(entry.log.__parsedChanges)" :key="row.key"
                            :class="row.changed ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900'">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ row.key }}</td>
                            <td class="px-2 py-1"><small>{{ displayValue(row.oldVal) }}</small></td>
                            <td class="px-2 py-1"><small>{{ displayValue(row.newVal) }}</small></td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else>
                      <pre
                        class="mt-2 p-2 rounded bg-gray-100 dark:bg-gray-800 overflow-x-auto">{{ JSON.stringify(entry.log.__parsedChanges, null, 2) }}</pre>
                    </template>
                  </template>

                  <template v-else>
                    <pre
                      class="mt-2 p-2 rounded bg-gray-100 dark:bg-gray-800 overflow-x-auto">{{ entry.log.__parsedChanges }}</pre>
                  </template>
                </div>
              </details>
            </td>

            <td class="px-4 py-3">{{ new Date(entry.log.created_at).toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AuthenticatedLayout>
</template>
