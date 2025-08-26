<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controlador base para el sistema de gestión de clínica dental
 * Maneja respuestas estandardizadas, logging de seguridad y actividades médicas
 * Sistema interno solo para personal de la clínica (admin, doctores, secretarias, operadores)
 */
abstract class Controller
{
    /**
     * Respuesta exitosa para operaciones de clínica dental
     */
    protected function successResponse($data = null, string $message = 'Operación exitosa en la clínica', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'clinic_system' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Respuesta de error para operaciones de clínica dental
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'clinic_system' => true,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Log de errores médicos para auditoría
        if ($code >= 400) {
            $this->logMedicalSystemError($message, $code, $errors);
        }

        return response()->json($response, $code);
    }

    /**
     * Respuesta de error de validación específica para datos médicos
     */
    protected function validationErrorResponse($validator): JsonResponse
    {
        return $this->errorResponse(
            'Error en validación de datos médicos - Revise la información ingresada',
            422,
            $validator->errors()
        );
    }

    /**
     * Respuesta de recurso no encontrado en sistema médico
     */
    protected function notFoundResponse(string $resource = 'Registro médico'): JsonResponse
    {
        return $this->errorResponse(
            "{$resource} no encontrado en el sistema de la clínica",
            404
        );
    }

    /**
     * Respuesta de no autorizado para personal de clínica
     */
    protected function unauthorizedResponse(string $message = 'Personal no autorizado - Inicie sesión'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Respuesta de acceso denegado para roles de clínica
     */
    protected function forbiddenResponse(string $message = 'Acceso denegado - Rol insuficiente para esta operación médica'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Log de eventos de seguridad en sistema médico
     */
    protected function logSecurityEvent(string $event, Request $request, array $data = []): void
    {
        $user = $request->user();
        
        Log::channel('security')->info("[CLÍNICA DENTAL] {$event}", array_merge([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => $user?->rol,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
            'timestamp' => now()->toISOString(),
            'system' => 'dental_clinic_internal',
        ], $data));
    }

    /**
     * Log de actividad médica y administrativa
     */
    protected function logMedicalActivity(string $action, string $model, $modelId, Request $request, array $extraData = []): void
    {
        $user = $request->user();
        
        Log::info("[ACTIVIDAD MÉDICA] {$action}", array_merge([
            'action' => $action,
            'medical_entity' => $model,
            'entity_id' => $modelId,
            'staff_id' => $user?->id,
            'staff_name' => $user?->name,
            'staff_role' => $user?->rol,
            'doctor_id' => $user?->doctor_id,
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString(),
            'clinic_system' => true,
        ], $extraData));
    }

    /**
     * Log de errores del sistema médico
     */
    protected function logMedicalSystemError(string $message, int $code, $errors = null): void
    {
        Log::error("[ERROR SISTEMA CLÍNICA] {$message}", [
            'error_code' => $code,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
            'system' => 'dental_clinic_internal',
        ]);
    }

    /**
     * Respuesta paginada para listas médicas (pacientes, turnos, etc.)
     */
    protected function paginatedResponse($paginator, string $message = 'Registros médicos obtenidos correctamente'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
                'showing' => "Mostrando {$paginator->firstItem()}-{$paginator->lastItem()} de {$paginator->total()} registros",
            ],
            'timestamp' => now()->toISOString(),
            'clinic_system' => true,
        ]);
    }

    /**
     * Manejo seguro de acciones médicas con try-catch
     */
    protected function handleMedicalAction(callable $action, string $operation = 'operación médica'): JsonResponse
    {
        try {
            return $action();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Registro médico');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->logSecurityEvent("Acceso denegado en {$operation}", request(), [
                'error' => $e->getMessage(),
            ]);
            return $this->forbiddenResponse("Sin permisos para realizar esta {$operation}");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator);
        } catch (\PDOException $e) {
            Log::error("[ERROR BD CLÍNICA] Error de base de datos en {$operation}", [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'timestamp' => now()->toISOString(),
            ]);
            return $this->errorResponse(
                'Error de base de datos - Contacte al administrador del sistema',
                500
            );
        } catch (\Exception $e) {
            Log::error("[ERROR SISTEMA CLÍNICA] Error crítico en {$operation}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString(),
                'user_id' => request()->user()?->id,
            ]);

            return $this->errorResponse(
                config('app.debug') 
                    ? "Error en {$operation}: {$e->getMessage()}" 
                    : 'Error interno del sistema de clínica - Contacte al administrador',
                500
            );
        }
    }

    /**
     * Validar horarios de atención de la clínica
     */
    protected function validateBusinessHours(): bool
    {
        $now = now();
        $hour = $now->hour;
        $isWeekend = $now->isWeekend();
        
        // Clínica dental: Lunes a Sábado 8:00 - 20:00, Domingos cerrado
        if ($isWeekend && $now->isSunday()) {
            return false;
        }
        
        return $hour >= 8 && $hour < 20;
    }

    /**
     * Respuesta para fuera de horarios de atención
     */
    protected function outsideBusinessHoursResponse(): JsonResponse
    {
        return $this->errorResponse(
            'Operación fuera del horario de atención de la clínica (Lunes a Sábado 8:00-20:00)',
            403
        );
    }
}
