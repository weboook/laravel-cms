<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Content Request
 *
 * Base form request for content retrieval operations.
 */
class ContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view-content');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'locale' => 'sometimes|string|max:10|in:' . implode(',', config('cms.locales', ['en'])),
            'include' => 'sometimes|string|max:200',
            'fields' => 'sometimes|string|max:200',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'locale.in' => 'The selected locale is not supported.',
            'include.string' => 'Include parameter must be a string.',
            'fields.string' => 'Fields parameter must be a string.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize include parameter
        if ($this->has('include')) {
            $this->merge([
                'include' => $this->sanitizeCommaSeparated($this->input('include')),
            ]);
        }

        // Sanitize fields parameter
        if ($this->has('fields')) {
            $this->merge([
                'fields' => $this->sanitizeCommaSeparated($this->input('fields')),
            ]);
        }
    }

    /**
     * Sanitize comma-separated values.
     */
    private function sanitizeCommaSeparated(string $value): string
    {
        return implode(',', array_filter(array_map('trim', explode(',', $value))));
    }
}