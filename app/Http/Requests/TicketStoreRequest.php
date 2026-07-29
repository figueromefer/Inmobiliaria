<?php 
// app/Http/Requests/TicketStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // o Policy
    public function rules(): array {
        return [
            'property_id' => ['required', Rule::exists('propiedades', 'pk_propiedad')->whereNull('deleted_at')],
            'title' => ['required','string','max:180'],
            'description' => ['nullable','string'],
            'priority' => ['nullable','in:low,medium,high'],
            'assigned_to' => ['nullable','exists:users,id'],
            'due_date' => ['nullable','date'],
        ];
    }
}
