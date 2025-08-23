<?php

namespace App\UseCases\AdminDiscountCode;

use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use Exception;

class ApplyAdminDiscountCodeUseCase
{
    private AdminDiscountCodeRepositoryInterface $repository;

    public function __construct(AdminDiscountCodeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $code, int $userId, int $productId): array
    {
        try {
            // Find discount code
            $entity = $this->repository->findByCode(strtoupper(trim($code)));

            if (! $entity) {
                return [
                    'success' => false,
                    'message' => 'Discount code not found',
                ];
            }

            // Check if code is already used
            if ($entity->isUsed()) {
                return [
                    'success' => false,
                    'message' => 'This discount code has already been used',
                ];
            }

            // Check if code is expired
            if ($entity->isExpired()) {
                return [
                    'success' => false,
                    'message' => 'This discount code has expired',
                ];
            }

            // Get product details
            $product = \App\Models\Product::find($productId);
            if (! $product) {
                return [
                    'success' => false,
                    'message' => 'Product not found',
                ];
            }

            // Calculate discount
            $originalPrice = $product->price;
            $discountAmount = ($originalPrice * $entity->getDiscountPercentage()) / 100;
            $finalPrice = $originalPrice - $discountAmount;

            // Mark code as used
            $entity->markAsUsed($userId, $productId, date('Y-m-d H:i:s'));

            // Save changes
            $this->repository->update($entity);

            return [
                'success' => true,
                'message' => 'Discount code applied successfully',
                'data' => [
                    'code' => $entity->toArray(),
                    'discount_percentage' => $entity->getDiscountPercentage(),
                    'discount_amount' => $discountAmount,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                    ],
                ],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error applying discount code: '.$e->getMessage(),
            ];
        }
    }
}
