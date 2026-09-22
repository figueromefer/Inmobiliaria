<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-records') ?? false;
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'array'],
            'expected_version_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
