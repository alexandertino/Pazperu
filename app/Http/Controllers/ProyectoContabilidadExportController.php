<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Carbon;

class ProyectoContabilidadExportController extends Controller
{
    public function exportarMesMultiples(Request $request, $proyecto)
    {
        $mes = (int) $request->query('mes', 0);
        $anio = (int) $request->query('anio', 0);

        if ($mes < 1 || $mes > 12 || $anio < 1900) {
            return abort(400, 'Parámetros mes/año inválidos.');
        }

        $proyectoRow = DB::table('proyectos')->where('id', $proyecto)->first();
        if (! $proyectoRow) {
            return abort(404, "Proyecto no encontrado: {$proyecto}");
        }

        
        // ========== NUEVOS PARAMS (C2..C5 y logos) ==========
        $c2_text = $request->query('c2', 'Islas de Paz Perú');
        $c3_ruc  = $request->query('ruc', $proyectoRow->ruc ?? 'ruc:20600630769');
        $c4_text = $request->query('c4', 'Organización no Gubernamental');
        $c5_dir  = $request->query('direccion', $proyectoRow->direccion ?? 'Dirección: Jr. Faustino Sánchez Carrión N° 117 - Amarilis - Huánuco ');

        // Logo principal (ruta por query o por defecto)
        $logo_path = $request->query('logo_path', public_path('images/logo.png'));
        // Logo secundario (col J-K final)
        $logo2_path = $request->query('logo2_path', public_path('images/logo_secundario.png'));
        // ====================================================

        $tableSuffix = (string) Str::of($proyectoRow->nombre)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');

        $tables = [
            'caja'  => 'am_caja_proyecto_' . $tableSuffix,
            'banco' => 'am_banco_proyecto_' . $tableSuffix,
            'easy_candidates' => [
                'am_easy_proyecto_' . $tableSuffix,
                'easy_proyecto_' . $tableSuffix,
            ],
        ];

        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = (clone $desde)->endOfMonth()->endOfDay();
        $prevDay = (clone $desde)->subDay()->endOfDay();

        $getEasyTable = function () use ($tables) {
            foreach ($tables['easy_candidates'] as $t) {
                if (Schema::hasTable($t)) return $t;
            }
            return null;
        };

        $mapping = [];
        if (Schema::hasTable($tables['caja']))  $mapping['Caja']  = $tables['caja'];
        if (Schema::hasTable($tables['banco'])) $mapping['Banco'] = $tables['banco'];
        $easyTable = $getEasyTable();
        if ($easyTable) $mapping['Easy'] = $easyTable;

        if (count($mapping) === 0) {
            return abort(404, "No se encontraron tablas contables (caja/banco/easy) para el proyecto '{$proyectoRow->nombre}'.");
        }

        // Helper: primer columna que exista entre candidatos
        $firstColumn = function (string $table, array $candidates) {
            foreach ($candidates as $c) {
                if ($c === null) continue;
                if (Schema::hasColumn($table, $c)) return $c;
            }
            return null;
        };

        // Helper: obtener valor de array por orden de candidatos (la versión antigua será reemplazada más abajo)
        // --------------------------------------------------
        // REEMPLAZOS: setNumericOrBlank (muestra 0) y pickValue (insensible a acentos/case)
        // --------------------------------------------------
        $setNumericOrBlank = function ($sheetObj, string $cell, $value, $format = '#,##0.00') {
            // null -> celda vacía (string)
            if ($value === null) {
                $sheetObj->setCellValueExplicit($cell, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                return;
            }

            // Si es numérico (incluye 0), ponerlo como número y aplicar formato
            if (is_numeric($value)) {
                // convertir a float para que PhpSpreadsheet lo trate como número
                $sheetObj->setCellValue($cell, (float)$value);
                $sheetObj->getStyle($cell)->getNumberFormat()->setFormatCode($format);
                return;
            }

            // En cualquier otro caso (texto, etc.) escribir como string
            $sheetObj->setCellValueExplicit($cell, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        };

        $pickValue = function (array $arr, array $candidates) {
            // Normalizar keys del array: convertir a ascii sin acentos y lowercase
            $map = [];
            foreach ($arr as $k => $v) {
                // convertir key a ASCII (elimina acentos) y a minúsculas
                $kNorm = strtolower(@iconv('UTF-8', 'ASCII//TRANSLIT', $k) ?: $k);
                $map[$kNorm] = $v;
            }

            foreach ($candidates as $c) {
                if ($c === null) continue;
                $cNorm = strtolower(@iconv('UTF-8', 'ASCII//TRANSLIT', $c) ?: $c);
                if (array_key_exists($cNorm, $map)) return $map[$cNorm];
                // también permitir que el candidate sea substring de alguna key (por si hay prefijos)
                foreach ($map as $kNorm => $v) {
                    if (strpos($kNorm, $cNorm) !== false) return $v;
                }
            }
            return null;
        };
        // --------------------------------------------------

        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

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

        // ---------- PARTE 2: Generación de hojas, formato y descarga ----------
        foreach ($mapping as $label => $tablaBase) {
            // Determinar columnas relevantes por tabla
            
            $colFecha = $firstColumn($tablaBase, ['fecha', 'created_at', 'updated_at']);
            if (! $colFecha) continue;

            $colNActa = $firstColumn($tablaBase, ['n_acta', 'nacta', 'numero_acta']);
            $colDescripcion = $firstColumn($tablaBase, ['descripcion', 'detalle', 'desc', 'concepto']);
            $colPresupuestario = $firstColumn($tablaBase, ['presupuestario', 'codigo_presupuestario', 'cod_presupuesto', 'codigo_presupuestario']);
            $colActividad = $firstColumn($tablaBase, ['actividad', 'actividad_nombre']);

            if (strtolower($label) === 'easy') {
                $colIngreso = $firstColumn($tablaBase, ['ingreso_moneda_local', 'ingreso', 'credito_moneda_gestion', 'credito']);
                $colEgreso  = $firstColumn($tablaBase, ['gasto_moneda_local', 'gasto', 'debito_moneda_gestion', 'debito']);
                $colSaldo   = $firstColumn($tablaBase, ['saldo', 'balance']);
            } else {
                $colIngreso = $firstColumn($tablaBase, ['ingresos', 'monto_ingreso', 'credito', 'ingreso']);
                $colEgreso  = $firstColumn($tablaBase, ['egresos', 'monto_egreso', 'debito', 'egreso']);
                $colSaldo   = $firstColumn($tablaBase, ['saldo', 'balance']);
            }

            // Consulta registros del mes
            $query = DB::table($tablaBase)
                ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                ->orderBy($colFecha, 'asc');
            if (Schema::hasColumn($tablaBase, 'id')) $query = $query->orderBy('id', 'asc');
            $rawRegistros = $query->get();

            $registrosMes = [];
            foreach ($rawRegistros as $r) {
                $arr = (array) $r;

                $nacta = $pickValue($arr, array_filter([$colNActa, 'n_acta', 'nacta', 'numero_acta']));
                $fechaVal = $pickValue($arr, array_filter([$colFecha, 'fecha', 'created_at', 'updated_at']));
                $descVal = $pickValue($arr, array_filter([$colDescripcion, 'descripcion', 'detalle', 'desc', 'concepto']));
                $presuVal = $pickValue($arr, array_filter([$colPresupuestario, 'presupuestario', 'codigo_presupuestario', 'cod_presupuesto']));
                $actividadVal = $pickValue($arr, array_filter([$colActividad, 'actividad', 'actividad_nombre']));

                $ing = null;
                $eg  = null;
                if ($colIngreso !== null && array_key_exists($colIngreso, $arr)) {
                    $ing = is_numeric($arr[$colIngreso]) ? (float)$arr[$colIngreso] : null;
                } elseif (array_key_exists('ingresos', $arr)) {
                    $ing = is_numeric($arr['ingresos']) ? (float)$arr['ingresos'] : null;
                }
                if ($colEgreso !== null && array_key_exists($colEgreso, $arr)) {
                    $eg = is_numeric($arr[$colEgreso]) ? (float)$arr[$colEgreso] : null;
                } elseif (array_key_exists('egresos', $arr)) {
                    $eg = is_numeric($arr['egresos']) ? (float)$arr['egresos'] : null;
                }

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

            // Calcular saldo apertura (igual que antes)...
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
                    $sumIngreso = 0;
                    $sumEgreso = 0;
                    if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                        $r = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                            ->selectRaw('COALESCE(SUM(`' . $colIngreso . '`),0) as s_ing')->first();
                        $sumIngreso = $r->s_ing ?? 0;
                    }
                    if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                        $r2 = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                            ->selectRaw('COALESCE(SUM(`' . $colEgreso . '`),0) as s_eg')->first();
                        $sumEgreso = $r2->s_eg ?? 0;
                    }
                    $saldoApertura = (float)$sumIngreso - (float)$sumEgreso;
                }
            } else {
                $sumIngreso = 0;
                $sumEgreso = 0;
                if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                    $r = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                        ->selectRaw('COALESCE(SUM(`' . $colIngreso . '`),0) as s_ing')->first();
                    $sumIngreso = $r->s_ing ?? 0;
                }
                if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                    $r2 = DB::table($tablaBase)->where($colFecha, '<=', $prevDay->toDateString())
                        ->selectRaw('COALESCE(SUM(`' . $colEgreso . '`),0) as s_eg')->first();
                    $sumEgreso = $r2->s_eg ?? 0;
                }
                $saldoApertura = (float)$sumIngreso - (float)$sumEgreso;
            }

            // Totales del mes
            $ingresosMes = 0;
            $egresosMes = 0;
            if ($colIngreso && Schema::hasColumn($tablaBase, $colIngreso)) {
                $r = DB::table($tablaBase)
                    ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                    ->selectRaw('COALESCE(SUM(`' . $colIngreso . '`),0) as s_ing')
                    ->first();
                $ingresosMes = $r->s_ing ?? 0;
            }
            if ($colEgreso && Schema::hasColumn($tablaBase, $colEgreso)) {
                $r2 = DB::table($tablaBase)
                    ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                    ->selectRaw('COALESCE(SUM(`' . $colEgreso . '`),0) as s_eg')
                    ->first();
                $egresosMes = $r2->s_eg ?? 0;
            }

            $movimientosMes = (float)$ingresosMes - (float)$egresosMes;

            // Obtener último saldo registrado en el mes (si existe)
            $ultimoSaldoMes = null;
            if ($colSaldo && Schema::hasColumn($tablaBase, $colSaldo)) {
                $ultimoReg = DB::table($tablaBase)
                    ->select($colSaldo, $colFecha)
                    ->whereBetween($colFecha, [$desde->toDateString(), $hasta->toDateString()])
                    ->whereNotNull($colSaldo)
                    ->orderBy($colFecha, 'desc');
                if (Schema::hasColumn($tablaBase, 'id')) $ultimoReg = $ultimoReg->orderBy('id', 'desc');
                $ultimoReg = $ultimoReg->first();
                if ($ultimoReg && isset($ultimoReg->{$colSaldo})) {
                    $ultimoSaldoMes = (float)$ultimoReg->{$colSaldo};
                }
            }

            // Si es CAJA y hay saldo real, usamos ese; en caso contrario, usamos el cálculo normal
            if (strtolower($label) === 'caja' && $ultimoSaldoMes !== null) {
                $saldoCierre = $ultimoSaldoMes;
            } else {
                $saldoCierre = (float)$saldoApertura + (float)$movimientosMes;
            }


            // Crear hoja
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $tituloHoja = substr($label . ' ' . $desde->format('M Y'), 0, 31);
            $sheet->setTitle($tituloHoja);

            // ===== Ajuste: Columna A en 15px (~0.94cm) para cada hoja =====
            $sheet->getColumnDimension('A')->setWidth(2.82);
            // ===== Inserción del logo principal Y logo secundario ARRIBA (más grandes) =====
            // Definimos coordenadas para logo2 según tipo de hoja
            $logo2Coord = (strtolower($label) === 'easy') ? 'M1' : ((strtolower($label) === 'banco') ? 'H1' : 'H1');

            if ($logo_path && file_exists($logo_path)) {
                try {
                    $drawing = new Drawing();
                    $drawing->setPath($logo_path);
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo principal');
                    $drawing->setCoordinates('B2');
                    $drawing->setResizeProportional(false);
                    // tamaño aumentado
                    $drawing->setHeight(53); // mayor alto
                    $drawing->setWidth(79); // mayor ancho
                    $drawing->setWorksheet($sheet);
                } catch (\Exception $e) {
                    // No interrumpir si falla insertar logo; sólo ignorar
                }
            }

            if ($logo2_path && file_exists($logo2_path)) {
                try {
                    $drawing2 = new Drawing();
                    $drawing2->setPath($logo2_path);
                    $drawing2->setName('Logo Secundario');
                    $drawing2->setDescription('Logo secundario (arriba)');
                    $drawing2->setResizeProportional(false);
                    $drawing2->setHeight(80);
                    $drawing2->setWidth(160);
                    $drawing2->setCoordinates($logo2Coord);
                    $drawing2->setWorksheet($sheet);
                } catch (\Exception $e) {
                }
            }

            // ===== EASY (compacto) =====
            if (strtolower($label) === 'easy') {
                // C2..C5 valores solicitados por ti (sin cambio)
                $mergeCols = $easyTable ? 'A1:N1' : 'A1:O1';
                $sheet->mergeCells($mergeCols);
                $sheet->setCellValue('A1', strtoupper("Proyecto: {$proyectoRow->nombre} — {$label} — " . $desde->format('F Y')));
                $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValueExplicit('C2', $c2_text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C3', $c3_ruc,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C4', $c4_text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C5', $c5_dir,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                // Banner en fila 7 MOVIDO a B..N (original A..M)
                $sheet->mergeCells('B7:N7');
                $sheet->setCellValue('B7', strtoupper('INFORME ECONÓMICO - ' . $desde->format('F Y')));
                $sheet->getStyle('B7')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('B7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('0D6EAF');

                // Encabezados fila 9, datos desde fila 11 -> ahora empiezan en B9
                $headers = [
                    'Compte général', 'Dépense (PEN)', 'Recette (PEN)', 'Moneda factura',
                    'Débito (EUR)', 'Crédito (EUR)', 'Moneda gestión', 'Num./Descripción',
                    'Código presup.', 'Naturaleza', 'Contrato', 'Bailleurs', 'Fecha'
                ];
                $sheet->fromArray($headers, null, 'B9');
                $sheet->getStyle('B9:N9')->getFont()->setBold(true);
                $sheet->getStyle('B9:N9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B9:N9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('9BC2E6');

                // Datos desde fila 11 (todas las columnas desplazadas +1)
                $row = 11;
                foreach ($registrosMes as $r) {
                    $arr = $r['_raw'];

                    $cuenta_general = $pickValue($arr, [
                        'cuenta_general', 'compte_general', 'codigo_general', 'codigo_cuenta', 'codigo_cuenta_general',
                        'comptegeneral', 'compte_general', 'compte general'
                    ]);
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

                    // SHIFT +1: A->B, B->C, C->D, ...
                    // Forzamos cuenta_general como string (así '0' o '00...' no se pierden)
                    $sheet->setCellValueExplicit('B' . $row, ($cuenta_general !== null ? (string)$cuenta_general : ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $setNumericOrBlank($sheet, 'C' . $row, is_numeric($gasto_pen) ? (float)$gasto_pen : $gasto_pen);
                    $setNumericOrBlank($sheet, 'D' . $row, is_numeric($receta_pen) ? (float)$receta_pen : $receta_pen);

                    $sheet->setCellValueExplicit('E' . $row, $moneda_facturacion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $setNumericOrBlank($sheet, 'F' . $row, is_numeric($debito_eur) ? (float)$debito_eur : $debito_eur);
                    $setNumericOrBlank($sheet, 'G' . $row, is_numeric($credito_eur) ? (float)$credito_eur : $credito_eur);

                    $sheet->setCellValueExplicit('H' . $row, $moneda_gestion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('I' . $row, $numero_descripcion ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('J' . $row, $codigo_presupuesto ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('K' . $row, $naturaleza ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('L' . $row, $contrato ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('M' . $row, $donantes ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('N' . $row, $fecha_val ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $row++;
                }

                $lastDataRow = max(11, $row - 1);
                $summaryRowStart = $lastDataRow + 2;

                // Rangos desplazados +1
                $sheet->getStyle("B9:N{$lastDataRow}")->applyFromArray($innerBorders);
                $sheet->getStyle("B9:N{$lastDataRow}")->applyFromArray($outerBorders);
                foreach (range('B', 'N') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // -> CONTROL DE ANCHOS: evitar que autosize sobreescriba nuestro ancho fijo
                foreach (range('B', 'N') as $col) {
                    // permitir autoSize en todas excepto C (C debe quedar fija)
                    if ($col === 'C') {
                        $sheet->getColumnDimension($col)->setAutoSize(false);
                        continue;
                    }
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Ahora fijamos definitivamente la columna C (después del loop)
                $sheet->getColumnDimension('C')->setAutoSize(false);
                $sheet->getColumnDimension('C')->setWidth(13.67); // ≈ 100-130 px según preferencia
                $sheet->getStyle('C:C')->getAlignment()->setWrapText(false);
                $sheet->getStyle('C:C')->getAlignment()->setShrinkToFit(false);

                // Evitar que el texto "desborde" (Excel muestra overflow si la celda derecha está vacía).
                // Vamos a asegurarnos de que la columna D tenga un valor (cadena vacía explícita) cuando escribamos filas.


                // Formatos numéricos (columnas movidas)
                $sheet->getStyle("C11:D{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00'); // Gasto, Receta -> C,D
                $sheet->getStyle("F11:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00'); // Debito, Credito -> F,G
                $sheet->getStyle("C{$summaryRowStart}:D" . ($summaryRowStart + 1))->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("N{$summaryRowStart}:N" . ($summaryRowStart + 1))->getNumberFormat()->setFormatCode('#,##0.00');

                // (Se quitó la inserción de logo2 abajo; ahora está arriba)
            } else {
                // ===== LIBRO DEL DIARIO (Caja/Banco) =====
                $isBanco = (strtolower($label) === 'banco');

                // C2..C5
                $sheet->setCellValueExplicit('C2', $c2_text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C3', $c3_ruc,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C4', $c4_text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C5', $c5_dir,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $mergeCols = $isBanco ? 'A1:I1' : 'A1:I1';
                $sheet->mergeCells($mergeCols);
                $sheet->setCellValue('A1', strtoupper("Proyecto: {$proyectoRow->nombre} — {$label} — " . $desde->format('F Y')));
                $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Banner en fila 7 MOVIDO a empezar en B
                $sheet->mergeCells($isBanco ? 'B7:I7' : 'B7:I7');
                $sheet->setCellValue('B7', "LIBRO DEL DIARIO - " . $desde->format('F Y'));
                $sheet->getStyle('B7')->getFont()->setBold(true);
                $sheet->getStyle('B7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFEB3B');

                // Encabezados en fila 9 (datos en fila 11) - ahora desplazados +1
                if ($isBanco) {
                    $cols = [
                        'B9' => 'N° Acta',
                        'C9' => 'Fecha',
                        'D9' => 'Descripción',
                        'E9' => 'Presupuestario',
                        'F9' => 'Actividad',
                        'G9' => 'Ingresos',
                        'H9' => 'Egresos',
                        'I9' => 'Saldo',
                    ];
                    foreach ($cols as $cell => $text) {
                        $sheet->setCellValue($cell, $text);
                    }
                    $sheet->getStyle('B9:I9')->getFont()->setBold(true);
                    $sheet->getStyle('B9:I9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B9:I9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');
                } else {
                    $cols = [
                        'B9' => 'N° Acta',
                        'C9' => 'Fecha',
                        'D9' => 'Descripción',
                        'E9' => 'Presupuestario',
                        'F9' => 'Actividad',
                        'G9' => 'Ingresos',
                        'H9' => 'Egresos',
                        'I9' => 'Saldo',
                    ];
                    foreach ($cols as $cell => $text) {
                        $sheet->setCellValue($cell, $text);
                    }
                    $sheet->getStyle('B9:I9')->getFont()->setBold(true);
                    $sheet->getStyle('B9:I9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B9:I9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');
                }

                // Saldo apertura (fila 8 intermedia) -> SALDO ahora en columna J (antes I)
                $setNumericOrBlank($sheet, 'I10', is_numeric($saldoApertura) ? (float)$saldoApertura : $saldoApertura);
                // Movemos el texto label para encajar con tabla desplazada (+1)
                $sheet->setCellValue('D10', 'Saldo del mes anterior');
                $sheet->getStyle('B10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('D10:J10')->getFont()->setBold(true);
                $sheet->getStyle('D10:J10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                // Datos desde fila 11  
                $startDataRow = 11;
                $row = $startDataRow;
                foreach ($registrosMes as $r) {
                    $arr = $r['_raw'];
                    $nacta = $pickValue($arr, [$colNActa, 'n_acta', 'numero_acta']);
                    $fechaVal = $pickValue($arr, [$colFecha, 'fecha', 'created_at']);
                    $descVal = $pickValue($arr, [$colDescripcion, 'descripcion', 'detalle']);
                    $presuVal = $pickValue($arr, [$colPresupuestario, 'presupuestario', 'codigo_presupuestario']);
                    $actividadVal = $pickValue($arr, [$colActividad, 'actividad']);
                    $ing = is_numeric($r['_ingresos']) ? (float)$r['_ingresos'] : $r['_ingresos'];
                    $eg  = is_numeric($r['_egresos']) ? (float)$r['_egresos'] : $r['_egresos'];
                    $sd  = is_numeric($r['_saldo']) ? (float)$r['_saldo'] : $r['_saldo'];

                    // SHIFT +1 for data columns: A->B, B->C, ...
                    $sheet->setCellValueExplicit('B' . $row, $nacta ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C' . $row, $fechaVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D' . $row, $descVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('E' . $row, $presuVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('F' . $row, $actividadVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $setNumericOrBlank($sheet, 'G' . $row, $ing);
                    $setNumericOrBlank($sheet, 'H' . $row, $eg);

                    if ($isBanco) {
                        $accionVal = $pickValue($arr, ['accion', 'accion_tipo', 'accion_nombre', 'accion_desc']);
                        $sheet->setCellValueExplicit('K' . $row, $accionVal ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $setNumericOrBlank($sheet, 'I' . $row, $sd);
                    } else {
                        $setNumericOrBlank($sheet, 'I' . $row, $sd);
                    }

                    $row++;
                }

                $lastDataRow = max($startDataRow, $row - 1);
                $summaryRow = $lastDataRow + 2;
                $sheet->setCellValue('D' . $summaryRow, 'Totales del mes — Movimientos');

                // Totales desplazados
                $setNumericOrBlank($sheet, 'G' . $summaryRow, $ingresosMes);
                $setNumericOrBlank($sheet, 'H' . $summaryRow, $egresosMes);
                if (strtolower($label) === 'caja') {
                    $setNumericOrBlank($sheet, 'I' . $summaryRow, $saldoCierre);
                } else {
                    $setNumericOrBlank($sheet, 'I' . $summaryRow, $movimientosMes);
                }


                // Aplicar estilos con nuevos rangos (desplazados +1)
                if ($isBanco) {
                    $sheet->getStyle("B9:I{$lastDataRow}")->applyFromArray($innerBorders);
                    $sheet->getStyle("B9:I{$lastDataRow}")->applyFromArray($outerBorders);
                    foreach (range('B', 'I') as $col) {
                    // mantener la columna C con ancho fijo
                    if ($col === 'C') {
                        $sheet->getColumnDimension($col)->setAutoSize(false);
                        $sheet->getColumnDimension($col)->setWidth(14.3);
                        continue;
                    }
                    $sheet->getColumnDimension($col)->setAutoSize(true);

                    }
                    $sheet->getStyle("G{$startDataRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("I{$startDataRow}:J{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("G{$summaryRow}:J{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                } else {
                    $sheet->getStyle("B9:I{$lastDataRow}")->applyFromArray($innerBorders);
                    $sheet->getStyle("B9:I{$lastDataRow}")->applyFromArray($outerBorders);
                    foreach (range('B', 'I') as $col) {
                        // mantener la columna C con ancho fijo
                        if ($col === 'C') {
                            $sheet->getColumnDimension($col)->setAutoSize(false);
                            $sheet->getColumnDimension($col)->setWidth(14.3);
                            continue;
                        }
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                    $sheet->getColumnDimension('C')->setAutoSize(false);
                    $sheet->getColumnDimension('C')->setWidth(14.3);
                    $sheet->getStyle("G{$startDataRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("J{$startDataRow}:J{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("G{$summaryRow}:J{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // (Se quitó la inserción de logo2 abajo; ahora está arriba)
            }

            $sheetIndex++;
        }
        // Finalizar: activar la primera hoja y guardar para descarga
        $spreadsheet->setActiveSheetIndex(0);

        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "contabilidad_proyecto_{$tableSuffix}_{$anio}_{$mes}_{$fechaHora}.xlsx";
        $rutaTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombreArchivo;

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaTmp);

        return response()->download($rutaTmp)->deleteFileAfterSend(true);

        // ---------- FIN PARTE 2 ----------
    }
}
