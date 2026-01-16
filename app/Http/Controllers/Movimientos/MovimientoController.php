<?php

namespace App\Http\Controllers\Movimientos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Movimiento;
use App\Models\SubcuentaMovimiento;
use App\Services\SaldosService;
use App\Models\CuentaGeneral;
use Inertia\Inertia;

class MovimientoController extends Controller
{
    /**
     * Convierte valores monetarios con formato "S/1,000.00" a float
     */
    private function convertirValorMonetario($valor)
    {
        if (is_null($valor)) {
            return 0.00;
        }

        if (is_numeric($valor)) {
            return floatval($valor);
        }

        if (is_string($valor)) {
            $limpio = str_replace(['S/', ' ', ','], '', $valor);
            return floatval($limpio);
        }

        return 0.00;
    }

    /**
     * Lista API de movimientos (opcionalmente por cuenta).
     */
    public function index(Request $request)
    {
        $q = Movimiento::with('subcuenta', 'cuenta', 'movimientoPendiente', 'movimientoSaldante');

        if ($request->filled('cuenta')) {
            $q->where('cuenta_general_id', $request->get('cuenta'));
        }

        if ($request->filled('pendientes')) {
            $q->where('es_pendiente', true)
              ->where('pendiente_saldado', false);
        }

        $movimientos = $q->orderBy('fecha_operacion')->orderBy('id')->get();

        return response()->json($movimientos);
    }

    /**
     * Mostrar formulario Inertia para crear movimiento.
     */
    public function create(Request $request)
    {
        $cuentaId = $request->id;

        if (!$cuentaId) {
            return redirect()->route('cuentas.index')
                ->with('error', 'Debe seleccionar una cuenta.');
        }

        // Obtener cuenta y subcuentas
        $cuenta = CuentaGeneral::with('subcuentas')->find($cuentaId);

        if (!$cuenta) {
            return redirect()->route('cuentas.index')
                ->with('error', 'Cuenta no encontrada.');
        }

        // Último movimiento
        $ultimoMovimiento = Movimiento::where('cuenta_general_id', $cuentaId)
            ->orderBy('id', 'desc')
            ->first();

        $ultimoSaldo = $ultimoMovimiento
            ? floatval($ultimoMovimiento->saldo)
            : floatval($cuenta->saldo_inicial);

        // Obtener pendientes activos para esta cuenta
        $pendientesActivos = Movimiento::where('cuenta_general_id', $cuentaId)
            ->where('es_pendiente', true)
            ->where('pendiente_saldado', false)
            ->orderBy('fecha_operacion')
            ->get(['id', 'numero', 'descripcion', 'deudor', 'acreedor', 'fecha_operacion']);

        return Inertia::render('Movimientos/Create', [
            'cuenta' => $cuenta,
            'ultimo_saldo' => $ultimoSaldo,
            'pendientes_activos' => $pendientesActivos,
        ]);
    }

    /**
     * Crear movimiento (store).
     */
    public function store(Request $request)
    {
        $request->validate([
            'cuenta_general_id' => 'required|integer|exists:cuentas_generales,id',
            'fecha_operacion'   => 'required|date',
            'descripcion'       => 'required|string',
            'deudor'            => 'nullable|numeric',
            'acreedor'          => 'nullable|numeric',
            'medio_pago'        => 'nullable|string|max:50',
            'subcuenta_id'      => 'nullable|integer|exists:subcuentas,id',
            'es_pendiente'      => 'nullable|boolean',
            'movimiento_pendiente_id' => 'nullable|integer|exists:movimientos,id',
        ]);

        return DB::transaction(function () use ($request) {
            $deudor = $request->deudor === null ? 0 : floatval($request->deudor);
            $acreedor = $request->acreedor === null ? 0 : floatval($request->acreedor);
            $esPendiente = $request->es_pendiente ?? false;
            $movimientoPendienteId = $request->movimiento_pendiente_id;

            // Validar que si es para saldar un pendiente, el movimiento pendiente exista
            if ($movimientoPendienteId) {
                $movimientoPendiente = Movimiento::findOrFail($movimientoPendienteId);
                
                // Verificar que el pendiente pertenezca a la misma cuenta
                if ($movimientoPendiente->cuenta_general_id != $request->cuenta_general_id) {
                    throw new \Exception('El movimiento pendiente no pertenece a esta cuenta.');
                }
                
                // Verificar que el pendiente esté activo
                if (!$movimientoPendiente->esPendienteActivo()) {
                    throw new \Exception('El movimiento pendiente ya fue saldado o no es un pendiente.');
                }
            }

            // 1. Obtener el último número
            $ultimoNumero = Movimiento::where('cuenta_general_id', $request->cuenta_general_id)
                ->lockForUpdate()
                ->max('numero') ?? 0;

            $nuevoNumero = $ultimoNumero + 1;

            // 2. Crear movimiento
            $mov = Movimiento::create([
                'cuenta_general_id' => $request->cuenta_general_id,
                'numero'            => $nuevoNumero,
                'fecha_operacion'   => $request->fecha_operacion,
                'descripcion'       => $request->descripcion,
                'deudor'            => $deudor,
                'acreedor'          => $acreedor,
                'saldo'             => 0,
                'medio_pago'        => $request->medio_pago,
                'subcuenta_id'      => $request->subcuenta_id,
                'es_pendiente'      => $esPendiente,
                'pendiente_saldado' => false,
                'movimiento_pendiente_id' => $movimientoPendienteId,
            ]);

            // 3. Si este movimiento salda un pendiente, actualizar el pendiente
            if ($movimientoPendienteId) {
                $movimientoPendiente = Movimiento::find($movimientoPendienteId);
                $movimientoPendiente->update([
                    'pendiente_saldado' => true,
                    'movimiento_saldante_id' => $mov->id,
                ]);
            }

            // 4. Manejar subcuentas
            if (!empty($request->subcuenta_id)) {
                SubcuentaMovimiento::create([
                    'subcuenta_id' => $request->subcuenta_id,
                    'fecha'        => $request->fecha_operacion,
                    'descripcion'  => $request->descripcion,
                    'deudor'       => $deudor,
                    'acreedor'     => $acreedor,
                    'saldo'        => 0,
                ]);
                SaldosService::recalcularSubcuenta($request->subcuenta_id);
            }

            // 5. Recalcular cuenta
            SaldosService::recalcularCuenta($request->cuenta_general_id);

            return redirect()->route('cuentas.show', $request->cuenta_general_id)
                ->with('success', "Movimiento #{$nuevoNumero} registrado correctamente.");
        });
    }

    /**
     * Mostrar vista para editar movimiento
     */
    public function edit($id)
    {
        $mov = Movimiento::with('subcuenta', 'movimientoPendiente')->findOrFail($id);
        $cuenta = CuentaGeneral::with('subcuentas')->findOrFail($mov->cuenta_general_id);

        // Obtener pendientes activos (excluyendo este movimiento si es pendiente)
        $pendientesActivos = Movimiento::where('cuenta_general_id', $mov->cuenta_general_id)
            ->where('es_pendiente', true)
            ->where('pendiente_saldado', false)
            ->where('id', '!=', $id) // Excluir este movimiento
            ->orderBy('fecha_operacion')
            ->get(['id', 'numero', 'descripcion', 'deudor', 'acreedor', 'fecha_operacion']);

        return Inertia::render('Movimientos/Edit', [
            'movimiento' => $mov,
            'cuenta' => $cuenta,
            'pendientes_activos' => $pendientesActivos,
        ]);
    }

    /**
     * Actualizar movimiento
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'fecha_operacion' => 'required|date',
            'medio_pago' => 'nullable|string',
            'descripcion' => 'required|string',
            'deudor' => 'nullable|numeric',
            'acreedor' => 'nullable|numeric',
            'subcuenta_id' => 'nullable|exists:subcuentas,id',
            'es_pendiente' => 'nullable|boolean',
            'movimiento_pendiente_id' => 'nullable|exists:movimientos,id',
        ]);

        $data['deudor'] = $data['deudor'] ?? 0;
        $data['acreedor'] = $data['acreedor'] ?? 0;
        $data['es_pendiente'] = $data['es_pendiente'] ?? false;

        $mov = Movimiento::findOrFail($id);

        return DB::transaction(function () use ($mov, $data, $request) {
            // Guardar IDs antiguos para comparaciones
            $subcuentaAnteriorId = $mov->subcuenta_id;
            $nuevaSubcuentaId = $data['subcuenta_id'] ?? null;
            
            $movimientoPendienteAnteriorId = $mov->movimiento_pendiente_id;
            $nuevoMovimientoPendienteId = $data['movimiento_pendiente_id'] ?? null;

            // 1. Si cambia el movimiento pendiente que salda
            if ($movimientoPendienteAnteriorId != $nuevoMovimientoPendienteId) {
                // Si había un movimiento pendiente anterior, desvincularlo
                if ($movimientoPendienteAnteriorId) {
                    $pendienteAnterior = Movimiento::find($movimientoPendienteAnteriorId);
                    $pendienteAnterior->update([
                        'pendiente_saldado' => false,
                        'movimiento_saldante_id' => null,
                    ]);
                }

                // Si se asigna un nuevo movimiento pendiente, vincularlo
                if ($nuevoMovimientoPendienteId) {
                    $nuevoPendiente = Movimiento::findOrFail($nuevoMovimientoPendienteId);
                    
                    // Validaciones
                    if ($nuevoPendiente->cuenta_general_id != $mov->cuenta_general_id) {
                        throw new \Exception('El movimiento pendiente no pertenece a esta cuenta.');
                    }
                    
                    if (!$nuevoPendiente->esPendienteActivo()) {
                        throw new \Exception('El movimiento pendiente ya fue saldado o no es un pendiente.');
                    }

                    $nuevoPendiente->update([
                        'pendiente_saldado' => true,
                        'movimiento_saldante_id' => $mov->id,
                    ]);
                }
            }

            // 2. Actualizar movimiento
            $mov->update($data);

            // 3. Manejar cambios en subcuenta
            if ($subcuentaAnteriorId != $nuevaSubcuentaId) {
                if ($subcuentaAnteriorId) {
                    SubcuentaMovimiento::where('subcuenta_id', $subcuentaAnteriorId)
                        ->where('fecha', $mov->fecha_operacion)
                        ->where('descripcion', $mov->descripcion)
                        ->delete();
                    SaldosService::recalcularSubcuenta($subcuentaAnteriorId);
                }

                if ($nuevaSubcuentaId) {
                    SubcuentaMovimiento::create([
                        'subcuenta_id' => $nuevaSubcuentaId,
                        'fecha'        => $mov->fecha_operacion,
                        'descripcion'  => $mov->descripcion,
                        'deudor'       => $mov->deudor,
                        'acreedor'     => $mov->acreedor,
                        'saldo'        => 0,
                    ]);
                }
            }

            // 4. Recalcular saldos
            SaldosService::recalcularCuenta($mov->cuenta_general_id);
            
            if ($nuevaSubcuentaId) {
                SaldosService::recalcularSubcuenta($nuevaSubcuentaId);
            }

            return redirect()->route('cuentas.show', $mov->cuenta_general_id)
                ->with('success', 'Movimiento actualizado correctamente.');
        });
    }

    /**
     * Mostrar movimiento (API)
     */
    public function show($id)
    {
        $mov = Movimiento::with('subcuenta', 'cuenta', 'movimientoPendiente', 'movimientoSaldante')->findOrFail($id);
        return response()->json($mov);
    }

    /**
     * Eliminar movimiento
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $mov = Movimiento::findOrFail($id);

            $cuentaId = $mov->cuenta_general_id;
            $subcuentaId = $mov->subcuenta_id;

            // Si este movimiento salda un pendiente, desvincular
            if ($mov->movimiento_pendiente_id) {
                $pendiente = Movimiento::find($mov->movimiento_pendiente_id);
                if ($pendiente) {
                    $pendiente->update([
                        'pendiente_saldado' => false,
                        'movimiento_saldante_id' => null,
                    ]);
                }
            }

            // Si este movimiento es un pendiente que fue saldado, desvincular
            if ($mov->es_pendiente && $mov->movimiento_saldante_id) {
                $saldante = Movimiento::find($mov->movimiento_saldante_id);
                if ($saldante) {
                    $saldante->update([
                        'movimiento_pendiente_id' => null,
                    ]);
                }
            }

            // Eliminar movimiento
            $mov->delete();

            // Manejar subcuenta
            if ($subcuentaId) {
                SubcuentaMovimiento::where('subcuenta_id', $subcuentaId)
                    ->where('fecha', $mov->fecha_operacion)
                    ->where('descripcion', $mov->descripcion)
                    ->delete();
                SaldosService::recalcularSubcuenta($subcuentaId);
            }

            // Recalcular cuenta
            SaldosService::recalcularCuenta($cuentaId);

            return redirect()->back()->with('success', 'Movimiento eliminado correctamente');
        });
    }

    /**
     * API: Marcar/desmarcar como pendiente
     */
/**
 * Marcar/desmarcar como pendiente
 */
    public function togglePendiente($id)
    {
        $mov = Movimiento::findOrFail($id);

        // Si ya es pendiente y está saldado, no se puede desmarcar directamente
        if ($mov->es_pendiente && $mov->pendiente_saldado) {
            return redirect()->back()
                ->with('error', 'No se puede desmarcar un pendiente que ya fue saldado.');
        }

        $nuevoEstado = !$mov->es_pendiente;
        
        $mov->update([
            'es_pendiente' => $nuevoEstado,
            'pendiente_saldado' => false,
            'movimiento_saldante_id' => null,
        ]);

        // Si deja de ser pendiente y tenía un movimiento saldante, desvincular
        if (!$nuevoEstado && $mov->movimiento_saldante_id) {
            $saldante = Movimiento::find($mov->movimiento_saldante_id);
            if ($saldante) {
                $saldante->update(['movimiento_pendiente_id' => null]);
            }
        }

        // Recalcular la cuenta después de cambiar el estado
        SaldosService::recalcularCuenta($mov->cuenta_general_id);

        return redirect()->back()
            ->with('success', $nuevoEstado 
                ? 'Movimiento marcado como pendiente' 
                : 'Movimiento desmarcado como pendiente');
    }

    /**
     * API: Obtener pendientes activos de una cuenta
     */
    public function pendientesActivos($cuentaId)
    {
        $pendientes = Movimiento::where('cuenta_general_id', $cuentaId)
            ->where('es_pendiente', true)
            ->where('pendiente_saldado', false)
            ->with('subcuenta')
            ->orderBy('fecha_operacion')
            ->orderBy('id')
            ->get();

        return response()->json($pendientes);
    }

    /**
     * API: Saldar un pendiente creando un nuevo movimiento
     */
    public function saldarPendiente(Request $request, $pendienteId)
    {
        $request->validate([
            'fecha_operacion' => 'required|date',
            'descripcion' => 'nullable|string',
            'medio_pago' => 'nullable|string|max:50',
            'subcuenta_id' => 'nullable|exists:subcuentas,id',
        ]);

        return DB::transaction(function () use ($request, $pendienteId) {
            $pendiente = Movimiento::findOrFail($pendienteId);

            if (!$pendiente->esPendienteActivo()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este movimiento no es un pendiente activo.',
                ], 400);
            }

            // Obtener último número
            $ultimoNumero = Movimiento::where('cuenta_general_id', $pendiente->cuenta_general_id)
                ->lockForUpdate()
                ->max('numero') ?? 0;

            $nuevoNumero = $ultimoNumero + 1;

            // Crear movimiento que salda el pendiente
            $movimientoSaldante = Movimiento::create([
                'cuenta_general_id' => $pendiente->cuenta_general_id,
                'numero'            => $nuevoNumero,
                'fecha_operacion'   => $request->fecha_operacion,
                'descripcion'       => $request->descripcion ?? "Saldado pendiente #{$pendiente->numero}",
                'deudor'            => $pendiente->acreedor, // Invertir débito/crédito
                'acreedor'          => $pendiente->deudor,
                'saldo'             => 0,
                'medio_pago'        => $request->medio_pago,
                'subcuenta_id'      => $request->subcuenta_id ?? $pendiente->subcuenta_id,
                'es_pendiente'      => false,
                'movimiento_pendiente_id' => $pendiente->id,
            ]);

            // Actualizar pendiente
            $pendiente->update([
                'pendiente_saldado' => true,
                'movimiento_saldante_id' => $movimientoSaldante->id,
            ]);

            // Manejar subcuenta
            if ($movimientoSaldante->subcuenta_id) {
                SubcuentaMovimiento::create([
                    'subcuenta_id' => $movimientoSaldante->subcuenta_id,
                    'fecha'        => $movimientoSaldante->fecha_operacion,
                    'descripcion'  => $movimientoSaldante->descripcion,
                    'deudor'       => $movimientoSaldante->deudor,
                    'acreedor'     => $movimientoSaldante->acreedor,
                    'saldo'        => 0,
                ]);
                SaldosService::recalcularSubcuenta($movimientoSaldante->subcuenta_id);
            }

            // Recalcular cuenta
            SaldosService::recalcularCuenta($pendiente->cuenta_general_id);

            return response()->json([
                'success' => true,
                'message' => 'Pendiente saldado correctamente',
                'movimiento' => $movimientoSaldante,
            ]);
        });
    }

    /**
     * Método para forzar recálculo de una cuenta
     */
    public function recalcular($cuentaId)
    {
        $saldoFinal = SaldosService::recalcularCuenta($cuentaId);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta recalculada correctamente',
            'saldo_final' => $saldoFinal
        ]);
    }
}