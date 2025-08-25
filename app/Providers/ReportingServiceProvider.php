<?php

namespace App\Providers;

use App\Services\Reporting\MedicalReportingService;
use App\Services\Reporting\StatisticalReportingService;
use App\Services\Reporting\ComplianceReportingService;
use App\Services\Reporting\FinancialReportingService;
use App\Services\Reporting\OperationalReportingService;
use App\Services\Reporting\QualityMetricsReportingService;
use App\Services\Reporting\PatientOutcomeReportingService;
use App\Services\Reporting\PerformanceReportingService;
use App\Services\Reporting\AuditReportingService;
use App\Services\Reporting\DashboardService;
use App\Services\Reporting\ExportService;
use App\Contracts\ReportingServiceInterface;
use App\Contracts\DashboardInterface;
use App\Contracts\ExportInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Provider de reportes y análisis para el sistema médico
 * Proporciona servicios completos de reporting médico y estadísticas
 * Incluye dashboards, exportaciones y cumplimiento normativo
 */
class ReportingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Servicio principal de reportes médicos
        $this->app->singleton(ReportingServiceInterface::class, function ($app) {
            return new MedicalReportingService(
                $app->make('db'),
                config('reporting.medical')
            );
        });

        // Servicio de reportes estadísticos
        $this->app->singleton('reporting.statistical', function ($app) {
            return new StatisticalReportingService(
                $app->make('db'),
                config('reporting.statistical')
            );
        });

        // Servicio de reportes de cumplimiento
        $this->app->singleton('reporting.compliance', function ($app) {
            return new ComplianceReportingService(
                $app->make('db'),
                config('reporting.compliance'),
                $app->make('security.audit')
            );
        });

        // Servicio de reportes financieros
        $this->app->singleton('reporting.financial', function ($app) {
            return new FinancialReportingService(
                $app->make('db'),
                config('reporting.financial')
            );
        });

        // Servicio de reportes operacionales
        $this->app->singleton('reporting.operational', function ($app) {
            return new OperationalReportingService(
                $app->make('db'),
                config('reporting.operational')
            );
        });

        // Servicio de reportes de métricas de calidad
        $this->app->singleton('reporting.quality', function ($app) {
            return new QualityMetricsReportingService(
                $app->make('db'),
                config('reporting.quality_metrics')
            );
        });

        // Servicio de reportes de resultados de pacientes
        $this->app->singleton('reporting.patient_outcomes', function ($app) {
            return new PatientOutcomeReportingService(
                $app->make('db'),
                config('reporting.patient_outcomes')
            );
        });

        // Servicio de reportes de rendimiento
        $this->app->singleton('reporting.performance', function ($app) {
            return new PerformanceReportingService(
                $app->make('db'),
                config('reporting.performance')
            );
        });

        // Servicio de reportes de auditoría
        $this->app->singleton('reporting.audit', function ($app) {
            return new AuditReportingService(
                $app->make('db'),
                config('reporting.audit'),
                $app->make('security.audit')
            );
        });

        // Servicio de dashboard
        $this->app->singleton(DashboardInterface::class, function ($app) {
            return new DashboardService(
                config('reporting.dashboard'),
                [
                    $app->make(ReportingServiceInterface::class),
                    $app->make('reporting.statistical'),
                    $app->make('reporting.operational'),
                    $app->make('reporting.quality'),
                ]
            );
        });

        // Servicio de exportación
        $this->app->singleton(ExportInterface::class, function ($app) {
            return new ExportService(
                config('reporting.export'),
                $app->make('filesystem')
            );
        });

        // Aliases para facilitar el acceso
        $this->app->alias(ReportingServiceInterface::class, 'reporting.medical');
        $this->app->alias(DashboardInterface::class, 'reporting.dashboard');
        $this->app->alias(ExportInterface::class, 'reporting.export');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuración de reportes médicos
        $this->registerMedicalReporting();
        
        // Configuración de dashboards
        $this->registerDashboardConfiguration();
        
        // Configuración de exportaciones
        $this->registerExportConfiguration();
        
        // Configuración de cumplimiento
        $this->registerComplianceReporting();
        
        // Configuración de métricas de calidad
        $this->registerQualityMetrics();
    }

    /**
     * Registra la configuración de reportes médicos
     */
    private function registerMedicalReporting(): void
    {
        Config::set('reporting.medical', [
            'enabled' => true,
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'report_types' => [
                'patient_demographics' => [
                    'enabled' => true,
                    'schedule' => 'daily',
                    'retention_days' => 365,
                    'fields' => [
                        'age_groups',
                        'gender_distribution',
                        'geographic_distribution',
                        'insurance_types',
                    ]
                ],
                'clinical_summaries' => [
                    'enabled' => true,
                    'schedule' => 'weekly',
                    'retention_days' => 2555, // 7 years
                    'fields' => [
                        'diagnoses',
                        'treatments',
                        'medications',
                        'outcomes',
                    ]
                ],
                'appointment_analytics' => [
                    'enabled' => true,
                    'schedule' => 'daily',
                    'retention_days' => 365,
                    'metrics' => [
                        'total_appointments',
                        'completed_appointments',
                        'cancelled_appointments',
                        'no_show_rates',
                        'average_wait_times',
                        'doctor_utilization',
                    ]
                ],
                'treatment_effectiveness' => [
                    'enabled' => true,
                    'schedule' => 'monthly',
                    'retention_days' => 2555,
                    'metrics' => [
                        'recovery_rates',
                        'readmission_rates',
                        'complication_rates',
                        'patient_satisfaction',
                    ]
                ],
                'prescription_analytics' => [
                    'enabled' => true,
                    'schedule' => 'weekly',
                    'retention_days' => 365,
                    'metrics' => [
                        'most_prescribed_medications',
                        'prescription_adherence',
                        'drug_interactions',
                        'cost_analysis',
                    ]
                ]
            ]
        ]);

        Config::set('reporting.statistical', [
            'enabled' => true,
            'analysis_types' => [
                'descriptive_statistics' => true,
                'trend_analysis' => true,
                'predictive_modeling' => true,
                'comparative_analysis' => true,
                'correlation_analysis' => true,
            ],
            'data_sources' => [
                'patient_records',
                'appointment_data',
                'treatment_outcomes',
                'financial_data',
                'operational_metrics',
            ],
            'statistical_methods' => [
                'regression_analysis',
                'time_series_analysis',
                'clustering_analysis',
                'hypothesis_testing',
                'survival_analysis',
            ]
        ]);
    }

    /**
     * Registra la configuración de dashboards
     */
    private function registerDashboardConfiguration(): void
    {
        Config::set('reporting.dashboard', [
            'enabled' => true,
            'refresh_interval' => 300, // 5 minutes
            'real_time_updates' => true,
            'user_customization' => true,
            'role_based_views' => true,
            'dashboards' => [
                'executive_dashboard' => [
                    'title' => 'Executive Overview',
                    'widgets' => [
                        'key_performance_indicators',
                        'financial_summary',
                        'patient_satisfaction',
                        'operational_efficiency',
                        'quality_metrics',
                    ],
                    'roles' => ['admin', 'executive'],
                ],
                'clinical_dashboard' => [
                    'title' => 'Clinical Operations',
                    'widgets' => [
                        'patient_census',
                        'appointment_schedule',
                        'clinical_alerts',
                        'lab_results_pending',
                        'medication_management',
                    ],
                    'roles' => ['doctor', 'nurse', 'clinical_manager'],
                ],
                'operational_dashboard' => [
                    'title' => 'Operational Metrics',
                    'widgets' => [
                        'resource_utilization',
                        'staff_scheduling',
                        'equipment_status',
                        'inventory_levels',
                        'maintenance_alerts',
                    ],
                    'roles' => ['operations_manager', 'facilities'],
                ],
                'financial_dashboard' => [
                    'title' => 'Financial Performance',
                    'widgets' => [
                        'revenue_tracking',
                        'cost_analysis',
                        'billing_status',
                        'insurance_claims',
                        'budget_variance',
                    ],
                    'roles' => ['finance_manager', 'billing'],
                ],
                'quality_dashboard' => [
                    'title' => 'Quality Assurance',
                    'widgets' => [
                        'patient_safety_indicators',
                        'clinical_quality_measures',
                        'infection_control_metrics',
                        'medication_safety',
                        'adverse_events',
                    ],
                    'roles' => ['quality_manager', 'compliance'],
                ]
            ]
        ]);
    }

    /**
     * Registra la configuración de exportaciones
     */
    private function registerExportConfiguration(): void
    {
        Config::set('reporting.export', [
            'enabled' => true,
            'formats' => [
                'pdf' => [
                    'enabled' => true,
                    'template_engine' => 'dompdf',
                    'default_orientation' => 'portrait',
                    'default_paper_size' => 'A4',
                ],
                'excel' => [
                    'enabled' => true,
                    'library' => 'maatwebsite/excel',
                    'default_format' => 'xlsx',
                ],
                'csv' => [
                    'enabled' => true,
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'encoding' => 'UTF-8',
                ],
                'json' => [
                    'enabled' => true,
                    'pretty_print' => true,
                ],
                'xml' => [
                    'enabled' => true,
                    'version' => '1.0',
                    'encoding' => 'UTF-8',
                ]
            ],
            'security' => [
                'password_protection' => true,
                'watermarking' => true,
                'access_logging' => true,
                'download_expiry' => 7, // days
            ],
            'templates' => [
                'patient_report' => [
                    'format' => 'pdf',
                    'template' => 'reports.patient_summary',
                    'orientation' => 'portrait',
                ],
                'clinical_summary' => [
                    'format' => 'pdf',
                    'template' => 'reports.clinical_summary',
                    'orientation' => 'landscape',
                ],
                'financial_statement' => [
                    'format' => 'excel',
                    'template' => 'reports.financial_statement',
                    'sheets' => ['summary', 'details', 'charts'],
                ],
                'compliance_report' => [
                    'format' => 'pdf',
                    'template' => 'reports.compliance_report',
                    'security' => [
                        'password_required' => true,
                        'watermark' => 'CONFIDENTIAL',
                    ]
                ]
            ]
        ]);
    }

    /**
     * Registra la configuración de reportes de cumplimiento
     */
    private function registerComplianceReporting(): void
    {
        Config::set('reporting.compliance', [
            'enabled' => true,
            'automated_generation' => true,
            'regulatory_frameworks' => [
                'hipaa' => [
                    'enabled' => true,
                    'reports' => [
                        'security_incident_report',
                        'breach_notification_report',
                        'access_audit_report',
                        'risk_assessment_report',
                    ],
                    'frequency' => 'monthly',
                ],
                'gdpr' => [
                    'enabled' => env('GDPR_COMPLIANCE', false),
                    'reports' => [
                        'data_processing_report',
                        'consent_management_report',
                        'data_breach_report',
                        'subject_rights_report',
                    ],
                    'frequency' => 'quarterly',
                ],
                'sox' => [
                    'enabled' => env('SOX_COMPLIANCE', false),
                    'reports' => [
                        'financial_controls_report',
                        'audit_trail_report',
                        'segregation_of_duties_report',
                    ],
                    'frequency' => 'quarterly',
                ],
                'joint_commission' => [
                    'enabled' => true,
                    'reports' => [
                        'patient_safety_report',
                        'quality_improvement_report',
                        'medication_management_report',
                        'infection_control_report',
                    ],
                    'frequency' => 'monthly',
                ]
            ],
            'audit_trail' => [
                'enabled' => true,
                'detailed_logging' => true,
                'retention_years' => 7,
                'encryption' => true,
            ]
        ]);
    }

    /**
     * Registra la configuración de métricas de calidad
     */
    private function registerQualityMetrics(): void
    {
        Config::set('reporting.quality_metrics', [
            'enabled' => true,
            'measurement_frameworks' => [
                'hedis' => [
                    'enabled' => true,
                    'measures' => [
                        'diabetes_care',
                        'cardiovascular_care',
                        'preventive_care',
                        'medication_management',
                    ]
                ],
                'cms_quality_measures' => [
                    'enabled' => true,
                    'measures' => [
                        'readmission_rates',
                        'mortality_rates',
                        'patient_safety_indicators',
                        'patient_experience',
                    ]
                ],
                'leapfrog' => [
                    'enabled' => true,
                    'measures' => [
                        'hospital_safety_grade',
                        'medication_safety',
                        'infections',
                        'staffing',
                    ]
                ]
            ],
            'benchmarking' => [
                'enabled' => true,
                'external_benchmarks' => true,
                'peer_comparison' => true,
                'trend_analysis' => true,
            ],
            'improvement_tracking' => [
                'enabled' => true,
                'action_plans' => true,
                'progress_monitoring' => true,
                'outcome_measurement' => true,
            ]
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ReportingServiceInterface::class,
            DashboardInterface::class,
            ExportInterface::class,
            'reporting.medical',
            'reporting.statistical',
            'reporting.compliance',
            'reporting.financial',
            'reporting.operational',
            'reporting.quality',
            'reporting.patient_outcomes',
            'reporting.performance',
            'reporting.audit',
            'reporting.dashboard',
            'reporting.export',
        ];
    }
}
