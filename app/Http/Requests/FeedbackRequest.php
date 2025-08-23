<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:100',
            'description' => 'required|string|min:20|max:1000',
            'type' => 'required|string|in:bug,improvement,other',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for your feedback',
            'title.min' => 'The title should be at least 5 characters',
            'title.max' => 'The title should not exceed 100 characters',
            'description.required' => 'Please provide a detailed description',
            'description.min' => 'Please provide a more detailed description (at least 20 characters)',
            'description.max' => 'The description should not exceed 1000 characters',
            'type.required' => 'Please select a feedback type',
            'type.in' => 'Invalid feedback type selected',
        ];
    }
}
