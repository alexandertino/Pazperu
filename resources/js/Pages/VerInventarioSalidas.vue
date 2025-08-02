<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

// 📌 Lista de proyectos
const proyectos = reactive([
    {
        id: 1,
        nombre: 'Proyecto Alpha',
        pestaña: 'inventario',
        inventario: [
            {
                codigo: 'P001',
                fecha: '2025-07-01',
                descripcion: 'Madera',
                unidad: 'm³',
                entradas: 100,
                salidas: 20,
                stock: 80,
            },
            {
                codigo: 'P002',
                fecha: '2025-07-01',
                descripcion: 'Piedra',
                unidad: 'm³',
                entradas: 100,
                salidas: 20,
                stock: 80,
            },
        ],
        salidas: [
            {
                id: 1,
                acta: 'A001',
                nombre: 'Juan Pérez',
                lugar: 'Lima',
                distrito: 'San Borja',
                fecha: '2025-07-02',
                producto: 'Madera',
                cantidad: 20,
            },
            {
                id: 2,
                acta: 'A002',
                nombre: 'Juan Pérez',
                lugar: 'Lima',
                distrito: 'San Borja',
                fecha: '2025-07-02',
                producto: 'Piedra',
                cantidad: 20,
            },
        ],
    },
]);

// 📌 Cambiar pestaña
function cambiarPestana(id, nuevaPestana) {
    const proyecto = proyectos.find(p => p.id === id);
    if (proyecto) proyecto.pestaña = nuevaPestana;
}

// 📌 Navegar a página de agregar inventario
function irAInventarioSalidas() {
    router.visit('/proyectos/inventario-nuevo');
}

// 📌 Métodos de acción
function verItem(item) {
    alert(`📄 Ver: ${JSON.stringify(item, null, 2)}`);
}

function editarItem(item) {
    alert(`✏️ Editar: ${JSON.stringify(item, null, 2)}`);
}

function eliminarItem(item) {
    if (confirm(`¿Seguro que deseas eliminar este registro?`)) {
        alert(`🗑️ Eliminado: ${JSON.stringify(item, null, 2)}`);
    }
}
</script>

<template>
    <Head title="Inventario y Salidas por Proyecto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">
                Proyectos: Inventario y Salidas
            </h2>
        </template>

        <div class="py-8 space-y-6">
            <div
                v-for="proyecto in proyectos"
                :key="proyecto.id"
                class="bg-white dark:bg-gray-800 shadow rounded-xl p-6"
            >
                <!-- 🔹 Título del proyecto -->
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {{ proyecto.nombre }}
                    </h3>
                </div>

                <!-- 🔹 Pestañas + Botón Agregar -->
                <div class="flex items-center justify-between mb-6">
                    <!-- 🔹 Pestañas -->
                    <div class="flex space-x-4">
                        <button
                            @click="cambiarPestana(proyecto.id, 'inventario')"
                            :class="[
                                'px-4 py-2 rounded transition font-medium',
                                proyecto.pestaña === 'inventario'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            Inventario
                        </button>
                        <button
                            @click="cambiarPestana(proyecto.id, 'salidas')"
                            :class="[
                                'px-4 py-2 rounded transition font-medium',
                                proyecto.pestaña === 'salidas'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            Salidas
                        </button>
                    </div>

                    <!-- 🔹 Botón Agregar -->
                    <div>
                        <button
                            v-if="proyecto.pestaña === 'inventario'"
                            @click="irAInventarioSalidas"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded shadow"
                        >
                            ➕ Agregar Inventario
                        </button>

                        <button
                            v-if="proyecto.pestaña === 'salidas'"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded shadow"
                        >
                            ➕ Agregar Salida
                        </button>
                    </div>
                </div>
                
                <!-- 🔹 Tabla de Inventario -->
                <div v-if="proyecto.pestaña === 'inventario'" class="overflow-x-auto">
                    <table class="w-full text-sm border dark:border-gray-700 border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-700 ">
                            <tr class="p-2 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                                <th class="p-2 border dark:border-gray-600">Código</th>
                                <th class="p-2 border dark:border-gray-600">Fecha</th>
                                <th class="p-2 border dark:border-gray-600">Descripción</th>
                                <th class="p-2 border dark:border-gray-600">U.M.</th>
                                <th class="p-2 border dark:border-gray-600">Entradas</th>
                                <th class="p-2 border dark:border-gray-600">Salidas</th>
                                <th class="p-2 border dark:border-gray-600">Stock</th>
                                <th class="p-2 border dark:border-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800">
                            <tr
                                v-for="(item, index) in proyecto.inventario"
                                :key="`${item.codigo}-${index}`"
                                class="border-t dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700
                                text-gray-800 dark:text-gray-100"
                            >
                                <td class="p-2 border dark:border-gray-600">{{ item.codigo }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.fecha }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.descripcion }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.unidad }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.entradas }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.salidas }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ item.stock }}</td>
                                <td class="p-2 border dark:border-gray-600 text-center">
                                    <button @click="editarItem(item)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded mr-1">✏️</button>
                                    <button @click="eliminarItem(item)" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded mr-1">🗑️</button>
                                    <button @click="verItem(item)" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded">👁️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 🔹 Línea divisoria -->
                <div class="my-6 border-t border-gray-300 dark:border-gray-600"></div>

                <!-- 🔹 Tabla de Salidas -->
                <div v-if="proyecto.pestaña === 'salidas'" class="overflow-x-auto">
                    <table class="w-full text-sm border dark:border-gray-700 border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr class ="p-2 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                                <th class="p-2 border dark:border-gray-600">N° Acta</th>
                                <th class="p-2 border dark:border-gray-600">Nombre</th>
                                <th class="p-2 border dark:border-gray-600">Lugar</th>
                                <th class="p-2 border dark:border-gray-600">Distrito</th>
                                <th class="p-2 border dark:border-gray-600">Fecha</th>
                                <th class="p-2 border dark:border-gray-600">Producto</th>
                                <th class="p-2 border dark:border-gray-600">Cantidad</th>
                                <th class="p-2 border dark:border-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800">
                            <tr
                                v-for="(salida, index) in proyecto.salidas"
                                :key="`${salida.id}-${index}`"
                                class="border-t dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700
                                text-gray-800 dark:text-gray-100"
                            >
                                <td class="p-2 border dark:border-gray-600">{{ salida.acta }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.nombre }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.lugar }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.distrito }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.fecha }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.producto }}</td>
                                <td class="p-2 border dark:border-gray-600">{{ salida.cantidad }}</td>
                                <td class="p-2 border dark:border-gray-600 text-center">
                                    <button @click="editarItem(salida)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded mr-1">✏️</button>
                                    <button @click="eliminarItem(salida)" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded mr-1">🗑️</button>
                                    <button @click="verItem(salida)" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded">👁️</button>
                                </td>
                            </tr>
                            <tr v-if="proyecto.salidas.length === 0">
                                <td colspan="8" class="p-2 text-center text-gray-500 dark:text-gray-400">
                                    Sin registros
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
