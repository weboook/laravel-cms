<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Restore Request
 *
 * Form request for restoring content from historical revisions.
 */
class RestoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('restore-content');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'backup' => 'sometimes|boolean',
            'force' => 'sometimes|boolean',
            'notify' => 'sometimes|boolean',
            'reason' => 'sometimes|string|max:500',
            'validate_data' => 'sometimes|boolean',
            'merge_conflicts' => 'sometimes|string|in:auto,manual,abort',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'Reason must not exceed 500 characters.',
            'merge_conflicts.in' => 'Merge conflicts strategy must be one of: auto, manual, abort.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'backup' => $this->input('backup', true),
            'force' => $this->input('force', false),
            'notify' => $this->input('notify', true),
            'validate_data' => $this->input('validate_data', true),
            'merge_conflicts' => $this->input('merge_conflicts', 'abort'),
        ]);

        // Sanitize reason
        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim(strip_tags($this->input('reason')))
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateRevisionAccess($validator);
            $this->validateRestorePermissions($validator);
        });
    }

    /**
     * Validate revision access.
     */
    private function validateRevisionAccess($validator): void
    {
        $revisionId = $this->route('id');

        if (!$revisionId) {
            $validator->errors()->add('revision', 'Revision ID is required.');
            return;
        }

        $revision = \Webook\LaravelCMS\Models\ContentHistory::find($revisionId);

        if (!$revision) {
            $validator->errors()->add('revision', 'Revision not found.');
            return;
        }

        // Check if user can access this revision
        if (!$this->user()->can('view', $revision)) {
            $validator->errors()->add('revision', 'You do not have permission to access this revision.');
        }

        // Check revision age
        $maxAge = config('cms.history.max_restore_age_days', 30);
        if ($revision->created_at->diffInDays(now()) > $maxAge) {
            if (!$this->input('force', false)) {
                $validator->errors()->add('revision', "Revision is older than {$maxAge} days. Use force option to restore.");
            }
        }
    }

    /**
     * Validate restore permissions and constraints.
     */
    private function validateRestorePermissions($validator): void
    {
        $userId = $this->user()->id;

        // Check daily restore limit
        $dailyLimit = config('cms.history.daily_restore_limit', 5);
        $dailyCount = cache()->get("daily_restores:{$userId}:" . date('Y-m-d'), 0);

        if ($dailyCount >= $dailyLimit) {
            $validator->errors()->add('restore', 'Daily restore limit exceeded.');
        }

        // Check if content is currently being edited
        $revisionId = $this->route('id');
        if ($revisionId) {
            $revision = \Webook\LaravelCMS\Models\ContentHistory::find($revisionId);

            if ($revision) {
                $contentLock = cache()->get("content_lock:{$revision->content_type}:{$revision->content_id}");

                if ($contentLock && $contentLock !== $userId) {
                    $validator->errors()->add('restore', 'Content is currently being edited by another user.');
                }
            }
        }
    }

    /**
     * Get validated data with restore tracking.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Update restore counter
        $userId = $this->user()->id;
        $today = date('Y-m-d');
        $dailyKey = "daily_restores:{$userId}:{$today}";

        cache()->increment($dailyKey, 1);
        cache()->put($dailyKey, cache()->get($dailyKey), now()->endOfDay());

        return $validated;
    }
}