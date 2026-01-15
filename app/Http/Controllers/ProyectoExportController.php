<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Style\Border;


class ProyectoExportController extends Controller
{
    public function exportarProyecto($proyecto)
    {
        // ================== 1. Nombres de tablas dinámicas ==================
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');
        $tablaSalidas    = 'salidas_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');

        // ================== 2. Consultas y limpieza de datos ==================
        // 🔹 Inventario (forzamos 0 en campos numéricos vacíos o nulos)
        $inventarios = DB::table($tablaInventario)
            ->select('codigo', 'fecha', 'descripcion', 'unidad_medida', 'categoria', 'entradas', 'salidas', 'stock')
            ->get()
            ->map(function ($item) {
                $arr = (array) $item;
                foreach (['entradas', 'salidas', 'stock'] as $campo) {
                    if (empty($arr[$campo]) && $arr[$campo] !== 0) {
                        $arr[$campo] = 0;
                    }
                    $arr[$campo] = (int) $arr[$campo];
                }
                return $arr;
            })
            ->toArray();

        // 🔹 Salidas (forzamos 0 en cantidad)
        $salidas = DB::table($tablaSalidas)
            ->select('n_acta', 'nombre', 'lugar', 'distrito', 'fecha', 'producto_code', 'producto_label', 'um', 'cantidad')
            ->get()
            ->map(function ($item) {
                $arr = (array) $item;
                if (empty($arr['cantidad']) && $arr['cantidad'] !== 0) {
                    $arr['cantidad'] = 0;
                }
                $arr['cantidad'] = (int) $arr['cantidad'];
                return $arr;
            })
            ->toArray();

        // ================== 3. Crear hoja Excel ==================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ================== 4. Estilos reutilizables ==================
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

        // ================== 5. Títulos principales ==================
        // 🔹 Fila 1 (Título del proyecto)
        $sheet->mergeCells('A1:Q1');
        $sheet->setCellValue('A1', strtoupper("Proyecto: $proyecto"));
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(88, 'px');

        $sheet->getRowDimension(2)->setRowHeight(61, 'px');
        $sheet->getRowDimension(3)->setRowHeight(61, 'px');

        // ================== 6. Tablas y encabezados ==================
        $ultimaFilaInv = count($inventarios) + 3;
        $ultimaFilaSal = count($salidas) + 3;

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'INVENTARIO DE PRODUCTOS');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:H2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:H2')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('9BC2E6');

        $headersInventario = ['CÓDIGO PRODUCTO', 'FECHA', 'DESCRIPCIÓN', 'UNIDAD DE MEDIDA', 'ENTRADAS', 'SALIDAS', 'STOCK', 'COMENTARIOS'];
        $sheet->fromArray($headersInventario, null, 'A3');
        $sheet->getStyle('A3:H3')->getFont()->setBold(true);
        $sheet->getStyle('A3:H3')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A3:H3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');

        $sheet->getStyle("A3:H{$ultimaFilaInv}")->applyFromArray($innerBorders);
        $sheet->getStyle("A3:H{$ultimaFilaInv}")->applyFromArray($outerBorders);

        $sheet->mergeCells('I2:Q2');
        $sheet->setCellValue('I2', 'SALIDAS');
        $sheet->getStyle('I2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('I2:O2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('I2:O2')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('C6E0B4');

        $headersSalidas = [
            'N° ACTA', 'NOMBRE', 'LUGAR', 'DISTRITO', 'FECHA', 
            'CÓDIGO PRODUCTO', 'PRODUCTO', 'U.M.', 'CANTIDAD'
        ];
        $sheet->fromArray($headersSalidas, null, 'I3');

        $sheet->getStyle('I3:Q3')->getFont()->setBold(true);
        $sheet->getStyle('I3:Q3')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I3:Q3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2EFDA');

        $sheet->getStyle("I3:Q{$ultimaFilaSal}")->applyFromArray($innerBorders);
        $sheet->getStyle("I3:Q{$ultimaFilaSal}")->applyFromArray($outerBorders);


        // ================== 7. Inserción de datos ==================
        // Inventario
        $row = 4;
        foreach ($inventarios as $inv) {
            $col = 'A';
            foreach ($inv as $value) {
                $sheet->setCellValueExplicit(
                    $col . $row,
                    is_numeric($value) ? (int) $value : $value,
                    is_numeric($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $row++;
        }

        // Salidas
        $row = 4;
        foreach ($salidas as $sal) {
            $col = 'I';
            foreach ($sal as $value) {
                $sheet->setCellValueExplicit(
                    $col . $row,
                    is_numeric($value) ? (int) $value : $value,
                    is_numeric($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $row++;
        }

        // ================== 8. Ajustes finales ==================
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ================== 9. Guardar archivo ==================
        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "proyecto_{$proyecto}_{$fechaHora}.xlsx";

        $carpeta = public_path('excel_consulta');
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }
        $rutaArchivo = $carpeta . '/' . $nombreArchivo;

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaArchivo);

        return response()->download($rutaArchivo);
    }
}

