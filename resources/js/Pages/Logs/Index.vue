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

const filteredLogs = computed(() => {
  return props.logs.filter(log => {
    return (
      (!searchUser.value || (log.user?.name || '').toLowerCase().includes(searchUser.value.toLowerCase())) &&
      (!searchAction.value || log.action === searchAction.value) &&
      (!searchModel.value || (log.model || '').toLowerCase().includes(searchModel.value.toLowerCase()))
    );
  });
});

// parsea safe cualquier log.changes (string JSON o objeto)
function parseChanges(changes) {
  if (!changes) return null;
  if (typeof changes === 'string') {
    try {
      return JSON.parse(changes);
    } catch {
      // si no es JSON, devolver la cadena cruda
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

        <input v-model="searchModel" placeholder="Filtrar por modelo"
          class="px-3 py-2 border rounded-lg w-48 dark:bg-gray-800 dark:text-white" />
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
          <tr v-for="log in filteredLogs" :key="log.id" class="border-t dark:border-white-700 dark:text-white align-top">
            <td class="px-4 py-3">{{ log.user?.name ?? '—' }}</td>

            <td class="px-4 py-3">
              <span
                :class="{
                  'text-green-600 font-semibold': log.action === 'create',
                  'text-red-600 font-semibold': log.action === 'delete',
                  'text-yellow-600 font-semibold': log.action === 'update'
                }"
              >
                {{ log.action }}
              </span>
            </td>

            <td class="px-4 py-3">{{ log.model ?? '—' }}</td>

            <!-- Cambios: caso create / delete / update -->
            <td class="px-4 py-3 w-1/2">
              <details class="group">
                <summary class="cursor-pointer text-blue-600 dark:text-blue-400">Ver cambios</summary>

                <div class="mt-2 text-xs">
                  <!-- parse object -->
                  <template v-if="typeof parseChanges(log.changes) === 'object'">
                    <!-- Delete o Create simples: objeto con 'deleted' o 'new' -->
                    <template v-if="parseChanges(log.changes).deleted && !parseChanges(log.changes).old">
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <tbody>
                          <tr v-for="(value, key) in parseChanges(log.changes).deleted" :key="key"
                              class="border-t even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ key }}</td>
                            <td class="px-2 py-1">{{ displayValue(value) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else-if="parseChanges(log.changes).new && !parseChanges(log.changes).old">
                      <!-- create -->
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <tbody>
                          <tr v-for="(value, key) in parseChanges(log.changes).new" :key="key"
                              class="border-t even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ key }}</td>
                            <td class="px-2 py-1">{{ displayValue(value) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else-if="parseChanges(log.changes).old && parseChanges(log.changes).new">
                      <!-- update: comparación -->
                      <table class="w-full text-left text-xs border rounded overflow-hidden">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th class="px-2 py-1">Campo</th>
                            <th class="px-2 py-1">Antes</th>
                            <th class="px-2 py-1">Después</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="row in getComparisonRows(parseChanges(log.changes))" :key="row.key"
                              :class="row.changed ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'even:bg-white odd:bg-gray-50 dark:even:bg-gray-800 dark:odd:bg-gray-900'">
                            <td class="px-2 py-1 font-medium text-gray-700 dark:text-gray-300">{{ row.key }}</td>
                            <td class="px-2 py-1"><small>{{ displayValue(row.oldVal) }}</small></td>
                            <td class="px-2 py-1"><small>{{ displayValue(row.newVal) }}</small></td>
                          </tr>
                        </tbody>
                      </table>
                    </template>

                    <template v-else>
                      <!-- fallback: render JSON formateado si no encaja -->
                      <pre class="mt-2 p-2 rounded bg-gray-100 dark:bg-gray-800 overflow-x-auto">{{ JSON.stringify(parseChanges(log.changes), null, 2) }}</pre>
                    </template>
                  </template>

                  <!-- Si parseChanges devolvió string u otro -->
                  <template v-else>
                    <pre class="mt-2 p-2 rounded bg-gray-100 dark:bg-gray-800 overflow-x-auto">{{ parseChanges(log.changes) }}</pre>
                  </template>
                </div>
              </details>
            </td>

            <td class="px-4 py-3">{{ new Date(log.created_at).toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AuthenticatedLayout>
</template>
