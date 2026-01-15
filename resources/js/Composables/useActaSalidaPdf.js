// resources/js/Composables/useActasSalidas.js
import Swal from 'sweetalert2';

export function useActasSalidas() {
    
    const actaPdf_escapeHtml = (unsafe) => {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return String(unsafe)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const actaPdf2_generateActaHTML = ({ nActa, nombre, lugar, distrito, fecha, items, proyectoNombre, logoData = null }) => {
        const rows = (items || []).map((it) => {
            const productoLabel = actaPdf_escapeHtml(it.producto_label || it.producto || it.producto_code || '—');
            const um = actaPdf_escapeHtml(it.um || '—');
            const qty = actaPdf_escapeHtml(Number(it.cantidad || 0));
            return `<tr>
          <td style="padding:6px;vertical-align:top">${productoLabel}</td>
          <td class="unit-col" style="padding:6px;text-align:center;vertical-align:top">${um}</td>
          <td class="qty-col" style="padding:6px;text-align:center;vertical-align:top">${qty}</td>
        </tr>`;
        }).join('') || `<tr><td colspan="3" style="padding:8px;text-align:center;color:#666">No hay items</td></tr>`;

        const css = `
        html,body{margin:0;padding:0;background:#f2f2f2}
        body{display:flex;justify-content:center;padding:10mm 0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif}
        .paper{width:210mm;min-height:297mm;background:#fff;padding:18mm;box-shadow:0 6px 18px rgba(0,0,0,0.08);border-radius:4px;border:1px solid #e2e6ef;box-sizing:border-box;color:#222}
        header{display:flex;justify-content:space-between;margin-bottom:6px;align-items:flex-start}
        .left-meta{display:flex;align-items:flex-start;gap:12px}
        .logo {width: 60px;height: 60px;border-radius: 6px;flex-shrink: 0;overflow: hidden;}
        .org-data{font-size:12px;color:#333}
        .org-title{font-weight:bold;font-size:14px;margin-bottom:4px}
        .right-meta{text-align:right;color:#555;font-size:12px}
        h1{text-align:center;color:#1e4ea8;margin:6px 0 12px 0;font-size:18px;letter-spacing:0.4px}
        .intro{font-size:12px;color:#333;line-height:1.45;margin-bottom:8px}
        .checkboxes{margin:8px 0 12px 0;font-size:12px;color:#2b2b2b}
        .doc-table{width:100%;border-collapse:collapse;margin-top:6px;margin-bottom:10px;font-size:12px;table-layout:fixed;line-height:1.1}
        .doc-table th,.doc-table td{border:1px solid #333;padding:6px 8px;vertical-align:top;text-align:left;word-break:break-word;overflow-wrap:break-word}
        .doc-table th{background:#e6eefc;text-align:left;font-weight:600}
        .unit-col,.qty-col{text-align:center;width:80px}
        .sign-row{display:flex;gap:18px;justify-content:space-between;margin-top:18px;flex-wrap:wrap;align-items:flex-start}
        .sign-box{flex:1 1 44%;max-width:44%;min-width:200px;background:transparent;padding-top:36px;padding-bottom:6px;padding-left:8px;padding-right:8px;box-sizing:border-box;position:relative}
        .sign-line{width:60%;margin:0 auto 6px auto;border-top:1.25px solid #000;height:0;box-sizing:border-box}
        .sign-box .sign-meta { text-align:center !important; margin:0 0 6px 0; font-size:0.75rem; line-height:1.05; padding:0; font-weight:700; letter-spacing:0.6px; }
        .field-row{display:flex;align-items:center;gap:12px;margin:8px 0}
        .field-label{width:36%;min-width:100px;font-weight:600;font-size:0.86rem;text-align:left;color:#333}
        .field-value{flex:1;min-height:20px;border-bottom:1px solid #000;box-sizing:border-box;padding:4px 6px;white-space:nowrap;overflow:hidden}
        .smallnote{font-size:11px;color:#666;margin-top:6px}
        tbody tr td{page-break-inside:avoid}
        @media print{body{padding:0}.paper{box-shadow:none;border:none}@page{size:A4;margin:10mm}.doc-table{font-size:10px}.doc-table th,.doc-table td{padding:4px 6px}}
      `;

        const introText = `En la localidad de <strong>${actaPdf_escapeHtml(lugar)}</strong>, distrito de <strong>${actaPdf_escapeHtml(distrito)}</strong>, siendo las <strong>.............</strong> horas del día <strong>06 de octubre de 2025</strong>, se procede a la entrega y recepción de materiales/implementos al Sr (a) <strong>${actaPdf_escapeHtml(nombre)}</strong>, los cuales se detallan en la siguiente relación, en el marco de las actividades del proyecto antes mencionado.`;

        const checksHtml = `
        <div style="font-family: Arial, sans-serif; font-size:13px; line-height:1.5; margin-top:10px; max-width:700px;">
          <p>Los bienes entregados serán utilizados únicamente para los fines y actividades del proyecto, bajo responsabilidad del receptor, quien se compromete a hacer un uso adecuado y responsable de los mismos.</p>
          <p>Asimismo, el material podrá ser empleado en acciones de promotoría, asesoría, capacitación, actividades comunitarias y para actividades operativas del personal, según corresponda.</p>
        </div>
        `;

        const Htmlmarca = `
        <div style="font-family: Arial, sans-serif; font-size:13px; margin-top:12px; max-width:700px;">
          <p>Marque con una "X" la opción correspondiente:</p>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div>La entrega - recepción es a título personal/familiar.</div>
            <div style="width:18px; height:18px; border:2px solid #2a4d9b; text-align:center; line-height:14px; display:flex; align-items:center; justify-content:center;">&nbsp;</div>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div>La entrega - recepción es para uso de varias familias y/o comunidad.</div>
            <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div>La entrega - recepción es para la realización de trabajos en promotoría / asesoría / capacitación.</div>
            <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>La entrega - recepción es para actividades operativas del personal.</div>
            <div style="width:18px; height:18px; border:2px solid #2a4d9b; display:flex; align-items:center; justify-content:center;"></div>
          </div>
        </div>
      `;

        return `<!doctype html>
        <html lang="es">
        <head>
          <meta charset="utf-8" />
          <title>Acta de Entrega - Recepción - ${actaPdf_escapeHtml(nActa || '')}</title>
          <style>${css}</style>
        </head>
        <body>
          <div class="paper">
            <header>
              <div class="left-meta">
                <div class="logo">
                  <img src="/images/logo.png" alt="logo">
                </div>
                <div class="org-data">
                  <div class="org-title">Islas de Paz Perú</div>
                  <div style="font-size:12px;color:#444">RUC: <strong>20600630769</strong></div>
                  <div style="font-size:12px;color:#444">Organización no Gubernamental</div>
                  <div style="font-size:12px;color:#444">Dirección Jr. Faustino Sánchez Carrión N° 117, Amarilis - Huánuco</div>
                </div>
              </div>
              <div class="right-meta">
                <div><strong>Número:</strong> ${actaPdf_escapeHtml(nActa || '')}</div>
                <div><strong>Proyecto:</strong> ${actaPdf_escapeHtml(proyectoNombre || '')}</div>
              </div>
            </header>
            <h1>ACTA DE ENTREGA - RECEPCIÓN</h1>
            <div class="intro">${introText}</div>
            ${checksHtml}
            ${Htmlmarca}
            <table class="doc-table">
              <thead>
                <tr>
                  <th>DETALLE DE LA ENTREGA-RECEPCIÓN</th>
                  <th class="unit-col">UNIDAD DE MEDIDA</th>
                  <th class="qty-col">CANTIDAD</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
            <div class="smallnote">En señal de conformidad con la entrega y recepción de los bienes antes descritos, firman los presentes:</div>
            <div class="sign-row" style="margin-top: 80px;">
              <div class="sign-box" id="sign-entrego-box">
                <div class="sign-line"></div>
                <div class="sign-meta"><strong>ENTREGÓ CONFORME</strong></div>
                <div class="field-row"><div class="field-label">NOMBRES:</div><div class="field-value"></div></div>
                <div class="field-row"><div class="field-label">APELLIDOS:</div><div class="field-value"></div></div>
                <div class="field-row"><div class="field-label">DNI:</div><div class="field-value"></div></div>
              </div>
              <div class="sign-box" id="sign-recibio-box">
                <div class="sign-line"></div>
                <div class="sign-meta"><strong>RECIBIÓ CONFORME</strong></div>
                <div class="field-row"><div class="field-label">NOMBRES:</div><div class="field-value"></div></div>
                <div class="field-row"><div class="field-label">APELLIDOS:</div><div class="field-value"></div></div>
                <div class="field-row"><div class="field-label">DNI:</div><div class="field-value"></div></div>
              </div>
            </div>
          </div>
        </body>
        </html>`;
    };

    const actaPdf2_downloadPdfFile = async (form, draft, codigoGenerado, props) => {
        // validaciones
        if (!form.nombre || !form.lugar || !form.distrito) {
            Swal.fire('Falta información', 'Completa nombre, lugar y distrito.', 'warning');
            return;
        }
        if (!draft.value.length) {
            Swal.fire('Borrador vacío', 'Agrega items antes de generar el acta.', 'warning');
            return;
        }

        const nActaValue = codigoGenerado.value || 'acta';
        const html = actaPdf2_generateActaHTML({
            nActa: nActaValue,
            nombre: form.nombre,
            lugar: form.lugar,
            distrito: form.distrito,
            fecha: form.fecha,
            items: draft.value,
            proyectoNombre: props.proyecto?.nombre || ''
        });

        let container = null;
        
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Generando PDF...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // convertir mm->px a 96dpi
            const mmToPx = (mm, dpi = 96) => Math.round(mm * (dpi / 25.4));
            const a4WidthPx = mmToPx(210, 96);
            
            container = document.createElement('div');
            container.style.position = 'fixed';
            container.style.left = '-10000px';
            container.style.top = '0';
            container.style.width = `${a4WidthPx}px`;
            container.style.boxSizing = 'border-box';
            container.style.zIndex = '-9999'; // 👈 AÑADIR ESTO
            container.innerHTML = html;
            document.body.appendChild(container);

            const elementToPdf = container.querySelector('.paper') || container;
            elementToPdf.style.margin = '0 auto';
            elementToPdf.style.boxSizing = 'border-box';
            elementToPdf.style.width = `${a4WidthPx}px`;

            // esperar recursos
            await (async function waitResources(root, timeout = 10000) {
                const imgs = Array.from(root.querySelectorAll('img'));
                const imgPromises = imgs.map(img => new Promise(res => {
                    if (!img.src) return res();
                    if (img.complete && img.naturalWidth !== 0) return res();
                    const done = () => { img.removeEventListener('load', done); img.removeEventListener('error', done); res(); };
                    img.addEventListener('load', done);
                    img.addEventListener('error', done);
                    setTimeout(done, 5000);
                }));
                const fontPromise = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
                await Promise.race([Promise.all([...imgPromises, fontPromise]), new Promise(r => setTimeout(r, timeout))]);
                await new Promise(r => setTimeout(r, 300)); // 👈 Aumentar tiempo de espera
            })(container);

            // cargar html2pdf si no está cargado
            if (!window.html2pdf) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
                    script.onload = () => {
                        setTimeout(() => {
                            if (window.html2pdf) {
                                resolve();
                            } else {
                                reject(new Error('html2pdf no se inicializó correctamente'));
                            }
                        }, 500);
                    };
                    script.onerror = () => reject(new Error('Error cargando html2pdf'));
                    document.head.appendChild(script);
                });
            }

            const scale = 2; // 👈 REDUCIR la escala para mejor rendimiento
            const filename = `${String(nActaValue).replace(/[\\\/:*?"<>|]/g, '_')}.pdf`;
            const opt = {
                margin: 8,
                filename,
                image: { type: 'jpeg', quality: 0.95 }, // 👈 Reducir calidad ligeramente
                html2canvas: {
                    scale,
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    width: a4WidthPx,
                    windowWidth: a4WidthPx,
                    dpi: 192 // 👈 Reducir DPI
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    compress: true // 👈 Añadir compresión
                },
                pagebreak: { mode: ['css', 'legacy'] }
            };

            // Cerrar el loading de SweetAlert2 antes de generar
            Swal.close();

            await new Promise((resolve, reject) => {
                try {
                    window.html2pdf()
                        .set(opt)
                        .from(elementToPdf)
                        .toPdf()
                        .get('pdf')
                        .then((pdf) => {
                            window.html2pdf()
                                .set(opt)
                                .from(elementToPdf)
                                .save(filename, () => {
                                    resolve();
                                });
                        })
                        .catch(err => reject(err));
                } catch (err) {
                    reject(err);
                }
            });

            // Éxito
            Swal.fire({
                icon: 'success',
                title: 'PDF Generado',
                text: `El acta ${nActaValue} se ha generado correctamente`,
                timer: 2000,
                showConfirmButton: false
            });

        } catch (err) {
            console.error('Error generando PDF:', err);
            
            // Cerrar cualquier loading
            Swal.close();
            
            Swal.fire({
                icon: 'error',
                title: 'Error al generar PDF',
                text: 'No se pudo generar el PDF. Intentando abrir vista imprimible...',
                confirmButtonText: 'Aceptar'
            });

            // fallback a vista imprimible
            try {
                const w = window.open('', '_blank', 'noopener,noreferrer,width=800,height=600');
                if (w) {
                    w.document.open();
                    w.document.write(html);
                    w.document.close();
                    w.focus();
                    setTimeout(() => {
                        try { 
                            w.print(); 
                        } catch (printErr) {
                            console.error('Error al imprimir:', printErr);
                        }
                    }, 1000);
                }
            } catch (windowErr) {
                console.error('Error abriendo ventana:', windowErr);
                Swal.fire('Error', 'No se pudo abrir la vista imprimible.', 'error');
            }
            
        } finally {
            // 👈 LIMPIAR SIEMPRE el container
            if (container && container.parentNode) {
                try {
                    document.body.removeChild(container);
                } catch (e) {
                    console.warn('No se pudo eliminar el container:', e);
                }
            }
        }
    };

    return {
        actaPdf2_downloadPdfFile
    };
}