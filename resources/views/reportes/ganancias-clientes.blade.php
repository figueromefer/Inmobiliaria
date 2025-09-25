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
</x-app-layout>


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartGanancias').getContext('2d');
    const data = {
        labels: {!! json_encode(collect($series)->pluck('cliente')) !!},
        datasets: [{
            label: '{{ __("Ganancias (comisiones) acumuladas") }}',
            data: {!! json_encode(collect($series)->pluck('total')) !!},
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };
    const config = {
        type: 'bar',
        data: data,
        options: {
            plugins: {
                legend: { display: true },
                tooltip: { callbacks: { label: ctx => ctx.raw.toLocaleString('es-MX', { style:'currency', currency:'MXN' }) } }
            },
            scales: {
                y: {
                    title: { display: true, text: '{{ __("Ganancias (MXN)") }}' },
                    beginAtZero: true
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
@endpush
