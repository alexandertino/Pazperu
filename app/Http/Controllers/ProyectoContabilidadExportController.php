<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Carbon;

class ProyectoContabilidadExportController extends Controller
{
    /**
     * Exportar contabilidad (Caja, Banco, Easy) por MES/AÑO en un solo archivo con varias hojas.
     *
     * GET /proyectos/{proyecto}/exportar-contabilidad-multiples?mes=9&anio=2025
     */
    public function exportarMesMultiples(Request $request, $proyecto)
    {
        $mes = (int) $request->query('mes', 0);
        $anio = (int) $request->query('anio', 0);

        if ($mes < 1 || $mes > 12 || $anio < 1900) {
            return abort(400, 'Parámetros mes/año inválidos.');
        }

        // Obtener proyecto para construir sufijo
        $proyectoRow = DB::table('proyectos')->where('id', $proyecto)->first();
        if (! $proyectoRow) {
            return abort(404, "Proyecto no encontrado: {$proyecto}");
        }

        // Normalizar sufijo (evita problemas con acentos o caracteres especiales)
        $tableSuffix = (string) Str::of($proyectoRow->nombre)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');

        // posibles tablas dinámicas
        $tables = [
            'caja'  => 'am_caja_proyecto_' . $tableSuffix,
            'banco' => 'am_banco_proyecto_' . $tableSuffix,
            'easy_candidates' => [
                'am_easy_proyecto_' . $tableSuffix,
                'easy_proyecto_' . $tableSuffix,
            ],
        ];

        // Rango del mes y día previo (último día del mes anterior)
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = (clone $desde)->endOfMonth()->endOfDay();
        $prevDay = (clone $desde)->subDay()->endOfDay();

        // Helper: elegir la primera tabla 'easy' que exista
        $getEasyTable = function () use ($tables) {
            foreach ($tables['easy_candidates'] as $t) {
                if (Schema::hasTable($t)) return $t;
            }
            return null;
        };

        // Mapping label => tabla (si existe)
        $mapping = [];
        if (Schema::hasTable($tables['caja']))  $mapping['Caja']  = $tables['caja'];
        if (Schema::hasTable($tables['banco'])) $mapping['Banco'] = $tables['banco'];
        $easyTable = $getEasyTable();
        if ($easyTable) $mapping['Easy'] = $easyTable;

        if (count($mapping) === 0) {
            return abort(404, "No se encontraron tablas contables (caja/banco/easy) para el proyecto '{$proyectoRow->nombre}'.");
        }

        // Helper: devolver la primera columna existente entre candidatas
        $firstColumn = function (string $table, array $candidates) {
            foreach ($candidates as $c) {
                if ($c === null) continue;
                if (Schema::hasColumn($table, $c)) return $c;
            }
            return null;
        };

        // Helper: devuelve el valor de la primera clave existente en $arr entre $candidates
        $pickValue = function (array $arr, array $candidates) {
            foreach ($candidates as $c) {
                if ($c === null) continue;
                if (array_key_exists($c, $arr)) return $arr[$c];
            }
            return null;
        };

        // Inicializar Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        // estilos
        $innerBorders = [
            'borders' => [
                'inside' => [
                    'borderStyle' => Border::BORDER_HAIR,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        $outerBorders = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '000000']],
                'left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '000000']],
                'right' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '000000']],
            ],
        ];

        // Recorremos cada tabla detectada
        foreach ($mapping as $label => $tablaBase) {
            // Determinar columna de fecha: preferir 'fecha', luego 'created_at', luego 'updated_at'
            $colFecha = $firstColumn($tablaBase, ['fecha', 'created_at', 'updated_at']);
            if (! $colFecha) {
                // sin columna de fecha: saltamos esta hoja para no romper la exportación
                continue;
            }

            // Detectar columns para n_acta/descripcion/presupuestario/actividad (candidatos comunes)
            $colNActa = $firstColumn($tablaBase, ['n_acta', 'nacta', 'numero_acta']);
            $colDescripcion = $firstColumn($tablaBase, ['descripcion', 'detalle', 'desc', 'concepto']);
            $colPresupuestario = $firstColumn($tablaBase, ['presupuestario', 'codigo_presupuestario', 'cod_presupuesto', 'codigo_presupuestario']);
            $colActividad = $firstColumn($tablaBase, ['actividad', 'actividad_nombre']);

            // Detectar columnas de ingreso/egreso/saldo según tipo
            if (strtolower($label) === 'easy') {
                $colIngreso = $firstColumn($tablaBase, ['ingreso_moneda_local', 'ingreso', 'credito_moneda_gestion', 'credito']);
                $colEgreso  = $firstColumn($tablaBase, ['gasto_moneda_local', 'gasto', 'debito_moneda_gestion', 'debito']);
                $colSaldo   = $firstColumn($tablaBase, ['saldo', 'balance']);
            } else {
                $colIngreso = $firstColumn($tablaBase, ['ingresos', 'monto_ingreso', 'credito', 'ingreso']);
                $colEgreso  = $firstColumn($tablaBase, ['egresos', 'monto_egreso', 'debito', 'egreso']);
                $colSaldo   = $firstColumn($tablaBase, ['saldo', 'balance']);
            }

            // Construir query segura para registros del mes (ordenados por la columna de fecha detectada)
            $query = DB::table($tablaBase)
                ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                ->orderBy($colFecha, 'asc');
            if (Schema::hasColumn($tablaBase, 'id')) $query = $query->orderBy('id', 'asc');

            $rawRegistros = $query->get();

            // Mapear registros a un array normalizado con claves conocidas (_n_acta, _fecha, _descripcion, _presupuestario, _actividad, _ingresos, _egresos, _saldo)
            $registrosMes = [];
            foreach ($rawRegistros as $r) {
                $arr = (array) $r;

                // usar pickValue para obtener valores seguros (evita undefined index)
                $nacta = $pickValue($arr, array_filter([$colNActa, 'n_acta', 'nacta', 'numero_acta']));
                $fechaVal = $pickValue($arr, array_filter([$colFecha, 'fecha', 'created_at', 'updated_at']));
                $descVal = $pickValue($arr, array_filter([$colDescripcion, 'descripcion', 'detalle', 'desc', 'concepto']));
                $presuVal = $pickValue($arr, array_filter([$colPresupuestario, 'presupuestario', 'codigo_presupuestario', 'cod_presupuesto']));
                $actividadVal = $pickValue($arr, array_filter([$colActividad, 'actividad', 'actividad_nombre']));

                // ingresos/egresos (numéricos) - forzamos 0 si no existen
                $ing = 0.0;
                $eg  = 0.0;
                if ($colIngreso !== null && array_key_exists($colIngreso, $arr)) $ing = is_numeric($arr[$colIngreso]) ? (float)$arr[$colIngreso] : 0.0;
                elseif (array_key_exists('ingresos', $arr)) $ing = is_numeric($arr['ingresos']) ? (float)$arr['ingresos'] : 0.0;
                if ($colEgreso !== null && array_key_exists($colEgreso, $arr)) $eg = is_numeric($arr[$colEgreso]) ? (float)$arr[$colEgreso] : 0.0;
                elseif (array_key_exists('egresos', $arr)) $eg = is_numeric($arr['egresos']) ? (float)$arr['egresos'] : 0.0;

                // saldo (nullable)
                $sd = null;
                if ($colSaldo !== null && array_key_exists($colSaldo, $arr)) {
                    $sd = is_numeric($arr[$colSaldo]) ? (float)$arr[$colSaldo] : null;
                } elseif (array_key_exists('saldo', $arr)) {
                    $sd = is_numeric($arr['saldo']) ? (float)$arr['saldo'] : null;
                }

                $registrosMes[] = [
                    '_raw' => $arr,
                    '_n_acta' => $nacta,
                    '_fecha' => $fechaVal,
                    '_descripcion' => $descVal,
                    '_presupuestario' => $presuVal,
                    '_actividad' => $actividadVal,
                    '_ingresos' => $ing,
                    '_egresos' => $eg,
                    '_saldo' => $sd,
                ];
            }

            // 2) Calcular saldo apertura: primero intentar último registro con saldo <= prevDay, sino fallback suma(ingresos)-suma(egresos) hasta prevDay
            $saldoApertura = 0.0;
            if ($colSaldo && Schema::hasColumn($tablaBase, $colSaldo)) {
                $ultimoQuery = DB::table($tablaBase)
                    ->select($colSaldo, $colFecha)
                    ->whereNotNull($colSaldo)
                    ->where($colFecha, '<=', $prevDay->toDateString())
                    ->orderBy($colFecha, 'desc');
                if (Schema::hasColumn($tablaBase, 'id')) $ultimoQuery = $ultimoQuery->orderBy('id', 'desc');
                $ultimo = $ultimoQuery->first();
                if ($ultimo && isset($ultimo->{$colSaldo})) {
                    $saldoApertura = (float) $ultimo->{$colSaldo};
                } else {
                    // fallback suma
                    $sumIngreso = 0;
                    $sumEgreso = 0;
                    if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                        $r = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                            ->selectRaw('COALESCE(SUM(`'.$colIngreso.'`),0) as s_ing')->first();
                        $sumIngreso = $r->s_ing ?? 0;
                    }
                    if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                        $r2 = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                            ->selectRaw('COALESCE(SUM(`'.$colEgreso.'`),0) as s_eg')->first();
                        $sumEgreso = $r2->s_eg ?? 0;
                    }
                    $saldoApertura = (float)$sumIngreso - (float)$sumEgreso;
                }
            } else {
                // sin columna saldo -> fallback suma ingresos - egresos hasta prevDay
                $sumIngreso = 0;
                $sumEgreso = 0;
                if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                    $r = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                        ->selectRaw('COALESCE(SUM(`'.$colIngreso.'`),0) as s_ing')->first();
                    $sumIngreso = $r->s_ing ?? 0;
                }
                if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                    $r2 = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                        ->selectRaw('COALESCE(SUM(`'.$colEgreso.'`),0) as s_eg')->first();
                    $sumEgreso = $r2->s_eg ?? 0;
                }
                $saldoApertura = (float)$sumIngreso - (float)$sumEgreso;
            }

            // 3) Totales del mes actual (ingresos/egresos)
            $ingresosMes = 0;
            $egresosMes = 0;
            if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                $r = DB::table($tablaBase)
                    ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                    ->selectRaw('COALESCE(SUM(`'.$colIngreso.'`),0) as s_ing')
                    ->first();
                $ingresosMes = $r->s_ing ?? 0;
            }
            if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                $r2 = DB::table($tablaBase)
                    ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                    ->selectRaw('COALESCE(SUM(`'.$colEgreso.'`),0) as s_eg')
                    ->first();
                $egresosMes = $r2->s_eg ?? 0;
            }

            $movimientosMes = (float)$ingresosMes - (float)$egresosMes;
            $saldoCierre = (float)$saldoApertura + (float)$movimientosMes;

            // =============== Crear hoja y volcar datos ===============
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $tituloHoja = substr($label . ' ' . $desde->format('M Y'), 0, 31);
            $sheet->setTitle($tituloHoja);

            // EASY (sin logos, compacto)
            if (strtolower($label) === 'easy') {
                // Encabezado compacto (A1:M1) - título corto
                $sheet->mergeCells('A1:M1');
                $sheet->setCellValue('A1', strtoupper("CONTABILIDAD — " . $desde->format('F Y')));
                $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Una sola línea con RUC y Dirección (fila 2)
                $rucDir = 'RUC: 20600630769' . ($proyectoRow->ruc ?? '') . '  |  Dirección: Dirección: Jr. Faustino Sánchez Carrión N° 117 - Amarilis - Huánuco ' . ($proyectoRow->direccion ?? '');
                $sheet->mergeCells('A2:M2');
                $sheet->setCellValue('A2', $rucDir);
                $sheet->getStyle('A2')->getFont()->setSize(10);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                // Banner azul compacto (fila 4)
                $bannerText = 'INFORME ECONÓMICO - ' . $desde->format('F Y');
                $sheet->mergeCells('A4:M4');
                $sheet->setCellValue('A4', strtoupper($bannerText));
                $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('0D6EAF');

                // Encabezados (fila 6)
                $headers = [
                    'Compte général',            // A
                    'Dépense (PEN)',             // B gasto (PEN)
                    'Recette (PEN)',             // C ingreso (PEN)
                    'Moneda factura',            // D
                    'Débito (EUR)',              // E
                    'Crédito (EUR)',             // F
                    'Moneda gestión',            // G
                    'Num./Descripción',          // H
                    'Código presup.',            // I
                    'Naturaleza',                // J
                    'Contrato',                  // K
                    'Bailleurs',                 // L
                    'Fecha'                      // M
                ];
                $sheet->fromArray($headers, null, 'A6');
                $sheet->getStyle('A6:M6')->getFont()->setBold(true);
                $sheet->getStyle('A6:M6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A6:M6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('9BC2E6');

                // Filas de datos a partir de la fila 7
                $row = 7;
                foreach ($registrosMes as $r) {
                    $arr = $r['_raw'];

                    $cuenta_general = $pickValue($arr, ['cuenta_general', 'codigo_general', 'codigo_cuenta']);
                    $gasto_pen = $pickValue($arr, ['gasto_moneda_local', 'gasto', 'monto_gasto']);
                    $receta_pen = $pickValue($arr, ['ingreso_moneda_local', 'ingreso', 'monto_ingreso']);
                    $moneda_facturacion = $pickValue($arr, ['moneda_facturacion', 'moneda_de_facturacion', 'moneda_factura']);
                    $debito_eur = $pickValue($arr, ['debito_moneda_gestion', 'debito', 'debito_eur']);
                    $credito_eur = $pickValue($arr, ['credito_moneda_gestion', 'credito', 'credito_eur']);
                    $moneda_gestion = $pickValue($arr, ['moneda_gestion', 'moneda_de_gestion']);
                    $numero_descripcion = $pickValue($arr, ['numero_descripcion_pieza', 'numeracion_descripcion', 'numero_descripcion']);
                    $codigo_presupuesto = $pickValue($arr, ['codigo_presupuestario', 'cod_presupuesto', 'presupuestario']);
                    $naturaleza = $pickValue($arr, ['naturaleza_presupuesto', 'naturaleza', 'tipo_presupuesto']);
                    $contrato = $pickValue($arr, ['contrato', 'contract']);
                    $donantes = $pickValue($arr, ['bailleur_fondos', 'donantes', 'bailleur']);
                    $fecha_val = $pickValue($arr, ['fecha', 'fecha_documento', 'created_at']);

                    $sheet->setCellValueExplicit('A'.$row, $cuenta_general ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B'.$row, is_numeric($gasto_pen) ? (float)$gasto_pen : 0);
                    $sheet->setCellValue('C'.$row, is_numeric($receta_pen) ? (float)$receta_pen : 0);
                    $sheet->setCellValueExplicit('D'.$row, $moneda_facturacion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E'.$row, is_numeric($debito_eur) ? (float)$debito_eur : 0);
                    $sheet->setCellValue('F'.$row, is_numeric($credito_eur) ? (float)$credito_eur : 0);
                    $sheet->setCellValueExplicit('G'.$row, $moneda_gestion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('H'.$row, $numero_descripcion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('I'.$row, $codigo_presupuesto ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('J'.$row, $naturaleza ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('K'.$row, $contrato ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('L'.$row, $donantes ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('M'.$row, $fecha_val ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $row++;
                }

                // Resumen (Saldo apertura / Totales) - fila debajo
                $lastDataRow = max(7, $row - 1);
                $summaryRowStart = $lastDataRow + 2;

                // estilos y auto-size para A..M
                $sheet->getStyle("A6:M{$lastDataRow}")->applyFromArray($innerBorders);
                $sheet->getStyle("A6:M{$lastDataRow}")->applyFromArray($outerBorders);
                foreach (range('A', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Formatos numéricos
                $sheet->getStyle("B7:C{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("E7:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("B{$summaryRowStart}:C".($summaryRowStart+1))->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("M{$summaryRowStart}:M".($summaryRowStart+1))->getNumberFormat()->setFormatCode('#,##0.00');

            } else {
                // ---------------------------
                // formato tipo "libro" para Caja/Banco (sin columnas Easy/Contable)
                // Para Banco añadimos columna extra "Acción"
                // ---------------------------

                $isBanco = (strtolower($label) === 'banco');

                // Título (fila 1) - nombre del proyecto + tipo
                $mergeCols = $isBanco ? 'A1:J1' : 'A1:K1';
                $sheet->mergeCells($mergeCols);
                $sheet->setCellValue('A1', strtoupper("Proyecto: {$proyectoRow->nombre} — {$label} — " . $desde->format('F Y')));
                $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Una línea compacta RUC | Dirección (fila 2)
                $rucDir = 'RUC: 20600630769' . ($proyectoRow->ruc ?? '') . '  |  Dirección: Dirección: Jr. Faustino Sánchez Carrión N° 117 - Amarilis - Huánuco  ' . ($proyectoRow->direccion ?? '');
                $sheet->mergeCells($isBanco ? 'A2:J2' : 'A2:K2');
                $sheet->setCellValue('A2', $rucDir);
                $sheet->getStyle('A2')->getFont()->setSize(10);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                // Banner amarillo compacto (fila 4)
                $tituloLibro = "LIBRO DEL DIARIO - " . $desde->format('F Y');
                $sheet->mergeCells($isBanco ? 'A4:J4' : 'A4:K4');
                $sheet->setCellValue('A4', $tituloLibro);
                $sheet->getStyle('A4')->getFont()->setBold(true);
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFEB3B');

                // Encabezados agrupados (fila 6)
                // Si es Banco usamos columnas A..J con Acción en H y Saldo en I; si es Caja usamos A..I con Saldo en I.
                if ($isBanco) {
                    // Banco: A..J (Acción columna H, Saldo en I, col J soporte)
                    $sheet->setCellValue('A6', 'N° Acta');
                    $sheet->setCellValue('B6', 'Fecha');
                    $sheet->setCellValue('C6', 'Descripción');
                    $sheet->setCellValue('D6', 'Código');        // agruparemos D6:E6
                    $sheet->setCellValue('F6', 'Movimiento S/.'); // agruparemos F6:G6
                    $sheet->setCellValue('I6', 'Saldo');         // Saldo en I
                    $sheet->setCellValue('J6', 'Acción');        // Acción (soporte extra)
                    // merges
                    $sheet->mergeCells('D6:E6'); // Código: Presupuestario, Actividad
                    $sheet->mergeCells('F6:G6'); // Movimiento: Ingresos, Egresos
                    // styles
                    $sheet->getStyle('A6:J6')->getFont()->setBold(true);
                    $sheet->getStyle('A6:J6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A6:J6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');

                    // Sub-encabezados fila 7
                    $sheet->setCellValue('A7', 'N°');
                    $sheet->setCellValue('B7', 'Fecha');
                    $sheet->setCellValue('C7', 'Descripción');
                    $sheet->setCellValue('D7', 'Presupuestario');
                    $sheet->setCellValue('E7', 'Actividad');
                    $sheet->setCellValue('F7', 'Ingresos');
                    $sheet->setCellValue('G7', 'Egresos');
                    $sheet->setCellValue('H7', ''); // soporte/espacio
                    $sheet->setCellValue('I7', 'Saldo');
                    $sheet->setCellValue('J7', 'Acción');

                } else {
                    // Caja: A..I (sin columna Acción)
                    $sheet->setCellValue('A6', 'N° Acta');
                    $sheet->setCellValue('B6', 'Fecha');
                    $sheet->setCellValue('C6', 'Descripción');
                    $sheet->setCellValue('D6', 'Código');        // agruparemos D6:E6
                    $sheet->setCellValue('F6', 'Movimiento S/.'); // agruparemos F6:G6
                    $sheet->setCellValue('I6', 'Saldo');         // Saldo en I
                    // merges
                    $sheet->mergeCells('D6:E6'); // Código: Presupuestario, Actividad
                    $sheet->mergeCells('F6:G6'); // Movimiento: Ingresos, Egresos
                    // styles
                    $sheet->getStyle('A6:I6')->getFont()->setBold(true);
                    $sheet->getStyle('A6:I6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A6:I6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');

                    // Sub-encabezados fila 7
                    $sheet->setCellValue('A7', 'N°');
                    $sheet->setCellValue('B7', 'Fecha');
                    $sheet->setCellValue('C7', 'Descripción');
                    $sheet->setCellValue('D7', 'Presupuestario');
                    $sheet->setCellValue('E7', 'Actividad');
                    $sheet->setCellValue('F7', 'Ingresos');
                    $sheet->setCellValue('G7', 'Egresos');
                    $sheet->setCellValue('H7', ''); // soporte/espacio
                    $sheet->setCellValue('I7', 'Saldo');

                    $sheet->getStyle('A7:I7')->getFont()->setBold(true);
                    $sheet->getStyle('A7:I7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }

                // Saldo apertura (fila 8)
                $startDataRow = 9;
                if ($isBanco) {
                    $sheet->setCellValue('C8', 'Saldo del mes anterior');
                    $sheet->setCellValue('I8', $saldoApertura);
                    $sheet->getStyle("I8")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('C8:I8')->getFont()->setBold(true);
                    $sheet->getStyle('C8:I8')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                } else {
                    $sheet->setCellValue('C8', 'Saldo del mes anterior');
                    $sheet->setCellValue('I8', $saldoApertura);
                    $sheet->getStyle("I8")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('C8:I8')->getFont()->setBold(true);
                    $sheet->getStyle('C8:I8')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                }

                // Filas de datos desde $startDataRow
                $row = $startDataRow;
                foreach ($registrosMes as $r) {
                    $arr = $r['_raw'];
                    $nacta = $pickValue($arr, [$colNActa, 'n_acta', 'numero_acta']);
                    $fechaVal = $pickValue($arr, [$colFecha, 'fecha', 'created_at']);
                    $descVal = $pickValue($arr, [$colDescripcion, 'descripcion', 'detalle']);
                    $presuVal = $pickValue($arr, [$colPresupuestario, 'presupuestario', 'codigo_presupuestario']);
                    $actividadVal = $pickValue($arr, [$colActividad, 'actividad']);
                    $ing = is_numeric($r['_ingresos']) ? (float)$r['_ingresos'] : 0;
                    $eg  = is_numeric($r['_egresos']) ? (float)$r['_egresos'] : 0;
                    $sd  = is_numeric($r['_saldo']) ? (float)$r['_saldo'] : '';

                    $sheet->setCellValueExplicit('A'.$row, $nacta ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('B'.$row, $fechaVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C'.$row, $descVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D'.$row, $presuVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('E'.$row, $actividadVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F'.$row, $ing);
                    $sheet->setCellValue('G'.$row, $eg);

                    if ($isBanco) {
                        // extra columna Acción (intenta varios nombres)
                        $accionVal = $pickValue($arr, ['accion', 'accion_tipo', 'accion_nombre', 'accion_desc']);
                        $sheet->setCellValueExplicit('J'.$row, $accionVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('I'.$row, $sd);
                    } else {
                        $sheet->setCellValue('I'.$row, $sd);
                    }

                    $row++;
                }

                // Totales
                $lastDataRow = max($startDataRow, $row - 1);
                $summaryRow = $lastDataRow + 2;
                $sheet->setCellValue('C'.$summaryRow, 'Totales del mes — Movimientos');
                $sheet->setCellValue('F'.$summaryRow, $ingresosMes);
                $sheet->setCellValue('G'.$summaryRow, $egresosMes);
                $sheet->setCellValue(($isBanco ? 'I' : 'I').$summaryRow, $movimientosMes);

                // Estilos finales: bordes, auto-size, formatos
                if ($isBanco) {
                    $sheet->getStyle("A6:J{$lastDataRow}")->applyFromArray($innerBorders);
                    $sheet->getStyle("A6:J{$lastDataRow}")->applyFromArray($outerBorders);
                    foreach (range('A', 'J') as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                    $sheet->getStyle("F{$startDataRow}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("I{$startDataRow}:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("F{$summaryRow}:I{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                } else {
                    $sheet->getStyle("A6:I{$lastDataRow}")->applyFromArray($innerBorders);
                    $sheet->getStyle("A6:I{$lastDataRow}")->applyFromArray($outerBorders);
                    foreach (range('A', 'I') as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                    $sheet->getStyle("F{$startDataRow}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("I{$startDataRow}:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("F{$summaryRow}:I{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }

            $sheetIndex++;
        } // end foreach mapping

        // Forzar la primera hoja activa en índice 0
        $spreadsheet->setActiveSheetIndex(0);

        // Guardar en temporal y devolver descarga
        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "contabilidad_proyecto_{$tableSuffix}_{$anio}_{$mes}_{$fechaHora}.xlsx";
        $rutaTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombreArchivo;

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaTmp);

        return response()->download($rutaTmp)->deleteFileAfterSend(true);
    }
}
