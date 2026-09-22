@php
    $get = fn (string $path, mixed $default = null) => old('payload.'.str_replace('.', '.', $path), data_get($payload, $path, $default));
    $uses = $get('leased_property.property_use_codes', []);
@endphp

@if ($errors->any())
    <div class="rounded border border-red-300 bg-red-50 p-4 text-sm text-red-700">Revisa los campos técnicos marcados. Los requisitos jurídicos definitivos aún no se validan en esta fase.</div>
@endif

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Metadatos</h3>
    <div><label class="block text-sm font-medium">Referencia interna</label><input name="payload[metadata][contract_reference]" value="{{ $get('metadata.contract_reference') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.metadata.contract_reference')" /></div>
    <div><label class="block text-sm font-medium">Fecha de firma</label><input type="date" name="payload[metadata][contract_date]" value="{{ $get('metadata.contract_date') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.metadata.contract_date')" /></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Arrendador</h3>
    <div><label class="block text-sm font-medium">Tipo de persona</label><select name="payload[lessor][person][person_type]" class="mt-1 w-full border rounded px-3 py-2"><option value="fisica" @selected($get('lessor.person.person_type') === 'fisica')>Persona física</option><option value="moral" @selected($get('lessor.person.person_type') === 'moral')>Persona moral</option></select></div>
    <div><label class="block text-sm font-medium">Nombre / razón social</label><input name="payload[lessor][person][full_name]" value="{{ $get('lessor.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">RFC</label><input name="payload[lessor][person][rfc]" value="{{ $get('lessor.person.rfc') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Correo</label><input type="email" name="payload[lessor][person][email]" value="{{ $get('lessor.person.email') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Arrendatario</h3>
    <div><label class="block text-sm font-medium">Tipo de persona</label><select name="payload[lessee][person][person_type]" class="mt-1 w-full border rounded px-3 py-2"><option value="fisica" @selected($get('lessee.person.person_type') === 'fisica')>Persona física</option><option value="moral" @selected($get('lessee.person.person_type') === 'moral')>Persona moral</option></select></div>
    <div><label class="block text-sm font-medium">Nombre / razón social</label><input name="payload[lessee][person][full_name]" value="{{ $get('lessee.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">RFC</label><input name="payload[lessee][person][rfc]" value="{{ $get('lessee.person.rfc') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Correo</label><input type="email" name="payload[lessee][person][email]" value="{{ $get('lessee.person.email') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Tercero / fiador</h3>
    <div><label class="block text-sm font-medium">Tipo de tercero</label><select id="guarantor-type" name="payload[guarantor][type]" class="mt-1 w-full border rounded px-3 py-2"><option value="none" @selected($get('guarantor.type') === 'none')>Sin tercero</option><option value="fisica" @selected($get('guarantor.type') === 'fisica')>Persona física</option><option value="moral" @selected($get('guarantor.type') === 'moral')>Persona moral</option></select></div>
    <div><label class="block text-sm font-medium">Nombre / razón del tercero</label><input name="payload[guarantor][person][full_name]" value="{{ $get('guarantor.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <input id="guarantor-person-type" type="hidden" name="payload[guarantor][person][person_type]" value="{{ $get('guarantor.type') === 'moral' ? 'moral' : 'fisica' }}">
    <div><label class="block text-sm font-medium">Inmueble en garantía</label><select name="payload[guarantee_property][exists]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('guarantee_property.exists') === 'no')>No</option><option value="yes" @selected($get('guarantee_property.exists') === 'yes')>Sí</option></select></div>
    <div><label class="block text-sm font-medium">Domicilio de garantía</label><input name="payload[guarantee_property][address]" value="{{ $get('guarantee_property.address') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Inmueble, vigencia e importes</h3>
    <div><label class="block text-sm font-medium">Alias del inmueble</label><input name="payload[leased_property][alias]" value="{{ $get('leased_property.alias') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Domicilio</label><input name="payload[leased_property][address]" value="{{ $get('leased_property.address') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div class="md:col-span-2"><span class="block text-sm font-medium">Uso del inmueble</span><input type="hidden" name="payload[leased_property][_property_use_codes_submitted]" value="1"><div class="mt-2 flex flex-wrap gap-4 text-sm">@foreach(['residential' => 'Residencial', 'industrial' => 'Industrial', 'commercial' => 'Comercial', 'other' => 'Otro'] as $code => $label)<label><input type="checkbox" name="payload[leased_property][property_use_codes][]" value="{{ $code }}" @checked(in_array($code, $uses, true))> {{ $label }}</label>@endforeach</div></div>
    <div><label class="block text-sm font-medium">Inicio</label><input type="date" name="payload[term][start_date]" value="{{ $get('term.start_date') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Fin</label><input type="date" name="payload[term][end_date]" value="{{ $get('term.end_date') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Regla de pago (texto literal)</label><input name="payload[term][rent_due_rule][raw_text]" value="{{ $get('term.rent_due_rule.raw_text') }}" placeholder="Ej. 05 a 10" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Renta mensual</label><input inputmode="decimal" name="payload[amounts][monthly_rent]" value="{{ $get('amounts.monthly_rent') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Depósito</label><input inputmode="decimal" name="payload[amounts][security_deposit]" value="{{ $get('amounts.security_deposit') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Pago, mantenimiento y renovación</h3>
    <div><label class="block text-sm font-medium">Forma de pago</label><select name="payload[payment][method]" class="mt-1 w-full border rounded px-3 py-2"><option value="unspecified" @selected($get('payment.method') === 'unspecified')>No especificada</option><option value="cash" @selected($get('payment.method') === 'cash')>Efectivo</option><option value="bank_transfer" @selected($get('payment.method') === 'bank_transfer')>Transferencia</option></select></div>
    <div><label class="block text-sm font-medium">Banco</label><input name="payload[payment][bank_name]" value="{{ $get('payment.bank_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium">Mantenimiento</label><select name="payload[maintenance][exists]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('maintenance.exists') === 'no')>No</option><option value="yes" @selected($get('maintenance.exists') === 'yes')>Sí</option></select></div>
    <div><label class="block text-sm font-medium">Quién paga mantenimiento</label><select name="payload[maintenance][payer]" class="mt-1 w-full border rounded px-3 py-2"><option value="">—</option><option value="lessor" @selected($get('maintenance.payer') === 'lessor')>Arrendador</option><option value="lessee" @selected($get('maintenance.payer') === 'lessee')>Arrendatario</option></select></div>
    <div><label class="block text-sm font-medium">¿Es renovación?</label><select name="payload[renewal][is_renewal]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('renewal.is_renewal') === 'no')>No</option><option value="yes" @selected($get('renewal.is_renewal') === 'yes')>Sí</option></select></div>
    <div><label class="block text-sm font-medium">Contrato previo (ID, opcional)</label><input inputmode="numeric" name="payload[renewal][previous_contract_id]" value="{{ $get('renewal.previous_contract_id') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
</section>

<div class="flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">{{ $submitLabel }}</button>
    <a href="{{ isset($draft) ? route('contratos.borradores.show', $draft) : route('contratos.borradores.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Cancelar</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('guarantor-type');
        const personType = document.getElementById('guarantor-person-type');
        if (!type || !personType) return;
        type.addEventListener('change', function () {
            personType.value = type.value === 'moral' ? 'moral' : 'fisica';
        });
    });
</script>
