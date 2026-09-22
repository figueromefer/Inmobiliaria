<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationTimezoneConfigurationTest extends TestCase
{
    public function test_application_timezone_reads_the_environment_variable(): void
    {
        $originalEnvironment = $_ENV['APP_TIMEZONE'] ?? null;
        $originalServer = $_SERVER['APP_TIMEZONE'] ?? null;

        try {
            $_ENV['APP_TIMEZONE'] = 'America/Mexico_City';
            $_SERVER['APP_TIMEZONE'] = 'America/Mexico_City';

            $configuration = require config_path('app.php');

            $this->assertSame('America/Mexico_City', $configuration['timezone']);
        } finally {
            if ($originalEnvironment === null) {
                unset($_ENV['APP_TIMEZONE']);
            } else {
                $_ENV['APP_TIMEZONE'] = $originalEnvironment;
            }
            if ($originalServer === null) {
                unset($_SERVER['APP_TIMEZONE']);
            } else {
                $_SERVER['APP_TIMEZONE'] = $originalServer;
            }
        }
    }
}
