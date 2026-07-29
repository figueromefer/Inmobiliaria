<?php
// app/Models/MaintenanceTicket.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id','created_by','assigned_to',
        'title','description','status','priority','due_date','closed_at'
    ];

    protected $casts = [
        'due_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    public function property() {
        return $this->belongsTo(Propiedad::class, 'property_id', 'pk_propiedad')->withTrashed();
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments() {
        return $this->hasMany(MaintenanceComment::class, 'ticket_id')->latest();
    }
}
