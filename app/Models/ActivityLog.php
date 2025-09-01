<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActivityLog extends Model
{
    use HasFactory;

    // ✅ Permitir asignación masiva
    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'changes',
    ];

    // ✅ Guardar/leer "changes" como JSON
    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
