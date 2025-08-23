<?php

namespace App\UseCases\Feedback;

use App\Domain\Entities\FeedbackEntity;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;

class SubmitFeedbackUseCase
{
    private FeedbackRepositoryInterface $feedbackRepository;

    private ?SellerRepositoryInterface $sellerRepository;

    /**
     * SubmitFeedbackUseCase constructor.
     */
    public function __construct(
        FeedbackRepositoryInterface $feedbackRepository,
        ?SellerRepositoryInterface $sellerRepository = null
    ) {
        $this->feedbackRepository = $feedbackRepository;
        $this->sellerRepository = $sellerRepository;
    }

    /**
     * Execute the use case.
     */
    public function execute(
        int $userId,
        string $title,
        string $description,
        string $type = 'improvement'
    ): FeedbackEntity {
        // Check if user is a seller
        $sellerId = null;
        if ($this->sellerRepository) {
            $seller = $this->sellerRepository->findByUserId($userId);
            if ($seller) {
                $sellerId = $seller->getId();
            }
        }

        // Create feedback entity
        $feedbackEntity = new FeedbackEntity(
            $userId,
            $title,
            $description,
            $sellerId,
            $type
        );

        // Save feedback
        return $this->feedbackRepository->create($feedbackEntity);
    }
}
