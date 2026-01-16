<template>
    <AuthenticatedLayout>
        <Head title="Editar Movimiento" />

        <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold mb-6 dark:text-gray-200">
                Editar Movimiento - {{ cuenta.nombre }}
            </h1>

            <!-- INFO DEL MOVIMIENTO ACTUAL -->
            <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200">
                        Movimiento #{{ movimiento.numero }}
                    </h3>
                    
                    <!-- INDICADOR DE ESTADO ACTUAL -->
                    <div class="flex items-center gap-2">
                        <div v-if="movimiento.es_pendiente && !movimiento.pendiente_saldado" 
                             class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 text-sm font-bold">
                            ⏳ PENDIENTE
                        </div>
                        <div v-else-if="movimiento.es_pendiente && movimiento.pendiente_saldado"
                             class="px-3 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 text-sm">
                            ✅ SALDADO
                        </div>
                        
                        <!-- Si este movimiento salda otro pendiente -->
                        <div v-if="movimiento.movimiento_pendiente_id" 
                             class="px-3 py-1 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 text-sm">
                            → Salda #{{ obtenerNumeroPendiente(movimiento.movimiento_pendiente_id) }}
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Fecha original:</span>
                        <span class="ml-2">{{ formatFechaDisplay(movimiento.fecha_operacion) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Monto:</span>
                        <span class="font-bold ml-2" :class="movimiento.deudor > 0 ? 'text-green-600' : 'text-red-600'">
                            S/ {{ formatoDinero(Math.abs(movimiento.deudor - movimiento.acreedor)) }}
                        </span>
                    </div>
                    <div v-if="movimiento.movimiento_saldante_id" class="col-span-2">
                        <span class="text-green-600 dark:text-green-400 text-sm">
                            ✅ Saldado por movimiento #{{ obtenerNumeroSaldante(movimiento.movimiento_saldante_id) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- LISTA DE PENDIENTES ACTIVOS (si no es este movimiento) -->
            <div v-if="!movimiento.es_pendiente && pendientesActivos.length > 0" 
                 class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700">
                <div class="flex items-center gap-3 mb-3">
                    <div class="text-amber-600 dark:text-amber-400 text-xl">⏳</div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-gray-200">Pagar un pendiente existente</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Puedes asignar este movimiento para saldar un pendiente activo
                        </p>
                    </div>
                </div>
                
                <div class="space-y-2 max-h-40 overflow-y-auto">
                    <div v-for="pendiente in pendientesActivos" :key="pendiente.id"
                         :class="[
                             'p-3 rounded border cursor-pointer transition',
                             form.movimiento_pendiente_id == pendiente.id
                                 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-700'
                                 : 'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700'
                         ]"
                         @click="seleccionarPendiente(pendiente)">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-4 h-4 rounded-full bg-amber-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">P</span>
                                    </div>
                                    <span class="font-medium">#{{ pendiente.numero }}</span>
                                    <span class="text-xs text-gray-500">{{ formatFechaDisplay(pendiente.fecha_operacion) }}</span>
                                </div>
                                <p class="text-sm">{{ pendiente.descripcion }}</p>
                            </div>
                            <div class="text-right">
                                <div class="font-bold" :class="pendiente.deudor > 0 ? 'text-green-600' : 'text-red-600'">
                                    S/ {{ formatoDinero(Math.abs(pendiente.deudor - pendiente.acreedor)) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="guardar" class="space-y-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <!-- CHECKBOX PARA MARCAR COMO PENDIENTE -->
                <div class="flex items-center">
                    <input type="checkbox" id="es_pendiente" v-model="form.es_pendiente" 
                           :disabled="!!form.movimiento_pendiente_id || movimiento.movimiento_saldante_id"
                           class="h-5 w-5 text-amber-500 rounded border-gray-300 focus:ring-amber-500">
                    <label for="es_pendiente" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Marcar como pendiente
                    </label>
                    <div v-if="movimiento.movimiento_saldante_id" class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                        (No disponible - ya fue saldado)
                    </div>
                    <div v-else-if="form.movimiento_pendiente_id" class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                        (No disponible cuando paga otro pendiente)
                    </div>
                </div>

                <!-- FECHA -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Fecha
                    </label>
                    <input
                        v-model="form.fecha_operacion"
                        type="date"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                        required
                    />
                </div>

                <!-- MEDIO DE PAGO -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Medio de pago
                    </label>
                    <input
                        v-model="form.medio_pago"
                        type="text"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                        placeholder="Ej: Transferencia, Efectivo"
                    />
                </div>

                <!-- DESCRIPCIÓN -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Descripción
                    </label>
                    <textarea
                        v-model="form.descripcion"
                        rows="3"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                        required
                    ></textarea>
                </div>

                <!-- DEUDOR / ACREEDOR -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Débito (Ingreso)
                        </label>
                        <input
                            v-model="form.deudor"
                            @input="onDeudorInput"
                            type="number"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Crédito (Egreso)
                        </label>
                        <input
                            v-model="form.acreedor"
                            @input="onAcreedorInput"
                            type="number"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                </div>

                <!-- SUBCUENTA -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Subcuenta (opcional)
                    </label>
                    <select
                        v-model="form.subcuenta_id"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Sin subcuenta</option>
                        <option v-for="s in cuenta.subcuentas" :key="s.id" :value="s.id">
                            {{ s.nombre }}
                        </option>
                    </select>
                </div>

                <!-- INFO SI SE SELECCIONÓ PENDIENTE -->
                <div v-if="form.movimiento_pendiente_id && pendienteSeleccionado" 
                     class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="text-green-600 dark:text-green-400">💰</div>
                        <div>
                            <h4 class="font-bold text-green-800 dark:text-green-300">
                                Este movimiento saldará el pendiente
                            </h4>
                            <p class="text-sm text-green-700 dark:text-green-400">
                                #{{ pendienteSeleccionado.numero }} - {{ pendienteSeleccionado.descripcion }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- BOTONES -->
                <div class="flex justify-between items-center pt-6 border-t dark:border-gray-700">
                    <div class="flex gap-2">
                        <Link
                            :href="`/cuentas/${cuenta.id}`"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancelar
                        </Link>
                        
                        <button type="button" @click="resetForm"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            Restablecer
                        </button>
                    </div>

                    <button
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else-if="form.movimiento_pendiente_id">💸 Actualizar y Pagar Pendiente</span>
                        <span v-else>💾 Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { ref, computed, onMounted } from "vue";

// Props desde Laravel
const props = defineProps({
    movimiento: Object,
    cuenta: Object,
    pendientes_activos: {
        type: Array,
        default: () => []
    }
});

// Estados
const pendientesActivos = ref(props.pendientes_activos);

// --- Formateo correcto YYYY-MM-DD ---
const formatFecha = (f) => {
    if (!f) return "";
    return new Date(f).toISOString().split("T")[0];
};

// --- Valores iniciales del formulario ---
const form = useForm({
    fecha_operacion: formatFecha(props.movimiento.fecha_operacion),
    medio_pago: props.movimiento.medio_pago,
    descripcion: props.movimiento.descripcion,
    deudor: props.movimiento.deudor,
    acreedor: props.movimiento.acreedor,
    subcuenta_id: props.movimiento.subcuenta_id,
    es_pendiente: props.movimiento.es_pendiente || false,
    movimiento_pendiente_id: props.movimiento.movimiento_pendiente_id || null,
});

// Cargar pendientes activos al montar (excluyendo este movimiento si es pendiente)
onMounted(() => {
    // Filtrar pendientes activos (excluir este movimiento si es pendiente)
    if (props.movimiento.es_pendiente && !props.movimiento.pendiente_saldado) {
        pendientesActivos.value = pendientesActivos.value.filter(p => p.id !== props.movimiento.id);
    }
});

// Formato dinero
function formatoDinero(valor) {
    if (valor === null || valor === undefined) return "0.00";
    const numero = typeof valor === 'string' ? parseFloat(valor.replace(/[^\d.-]/g, '')) : Number(valor);
    return numero.toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// Formato fecha (DD/MM/YYYY)
function formatFechaDisplay(fechaString) {
    if (!fechaString) return "";
    const fecha = new Date(fechaString);
    if (isNaN(fecha.getTime())) return "";
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const año = fecha.getFullYear();
    return `${dia}/${mes}/${año}`;
}

// Obtener número de movimiento pendiente
function obtenerNumeroPendiente(id) {
    // Buscar en los pendientes activos primero
    const pendiente = pendientesActivos.value.find(p => p.id === id);
    if (pendiente) return pendiente.numero;
    
    // Si no está en activos, buscar en props
    return props.movimiento.movimiento_pendiente?.numero || '?';
}

// Obtener número de movimiento saldante
function obtenerNumeroSaldante(id) {
    return props.movimiento.movimiento_saldante?.numero || '?';
}

// Pendiente seleccionado
const pendienteSeleccionado = computed(() => {
    if (!form.movimiento_pendiente_id) return null;
    return pendientesActivos.value.find(p => p.id == form.movimiento_pendiente_id);
});

// Seleccionar pendiente
function seleccionarPendiente(pendiente) {
    if (form.movimiento_pendiente_id === pendiente.id) {
        // Deseleccionar
        form.movimiento_pendiente_id = null;
    } else {
        // Seleccionar nuevo pendiente
        form.movimiento_pendiente_id = pendiente.id;
        form.es_pendiente = false; // No puede ser pendiente si paga otro
        
        // Sugerir descripción si está vacía
        if (!form.descripcion.trim() || form.descripcion === props.movimiento.descripcion) {
            form.descripcion = `Pago de pendiente #${pendiente.numero} - ${pendiente.descripcion}`;
        }
    }
}

// --- Regla: si se escribe Deudor → limpiar Acreedor ---
function onDeudorInput() {
    if (Number(form.deudor) > 0) form.acreedor = "";
}

// --- Regla: si se escribe Acreedor → limpiar Deudor ---
function onAcreedorInput() {
    if (Number(form.acreedor) > 0) form.deudor = "";
}

// Restablecer formulario
function resetForm() {
    Swal.fire({
        title: "¿Restablecer cambios?",
        text: "Se perderán todos los cambios realizados.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, restablecer",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (result.isConfirmed) {
            form.fecha_operacion = formatFecha(props.movimiento.fecha_operacion);
            form.medio_pago = props.movimiento.medio_pago;
            form.descripcion = props.movimiento.descripcion;
            form.deudor = props.movimiento.deudor;
            form.acreedor = props.movimiento.acreedor;
            form.subcuenta_id = props.movimiento.subcuenta_id;
            form.es_pendiente = props.movimiento.es_pendiente || false;
            form.movimiento_pendiente_id = props.movimiento.movimiento_pendiente_id || null;
        }
    });
}

// Validación mejorada
async function validarAntesDeEnviar() {
    if (!form.fecha_operacion) {
        Swal.fire("Fecha requerida", "Debes seleccionar una fecha.", "warning");
        return false;
    }

    const d = Number(form.deudor) || 0;
    const a = Number(form.acreedor) || 0;

    if (d <= 0 && a <= 0) {
        Swal.fire("Monto requerido", "Debes ingresar un monto en Deudor o Acreedor.", "warning");
        return false;
    }

    if (d > 0 && a > 0) {
        Swal.fire("Datos incorrectos", "Solo se puede ingresar un valor: deudor o acreedor, no ambos.", "error");
        return false;
    }

    // Validación específica para pagar pendientes
    if (form.movimiento_pendiente_id) {
        const pendiente = pendienteSeleccionado.value;
        if (!pendiente) {
            Swal.fire("Pendiente no encontrado", "El pendiente seleccionado ya no existe.", "error");
            return false;
        }
        
        // Si este movimiento ya estaba asignado a otro pendiente diferente
        if (props.movimiento.movimiento_pendiente_id && 
            props.movimiento.movimiento_pendiente_id !== form.movimiento_pendiente_id) {
            const confirm = await Swal.fire({
                title: "Cambiar pendiente asociado",
                html: `
                    <div class="text-left">
                        <p>Este movimiento actualmente salda el pendiente #${obtenerNumeroPendiente(props.movimiento.movimiento_pendiente_id)}.</p>
                        <p class="text-sm text-gray-600 mt-2">¿Deseas cambiar para que salde el pendiente #${pendiente.numero}?</p>
                    </div>
                `,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, cambiar",
                cancelButtonText: "Mantener actual",
            });
            
            if (!confirm.isConfirmed) return false;
        }
        
        // Si el movimiento actual es un pendiente y se quiere asignar a otro pendiente
        if (props.movimiento.es_pendiente && !props.movimiento.pendiente_saldado) {
            Swal.fire("Operación inválida", "Un movimiento pendiente no puede saldar otro pendiente.", "error");
            return false;
        }
    }

    return true;
}

// --- Guardar con confirmación mejorada ---
const guardar = async () => {
    const isValid = await validarAntesDeEnviar();
    if (!isValid) return;

    // Preparar mensaje de confirmación
    let confirmTitle = "¿Guardar cambios?";
    let confirmHtml = `
        <div class="text-left">
            <p><strong>Movimiento #${props.movimiento.numero}</strong></p>
            <p><strong>Fecha:</strong> ${form.fecha_operacion}</p>
            <p><strong>Descripción:</strong> ${form.descripcion}</p>
            <p><strong>Monto:</strong> ${form.deudor ? 'S/ ' + formatoDinero(form.deudor) + ' (Débito)' : 'S/ ' + formatoDinero(form.acreedor) + ' (Crédito)'}</p>
    `;

    // Información adicional según cambios
    if (form.es_pendiente !== props.movimiento.es_pendiente) {
        if (form.es_pendiente) {
            confirmHtml += `<p class="text-amber-600 font-bold">⚠️ Se marcará como PENDIENTE</p>`;
        } else {
            confirmHtml += `<p class="text-gray-600">✅ Se quitará el estado de pendiente</p>`;
        }
    }

    if (form.movimiento_pendiente_id !== (props.movimiento.movimiento_pendiente_id || null)) {
        if (form.movimiento_pendiente_id) {
            const pendiente = pendienteSeleccionado.value;
            confirmHtml += `<p class="text-green-600 font-bold">💰 Pagará el pendiente #${pendiente.numero}</p>`;
        } else {
            confirmHtml += `<p class="text-gray-600">🔄 Ya no pagará ningún pendiente</p>`;
        }
    }

    confirmHtml += `</div>`;

    Swal.fire({
        title: confirmTitle,
        html: confirmHtml,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#2563eb",
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            form.put(`/movimientos/${props.movimiento.id}`, {
                onSuccess: () => {
                    let successMessage = "Movimiento actualizado correctamente.";
                    
                    if (form.es_pendiente && !props.movimiento.es_pendiente) {
                        successMessage = "✅ Movimiento actualizado y marcado como PENDIENTE";
                    } else if (!form.es_pendiente && props.movimiento.es_pendiente) {
                        successMessage = "✅ Pendiente removido correctamente";
                    } else if (form.movimiento_pendiente_id) {
                        successMessage = "💰 Movimiento actualizado para pagar pendiente";
                    }

                    Swal.fire({
                        title: "¡Éxito!",
                        text: successMessage,
                        icon: "success",
                        timer: 1500,
                    });

                    // Volver a la cuenta
                    router.visit(`/cuentas/${props.cuenta.id}`);
                },
                onError: (errors) => {
                    let errorMessage = "Revisa los datos ingresados.";
                    
                    if (errors.message) {
                        errorMessage = errors.message;
                    } else if (errors.error) {
                        errorMessage = errors.error;
                    }
                    
                    Swal.fire({
                        title: "Error",
                        text: errorMessage,
                        icon: "error",
                    });
                },
            });
        }
    });
};
</script>