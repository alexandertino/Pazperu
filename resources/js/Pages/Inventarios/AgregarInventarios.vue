<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const user = usePage().props.auth.user;

const props = defineProps({
    proyecto: Object,
    categorias: { type: Array, default: () => [] },
    UnidadMedida: { type: Array, default: () => [] },
    solicitantes: { type: Array, default: () => [] },
});

const form = useForm({
    proyecto_id: props.proyecto.id,
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
    solicitado_por: '',
    comentario: '',
    am_table: '',
    am_row_id: '',
    am_from_desc: '',
    am_from_fecha: ''
});


const categorias = ref([]);
const unidades = ref([]);
const solicitantes = ref([]);

const searchCategoria = ref("");
const mostrarCategorias = ref(false);

const searchUnidades = ref("");
const mostrarUnidades = ref(false);

const searchSolicitantes = ref("");
const mostrarSolicitantes = ref(false);

const filtradas = computed(() =>
    categorias.value.filter(c =>
        c.nombre.toLowerCase().includes(searchCategoria.value.toLowerCase())
    )
);

const filtradasUni = computed(() =>
    unidades.value.filter(c =>
        c.nombre.toLowerCase().includes(searchUnidades.value.toLowerCase())
    )
);

const filtradasSol = computed(() =>
    solicitantes.value.filter(c =>
        c.nombre.toLowerCase().includes(searchSolicitantes.value.toLowerCase())
    )
);

const seleccionarCategoria = (nombre) => {
    searchCategoria.value = nombre;
    form.categoria = nombre;
    mostrarCategorias.value = false;
};

const seleccionarUnidades = (nombre) => {
    searchUnidades.value = nombre;
    form.unidad_medida = nombre;
    mostrarUnidades.value = false;
};

const seleccionarSolicitantes = (nombre) => {
    searchSolicitantes.value = nombre;
    form.solicitado_por = nombre;
    mostrarSolicitantes.value = false;
};

const syncCombosToForm = () => {
    if (searchCategoria.value) form.categoria = searchCategoria.value;
    if (searchUnidades.value) form.unidad_medida = searchUnidades.value;
    if (searchSolicitantes.value) form.solicitado_por = searchSolicitantes.value;
};

onMounted(async () => {
    // -----------------------
    // Helper: parsear fecha y devolver YYYY-MM-DD o null
    // -----------------------
    const parseDateToYMD = (raw) => {
        if (!raw) return null;
        raw = decodeURIComponent(String(raw)).trim();

        const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (isoMatch) {
            const [ , y, m, d ] = isoMatch;
            const dt = new Date(`${y}-${m}-${d}T00:00:00`);
            if (!Number.isNaN(dt.getTime())) return `${y}-${m}-${d}`;
        }

        const ymdSlash = raw.match(/^(\d{4})\/(\d{2})\/(\d{2})$/);
        if (ymdSlash) {
            const [ , y, m, d ] = ymdSlash;
            const dt = new Date(`${y}-${m}-${d}T00:00:00`);
            if (!Number.isNaN(dt.getTime())) return `${y}-${m}-${d}`;
        }

        const dmy = raw.match(/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/);
        if (dmy) {
            const [ , dd, mm, yyyy ] = dmy;
            const dt = new Date(`${yyyy}-${mm}-${dd}T00:00:00`);
            if (!Number.isNaN(dt.getTime())) return `${yyyy}-${mm}-${dd}`;
        }

        const maybeDate = new Date(raw);
        if (!Number.isNaN(maybeDate.getTime())) {
            const y = maybeDate.getFullYear();
            const m = String(maybeDate.getMonth() + 1).padStart(2, '0');
            const d = String(maybeDate.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        return null;
    };

    // -----------------------
    // Leer query params (AM)
    // -----------------------
    try {
        const params = new URLSearchParams(window.location.search);
        const am_table = params.get('am_table');
        const am_row_id = params.get('am_row_id');
        const am_desc = params.get('descripcion') || params.get('am_from_desc');
        const am_fecha_raw = params.get('fecha') || params.get('am_from_fecha') || params.get('am_fecha');
        const am_num_raw = params.get('numero');

        if (am_table) {
            form.am_table = am_table;
            form.am_row_id = am_row_id || '';
            if (am_desc) {
                form.descripcion = form.descripcion || decodeURIComponent(am_desc);
                form.am_from_desc = am_desc;
            }

            Swal.fire({
                title: 'Procede vincular con Acta',
                html: `Se detectó una acta: <b>${am_table}</b> (id: <b>${am_row_id || ''}</b>). Al guardar, se creará la vinculación.`,
                icon: 'info',
                confirmButtonText: 'Entendido'
            });
        }

        if (am_num_raw) {
            const matches = (String(am_num_raw).match(/\d+/g) || []);
            let digits = matches.length ? matches[matches.length - 1] : '';
            if (!digits) {
                form.numero = '';
            } else {
                digits = digits.slice(-3);
                form.numero = String(digits).padStart(3, '0');
            }
        }

        const parsedFecha = parseDateToYMD(am_fecha_raw);
        if (parsedFecha) {
            if (!form.fecha || String(form.fecha).trim() === '') {
                form.fecha = parsedFecha;
                form.am_from_fecha = am_fecha_raw;
            }
        }
    } catch (err) {
        console.warn('No se pudieron leer params AM:', err);
    }

    // año por defecto
    if (!form.anio || String(form.anio).trim() === '') {
        form.anio = String(new Date().getFullYear());
    }

    // -----------------------
    // Cargar listas (preferir props, si no => intentar endpoint)
    // -----------------------
    const assignListSafely = (src) => {
        if (!Array.isArray(src)) return [];
        return src.map(item => ({ id: item.id ?? null, nombre: item.nombre ?? String(item) ?? '' }));
    };

    // Usar props.categorias, props.UnidadMedida y props.solicitantes (si vienen)
    if (Array.isArray(props.categorias) || Array.isArray(props.UnidadMedida) || Array.isArray(props.solicitantes)) {
        categorias.value = assignListSafely(props.categorias || []);
        unidades.value = assignListSafely(props.UnidadMedida || []); // <-- aquí usamos UnidadMedida
        solicitantes.value = assignListSafely(props.solicitantes || []);
    } else {
        // Intentar cargar vía endpoint
        try {
            const res = await axios.get(`/proyectos/${props.proyecto.id}/inventario/meta`);
            categorias.value = assignListSafely(res.data.categorias || []);
            unidades.value = assignListSafely(res.data.UnidadMedida || []); // <-- y aquí también
            solicitantes.value = assignListSafely(res.data.solicitantes || []);
        } catch (err) {
            console.warn('No se pudieron cargar listas meta (categoria/unidad/solicitantes):', err);
            categorias.value = categorias.value || [];
            unidades.value = unidades.value || [];
            solicitantes.value = solicitantes.value || [];
        }
    }

    // seleccionar primer elemento por defecto si no hay valor en el form
    const firstNonEmpty = (arr) => (Array.isArray(arr) && arr.length ? arr.find(x => String(x.nombre || '').trim() !== '') : null);

});

const mostrarComentario = ref(false);

const volverATabla = () => {
    window.location.href = `/proyectos/${props.proyecto.id}/inventario-salidas`;
};

const anioRef = ref(null);
const numeroRef = ref(null);
const codigoFinalRef = ref(null);

const campoMeta = {
    anio: { ref: anioRef, maxlength: 4, next: 'numero', formKey: 'anio' },
    numero: { ref: numeroRef, maxlength: 3, next: 'codigo_final', prev: 'anio', formKey: 'numero' },
    codigo_final: { ref: codigoFinalRef, maxlength: 3, prev: 'numero', formKey: 'codigo_final' }
};

const focusCampo = async (name) => {
    const meta = campoMeta[name];
    await nextTick();
    if (meta && meta.ref && meta.ref.value && typeof meta.ref.value.focus === 'function') {
        meta.ref.value.focus();
        const el = meta.ref.value;
        const len = String(el.value || '').length;
        el.setSelectionRange(len, len);
    }
};

const codigoValidado = computed(() => {
    const a = String(form.anio || '').replace(/\D/g, '');
    const n = String(form.numero || '').replace(/\D/g, '');
    const c = String(form.codigo_final || '').replace(/\D/g, '');
    return a.length === 4 && n.length === 3 && c.length === 3;
});

const codigoInputsRef = () => {
    const arr = [anioRef.value, numeroRef.value, codigoFinalRef.value].filter(Boolean);
    return arr;
};

const focusNext = (event) => {
    event.preventDefault();

    const all = Array.from(document.querySelectorAll('.focusable'))
        .filter(el => !el.disabled && el.offsetParent !== null);

    const active = event.target;
    if (!active) return;

    if (!codigoValidado.value) {
        const codeInputs = codigoInputsRef();
        const idx = codeInputs.indexOf(active);
        if (idx === -1) {
            return;
        }
        const next = codeInputs[idx + 1] || codeInputs[0];
        if (next) {
            next.focus();
            if (typeof next.select === 'function') next.select();
        }
        return;
    }

    const idx = all.indexOf(active);
    if (idx === -1) return;

    const next = all[idx + 1] || all[0];
    if (next) {
        next.focus();
        if (typeof next.select === 'function') next.select();
    }
};

const findNearest = (active, list, direction) => {
    if (!active) return null;
    const aRect = active.getBoundingClientRect();
    const aCenter = { x: aRect.left + aRect.width / 2, y: aRect.top + aRect.height / 2 };

    let best = null;
    let bestScore = Infinity;

    list.forEach(el => {
        if (!el || el === active) return;
        const r = el.getBoundingClientRect();
        const c = { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        const dx = c.x - aCenter.x;
        const dy = c.y - aCenter.y;

        if (direction === 'down' && dy <= 0) return;
        if (direction === 'up' && dy >= 0) return;
        if (direction === 'right' && dx <= 0) return;
        if (direction === 'left' && dx >= 0) return;

        const primary = Math.abs(direction === 'left' || direction === 'right' ? dx : dy);
        const perpendicular = Math.abs(direction === 'left' || direction === 'right' ? dy : dx);
        const score = primary * 1000 + perpendicular;

        if (score < bestScore) {
            bestScore = score;
            best = el;
        }
    });

    return best;
};

const navHandler = (e) => {
    const key = e.key;
    if (!['ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Enter'].includes(key)) return;

    const active = document.activeElement;
    if (!active || !active.classList || !active.classList.contains('focusable')) return;

    const isStepper = (el) => {
        if (!el) return false;
        const tag = el.tagName && el.tagName.toLowerCase();
        const type = el.type && el.type.toLowerCase();
        return (tag === 'input' && type === 'number') || (el.classList && el.classList.contains('stepper'));
    };

    if ((key === 'ArrowUp' || key === 'ArrowDown') && isStepper(active)) {
        return;
    }

    const list = window.__focusables || [];
    if (!list || list.length === 0) return;

    const codeInputs = codigoInputsRef();
    const activeIsCodigoInput = codeInputs.includes(active);

    if (!codigoValidado.value && !activeIsCodigoInput) {
        e.preventDefault();
        const idx = list.indexOf(active);
        if (idx === -1) return;
        if (key === 'ArrowRight' || key === 'ArrowDown') {
            if (idx < list.length - 1) list[idx + 1].focus();
        } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
            if (idx > 0) list[idx - 1].focus();
        }
        return;
    }

    if (key === 'ArrowDown') {
        e.preventDefault();
        const nxt = findNearest(active, list, 'down');
        if (nxt) nxt.focus();
        return;
    }
    if (key === 'ArrowUp') {
        e.preventDefault();
        const prev = findNearest(active, list, 'up');
        if (prev) prev.focus();
        return;
    }
    if (key === 'ArrowRight') {
        e.preventDefault();
        const right = findNearest(active, list, 'right') || list[list.indexOf(active) + 1];
        if (right) right.focus();
        return;
    }
    if (key === 'ArrowLeft') {
        e.preventDefault();
        const left = findNearest(active, list, 'left') || list[list.indexOf(active) - 1];
        if (left) left.focus();
        return;
    }
};

const actualizarFocusableList = () => {
    window.__focusables = Array.from(document.querySelectorAll('.focusable'));
};

const onComboFocusOut = (event, tipo) => {
    setTimeout(() => {
        const active = document.activeElement;
        const wrapper = event.target.closest('.relative');
        const inside = wrapper && wrapper.contains(active);

        if (!inside) {
            if (tipo === 'categoria') mostrarCategorias.value = false;
            if (tipo === 'unidades') mostrarUnidades.value = false;
            if (tipo === 'solicitantes') mostrarSolicitantes.value = false;
            syncCombosToForm();
        }
    }, 0);
};

onMounted(() => {
    actualizarFocusableList();
    const obs = new MutationObserver(actualizarFocusableList);
    obs.observe(document.body, { childList: true, subtree: true });
    window.__focusObserver = obs;
    document.addEventListener('keydown', navHandler, false);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', navHandler, false);
    if (window.__focusObserver) {
        window.__focusObserver.disconnect();
        delete window.__focusObserver;
    }
    delete window.__focusables;
});

const handleKey = (event, campo) => {
    const key = event.key;
    const meta = campoMeta[campo];
    if (!meta) return;

    // campos de código donde aplicamos comportamiento "rodillo"
    const isCodigoField = ['anio', 'numero', 'codigo_final'].includes(campo);

    // --- 1) Dígitos en campos de código: "rodillo" (append then slice) ---
    if (isCodigoField && /^[0-9]$/.test(key)) {
        event.preventDefault();
        event.stopPropagation();
        const max = meta.maxlength;
        const cur = String(form[meta.formKey] || '').padStart(max, '0');
        form[meta.formKey] = (cur + key).slice(-max); // ej: '0000' + '2' => '0002'
        return;
    }

    // --- 2) Backspace en campos de código: operación inversa (shift right, prepend 0) ---
    if (isCodigoField && key === 'Backspace') {
        event.preventDefault();
        event.stopPropagation();
        const max = meta.maxlength;
        const cur = String(form[meta.formKey] || '').padStart(max, '0');
        // insertar un '0' a la izquierda y recortar a la longitud
        form[meta.formKey] = ('0' + cur).slice(0, max); // ej: '2025' -> '0202'
        return;
    }

    // --- 3) Delete en campos de código: limpiar a ceros ---
    if (isCodigoField && key === 'Delete') {
        event.preventDefault();
        event.stopPropagation();
        const max = meta.maxlength;
        form[meta.formKey] = ''.padStart(max, '0'); // '0000'
        return;
    }

    // --- 4) Navegación derecha/izquierda entre campos de código (comportamiento previo) ---
    if (key === 'ArrowRight') {
        event.preventDefault();
        event.stopPropagation();
        if (meta.next) focusCampo(meta.next);
        return;
    }
    if (key === 'ArrowLeft') {
        event.preventDefault();
        event.stopPropagation();
        if (meta.prev) focusCampo(meta.prev);
        return;
    }

    // --- 5) Incremento/decremento con flechas arriba/abajo (sigue manteniendo la funcionalidad original) ---
    if (key === 'ArrowUp' || key === 'ArrowDown') {
        // si el campo no es numérico para incremento, no hacemos nada aquí
        event.preventDefault();
        event.stopPropagation();
        const max = meta.maxlength;
        const valueRaw = String(form[meta.formKey] || '0').replace(/\D/g, '');
        const cur = parseInt(valueRaw || '0', 10);
        let nuevo = cur + (key === 'ArrowUp' ? 1 : -1);
        if (nuevo < 0) nuevo = 0;
        const limit = Math.pow(10, max) - 1;
        if (nuevo > limit) nuevo = limit;
        form[meta.formKey] = String(nuevo).padStart(max, '0');
        return;
    }

    // permitir Backspace, Delete, Tab, Enter (si no lo consumimos arriba)
    const allowed = ['Backspace', 'Delete', 'Tab', 'Enter'];
    if (allowed.includes(key)) return;

    // si no es dígito, prevenir (evita caracteres)
    if (!/^[0-9]$/.test(key)) {
        event.preventDefault();
    }
};

const onInputAutoNext = (event, campo) => {
    const meta = campoMeta[campo];
    if (!meta) return;

    const val = String(event.target.value || '').replace(/\D/g, '');
    const max = meta.maxlength;
    const truncated = val.slice(0, max);

    form[meta.formKey] = truncated.padStart(max, '0').slice(-max);

    if (truncated.length >= max && meta.next) {
        focusCampo(meta.next);
    }
};

const codigoCompleto = computed(() => {
    const anio = String(form.anio || '').padStart(4, '0');
    const numero = String(form.numero || '').padStart(3, '0');
    const codigoFinal = String(form.codigo_final || '').padStart(3, '0');

    if (!anio.trim() && !numero.trim() && !codigoFinal.trim()) return '';
    return `IDPP/${anio}_${numero}_C${codigoFinal}`;
});

const validarCampos = () => {
    const entradasNum = Number(form.entradas);
    const precioNum = Number(form.precio);

    if (!form.anio || !form.numero || !form.codigo_final || !form.fecha ||
        !form.descripcion || !form.categoria || !form.unidad_medida ||
        !form.solicitado_por || Number.isNaN(entradasNum) || entradasNum <= 0 ||
        Number.isNaN(precioNum) || precioNum < 0) {
        Swal.fire('⚠️ Campos incompletos', 'Por favor, complete todos los campos correctamente antes de guardar.', 'warning');
        return false;
    }
    return true;
};

const existsIn = (list, value) => list.some(x => x.nombre === value);

const validarExistencias = () => {
    if (!existsIn(categorias.value, form.categoria)) {
        Swal.fire('⚠️ Categoría no válida', 'La categoría ingresada no está registrada. Selecciona una categoría existente.', 'warning');
        return false;
    }
    if (!existsIn(unidades.value, form.unidad_medida)) {
        Swal.fire('⚠️ Unidad no válida', 'La unidad de medida ingresada no está registrada. Selecciona una unidad existente.', 'warning');
        return false;
    }
    if (!existsIn(solicitantes.value, form.solicitado_por)) {
        Swal.fire('⚠️ Solicitante no válido', 'El solicitante ingresado no está registrado. Selecciona un solicitante existente.', 'warning');
        return false;
    }
    return true;
};

const crearPayloadPlano = () => JSON.parse(JSON.stringify(form));

const guardarNormal = () => {
    if (!user || user.role !== 'admin') {
        Swal.fire('⛔ Acceso denegado', 'No tienes permisos para guardar en el inventario.', 'error');
        return;
    }

    if (!form.anio || String(form.anio).trim() === '') {
        form.anio = String(new Date().getFullYear());
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
        form.stock = Number(form.entradas);
        form.salidas = 0;

        const payload = crearPayloadPlano();

        axios.post(`/proyectos/${props.proyecto.id}/inventarios`, payload, {
            headers: { 'Accept': 'application/json' }
        })
            .then(() => {
                Swal.fire('✅ Guardado', 'Inventario creado correctamente', 'success')
                    .then(() => window.location.reload());
            })
            .catch(error => {
                console.error("❌ Error en guardarNormal:", error);
                if (error.response && error.response.data && error.response.data.message) {
                    Swal.fire('⚠️ No guardado', error.response.data.message, 'warning');
                } else {
                    Swal.fire('❌ Error', 'Ocurrió un error inesperado al guardar.', 'error');
                }
            });
    });
};

const guardarMantener = () => {
    if (!user || user.role !== 'admin') {
        Swal.fire('⛔ Acceso denegado', 'No tienes permisos para guardar en el inventario.', 'error');
        return;
    }

    if (!form.anio || String(form.anio).trim() === '') {
        form.anio = String(new Date().getFullYear());
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
            const codigoFinalActual = parseInt(form.codigo_final || '0', 10);
            const solicitadoPorActual = form.solicitado_por;

            form.codigo = codigoCompleto.value;
            form.stock = Number(form.entradas);
            form.salidas = 0;

            const payload = crearPayloadPlano();

            axios.post(`/proyectos/${props.proyecto.id}/inventarios`, payload, {
                headers: { 'Accept': 'application/json' }
            })
                .then(() => {
                    Swal.fire('✅ Guardado', 'Inventario creado correctamente (manteniendo datos)', 'success');

                    form.descripcion = '';
                    form.categoria = '';
                    form.unidad_medida = '';
                    form.entradas = '';
                    form.precio = '';
                    form.fecha = fechaActual;
                    form.anio = anioActual;
                    form.numero = numeroActual;
                    form.solicitado_por = solicitadoPorActual;
                    form.codigo_final = String(codigoFinalActual + 1).padStart(3, '0');
                })
                .catch(error => {
                    console.error("❌ Error en guardarMantener:", error);
                    if (error.response && error.response.data && error.response.data.message) {
                        Swal.fire('⚠️ Error de validación', error.response.data.message, 'warning');
                    } else {
                        Swal.fire('❌ Error', 'Ocurrió un error inesperado al guardar.', 'error');
                    }
                });

        } catch (err) {
            console.error("❌ Error en la función guardarMantener:", err);
        }
    });
};

const submit = () => {
    guardarNormal();
};

const handleComboKey = (event, tipo) => {
    const key = event.key;

    const mostrar = tipo === 'categoria' ? mostrarCategorias.value
        : tipo === 'unidades' ? mostrarUnidades.value
            : mostrarSolicitantes.value;

    const lista = tipo === 'categoria' ? filtradas.value
        : tipo === 'unidades' ? filtradasUni.value
            : filtradasSol.value;

    if ((key === 'Enter' || key === 'Tab') && mostrar && lista.length > 0) {
        event.preventDefault();
        event.stopPropagation();

        const primero = lista[0];
        if (!primero) return;

        if (tipo === 'categoria') seleccionarCategoria(primero.nombre);
        else if (tipo === 'unidades') seleccionarUnidades(primero.nombre);
        else seleccionarSolicitantes(primero.nombre);

        nextTick(() => focusNext(event));
        return;
    }

    if (key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        focusNext(event);
        return;
    }

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
                        <span class="text-gray-500">IDPP/</span>

                        <!-- Año -->
                        <input ref="anioRef" v-model="form.anio" type="text" maxlength="4" inputmode="numeric"
                            pattern="\d*"
                            class="dark:bg-gray-700 dark:text-white w-20 text-center border rounded focusable"
                            @keydown="handleKey($event, 'anio')" @input="onInputAutoNext($event, 'anio')"
                            @keydown.enter.prevent="focusCampo('numero')" placeholder="0000" tabindex="1"
                            aria-label="Año (código)" required />

                        <span class="text-gray-500">_</span>

                        <!-- Número -->
                        <input ref="numeroRef" v-model="form.numero" type="text" maxlength="3" inputmode="numeric"
                            pattern="\d*"
                            class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded focusable"
                            @keydown="handleKey($event, 'numero')" @input="onInputAutoNext($event, 'numero')"
                            @keydown.enter.prevent="focusCampo('codigo_final')" placeholder="000" tabindex="2"
                            aria-label="Número (código)" required />

                        <span class="text-gray-500">_C</span>

                        <!-- Código final -->
                        <input ref="codigoFinalRef" v-model="form.codigo_final" type="text" maxlength="3"
                            inputmode="numeric" pattern="\d*"
                            class="dark:bg-gray-700 dark:text-white w-16 text-center border rounded focusable"
                            @keydown="handleKey($event, 'codigo_final')"
                            @input="onInputAutoNext($event, 'codigo_final')" @keydown.enter.prevent="focusNext"
                            placeholder="000" tabindex="3" aria-label="Código final" required />
                    </div>


                    <p class="text-sm text-gray-500 mt-1">
                        Código generado: <strong>{{ codigoCompleto }}</strong>
                    </p>
                </div>

                <!-- Fecha -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Fecha</label>
                    <input v-model="form.fecha" type="date"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"
                        @keydown.enter.prevent="focusNext" required />
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Descripción</label>
                    <input v-model="form.descripcion" type="text"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"
                        @keydown.enter.prevent="focusNext" required />
                </div>

                <!-- Categoria -->
                <div class="relative">
                    <label class="block font-bold mb-1 dark:text-gray-200">Categoría</label>

                    <!-- Input -->
                    <input v-model="searchCategoria" type="text"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"
                        @focus="mostrarCategorias = true" @input="mostrarCategorias = true"
                        @focusout="onComboFocusOut($event, 'categoria')" @keydown="handleComboKey($event, 'categoria')"
                        required />

                    <!-- Lista desplegable -->
                    <ul v-if="mostrarCategorias && filtradas.length > 0"
                        class="absolute z-10 dark:text-white bg-white dark:bg-gray-700 border rounded w-full mt-1 max-h-40 overflow-auto shadow-lg">
                        <li v-for="c in filtradas" :key="c.id" @mousedown.prevent="seleccionarCategoria(c.nombre)"
                            class="p-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ c.nombre }}
                        </li>
                    </ul>
                </div>

                <!-- Unidad de Medida -->
                <div class="relative">
                    <label class="block font-bold mb-1 dark:text-gray-200">Unidad de Medida</label>

                    <!-- Input -->
                    <input v-model="searchUnidades" type="text"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"
                        @focus="mostrarUnidades = true" @input="mostrarUnidades = true"
                        @focusout="onComboFocusOut($event, 'unidades')" @keydown="handleComboKey($event, 'unidades')"
                        required />

                    <!-- Lista desplegable -->
                    <ul v-if="mostrarUnidades && filtradasUni.length > 0"
                        class="absolute z-10 dark:text-white bg-white dark:bg-gray-700 border rounded w-full mt-1 max-h-40 overflow-auto shadow-lg">
                        <li v-for="c in filtradasUni" :key="c.id" @mousedown.prevent="seleccionarUnidades(c.nombre)"
                            class="p-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ c.nombre }}
                        </li>
                    </ul>
                </div>

                <!-- Entradas -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Entradas</label>
                    <input v-model="form.entradas" type="number" min="1" step="1"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable stepper"
                        @keydown.enter.prevent="focusNext" required />
                </div>

                <!-- Precio -->
                <div>
                    <label class="block font-bold mb-1 dark:text-gray-200">Precio (S/) (Unitario)</label>
                    <input v-model="form.precio" type="number" step="0.01" min="0"
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable stepper"
                        @keydown.enter.prevent="focusNext" required />
                </div>

                <!-- Solicitado por -->
                <div class="relative">
                    <label class="block font-bold mb-1 dark:text-gray-200">Solicitado por</label>

                    <!-- Input -->
                    <input v-model="searchSolicitantes" type="text" 
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"
                        @focus="mostrarSolicitantes = true" @input="mostrarSolicitantes = true"
                        @focusout="onComboFocusOut($event, 'solicitantes')"
                        @keydown="handleComboKey($event, 'solicitantes')" required />

                    <!-- Lista desplegable -->
                    <ul v-if="mostrarSolicitantes && filtradasSol.length > 0"
                        class="absolute z-10 dark:text-white bg-white dark:bg-gray-700 border rounded w-full mt-1 max-h-40 overflow-auto shadow-lg">
                        <li v-for="c in filtradasSol" :key="c.id" @mousedown.prevent="seleccionarSolicitantes(c.nombre)"
                            class="p-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ c.nombre }}
                        </li>
                    </ul>
                </div>

                <!-- Comentario (opcional, se muestra con toggle) -->
                <div v-if="mostrarComentario" class="mt-3">
                    <label class="block font-bold mb-1 dark:text-gray-200">Comentario</label>
                    <textarea v-model="form.comentario" rows="3" placeholder="Escribe un comentario..."
                        class="w-full p-2 border rounded dark:bg-gray-700 dark:text-white focusable"></textarea>
                </div>

                <!-- Botones de acción -->
                <div class="flex gap-2">
                    <!-- Guardar normal -->
                    <button type="button" @click="guardarNormal"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 focusable">
                        💾 Guardar
                    </button>

                    <!-- Guardar manteniendo datos -->
                    <button type="button" @click="guardarMantener"
                        class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 focusable">
                        📌 Mantener Datos
                    </button>

                    <!-- Volver -->
                    <button type="button" @click="volverATabla"
                        class="ml-auto bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-700 focusable">
                        ↩ Volver
                    </button>

                    <!-- Mostrar/Ocultar comentario -->
                    <button type="button" @click="mostrarComentario = !mostrarComentario" :class="[
                        'px-4 py-2 rounded transition focusable',
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
