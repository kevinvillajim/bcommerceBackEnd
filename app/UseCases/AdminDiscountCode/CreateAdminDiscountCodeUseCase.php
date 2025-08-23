<?php

namespace App\UseCases\AdminDiscountCode;

use App\Domain\Entities\AdminDiscountCodeEntity;
use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use Exception;

class CreateAdminDiscountCodeUseCase
{
    private AdminDiscountCodeRepositoryInterface $repository;

    public function __construct(AdminDiscountCodeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data): array
    {
        try {
            // Validate required fields
            $requiredFields = ['code', 'discount_percentage', 'expires_at', 'created_by'];
            foreach ($requiredFields as $field) {
                if (! isset($data[$field]) || empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field {$field} is required",
                    ];
                }
            }

            // Validate discount percentage range
            if ($data['discount_percentage'] < 5 || $data['discount_percentage'] > 50) {
                return [
                    'success' => false,
                    'message' => 'Discount percentage must be between 5% and 50%',
                ];
            }

            // Check if code already exists
            if ($this->repository->codeExists($data['code'])) {
                return [
                    'success' => false,
                    'message' => 'A discount code with this code already exists',
                ];
            }

            // Validate expiration date
            if (strtotime($data['expires_at']) <= time()) {
                return [
                    'success' => false,
                    'message' => 'Expiration date must be in the future',
                ];
            }

            // Create entity
            $entity = new AdminDiscountCodeEntity(
                strtoupper(trim($data['code'])), // Normalize code to uppercase
                (int) $data['discount_percentage'],
                $data['expires_at'],
                (int) $data['created_by'],
                false, // is_used
                null,  // used_by
                null,  // used_at
                null,  // used_on_product_id
                $data['description'] ?? null
            );

            // Save to repository
            $savedEntity = $this->repository->create($entity);

            return [
                'success' => true,
                'message' => 'Discount code created successfully',
                'data' => $savedEntity->toArray(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating discount code: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generate a random discount code
     */
    public static function generateRandomCode(): string
    {
        $prefixes = ['PROMO', 'DESCUENTO', 'OFERTA', 'REGALO', 'COMERSIA'];
        $prefix = $prefixes[array_rand($prefixes)];

        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $prefix.$suffix;
    }
}
