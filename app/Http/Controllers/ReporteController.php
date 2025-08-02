<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteController extends Controller
{
    public function index()
    {
        return Inertia::render('reportes');
    }
}