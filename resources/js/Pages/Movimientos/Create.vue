<template>
    <AuthenticatedLayout>
        <Head title="Registrar Movimiento" />

        <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2 dark:text-gray-200">
                    Registrar Movimiento - {{ cuenta.nombre }}
                </h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Saldo Actual</h3>
                        <p class="text-2xl font-bold text-green-600">
                            S/ {{ formatoDinero(ultimo_saldo) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Nuevo Saldo</h3>
                        <p class="text-2xl font-bold" :class="nuevoSaldo() >= 0 ? 'text-green-600' : 'text-red-600'">
                            S/ {{ formatoDinero(nuevoSaldo()) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- BANNER DE PENDIENTES ACTIVOS -->
            <div v-if="pendientesActivos.length > 0" 
                 class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex items-center gap-3 mb-3">
                    <div class="text-amber-600 dark:text-amber-400 text-xl">⏳</div>
                    <div>
                        <h3 class="font-bold text-amber-800 dark:text-amber-300">Pendientes activos disponibles</h3>
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            Tienes {{ pendientesActivos.length }} movimiento(s) pendiente(s)
                        </p>
                    </div>
                </div>
                
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    <div v-for="pendiente in pendientesActivos" :key="pendiente.id"
                         :class="[
                             'p-3 rounded border cursor-pointer transition',
                             form.movimiento_pendiente_id == pendiente.id
                                 ? 'bg-amber-100 dark:bg-amber-900/30 border-amber-300 dark:border-amber-700'
                                 : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                         ]"
                         @click="seleccionarPendiente(pendiente)">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-5 h-5 rounded-full bg-amber-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">P</span>
                                    </div>
                                    <span class="font-medium">#{{ pendiente.numero }}</span>
                                    <span class="text-xs text-gray-500">{{ formatFechaDisplay(pendiente.fecha_operacion) }}</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ pendiente.descripcion }}</p>
                            </div>
                            <div class="text-right">
                                <div class="font-bold" :class="pendiente.deudor > 0 ? 'text-green-600' : 'text-red-600'">
                                    S/ {{ formatoDinero(Math.abs(pendiente.deudor - pendiente.acreedor)) }}
                                </div>
                                <div v-if="form.movimiento_pendiente_id == pendiente.id" 
                                     class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    ✓ Seleccionado para pagar
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="form.movimiento_pendiente_id" class="mt-4 pt-4 border-t border-amber-200 dark:border-amber-800">
                    <button @click="form.movimiento_pendiente_id = null" 
                            class="text-sm text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                        ✕ Deseleccionar pendiente
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <!-- Checkbox para marcar como pendiente -->
                <div class="flex items-center">
                    <input type="checkbox" id="es_pendiente" v-model="form.es_pendiente" 
                           :disabled="!!form.movimiento_pendiente_id"
                           class="h-5 w-5 text-amber-500 rounded border-gray-300 focus:ring-amber-500">
                    <label for="es_pendiente" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Marcar como movimiento pendiente
                    </label>
                    <div v-if="form.movimiento_pendiente_id" class="ml-4 text-xs text-amber-600 dark:text-amber-400">
                        (No disponible cuando se paga un pendiente)
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha</label>
                    <input v-model="form.fecha_operacion" type="date" class="w-full border rounded px-3 py-2" required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subcuenta (opcional)</label>
                    <select v-model="form.subcuenta_id" class="w-full border rounded px-3 py-2">
                        <option value="">Seleccionar subcuenta</option>
                        <option v-for="s in cuenta.subcuentas" :key="s.id" :value="s.id">
                            {{ s.nombre }} 
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Medio de pago</label>
                    <input v-model="form.medio_pago" type="text" placeholder="Ej: 003, Transferencia, Efectivo" class="w-full border rounded px-3 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Descripción</label>
                    <textarea v-model="form.descripcion" rows="3" class="w-full border rounded px-3 py-2" required></textarea>
                    <p v-if="form.movimiento_pendiente_id" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Sugerencia: "Pago de pendiente #{{ form.movimiento_pendiente_id }}" o similar
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Débito (Ingreso)</label>
                        <input v-model="form.deudor" @input="onDeudorInput" type="number" step="0.01" placeholder="0.00" 
                               class="w-full border rounded px-3 py-2" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Crédito (Egreso)</label>
                        <input v-model="form.acreedor" @input="onAcreedorInput" type="number" step="0.01" placeholder="0.00" 
                               class="w-full border rounded px-3 py-2" />
                    </div>
                </div>

                <!-- INFO DEL PENDIENTE SELECCIONADO -->
                <div v-if="form.movimiento_pendiente_id && pendienteSeleccionado" 
                     class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="text-green-600 dark:text-green-400 text-xl">💰</div>
                        <div>
                            <h3 class="font-bold text-green-800 dark:text-green-300">Pagar pendiente seleccionado</h3>
                            <p class="text-sm text-green-700 dark:text-green-400">
                                Este movimiento saldará el pendiente #{{ pendienteSeleccionado.numero }}
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Pendiente:</span>
                            <span class="font-medium ml-2">#{{ pendienteSeleccionado.numero }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Fecha original:</span>
                            <span class="ml-2">{{ formatFechaDisplay(pendienteSeleccionado.fecha_operacion) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Monto pendiente:</span>
                            <span class="font-bold ml-2" :class="pendienteSeleccionado.deudor > 0 ? 'text-green-600' : 'text-red-600'">
                                S/ {{ formatoDinero(Math.abs(pendienteSeleccionado.deudor - pendienteSeleccionado.acreedor)) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Descripción:</span>
                            <span class="ml-2">{{ pendienteSeleccionado.descripcion }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-6 border-t">
                    <Link :href="`/cuentas/${cuenta.id}`" class="text-gray-600 hover:underline">← Volver a la cuenta</Link>
                    
                    <div class="flex gap-3">
                        <button type="button" @click="resetForm" 
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            Limpiar
                        </button>
                        
                        <button class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition" 
                                :disabled="form.processing || enviando">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else-if="form.movimiento_pendiente_id">💸 Pagar Pendiente</span>
                            <span v-else>💾 Guardar Movimiento</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>


<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";
import { ref, computed, onMounted } from "vue";

const props = defineProps({
    cuenta: Object,
    ultimo_saldo: { type: [Number, String], default: 0 },
    pendientes_activos: {
        type: Array,
        default: () => []
    }
});

// --- FORM ---
const form = useForm({
    cuenta_general_id: props.cuenta.id,
    fecha_operacion: "",
    medio_pago: "",
    descripcion: "",
    deudor: "",
    acreedor: "",
    subcuenta_id: "",
    es_pendiente: false,
    movimiento_pendiente_id: null,
});

// Estados
const pendientesActivos = ref(props.pendientes_activos);
const enviando = ref(false);

// Fecha por defecto
form.fecha_operacion = new Date().toISOString().split("T")[0];

// Cargar pendientes activos al montar
onMounted(async () => {
    if (pendientesActivos.value.length === 0) {
        try {
            const response = await axios.get(`/api/cuentas/${props.cuenta.id}/pendientes-activos`);
            pendientesActivos.value = response.data;
        } catch (error) {
            console.error("Error cargando pendientes:", error);
        }
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

// Pendiente seleccionado
const pendienteSeleccionado = computed(() => {
    if (!form.movimiento_pendiente_id) return null;
    return pendientesActivos.value.find(p => p.id == form.movimiento_pendiente_id);
});

// Seleccionar pendiente
function seleccionarPendiente(pendiente) {
    if (form.movimiento_pendiente_id === pendiente.id) {
        form.movimiento_pendiente_id = null;
        form.es_pendiente = false;
    } else {
        form.movimiento_pendiente_id = pendiente.id;
        form.es_pendiente = false; // No puede ser pendiente si paga otro
        
        // Sugerir descripción automática
        if (!form.descripcion.trim()) {
            form.descripcion = `Pago de pendiente #${pendiente.numero} - ${pendiente.descripcion}`;
        }
        
        // Sugerir montos inversos al pendiente
        if (!form.deudor && !form.acreedor) {
            if (pendiente.deudor > 0) {
                form.acreedor = pendiente.deudor; // Si el pendiente era débito, este es crédito
            } else if (pendiente.acreedor > 0) {
                form.deudor = pendiente.acreedor; // Si el pendiente era crédito, este es débito
            }
        }
    }
}

function safeNumber(v) {
    if (v === null || v === undefined || v === '') return 0;
    const n = Number(v);
    return isNaN(n) ? 0 : n;
}

// Nuevo saldo
const nuevoSaldo = () => {
    const saldoActual = safeNumber(props.ultimo_saldo);
    const deudor = safeNumber(form.deudor);
    const acreedor = safeNumber(form.acreedor);
    return saldoActual + deudor - acreedor;
};

function onDeudorInput() {
    if (safeNumber(form.deudor) > 0) form.acreedor = "";
}

function onAcreedorInput() {
    if (safeNumber(form.acreedor) > 0) form.deudor = "";
}

// CORREGIDO: Convertir a función async
async function validarAntesDeEnviar() {
    if (!form.fecha_operacion) {
        Swal.fire("Fecha requerida", "Debes seleccionar una fecha.", "warning");
        return false;
    }

    const d = safeNumber(form.deudor);
    const a = safeNumber(form.acreedor);

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
        
        // Verificar que el monto coincida (no obligatorio pero sugerido)
        const montoPendiente = Math.abs(pendiente.deudor - pendiente.acreedor);
        const montoMovimiento = Math.abs(d - a);
        
        if (montoMovimiento !== montoPendiente) {
            const confirm = await Swal.fire({
                title: "Montos diferentes",
                html: `
                    <div class="text-left">
                        <p>El monto del movimiento (S/ ${formatoDinero(montoMovimiento)}) 
                           no coincide con el monto del pendiente (S/ ${formatoDinero(montoPendiente)}).</p>
                        <p class="text-sm text-gray-600 mt-2">¿Deseas continuar de todos modos?</p>
                    </div>
                `,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Corregir monto",
            });
            
            if (!confirm.isConfirmed) return false;
        }
    }

    return true;
}

// Resetear formulario
function resetForm() {
    Swal.fire({
        title: "¿Limpiar formulario?",
        text: "Se perderán todos los datos ingresados.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, limpiar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (result.isConfirmed) {
            form.reset();
            form.cuenta_general_id = props.cuenta.id;
            form.fecha_operacion = new Date().toISOString().split("T")[0];
            form.es_pendiente = false;
            form.movimiento_pendiente_id = null;
        }
    });
}

// Submit
async function submit() {
    if (enviando.value) return;
    
    const isValid = await validarAntesDeEnviar(); // Ahora puede usar await
    if (!isValid) return;

    // Preparar confirmación según tipo
    let confirmMessage = "";
    let confirmTitle = "";
    
    if (form.movimiento_pendiente_id) {
        const pendiente = pendienteSeleccionado.value;
        confirmTitle = "¿Pagar pendiente?";
        confirmMessage = `
            <div class="text-left">
                <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                    <p class="font-bold text-amber-800 dark:text-amber-300">PENDIENTE #${pendiente.numero}</p>
                    <p class="text-sm">${pendiente.descripcion}</p>
                    <p class="mt-2">Monto: <span class="font-bold">S/ ${formatoDinero(Math.abs(pendiente.deudor - pendiente.acreedor))}</span></p>
                </div>
                <p><strong>Fecha pago:</strong> ${form.fecha_operacion}</p>
                <p><strong>Descripción:</strong> ${form.descripcion}</p>
                <p><strong>Monto pago:</strong> ${form.deudor ? 'S/ ' + formatoDinero(form.deudor) + ' (Débito)' : 'S/ ' + formatoDinero(form.acreedor) + ' (Crédito)'}</p>
                <p><strong>Nuevo saldo:</strong> S/ ${formatoDinero(nuevoSaldo())}</p>
            </div>
        `;
    } else {
        confirmTitle = "¿Registrar movimiento?";
        confirmMessage = `
            <div class="text-left">
                <p><strong>Fecha:</strong> ${form.fecha_operacion}</p>
                <p><strong>Descripción:</strong> ${form.descripcion}</p>
                <p><strong>Monto:</strong> ${form.deudor ? 'S/ ' + formatoDinero(form.deudor) + ' (Débito)' : 'S/ ' + formatoDinero(form.acreedor) + ' (Crédito)'}</p>
                ${form.es_pendiente ? '<p class="text-amber-600 font-bold">⚠️ Este movimiento será marcado como PENDIENTE</p>' : ''}
                <p><strong>Nuevo saldo:</strong> S/ ${formatoDinero(nuevoSaldo())}</p>
            </div>
        `;
    }

    const confirm = await Swal.fire({
        title: confirmTitle,
        html: confirmMessage,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: form.movimiento_pendiente_id ? "Sí, pagar pendiente" : "Sí, guardar",
        cancelButtonText: "Cancelar",
        width: '500px'
    });

    if (!confirm.isConfirmed) return;

    enviando.value = true;
    form.processing = true;

    Swal.fire({
        title: form.movimiento_pendiente_id ? "Procesando pago..." : "Guardando...",
        text: form.movimiento_pendiente_id ? "Estamos registrando el pago del pendiente" : "Estamos registrando el movimiento",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const response = await axios.post("/movimientos", form.data());
        
        let successMessage = "";
        if (form.movimiento_pendiente_id) {
            successMessage = `✅ Pendiente #${pendienteSeleccionado.value.numero} saldado correctamente`;
        } else if (form.es_pendiente) {
            successMessage = `⏳ Movimiento registrado y marcado como PENDIENTE`;
        } else {
            successMessage = "Movimiento registrado correctamente";
        }

        Swal.fire({
            title: "¡Éxito!",
            text: successMessage,
            icon: "success",
            timer: 1500,
            showConfirmButton: false,
        });

        router.visit(`/cuentas/${form.cuenta_general_id}`);

    } catch (error) {
        console.error("Error:", error);
        let errorMessage = "Hubo un problema al guardar.";
        
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            errorMessage = errors.join("<br>");
        }
        
        Swal.fire({
            title: "Error",
            html: errorMessage,
            icon: "error",
        });
    } finally {
        enviando.value = false;
        form.processing = false;
    }
}
</script>