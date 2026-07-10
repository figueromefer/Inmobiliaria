<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Propiedad</h2>
    </x-slot>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                <p class="font-semibold mb-2">No se pudo guardar la propiedad. Revisa estos campos:</p>
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('propiedades.update', $propiedad) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="fk_cliente" class="block font-medium text-sm text-gray-700">Cliente</label>
                    <select name="fk_cliente" id="fk_cliente" class="js-searchable-select form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="">Seleccione un cliente</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->pk_cliente }}" {{ old('fk_cliente', $propiedad->fk_cliente) == $cliente->pk_cliente ? 'selected' : '' }}>
                                {{ $cliente->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('fk_cliente') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="alias" class="block font-medium text-sm text-gray-700">Alias</label>
                    <input type="text" name="alias" id="alias" value="{{ old('alias', $propiedad->alias) }}" class="form-input mt-1 block w-full" />
                    @error('alias') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-3">Domicilio</h3>
                    <input type="text" name="calle" value="{{ old('calle', $propiedad->calle) }}" placeholder="Calle" class="js-address-field form-input mb-2 w-full" />
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="numero_exterior" value="{{ old('numero_exterior', $propiedad->numero_exterior) }}" placeholder="No. Exterior" class="js-address-field form-input w-full" />
                        <input type="text" name="numero_interior" value="{{ old('numero_interior', $propiedad->numero_interior) }}" placeholder="No. Interior" class="js-address-field form-input w-full" />
                    </div>
                    <input type="text" name="colonia" value="{{ old('colonia', $propiedad->colonia) }}" placeholder="Colonia" class="js-address-field form-input mt-2 w-full" />
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <input type="text" name="codigo_postal" value="{{ old('codigo_postal', $propiedad->codigo_postal) }}" placeholder="Código Postal" class="js-address-field form-input w-full" />
                        <input type="text" name="municipio" value="{{ old('municipio', $propiedad->municipio) }}" placeholder="Municipio" class="js-address-field form-input w-full" />
                    </div>
                    <input type="text" name="estado" value="{{ old('estado', $propiedad->estado) }}" placeholder="Estado" class="js-address-field form-input mt-2 w-full" />
                </div>

                <div>
                    <label for="siapa" class="block font-medium text-sm text-gray-700">Agua</label>
                    <input type="text" name="siapa" id="siapa" value="{{ old('siapa', $propiedad->siapa) }}" class="form-input mt-1 block w-full" />
                    @error('siapa') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cfe" class="block font-medium text-sm text-gray-700">CFE</label>
                    <input type="text" name="cfe" id="cfe" value="{{ old('cfe', $propiedad->cfe) }}" class="form-input mt-1 block w-full" />
                    @error('cfe') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="predial" class="block font-medium text-sm text-gray-700">Predial</label>
                    <input type="text" name="predial" id="predial" value="{{ old('predial', $propiedad->predial) }}" class="form-input mt-1 block w-full" />
                    @error('predial') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Datos para mantenimiento</h3>
                    @php($mantenimientoDiaPago = $propiedad->mantenimiento_fecha_pago ? $propiedad->mantenimiento_fecha_pago->day : null)
                    <input type="text" name="mantenimiento_banco" placeholder="Banco" value="{{ old('mantenimiento_banco', $propiedad->mantenimiento_banco) }}" class="form-input mb-2 w-full" />
                    <input type="text" name="mantenimiento_cuenta" placeholder="Cuenta" value="{{ old('mantenimiento_cuenta', $propiedad->mantenimiento_cuenta) }}" class="form-input mb-2 w-full" />
                    <input type="text" name="referencia" placeholder="Referencia" value="{{ old('referencia', $propiedad->referencia) }}" class="form-input mb-2 w-full" />
                    @error('referencia') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
                    <div class="mb-2">
                        <input type="text" name="clabe" placeholder="CLABE" value="{{ old('clabe', $propiedad->clabe) }}" inputmode="numeric" maxlength="23" pattern="[0-9 ]*" class="form-input w-full" />
                        <p class="text-xs text-gray-500 mt-1">18 dígitos.</p>
                        @error('clabe') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <input type="text" name="mantenimiento_monto" inputmode="decimal" placeholder="Monto" value="{{ old('mantenimiento_monto', $propiedad->mantenimiento_monto) }}" class="js-money-input form-input mb-2 w-full" />
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Día de pago de mantenimiento</label>
                        <input type="number" name="mantenimiento_fecha_pago" min="1" max="31" step="1" placeholder="Ej. 5" value="{{ old('mantenimiento_fecha_pago', $mantenimientoDiaPago) }}" class="js-day-of-month-input form-input w-full" />
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Ubicación</h3>
                    <p class="text-sm text-gray-500 mb-3">Actualiza desde el domicilio para proponer el pin. También puedes hacer clic en el mapa o arrastrar el pin para ajustar manualmente.</p>
                    <input type="hidden" name="coordenadas_manual" id="coordenadas_manual" value="{{ old('coordenadas_manual', '0') }}">
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <button type="button" id="geocode-address" class="bg-gray-800 hover:bg-gray-700 text-white px-3 py-2 rounded text-sm">Actualizar ubicación desde domicilio</button>
                        <span id="geocode-status" class="text-sm text-gray-500"></span>
                    </div>
                    <div id="property-map" class="w-full rounded border" style="height: 360px;"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label>Latitud</label>
                            <input id="latitud" type="text" name="latitud" value="{{ old('latitud', $propiedad->latitud) }}" class="form-input w-full" readonly />
                            @error('latitud') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label>Longitud</label>
                            <input id="longitud" type="text" name="longitud" value="{{ old('longitud', $propiedad->longitud) }}" class="form-input w-full" readonly />
                            @error('longitud') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Estatus de información</label>
                    <select name="estatus_informacion" class="form-select mt-1 w-full">
                        <option value="pendiente_critico" {{ old('estatus_informacion', $propiedad->estatus_informacion) === 'pendiente_critico' ? 'selected' : '' }}>🔴 Pendiente crítico</option>
                        <option value="pendiente" {{ old('estatus_informacion', $propiedad->estatus_informacion) === 'pendiente' ? 'selected' : '' }}>🟠 Pendiente</option>
                        @if(old('estatus_informacion', $propiedad->estatus_informacion) === 'pendiente_completar')
                            <option value="pendiente_completar" selected>Pendiente de completar</option>
                        @endif
                        <option value="completo" {{ old('estatus_informacion', $propiedad->estatus_informacion) === 'completo' ? 'selected' : '' }}>🟢 Completo</option>
                    </select>
                    @error('estatus_informacion') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('propiedades.show', $propiedad) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Cancelar</a>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded">Guardar</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const latInput = document.getElementById('latitud');
            const lngInput = document.getElementById('longitud');
            const manualInput = document.getElementById('coordenadas_manual');
            const geocodeButton = document.getElementById('geocode-address');
            const geocodeStatus = document.getElementById('geocode-status');
            const hasInitialCoordinates = latInput.value !== '' && lngInput.value !== '';
            const defaultLat = hasInitialCoordinates ? parseFloat(latInput.value) : 20.6597;
            const defaultLng = hasInitialCoordinates ? parseFloat(lngInput.value) : -103.3496;
            const map = L.map('property-map').setView([defaultLat, defaultLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
            function setCoordinates(lat, lng, manual) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], Math.max(map.getZoom(), 15));
                if (manual) {
                    manualInput.value = '1';
                }
            }
            map.on('click', function (event) {
                setCoordinates(event.latlng.lat, event.latlng.lng, true);
            });
            marker.on('dragend', function () {
                const position = marker.getLatLng();
                setCoordinates(position.lat, position.lng, true);
            });

            function addressValue(name) {
                return (form.querySelector('[name="' + name + '"]')?.value || '').trim();
            }

            function buildAddress() {
                const street = [
                    addressValue('calle'),
                    addressValue('numero_exterior'),
                    addressValue('numero_interior') ? 'Int. ' + addressValue('numero_interior') : ''
                ].filter(Boolean).join(' ');

                return [
                    street,
                    addressValue('colonia') ? 'Col. ' + addressValue('colonia') : '',
                    addressValue('codigo_postal') ? 'CP ' + addressValue('codigo_postal') : '',
                    addressValue('municipio'),
                    addressValue('estado')
                ].filter(Boolean).join(', ');
            }

            function geocodingQuery(address) {
                const normalized = address.toLowerCase();
                return normalized.includes('jalisco') || normalized.includes('mexico')
                    ? address
                    : address + ', Jalisco, México';
            }

            geocodeButton.addEventListener('click', async function () {
                const address = buildAddress();

                if (!address) {
                    geocodeStatus.textContent = 'Captura el domicilio antes de buscar.';
                    return;
                }

                geocodeButton.disabled = true;
                geocodeStatus.textContent = 'Buscando ubicación...';

                try {
                    const params = new URLSearchParams({
                        q: geocodingQuery(address),
                        format: 'jsonv2',
                        limit: '1',
                        addressdetails: '0'
                    });
                    const response = await fetch('https://nominatim.openstreetmap.org/search?' + params.toString());
                    const results = await response.json();
                    const result = results[0];

                    if (!result || !result.lat || !result.lon) {
                        geocodeStatus.textContent = 'No se encontró ubicación para ese domicilio.';
                        return;
                    }

                    setCoordinates(parseFloat(result.lat), parseFloat(result.lon), false);
                    manualInput.value = '0';
                    geocodeStatus.textContent = 'Ubicación actualizada desde domicilio.';
                } catch (error) {
                    geocodeStatus.textContent = 'No se pudo consultar la ubicación.';
                } finally {
                    geocodeButton.disabled = false;
                }
            });
        });
    </script>
</x-app-layout>
