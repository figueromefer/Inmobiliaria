<?php
// app/Http/Requests/TicketUpdateRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'property_id' => ['sometimes','exists:propiedades,pk_propiedad'],
            'title' => ['sometimes','string','max:180'],
            'description' => ['sometimes','nullable','string'],
            'priority' => ['sometimes','nullable','in:low,medium,high'],
            'assigned_to' => ['sometimes','nullable','exists:users,id'],
            'due_date' => ['sometimes','nullable','date'],
            'status' => ['sometimes','in:open,in_progress,completed,canceled'],
        ];
    }
}
