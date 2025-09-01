<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Inertia\Inertia;


class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                // Solo decodificamos si es string
                if (is_string($log->changes)) {
                    $log->changes = json_decode($log->changes, true);
                }
                return $log;
            });

        return Inertia::render('Logs/Index', [
            'logs' => $logs
        ]);
    }


}
