# Security Guide

Comprehensive security guide for Laravel CMS covering best practices, threat prevention, and security configurations.

## 🔐 Security Overview

Laravel CMS prioritizes security with multiple layers of protection:

- **Input Sanitization**: All content is sanitized using HTML Purifier
- **CSRF Protection**: All forms and API endpoints are protected
- **XSS Prevention**: Output encoding and content filtering
- **File Upload Security**: Malware scanning and type validation
- **Permission System**: Role-based access control
- **Audit Logging**: Complete activity tracking
- **Rate Limiting**: API and form submission protection

## 🛡️ Content Security

### HTML Sanitization

Laravel CMS automatically sanitizes all rich text content:

```php
// config/cms.php
'security' => [
    'sanitize_html' => true,
    'allowed_tags' => [
        'p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'a', 'img', 'blockquote', 'table', 'tr', 'td', 'th'
    ],
    'allowed_attributes' => [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
        'table' => ['class'],
        'tr' => ['class'],
        'td' => ['class', 'colspan', 'rowspan'],
        'th' => ['class', 'colspan', 'rowspan'],
        '*' => ['class', 'id'], // Global attributes
    ],
    'forbidden_attributes' => [
        'onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout',
        'javascript:', 'vbscript:', 'data:'
    ],
];
```

### Custom Sanitization Rules

Create custom sanitization for specific content types:

```php
<?php

namespace App\CMS\Sanitizers;

use Webook\LaravelCMS\Contracts\SanitizerInterface;

class StrictContentSanitizer implements SanitizerInterface
{
    protected $purifier;

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();

        // Strict configuration
        $config->set('HTML.Allowed', 'p,br,strong,em,u');
        $config->set('HTML.AllowedAttributes', '');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.Linkify', false);

        $this->purifier = new \HTMLPurifier($config);
    }

    public function sanitize(string $content): string
    {
        return $this->purifier->purify($content);
    }

    public function isAllowed(string $tag, array $attributes = []): bool
    {
        $allowedTags = ['p', 'br', 'strong', 'em', 'u'];
        return in_array($tag, $allowedTags) && empty($attributes);
    }
}
```

### XSS Prevention

Protect against Cross-Site Scripting attacks:

```blade
{{-- Always use {{ }} for user content (auto-escaped) --}}
<h1>{{ cms_text('page.title') }}</h1>

{{-- Only use {!! !!} for sanitized rich content --}}
<div class="content">
    {!! cms_rich('page.content') !!}
</div>

{{-- Never trust user input in attributes --}}
<img src="{{ cms_image('hero.banner') }}"
     alt="{{ cms_text('hero.alt') }}"
     class="hero-image">

{{-- Use proper escaping for JavaScript --}}
<script>
    const pageTitle = @json(cms_text('page.title'));
    const config = @json(cms_config('page.settings'));
</script>
```

### Content Security Policy (CSP)

Implement CSP headers for additional protection:

```php
<?php

namespace App\Http\Middleware;

use Closure;

class ContentSecurityPolicy
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.tiny.cloud",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
```

## 🔒 Authentication & Authorization

### User Authentication

Secure user authentication configuration:

```php
// config/cms-auth.php
return [
    'authentication' => [
        'enabled' => true,
        'guard' => 'web',
        'providers' => ['users'],

        'session' => [
            'lifetime' => 120, // minutes
            'expire_on_close' => false,
            'encrypt' => true,
            'http_only' => true,
            'same_site' => 'strict',
        ],

        'password' => [
            'min_length' => 12,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'prevent_reuse' => 5, // Last 5 passwords
        ],

        'lockout' => [
            'max_attempts' => 5,
            'decay_minutes' => 15,
            'lockout_duration' => 60, // minutes
        ],
    ],
];
```

### Two-Factor Authentication

Enable 2FA for enhanced security:

```php
<?php

namespace App\Http\Controllers\Auth;

use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorController extends Controller
{
    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_secret) {
            return back()->withErrors('2FA is already enabled.');
        }

        $user->forceFill([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, function () {
                return RecoveryCode::generate();
            })->all())),
        ])->save();

        return response()->json([
            'svg' => $user->twoFactorQrCodeSvg(),
            'secret' => decrypt($user->two_factor_secret),
            'recovery_codes' => json_decode(decrypt($user->two_factor_recovery_codes))
        ]);
    }

    public function confirm(Request $request)
    {
        $confirmed = app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($request->user()->two_factor_secret),
            $request->code
        );

        if (!$confirmed) {
            return back()->withErrors('Invalid 2FA code.');
        }

        $request->user()->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return redirect()->route('cms.dashboard')->with('status', '2FA enabled successfully.');
    }
}
```

### Permission System

Implement granular permissions:

```php
<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'web';

    public function canEditCMS(): bool
    {
        return $this->hasPermissionTo('cms:edit');
    }

    public function canTranslate(): bool
    {
        return $this->hasPermissionTo('cms:translate');
    }

    public function canUploadFiles(): bool
    {
        return $this->hasPermissionTo('cms:upload');
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasPermissionTo('cms:admin');
    }

    public function canEditContent(string $key): bool
    {
        // Check specific content permissions
        if ($this->hasPermissionTo('cms:admin')) {
            return true;
        }

        // Check content-specific permissions
        $prefix = explode('.', $key)[0];
        return $this->hasPermissionTo("cms:edit:{$prefix}");
    }
}
```

### Role-Based Access Control

Define roles and permissions:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CMSPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create permissions
        $permissions = [
            // System permissions
            'cms:admin' => 'Full CMS administration',
            'cms:config' => 'Modify CMS configuration',
            'cms:users' => 'Manage CMS users',
            'cms:backup' => 'Create and restore backups',

            // Content permissions
            'cms:view' => 'View CMS interface',
            'cms:edit' => 'Edit content',
            'cms:edit:page' => 'Edit page content',
            'cms:edit:nav' => 'Edit navigation',
            'cms:edit:footer' => 'Edit footer content',
            'cms:delete' => 'Delete content',
            'cms:publish' => 'Publish/unpublish content',

            // Translation permissions
            'cms:translate' => 'Manage translations',
            'cms:translate:import' => 'Import translations',
            'cms:translate:export' => 'Export translations',

            // Media permissions
            'cms:upload' => 'Upload files',
            'cms:media:manage' => 'Manage media library',
            'cms:media:delete' => 'Delete media files',
        ];

        foreach ($permissions as $name => $description) {
            Permission::create([
                'name' => $name,
                'description' => $description,
                'guard_name' => 'web'
            ]);
        }

        // Create roles
        $adminRole = Role::create(['name' => 'cms_admin', 'guard_name' => 'web']);
        $editorRole = Role::create(['name' => 'cms_editor', 'guard_name' => 'web']);
        $translatorRole = Role::create(['name' => 'cms_translator', 'guard_name' => 'web']);
        $contributorRole = Role::create(['name' => 'cms_contributor', 'guard_name' => 'web']);

        // Assign permissions to roles
        $adminRole->givePermissionTo(Permission::all());

        $editorRole->givePermissionTo([
            'cms:view', 'cms:edit', 'cms:edit:page', 'cms:edit:nav',
            'cms:upload', 'cms:media:manage', 'cms:publish'
        ]);

        $translatorRole->givePermissionTo([
            'cms:view', 'cms:translate', 'cms:translate:import', 'cms:translate:export'
        ]);

        $contributorRole->givePermissionTo([
            'cms:view', 'cms:edit:page'
        ]);
    }
}
```

## 📁 File Upload Security

### File Type Validation

Secure file upload configuration:

```php
// config/cms.php
'security' => [
    'file_upload' => [
        'max_size' => 10240, // KB (10MB)
        'allowed_extensions' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
            'documents' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
            'archives' => ['zip', 'tar', 'gz'],
        ],
        'blocked_extensions' => [
            'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js',
            'php', 'asp', 'jsp', 'pl', 'py', 'rb', 'sh'
        ],
        'mime_validation' => true,
        'scan_uploads' => true,
        'quarantine_suspicious' => true,
    ],
];
```

### Malware Scanning

Implement file scanning:

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class FileSecurityScanner
{
    protected $scanners = [];

    public function __construct()
    {
        $this->scanners = [
            new ClamAVScanner(),
            new SignatureScanner(),
            new BehaviorScanner(),
        ];
    }

    public function scanFile(UploadedFile $file): array
    {
        $results = [
            'safe' => true,
            'threats' => [],
            'quarantined' => false,
        ];

        foreach ($this->scanners as $scanner) {
            $scanResult = $scanner->scan($file);

            if (!$scanResult['safe']) {
                $results['safe'] = false;
                $results['threats'] = array_merge($results['threats'], $scanResult['threats']);
            }
        }

        if (!$results['safe']) {
            $this->quarantineFile($file);
            $results['quarantined'] = true;
        }

        return $results;
    }

    protected function quarantineFile(UploadedFile $file): void
    {
        $quarantinePath = storage_path('quarantine');

        if (!file_exists($quarantinePath)) {
            mkdir($quarantinePath, 0700, true);
        }

        $quarantineFile = $quarantinePath . '/' . uniqid() . '_' . $file->getClientOriginalName();
        move_uploaded_file($file->getPathname(), $quarantineFile);

        // Log security incident
        \Log::warning('Malicious file quarantined', [
            'original_name' => $file->getClientOriginalName(),
            'quarantine_path' => $quarantineFile,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }
}

class ClamAVScanner
{
    public function scan(UploadedFile $file): array
    {
        if (!class_exists('Socket\Raw\Factory')) {
            return ['safe' => true, 'threats' => []];
        }

        try {
            $socket = (new \Socket\Raw\Factory())->createClient('unix:///var/run/clamav/clamd.ctl');
            $socket->write("SCAN {$file->getPathname()}\n");
            $response = $socket->read(4096);
            $socket->close();

            if (strpos($response, 'FOUND') !== false) {
                preg_match('/(.+): (.+) FOUND/', $response, $matches);
                return [
                    'safe' => false,
                    'threats' => [$matches[2] ?? 'Unknown threat']
                ];
            }

            return ['safe' => true, 'threats' => []];
        } catch (\Exception $e) {
            \Log::error('ClamAV scan failed: ' . $e->getMessage());
            return ['safe' => true, 'threats' => []]; // Fail open
        }
    }
}
```

### File Type Detection

Validate file types beyond extensions:

```php
<?php

namespace App\Validators;

use Illuminate\Http\UploadedFile;

class SecureFileValidator
{
    protected $mimeTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    public function validate(UploadedFile $file): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > config('cms.security.file_upload.max_size') * 1024) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_merge(...array_values(config('cms.security.file_upload.allowed_extensions')));

        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'File type not allowed';
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (isset($this->mimeTypes[$extension])) {
            if (!in_array($mimeType, $this->mimeTypes[$extension])) {
                $errors[] = 'File MIME type does not match extension';
            }
        }

        // Check for embedded executables
        if ($this->containsExecutableCode($file)) {
            $errors[] = 'File contains executable code';
        }

        // Check file header (magic bytes)
        if (!$this->validateFileHeader($file, $extension)) {
            $errors[] = 'File header does not match file type';
        }

        return $errors;
    }

    protected function containsExecutableCode(UploadedFile $file): bool
    {
        $content = file_get_contents($file->getPathname());

        $patterns = [
            '/(<script[^>]*>.*?<\/script>)/is',
            '/(javascript:)/i',
            '/(vbscript:)/i',
            '/(onload=|onclick=|onerror=)/i',
            '/(<\?php)/i',
            '/(<\%)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function validateFileHeader(UploadedFile $file, string $extension): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        $header = fread($handle, 20);
        fclose($handle);

        $signatures = [
            'jpg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89PNG\r\n\x1A\n"],
            'gif' => ["GIF87a", "GIF89a"],
            'pdf' => ["%PDF"],
        ];

        if (!isset($signatures[$extension])) {
            return true; // No signature to check
        }

        foreach ($signatures[$extension] as $signature) {
            if (strpos($header, $signature) === 0) {
                return true;
            }
        }

        return false;
    }
}
```

## 🔍 Audit Logging

### Security Event Logging

Track all security-related events:

```php
<?php

namespace App\Listeners;

use Webook\LaravelCMS\Events\SecurityEvent;
use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    public function handle(SecurityEvent $event)
    {
        $context = [
            'event_type' => $event->type,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
            'severity' => $event->severity,
            'details' => $event->details,
        ];

        // Log to security channel
        Log::channel('security')->log($event->severity, $event->message, $context);

        // Alert on high-severity events
        if ($event->severity === 'critical') {
            $this->sendSecurityAlert($event, $context);
        }

        // Store in audit table
        \DB::table('security_audit_log')->insert([
            'event_type' => $event->type,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'details' => json_encode($context),
            'created_at' => now(),
        ]);
    }

    protected function sendSecurityAlert(SecurityEvent $event, array $context): void
    {
        // Send email alert
        \Mail::to(config('cms.security.alert_email'))->send(
            new \App\Mail\SecurityAlert($event, $context)
        );

        // Send to Slack/Discord webhook
        if ($webhookUrl = config('cms.security.webhook_url')) {
            \Http::post($webhookUrl, [
                'text' => "🚨 Security Alert: {$event->message}",
                'attachments' => [
                    [
                        'color' => 'danger',
                        'fields' => [
                            ['title' => 'Event', 'value' => $event->type, 'short' => true],
                            ['title' => 'User', 'value' => auth()->user()->email ?? 'Guest', 'short' => true],
                            ['title' => 'IP', 'value' => request()->ip(), 'short' => true],
                            ['title' => 'Time', 'value' => now()->toDateTimeString(), 'short' => true],
                        ]
                    ]
                ]
            ]);
        }
    }
}
```

### Activity Monitoring

Monitor user activity for suspicious behavior:

```php
<?php

namespace App\Services;

class ActivityMonitor
{
    protected $suspiciousPatterns = [
        'rapid_requests' => 100, // requests per minute
        'failed_logins' => 5,    // failed attempts per hour
        'bulk_updates' => 50,    // content updates per hour
        'file_uploads' => 20,    // uploads per hour
    ];

    public function checkActivity(string $activity, $user = null): bool
    {
        $user = $user ?? auth()->user();
        $ip = request()->ip();

        switch ($activity) {
            case 'request':
                return $this->checkRapidRequests($ip);

            case 'login_failed':
                return $this->checkFailedLogins($ip);

            case 'content_update':
                return $this->checkBulkUpdates($user);

            case 'file_upload':
                return $this->checkFileUploads($user);
        }

        return false;
    }

    protected function checkRapidRequests(string $ip): bool
    {
        $key = "rapid_requests:{$ip}";
        $requests = \Cache::increment($key);

        if ($requests === 1) {
            \Cache::put($key, 1, 60); // 1 minute TTL
        }

        if ($requests > $this->suspiciousPatterns['rapid_requests']) {
            $this->flagSuspiciousActivity('rapid_requests', [
                'ip' => $ip,
                'requests' => $requests,
            ]);
            return true;
        }

        return false;
    }

    protected function checkFailedLogins(string $ip): bool
    {
        $key = "failed_logins:{$ip}";
        $failures = \Cache::increment($key);

        if ($failures === 1) {
            \Cache::put($key, 1, 3600); // 1 hour TTL
        }

        if ($failures > $this->suspiciousPatterns['failed_logins']) {
            $this->flagSuspiciousActivity('failed_logins', [
                'ip' => $ip,
                'failures' => $failures,
            ]);
            return true;
        }

        return false;
    }

    protected function flagSuspiciousActivity(string $type, array $details): void
    {
        event(new \Webook\LaravelCMS\Events\SecurityEvent(
            'suspicious_activity',
            "Suspicious activity detected: {$type}",
            'warning',
            $details
        ));
    }
}
```

## 🚫 Rate Limiting

### API Rate Limiting

Implement comprehensive rate limiting:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

class CMSRateLimit extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier());
        }

        return sha1($request->ip());
    }

    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        // Log rate limit violation
        \Log::warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'user_id' => $request->user()->id ?? null,
            'route' => $request->route()->getName(),
            'max_attempts' => $maxAttempts,
            'retry_after' => $retryAfter,
        ]);

        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429, $headers);
    }
}
```

Define rate limits in routes:

```php
// routes/web.php
Route::middleware(['cms.rate.limit:60,1'])->group(function () {
    Route::post('/cms/api/content/update', 'ContentController@update');
});

Route::middleware(['cms.rate.limit:10,1'])->group(function () {
    Route::post('/cms/api/files/upload', 'FileController@upload');
});

Route::middleware(['cms.rate.limit:5,1'])->group(function () {
    Route::post('/cms/api/translations/auto-translate', 'TranslationController@autoTranslate');
});
```

## 🔐 Environment Security

### Production Security Checklist

Essential security configurations for production:

```bash
# Environment variables security
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-32-character-secret-key

# Database security
DB_HOST=127.0.0.1  # Never expose database publicly
DB_USERNAME=cms_user  # Use dedicated user with minimal privileges

# Session security
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# HTTPS enforcement
FORCE_HTTPS=true

# Cache security
CACHE_DRIVER=redis  # More secure than file cache
REDIS_PASSWORD=your-redis-password

# File security
CMS_SANITIZE_HTML=true
CMS_SCAN_UPLOADS=true
CMS_MAX_FILE_SIZE=10240

# Logging
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=stack

# Security headers
SECURITY_HEADERS_ENABLED=true
```

### Server Security Configuration

#### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Hide server information
    server_tokens off;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=cms:10m rate=10r/s;
    limit_req zone=cms burst=20 nodelay;

    # File upload restrictions
    client_max_body_size 20M;

    # Block access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ \.(env|log|ini)$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # PHP configuration
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/your/app/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1

    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # Hide server information
    ServerTokens Prod
    ServerSignature Off

    # Block access to sensitive files
    <Files ~ "^\.">
        Require all denied
    </Files>

    <Files ~ "\.(env|log|ini)$">
        Require all denied
    </Files>

    # File upload size
    LimitRequestBody 20971520  # 20MB
</VirtualHost>
```

## 🛡️ Security Monitoring

### Real-time Security Monitoring

```php
<?php

namespace App\Services;

class SecurityMonitor
{
    protected $threats = [];

    public function __construct()
    {
        $this->threats = [
            'sql_injection' => new SQLInjectionDetector(),
            'xss_attempt' => new XSSDetector(),
            'file_inclusion' => new FileInclusionDetector(),
            'brute_force' => new BruteForceDetector(),
        ];
    }

    public function monitor(Request $request): array
    {
        $detectedThreats = [];

        foreach ($this->threats as $type => $detector) {
            if ($detector->detect($request)) {
                $detectedThreats[] = $type;
                $this->handleThreat($type, $request, $detector->getDetails());
            }
        }

        return $detectedThreats;
    }

    protected function handleThreat(string $type, Request $request, array $details): void
    {
        // Log the threat
        \Log::channel('security')->critical("Security threat detected: {$type}", [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'details' => $details,
        ]);

        // Block IP temporarily
        if (in_array($type, ['sql_injection', 'xss_attempt'])) {
            \Cache::put("blocked_ip:{$request->ip()}", true, 3600); // 1 hour
        }

        // Send alert
        event(new \Webook\LaravelCMS\Events\SecurityEvent(
            $type,
            "Security threat detected: {$type}",
            'critical',
            $details
        ));
    }
}

class SQLInjectionDetector
{
    protected $patterns = [
        '/(\b(select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
        '/(union\s+(all\s+)?select)/i',
        '/(\bor\s+\d+\s*=\s*\d+)/i',
        '/(\band\s+\d+\s*=\s*\d+)/i',
        '/(\'|\"|`).*(\'|\"|`)/',
    ];

    public function detect(Request $request): bool
    {
        $inputs = array_merge($request->all(), [$request->getPathInfo()]);

        foreach ($inputs as $input) {
            if (is_string($input)) {
                foreach ($this->patterns as $pattern) {
                    if (preg_match($pattern, $input)) {
                        $this->details = ['pattern' => $pattern, 'input' => $input];
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
```

## 🔧 Security Maintenance

### Regular Security Tasks

Create scheduled tasks for security maintenance:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityMaintenance extends Command
{
    protected $signature = 'cms:security-maintenance';
    protected $description = 'Perform regular security maintenance tasks';

    public function handle()
    {
        $this->info('Starting security maintenance...');

        // Clean old logs
        $this->cleanOldLogs();

        // Update security signatures
        $this->updateSecuritySignatures();

        // Check for vulnerable dependencies
        $this->checkVulnerabilities();

        // Rotate API keys
        $this->rotateAPIKeys();

        // Clean quarantine directory
        $this->cleanQuarantine();

        $this->info('Security maintenance completed.');
    }

    protected function cleanOldLogs(): void
    {
        $this->line('Cleaning old security logs...');

        \DB::table('security_audit_log')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }

    protected function updateSecuritySignatures(): void
    {
        $this->line('Updating security signatures...');

        // Update malware signatures
        // Update IP blocklists
        // Update vulnerability databases
    }

    protected function checkVulnerabilities(): void
    {
        $this->line('Checking for vulnerable dependencies...');

        // Run security audit
        exec('composer audit --format=json', $output);
        $audit = json_decode(implode('', $output), true);

        if (!empty($audit['advisories'])) {
            foreach ($audit['advisories'] as $advisory) {
                \Log::warning('Vulnerable dependency detected', $advisory);
            }
        }
    }

    protected function rotateAPIKeys(): void
    {
        $this->line('Rotating API keys...');

        // Rotate old API tokens
        \DB::table('personal_access_tokens')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
    }

    protected function cleanQuarantine(): void
    {
        $this->line('Cleaning quarantine directory...');

        $quarantineDir = storage_path('quarantine');
        if (is_dir($quarantineDir)) {
            $files = glob($quarantineDir . '/*');
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-7 days')) {
                    unlink($file);
                }
            }
        }
    }
}
```

Schedule the maintenance task:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('cms:security-maintenance')
             ->daily()
             ->at('02:00');

    $schedule->command('cms:cleanup-failed-jobs')
             ->hourly();

    $schedule->command('cms:rotate-logs')
             ->weekly();
}
```

## 📊 Security Metrics

### Security Dashboard

Track security metrics:

```php
<?php

namespace App\Http\Controllers\CMS;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        $metrics = [
            'threats_blocked' => $this->getThreatsBlocked(),
            'failed_logins' => $this->getFailedLogins(),
            'suspicious_activity' => $this->getSuspiciousActivity(),
            'file_scans' => $this->getFileScanResults(),
            'vulnerabilities' => $this->getVulnerabilities(),
        ];

        return view('cms.security.dashboard', compact('metrics'));
    }

    protected function getThreatsBlocked(): array
    {
        return \DB::table('security_audit_log')
            ->where('event_type', 'threat_blocked')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('details->threat_type')
            ->selectRaw('details->>"$.threat_type" as type, count(*) as count')
            ->get()
            ->pluck('count', 'type')
            ->toArray();
    }

    protected function getFailedLogins(): int
    {
        return \DB::table('security_audit_log')
            ->where('event_type', 'login_failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }
}
```

## 📞 Next Steps

Now that you understand CMS security:

1. **Deploy Securely**: [Deployment Guide](deployment.md)
2. **Monitor Performance**: [Performance Guide](performance.md)
3. **Handle Issues**: [Troubleshooting Guide](troubleshooting.md)
4. **Stay Updated**: [Security Announcements](https://github.com/weboook/laravel-cms/security/advisories)

---

**Security is ongoing!** Join our [Security Discord Channel](https://discord.gg/laravel-cms-security) for updates and community support.