<?php

namespace App\UseCases\Feedback;

use App\Domain\Entities\DiscountCodeEntity;
use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class GenerateDiscountCodeUseCase
{
    private DiscountCodeRepositoryInterface $discountCodeRepository;

    private FeedbackRepositoryInterface $feedbackRepository;

    private NotificationService $notificationService;

    /**
     * GenerateDiscountCodeUseCase constructor.
     */
    public function __construct(
        DiscountCodeRepositoryInterface $discountCodeRepository,
        FeedbackRepositoryInterface $feedbackRepository,
        NotificationService $notificationService
    ) {
        $this->discountCodeRepository = $discountCodeRepository;
        $this->feedbackRepository = $feedbackRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the use case.
     */
    public function execute(int $feedbackId, int $validityDays = 30): DiscountCodeEntity
    {
        try {
            // Log inicial para depuración
            Log::info('Generating discount code', [
                'feedback_id' => $feedbackId,
                'validity_days' => $validityDays,
            ]);

            // Check if feedback exists and is approved
            $feedback = $this->feedbackRepository->findById($feedbackId);

            if (! $feedback) {
                Log::error('Feedback not found', ['feedback_id' => $feedbackId]);
                throw new \InvalidArgumentException("Feedback with ID {$feedbackId} not found");
            }

            if ($feedback->getStatus() !== 'approved') {
                Log::error('Feedback not approved', [
                    'feedback_id' => $feedbackId,
                    'status' => $feedback->getStatus(),
                ]);
                throw new \InvalidArgumentException('Discount codes can only be generated for approved feedback');
            }

            // Check if a discount code already exists for this feedback
            $existingCode = $this->discountCodeRepository->findByFeedbackId($feedbackId);

            if ($existingCode) {
                Log::info('Discount code already exists', [
                    'feedback_id' => $feedbackId,
                    'code' => $existingCode->getCode(),
                ]);

                return $existingCode;
            }

            // Generate a unique code
            $code = $this->discountCodeRepository->generateUniqueCode();
            Log::info('Generated unique code', ['code' => $code]);

            // Calculate expiration date
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));

            // Create the discount code entity
            $discountCodeEntity = new DiscountCodeEntity(
                $feedbackId,
                $code,
                5.00, // 5% discount
                false,
                null,
                null,
                null,
                $expiresAt
            );

            // Save and return the discount code
            $result = $this->discountCodeRepository->create($discountCodeEntity);

            Log::info('Discount code created successfully', [
                'feedback_id' => $feedbackId,
                'code' => $result->getCode(),
                'expires_at' => $result->getExpiresAt(),
            ]);

            // Send notification to user
            $this->notificationService->sendDiscountCodeNotification(
                $feedback->getUserId(),
                $result->getCode(),
                (int) $result->getDiscountPercentage(),
                date('Y-m-d', strtotime($result->getExpiresAt()))
            );

            return $result;
        } catch (\Exception $e) {
            Log::error('Error generating discount code: '.$e->getMessage(), [
                'feedback_id' => $feedbackId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
