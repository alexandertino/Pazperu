<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                if (is_string($log->changes)) {
                    $log->changes = json_decode($log->changes, true);
                }
                return $log;
            });

        return Inertia::render('Logs/Index', [
            'logs' => $logs
        ]);
    }

    // Nuevo: devuelve JSON con los logs de un modelo+id (solo admin)
    public function forModel(Request $request, $model, $id)
    {
        $user = Auth::user();

        // Solo admin puede ver este endpoint
        if (! $user || $user->role !== 'admin') {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        // Normalizar si viene urlencoded
        $model = urldecode($model);

        $logs = ActivityLog::with('user')
            ->where('model', $model)
            ->where('model_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                if (is_string($log->changes)) {
                    $log->changes = json_decode($log->changes, true);
                }
                return $log;
            });

        return response()->json(['ok' => true, 'logs' => $logs], 200);
    }
}
