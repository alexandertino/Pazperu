<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Models\User;
use App\Models\Salida;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    public function exportarUsuarios()
    {
        $users = Salida::all();

        // Ruta pública (pantalla principal = carpeta "public")
        $filePath = public_path('usuarios.xlsx');

        // Exportar el archivo
        (new FastExcel($users))->export($filePath);

        // Retornar el archivo para su descarga
        return response()->download($filePath);
    }

    public function exportarPdf(){
        $Salidas = Salida::all();

        $pdf = Pdf::loadView('pdf.Salida', compact('Salidas'));

        return $pdf->stream('Salidas.pdf');

    }
}