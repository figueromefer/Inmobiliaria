<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LinkContractDraftEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-records') ?? false;
    }

    public function rules(): array
    {
        $target = match ($this->route('entity')) {
            'cliente' => ['clientes', 'pk_cliente'],
            'propiedad' => ['propiedades', 'pk_propiedad'],
            'inquilino' => ['inquilinos', 'id'],
            default => [null, null],
        };

        $exists = Rule::exists($target[0], $target[1]);
        if (in_array($this->route('entity'), ['cliente', 'propiedad'], true) && Schema::hasColumn($target[0], 'deleted_at')) {
            $exists->whereNull('deleted_at');
        }

        return ['entity_id' => ['required', 'integer', 'min:1', $exists]];
    }
}
