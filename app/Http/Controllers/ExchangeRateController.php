<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\exchange_rates;
use App\Models\Proyecto;
use Illuminate\Support\Facades\Validator;

class ExchangeRateController extends Controller
{
    // GET /proyectos/{proyecto}/exchange-rate?year=2025&month=9
    public function getForMonth(Proyecto $proyecto, Request $request)
    {
        $year = (int) ($request->query('year') ?? date('Y'));
        $month = (int) ($request->query('month') ?? date('n'));
        $from = $request->query('from_currency', 'PEN');
        $to = $request->query('to_currency', 'EUR');

        $rateRow = exchange_rates::currentForProject($proyecto->id, $year, $month, $from, $to)
            ?? exchange_rates::fallbackGlobal($year, $month, $from, $to);

        return response()->json([
            'ok' => true,
            'rate' => $rateRow ? (float)$rateRow->rate : null,
            'found_in' => $rateRow ? ($rateRow->proyecto_id ? 'project' : 'global') : null,
            'year' => $year,
            'month' => $month
        ]);
    }

    // POST /proyectos/{proyecto}/exchange-rate  (o sin proyecto para global)
    public function upsert(Proyecto $proyecto = null, Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'rate' => 'required|numeric|min:0',
            'proyecto_id' => 'nullable|exists:proyectos,id',
            'from_currency' => 'required|string|max:10',
            'to_currency' => 'required|string|max:10',
            'source' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $row = exchange_rates::updateOrCreate(
            [
                'proyecto_id' => $data['proyecto_id'] ?? ($proyecto ? $proyecto->id : null),
                'year' => $data['year'],
                'month' => $data['month'],
                'from_currency' => $data['from_currency'],
                'to_currency' => $data['to_currency'],
            ],
            [
                'rate' => $data['rate'],
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null
            ]
        );

        return response()->json(['ok'=>true,'rate' => (float)$row->rate, 'id'=>$row->id]);
    }
}
