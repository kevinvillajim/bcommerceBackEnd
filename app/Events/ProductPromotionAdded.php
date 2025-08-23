<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPromotionAdded
{
    use Dispatchable, SerializesModels;

    public int $productId;

    public float $discountPercentage;

    public ?string $promotionName;

    public ?\DateTime $expirationDate;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $productId,
        float $discountPercentage,
        ?string $promotionName = null,
        ?\DateTime $expirationDate = null
    ) {
        $this->productId = $productId;
        $this->discountPercentage = $discountPercentage;
        $this->promotionName = $promotionName;
        $this->expirationDate = $expirationDate;
    }
}
