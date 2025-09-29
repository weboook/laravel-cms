<?php

/**
 * Laravel CMS - Custom Permissions Examples
 *
 * This file demonstrates how to implement custom permission systems,
 * role-based access control, and content-specific permissions
 * with Laravel CMS.
 */

// =============================================================================
// BASIC PERMISSION SETUP
// =============================================================================

/**
 * Example 1: Setting Up Permission System
 *
 * Installing and configuring Laravel Permission (Spatie)
 */

// Install: composer require spatie/laravel-permission

// User model setup
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'web';

    // Custom permission methods for CMS
    public function canEditCMS(): bool
    {
        return $this->hasAnyPermission(['cms:edit', 'cms:admin']);
    }

    public function canTranslate(): bool
    {
        return $this->hasAnyPermission(['cms:translate', 'cms:admin']);
    }

    public function canUploadFiles(): bool
    {
        return $this->hasAnyPermission(['cms:upload', 'cms:admin']);
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasPermissionTo('cms:admin');
    }

    public function canEditContent(string $key): bool
    {
        // Admin can edit everything
        if ($this->hasPermissionTo('cms:admin')) {
            return true;
        }

        // Check general edit permission
        if (!$this->hasPermissionTo('cms:edit')) {
            return false;
        }

        // Check content-specific permissions
        $prefix = explode('.', $key)[0];
        $specificPermission = "cms:edit:{$prefix}";

        return $this->hasPermissionTo($specificPermission);
    }

    public function canEditLanguage(string $language): bool
    {
        if ($this->hasPermissionTo('cms:admin')) {
            return true;
        }

        return $this->hasPermissionTo("cms:translate:{$language}");
    }

    public function getAllowedContentPrefixes(): array
    {
        $permissions = $this->getAllPermissions();
        $prefixes = [];

        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'cms:edit:')) {
                $prefixes[] = str_replace('cms:edit:', '', $permission->name);
            }
        }

        return $prefixes;
    }
}

/**
 * Example 2: Permission Seeder
 *
 * Creating comprehensive permissions and roles
 */

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CMSPermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // System permissions
            'cms:admin' => 'Full CMS administration access',
            'cms:config' => 'Modify CMS configuration',
            'cms:users' => 'Manage CMS users and permissions',
            'cms:backup' => 'Create and restore backups',
            'cms:system' => 'Access system information and logs',

            // Content permissions
            'cms:view' => 'View CMS interface',
            'cms:edit' => 'Edit content (global)',
            'cms:delete' => 'Delete content',
            'cms:publish' => 'Publish/unpublish content',
            'cms:schedule' => 'Schedule content publication',

            // Content-specific permissions
            'cms:edit:page' => 'Edit page content',
            'cms:edit:nav' => 'Edit navigation content',
            'cms:edit:footer' => 'Edit footer content',
            'cms:edit:hero' => 'Edit hero sections',
            'cms:edit:blog' => 'Edit blog content',
            'cms:edit:product' => 'Edit product content',
            'cms:edit:testimonial' => 'Edit testimonials',

            // Translation permissions
            'cms:translate' => 'Manage translations (all languages)',
            'cms:translate:en' => 'Manage English translations',
            'cms:translate:es' => 'Manage Spanish translations',
            'cms:translate:fr' => 'Manage French translations',
            'cms:translate:de' => 'Manage German translations',
            'cms:translate:import' => 'Import translations',
            'cms:translate:export' => 'Export translations',

            // Media permissions
            'cms:upload' => 'Upload files and images',
            'cms:media:manage' => 'Manage media library',
            'cms:media:delete' => 'Delete media files',
            'cms:media:optimize' => 'Optimize and process media',

            // API permissions
            'cms:api' => 'Access CMS API',
            'cms:api:read' => 'Read data via API',
            'cms:api:write' => 'Write data via API',
            'cms:api:admin' => 'Administrative API access',

            // Analytics permissions
            'cms:analytics:view' => 'View analytics and reports',
            'cms:analytics:export' => 'Export analytics data',
        ];

        foreach ($permissions as $name => $description) {
            Permission::create([
                'name' => $name,
                'description' => $description,
                'guard_name' => 'web'
            ]);
        }

        // Create roles and assign permissions
        $this->createRoles();
    }

    protected function createRoles()
    {
        // Super Admin - All permissions
        $superAdmin = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // CMS Admin - All CMS permissions
        $cmsAdmin = Role::create(['name' => 'cms_admin', 'guard_name' => 'web']);
        $cmsAdmin->givePermissionTo([
            'cms:admin', 'cms:config', 'cms:users', 'cms:backup', 'cms:system',
            'cms:view', 'cms:edit', 'cms:delete', 'cms:publish', 'cms:schedule',
            'cms:edit:page', 'cms:edit:nav', 'cms:edit:footer', 'cms:edit:hero',
            'cms:edit:blog', 'cms:edit:product', 'cms:edit:testimonial',
            'cms:translate', 'cms:translate:import', 'cms:translate:export',
            'cms:upload', 'cms:media:manage', 'cms:media:delete', 'cms:media:optimize',
            'cms:api', 'cms:api:read', 'cms:api:write',
            'cms:analytics:view', 'cms:analytics:export'
        ]);

        // Content Editor - Content editing permissions
        $contentEditor = Role::create(['name' => 'content_editor', 'guard_name' => 'web']);
        $contentEditor->givePermissionTo([
            'cms:view', 'cms:edit', 'cms:publish',
            'cms:edit:page', 'cms:edit:blog', 'cms:edit:product',
            'cms:upload', 'cms:media:manage',
            'cms:api:read', 'cms:api:write'
        ]);

        // Translator - Translation permissions
        $translator = Role::create(['name' => 'translator', 'guard_name' => 'web']);
        $translator->givePermissionTo([
            'cms:view', 'cms:translate', 'cms:translate:import', 'cms:translate:export',
            'cms:translate:es', 'cms:translate:fr', 'cms:translate:de',
            'cms:api:read'
        ]);

        // Content Contributor - Limited editing
        $contributor = Role::create(['name' => 'contributor', 'guard_name' => 'web']);
        $contributor->givePermissionTo([
            'cms:view', 'cms:edit:blog', 'cms:upload'
        ]);

        // Marketing Team - Marketing content only
        $marketing = Role::create(['name' => 'marketing', 'guard_name' => 'web']);
        $marketing->givePermissionTo([
            'cms:view', 'cms:edit:hero', 'cms:edit:testimonial',
            'cms:upload', 'cms:media:manage',
            'cms:analytics:view'
        ]);

        // Developer - Technical permissions
        $developer = Role::create(['name' => 'developer', 'guard_name' => 'web']);
        $developer->givePermissionTo([
            'cms:view', 'cms:config', 'cms:backup', 'cms:system',
            'cms:api', 'cms:api:read', 'cms:api:write', 'cms:api:admin'
        ]);
    }
}

// =============================================================================
// CUSTOM PERMISSION MIDDLEWARE
// =============================================================================

/**
 * Example 3: Custom Permission Middleware
 *
 * Creating middleware for fine-grained access control
 */

class CMSPermissionMiddleware
{
    public function handle(Request $request, \Closure $next, ...$permissions)
    {
        if (!auth()->check()) {
            return redirect()->route('login')
                ->with('error', 'You must be logged in to access the CMS.');
        }

        $user = auth()->user();

        // Check if user has any of the required permissions
        if (!empty($permissions)) {
            $hasPermission = false;

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                abort(403, 'Insufficient permissions to access this resource.');
            }
        }

        return $next($request);
    }
}

class ContentSpecificPermissionMiddleware
{
    public function handle(Request $request, \Closure $next, string $contentType = null)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin bypass
        if ($user->hasPermissionTo('cms:admin')) {
            return $next($request);
        }

        // Check general CMS access
        if (!$user->hasPermissionTo('cms:view')) {
            abort(403, 'No CMS access.');
        }

        // Check content-specific permissions
        if ($contentType) {
            $permission = "cms:edit:{$contentType}";
            if (!$user->hasPermissionTo($permission)) {
                abort(403, "No permission to edit {$contentType} content.");
            }
        }

        // Check if editing specific content key
        if ($request->has('key')) {
            $key = $request->get('key');
            if (!$user->canEditContent($key)) {
                abort(403, "No permission to edit content: {$key}");
            }
        }

        return $next($request);
    }
}

class LanguagePermissionMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $language = $request->get('language', app()->getLocale());

        // Admin bypass
        if ($user->hasPermissionTo('cms:admin')) {
            return $next($request);
        }

        // Check translation permissions
        if (!$user->canEditLanguage($language)) {
            abort(403, "No permission to edit {$language} translations.");
        }

        return $next($request);
    }
}

// =============================================================================
// PERMISSION-AWARE CONTENT HELPERS
// =============================================================================

/**
 * Example 4: Permission-Aware Helper Functions
 *
 * Creating helpers that respect user permissions
 */

function cms_text_with_permissions($key, $default = '', $language = null)
{
    // Check if user can view this content
    if (auth()->check() && !auth()->user()->canEditContent($key)) {
        return $default;
    }

    return cms_text($key, $default, $language);
}

function cms_editable_if_allowed($key, $content, $attributes = [])
{
    if (auth()->check() && auth()->user()->canEditContent($key)) {
        $attributes['data-cms-text'] = $key;
        $attributeString = '';
        foreach ($attributes as $attr => $value) {
            $attributeString .= " {$attr}=\"{$value}\"";
        }
        return "<span{$attributeString}>{$content}</span>";
    }

    return $content;
}

function can_edit_cms_content($key = null)
{
    if (!auth()->check()) {
        return false;
    }

    $user = auth()->user();

    if ($key) {
        return $user->canEditContent($key);
    }

    return $user->hasAnyPermission(['cms:edit', 'cms:admin']);
}

function get_editable_content_keys($user = null)
{
    $user = $user ?? auth()->user();

    if (!$user) {
        return [];
    }

    if ($user->hasPermissionTo('cms:admin')) {
        return ['*']; // Admin can edit everything
    }

    return $user->getAllowedContentPrefixes();
}

/**
 * Example 5: Permission-Based Content Filtering
 *
 * Filtering content based on user permissions
 */

class PermissionAwareContentService
{
    protected $user;

    public function __construct(User $user = null)
    {
        $this->user = $user ?? auth()->user();
    }

    public function getEditableContent($pattern = '*', $language = null)
    {
        if (!$this->user) {
            return [];
        }

        $allContent = cms_translations($pattern, $language);
        $editableContent = [];

        foreach ($allContent as $key => $value) {
            if ($this->user->canEditContent($key)) {
                $editableContent[$key] = $value;
            }
        }

        return $editableContent;
    }

    public function getEditableLanguages()
    {
        if (!$this->user) {
            return [];
        }

        if ($this->user->hasPermissionTo('cms:admin')) {
            return array_keys(cms_locales());
        }

        $editableLanguages = [];
        $allLanguages = array_keys(cms_locales());

        foreach ($allLanguages as $language) {
            if ($this->user->canEditLanguage($language)) {
                $editableLanguages[] = $language;
            }
        }

        return $editableLanguages;
    }

    public function filterContentByPermissions(array $content)
    {
        if (!$this->user) {
            return [];
        }

        $filtered = [];

        foreach ($content as $key => $value) {
            if ($this->user->canEditContent($key)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    public function getAccessibleMenuItems()
    {
        $menuItems = [
            'dashboard' => ['permission' => 'cms:view', 'icon' => 'dashboard'],
            'content' => ['permission' => 'cms:edit', 'icon' => 'edit'],
            'translations' => ['permission' => 'cms:translate', 'icon' => 'language'],
            'media' => ['permission' => 'cms:upload', 'icon' => 'image'],
            'analytics' => ['permission' => 'cms:analytics:view', 'icon' => 'chart'],
            'users' => ['permission' => 'cms:users', 'icon' => 'users'],
            'settings' => ['permission' => 'cms:config', 'icon' => 'settings'],
        ];

        $accessibleItems = [];

        foreach ($menuItems as $key => $item) {
            if ($this->user->hasPermissionTo($item['permission'])) {
                $accessibleItems[$key] = $item;
            }
        }

        return $accessibleItems;
    }
}

// =============================================================================
// CUSTOM PERMISSION GATES
// =============================================================================

/**
 * Example 6: Laravel Gates for CMS Permissions
 *
 * Defining custom gates for complex permission logic
 */

// In AuthServiceProvider
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();

        // CMS Gates
        Gate::define('edit-cms-content', function (User $user, string $key = null) {
            if ($key) {
                return $user->canEditContent($key);
            }
            return $user->hasAnyPermission(['cms:edit', 'cms:admin']);
        });

        Gate::define('translate-content', function (User $user, string $language = null) {
            if ($language) {
                return $user->canEditLanguage($language);
            }
            return $user->hasAnyPermission(['cms:translate', 'cms:admin']);
        });

        Gate::define('upload-files', function (User $user) {
            return $user->hasAnyPermission(['cms:upload', 'cms:admin']);
        });

        Gate::define('manage-cms-users', function (User $user) {
            return $user->hasPermissionTo('cms:users');
        });

        Gate::define('access-cms-api', function (User $user, string $action = 'read') {
            $permission = "cms:api:{$action}";
            return $user->hasAnyPermission([$permission, 'cms:api', 'cms:admin']);
        });

        Gate::define('edit-sensitive-content', function (User $user, string $key) {
            $sensitivePatterns = ['nav.*', 'footer.*', 'legal.*'];

            foreach ($sensitivePatterns as $pattern) {
                if (fnmatch($pattern, $key)) {
                    return $user->hasPermissionTo('cms:admin');
                }
            }

            return $user->canEditContent($key);
        });

        Gate::define('bulk-operations', function (User $user) {
            return $user->hasAnyPermission(['cms:admin', 'cms:translate:import']);
        });

        // Time-based permissions
        Gate::define('edit-during-business-hours', function (User $user) {
            if ($user->hasPermissionTo('cms:admin')) {
                return true;
            }

            $now = now();
            $isBusinessHours = $now->hour >= 9 && $now->hour <= 17;
            $isWeekday = $now->isWeekday();

            return $isBusinessHours && $isWeekday;
        });

        // Content approval workflow
        Gate::define('approve-content', function (User $user, string $contentType) {
            $approvalRequired = ['hero', 'testimonial', 'product'];

            if (in_array($contentType, $approvalRequired)) {
                return $user->hasAnyPermission(['cms:admin', 'cms:publish']);
            }

            return true;
        });
    }
}

/**
 * Example 7: Permission-Aware Blade Directives
 *
 * Creating custom Blade directives for permissions
 */

// In AppServiceProvider
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // CMS permission directive
        Blade::directive('canEditCMS', function ($key = null) {
            if ($key) {
                return "<?php if(auth()->check() && auth()->user()->canEditContent({$key})): ?>";
            }
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo('cms:edit')): ?>";
        });

        Blade::directive('endCanEditCMS', function () {
            return '<?php endif; ?>';
        });

        // Translation permission directive
        Blade::directive('canTranslate', function ($language = null) {
            if ($language) {
                return "<?php if(auth()->check() && auth()->user()->canEditLanguage({$language})): ?>";
            }
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo('cms:translate')): ?>";
        });

        Blade::directive('endCanTranslate', function () {
            return '<?php endif; ?>';
        });

        // Upload permission directive
        Blade::directive('canUpload', function () {
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo('cms:upload')): ?>";
        });

        Blade::directive('endCanUpload', function () {
            return '<?php endif; ?>';
        });

        // Admin permission directive
        Blade::directive('isCMSAdmin', function () {
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo('cms:admin')): ?>";
        });

        Blade::directive('endIsCMSAdmin', function () {
            return '<?php endif; ?>';
        });
    }
}

// =============================================================================
// USAGE EXAMPLES IN VIEWS
// =============================================================================

/**
 * Example 8: Using Permissions in Blade Templates
 *
 * Practical examples of permission usage
 */

?>

<!-- Basic permission checks -->
@canEditCMS('page.title')
    <h1 data-cms-text="page.title" class="editable">{{ cms_text('page.title') }}</h1>
@else
    <h1>{{ cms_text('page.title') }}</h1>
@endCanEditCMS

<!-- Navigation with permission-based editing -->
<nav class="navbar">
    @foreach(['home', 'about', 'services', 'contact'] as $item)
        <a href="{{ cms_link("nav.{$item}") }}"
           @canEditCMS("nav.{$item}") data-cms-link="nav.{{ $item }}" @endCanEditCMS
           class="nav-link">
            @canEditCMS("nav.{$item}")
                <span data-cms-text="nav.{{ $item }}">{{ cms_text("nav.{$item}") }}</span>
            @else
                {{ cms_text("nav.{$item}") }}
            @endCanEditCMS
        </a>
    @endforeach
</nav>

<!-- Translation interface -->
@canTranslate()
    <div class="translation-panel">
        <h3>Translations</h3>
        @foreach(cms_locales() as $locale => $config)
            @canTranslate($locale)
                <div class="translation-row">
                    <label>{{ $config['name'] }}</label>
                    <input type="text"
                           data-cms-text="page.title"
                           data-cms-lang="{{ $locale }}"
                           value="{{ cms_text('page.title', '', $locale) }}">
                </div>
            @endCanTranslate
        @endforeach
    </div>
@endCanTranslate

<!-- File upload interface -->
@canUpload
    <div class="upload-area">
        <input type="file" data-cms-image="hero.background" accept="image/*">
        <p>Drop files here to upload</p>
    </div>
@endCanUpload

<!-- Admin-only settings -->
@isCMSAdmin
    <div class="admin-panel">
        <h3>Admin Settings</h3>
        <a href="{{ route('cms.settings') }}" class="btn btn-primary">CMS Settings</a>
        <a href="{{ route('cms.users') }}" class="btn btn-secondary">Manage Users</a>
        <a href="{{ route('cms.backup') }}" class="btn btn-warning">Backup System</a>
    </div>
@endIsCMSAdmin

<!-- Permission-based content sections -->
@can('edit-cms-content', 'hero.title')
    <section class="hero editable-section">
        <h1 data-cms-text="hero.title">{{ cms_text('hero.title') }}</h1>
        <p data-cms-text="hero.subtitle">{{ cms_text('hero.subtitle') }}</p>
        <a href="{{ cms_link('hero.cta_link') }}"
           data-cms-link="hero.cta_link"
           data-cms-text="hero.cta_text"
           class="btn btn-primary">
            {{ cms_text('hero.cta_text') }}
        </a>
    </section>
@else
    <section class="hero">
        <h1>{{ cms_text('hero.title') }}</h1>
        <p>{{ cms_text('hero.subtitle') }}</p>
        <a href="{{ cms_link('hero.cta_link') }}" class="btn btn-primary">
            {{ cms_text('hero.cta_text') }}
        </a>
    </section>
@endcan

<!-- Dynamic content based on user permissions -->
@php
    $editableKeys = get_editable_content_keys();
    $contentSections = [
        'page' => 'Page Content',
        'nav' => 'Navigation',
        'footer' => 'Footer',
        'blog' => 'Blog Content',
        'product' => 'Products'
    ];
@endphp

<div class="content-manager">
    <h2>Content Manager</h2>
    @foreach($contentSections as $prefix => $label)
        @if(in_array($prefix, $editableKeys) || in_array('*', $editableKeys))
            <div class="content-section">
                <h3>{{ $label }}</h3>
                <div class="content-items">
                    @foreach(cms_group("{$prefix}.*") as $key => $value)
                        @can('edit-cms-content', $key)
                            <div class="content-item">
                                <label>{{ $key }}</label>
                                <input type="text" data-cms-text="{{ $key }}" value="{{ $value }}">
                            </div>
                        @endcan
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

<?php

/**
 * Example 9: API Permissions
 *
 * Implementing permissions in API controllers
 */

class CMSApiController extends Controller
{
    public function updateContent(Request $request)
    {
        $key = $request->input('key');
        $value = $request->input('value');
        $language = $request->input('language', 'en');

        // Check permissions
        if (!auth()->user()->canEditContent($key)) {
            return response()->json([
                'error' => 'Insufficient permissions to edit this content'
            ], 403);
        }

        if (!auth()->user()->canEditLanguage($language)) {
            return response()->json([
                'error' => "No permission to edit {$language} content"
            ], 403);
        }

        // Update content
        cms_set_text($key, $value, $language);

        return response()->json([
            'success' => true,
            'message' => 'Content updated successfully'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $this->authorize('bulk-operations');

        $content = $request->input('content', []);
        $language = $request->input('language', 'en');

        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($content as $key => $value) {
            if (!auth()->user()->canEditContent($key)) {
                $failed++;
                $errors[] = "No permission to edit: {$key}";
                continue;
            }

            try {
                cms_set_text($key, $value, $language);
                $updated++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Failed to update {$key}: " . $e->getMessage();
            }
        }

        return response()->json([
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ]);
    }

    public function uploadFile(Request $request)
    {
        $this->authorize('upload-files');

        // File upload logic
        // ...

        return response()->json([
            'success' => true,
            'file_url' => $fileUrl
        ]);
    }
}

echo "✅ Custom permissions examples completed!\n";
echo "Your CMS now has comprehensive role-based access control.\n";