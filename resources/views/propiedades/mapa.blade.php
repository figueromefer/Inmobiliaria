<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<style>
        #map {
            height: 90vh;
            width: 100%;
        }
    </style>
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">Mapa</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div id="map"></div>
                <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
                <script>
                    const propiedades = @json($propiedades);

                    // Inicializar el mapa centrado en una ubicación genérica
                    const map = L.map('map').setView([20.65, -103.37], 11); // Centro Guadalajara

                    // Capa de OpenStreetMap
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    // Agregar pines al mapa
                    propiedades.forEach(p => {
                        if (p.latitud && p.longitud) {
                            L.marker([p.latitud, p.longitud])
                                .addTo(map)
                                .bindPopup(`<strong>${p.alias}</strong><br>${p.domicilio}`);
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</x-app-layout>
