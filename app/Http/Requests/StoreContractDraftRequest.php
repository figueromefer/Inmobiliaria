<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-records') ?? false;
    }

    public function rules(): array
    {
        return ['payload' => ['required', 'array']];
    }
}
