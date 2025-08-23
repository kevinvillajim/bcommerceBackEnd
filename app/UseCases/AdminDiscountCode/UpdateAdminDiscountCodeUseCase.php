<?php

namespace App\UseCases\AdminDiscountCode;

use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use Exception;

class UpdateAdminDiscountCodeUseCase
{
    private AdminDiscountCodeRepositoryInterface $repository;

    public function __construct(AdminDiscountCodeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $id, array $data): array
    {
        try {
            // Find existing discount code
            $entity = $this->repository->findById($id);
            if (! $entity) {
                return [
                    'success' => false,
                    'message' => 'Discount code not found',
                ];
            }

            // Check if code is already used (prevent modification if used)
            if ($entity->isUsed()) {
                return [
                    'success' => false,
                    'message' => 'Cannot modify a discount code that has already been used',
                ];
            }

            // Validate discount percentage if provided
            if (isset($data['discount_percentage'])) {
                if ($data['discount_percentage'] < 5 || $data['discount_percentage'] > 50) {
                    return [
                        'success' => false,
                        'message' => 'Discount percentage must be between 5% and 50%',
                    ];
                }
                $entity->setDiscountPercentage((int) $data['discount_percentage']);
            }

            // Validate and update code if provided
            if (isset($data['code']) && $data['code'] !== $entity->getCode()) {
                $normalizedCode = strtoupper(trim($data['code']));

                // Check if new code already exists
                if ($this->repository->codeExists($normalizedCode, $id)) {
                    return [
                        'success' => false,
                        'message' => 'A discount code with this code already exists',
                    ];
                }

                $entity->setCode($normalizedCode);
            }

            // Validate and update expiration date if provided
            if (isset($data['expires_at'])) {
                if (strtotime($data['expires_at']) <= time()) {
                    return [
                        'success' => false,
                        'message' => 'Expiration date must be in the future',
                    ];
                }
                $entity->setExpiresAt($data['expires_at']);
            }

            // Update description if provided
            if (isset($data['description'])) {
                $entity->setDescription($data['description']);
            }

            // Save to repository
            $updatedEntity = $this->repository->update($entity);

            return [
                'success' => true,
                'message' => 'Discount code updated successfully',
                'data' => $updatedEntity->toArray(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating discount code: '.$e->getMessage(),
            ];
        }
    }
}
