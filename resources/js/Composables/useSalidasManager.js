// resources/js/Composables/useSalidasManager.js
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

export function useSalidasManager(proyectoId) {
    const salidasProducto = ref([]);
    const modalVisible = ref(false);
    const currentCodigo = ref(null);
    const currentProducto = ref({ 
        nombre: null, 
        codigo: null, 
        descripcion: null, 
        stock: null, 
        solicitado_por: null 
    });
    const total = ref(null);
    const cargando = ref(false);

    const verSalidas = async (itemOrCodigo) => {
        let item = null;
        let codigo = null;

        if (!itemOrCodigo) return;
        
        if (typeof itemOrCodigo === 'object') {
            item = itemOrCodigo;
            codigo = item.codigo ?? item.producto_code ?? item.code ?? item.id ?? null;
        } else {
            codigo = itemOrCodigo;
        }

        if (!codigo) {
            Swal.fire('Error', 'No se pudo determinar el código del producto.', 'warning');
            return;
        }

        cargando.value = true;

        try {
            const url = `/proyectos/${proyectoId}/salidas/producto/${encodeURIComponent(codigo)}`;
            const res = await axios.get(url, { headers: { Accept: 'application/json' } });

            const datos = res.data.salidas ?? res.data ?? [];
            salidasProducto.value = Array.isArray(datos) ? datos : [];
            currentCodigo.value = codigo;

            // Actualizar información del producto
            currentProducto.value = {
                nombre: item?.descripcion ?? item?.producto_label ?? item?.producto_name ?? null,
                codigo: codigo,
                descripcion: item?.descripcion ?? item?.desc ?? null,
                stock: item?.stock ?? (item?.inventario ?? null),
                solicitado_por: item?.solicitado_por ?? null
            };

            // Si no teníamos nombre, intentar extraerlo de la primera fila
            if (!currentProducto.value.nombre && salidasProducto.value.length > 0) {
                const first = salidasProducto.value[0];
                currentProducto.value.nombre = first.producto_label ?? first.producto ?? first.producto_name ?? first.solicitante ?? null;
            }

            // Calcular total
            calcularTotalSalidas();

            modalVisible.value = true;

        } catch (err) {
            console.error('Error al obtener salidas:', err);
            manejarErrorSalidas(err, codigo);
        } finally {
            cargando.value = false;
        }
    };

    const calcularTotalSalidas = () => {
        const posibleCantidad = (r) => r.cantidad ?? r.qty ?? r.cant ?? r.cantidad_salida ?? null;
        
        if (salidasProducto.value.length > 0) {
            let suma = 0;
            let tiene = false;
            
            for (const row of salidasProducto.value) {
                const v = posibleCantidad(row);
                const n = Number(v);
                if (!isNaN(n)) { 
                    suma += n; 
                    tiene = true; 
                }
            }
            
            total.value = tiene ? suma : null;
        } else {
            total.value = null;
        }
    };

    const manejarErrorSalidas = (err, codigo) => {
        salidasProducto.value = [];
        currentCodigo.value = codigo;
        modalVisible.value = true;

        let mensaje = "Error al obtener salidas.";
        if (err.response) {
            mensaje += `\nCódigo: ${err.response.status} - ${err.response.statusText}`;
            if (err.response.data?.message) mensaje += `\nDetalle: ${err.response.data.message}`;
        } else if (err.request) {
            mensaje += "\nEl servidor no respondió.";
        } else {
            mensaje += `\n${err.message}`;
        }
        
        Swal.fire('Error', mensaje, 'error');
    };

    const cerrarModal = () => {
        modalVisible.value = false;
        salidasProducto.value = [];
        currentProducto.value = { 
            nombre: null, 
            codigo: null, 
            descripcion: null, 
            stock: null, 
            solicitado_por: null 
        };
        total.value = null;
    };

    return {
        salidasProducto,
        modalVisible,
        currentCodigo,
        currentProducto,
        total,
        cargando,
        verSalidas,
        cerrarModal,
        calcularTotalSalidas
    };
}