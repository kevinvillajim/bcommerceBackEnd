<?php

namespace App\UseCases\Rating;

use App\Domain\Entities\RatingEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\Rating;

class RateUserUseCase
{
    private RatingRepositoryInterface $ratingRepository;

    private SellerRepositoryInterface $sellerRepository;

    private OrderRepositoryInterface $orderRepository;

    private UserRepositoryInterface $userRepository;

    /**
     * Constructor
     */
    public function __construct(
        RatingRepositoryInterface $ratingRepository,
        SellerRepositoryInterface $sellerRepository,
        OrderRepositoryInterface $orderRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->ratingRepository = $ratingRepository;
        $this->sellerRepository = $sellerRepository;
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Execute the use case
     */
    public function execute(
        int $sellerId,
        int $userId,
        float $rating,
        ?int $orderId = null,
        ?string $title = null,
        ?string $comment = null
    ): RatingEntity {
        // Validate rating value
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        // Check if seller exists
        $seller = $this->sellerRepository->findById($sellerId);
        if (! $seller) {
            throw new \InvalidArgumentException('Seller not found');
        }

        // Check if user exists
        $user = $this->userRepository->findById($userId);
        if (! $user) {
            throw new \InvalidArgumentException('User not found');
        }

        // Get the actual seller user ID
        $sellerUserId = $seller->getUserId();

        // If order ID is provided, validate it
        if ($orderId) {
            $order = $this->orderRepository->findById($orderId);
            if (! $order) {
                throw new \InvalidArgumentException('Order not found');
            }

            // Check if order is from this seller
            if ($order->getSellerId() !== $sellerId) {
                throw new \InvalidArgumentException('Order is not from this seller');
            }

            // Check if order is to this user
            if ($order->getUserId() !== $userId) {
                throw new \InvalidArgumentException('Order is not for this user');
            }

            // Check if order is completed
            if ($order->getStatus() !== 'completed' && $order->getStatus() !== 'delivered') {
                throw new \InvalidArgumentException('Can only rate completed or delivered orders');
            }

            // Check if seller has already rated this user for this order
            if ($this->ratingRepository->hasSellerRatedUser($sellerUserId, $userId, $orderId)) {
                throw new \InvalidArgumentException('Seller has already rated this user for this order');
            }
        } else {
            // Check if seller has already rated this user (without an order)
            if ($this->ratingRepository->hasSellerRatedUser($sellerUserId, $userId)) {
                throw new \InvalidArgumentException('Seller has already rated this user');
            }
        }

        // Create rating entity
        $ratingEntity = new RatingEntity(
            $sellerUserId, // The user ID is the seller's user ID
            $rating,
            Rating::TYPE_SELLER_TO_USER,
            $sellerId,
            $orderId,
            null, // No product ID for user ratings
            $title,
            $comment,
            Rating::STATUS_PENDING // New ratings start as pending
        );

        // Save rating
        return $this->ratingRepository->create($ratingEntity);
    }
}
