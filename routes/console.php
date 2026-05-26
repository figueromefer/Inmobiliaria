<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RecurringTaskService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tasks:generate-recurring', function (RecurringTaskService $service) {
    $result = $service->generateAll();

    $this->info('Recurring tasks generated successfully.');
    $this->line('Maintenance payments: ' . $result['maintenance_payment']);
    $this->line('Property tax: ' . $result['property_tax']);
    $this->line('Contract renewals: ' . $result['contract_renewal']);
    $this->line('Rent collection: ' . $result['rent_collection']);
    $this->line('TOTAL: ' . $result['total']);
})->purpose('Generate recurring real estate tasks');

Schedule::command('tasks:generate-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping();
