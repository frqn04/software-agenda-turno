<?php

namespace App\Providers;

use App\Services\Security\EncryptionService;
use App\Services\Security\AuditService;
use App\Services\Security\AccessControlService;
use App\Services\Security\ComplianceService;
use App\Services\Security\TwoFactorAuthService;
use App\Services\Security\SessionSecurityService;
use App\Services\Security\ApiSecurityService;
use App\Services\Security\DataAnonymizationService;
use App\Services\Security\VulnerabilityScanner;
use App\Services\Security\IntrusionDetectionService;
use App\Services\Security\SecurityMonitoringService;
use App\Services\Security\BackupSecurityService;
use App\Contracts\EncryptionServiceInterface;
use App\Contracts\AuditServiceInterface;
use App\Contracts\AccessControlInterface;
use App\Contracts\ComplianceInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Provider de seguridad médica avanzada
 * Implementa todas las medidas de seguridad requeridas para sistemas médicos
 * Incluye HIPAA, protección de datos, auditoría y cumplimiento normativo
 */
class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Servicio de encriptación médica
        $this->app->singleton(EncryptionServiceInterface::class, function ($app) {
            return new EncryptionService(
                config('security.medical.encryption'),
                config('app.key')
            );
        });

        // Servicio de auditoría médica
        $this->app->singleton(AuditServiceInterface::class, function ($app) {
            return new AuditService(
                $app->make('db'),
                config('security.medical.audit')
            );
        });

        // Servicio de control de acceso
        $this->app->singleton(AccessControlInterface::class, function ($app) {
            return new AccessControlService(
                $app->make('db'),
                config('security.medical.access_control')
            );
        });

        // Servicio de cumplimiento normativo
        $this->app->singleton(ComplianceInterface::class, function ($app) {
            return new ComplianceService(
                config('security.medical.compliance'),
                $app->make(AuditServiceInterface::class)
            );
        });

        // Servicio de autenticación de dos factores
        $this->app->singleton('security.2fa', function ($app) {
            return new TwoFactorAuthService(
                config('security.medical.two_factor'),
                $app->make('cache.store')
            );
        });

        // Servicio de seguridad de sesiones
        $this->app->singleton('security.session', function ($app) {
            return new SessionSecurityService(
                config('security.medical.sessions'),
                $app->make('session.store')
            );
        });

        // Servicio de seguridad de API
        $this->app->singleton('security.api', function ($app) {
            return new ApiSecurityService(
                config('security.medical.api'),
                $app->make('cache.store')
            );
        });

        // Servicio de anonimización de datos
        $this->app->singleton('security.anonymization', function ($app) {
            return new DataAnonymizationService(
                config('security.medical.anonymization'),
                $app->make(EncryptionServiceInterface::class)
            );
        });

        // Servicio de escaneo de vulnerabilidades
        $this->app->singleton('security.vulnerability', function ($app) {
            return new VulnerabilityScanner(
                config('security.medical.vulnerability_scanning'),
                $app->make('log')
            );
        });

        // Servicio de detección de intrusiones
        $this->app->singleton('security.intrusion', function ($app) {
            return new IntrusionDetectionService(
                config('security.medical.intrusion_detection'),
                $app->make('log')
            );
        });

        // Servicio de monitoreo de seguridad
        $this->app->singleton('security.monitoring', function ($app) {
            return new SecurityMonitoringService(
                config('security.medical.monitoring'),
                $app->make('log'),
                $app->make('cache.store')
            );
        });

        // Servicio de seguridad de backups
        $this->app->singleton('security.backup', function ($app) {
            return new BackupSecurityService(
                config('security.medical.backup'),
                $app->make(EncryptionServiceInterface::class)
            );
        });

        // Aliases para facilitar el acceso
        $this->app->alias(EncryptionServiceInterface::class, 'security.encryption');
        $this->app->alias(AuditServiceInterface::class, 'security.audit');
        $this->app->alias(AccessControlInterface::class, 'security.access');
        $this->app->alias(ComplianceInterface::class, 'security.compliance');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuración de seguridad médica
        $this->registerMedicalSecurityConfig();
        
        // Configuración de HIPAA
        $this->registerHipaaCompliance();
        
        // Configuración de auditoría
        $this->registerAuditConfiguration();
        
        // Configuración de encriptación
        $this->registerEncryptionConfiguration();
        
        // Configuración de monitoreo
        $this->registerMonitoringConfiguration();
    }

    /**
     * Registra la configuración de seguridad médica principal
     */
    private function registerMedicalSecurityConfig(): void
    {
        Config::set('security.medical.general', [
            'enabled' => true,
            'strict_mode' => env('MEDICAL_SECURITY_STRICT', true),
            'require_https' => true,
            'require_secure_headers' => true,
            'max_login_attempts' => 3,
            'lockout_duration' => 900, // 15 minutes
            'password_min_length' => 12,
            'password_complexity' => true,
            'force_password_change' => 90, // days
            'session_timeout' => 1800, // 30 minutes
            'concurrent_sessions_limit' => 1,
        ]);

        Config::set('security.medical.access_control', [
            'enabled' => true,
            'rbac_enabled' => true,
            'ip_whitelist' => env('SECURITY_IP_WHITELIST', ''),
            'geo_restrictions' => env('SECURITY_GEO_RESTRICTIONS', false),
            'device_fingerprinting' => true,
            'suspicious_activity_detection' => true,
            'automatic_lockout' => true,
        ]);

        Config::set('security.medical.api', [
            'rate_limiting' => [
                'enabled' => true,
                'requests_per_minute' => 60,
                'burst_limit' => 100,
            ],
            'api_key_rotation' => [
                'enabled' => true,
                'rotation_interval' => 2592000, // 30 days
            ],
            'request_signing' => [
                'enabled' => true,
                'algorithm' => 'sha256',
            ],
            'cors' => [
                'strict_mode' => true,
                'allowed_origins' => [],
            ]
        ]);
    }

    /**
     * Registra la configuración de cumplimiento HIPAA
     */
    private function registerHipaaCompliance(): void
    {
        Config::set('security.medical.compliance.hipaa', [
            'enabled' => env('HIPAA_COMPLIANCE_ENABLED', true),
            'administrative_safeguards' => [
                'assigned_security_responsibility' => true,
                'workforce_training' => true,
                'information_access_management' => true,
                'security_awareness_training' => true,
                'security_incident_procedures' => true,
                'contingency_plan' => true,
                'periodic_security_evaluations' => true,
            ],
            'physical_safeguards' => [
                'facility_access_controls' => true,
                'workstation_use_controls' => true,
                'device_and_media_controls' => true,
            ],
            'technical_safeguards' => [
                'access_control' => true,
                'audit_controls' => true,
                'integrity' => true,
                'person_or_entity_authentication' => true,
                'transmission_security' => true,
            ],
            'breach_notification' => [
                'enabled' => true,
                'notification_timeframe' => 72, // hours
                'affected_individuals_notification' => 60, // days
            ]
        ]);

        Config::set('security.medical.compliance.gdpr', [
            'enabled' => env('GDPR_COMPLIANCE_ENABLED', true),
            'data_protection_impact_assessment' => true,
            'privacy_by_design' => true,
            'right_to_be_forgotten' => true,
            'data_portability' => true,
            'consent_management' => true,
            'breach_notification' => [
                'enabled' => true,
                'notification_timeframe' => 72, // hours
            ]
        ]);
    }

    /**
     * Registra la configuración de auditoría
     */
    private function registerAuditConfiguration(): void
    {
        Config::set('security.medical.audit', [
            'enabled' => true,
            'comprehensive_logging' => true,
            'real_time_monitoring' => true,
            'log_retention_days' => 2555, // 7 years (HIPAA requirement)
            'encrypted_logs' => true,
            'tamper_protection' => true,
            'events_to_audit' => [
                'user_authentication',
                'user_authorization',
                'data_access',
                'data_modification',
                'data_deletion',
                'system_access',
                'admin_actions',
                'configuration_changes',
                'backup_operations',
                'security_incidents',
                'failed_access_attempts',
                'privilege_escalation',
            ],
            'alert_thresholds' => [
                'failed_logins' => 3,
                'suspicious_data_access' => 5,
                'after_hours_access' => 1,
                'bulk_data_operations' => 10,
            ],
            'reporting' => [
                'daily_summary' => true,
                'weekly_detailed' => true,
                'monthly_compliance' => true,
                'annual_security_review' => true,
            ]
        ]);
    }

    /**
     * Registra la configuración de encriptación
     */
    private function registerEncryptionConfiguration(): void
    {
        Config::set('security.medical.encryption', [
            'algorithm' => 'AES-256-GCM',
            'key_rotation_interval' => 2592000, // 30 days
            'at_rest_encryption' => [
                'enabled' => true,
                'database_encryption' => true,
                'file_encryption' => true,
                'backup_encryption' => true,
            ],
            'in_transit_encryption' => [
                'enabled' => true,
                'tls_version' => '1.3',
                'cipher_suites' => [
                    'TLS_AES_256_GCM_SHA384',
                    'TLS_CHACHA20_POLY1305_SHA256',
                    'TLS_AES_128_GCM_SHA256',
                ],
            ],
            'key_management' => [
                'hardware_security_module' => env('HSM_ENABLED', false),
                'key_escrow' => true,
                'split_knowledge' => true,
                'dual_control' => true,
            ]
        ]);

        Config::set('security.medical.anonymization', [
            'enabled' => true,
            'techniques' => [
                'data_masking' => true,
                'pseudonymization' => true,
                'generalization' => true,
                'noise_addition' => true,
            ],
            'phi_fields' => [
                'patient_name',
                'address',
                'phone_number',
                'email',
                'social_security_number',
                'medical_record_number',
                'health_plan_number',
                'account_numbers',
                'certificate_numbers',
                'vehicle_identifiers',
                'device_identifiers',
                'web_urls',
                'ip_addresses',
                'biometric_identifiers',
                'full_face_photos',
            ]
        ]);
    }

    /**
     * Registra la configuración de monitoreo
     */
    private function registerMonitoringConfiguration(): void
    {
        Config::set('security.medical.monitoring', [
            'real_time_alerts' => true,
            'anomaly_detection' => true,
            'behavioral_analysis' => true,
            'threat_intelligence' => true,
            'automated_response' => [
                'enabled' => true,
                'auto_block_suspicious_ips' => true,
                'auto_disable_compromised_accounts' => true,
                'auto_quarantine_malicious_files' => true,
            ],
            'security_metrics' => [
                'login_failure_rate',
                'data_access_patterns',
                'unusual_activity_detection',
                'privilege_escalation_attempts',
                'data_exfiltration_indicators',
                'malware_detection',
                'vulnerability_exposure',
            ],
            'incident_response' => [
                'automated_containment' => true,
                'notification_escalation' => true,
                'forensic_data_collection' => true,
                'recovery_procedures' => true,
            ]
        ]);

        Config::set('security.medical.vulnerability_scanning', [
            'enabled' => true,
            'scan_frequency' => 'daily',
            'scan_types' => [
                'web_application',
                'network_infrastructure',
                'database_security',
                'configuration_assessment',
                'dependency_scanning',
            ],
            'automatic_patching' => [
                'enabled' => false, // Manual approval required for medical systems
                'critical_only' => true,
                'test_environment_first' => true,
            ]
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            EncryptionServiceInterface::class,
            AuditServiceInterface::class,
            AccessControlInterface::class,
            ComplianceInterface::class,
            'security.encryption',
            'security.audit',
            'security.access',
            'security.compliance',
            'security.2fa',
            'security.session',
            'security.api',
            'security.anonymization',
            'security.vulnerability',
            'security.intrusion',
            'security.monitoring',
            'security.backup',
        ];
    }
}
