<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:500',
            'generate_discount' => 'nullable|boolean',
            'validity_days' => 'nullable|integer|min:1|max:365',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Please provide a status for the feedback',
            'status.in' => 'Status must be either approved or rejected',
            'admin_notes.max' => 'Notes should not exceed 500 characters',
            'validity_days.min' => 'Validity days must be at least 1 day',
            'validity_days.max' => 'Validity days cannot exceed 365 days',
        ];
    }
}
