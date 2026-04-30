<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => $meta,
        ], $status);
    }

    protected function error(string $code, string $message, mixed $details = null, int $status = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status);
    }

    protected function envelopeFromException(\Throwable $e): JsonResponse
    {
        $code = match (true) {
            $e instanceof \Illuminate\Auth\AuthenticationException => 'UNAUTHENTICATED',
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 'FORBIDDEN',
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 'NOT_FOUND',
            $e instanceof \App\Domain\Floor\ZoneOverlapException => 'ZONE_OVERLAP',
            default => 'VALIDATION_ERROR',
        };

        $status = match ($code) {
            'UNAUTHENTICATED' => 401,
            'NOT_FOUND'       => 404,
            'FORBIDDEN'       => 403,
            'CONFLICT'        => 409,
            default           => 400,
        };

        return $this->error($code, $e->getMessage(), status: $status);
    }
}
