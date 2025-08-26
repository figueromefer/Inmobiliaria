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
        <span class="inline-block w-3 h-3 rounded" style="background:#f59e0b"></span> Recordatorio (−30 días)
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
        events: {
          url: '{{ route('api.contratos.events') }}',
          failure: function() {
            alert('No se pudieron cargar los eventos.');
          }
        },
        eventClick: function(info) {
          const p = info.event.extendedProps || {};
          const msg = [
            info.event.title,
            p.domicilio ? ('Domicilio: ' + p.domicilio) : null,
            p.inquilino ? ('Inquilino: ' + p.inquilino) : null,
            p.tipo === 'recordatorio' && p.fecha_fin ? ('Fin: ' + p.fecha_fin) : null,
          ].filter(Boolean).join('\n');
          alert(msg || info.event.title);
        }
      });
      calendar.render();
    });
  </script>
</x-app-layout>
