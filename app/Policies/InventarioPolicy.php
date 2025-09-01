<?php

namespace App\Policies;

use App\Models\User;

class InventarioPolicy
{
    public function viewAny(User $user)
    {
        // Todos pueden ver
        return true;
    }

    public function create(User $user)
    {
        // Solo admin puede crear
        return $user->role === 'admin';
    }

    public function update(User $user)
    {
        // Solo admin puede editar
        return $user->role === 'admin';
    }

    public function delete(User $user)
    {
        // Solo admin puede borrar
        return $user->role === 'admin';
    }
}
