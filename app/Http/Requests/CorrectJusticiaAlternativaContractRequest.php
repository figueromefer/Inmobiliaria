<?php

namespace App\Http\Requests;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class CorrectJusticiaAlternativaContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-records') ?? false;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'dias_pago' => ['nullable', 'integer', 'between:1,31'],
            'monto_mensual' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'monto_total' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'monto_deposito' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'comision_mensual' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'domicilio_inmueble' => ['nullable', 'string', 'max:2000'],
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $contrato = $this->route('contrato');
            $commission = $this->input('comision_mensual');

            if ($contrato instanceof Contrato && filled($commission)
                && ! Contrato::isValidMonthlyCommission($commission, $contrato->comision_mensual_unidad)) {
                $validator->errors()->add('comision_mensual', 'Captura una comisión mensual positiva compatible con su unidad actual.');
            }
        });
    }
}
