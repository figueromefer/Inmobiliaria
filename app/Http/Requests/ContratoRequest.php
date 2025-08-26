<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Propiedad;

class ContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajusta según tu lógica de autorización
    }

    public function rules(): array
    {
        return [
            // FKs requeridas
            'fk_cliente'   => ['required','integer','exists:clientes,pk_cliente'],
            'fk_propiedad' => ['required','integer','exists:propiedades,pk_propiedad'],

            // Resto de tus campos (ajusta según los nombres en tu payload)
            'tipo_solicitante'     => ['nullable','string'],
            'tipo_complementaria'  => ['nullable','string'],
            'tipo_tercero'         => ['nullable','string'],
            'solicitante'          => ['nullable','string'],
            'fecha_inicio'         => ['nullable','date'],
            'fecha_fin'            => ['nullable','date','after_or_equal:fecha_inicio'],
            'comision_renta'       => ['nullable','numeric','min:0'],
            'comision_mensual'     => ['nullable','numeric','min:0'],
            'dias_pago'            => ['nullable','integer','min:0'],
            'monto_total'          => ['nullable','numeric','min:0'],
            'monto_mensual'        => ['nullable','numeric','min:0'],
            'monto_deposito'       => ['nullable','numeric','min:0'],
            'edit_url'             => ['nullable','url'],
            'inquilino_id'         => ['nullable','integer','exists:inquilinos,id'],
        ];
    }

    public function withValidator($validator)
    {
        // Comprobación extra: la propiedad debe pertenecer al cliente
        $validator->after(function ($v) {
            $clienteId = $this->input('fk_cliente');
            $propId    = $this->input('fk_propiedad');

            if ($clienteId && $propId) {
                $ok = Propiedad::where('pk_propiedad', $propId)
                               ->where('fk_cliente', $clienteId)
                               ->exists();
                if (!$ok) {
                    $v->errors()->add('fk_propiedad', 'La propiedad no pertenece al cliente seleccionado.');
                }
            }
        });
    }
}
