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
    <div><label class="block text-sm font-medium">Tipo de persona</label><select data-party-type="lessor" name="payload[lessor][person][person_type]" class="mt-1 w-full border rounded px-3 py-2"><option value="fisica" @selected($get('lessor.person.person_type') === 'fisica')>Persona física</option><option value="moral" @selected($get('lessor.person.person_type') === 'moral')>Persona moral</option></select><x-input-error :messages="$errors->get('payload.lessor.person.person_type')" /></div>
    <div data-party-physical="lessor"><label class="block text-sm font-medium">Nombre completo</label><input name="payload[lessor][person][full_name]" value="{{ $get('lessor.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessor.person.full_name')" /></div>
    <div data-party-moral="lessor"><label class="block text-sm font-medium">Razón social</label><input name="payload[lessor][person][legal_name]" value="{{ $get('lessor.person.legal_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessor.person.legal_name')" /></div>
    <div><label class="block text-sm font-medium">RFC</label><input name="payload[lessor][person][rfc]" value="{{ $get('lessor.person.rfc') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessor.person.rfc')" /></div>
    <div><label class="block text-sm font-medium">Correo</label><input type="email" name="payload[lessor][person][email]" value="{{ $get('lessor.person.email') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessor.person.email')" /></div>
    <div data-party-moral="lessor"><label class="block text-sm font-medium">Representante legal</label><input name="payload[lessor][representative][full_name]" value="{{ $get('lessor.representative.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessor.representative.full_name')" /></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Arrendatario</h3>
    <div><label class="block text-sm font-medium">Tipo de persona</label><select data-party-type="lessee" name="payload[lessee][person][person_type]" class="mt-1 w-full border rounded px-3 py-2"><option value="fisica" @selected($get('lessee.person.person_type') === 'fisica')>Persona física</option><option value="moral" @selected($get('lessee.person.person_type') === 'moral')>Persona moral</option></select><x-input-error :messages="$errors->get('payload.lessee.person.person_type')" /></div>
    <div data-party-physical="lessee"><label class="block text-sm font-medium">Nombre completo</label><input name="payload[lessee][person][full_name]" value="{{ $get('lessee.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessee.person.full_name')" /></div>
    <div data-party-moral="lessee"><label class="block text-sm font-medium">Razón social</label><input name="payload[lessee][person][legal_name]" value="{{ $get('lessee.person.legal_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessee.person.legal_name')" /></div>
    <div><label class="block text-sm font-medium">RFC</label><input name="payload[lessee][person][rfc]" value="{{ $get('lessee.person.rfc') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessee.person.rfc')" /></div>
    <div><label class="block text-sm font-medium">Correo</label><input type="email" name="payload[lessee][person][email]" value="{{ $get('lessee.person.email') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessee.person.email')" /></div>
    <div data-party-moral="lessee"><label class="block text-sm font-medium">Representante legal</label><input name="payload[lessee][representative][full_name]" value="{{ $get('lessee.representative.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.lessee.representative.full_name')" /></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Tercero / fiador</h3>
    <div><label class="block text-sm font-medium">Tipo de tercero</label><select id="guarantor-type" name="payload[guarantor][type]" class="mt-1 w-full border rounded px-3 py-2"><option value="none" @selected($get('guarantor.type') === 'none')>Sin tercero</option><option value="fisica" @selected($get('guarantor.type') === 'fisica')>Persona física</option><option value="moral" @selected($get('guarantor.type') === 'moral')>Persona moral</option></select><x-input-error :messages="$errors->get('payload.guarantor.type')" /></div>
    <div data-guarantor-person="fisica"><label class="block text-sm font-medium">Nombre completo del tercero</label><input name="payload[guarantor][person][full_name]" value="{{ $get('guarantor.person.full_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.guarantor.person.full_name')" /></div>
    <div data-guarantor-person="moral"><label class="block text-sm font-medium">Razón social del tercero</label><input name="payload[guarantor][person][legal_name]" value="{{ $get('guarantor.person.legal_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.guarantor.person.legal_name')" /></div>
    <input id="guarantor-person-type" type="hidden" name="payload[guarantor][person][person_type]" value="{{ $get('guarantor.type') === 'moral' ? 'moral' : 'fisica' }}">
    <div><label class="block text-sm font-medium">Inmueble en garantía</label><select id="guarantee-exists" name="payload[guarantee_property][exists]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('guarantee_property.exists') === 'no')>No</option><option value="yes" @selected($get('guarantee_property.exists') === 'yes')>Sí</option></select><x-input-error :messages="$errors->get('payload.guarantee_property.exists')" /></div>
    <div data-guarantee-field><label class="block text-sm font-medium">Domicilio de garantía</label><input name="payload[guarantee_property][address]" value="{{ $get('guarantee_property.address') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.guarantee_property.address')" /></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Inmueble, vigencia e importes</h3>
    <div><label class="block text-sm font-medium">Alias del inmueble</label><input name="payload[leased_property][alias]" value="{{ $get('leased_property.alias') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.leased_property.alias')" /></div>
    <div><label class="block text-sm font-medium">Domicilio</label><input name="payload[leased_property][address]" value="{{ $get('leased_property.address') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.leased_property.address')" /></div>
    <div class="md:col-span-2"><span class="block text-sm font-medium">Uso del inmueble</span><input type="hidden" name="payload[leased_property][_property_use_codes_submitted]" value="1"><div class="mt-2 flex flex-wrap gap-4 text-sm">@foreach(['residential' => 'Residencial', 'industrial' => 'Industrial', 'commercial' => 'Comercial', 'other' => 'Otro'] as $code => $label)<label><input type="checkbox" name="payload[leased_property][property_use_codes][]" value="{{ $code }}" @checked(in_array($code, $uses, true))> {{ $label }}</label>@endforeach</div><x-input-error :messages="$errors->get('payload.leased_property.property_use_codes')" /></div>
    <div><label class="block text-sm font-medium">Inicio</label><input type="date" name="payload[term][start_date]" value="{{ $get('term.start_date') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.term.start_date')" /></div>
    <div><label class="block text-sm font-medium">Fin</label><input type="date" name="payload[term][end_date]" value="{{ $get('term.end_date') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.term.end_date')" /></div>
    <div><label class="block text-sm font-medium">Regla de pago (texto literal)</label><input name="payload[term][rent_due_rule][raw_text]" value="{{ $get('term.rent_due_rule.raw_text') }}" placeholder="Ej. 05 a 10" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.term.rent_due_rule.raw_text')" /></div>
    <div><label class="block text-sm font-medium">Renta mensual</label><input inputmode="decimal" name="payload[amounts][monthly_rent]" value="{{ $get('amounts.monthly_rent') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.amounts.monthly_rent')" /></div>
    <div><label class="block text-sm font-medium">Depósito</label><input inputmode="decimal" name="payload[amounts][security_deposit]" value="{{ $get('amounts.security_deposit') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.amounts.security_deposit')" /></div>
</section>

<section class="grid gap-4 md:grid-cols-2 border rounded-lg p-4">
    <h3 class="md:col-span-2 font-semibold text-gray-800">Pago, mantenimiento y renovación</h3>
    <div><label class="block text-sm font-medium">Forma de pago</label><select id="payment-method" name="payload[payment][method]" class="mt-1 w-full border rounded px-3 py-2"><option value="unspecified" @selected($get('payment.method') === 'unspecified')>No especificada</option><option value="cash" @selected($get('payment.method') === 'cash')>Efectivo</option><option value="bank_transfer" @selected($get('payment.method') === 'bank_transfer')>Transferencia</option></select><x-input-error :messages="$errors->get('payload.payment.method')" /></div>
    <div data-bank-field><label class="block text-sm font-medium">Banco</label><input name="payload[payment][bank_name]" value="{{ $get('payment.bank_name') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.payment.bank_name')" /></div>
    <div><label class="block text-sm font-medium">Mantenimiento</label><select id="maintenance-exists" name="payload[maintenance][exists]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('maintenance.exists') === 'no')>No</option><option value="yes" @selected($get('maintenance.exists') === 'yes')>Sí</option></select><x-input-error :messages="$errors->get('payload.maintenance.exists')" /></div>
    <div data-maintenance-payer><label class="block text-sm font-medium">Quién paga mantenimiento</label><select name="payload[maintenance][payer]" class="mt-1 w-full border rounded px-3 py-2"><option value="">—</option><option value="lessor" @selected($get('maintenance.payer') === 'lessor')>Arrendador</option><option value="lessee" @selected($get('maintenance.payer') === 'lessee')>Arrendatario</option></select><x-input-error :messages="$errors->get('payload.maintenance.payer')" /></div>
    <div><label class="block text-sm font-medium">¿Es renovación?</label><select id="renewal-is-renewal" name="payload[renewal][is_renewal]" class="mt-1 w-full border rounded px-3 py-2"><option value="no" @selected($get('renewal.is_renewal') === 'no')>No</option><option value="yes" @selected($get('renewal.is_renewal') === 'yes')>Sí</option></select><x-input-error :messages="$errors->get('payload.renewal.is_renewal')" /></div>
    <div data-renewal-field><label class="block text-sm font-medium">Contrato previo (ID, opcional)</label><input inputmode="numeric" name="payload[renewal][previous_contract_id]" value="{{ $get('renewal.previous_contract_id') }}" class="mt-1 w-full border rounded px-3 py-2"><x-input-error :messages="$errors->get('payload.renewal.previous_contract_id')" /></div>
</section>

<div class="flex gap-3">
    <button type="submit" style="background:#2563eb;color:#fff" class="inline-flex items-center rounded-lg px-4 py-2 font-bold hover:bg-blue-700">{{ $submitLabel }}</button>
    <a href="{{ isset($draft) ? route('contratos.borradores.show', $draft) : route('contratos.borradores.index') }}" style="background:#6b7280;color:#fff" class="inline-flex items-center rounded-lg px-4 py-2 font-bold hover:bg-gray-700">Cancelar</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const setApplicable = (selector, enabled) => document.querySelectorAll(selector).forEach(function (field) {
            field.hidden = !enabled;
            field.querySelectorAll('input, select, textarea').forEach(function (input) { input.disabled = !enabled; });
        });
        document.querySelectorAll('[data-party-type]').forEach(function (select) {
            const sync = function () {
                setApplicable('[data-party-physical="' + select.dataset.partyType + '"]', select.value === 'fisica');
                setApplicable('[data-party-moral="' + select.dataset.partyType + '"]', select.value === 'moral');
            };
            select.addEventListener('change', sync); sync();
        });
        const guarantorType = document.getElementById('guarantor-type');
        const guarantorPersonType = document.getElementById('guarantor-person-type');
        const syncGuarantor = function () {
            if (!guarantorType || !guarantorPersonType) return;
            guarantorPersonType.disabled = guarantorType.value === 'none';
            guarantorPersonType.value = guarantorType.value === 'moral' ? 'moral' : 'fisica';
            setApplicable('[data-guarantor-person="fisica"]', guarantorType.value === 'fisica');
            setApplicable('[data-guarantor-person="moral"]', guarantorType.value === 'moral');
        };
        guarantorType?.addEventListener('change', syncGuarantor); syncGuarantor();
        const conditional = [
            ['guarantee-exists', '[data-guarantee-field]', 'yes'],
            ['payment-method', '[data-bank-field]', 'bank_transfer'],
            ['maintenance-exists', '[data-maintenance-payer]', 'yes'],
            ['renewal-is-renewal', '[data-renewal-field]', 'yes'],
        ];
        conditional.forEach(function ([id, selector, value]) {
            const select = document.getElementById(id);
            if (!select) return;
            const sync = function () { setApplicable(selector, select.value === value); };
            select.addEventListener('change', sync); sync();
        });
    });
</script>
