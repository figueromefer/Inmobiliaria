<?php
// app/Models/MaintenanceComment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceComment extends Model
{
    protected $fillable = ['ticket_id','user_id','body','attachments'];

    protected $casts = ['attachments' => 'array'];

    public function ticket() {
        return $this->belongsTo(MaintenanceTicket::class);
    }

    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
