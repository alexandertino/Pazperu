<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AmInventarioLink extends Model
{
    protected $table = 'am_inventario_links';

    protected $fillable = [
        'am_table',
        'am_row_id',
        'proyecto_id',
        'n_acta',
        'inventory_table',
        'inventory_row_id',
        'created_by'
    ];
}
