// resources/js/utils/pdfGenerators/salidasPdf.js
import Swal from 'sweetalert2';

// Helper functions
const escapeHtml = (s) => {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
};

const formatearCantidad = (valor) => {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Number(valor);
    if (Number.isNaN(n)) return String(valor);
    if (Number.isInteger(n)) return String(n);
    const f = n.toFixed(2);
    return f.replace(/\.?0+$/, '').replace(/\.(\d)0$/, '.$1');
};

// Generar HTML para PDF
export const generarHtmlSalidas = ({ producto = {}, salidas = [], proyectoNombre = '' }) => {
    const fmtDate = (d) => {
        if (!d) return '';
        const dt = (d instanceof Date) ? d : new Date(d);
        if (isNaN(dt)) return escapeHtml(d);
        return dt.toLocaleString();
    };

    const rowsHtml = salidas.map((r, i) => {
        const cantidad = r.cantidad ?? r.qty ?? r.cant ?? r.cantidad_salida ?? '';
        const cantidadFmt = formatearCantidad(cantidad) || '—';
        const fechaSalida = r.fecha ?? r.fecha_salida ?? r.created_at ?? '';
        const solicitante = r.solicitado_por ?? r.solicitante ?? r.persona ?? r.persona_nombre ?? '';
        const nActa = r.n_acta ?? r.nacta ?? r.acta ?? '';
        const lugar = r.lugar ?? r.site ?? '';
        const distrito = r.distrito ?? r.district ?? '';
        
        return `
    <tr>
      <td style="padding:6px 8px;text-align:right">${i + 1}</td>
      <td style="padding:6px 8px">${escapeHtml(String(nActa || '—'))}</td>
      <td style="padding:6px 8px">${escapeHtml(String(r.nombre ?? solicitante ?? '—'))}</td>
      <td style="padding:6px 8px">${escapeHtml(lugar || '—')}</td>
      <td style="padding:6px 8px">${escapeHtml(distrito || '—')}</td>
      <td style="padding:6px 8px">${escapeHtml(fmtDate(fechaSalida) || '—')}</td>
      <td style="padding:6px 8px;text-align:right">${escapeHtml(cantidadFmt)}</td>
    </tr>`;
    }).join('');

    const nombre = producto.nombre ?? producto.producto_label ?? producto.producto_name ?? '';
    const codigo = producto.codigo ?? producto.code ?? '';
    const descripcion = producto.descripcion ?? producto.desc ?? '';
    const stock = (producto.stock !== undefined && producto.stock !== null) ? producto.stock : '';
    const solicitadoPor = producto.solicitado_por ?? '';
    const fechaGeneracion = fmtDate(new Date());

    return `
  <div class="paper" style="font-family: Arial, Helvetica, sans-serif; box-sizing:border-box; padding:18px; color:#222;">
    <style>
      .header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
      .brand { display:flex; align-items:center; gap:12px; }
      .logo-wrap { width:86px; height:86px; display:flex; align-items:center; justify-content:center; border-radius:8px; overflow:hidden; background:#fff; border:1px solid #eee; }
      .logo-wrap img { max-width:100%; max-height:100%; display:block; }
      .company { font-size:16px; font-weight:700; }
      .meta { font-size:12px; color:#444; }
      .title { font-size:16px; font-weight:700; margin-bottom:6px; }
      .product-info { border:1px solid #e6e6e6; padding:10px; border-radius:6px; margin-bottom:12px; font-size:13px; background: #fafafa; }
      table { width:100%; border-collapse:collapse; font-size:12px; }
      th, td { border:1px solid #e6e6e6; padding:6px 8px; }
      th { background:#f4f4f4; text-align:left; font-weight:700; }
      .small { font-size:11px; color:#666; }
      .right { text-align:right; }
      @media print { .paper { padding: 12mm; } }
    </style>

    <div class="header">
      <div class="brand">
        <div class="logo-wrap">
            <img src="/images/logo.png" alt="Logo" />
        </div>
        <div>
          <div class="company">Islas De Paz Peru</div>
          <div class="meta">Proyecto: ${escapeHtml(proyectoNombre || '')}</div>
        </div>
      </div>
      <div class="small right">Generado: ${escapeHtml(fechaGeneracion)}</div>
    </div>

    <div class="title">Salidas del producto — ${escapeHtml(nombre || codigo)}</div>

    <div class="product-info">
      <div><strong>Producto:</strong> ${escapeHtml(nombre)}</div>
      <div><strong>Código:</strong> ${escapeHtml(codigo)}</div>
      <div><strong>Descripción:</strong> ${escapeHtml(descripcion)}</div>
      <div><strong>Stock Actual:</strong> ${escapeHtml(String(stock))}</div>
      <div><strong>Solicitado por:</strong> ${escapeHtml(solicitadoPor)}</div>
    </div>

    <div style="margin-top:6px; margin-bottom:6px; font-weight:600">Detalle de salidas</div>

    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th style="width:90px">N° Acta</th>
          <th style="min-width:140px">Nombre</th>
          <th style="width:120px">Lugar</th>
          <th style="width:120px">Distrito</th>
          <th style="width:140px">Fecha</th>
          <th style="width:90px">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        ${rowsHtml}
      </tbody>
    </table>

    <div style="margin-top:18px; font-size:12px; color:#333; display:flex; justify-content:space-between; align-items:center;">
      <div><strong>Total filas:</strong> ${salidas.length}</div>
    </div>
  </div>
  `;
};

// Descargar PDF
export const descargarPdfSalidas = async ({ producto = {}, salidas = [], proyectoNombre = '' }) => {
    if (!producto || (!producto.codigo && !producto.nombre)) {
        Swal.fire('Falta información', 'El producto debe tener nombre o código para generar el PDF.', 'warning');
        return;
    }

    const nFile = `${String(producto.nombre ?? producto.codigo ?? 'salidas')}.pdf`.replace(/[\\\/:*?"<>|]/g, '_');
    const html = generarHtmlSalidas({ producto, salidas, proyectoNombre });

    // Lógica de generación de PDF (simplificada)
    const container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.left = '-10000px';
    container.innerHTML = html;
    document.body.appendChild(container);

    try {
        // Cargar html2pdf si no existe
        if (!window.html2pdf) {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        await window.html2pdf()
            .set({
                margin: 8,
                filename: nFile,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            })
            .from(container)
            .save();

        Swal.fire('Éxito', 'PDF generado correctamente', 'success');
    } catch (err) {
        console.error('Error generando PDF:', err);
        Swal.fire('Error', 'No se pudo generar el PDF', 'error');
    } finally {
        document.body.removeChild(container);
    }
};