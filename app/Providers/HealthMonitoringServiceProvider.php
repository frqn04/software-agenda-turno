<?php

namespace App\Providers;

use App\Services\Monitoring\HealthCheckService;
use App\Services\Monitoring\PerformanceMonitoringService;
use App\Services\Monitoring\AlertingService;
use App\Services\Monitoring\MetricsCollectionService;
use App\Services\Monitoring\LogAnalysisService;
use App\Services\Monitoring\DatabaseMonitoringService;
use App\Services\Monitoring\ApiMonitoringService;
use App\Services\Monitoring\InfrastructureMonitoringService;
use App\Services\Monitoring\MedicalSystemMonitoringService;
use App\Services\Monitoring\SlaMonitoringService;
use App\Services\Monitoring\DisasterRecoveryService;
use App\Contracts\HealthCheckInterface;
use App\Contracts\MonitoringServiceInterface;
use App\Contracts\AlertingInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Provider de monitoreo y salud del sistema médico
 * Supervisa todos los aspectos críticos del sistema de salud
 * Incluye monitoreo médico especializado y alertas de emergencia
 */
class HealthMonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Servicio principal de verificación de salud
        $this->app->singleton(HealthCheckInterface::class, function ($app) {
            return new HealthCheckService(
                config('monitoring.health_checks'),
                $app->make('log')
            );
        });

        // Servicio de monitoreo de rendimiento
        $this->app->singleton('monitoring.performance', function ($app) {
            return new PerformanceMonitoringService(
                config('monitoring.performance'),
                $app->make('cache.store')
            );
        });

        // Servicio de alertas
        $this->app->singleton(AlertingInterface::class, function ($app) {
            return new AlertingService(
                config('monitoring.alerting'),
                $app->make('notification.emergency')
            );
        });

        // Servicio de recolección de métricas
        $this->app->singleton('monitoring.metrics', function ($app) {
            return new MetricsCollectionService(
                config('monitoring.metrics'),
                $app->make('db')
            );
        });

        // Servicio de análisis de logs
        $this->app->singleton('monitoring.logs', function ($app) {
            return new LogAnalysisService(
                config('monitoring.log_analysis'),
                $app->make('log')
            );
        });

        // Servicio de monitoreo de base de datos
        $this->app->singleton('monitoring.database', function ($app) {
            return new DatabaseMonitoringService(
                config('monitoring.database'),
                $app->make('db')
            );
        });

        // Servicio de monitoreo de API
        $this->app->singleton('monitoring.api', function ($app) {
            return new ApiMonitoringService(
                config('monitoring.api'),
                $app->make('cache.store')
            );
        });

        // Servicio de monitoreo de infraestructura
        $this->app->singleton('monitoring.infrastructure', function ($app) {
            return new InfrastructureMonitoringService(
                config('monitoring.infrastructure'),
                $app->make('log')
            );
        });

        // Servicio de monitoreo médico especializado
        $this->app->singleton('monitoring.medical', function ($app) {
            return new MedicalSystemMonitoringService(
                config('monitoring.medical_systems'),
                $app->make(AlertingInterface::class)
            );
        });

        // Servicio de monitoreo de SLA
        $this->app->singleton('monitoring.sla', function ($app) {
            return new SlaMonitoringService(
                config('monitoring.sla'),
                $app->make('monitoring.metrics')
            );
        });

        // Servicio de recuperación ante desastres
        $this->app->singleton('monitoring.disaster_recovery', function ($app) {
            return new DisasterRecoveryService(
                config('monitoring.disaster_recovery'),
                $app->make(AlertingInterface::class)
            );
        });

        // Servicio principal de monitoreo
        $this->app->singleton(MonitoringServiceInterface::class, function ($app) {
            return $app->make(HealthCheckInterface::class);
        });

        // Aliases para facilitar el acceso
        $this->app->alias(HealthCheckInterface::class, 'monitoring.health');
        $this->app->alias(AlertingInterface::class, 'monitoring.alerts');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuración de verificaciones de salud
        $this->registerHealthChecks();
        
        // Configuración de monitoreo de rendimiento
        $this->registerPerformanceMonitoring();
        
        // Configuración de alertas médicas
        $this->registerMedicalAlerting();
        
        // Configuración de métricas
        $this->registerMetricsConfiguration();
        
        // Configuración de SLA médicos
        $this->registerMedicalSlaConfiguration();
    }

    /**
     * Registra las verificaciones de salud del sistema médico
     */
    private function registerHealthChecks(): void
    {
        Config::set('monitoring.health_checks', [
            'enabled' => true,
            'frequency' => 60, // seconds
            'timeout' => 30,
            'checks' => [
                'database_connectivity' => [
                    'enabled' => true,
                    'critical' => true,
                    'timeout' => 10,
                    'query' => 'SELECT 1',
                ],
                'redis_connectivity' => [
                    'enabled' => true,
                    'critical' => true,
                    'timeout' => 5,
                ],
                'queue_status' => [
                    'enabled' => true,
                    'critical' => true,
                    'max_failed_jobs' => 10,
                ],
                'storage_availability' => [
                    'enabled' => true,
                    'critical' => true,
                    'min_free_space_gb' => 5,
                ],
                'external_api_status' => [
                    'enabled' => true,
                    'critical' => false,
                    'apis' => [
                        'laboratory_system',
                        'insurance_api',
                        'emergency_services',
                    ]
                ],
                'medical_device_connectivity' => [
                    'enabled' => true,
                    'critical' => true,
                    'devices' => [
                        'patient_monitor',
                        'ecg_machine',
                        'laboratory_analyzer',
                    ]
                ],
                'backup_system_status' => [
                    'enabled' => true,
                    'critical' => true,
                    'last_backup_max_age_hours' => 24,
                ],
                'ssl_certificate_expiry' => [
                    'enabled' => true,
                    'critical' => false,
                    'warning_days' => 30,
                    'critical_days' => 7,
                ],
                'memory_usage' => [
                    'enabled' => true,
                    'critical' => true,
                    'warning_threshold' => 80, // percentage
                    'critical_threshold' => 95,
                ],
                'cpu_usage' => [
                    'enabled' => true,
                    'critical' => true,
                    'warning_threshold' => 80,
                    'critical_threshold' => 95,
                ],
                'disk_usage' => [
                    'enabled' => true,
                    'critical' => true,
                    'warning_threshold' => 80,
                    'critical_threshold' => 95,
                ]
            ]
        ]);
    }

    /**
     * Registra el monitoreo de rendimiento
     */
    private function registerPerformanceMonitoring(): void
    {
        Config::set('monitoring.performance', [
            'enabled' => true,
            'sampling_rate' => 1.0, // 100% sampling for medical systems
            'response_time_thresholds' => [
                'api_endpoints' => [
                    'warning' => 500, // ms
                    'critical' => 2000,
                ],
                'database_queries' => [
                    'warning' => 100,
                    'critical' => 1000,
                ],
                'external_integrations' => [
                    'warning' => 2000,
                    'critical' => 10000,
                ]
            ],
            'track_metrics' => [
                'response_times' => true,
                'throughput' => true,
                'error_rates' => true,
                'memory_usage' => true,
                'cpu_usage' => true,
                'database_performance' => true,
                'cache_hit_rates' => true,
                'queue_processing_times' => true,
            ],
            'alerting_thresholds' => [
                'error_rate_percentage' => 5,
                'slow_response_percentage' => 10,
                'memory_usage_percentage' => 90,
                'cpu_usage_percentage' => 90,
            ]
        ]);
    }

    /**
     * Registra las alertas médicas especializadas
     */
    private function registerMedicalAlerting(): void
    {
        Config::set('monitoring.alerting', [
            'enabled' => true,
            'channels' => [
                'email' => [
                    'enabled' => true,
                    'recipients' => [
                        'system_admin@hospital.com',
                        'it_support@hospital.com',
                    ]
                ],
                'sms' => [
                    'enabled' => true,
                    'recipients' => [
                        '+1234567890', // System Admin
                        '+0987654321', // IT Support
                    ]
                ],
                'slack' => [
                    'enabled' => env('SLACK_ALERTS_ENABLED', false),
                    'webhook_url' => env('SLACK_WEBHOOK_URL'),
                    'channel' => '#medical-system-alerts',
                ],
                'pagerduty' => [
                    'enabled' => env('PAGERDUTY_ENABLED', false),
                    'service_key' => env('PAGERDUTY_SERVICE_KEY'),
                ]
            ],
            'alert_levels' => [
                'info' => [
                    'channels' => ['email'],
                    'throttle_minutes' => 60,
                ],
                'warning' => [
                    'channels' => ['email', 'slack'],
                    'throttle_minutes' => 30,
                ],
                'critical' => [
                    'channels' => ['email', 'sms', 'slack', 'pagerduty'],
                    'throttle_minutes' => 0, // No throttling for critical alerts
                    'escalation_minutes' => 15,
                ],
                'emergency' => [
                    'channels' => ['sms', 'pagerduty'],
                    'throttle_minutes' => 0,
                    'immediate_notification' => true,
                ]
            ],
            'medical_specific_alerts' => [
                'patient_data_breach' => [
                    'level' => 'emergency',
                    'auto_notify_compliance_officer' => true,
                ],
                'medical_device_failure' => [
                    'level' => 'critical',
                    'auto_notify_biomedical_team' => true,
                ],
                'appointment_system_down' => [
                    'level' => 'critical',
                    'auto_notify_front_desk' => true,
                ],
                'lab_results_delay' => [
                    'level' => 'warning',
                    'threshold_hours' => 2,
                ],
                'emergency_system_failure' => [
                    'level' => 'emergency',
                    'auto_notify_emergency_team' => true,
                ]
            ]
        ]);

        Config::set('monitoring.medical_systems', [
            'patient_monitoring' => [
                'enabled' => true,
                'check_interval' => 30, // seconds
                'vital_signs_threshold' => [
                    'heart_rate' => [60, 100],
                    'blood_pressure_systolic' => [90, 140],
                    'blood_pressure_diastolic' => [60, 90],
                    'temperature' => [36.1, 37.8], // Celsius
                    'oxygen_saturation' => [95, 100],
                ]
            ],
            'medical_devices' => [
                'enabled' => true,
                'device_types' => [
                    'ventilators',
                    'defibrillators',
                    'infusion_pumps',
                    'patient_monitors',
                    'diagnostic_equipment',
                ],
                'status_check_interval' => 60,
                'alert_on_malfunction' => true,
            ],
            'laboratory_systems' => [
                'enabled' => true,
                'result_processing_time_limit' => 120, // minutes
                'quality_control_alerts' => true,
                'critical_value_alerts' => true,
            ],
            'pharmacy_systems' => [
                'enabled' => true,
                'medication_interaction_alerts' => true,
                'dosage_verification_alerts' => true,
                'controlled_substance_monitoring' => true,
            ]
        ]);
    }

    /**
     * Registra la configuración de métricas
     */
    private function registerMetricsConfiguration(): void
    {
        Config::set('monitoring.metrics', [
            'collection_enabled' => true,
            'retention_days' => 365,
            'aggregation_intervals' => [
                'minute' => 1,
                'hour' => 60,
                'day' => 1440,
                'week' => 10080,
                'month' => 43200,
            ],
            'medical_metrics' => [
                'patient_satisfaction_scores' => true,
                'appointment_no_show_rates' => true,
                'average_wait_times' => true,
                'treatment_completion_rates' => true,
                'readmission_rates' => true,
                'medication_adherence_rates' => true,
                'clinical_outcome_measures' => true,
                'emergency_response_times' => true,
            ],
            'system_metrics' => [
                'api_response_times' => true,
                'database_query_performance' => true,
                'cache_hit_rates' => true,
                'queue_processing_times' => true,
                'error_rates' => true,
                'user_session_durations' => true,
                'concurrent_user_counts' => true,
            ]
        ]);
    }

    /**
     * Registra la configuración de SLA médicos
     */
    private function registerMedicalSlaConfiguration(): void
    {
        Config::set('monitoring.sla', [
            'enabled' => true,
            'reporting_enabled' => true,
            'targets' => [
                'system_availability' => 99.9, // percentage
                'emergency_system_availability' => 99.99,
                'api_response_time' => 500, // ms (95th percentile)
                'emergency_api_response_time' => 100,
                'database_response_time' => 100,
                'patient_data_access_time' => 200,
                'appointment_booking_time' => 1000,
                'lab_result_processing_time' => 7200, // seconds (2 hours)
                'prescription_processing_time' => 300, // 5 minutes
            ],
            'measurement_windows' => [
                'daily' => true,
                'weekly' => true,
                'monthly' => true,
                'quarterly' => true,
                'annually' => true,
            ],
            'breach_notifications' => [
                'immediate' => true,
                'daily_summary' => true,
                'weekly_report' => true,
                'monthly_analysis' => true,
            ]
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            HealthCheckInterface::class,
            MonitoringServiceInterface::class,
            AlertingInterface::class,
            'monitoring.health',
            'monitoring.performance',
            'monitoring.alerts',
            'monitoring.metrics',
            'monitoring.logs',
            'monitoring.database',
            'monitoring.api',
            'monitoring.infrastructure',
            'monitoring.medical',
            'monitoring.sla',
            'monitoring.disaster_recovery',
        ];
    }
}
