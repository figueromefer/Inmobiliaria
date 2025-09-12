<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Calendario de contratos') }}
    </h2>
  </x-slot>

  <div class="max-w-7xl mx-auto p-4">
    <div class="mb-3 flex items-center gap-3 text-sm">
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#16a34a"></span> Inicio
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#dc2626"></span> Fin
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#f59e0b"></span> Recordatorio (-30 días)
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#f59e0b"></span> Adeudo mes en curso
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#ef4444"></span> Adeudo con saldo anterior
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background:#8b5cf6"></span> Adeudo mes con cobranza completa
        </span>
    </div>

    <div id="calendar" class="bg-white border rounded p-2"></div>
  </div>

  {{-- FullCalendar (CDN) --}}
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const calendarEl = document.getElementById('calendar');
      const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        height: 'auto',
        navLinks: true,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        // Agrega múltiples fuentes de eventos
        eventSources: [
          {
            url: '{{ route('api.contratos.events') }}',
            failure: function() {
              alert('No se pudieron cargar los contratos.');
            }
          },
          {
            url: '{{ route('api.pagos.events') }}',
            failure: function() {
              alert('No se pudieron cargar los adeudos.');
            }
          },
        ],
        eventClick: function(info) {
          const props = info.event.extendedProps || {};
          let msg = info.event.title;
          if (props.total_a_pagar !== undefined) {
            msg += '\\nTotal a pagar: $' + Number(props.total_a_pagar).toFixed(2);
            if (props.saldo_anterior > 0) {
              msg += '\\nIncluye saldo anterior: $' + Number(props.saldo_anterior).toFixed(2);
            }
          } else {
            msg += '\\n' + (props.domicilio ? ('Domicilio: ' + props.domicilio) : '');
            msg += '\\n' + (props.inquilino ? ('Inquilino: ' + props.inquilino) : '');
            if (props.tipo === 'recordatorio' && props.fecha_fin) {
              msg += '\\nFin: ' + props.fecha_fin;
            }
          }
          alert(msg);
        }
      });
      calendar.render();
    });
  </script>
</x-app-layout>
