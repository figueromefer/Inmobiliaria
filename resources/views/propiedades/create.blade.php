<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva Propiedad</h2>
    </x-slot>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('propiedades.store') }}">
            @csrf

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="fk_cliente" class="block font-medium text-sm text-gray-700">Cliente</label>
                    <select name="fk_cliente" id="fk_cliente" class="js-searchable-select form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="">Seleccione un cliente</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->pk_cliente }}"
                                {{ (old('fk_cliente') ?? $clientePreseleccionado ?? '') == $cliente->pk_cliente ? 'selected' : '' }}>
                                {{ $cliente->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="alias" class="block font-medium text-sm text-gray-700">Alias</label>
                    <input type="text" name="alias" id="alias" value="{{ old('alias') }}" class="form-input mt-1 block w-full" />
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-3">Domicilio</h3>
                    <input type="text" name="calle" placeholder="Calle" class="form-input mb-2 w-full" />
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="numero_exterior" placeholder="No. Exterior" class="form-input w-full" />
                        <input type="text" name="numero_interior" placeholder="No. Interior" class="form-input w-full" />
                    </div>
                    <input type="text" name="colonia" placeholder="Colonia" class="form-input mt-2 w-full" />
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <input type="text" name="codigo_postal" placeholder="Código Postal" class="form-input w-full" />
                        <input type="text" name="municipio" placeholder="Municipio" class="form-input w-full" />
                    </div>
                    <input type="text" name="estado" placeholder="Estado" class="form-input mt-2 w-full" />
                </div>

                <div>
                    <label for="siapa" class="block font-medium text-sm text-gray-700">Agua</label>
                    <input type="text" name="siapa" id="siapa" value="{{ old('siapa') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="cfe" class="block font-medium text-sm text-gray-700">CFE</label>
                    <input type="text" name="cfe" id="cfe" value="{{ old('cfe') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="predial" class="block font-medium text-sm text-gray-700">Predial</label>
                    <input type="text" name="predial" id="predial" value="{{ old('predial') }}" class="form-input mt-1 block w-full" />
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Datos para mantenimiento</h3>
                    <input type="text" name="mantenimiento_banco" placeholder="Banco" value="{{ old('mantenimiento_banco') }}" class="form-input mb-2 w-full" />
                    <input type="text" name="mantenimiento_cuenta" placeholder="Cuenta" value="{{ old('mantenimiento_cuenta') }}" class="form-input mb-2 w-full" />
                    <input type="text" name="mantenimiento_monto" inputmode="decimal" placeholder="Monto" value="{{ old('mantenimiento_monto') }}" class="js-money-input form-input mb-2 w-full" />
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Día de pago de mantenimiento</label>
                        <input type="number" name="mantenimiento_fecha_pago" min="1" max="31" step="1" placeholder="Ej. 5" value="{{ old('mantenimiento_fecha_pago') }}" class="js-day-of-month-input form-input w-full" />
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Ubicación</h3>
                    <p class="text-sm text-gray-500 mb-3">Haz clic en el mapa o arrastra el pin para guardar las coordenadas.</p>
                    <div id="property-map" class="w-full rounded border" style="height: 360px;"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label>Latitud</label>
                            <input id="latitud" type="text" name="latitud" value="{{ old('latitud') }}" class="form-input w-full" readonly />
                        </div>
                        <div>
                            <label>Longitud</label>
                            <input id="longitud" type="text" name="longitud" value="{{ old('longitud') }}" class="form-input w-full" readonly />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Estatus de información</label>
                    <select name="estatus_informacion" class="form-select mt-1 w-full">
                        <option value="pendiente_critico" {{ old('estatus_informacion') === 'pendiente_critico' ? 'selected' : '' }}>🔴 Pendiente crítico</option>
                        <option value="pendiente" {{ old('estatus_informacion') === 'pendiente' ? 'selected' : '' }}>🟠 Pendiente</option>
                        <option value="completo" {{ old('estatus_informacion') === 'completo' ? 'selected' : '' }}>🟢 Completo</option>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded">Guardar</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latInput = document.getElementById('latitud');
            const lngInput = document.getElementById('longitud');
            const defaultLat = parseFloat(latInput.value) || 20.6597;
            const defaultLng = parseFloat(lngInput.value) || -103.3496;
            const map = L.map('property-map').setView([defaultLat, defaultLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
            function setCoordinates(lat, lng) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                marker.setLatLng([lat, lng]);
            }
            setCoordinates(defaultLat, defaultLng);
            map.on('click', function (event) {
                setCoordinates(event.latlng.lat, event.latlng.lng);
            });
            marker.on('dragend', function () {
                const position = marker.getLatLng();
                setCoordinates(position.lat, position.lng);
            });
        });
    </script>
</x-app-layout>
