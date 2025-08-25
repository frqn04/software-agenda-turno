<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    /**
     * Success response method
     */
    protected function successResponse($data = null, string $message = 'Operación exitosa', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Error response method
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse($validator): JsonResponse
    {
        return $this->errorResponse(
            'Datos de validación incorrectos',
            422,
            $validator->errors()
        );
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $resource = 'Recurso'): JsonResponse
    {
        return $this->errorResponse(
            "{$resource} no encontrado",
            404
        );
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'No autorizado'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(string $message = 'Acceso denegado'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Log security event
     */
    protected function logSecurityEvent(string $event, Request $request, array $data = []): void
    {
        Log::channel('security')->info($event, array_merge([
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ], $data));
    }

    /**
     * Log user activity
     */
    protected function logActivity(string $action, string $model, $modelId, Request $request): void
    {
        Log::info('User activity', [
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Handle pagination response
     */
    protected function paginatedResponse($paginator, string $message = 'Datos obtenidos correctamente'): JsonResponse
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
            ],
        ]);
    }

    /**
     * Safe try-catch wrapper for controller actions
     */
    protected function handleAction(callable $action, string $operation = 'operación'): JsonResponse
    {
        try {
            return $action();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Illuminate\Authorization\AuthorizationException $e) {
            return $this->forbiddenResponse();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator);
        } catch (\Exception $e) {
            Log::error("Error en {$operation}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse(
                config('app.debug') 
                    ? "Error: {$e->getMessage()}" 
                    : 'Error interno del servidor',
                500
            );
        }
    }
}
