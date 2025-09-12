<?php
// app/Policies/MaintenanceTicketPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\MaintenanceTicket;

class MaintenanceTicketPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, MaintenanceTicket $ticket): bool {
        // Define tu lógica (mismo equipo, misma empresa, es el creador, etc.)
        return true;
    }
    public function create(User $user): bool { return true; }
    public function update(User $user, MaintenanceTicket $ticket): bool {
        // Ej: creador o asignado o rol admin
        return $ticket->created_by === $user->id || $ticket->assigned_to === $user->id || $user->hasRole('admin');
    }
    public function delete(User $user, MaintenanceTicket $ticket): bool {
        return $user->hasRole('admin');
    }
}
