<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleFavoriteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only regular users can add/remove favorites, not admins or sellers
        return auth()->check() && ! $this->isSellerOrAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.notify_price_change' => 'sometimes|boolean',
            'notification_preferences.notify_promotion' => 'sometimes|boolean',
            'notification_preferences.notify_low_stock' => 'sometimes|boolean',
        ];
    }

    /**
     * Check if the authenticated user is a seller or admin
     */
    private function isSellerOrAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSeller') && $user->isSeller()) {
            return true;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return false;
    }
}
