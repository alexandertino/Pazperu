<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
    tablas: Array
})

const tablaSeleccionada = ref(null)
const datos = ref([])
const columnas = ref([])
const cargando = ref(false)

// ─── Modal agregar ───
const mostrarModal = ref(false)
const guardando = ref(false)

const formAgregar = ref({
    mes: new Date().getMonth() + 1,
    anio: new Date().getFullYear(),
    fecha: '',
    posicion: 1,
    descripcion: '',
    n_acta: '',
    presupuestario: '',
    actividad: '',
    ingresos: 0,
    egresos: 0,
})

const meses = [
    { value: 1, label: 'Enero' },
    { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' },
    { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' },
]

// Registros filtrados por mes/año seleccionado en el modal
const registrosMesAnio = computed(() => {
    if (!datos.value.length) return []
    const m = formAgregar.value.mes
    const a = formAgregar.value.anio
    return datos.value.filter(r => {
        if (!r.fecha) return false
        const d = new Date(r.fecha)
        return (d.getMonth() + 1) === m && d.getFullYear() === a
    })
})

// Opciones de posición dinámicas
const opcionesPosicion = computed(() => {
    const regs = registrosMesAnio.value
    const opciones = []

    if (regs.length === 0) {
        opciones.push({ value: 1, label: '1 — Primer registro del mes' })
        return opciones
    }

    for (let i = 0; i <= regs.length; i++) {
        if (i === 0) {
            const desc = regs[0]?.descripcion || regs[0]?.n_acta || `ID ${regs[0]?.id}`
            opciones.push({ value: 1, label: `1 — Antes de "${truncar(desc, 40)}"` })
        } else if (i === regs.length) {
            const desc = regs[i - 1]?.descripcion || regs[i - 1]?.n_acta || `ID ${regs[i - 1]?.id}`
            opciones.push({ value: i + 1, label: `${i + 1} — Después de "${truncar(desc, 40)}" (al final)` })
        } else {
            const antes = regs[i - 1]?.descripcion || regs[i - 1]?.n_acta || `ID ${regs[i - 1]?.id}`
            const despues = regs[i]?.descripcion || regs[i]?.n_acta || `ID ${regs[i]?.id}`
            opciones.push({ value: i + 1, label: `${i + 1} — Entre "${truncar(antes, 25)}" y "${truncar(despues, 25)}"` })
        }
    }
    return opciones
})

// Resetear posición cuando cambia mes/año
watch([() => formAgregar.value.mes, () => formAgregar.value.anio], () => {
    formAgregar.value.posicion = 1
})

// Generar años disponibles
const aniosDisponibles = computed(() => {
    const currentYear = new Date().getFullYear()
    const years = []
    for (let y = currentYear - 5; y <= currentYear + 2; y++) {
        years.push(y)
    }
    return years
})

const truncar = (text, max) => {
    if (!text) return '—'
    return text.length > max ? text.substring(0, max) + '…' : text
}

const abrirModalAgregar = () => {
    formAgregar.value = {
        mes: new Date().getMonth() + 1,
        anio: new Date().getFullYear(),
        fecha: '',
        posicion: 1,
        descripcion: '',
        n_acta: '',
        presupuestario: '',
        actividad: '',
        ingresos: 0,
        egresos: 0,
    }
    mostrarModal.value = true
}

const cerrarModal = () => {
    mostrarModal.value = false
}

// Construir fecha del primer día del mes seleccionado como default
const fechaDefault = computed(() => {
    const m = String(formAgregar.value.mes).padStart(2, '0')
    const a = formAgregar.value.anio
    return `${a}-${m}-01`
})

const enviarAgregar = async () => {
    if (!tablaSeleccionada.value) return

    guardando.value = true

    const fecha = formAgregar.value.fecha || fechaDefault.value

    try {
        const res = await axios.post(`/orden/${tablaSeleccionada.value}/agregar`, {
            fecha: fecha,
            posicion: formAgregar.value.posicion,
            descripcion: formAgregar.value.descripcion,
            n_acta: formAgregar.value.n_acta,
            presupuestario: formAgregar.value.presupuestario,
            actividad: formAgregar.value.actividad,
            ingresos: formAgregar.value.ingresos || 0,
            egresos: formAgregar.value.egresos || 0,
        })

        mostrarModal.value = false
        Swal.fire('¡Agregado!', res.data.message || 'Registro agregado correctamente', 'success')
        cargar(tablaSeleccionada.value)
    } catch (error) {
        Swal.fire('Error', error.response?.data?.error || 'No se pudo agregar el registro', 'error')
    } finally {
        guardando.value = false
    }
}

// ─── Funciones existentes ───
const cargar = async (tabla) => {
    cargando.value = true
    tablaSeleccionada.value = tabla

    try {
        const res = await axios.get(`/orden/${tabla}`)
        datos.value = res.data

        if (res.data.length > 0) {
            columnas.value = Object.keys(res.data[0]).filter(c => 
                !c.endsWith('_at') && 
                !['ingresos', 'egresos', 'saldo'].includes(c)
            )
        }
    } catch (error) {
        Swal.fire('Error', 'No se pudieron cargar los datos', 'error')
    } finally {
        cargando.value = false
    }
}

const subir = async (id) => {
    const result = await Swal.fire({
        title: '¿Subir registro?',
        text: 'Este registro subirá una posición',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, subir',
        cancelButtonText: 'Cancelar'
    })

    if (!result.isConfirmed) return

    try {
        await axios.post(`/orden/${tablaSeleccionada.value}/subir/${id}`)
        Swal.fire('¡Subido!', 'Registro movido hacia arriba', 'success')
        cargar(tablaSeleccionada.value)
    } catch (error) {
        Swal.fire('Error', 'No se pudo mover el registro', 'error')
    }
}

const bajar = async (id) => {
    const result = await Swal.fire({
        title: '¿Bajar registro?',
        text: 'Este registro bajará una posición',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, bajar',
        cancelButtonText: 'Cancelar'
    })

    if (!result.isConfirmed) return

    try {
        await axios.post(`/orden/${tablaSeleccionada.value}/bajar/${id}`)
        Swal.fire('¡Bajado!', 'Registro movido hacia abajo', 'success')
        cargar(tablaSeleccionada.value)
    } catch (error) {
        Swal.fire('Error', 'No se pudo mover el registro', 'error')
    }
}

// 🆕 NUEVA FUNCIÓN: Mover a posición específica
const moverAPosicion = async (id, descripcionActual) => {
    const totalRegistros = datos.value.length
    
    const { value: posicion } = await Swal.fire({
        title: 'Mover registro a posición',
        html: `
            <div class="text-left">
                <p class="mb-2"><strong>Registro:</strong> ${descripcionActual.substring(0, 50)}...</p>
                <p class="mb-2"><strong>Posición actual:</strong> #${id}</p>
                <p class="mb-4"><strong>Total registros:</strong> ${totalRegistros}</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Posición deseada:</label>
                <input id="posicion-input" class="swal2-input" placeholder="1 - ${totalRegistros}" type="number" min="1" max="${totalRegistros}">
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Mover',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const pos = parseInt(document.getElementById('posicion-input').value)
            if (isNaN(pos) || pos < 1 || pos > totalRegistros) {
                Swal.showValidationMessage(`Posición inválida. Debe ser entre 1 y ${totalRegistros}`)
                return false
            }
            return pos
        }
    })

    if (!posicion) return

    try {
        await axios.post(`/orden/${tablaSeleccionada.value}/mover-posicion/${id}`, {
            nueva_posicion: posicion
        })
        Swal.fire('¡Movido!', `Registro movido a la posición ${posicion}`, 'success')
        cargar(tablaSeleccionada.value)
    } catch (error) {
        Swal.fire('Error', error.response?.data?.error || 'No se pudo mover el registro', 'error')
    }
}

const regresar = () => {
    router.visit('/proyectos')
}

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0.00'
    const num = Number(value)
    if (isNaN(num)) return value
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatDate = (value) => {
    if (!value) return ''
    const date = new Date(value)
    return date.toLocaleDateString('es-PE')
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <!-- Header con botón regresar -->
        <div class="bg-white shadow-lg border-b border-gray-200 sticky top-0 z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button @click="regresar"
                            class="flex items-center space-x-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <span>Regresar</span>
                        </button>
                        <div class="h-8 w-px bg-gray-300"></div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">📋 Panel de Orden</h1>
                            <p class="text-sm text-gray-500">Reordenar registros cambiando sus IDs</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-400">
                        ⚠️ Modificar IDs cambia el orden físico
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Tarjeta de selección -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    🗂️ Seleccionar tabla
                </label>
                <select @change="cargar($event.target.value)"
                    class="w-full md:w-96 border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    :disabled="cargando">
                    <option value="">-- Selecciona una tabla --</option>
                    <option v-for="t in tablas" :key="t" :value="t">
                        {{ t }}
                    </option>
                </select>

                <div v-if="cargando" class="mt-4 flex items-center text-blue-600">
                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Cargando datos...
                </div>
            </div>

            <!-- Tabla de datos -->
            <div v-if="datos.length" class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">
                        📊 Registros de:
                        <span class="text-blue-600 font-mono">{{ tablaSeleccionada }}</span>
                        <span class="ml-2 text-sm text-gray-500">({{ datos.length }} registros)</span>
                    </h2>
                    <!-- 🆕 BOTÓN AGREGAR -->
                    <button @click="abrirModalAgregar"
                        class="flex items-center space-x-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition duration-200 transform hover:scale-105 shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span class="font-medium">Agregar</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    ID
                                </th>
                                <th v-for="col in columnas.filter(c => c !== 'id')" :key="col"
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ col }}
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(row, index) in datos" :key="row.id" class="hover:bg-gray-50 transition duration-150">
                                <!-- ID con posición -->
                                <td class="px-4 py-3 text-center text-sm font-mono font-bold">
                                    <span class="inline-flex items-center justify-center w-12 px-2 py-1 bg-gray-100 rounded-full text-xs">
                                        #{{ index + 1 }}
                                    </span>
                                </td>
                                
                                <!-- Demás columnas -->
                                <td v-for="col in columnas.filter(c => c !== 'id')" :key="col" class="px-4 py-3 text-sm text-gray-900">
                                    <span v-if="col === 'fecha'" class="text-gray-600">
                                        {{ formatDate(row[col]) }}
                                    </span>
                                    <span v-else-if="col === 'n_acta'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ row[col] || 'Sin acta' }}
                                    </span>
                                    <span v-else-if="col === 'descripcion'" :title="row[col]" class="block max-w-md truncate">
                                        {{ row[col] }}
                                    </span>
                                    <span v-else>{{ row[col] || '—' }}</span>
                                </td>

                                <!-- Botones de acción -->
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button @click="subir(row.id)"
                                            class="p-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition duration-200 transform hover:scale-105"
                                            title="Subir una posición">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            </svg>
                                        </button>
                                        
                                        <button @click="bajar(row.id)"
                                            class="p-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition duration-200 transform hover:scale-105"
                                            title="Bajar una posición">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <!-- 🆕 BOTÓN MOVER A POSICIÓN ESPECÍFICA -->
                                        <button @click="moverAPosicion(row.id, row.descripcion)"
                                            class="p-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition duration-200 transform hover:scale-105"
                                            title="Mover a posición específica">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mensaje sin datos -->
            <div v-else-if="tablaSeleccionada && !cargando" class="bg-white rounded-xl shadow-md p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                    </path>
                </svg>
                <p class="mt-2 text-sm text-gray-500">No hay registros en esta tabla</p>
                <!-- Botón agregar incluso sin datos -->
                <button @click="abrirModalAgregar"
                    class="mt-4 inline-flex items-center space-x-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Agregar primer registro</span>
                </button>
            </div>

            <!-- Mensaje inicial -->
            <div v-else-if="!tablaSeleccionada" class="bg-white rounded-xl shadow-md p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
                    </path>
                </svg>
                <p class="mt-2 text-sm text-gray-500">Selecciona una tabla para comenzar</p>
            </div>
        </div>

        <!-- ═══════════════ MODAL AGREGAR ═══════════════ -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrarModal"></div>

            <!-- Modal Card -->
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="sticky top-0 bg-emerald-600 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
                    <div>
                        <h3 class="text-lg font-bold flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Agregar Registro</span>
                        </h3>
                        <p class="text-emerald-100 text-sm mt-0.5">Selecciona mes/año y la posición donde insertar</p>
                    </div>
                    <button @click="cerrarModal" class="p-1 hover:bg-emerald-700 rounded-lg transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5">

                    <!-- Mes / Año -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">📅 Mes</label>
                            <select v-model="formAgregar.mes"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                                <option v-for="m in meses" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">📅 Año</label>
                            <select v-model="formAgregar.anio"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                                <option v-for="y in aniosDisponibles" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Info registros del mes -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                        <p class="text-sm text-blue-800">
                            <strong>{{ registrosMesAnio.length }}</strong> registro(s) encontrados en 
                            <strong>{{ meses.find(m => m.value === formAgregar.mes)?.label }} {{ formAgregar.anio }}</strong>
                        </p>
                    </div>

                    <!-- Posición -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">📍 Posición donde insertar</label>
                        <select v-model="formAgregar.posicion"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                            <option v-for="op in opcionesPosicion" :key="op.value" :value="op.value">{{ op.label }}</option>
                        </select>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Fecha exacta -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">📆 Fecha exacta</label>
                        <input type="date" v-model="formAgregar.fecha"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                            :placeholder="fechaDefault">
                        <p class="text-xs text-gray-400 mt-1">Dejar vacío para usar {{ fechaDefault }}</p>
                    </div>

                    <!-- N° Acta -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">📝 N° Acta</label>
                        <input type="text" v-model="formAgregar.n_acta"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                            placeholder="Ej: C-045">
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">📄 Descripción</label>
                        <input type="text" v-model="formAgregar.descripcion"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                            placeholder="Descripción del movimiento">
                    </div>

                    <!-- Presupuestario / Actividad -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">💰 Presupuestario</label>
                            <input type="text" v-model="formAgregar.presupuestario"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                                placeholder="----------">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">🎯 Actividad</label>
                            <input type="text" v-model="formAgregar.actividad"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                                placeholder="----------">
                        </div>
                    </div>

                    <!-- Ingresos / Egresos -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">📈 Ingresos</label>
                            <input type="number" step="0.01" v-model="formAgregar.ingresos"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                                placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">📉 Egresos</label>
                            <input type="number" step="0.01" v-model="formAgregar.egresos"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                                placeholder="0.00">
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex items-center justify-end space-x-3 border-t border-gray-200">
                    <button @click="cerrarModal"
                        class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition duration-200 font-medium">
                        Cancelar
                    </button>
                    <button @click="enviarAgregar" :disabled="guardando"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-400 text-white rounded-lg transition duration-200 font-medium flex items-center space-x-2">
                        <svg v-if="guardando" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ guardando ? 'Guardando...' : 'Agregar Registro' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>