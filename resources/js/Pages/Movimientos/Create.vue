<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const props = defineProps({
    cuenta: Object,
    ultimo_saldo: { type: [Number, String], default: 0 },
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
});

// Fecha por defecto
form.fecha_operacion = new Date().toISOString().split("T")[0];

function safeNumber(v) {
    if (v === null || v === undefined || v === '') return 0;
    const n = Number(v);
    return isNaN(n) ? 0 : n;
}

const formatoDinero = (valor) => {
    if (valor === null || valor === undefined) return "0.00";
    const numero = typeof valor === 'string' ? parseFloat(valor.replace(/[^\d.-]/g, '')) : Number(valor);
    return numero.toLocaleString("es-PE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// nuevo saldo usa props.ultimo_saldo
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

function validarAntesDeEnviar() {
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

    return true;
}

let enviando = false;

async function submit() {
    if (enviando) return;
    if (!validarAntesDeEnviar()) return;

    const confirm = await Swal.fire({
        title: "¿Registrar movimiento?",
        html: `
            <div class="text-left">
                <p><strong>Fecha:</strong> ${form.fecha_operacion}</p>
                <p><strong>Descripción:</strong> ${form.descripcion}</p>
                <p><strong>Monto:</strong> ${form.deudor ? 'S/ ' + formatoDinero(form.deudor) + ' (Débito)' : 'S/ ' + formatoDinero(form.acreedor) + ' (Crédito)'}</p>
                <p><strong>Nuevo saldo:</strong> S/ ${formatoDinero(nuevoSaldo())}</p>
            </div>
        `,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
    });

    if (!confirm.isConfirmed) return;

    enviando = true;
    form.processing = true;

    Swal.fire({
        title: "Guardando...",
        text: "Estamos registrando el movimiento",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        await axios.post("/movimientos", form.data());

        Swal.fire({
            title: "Éxito",
            text: "Movimiento registrado correctamente.",
            icon: "success",
            timer: 1200,
            showConfirmButton: false,
        });

        router.visit(`/cuentas/${form.cuenta_general_id}`);

    } catch (error) {
        console.error(error);
        Swal.fire({
            title: "Error",
            text: "Hubo un problema al guardar.",
            icon: "error",
        });
    } finally {
        enviando = false;
        form.processing = false;
    }
}
</script>

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

            <form @submit.prevent="submit" class="space-y-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
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
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Débito (Ingreso)</label>
                        <input v-model="form.deudor" @input="onDeudorInput" type="number" step="0.01" placeholder="0.00" class="w-full border rounded px-3 py-2" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Crédito (Egreso)</label>
                        <input v-model="form.acreedor" @input="onAcreedorInput" type="number" step="0.01" placeholder="0.00" class="w-full border rounded px-3 py-2" />
                    </div>
                </div>

                <div class="flex justify-between items-center pt-6 border-t">
                    <Link :href="`/cuentas/${cuenta.id}`" class="text-gray-600 hover:underline">← Volver a la cuenta</Link>
                    <button class="bg-blue-600 text-white px-6 py-3 rounded-lg" :disabled="form.processing || enviando">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>💾 Guardar Movimiento</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
