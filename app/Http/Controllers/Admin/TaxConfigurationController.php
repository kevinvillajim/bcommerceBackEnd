<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaxConfigurationController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener configuración de impuestos
     * Este endpoint es público para que sellers y el frontend puedan acceder sin autenticación
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                'tax_rate' => $this->configService->getConfig('tax.rate', 15.0),
                'tax_name' => $this->configService->getConfig('tax.name', 'IVA'),
                'enabled' => $this->configService->getConfig('tax.enabled', true),
                'last_updated' => $this->configService->getConfig('tax.updated_at', now()->toISOString()),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración de impuestos:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar configuración de impuestos
     * Este método requiere autenticación de administrador
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_rate' => 'required|numeric|min:0|max:100', // Máximo 100% de impuesto
                'tax_name' => 'required|string|max:50',
                'enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxRate = $request->input('tax_rate');
            $taxName = $request->input('tax_name');
            $enabled = $request->input('enabled');

            // Guardar configuración
            $this->configService->setConfig('tax.rate', $taxRate);
            $this->configService->setConfig('tax.name', $taxName);
            $this->configService->setConfig('tax.enabled', $enabled);
            $this->configService->setConfig('tax.updated_at', now()->toISOString());

            // Actualizar versión para invalidar cache del frontend
            $this->configService->setConfig('tax.version', time());

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración de impuestos actualizada exitosamente',
                'data' => [
                    'tax_rate' => $taxRate,
                    'tax_name' => $taxName,
                    'enabled' => $enabled,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando configuración de impuestos:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Calcular impuestos para un monto dado
     */
    public function calculateTax(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $taxRate = $this->configService->getConfig('tax.rate', 15.0);
            $taxName = $this->configService->getConfig('tax.name', 'IVA');
            $enabled = $this->configService->getConfig('tax.enabled', true);

            if (! $enabled) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'original_amount' => $amount,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total_amount' => $amount,
                        'tax_name' => $taxName,
                        'tax_enabled' => false,
                    ],
                ]);
            }

            $taxAmount = ($amount * $taxRate) / 100;
            $totalAmount = $amount + $taxAmount;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'original_amount' => $amount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => round($taxAmount, 2),
                    'total_amount' => round($totalAmount, 2),
                    'tax_name' => $taxName,
                    'tax_enabled' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculando impuestos:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al calcular impuestos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }
}
