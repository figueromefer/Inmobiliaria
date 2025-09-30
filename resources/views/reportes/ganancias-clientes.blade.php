<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ganancias por Cliente') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <p class="mb-2">{{ __('Año:') }} {{ $year }}</p>
        <canvas id="chartGanancias" class="w-full h-96"></canvas>
    </div>

    {{-- Incluir Chart.js y la configuración del gráfico directamente --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('chartGanancias').getContext('2d');

            // Datos pasados desde el controlador
            const labels = {!! json_encode(collect($series)->pluck('cliente')) !!};
            const dataValues = {!! json_encode(collect($series)->pluck('total')) !!};

            const data = {
                labels: labels,
                datasets: [{
                    label: '{{ __("Ganancias (comisiones) acumuladas") }}',
                    data: dataValues,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                }]
            };

            const config = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    return value.toLocaleString('es-MX', { style:'currency', currency:'MXN' });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: '{{ __("Ganancias (MXN)") }}' }
                        },
                        x: {
                            title: { display: true, text: '{{ __("Cliente") }}' }
                        }
                    }
                }
            };

            new Chart(ctx, config);
        });
    </script>
</x-app-layout>
