<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Sync Request
 *
 * Form request for synchronizing translations and content.
 */
class SyncRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('sync-translations');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                'max:10',
                Rule::in(array_keys(config('cms.locales', ['en' => 'English']))),
            ],
            'source' => 'sometimes|string|in:files,database,external,api',
            'groups' => 'sometimes|array',
            'groups.*' => 'string|max:100|regex:/^[a-zA-Z0-9._-]+$/',
            'force' => 'sometimes|boolean',
            'backup' => 'sometimes|boolean',
            'dry_run' => 'sometimes|boolean',
            'strategy' => 'sometimes|string|in:merge,overwrite,skip_existing',
            'external_config' => 'sometimes|array',
            'external_config.api_key' => 'sometimes|string|max:255',
            'external_config.endpoint' => 'sometimes|url|max:2048',
            'external_config.format' => 'sometimes|string|in:json,xml,csv,po',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'locale.required' => 'Locale is required for synchronization.',
            'locale.in' => 'The selected locale is not supported.',
            'source.in' => 'Source must be one of: files, database, external, api.',
            'groups.array' => 'Groups must be an array.',
            'groups.*.regex' => 'Group names may only contain letters, numbers, dots, underscores, and hyphens.',
            'strategy.in' => 'Strategy must be one of: merge, overwrite, skip_existing.',
            'external_config.endpoint.url' => 'External endpoint must be a valid URL.',
            'external_config.format.in' => 'External format must be one of: json, xml, csv, po.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize locale
        if ($this->has('locale')) {
            $this->merge([
                'locale' => strtolower(trim($this->input('locale')))
            ]);
        }

        // Sanitize groups
        if ($this->has('groups')) {
            $groups = array_filter(array_map(function ($group) {
                $group = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($group));
                return strtolower($group);
            }, $this->input('groups', [])));

            $this->merge(['groups' => array_unique($groups)]);
        }

        // Set defaults
        $this->merge([
            'source' => $this->input('source', 'files'),
            'force' => $this->input('force', false),
            'backup' => $this->input('backup', true),
            'dry_run' => $this->input('dry_run', false),
            'strategy' => $this->input('strategy', 'merge'),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateSourceAccess($validator);
            $this->validateExternalConfig($validator);
            $this->validateSyncLimits($validator);
        });
    }

    /**
     * Validate source access permissions.
     */
    private function validateSourceAccess($validator): void
    {
        $source = $this->input('source');

        switch ($source) {
            case 'files':
                if (!Gate::allows('access-translation-files')) {
                    $validator->errors()->add('source', 'You do not have permission to sync from files.');
                }
                break;

            case 'external':
            case 'api':
                if (!Gate::allows('sync-external-translations')) {
                    $validator->errors()->add('source', 'You do not have permission to sync from external sources.');
                }

                if (!$this->has('external_config')) {
                    $validator->errors()->add('external_config', 'External configuration is required for external sync.');
                }
                break;

            case 'database':
                if (!Gate::allows('sync-database-translations')) {
                    $validator->errors()->add('source', 'You do not have permission to sync from database.');
                }
                break;
        }
    }

    /**
     * Validate external configuration.
     */
    private function validateExternalConfig($validator): void
    {
        $source = $this->input('source');

        if (!in_array($source, ['external', 'api'])) {
            return;
        }

        $config = $this->input('external_config', []);

        // Validate required fields based on source type
        if ($source === 'api') {
            if (empty($config['endpoint'])) {
                $validator->errors()->add('external_config.endpoint', 'API endpoint is required for API sync.');
            }

            if (empty($config['api_key'])) {
                $validator->errors()->add('external_config.api_key', 'API key is required for API sync.');
            }
        }

        // Validate endpoint security
        if (isset($config['endpoint'])) {
            $endpoint = $config['endpoint'];

            // Ensure HTTPS for external APIs
            if (!str_starts_with($endpoint, 'https://')) {
                $validator->errors()->add('external_config.endpoint', 'External endpoints must use HTTPS.');
            }

            // Check against blocked domains
            $blockedDomains = config('cms.sync.blocked_domains', []);
            $parsedUrl = parse_url($endpoint);

            if (isset($parsedUrl['host'])) {
                $host = strtolower($parsedUrl['host']);

                foreach ($blockedDomains as $blocked) {
                    if (str_contains($host, strtolower($blocked))) {
                        $validator->errors()->add('external_config.endpoint', 'Domain is not allowed for sync.');
                        break;
                    }
                }
            }
        }

        // Validate API key format
        if (isset($config['api_key'])) {
            $apiKey = $config['api_key'];

            if (strlen($apiKey) < 10) {
                $validator->errors()->add('external_config.api_key', 'API key appears to be too short.');
            }

            // Check for common invalid patterns
            if (in_array(strtolower($apiKey), ['test', 'demo', 'example', 'placeholder'])) {
                $validator->errors()->add('external_config.api_key', 'API key appears to be a placeholder.');
            }
        }
    }

    /**
     * Validate sync operation limits.
     */
    private function validateSyncLimits($validator): void
    {
        $userId = $this->user()->id;

        // Check daily sync limit
        $dailyLimit = config('cms.sync.daily_limit', 10);
        $dailyCount = cache()->get("daily_syncs:{$userId}:" . date('Y-m-d'), 0);

        if ($dailyCount >= $dailyLimit) {
            $validator->errors()->add('source', 'Daily sync limit exceeded.');
        }

        // Check hourly sync limit
        $hourlyLimit = config('cms.sync.hourly_limit', 3);
        $hourlyCount = cache()->get("hourly_syncs:{$userId}:" . date('Y-m-d-H'), 0);

        if ($hourlyCount >= $hourlyLimit) {
            $validator->errors()->add('source', 'Hourly sync limit exceeded.');
        }

        // Check concurrent sync operations
        $maxConcurrent = config('cms.sync.max_concurrent', 2);
        $currentSyncs = cache()->get("active_syncs:{$userId}", 0);

        if ($currentSyncs >= $maxConcurrent) {
            $validator->errors()->add('source', 'Maximum concurrent sync operations reached.');
        }

        // Validate groups limit
        $groups = $this->input('groups', []);
        $maxGroups = config('cms.sync.max_groups_per_sync', 20);

        if (count($groups) > $maxGroups) {
            $validator->errors()->add('groups', "Maximum {$maxGroups} groups allowed per sync operation.");
        }
    }

    /**
     * Get validated data with sync tracking.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Update sync counters if not dry run
        if (!$validated['dry_run']) {
            $userId = $this->user()->id;
            $today = date('Y-m-d');
            $currentHour = date('Y-m-d-H');

            $dailyKey = "daily_syncs:{$userId}:{$today}";
            $hourlyKey = "hourly_syncs:{$userId}:{$currentHour}";

            cache()->increment($dailyKey, 1);
            cache()->put($dailyKey, cache()->get($dailyKey), now()->endOfDay());

            cache()->increment($hourlyKey, 1);
            cache()->put($hourlyKey, cache()->get($hourlyKey), now()->endOfHour());

            // Track active sync
            cache()->increment("active_syncs:{$userId}", 1);
        }

        return $validated;
    }
}