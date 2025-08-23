<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFavoriteNotificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only regular users can update favorite notifications, not admins or sellers
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
            'notify_price_change' => 'required|boolean',
            'notify_promotion' => 'required|boolean',
            'notify_low_stock' => 'required|boolean',
        ];
    }

    /**
     * Check if the authenticated user is a seller or admin
     */
    private function isSellerOrAdmin(): bool
    {
        $user = auth()->user();

        if (method_exists($user, 'isSeller') && $user->isSeller()) {
            return true;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return false;
    }
}
