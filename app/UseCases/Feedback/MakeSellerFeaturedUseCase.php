<?php

namespace App\UseCases\Feedback;

use App\Domain\Entities\FeedbackEntity;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;

class MakeSellerFeaturedUseCase
{
    private ?SellerRepositoryInterface $sellerRepository;

    public function __construct(?SellerRepositoryInterface $sellerRepository = null)
    {
        $this->sellerRepository = $sellerRepository;
    }

    /**
     * Make a seller featured when their feedback is approved
     */
    public function execute(FeedbackEntity $feedback, int $featuredDays = 15): ?Seller
    {
        try {
            // Only process if feedback has a seller_id (came from a seller)
            if (! $feedback->getSellerId()) {
                Log::info('Feedback is not from a seller, skipping featured promotion');

                return null;
            }

            // Find the seller
            $seller = null;
            if ($this->sellerRepository) {
                $sellerEntity = $this->sellerRepository->findById($feedback->getSellerId());
                if ($sellerEntity) {
                    $seller = Seller::find($sellerEntity->getId());
                }
            } else {
                // Fallback to direct model access
                $seller = Seller::find($feedback->getSellerId());
            }

            if (! $seller) {
                Log::warning('Seller not found for feedback', ['seller_id' => $feedback->getSellerId()]);

                return null;
            }

            // Check if seller is active
            if ($seller->status !== 'active') {
                Log::info('Seller is not active, cannot make featured', [
                    'seller_id' => $seller->id,
                    'status' => $seller->status,
                ]);

                return null;
            }

            // If already featured with a longer or equal duration, don't override
            if ($seller->isCurrentlyFeatured()) {
                $currentExpiresAt = $seller->featured_expires_at;
                $newExpiresAt = now()->addDays($featuredDays);

                // Only extend if the new expiration is later
                if ($currentExpiresAt && $newExpiresAt->greaterThan($currentExpiresAt)) {
                    $seller->update([
                        'featured_expires_at' => $newExpiresAt,
                        'featured_reason' => 'feedback',
                    ]);
                    Log::info('Extended seller featured status', [
                        'seller_id' => $seller->id,
                        'new_expires_at' => $newExpiresAt,
                    ]);
                } else {
                    Log::info('Seller already featured with sufficient time', [
                        'seller_id' => $seller->id,
                        'current_expires_at' => $currentExpiresAt,
                    ]);
                }

                return $seller;
            }

            // Make the seller featured
            $seller->makeFeatured($featuredDays, 'feedback');

            Log::info('Seller made featured due to approved feedback', [
                'seller_id' => $seller->id,
                'feedback_id' => $feedback->getId(),
                'featured_days' => $featuredDays,
                'expires_at' => $seller->featured_expires_at,
            ]);

            return $seller;

        } catch (\Exception $e) {
            Log::error('Error making seller featured', [
                'feedback_id' => $feedback->getId(),
                'seller_id' => $feedback->getSellerId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw the exception, just log it and return null
            // so the feedback approval process can continue
            return null;
        }
    }
}
