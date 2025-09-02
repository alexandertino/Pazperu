<script setup>
// 📦 Importamos componentes y librerías necesarias
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Layout para usuarios autenticados
import { useForm } from '@inertiajs/vue3'; // Manejo de formularios con Inertia
import { computed, ref, reactive, onMounted } from 'vue'; // Herramientas de Vue
import axios from 'axios'; // Cliente HTTP
import { usePage } from '@inertiajs/vue3'; // Acceso a props globales (ej: usuario logueado)
import Swal from 'sweetalert2'; // Librería para alertas bonitas

// 👤 Usuario autenticado
const user = usePage().props.auth.user;

// 📌 Props recibidas desde el backend (proyecto actual)
const props = defineProps({
    proyecto: Object
});

// 📑 Definición del formulario (reactivo con useForm de Inertia)
const form = useForm({
    proyecto_id: props.proyecto.id, // Relación con el proyecto
    anio: '',
    numero: '',
    codigo_final: '',
    fecha: '',
    descripcion: '',
    categoria: '',
    unidad_medida: '',
    entradas: '',
    salidas: '',
    stock: '',
    codigo: '',
    precio: '',
    solicitado_por: ''
});

// 📦 Datos desde el backend
const categorias = ref([]);
const unidades = ref([]);
const solicitantes = ref([]);

// 🔎 Filtrados en tiempo real
const categoriasFiltradas = computed(() =>
    categorias.value.filter(c =>
        c.nombre.toLowerCase().includes(form.value.categoria.toLowerCase())
    )
);

const unidadesFiltradas = computed(() =>
    unidades.value.filter(u =>
        u.nombre.toLowerCase().includes(form.value.unidad_medida.toLowerCase())
    )
);

const solicitantesFiltrados = computed(() =>
    solicitantes.value.filter(s =>
        s.nombre.toLowerCase().includes(form.value.solicitado_por.toLowerCase())
    )
);

// ✅ Seleccionar sugerencia
const seleccionarCategoria = (nombre) => {
    form.value.categoria = nombre;
};

const seleccionarUnidad = (nombre) => {
    form.value.unidad_medida = nombre;
};

const seleccionarSolicitante = (nombre) => {
    form.value.solicitado_por = nombre;
};

// 🔄 Cargar datos iniciales
onMounted(async () => {
    const resCat = await axios.get("/categorias");
    categorias.value = resCat.data;

    const resUni = await axios.get("/unidades-medida");
    unidades.value = resUni.data;

    const resSol = await axios.get("/solicitantes");
    solicitantes.value = resSol.data;
});

// 🔄 Controla si se muestra o no el campo "Comentario"
const mostrarComentario = ref(false);

// 🔙 Función para volver a la tabla de inventarios
const volverATabla = () => {
    window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};

// 🔢 Genera automáticamente el código de producto a partir de año, número y código final
const codigoCompleto = computed(() => {
    try {
        const anio = form.anio.padStart(4, '0');
        const numero = form.numero.padStart(3, '0');
        const codigoFinal = form.codigo_final.padStart(3, '0');
        return `IDPP/${anio}_${numero}_C${codigoFinal}`;
    } catch (err) {
        console.error("❌ Error generando código:", err);
        return "";
    }
});

// ✅ Validación de campos obligatorios
const validarCampos = () => {
    if (!form.anio || !form.numero || !form.codigo_final || !form.fecha ||
        !form.descripcion || !form.categoria || !form.unidad_medida ||
        form.entradas === null || form.precio === null) {
        Swal.fire('⚠️ Campos incompletos', 'Por favor, complete todos los campos antes de guardar.', 'warning');
        return false;
    }
    return true;
};

// 📌 Guardar inventario (solo admin)
const guardarNormal = () => {
    if (user.role !== 'admin') {
        Swal.fire('⛔ Acceso denegado', 'No tienes permisos para guardar en el inventario.', 'error');
        return;
    }

    if (!validarCampos()) return;

    Swal.fire({
        title: '¿Guardar inventario?',
        text: "Se registrará en la base de datos",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.codigo = codigoCompleto.value;
        form.stock = form.entradas;
        form.salidas = 0;

        axios.post(`/proyectos/${props.proyecto.id}/inventarios`, form, {
            headers: { 'Accept': 'application/json' }
        })
            .then(() => {
                Swal.fire('✅ Guardado', 'Inventario creado correctamente', 'success')
                    .then(() => window.location.reload());
            })
            .catch(error => {
                console.error("❌ Error en guardarNormal:", error);
                Swal.fire('❌ Error', 'Ocurrió un error inesperado al guardar.', 'error');
            });
    });
};

// 📌 Guardar inventario manteniendo datos (solo admin)
const guardarMantener = () => {
    if (user.role !== 'admin') {
        Swal.fire('⛔ Acceso denegado', 'No tienes permisos para guardar en el inventario.', 'error');
        return;
    }

    if (!validarCampos()) return;

    Swal.fire({
        title: '¿Guardar inventario manteniendo datos?',
        text: "Se guardará y algunos campos se conservarán",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        try {
            const fechaActual = form.fecha;
            const anioActual = form.anio;
            const numeroActual = form.numero;
            const codigoFinalActual = parseInt(form.codigo_final || 0, 10);
            const solicitadoPorActual = form.solicitado_por;

            form.codigo = codigoCompleto.value;
            form.stock = form.entradas;
            form.salidas = 0;

            axios.post(`/proyectos/${props.proyecto.id}/inventarios`, form, {
                headers: { 'Accept': 'application/json' }
            })
                .then(() => {
                    Swal.fire('✅ Guardado', 'Inventario creado correctamente (manteniendo datos)', 'success');

                    // 🔹 Reseteo selectivo (sin borrar solicitado_por)
                    form.descripcion = '';
                    form.categoria = '';
                    form.unidad_medida = '';
                    form.entradas = 0;
                    form.precio = 0;
                    form.fecha = fechaActual;
                    form.anio = anioActual;
                    form.numero = numeroActual;
                    form.solicitado_por = solicitadoPorActual;
                    form.codigo_final = (codigoFinalActual + 1).toString().padStart(3, '0');
                })
                .catch(error => {
                    console.error("❌ Error en guardarMantener:", error);
                    if (error.response && error.response.status === 422) {
                        Swal.fire('⚠️ Error', error.response.data.error || 'El código ya está registrado', 'warning');
                    } else {
                        Swal.fire('❌ Error', 'Ocurrió un error inesperado al guardar.', 'error');
                    }
                });
        } catch (err) {
            console.error("❌ Error en la función guardarMantener:", err);
        }
    });
};

// 🚀 Acción por defecto al enviar formulario
const submit = () => {
    guardarNormal();
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Contenedor principal -->
        <div class="bg-white dark:bg-gray-800 max-w-3xl mx-auto mt-6 p-6 shadow rounded-lg"
            v-if="user.role === 'admin'">

            <!-- Título -->
            <h1 class="text-2xl font-bold mb-6 text-gray-700 dark:text-gray-200">
                Crear Inventario
            </h1>

            <!-- Formulario -->
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Código Producto -->
                <div>
                    <label class="block font-bold mb-2 text-gray-700 dark:text-gray-200">
                        Código Producto
                    </label>
                    <div
                        class="flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <!-- Código dinámico dividido en 3 partes -->
                        <span class="text-gray-500">IDPP/</span>
                        <input v-model="form.anio" type="text" maxlength="4"
                            class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded" required>
                        <span class="text-gray-500">_</span>
                        <input v-model="form.numero" type="text" maxlength="3"
                            class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded" required>
                        <span class="text-gray-500">_C</span>
                        <input v-model="form.codigo_final" type="text" maxlength="3"
                            class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded" required>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Código generado: <strong>{{ codigoCompleto }}</strong>
                    </p>
                </div>

                <!-- Fecha -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Fecha</label>
                    <input v-model="form.fecha" type="date"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Descripción</label>
                    <input v-model="form.descripcion" type="text"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                </div>

                <!-- Categoria -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Categoría</label>
                    <input v-model="form.categoria" type="text" list="categoriasList" @change="autocompletarCategoria"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required />
                    <datalist id="categoriasList">
                        <option v-for="c in categorias" :key="c.id" :value="c.nombre" />
                    </datalist>
                </div>

                <!-- Unidad de Medida -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Unidad de Medida</label>
                    <input v-model="form.unidad_medida" type="text" list="unidadesList" @change="autocompletarUnidad"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required />
                    <datalist id="unidadesList">
                        <option v-for="u in unidades" :key="u.id" :value="u.nombre" />
                    </datalist>
                </div>

                <!-- Entradas -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Entradas</label>
                    <input v-model="form.entradas" type="number" min="1"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                </div>

                <!-- Precio -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Precio (S/) (Unitario)</label>
                    <input v-model="form.precio" type="number" step="0.01" min="0"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required>
                </div>

                <!-- Solicitado por -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Solicitado por</label>
                    <input v-model="form.solicitado_por" type="text" list="solicitantesList"
                        @change="autocompletarSolicitante"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white" required />
                    <datalist id="solicitantesList">
                        <option v-for="s in solicitantes" :key="s.id" :value="s.nombre" />
                    </datalist>
                </div>

                <!-- Comentario (opcional, se muestra con toggle) -->
                <div v-if="mostrarComentario" class="mt-3">
                    <label class="block font-bold mb-1 dark:text-gray-200">Comentario</label>
                    <textarea v-model="form.comentario" rows="3" placeholder="Escribe un comentario..."
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white"></textarea>
                </div>

                <!-- Botones de acción -->
                <div class="flex gap-2">
                    <!-- Guardar normal -->
                    <button type="button" @click="guardarNormal"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600">
                        💾 Guardar
                    </button>

                    <!-- Guardar manteniendo datos -->
                    <button type="button" @click="guardarMantener"
                        class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600">
                        📌 Mantener Datos
                    </button>

                    <!-- Volver -->
                    <button type="button" @click="volverATabla"
                        class="ml-auto bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700">
                        ↩ Volver
                    </button>

                    <!-- Mostrar/Ocultar comentario -->
                    <button type="button" @click="mostrarComentario = !mostrarComentario" :class="[
                        'px-4 py-2 rounded transition',
                        mostrarComentario
                            ? 'bg-purple-700 text-white hover:bg-purple-800'
                            : 'bg-purple-500 text-white hover:bg-purple-700'
                    ]">
                        {{ mostrarComentario ? 'Ocultar comentario' : 'Agregar comentario' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
