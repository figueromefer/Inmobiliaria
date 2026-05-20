<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="flex items-center justify-between h-16">
            <div class="shrink-0 flex items-center">
                <a href="{{ route('tasks.index') }}">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                </a>
            </div>

            <div class="hidden md:flex justify-center h-16 items-center space-x-4">
                <x-nav-link :href="route('tasks.index')" :active="request()->routeIs('tasks.*')">{{ __('Tareas') }}</x-nav-link>
                <x-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')">{{ __('Tickets') }}</x-nav-link>

                <div class="relative group inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <span class="cursor-pointer flex items-center">Catálogos <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.06z" clip-rule="evenodd" /></svg></span>
                    <div x-show="open" x-transition class="absolute left-0 top-full hidden group-hover:block bg-white border border-gray-200 rounded-md shadow-lg w-56 z-50">
                        <a href="{{ route('clientes.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Clientes</a>
                        <a href="{{ route('propiedades.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Propiedades</a>
                        <a href="{{ route('inquilinos.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Inquilinos</a>
                        <a href="{{ route('contratos.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Contratos</a>
                        <a href="{{ route('documentos.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Documentos</a>
                    </div>
                </div>

                <div class="relative group inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <span class="cursor-pointer flex items-center">Operaciones <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.06z" clip-rule="evenodd" /></svg></span>
                    <div x-show="open" x-transition class="absolute left-0 top-full hidden group-hover:block bg-white border border-gray-200 rounded-md shadow-lg w-56 z-50">
                        <a href="{{ route('movimientos.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Movimientos</a>
                        <a href="{{ route('calendario.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Calendario / Adeudos</a>
                    </div>
                </div>

                <div class="relative group inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <span class="cursor-pointer flex items-center">Reportes <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.06z" clip-rule="evenodd" /></svg></span>
                    <div x-show="open" x-transition class="absolute left-0 top-full hidden group-hover:block bg-white border border-gray-200 rounded-md shadow-lg w-64 z-50">
                        <a href="{{ route('reportes.mensual') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mensual</a>
                        <a href="{{ route('reportes.ganancias-clientes') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Rendimiento por cliente</a>
                    </div>
                </div>

                @can('manage-users')
                    <div class="relative group inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                        <span class="cursor-pointer flex items-center">Administración <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.06z" clip-rule="evenodd" /></svg></span>
                        <div x-show="open" x-transition class="absolute left-0 top-full hidden group-hover:block bg-white border border-gray-200 rounded-md shadow-lg w-56 z-50">
                            <a href="{{ route('bitacora.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Bitácora</a>
                            <a href="{{ route('users.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Usuarios</a>
                        </div>
                    </div>
                @endcan
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1"><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Perfil') }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">@csrf<x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Cerrar sesión') }}</x-dropdown-link></form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('tasks.index')" :active="request()->routeIs('tasks.*')">Tareas</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')">Tickets</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('clientes.index')" :active="request()->routeIs('clientes.*')">Clientes</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('propiedades.index')" :active="request()->routeIs('propiedades.*')">Propiedades</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('inquilinos.index')" :active="request()->routeIs('inquilinos.*')">Inquilinos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('contratos.index')" :active="request()->routeIs('contratos.*')">Contratos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('documentos.index')" :active="request()->routeIs('documentos.*')">Documentos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('movimientos.index')" :active="request()->routeIs('movimientos.*')">Movimientos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('calendario.index')" :active="request()->routeIs('calendario.*')">Calendario / Adeudos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reportes.mensual')" :active="request()->routeIs('reportes.*')">Reportes</x-responsive-nav-link>
            @can('manage-users')
                <x-responsive-nav-link :href="route('bitacora.index')" :active="request()->routeIs('bitacora.*')">Bitácora</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-responsive-nav-link>
            @endcan
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">Perfil</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">@csrf<x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Cerrar sesión</x-responsive-nav-link></form>
            </div>
        </div>
    </div>
</nav>
