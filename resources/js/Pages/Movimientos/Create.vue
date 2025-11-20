<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const props = defineProps({
    cuenta: Object,
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

function safeNumber(v) {
    const n = Number(v);
    return isNaN(n) ? 0 : n;
}

// --- REGLA DE DEUDOR Y ACREEDOR ---
function onDeudorInput() {
    if (safeNumber(form.deudor) > 0) form.acreedor = "";
}
function onAcreedorInput() {
    if (safeNumber(form.acreedor) > 0) form.deudor = "";
}

// --- VALIDACIONES ---
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
        Swal.fire(
            "Datos incorrectos",
            "Solo se puede ingresar un valor: deudor o acreedor, no ambos.",
            "error"
        );
        return false;
    }

    return true;
}

// 🔒 Protección contra doble envío
let enviando = false;

// --- ENVÍO FINAL ---
async function submit() {
    if (enviando) return;

    if (!validarAntesDeEnviar()) return;

    // Preguntar antes de enviar
    const confirm = await Swal.fire({
        title: "¿Registrar movimiento?",
        text: "Revisa que todos los datos estén correctos.",
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
            timer: 1500,
            showConfirmButton: false,
        });

        router.visit(`/cuentas/${form.cuenta_general_id}`);

    } catch (error) {
        Swal.fire({
            title: "Error",
            text: "Hubo un problema al guardar.",
            icon: "error",
        });

        console.error(error);
    } finally {
        enviando = false;
        form.processing = false;
    }
}
</script>


<template>
    <AuthenticatedLayout>

        <Head title="Registrar Movimiento" />
        <div class="max-w-3xl mx-auto py-10">

            <h1 class="text-2xl font-bold mb-2 dark:text-gray-200">
                Registrar Movimiento - {{ cuenta.nombre }}
            </h1>

            <!-- SALDO DE LA CUENTA -->
            <p class="mb-6 text-gray-700 dark:text-gray-300">
                Saldo actual de esta cuenta:
                <strong class="text-green-500">S/ {{ safeNumber(cuenta.saldo).toFixed(2) }}
                </strong>
            </p>

            <form @submit.prevent="submit" class="space-y-6">

                <!-- FECHA -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Fecha</label>
                    <input v-model="form.fecha_operacion" type="date"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" required />
                </div>

                <!-- SUBCUENTA -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Subcuenta (opcional)</label>

                    <select v-model="form.subcuenta_id"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Seleccionar subcuenta</option>

                        <option v-for="s in cuenta.subcuentas" :key="s.id" :value="s.id">
                            {{ s.nombre }} — saldo: S/ {{ safeNumber(s.saldo).toFixed(2) }}

                        </option>
                    </select>
                </div>

                <!-- MEDIO DE PAGO -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Medio de pago</label>
                    <input v-model="form.medio_pago" type="text"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" />
                </div>

                <!-- DESCRIPCIÓN -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Descripción</label>
                    <textarea v-model="form.descripcion"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" required></textarea>
                </div>

                <!-- DEUDOR / ACREEDOR -->
                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="text-gray-700 dark:text-gray-300">Deudor</label>
                        <input v-model="form.deudor" @input="onDeudorInput" type="number" step="0.01"
                            :disabled="Number(form.acreedor) > 0"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" />

                    </div>

                    <div>
                        <label class="text-gray-700 dark:text-gray-300">Acreedor</label>
                        <input v-model="form.acreedor" @input="onAcreedorInput" type="number" step="0.01"
                            :disabled="Number(form.deudor) > 0"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200" />
                    </div>

                </div>

                <!-- BOTONES -->
                <div class="flex justify-between pt-6">
                    <Link href="/cuentas" class="text-gray-600 dark:text-gray-300 hover:underline">
                    Cancelar
                    </Link>

                    <button class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700
           flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="form.processing || enviando">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar</span>
                    </button>

                </div>

            </form>

        </div>

    </AuthenticatedLayout>
</template>
