// resources/js/utils/formatters.js
export const formatFecha = (f) => {
    if (!f) return '—';
    try {
        const d = new Date(f);
        if (isNaN(d)) return f;
        return d.toLocaleDateString();
    } catch (e) {
        return f;
    }
};

export const formatearCantidad = (valor) => {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Number(valor);
    if (Number.isNaN(n)) return String(valor);
    if (Number.isInteger(n)) return String(n);
    const f = n.toFixed(2);
    return f.replace(/\.?0+$/, '').replace(/\.(\d)0$/, '.$1');
};

export const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
    }).format(amount);
};