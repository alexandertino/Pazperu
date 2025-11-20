<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

// Props desde Laravel
const props = defineProps({
    movimiento: Object,
    cuenta: Object,
});

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
});

// --- Regla: si se escribe Deudor → limpiar Acreedor
function onDeudorInput() {
    if (Number(form.deudor) > 0) form.acreedor = "";
}

// --- Regla: si se escribe Acreedor → limpiar Deudor
function onAcreedorInput() {
    if (Number(form.acreedor) > 0) form.deudor = "";
}

// --- Guardar con confirmación ---
const guardar = () => {
    Swal.fire({
        title: "¿Guardar cambios?",
        text: "Se actualizará el movimiento seleccionado.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#2563eb",
    }).then((result) => {
        if (result.isConfirmed) {
            form.put(`/movimientos/${props.movimiento.id}`, {
                onSuccess: () => {
                    Swal.fire({
                        title: "Actualizado",
                        text: "El movimiento se actualizó correctamente.",
                        icon: "success",
                        timer: 1500,
                    });

                    // Volver a la cuenta
                    router.visit(`/cuentas/${props.cuenta.id}`);
                },
                onError: () => {
                    Swal.fire({
                        title: "Error",
                        text: "Revisa los datos ingresados.",
                        icon: "error",
                    });
                },
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Editar Movimiento" />

        <div class="max-w-3xl mx-auto py-10">

            <h1 class="text-2xl font-bold mb-6 dark:text-gray-200">
                Editar Movimiento - {{ cuenta.nombre }}
            </h1>

            <form @submit.prevent="guardar" class="space-y-6">
                
                <!-- FECHA -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Fecha</label>
                    <input
                        v-model="form.fecha_operacion"
                        type="date"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                        required
                    />
                </div>

                <!-- MEDIO DE PAGO -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Medio de pago</label>
                    <input
                        v-model="form.medio_pago"
                        type="text"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                    />
                </div>

                <!-- DESCRIPCIÓN -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Descripción</label>
                    <textarea
                        v-model="form.descripcion"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                    ></textarea>
                </div>

                <!-- DEUDOR / ACREEDOR -->
                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="text-gray-700 dark:text-gray-300">Deudor</label>
                        <input
                            v-model="form.deudor"
                            @input="onDeudorInput"
                            type="number"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>

                    <div>
                        <label class="text-gray-700 dark:text-gray-300">Acreedor</label>
                        <input
                            v-model="form.acreedor"
                            @input="onAcreedorInput"
                            type="number"
                            step="0.01"
                            class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>

                </div>

                <!-- SUBCUENTA -->
                <div>
                    <label class="text-gray-700 dark:text-gray-300">Subcuenta (opcional)</label>

                    <select
                        v-model="form.subcuenta_id"
                        class="w-full border rounded px-3 py-2 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="">Sin subcuenta</option>

                        <option v-for="s in cuenta.subcuentas" :key="s.id" :value="s.id">
                            {{ s.nombre }}
                        </option>
                    </select>
                </div>

                <!-- BOTONES -->
                <div class="flex justify-between pt-6">
                    <Link
                        :href="`/cuentas/${cuenta.id}`"
                        class="text-gray-600 dark:text-gray-300 hover:underline"
                    >
                        Cancelar
                    </Link>

                    <button
                        class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700"
                        :disabled="form.processing"
                    >
                        Guardar Cambios
                    </button>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
