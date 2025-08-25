<?php

namespace App\Providers;

use App\Services\Integration\HospitalSystemService;
use App\Services\Integration\LaboratoryService;
use App\Services\Integration\PharmacyService;
use App\Services\Integration\InsuranceService;
use App\Services\Integration\BillingSystemService;
use App\Services\Integration\TelehealthService;
use App\Services\Integration\ImagingService;
use App\Services\Integration\EmergencySystemService;
use App\Services\Integration\HL7Service;
use App\Services\Integration\FHIRService;
use App\Services\Integration\ApiGatewayService;
use App\Services\Integration\WebhookService;
use App\Services\Integration\SyncService;
use App\Contracts\IntegrationServiceInterface;
use App\Contracts\HL7ServiceInterface;
use App\Contracts\FHIRServiceInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as HttpClient;

/**
 * Provider de integraciones para el sistema médico
 * Maneja todas las integraciones con sistemas externos
 * Incluye estándares médicos HL7, FHIR y APIs especializadas
 */
class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Cliente HTTP base para integraciones
        $this->app->singleton('integration.http', function ($app) {
            return new HttpClient([
                'timeout' => config('integrations.timeout', 30),
                'verify' => config('integrations.ssl_verify', true),
                'headers' => [
                    'User-Agent' => config('app.name') . ' Medical System',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
        });

        // Servicio de gateway de APIs
        $this->app->singleton('integration.gateway', function ($app) {
            return new ApiGatewayService(
                $app->make('integration.http'),
                config('integrations.gateway')
            );
        });

        // Servicios de estándares médicos
        $this->app->singleton(HL7ServiceInterface::class, function ($app) {
            return new HL7Service(
                config('integrations.hl7'),
                $app->make('integration.http')
            );
        });

        $this->app->singleton(FHIRServiceInterface::class, function ($app) {
            return new FHIRService(
                config('integrations.fhir'),
                $app->make('integration.http')
            );
        });

        // Servicios de sistemas hospitalarios
        $this->app->singleton('integration.hospital', function ($app) {
            return new HospitalSystemService(
                config('integrations.hospital_systems'),
                $app->make('integration.gateway')
            );
        });

        $this->app->singleton('integration.emergency', function ($app) {
            return new EmergencySystemService(
                config('integrations.emergency_systems'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de laboratorios
        $this->app->singleton('integration.laboratory', function ($app) {
            return new LaboratoryService(
                config('integrations.laboratories'),
                $app->make('integration.gateway')
            );
        });

        $this->app->singleton('integration.imaging', function ($app) {
            return new ImagingService(
                config('integrations.imaging_centers'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de farmacias
        $this->app->singleton('integration.pharmacy', function ($app) {
            return new PharmacyService(
                config('integrations.pharmacies'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de obras sociales y seguros
        $this->app->singleton('integration.insurance', function ($app) {
            return new InsuranceService(
                config('integrations.insurance_companies'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de facturación
        $this->app->singleton('integration.billing', function ($app) {
            return new BillingSystemService(
                config('integrations.billing_systems'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de telemedicina
        $this->app->singleton('integration.telehealth', function ($app) {
            return new TelehealthService(
                config('integrations.telehealth_platforms'),
                $app->make('integration.gateway')
            );
        });

        // Servicios de webhooks
        $this->app->singleton('integration.webhook', function ($app) {
            return new WebhookService(
                $app->make('db'),
                config('integrations.webhooks')
            );
        });

        // Servicio de sincronización
        $this->app->singleton('integration.sync', function ($app) {
            return new SyncService(
                $app->make('queue'),
                config('integrations.sync_settings')
            );
        });

        // Servicio principal de integración
        $this->app->singleton(IntegrationServiceInterface::class, function ($app) {
            return $app->make('integration.gateway');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuración de integraciones médicas
        $this->registerMedicalIntegrations();
        
        // Configuración de estándares médicos
        $this->registerMedicalStandards();
        
        // Configuración de APIs externas
        $this->registerExternalApis();
        
        // Configuración de webhooks
        $this->registerWebhookConfiguration();
        
        // Configuración de sincronización
        $this->registerSyncConfiguration();
    }

    /**
     * Registra las integraciones médicas principales
     */
    private function registerMedicalIntegrations(): void
    {
        Config::set('integrations.hospital_systems', [
            'enabled' => env('HOSPITAL_INTEGRATION_ENABLED', false),
            'systems' => [
                'epic' => [
                    'name' => 'Epic EHR',
                    'base_url' => env('EPIC_BASE_URL'),
                    'client_id' => env('EPIC_CLIENT_ID'),
                    'endpoints' => [
                        'patients' => '/api/FHIR/R4/Patient',
                        'appointments' => '/api/FHIR/R4/Appointment',
                        'observations' => '/api/FHIR/R4/Observation',
                    ]
                ],
                'cerner' => [
                    'name' => 'Cerner PowerChart',
                    'base_url' => env('CERNER_BASE_URL'),
                    'client_id' => env('CERNER_CLIENT_ID'),
                    'endpoints' => [
                        'patients' => '/v1/Patient',
                        'encounters' => '/v1/Encounter',
                        'medications' => '/v1/MedicationOrder',
                    ]
                ],
                'allscripts' => [
                    'name' => 'Allscripts Sunrise',
                    'base_url' => env('ALLSCRIPTS_BASE_URL'),
                    'credentials' => [
                        'username' => env('ALLSCRIPTS_USERNAME'),
                        'password' => env('ALLSCRIPTS_PASSWORD'),
                    ]
                ]
            ]
        ]);

        Config::set('integrations.laboratories', [
            'enabled' => env('LAB_INTEGRATION_ENABLED', false),
            'providers' => [
                'quest' => [
                    'name' => 'Quest Diagnostics',
                    'base_url' => env('QUEST_BASE_URL'),
                    'api_key' => env('QUEST_API_KEY'),
                    'endpoints' => [
                        'orders' => '/api/v1/lab-orders',
                        'results' => '/api/v1/results',
                        'status' => '/api/v1/order-status',
                    ]
                ],
                'labcorp' => [
                    'name' => 'LabCorp',
                    'base_url' => env('LABCORP_BASE_URL'),
                    'credentials' => [
                        'client_id' => env('LABCORP_CLIENT_ID'),
                        'client_secret' => env('LABCORP_CLIENT_SECRET'),
                    ]
                ]
            ]
        ]);

        Config::set('integrations.pharmacies', [
            'enabled' => env('PHARMACY_INTEGRATION_ENABLED', false),
            'providers' => [
                'surescripts' => [
                    'name' => 'Surescripts Network',
                    'base_url' => env('SURESCRIPTS_BASE_URL'),
                    'certification_id' => env('SURESCRIPTS_CERT_ID'),
                ]
            ]
        ]);
    }

    /**
     * Registra los estándares médicos
     */
    private function registerMedicalStandards(): void
    {
        Config::set('integrations.hl7', [
            'version' => '2.5.1',
            'encoding' => 'UTF-8',
            'field_separator' => '|',
            'component_separator' => '^',
            'repetition_separator' => '~',
            'escape_character' => '\\',
            'subcomponent_separator' => '&',
            'message_types' => [
                'ADT' => 'Admission, Discharge, Transfer',
                'ORM' => 'Order Message',
                'ORU' => 'Observation Result',
                'SIU' => 'Scheduling Information Unsolicited',
            ]
        ]);

        Config::set('integrations.fhir', [
            'version' => 'R4',
            'base_url' => env('FHIR_SERVER_URL'),
            'auth' => [
                'type' => env('FHIR_AUTH_TYPE', 'oauth2'),
                'client_id' => env('FHIR_CLIENT_ID'),
                'client_secret' => env('FHIR_CLIENT_SECRET'),
                'scope' => 'patient/*.read patient/*.write',
            ],
            'resources' => [
                'Patient',
                'Practitioner',
                'Organization',
                'Appointment',
                'Observation',
                'DiagnosticReport',
                'MedicationRequest',
                'Encounter',
            ]
        ]);
    }

    /**
     * Registra las APIs externas
     */
    private function registerExternalApis(): void
    {
        Config::set('integrations.insurance_companies', [
            'enabled' => env('INSURANCE_INTEGRATION_ENABLED', false),
            'providers' => [
                'osde' => [
                    'name' => 'OSDE',
                    'base_url' => env('OSDE_API_URL'),
                    'credentials' => [
                        'username' => env('OSDE_USERNAME'),
                        'password' => env('OSDE_PASSWORD'),
                    ]
                ],
                'swiss_medical' => [
                    'name' => 'Swiss Medical',
                    'base_url' => env('SWISS_API_URL'),
                    'api_key' => env('SWISS_API_KEY'),
                ]
            ]
        ]);

        Config::set('integrations.telehealth_platforms', [
            'enabled' => env('TELEHEALTH_INTEGRATION_ENABLED', false),
            'providers' => [
                'zoom' => [
                    'name' => 'Zoom for Healthcare',
                    'api_key' => env('ZOOM_API_KEY'),
                    'api_secret' => env('ZOOM_API_SECRET'),
                    'webhook_secret' => env('ZOOM_WEBHOOK_SECRET'),
                ],
                'doxy' => [
                    'name' => 'Doxy.me',
                    'api_key' => env('DOXY_API_KEY'),
                    'room_prefix' => env('DOXY_ROOM_PREFIX', 'medical'),
                ]
            ]
        ]);
    }

    /**
     * Registra la configuración de webhooks
     */
    private function registerWebhookConfiguration(): void
    {
        Config::set('integrations.webhooks', [
            'enabled' => env('WEBHOOKS_ENABLED', true),
            'secret' => env('WEBHOOK_SECRET'),
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 5,
            'events' => [
                'patient.created',
                'patient.updated',
                'appointment.scheduled',
                'appointment.cancelled',
                'appointment.completed',
                'lab.result.ready',
                'prescription.filled',
                'emergency.alert',
            ]
        ]);
    }

    /**
     * Registra la configuración de sincronización
     */
    private function registerSyncConfiguration(): void
    {
        Config::set('integrations.sync_settings', [
            'enabled' => env('SYNC_ENABLED', true),
            'batch_size' => 100,
            'retry_attempts' => 3,
            'retry_delay' => 300, // 5 minutes
            'schedules' => [
                'patients' => '0 */6 * * *', // Every 6 hours
                'appointments' => '*/15 * * * *', // Every 15 minutes
                'lab_results' => '0 */2 * * *', // Every 2 hours
                'billing' => '0 1 * * *', // Daily at 1 AM
            ]
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            IntegrationServiceInterface::class,
            HL7ServiceInterface::class,
            FHIRServiceInterface::class,
            'integration.http',
            'integration.gateway',
            'integration.hospital',
            'integration.laboratory',
            'integration.pharmacy',
            'integration.insurance',
            'integration.billing',
            'integration.telehealth',
            'integration.imaging',
            'integration.emergency',
            'integration.webhook',
            'integration.sync',
        ];
    }
}
