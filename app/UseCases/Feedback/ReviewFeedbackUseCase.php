<?php

namespace App\UseCases\Feedback;

use App\Domain\Entities\FeedbackEntity;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ReviewFeedbackUseCase
{
    private FeedbackRepositoryInterface $feedbackRepository;

    private MakeSellerFeaturedUseCase $makeSellerFeaturedUseCase;

    /**
     * ReviewFeedbackUseCase constructor.
     */
    public function __construct(
        FeedbackRepositoryInterface $feedbackRepository,
        MakeSellerFeaturedUseCase $makeSellerFeaturedUseCase
    ) {
        $this->feedbackRepository = $feedbackRepository;
        $this->makeSellerFeaturedUseCase = $makeSellerFeaturedUseCase;
    }

    /**
     * Approve feedback.
     */
    public function approve(int $feedbackId, int $adminId, ?string $notes = null): FeedbackEntity
    {
        try {
            $feedback = $this->feedbackRepository->findById($feedbackId);

            if (! $feedback) {
                throw new \InvalidArgumentException("Feedback with ID {$feedbackId} not found");
            }

            if ($feedback->getStatus() !== 'pending') {
                throw new \InvalidArgumentException('Feedback has already been reviewed');
            }

            // Actualizar el estado
            $feedback->approve($adminId, $notes);

            // Registrar el log para depuración
            Log::info('Approving feedback', [
                'feedback_id' => $feedbackId,
                'admin_id' => $adminId,
                'status' => $feedback->getStatus(),
                'reviewed_by' => $feedback->getReviewedBy(),
                'reviewed_at' => $feedback->getReviewedAt(),
            ]);

            $updatedFeedback = $this->feedbackRepository->update($feedback);

            // If feedback is from a seller and was approved, make the seller featured
            if ($updatedFeedback->getSellerId()) {
                $featuredSeller = $this->makeSellerFeaturedUseCase->execute($updatedFeedback, 15); // 15 days featured

                if ($featuredSeller) {
                    Log::info('Seller made featured due to approved feedback', [
                        'feedback_id' => $feedbackId,
                        'seller_id' => $updatedFeedback->getSellerId(),
                        'store_name' => $featuredSeller->store_name,
                    ]);
                }
            }

            return $updatedFeedback;
        } catch (\Exception $e) {
            Log::error('Error approving feedback: '.$e->getMessage(), [
                'feedback_id' => $feedbackId,
                'admin_id' => $adminId,
            ]);
            throw $e;
        }
    }

    /**
     * Reject feedback.
     */
    public function reject(int $feedbackId, int $adminId, ?string $notes = null): FeedbackEntity
    {
        try {
            $feedback = $this->feedbackRepository->findById($feedbackId);

            if (! $feedback) {
                throw new \InvalidArgumentException("Feedback with ID {$feedbackId} not found");
            }

            if ($feedback->getStatus() !== 'pending') {
                throw new \InvalidArgumentException('Feedback has already been reviewed');
            }

            // Actualizar el estado
            $feedback->reject($adminId, $notes);

            // Registrar el log para depuración
            Log::info('Rejecting feedback', [
                'feedback_id' => $feedbackId,
                'admin_id' => $adminId,
                'status' => $feedback->getStatus(),
                'reviewed_by' => $feedback->getReviewedBy(),
                'reviewed_at' => $feedback->getReviewedAt(),
            ]);

            return $this->feedbackRepository->update($feedback);
        } catch (\Exception $e) {
            Log::error('Error rejecting feedback: '.$e->getMessage(), [
                'feedback_id' => $feedbackId,
                'admin_id' => $adminId,
            ]);
            throw $e;
        }
    }
}
