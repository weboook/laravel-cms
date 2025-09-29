<?php

/**
 * Laravel CMS - API Integration Examples
 *
 * This file demonstrates how to integrate with the Laravel CMS API
 * for headless CMS functionality, external integrations, and
 * programmatic content management.
 */

// =============================================================================
// API AUTHENTICATION
// =============================================================================

/**
 * Example 1: API Authentication
 *
 * Different methods to authenticate with the CMS API
 */

// Method 1: Session-based authentication (for web applications)
function authenticateWithSession($email, $password)
{
    $response = Http::post(config('cms.api_url') . '/auth/login', [
        'email' => $email,
        'password' => $password
    ]);

    if ($response->successful()) {
        $data = $response->json();
        session(['cms_token' => $data['access_token']]);
        return $data;
    }

    throw new \Exception('Authentication failed: ' . $response->body());
}

// Method 2: API Token authentication
function authenticateWithToken($apiToken)
{
    return Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiToken,
        'Accept' => 'application/json'
    ]);
}

// Method 3: Create long-lived personal access token
function createPersonalAccessToken($user, $permissions = [])
{
    return $user->createToken('API Token', $permissions)->plainTextToken;
}

/**
 * Example 2: API Client Class
 *
 * Reusable API client for CMS operations
 */

class CMSApiClient
{
    protected $baseUrl;
    protected $token;
    protected $http;

    public function __construct($baseUrl, $token = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/cms/api';
        $this->token = $token;
        $this->http = Http::baseUrl($this->baseUrl);

        if ($token) {
            $this->http = $this->http->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ]);
        }
    }

    public function login($email, $password)
    {
        $response = $this->http->post('/auth/login', [
            'email' => $email,
            'password' => $password
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->token = $data['access_token'];
            $this->http = $this->http->withHeaders([
                'Authorization' => 'Bearer ' . $this->token
            ]);
            return $data;
        }

        throw new \Exception('Login failed: ' . $response->body());
    }

    public function getText($key, $language = null)
    {
        $url = '/content/text/' . $key;
        if ($language) {
            $url .= '?language=' . $language;
        }

        $response = $this->http->get($url);
        return $response->successful() ? $response->json() : null;
    }

    public function updateText($key, $value, $language = 'en')
    {
        $response = $this->http->put('/content/text', [
            'key' => $key,
            'value' => $value,
            'language' => $language
        ]);

        return $response->json();
    }

    public function bulkUpdateText($content, $language = 'en')
    {
        $response = $this->http->post('/content/text/bulk', [
            'language' => $language,
            'content' => $content
        ]);

        return $response->json();
    }

    public function uploadImage($key, $file, $alt = null)
    {
        $response = $this->http->attach('file', $file, basename($file))
            ->post('/content/image/upload', [
                'key' => $key,
                'alt' => $alt,
                'resize' => true,
                'quality' => 85
            ]);

        return $response->json();
    }

    public function getTranslations($key = null, $language = null)
    {
        $params = [];
        if ($key) $params['key'] = $key;
        if ($language) $params['language'] = $language;

        $response = $this->http->get('/translations', $params);
        return $response->successful() ? $response->json() : null;
    }

    public function updateTranslations($key, $translations)
    {
        $response = $this->http->put("/translations/{$key}", [
            'translations' => $translations
        ]);

        return $response->json();
    }

    public function autoTranslate($key, $sourceLanguage, $targetLanguages, $service = 'google')
    {
        $response = $this->http->post('/translations/auto-translate', [
            'key' => $key,
            'source_language' => $sourceLanguage,
            'target_languages' => $targetLanguages,
            'service' => $service
        ]);

        return $response->json();
    }

    public function getFiles($type = null, $search = null)
    {
        $params = [];
        if ($type) $params['type'] = $type;
        if ($search) $params['search'] = $search;

        $response = $this->http->get('/files', $params);
        return $response->successful() ? $response->json() : null;
    }

    public function getAnalytics($period = 'week')
    {
        $response = $this->http->get('/analytics/usage', [
            'period' => $period
        ]);

        return $response->successful() ? $response->json() : null;
    }

    public function getSystemStatus()
    {
        $response = $this->http->get('/system/status');
        return $response->successful() ? $response->json() : null;
    }
}

// =============================================================================
// CONTENT MANAGEMENT VIA API
// =============================================================================

/**
 * Example 3: Headless CMS Implementation
 *
 * Using the API for headless CMS functionality
 */

class HeadlessCMSService
{
    protected $api;

    public function __construct(CMSApiClient $api)
    {
        $this->api = $api;
    }

    public function getPageContent($pageSlug, $language = 'en')
    {
        $content = [
            'meta' => $this->api->getText("pages.{$pageSlug}.meta.title", $language),
            'title' => $this->api->getText("pages.{$pageSlug}.title", $language),
            'subtitle' => $this->api->getText("pages.{$pageSlug}.subtitle", $language),
            'content' => $this->api->getText("pages.{$pageSlug}.content", $language),
            'hero_image' => $this->api->getText("pages.{$pageSlug}.hero_image", $language),
            'cta_text' => $this->api->getText("pages.{$pageSlug}.cta_text", $language),
            'cta_link' => $this->api->getText("pages.{$pageSlug}.cta_link", $language),
        ];

        return array_filter($content, function($item) {
            return $item !== null;
        });
    }

    public function getNavigationMenu($language = 'en')
    {
        $navItems = [
            'home' => ['url' => '/', 'label' => 'nav.home'],
            'about' => ['url' => '/about', 'label' => 'nav.about'],
            'services' => ['url' => '/services', 'label' => 'nav.services'],
            'contact' => ['url' => '/contact', 'label' => 'nav.contact'],
        ];

        $menu = [];
        foreach ($navItems as $key => $item) {
            $label = $this->api->getText($item['label'], $language);
            if ($label) {
                $menu[$key] = [
                    'url' => $item['url'],
                    'label' => $label['value'],
                    'active' => false
                ];
            }
        }

        return $menu;
    }

    public function getSiteSettings($language = 'en')
    {
        return [
            'site_name' => $this->api->getText('site.name', $language),
            'site_description' => $this->api->getText('site.description', $language),
            'contact_email' => $this->api->getText('contact.email', $language),
            'contact_phone' => $this->api->getText('contact.phone', $language),
            'social_facebook' => $this->api->getText('social.facebook', $language),
            'social_twitter' => $this->api->getText('social.twitter', $language),
            'logo' => $this->api->getText('site.logo', $language),
        ];
    }

    public function searchContent($query, $language = 'en')
    {
        // Implementation depends on your search API endpoint
        $response = Http::get($this->api->baseUrl . '/search', [
            'query' => $query,
            'language' => $language
        ]);

        return $response->successful() ? $response->json() : [];
    }
}

/**
 * Example 4: React/Vue.js Frontend Integration
 *
 * JavaScript code for frontend integration
 */

// JavaScript CMS API Client
?>
<script>
class CMSApiClient {
    constructor(baseUrl, token = null) {
        this.baseUrl = baseUrl.replace(/\/$/, '') + '/cms/api';
        this.token = token;
    }

    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const response = await fetch(url, {
            ...options,
            headers
        });

        if (!response.ok) {
            throw new Error(`API Error: ${response.status} ${response.statusText}`);
        }

        return response.json();
    }

    async getText(key, language = null) {
        const params = language ? `?language=${language}` : '';
        return this.request(`/content/text/${key}${params}`);
    }

    async updateText(key, value, language = 'en') {
        return this.request('/content/text', {
            method: 'PUT',
            body: JSON.stringify({ key, value, language })
        });
    }

    async bulkUpdateText(content, language = 'en') {
        return this.request('/content/text/bulk', {
            method: 'POST',
            body: JSON.stringify({ language, content })
        });
    }

    async uploadImage(key, file, alt = null) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('key', key);
        if (alt) formData.append('alt', alt);
        formData.append('resize', true);
        formData.append('quality', 85);

        return this.request('/content/image/upload', {
            method: 'POST',
            body: formData,
            headers: {
                // Don't set Content-Type for FormData
                'Authorization': `Bearer ${this.token}`
            }
        });
    }

    async getTranslations(key = null, language = null) {
        const params = new URLSearchParams();
        if (key) params.append('key', key);
        if (language) params.append('language', language);

        const queryString = params.toString();
        return this.request(`/translations${queryString ? '?' + queryString : ''}`);
    }
}

// React Component Example
const CMSTextComponent = ({ cmsKey, defaultValue = '', language = 'en', editable = false }) => {
    const [content, setContent] = React.useState(defaultValue);
    const [isEditing, setIsEditing] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(true);

    const api = new CMSApiClient('/api', localStorage.getItem('cms_token'));

    React.useEffect(() => {
        loadContent();
    }, [cmsKey, language]);

    const loadContent = async () => {
        try {
            setIsLoading(true);
            const response = await api.getText(cmsKey, language);
            if (response && response.value) {
                setContent(response.value);
            }
        } catch (error) {
            console.error('Failed to load content:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const saveContent = async (newContent) => {
        try {
            await api.updateText(cmsKey, newContent, language);
            setContent(newContent);
            setIsEditing(false);
        } catch (error) {
            console.error('Failed to save content:', error);
            alert('Failed to save content');
        }
    };

    if (isLoading) {
        return <div className="cms-loading">Loading...</div>;
    }

    if (editable && isEditing) {
        return (
            <div className="cms-editor">
                <textarea
                    value={content}
                    onChange={(e) => setContent(e.target.value)}
                    onBlur={() => saveContent(content)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && e.ctrlKey) {
                            saveContent(content);
                        }
                        if (e.key === 'Escape') {
                            setIsEditing(false);
                        }
                    }}
                    autoFocus
                />
            </div>
        );
    }

    return (
        <span
            className={`cms-content ${editable ? 'cms-editable' : ''}`}
            onClick={() => editable && setIsEditing(true)}
            data-cms-key={cmsKey}
        >
            {content || defaultValue}
        </span>
    );
};

// Vue.js Component Example
const CMSContentComponent = {
    props: {
        cmsKey: String,
        defaultValue: { type: String, default: '' },
        language: { type: String, default: 'en' },
        editable: { type: Boolean, default: false }
    },
    data() {
        return {
            content: this.defaultValue,
            isEditing: false,
            isLoading: true
        };
    },
    async mounted() {
        await this.loadContent();
    },
    methods: {
        async loadContent() {
            try {
                this.isLoading = true;
                const api = new CMSApiClient('/api', localStorage.getItem('cms_token'));
                const response = await api.getText(this.cmsKey, this.language);
                if (response && response.value) {
                    this.content = response.value;
                }
            } catch (error) {
                console.error('Failed to load content:', error);
            } finally {
                this.isLoading = false;
            }
        },
        async saveContent() {
            try {
                const api = new CMSApiClient('/api', localStorage.getItem('cms_token'));
                await api.updateText(this.cmsKey, this.content, this.language);
                this.isEditing = false;
            } catch (error) {
                console.error('Failed to save content:', error);
                alert('Failed to save content');
            }
        }
    },
    template: `
        <div v-if="isLoading" class="cms-loading">Loading...</div>
        <div v-else-if="editable && isEditing" class="cms-editor">
            <textarea
                v-model="content"
                @blur="saveContent"
                @keydown.ctrl.enter="saveContent"
                @keydown.escape="isEditing = false"
                ref="editor"
            ></textarea>
        </div>
        <span
            v-else
            :class="['cms-content', { 'cms-editable': editable }]"
            @click="editable && (isEditing = true)"
            :data-cms-key="cmsKey"
        >
            {{ content || defaultValue }}
        </span>
    `
};
</script>

<?php

// =============================================================================
// EXTERNAL INTEGRATIONS
// =============================================================================

/**
 * Example 5: Webhook Integration
 *
 * Setting up webhooks for external service integration
 */

class WebhookService
{
    protected $api;

    public function __construct(CMSApiClient $api)
    {
        $this->api = $api;
    }

    public function createWebhook($name, $url, $events, $secret = null)
    {
        $response = $this->api->http->post('/webhooks', [
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'secret' => $secret ?: Str::random(32),
            'active' => true
        ]);

        return $response->json();
    }

    public function handleWebhook($payload, $signature, $secret)
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        $data = json_decode($payload, true);

        switch ($data['event']) {
            case 'content.updated':
                $this->handleContentUpdated($data);
                break;

            case 'translation.updated':
                $this->handleTranslationUpdated($data);
                break;

            case 'file.uploaded':
                $this->handleFileUploaded($data);
                break;

            default:
                \Log::info('Unhandled webhook event: ' . $data['event']);
        }
    }

    protected function handleContentUpdated($data)
    {
        // Clear cache
        Cache::tags(['cms', 'content'])->flush();

        // Update search index
        if (class_exists('\Laravel\Scout\Searchable')) {
            \App\Models\SearchableContent::updateOrCreate(
                ['key' => $data['key'], 'language' => $data['language']],
                ['content' => $data['value']]
            );
        }

        // Notify team via Slack
        if ($slackUrl = config('services.slack.webhook_url')) {
            Http::post($slackUrl, [
                'text' => "Content updated: {$data['key']} by {$data['user']['name']}"
            ]);
        }
    }

    protected function handleTranslationUpdated($data)
    {
        // Clear translation cache
        Cache::tags(['cms', 'translations'])->flush();

        // Update localization files if needed
        $this->updateLocalizationFiles($data);
    }

    protected function handleFileUploaded($data)
    {
        // Process uploaded file
        if ($data['type'] === 'image') {
            // Generate additional image sizes
            $this->generateImageVariants($data['path']);
        }

        // Update CDN if needed
        if (config('cms.cdn.enabled')) {
            $this->uploadToCDN($data['path']);
        }
    }
}

/**
 * Example 6: Third-Party Service Integration
 *
 * Integrating with external services and APIs
 */

class ExternalServiceIntegrator
{
    protected $api;

    public function __construct(CMSApiClient $api)
    {
        $this->api = $api;
    }

    // Mailchimp integration for newsletter signups
    public function syncToMailchimp($email, $language = 'en')
    {
        $mailchimp = new \MailchimpMarketing\ApiClient();
        $mailchimp->setConfig([
            'apiKey' => config('services.mailchimp.api_key'),
            'server' => config('services.mailchimp.server')
        ]);

        // Get translated content for email
        $welcomeMessage = $this->api->getText("email.welcome", $language);
        $listId = config('services.mailchimp.list_id');

        try {
            $mailchimp->lists->addListMember($listId, [
                'email_address' => $email,
                'status' => 'subscribed',
                'language' => $language,
                'merge_fields' => [
                    'FNAME' => '',
                    'LANG' => strtoupper($language)
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Mailchimp sync failed: ' . $e->getMessage());
            return false;
        }
    }

    // Salesforce integration
    public function syncToSalesforce($contactData, $language = 'en')
    {
        $salesforce = new \Forrest\Providers\Laravel\ForrestServiceProvider();

        // Get translated field labels
        $fieldLabels = [
            'first_name' => $this->api->getText("salesforce.fields.first_name", $language),
            'last_name' => $this->api->getText("salesforce.fields.last_name", $language),
            'email' => $this->api->getText("salesforce.fields.email", $language),
        ];

        try {
            $salesforce->sobjects('Contact', [
                'method' => 'post',
                'body' => [
                    'FirstName' => $contactData['first_name'],
                    'LastName' => $contactData['last_name'],
                    'Email' => $contactData['email'],
                    'LeadSource' => 'Website',
                    'Language__c' => $language
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Salesforce sync failed: ' . $e->getMessage());
            return false;
        }
    }

    // Google Analytics integration
    public function trackContentView($key, $language = 'en')
    {
        if (!config('services.google_analytics.enabled')) {
            return;
        }

        $analytics = Google_Service_Analytics::new(\Google_Client::new());

        try {
            $analytics->management_customDimensions->insert(
                config('services.google_analytics.account_id'),
                config('services.google_analytics.property_id'),
                new \Google_Service_Analytics_CustomDimension([
                    'name' => 'CMS Content Key',
                    'scope' => 'HIT',
                    'active' => true
                ])
            );

            // Track the content view
            // Implementation depends on your GA setup
        } catch (\Exception $e) {
            \Log::error('Google Analytics tracking failed: ' . $e->getMessage());
        }
    }
}

/**
 * Example 7: Bulk Operations and Synchronization
 *
 * Performing bulk operations via API
 */

class BulkOperationService
{
    protected $api;

    public function __construct(CMSApiClient $api)
    {
        $this->api = $api;
    }

    public function importFromCSV($csvFile, $keyColumn, $valueColumn, $language = 'en')
    {
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);

        $keyIndex = array_search($keyColumn, $header);
        $valueIndex = array_search($valueColumn, $header);

        if ($keyIndex === false || $valueIndex === false) {
            throw new \Exception('Required columns not found in CSV');
        }

        $content = [];
        foreach ($csv as $row) {
            $key = $row[$keyIndex];
            $value = $row[$valueIndex];

            if (!empty($key) && !empty($value)) {
                $content[$key] = $value;
            }
        }

        // Bulk update via API
        return $this->api->bulkUpdateText($content, $language);
    }

    public function exportToCSV($keyPattern = '*', $languages = ['en'])
    {
        $translations = [];

        foreach ($languages as $language) {
            $content = $this->api->getTranslations($keyPattern, $language);
            if ($content && isset($content['data'])) {
                foreach ($content['data'] as $item) {
                    $translations[$item['key']][$language] = $item['value'];
                }
            }
        }

        $csv = "key," . implode(',', $languages) . "\n";

        foreach ($translations as $key => $values) {
            $row = [$key];
            foreach ($languages as $language) {
                $row[] = '"' . str_replace('"', '""', $values[$language] ?? '') . '"';
            }
            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    public function synchronizeWithExternalCMS($externalApiUrl, $mapping = [])
    {
        $externalData = Http::get($externalApiUrl)->json();
        $updates = [];

        foreach ($externalData as $item) {
            foreach ($mapping as $externalField => $cmsKey) {
                if (isset($item[$externalField])) {
                    $updates[$cmsKey] = $item[$externalField];
                }
            }
        }

        if (!empty($updates)) {
            return $this->api->bulkUpdateText($updates);
        }

        return ['updated' => 0, 'message' => 'No updates needed'];
    }

    public function migrateContent($fromLanguage, $toLanguage, $keyPatterns = ['*'])
    {
        $migrated = 0;
        $errors = [];

        foreach ($keyPatterns as $pattern) {
            try {
                $sourceContent = $this->api->getTranslations($pattern, $fromLanguage);

                if ($sourceContent && isset($sourceContent['data'])) {
                    foreach ($sourceContent['data'] as $item) {
                        // Check if target translation exists
                        $existing = $this->api->getText($item['key'], $toLanguage);

                        if (!$existing || empty($existing['value'])) {
                            // Auto-translate
                            $translated = $this->api->autoTranslate(
                                $item['key'],
                                $fromLanguage,
                                [$toLanguage]
                            );

                            if ($translated && $translated['success']) {
                                $migrated++;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Pattern {$pattern}: " . $e->getMessage();
            }
        }

        return compact('migrated', 'errors');
    }
}

// =============================================================================
// USAGE EXAMPLES
// =============================================================================

/**
 * Example 8: Complete Integration Example
 *
 * Real-world usage of the API client
 */

// Initialize API client
$api = new CMSApiClient('https://your-site.com', env('CMS_API_TOKEN'));

// Authenticate if needed
try {
    $authData = $api->login('admin@example.com', 'password');
    echo "Authenticated as: " . $authData['user']['name'] . "\n";
} catch (\Exception $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Update homepage content
$homepageContent = [
    'hero.title' => 'Welcome to Our Amazing Platform',
    'hero.subtitle' => 'Discover the power of seamless content management',
    'hero.cta_button' => 'Get Started Today',
    'features.title' => 'Why Choose Us?',
    'features.subtitle' => 'We provide the best solutions for your needs'
];

$result = $api->bulkUpdateText($homepageContent, 'en');
echo "Updated {$result['updated']} content items\n";

// Auto-translate to other languages
$languages = ['es', 'fr', 'de'];
foreach (array_keys($homepageContent) as $key) {
    $translated = $api->autoTranslate($key, 'en', $languages);
    if ($translated['success']) {
        echo "Translated {$key} to " . implode(', ', $languages) . "\n";
    }
}

// Upload and manage images
$heroImage = '/path/to/hero-image.jpg';
if (file_exists($heroImage)) {
    $imageResult = $api->uploadImage('hero.background', $heroImage, 'Hero background image');
    echo "Uploaded hero image: " . $imageResult['data']['url'] . "\n";
}

// Get analytics data
$analytics = $api->getAnalytics('month');
if ($analytics) {
    echo "Total edits this month: " . $analytics['total_edits'] . "\n";
    echo "Unique editors: " . $analytics['unique_editors'] . "\n";
}

// Check system status
$status = $api->getSystemStatus();
echo "CMS Status: " . $status['status'] . "\n";
echo "Cache hit rate: " . $status['cache']['hit_rate'] . "%\n";

echo "✅ API integration examples completed!\n";
echo "Your application can now interact with Laravel CMS via API.\n";