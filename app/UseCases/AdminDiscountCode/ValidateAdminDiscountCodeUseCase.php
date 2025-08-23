<?php

namespace App\UseCases\AdminDiscountCode;

use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use Exception;

class ValidateAdminDiscountCodeUseCase
{
    private AdminDiscountCodeRepositoryInterface $repository;

    public function __construct(AdminDiscountCodeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $code, ?int $productId = null): array
    {
        try {
            // Find discount code
            $entity = $this->repository->findByCode(strtoupper(trim($code)));

            if (! $entity) {
                return [
                    'success' => false,
                    'valid' => false,
                    'message' => 'Discount code not found',
                ];
            }

            // Check if code is already used
            if ($entity->isUsed()) {
                return [
                    'success' => false,
                    'valid' => false,
                    'message' => 'This discount code has already been used',
                ];
            }

            // Check if code is expired
            if ($entity->isExpired()) {
                return [
                    'success' => false,
                    'valid' => false,
                    'message' => 'This discount code has expired',
                ];
            }

            // Calculate discount amount if product price is provided
            $discountAmount = null;
            $originalPrice = null;
            $finalPrice = null;

            if ($productId && class_exists('\App\Models\Product')) {
                $product = \App\Models\Product::find($productId);
                if ($product) {
                    $originalPrice = $product->price;
                    $discountAmount = ($originalPrice * $entity->getDiscountPercentage()) / 100;
                    $finalPrice = $originalPrice - $discountAmount;
                }
            }

            return [
                'success' => true,
                'valid' => true,
                'message' => 'Discount code is valid',
                'data' => [
                    'code' => $entity->toArray(),
                    'discount_percentage' => $entity->getDiscountPercentage(),
                    'discount_amount' => $discountAmount,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
                ],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'message' => 'Error validating discount code: '.$e->getMessage(),
            ];
        }
    }
}
