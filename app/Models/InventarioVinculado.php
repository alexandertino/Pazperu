<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventarioVinculado extends Model
{
    use HasFactory;

    protected $table = 'inventarios_vinculados';

    protected $fillable = [
        'proyecto_id',
        'am_table',
        'am_row_id',
        'inventario_table',
        'inventario_row_id',
        'codigo',
        'descripcion',
        'fecha',
        'cantidad',
        'meta',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'fecha' => 'date',
    ];

    // Relación con Proyecto (si existe modelo Proyecto)
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    // Helper: obtener inventario dinámico (opcional)
    public function inventario()
    {
        // método placeholder — no Eloquent directo porque la tabla es dinámica.
        return null;
    }

    // Puedes añadir scopes para estados comunes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLinked($query)
    {
        return $query->where('status', 'linked');
    }
}
