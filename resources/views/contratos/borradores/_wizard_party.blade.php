@php($person = data_get($payload, $party.'.person', []))
@php($representative = data_get($payload, $party.'.representative', []))
@php($type = data_get($person, 'person_type', 'fisica'))
<section class="contract-party space-y-5" data-party-container="{{ $party }}">
    @if(!($hidePartyType ?? false))
        <div><label class="block text-sm font-medium" for="{{ $party }}-person-type">Tipo de persona</label><select id="{{ $party }}-person-type" data-party-type="{{ $party }}" name="payload[{{ $party }}][person][person_type]" class="mt-1 w-full border rounded px-3 py-2"><option value="fisica" @selected($type === 'fisica')>Persona física</option><option value="moral" @selected($type === 'moral')>Persona moral</option></select></div>
    @endif
    <div data-party-branch="{{ $party }}" data-type="fisica" class="field-grid grid gap-4 md:grid-cols-2">
        <div><label class="block text-sm font-medium">Nombre completo</label><input name="payload[{{ $party }}][person][full_name]" value="{{ data_get($person, 'full_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">RFC</label><input name="payload[{{ $party }}][person][rfc]" value="{{ data_get($person, 'rfc') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Nacionalidad</label><input name="payload[{{ $party }}][person][nationality]" value="{{ data_get($person, 'nationality') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Lugar de nacimiento</label><input name="payload[{{ $party }}][person][birth_place]" value="{{ data_get($person, 'birth_place') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Fecha de nacimiento</label><input type="date" name="payload[{{ $party }}][person][birth_date]" value="{{ data_get($person, 'birth_date') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Estado civil</label><input name="payload[{{ $party }}][person][marital_status]" value="{{ data_get($person, 'marital_status') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Ocupación</label><input name="payload[{{ $party }}][person][occupation]" value="{{ data_get($person, 'occupation') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">Identificación</label><input name="payload[{{ $party }}][person][identification_type]" value="{{ data_get($person, 'identification_type') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    </div>
    <div data-party-branch="{{ $party }}" data-type="moral" class="field-grid grid gap-4 md:grid-cols-2">
        <div><label class="block text-sm font-medium">Razón social</label><input name="payload[{{ $party }}][person][legal_name]" value="{{ data_get($person, 'legal_name') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm font-medium">RFC</label><input name="payload[{{ $party }}][person][rfc]" value="{{ data_get($person, 'rfc') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        <div class="field-wide md:col-span-2"><label class="block text-sm font-medium">Acta constitutiva</label><input name="payload[{{ $party }}][person][incorporation_deed]" value="{{ data_get($person, 'incorporation_deed') }}" class="mt-1 w-full border rounded px-3 py-2"></div>
    </div>
    <div class="party-common field-grid grid gap-4 md:grid-cols-2"><div class="field-wide md:col-span-2"><label class="block text-sm font-medium">Domicilio</label><input name="payload[{{ $party }}][person][address]" value="{{ data_get($person, 'address') }}" class="mt-1 w-full border rounded px-3 py-2"></div><div><label class="block text-sm font-medium">Teléfono</label><input name="payload[{{ $party }}][person][phone]" value="{{ data_get($person, 'phone') }}" class="mt-1 w-full border rounded px-3 py-2"></div><div><label class="block text-sm font-medium">Correo</label><input type="email" name="payload[{{ $party }}][person][email]" value="{{ data_get($person, 'email') }}" class="mt-1 w-full border rounded px-3 py-2"></div></div>
    <fieldset data-party-branch="{{ $party }}" data-type="moral" class="field-grid border rounded p-4 grid gap-4 md:grid-cols-2"><legend class="px-1 text-sm font-semibold">Representante</legend>
        @foreach(['full_name' => 'Nombre completo', 'nationality' => 'Nacionalidad', 'birth_place' => 'Lugar de nacimiento', 'birth_date' => 'Fecha de nacimiento', 'occupation' => 'Ocupación', 'address' => 'Domicilio', 'identification_type' => 'Identificación', 'authority_deed' => 'Acta de facultades'] as $field => $label)
            <div @if(in_array($field, ['address', 'authority_deed'], true)) class="field-wide md:col-span-2" @endif><label class="block text-sm font-medium">{{ $label }}</label><input @if($field === 'birth_date') type="date" @endif name="payload[{{ $party }}][representative][{{ $field }}]" value="{{ data_get($representative, $field) }}" class="mt-1 w-full border rounded px-3 py-2"></div>
        @endforeach
    </fieldset>
</section>
