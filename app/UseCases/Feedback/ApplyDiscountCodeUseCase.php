<?php

namespace App\UseCases\Feedback;

use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;

class ApplyDiscountCodeUseCase
{
    private DiscountCodeRepositoryInterface $discountCodeRepository;

    private ProductRepositoryInterface $productRepository;

    /**
     * ApplyDiscountCodeUseCase constructor.
     */
    public function __construct(
        DiscountCodeRepositoryInterface $discountCodeRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->discountCodeRepository = $discountCodeRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Execute the use case.
     */
    public function execute(string $code, int $productId, int $userId): array
    {
        // Validate the discount code
        $discountCode = $this->discountCodeRepository->findByCode($code);

        if (! $discountCode) {
            return [
                'success' => false,
                'message' => 'Invalid discount code',
                'discount' => 0,
            ];
        }

        if (! $discountCode->isValid()) {
            return [
                'success' => false,
                'message' => $discountCode->isUsed() ?
                    'This discount code has already been used' :
                    'This discount code has expired',
                'discount' => 0,
            ];
        }

        // Get the product
        $product = $this->productRepository->findById($productId);

        if (! $product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'discount' => 0,
            ];
        }

        // Calculate the discount amount
        $originalPrice = $product->getPrice();
        $discountPercentage = $discountCode->getDiscountPercentage();
        $discountAmount = ($originalPrice * $discountPercentage) / 100;
        $finalPrice = $originalPrice - $discountAmount;

        // Mark the discount code as used
        $discountCode->markAsUsed($userId, $productId);
        $this->discountCodeRepository->update($discountCode);

        return [
            'success' => true,
            'message' => 'Discount applied successfully',
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'original_price' => $originalPrice,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Validate a discount code without applying it.
     */
    public function validate(string $code, int $productId): array
    {
        // Validate the discount code
        $discountCode = $this->discountCodeRepository->findByCode($code);

        if (! $discountCode) {
            return [
                'success' => false,
                'message' => 'Invalid discount code',
                'discount' => 0,
            ];
        }

        if (! $discountCode->isValid()) {
            return [
                'success' => false,
                'message' => $discountCode->isUsed() ?
                    'This discount code has already been used' :
                    'This discount code has expired',
                'discount' => 0,
            ];
        }

        // Get the product
        $product = $this->productRepository->findById($productId);

        if (! $product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'discount' => 0,
            ];
        }

        // Calculate the discount amount
        $originalPrice = $product->getPrice();
        $discountPercentage = $discountCode->getDiscountPercentage();
        $discountAmount = ($originalPrice * $discountPercentage) / 100;
        $finalPrice = $originalPrice - $discountAmount;

        return [
            'success' => true,
            'message' => 'Valid discount code',
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'original_price' => $originalPrice,
            'final_price' => $finalPrice,
        ];
    }
}
