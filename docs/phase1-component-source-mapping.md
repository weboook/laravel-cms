# Phase 1: Component Source Mapping Implementation

## Overview

This document describes the implementation of Phase 1 from the component translation action plan: **Source Mapping for Components**. This feature enables the CMS to track which Blade component or partial file a piece of content originates from, allowing precise editing of component content.

## Problem Solved

Previously, the CMS could only track content back to the main view file of a page. When content came from shared Blade components or partials, edits would fail because the system didn't know which component file to update.

**Example Problem:**
```blade
{{-- Main view: resources/views/welcome.blade.php --}}
<x-alert>This is component content</x-alert>

{{-- Component: resources/views/components/alert.blade.php --}}
<div class="alert">{{ $slot }}</div>
```

When editing "This is component content", the CMS would try to update `welcome.blade.php` instead of `components/alert.blade.php`.

## Solution Architecture

### 1. Blade Source Tracking Service

**File:** `src/Services/BladeSourceTracker.php`

A new service that:
- Hooks into Laravel's view composition
- Tracks which view file is currently being rendered
- Provides Blade directives to mark trackable content sections
- Validates source paths for security

**Key Methods:**
- `register()` - Registers view composers and Blade directives
- `getCurrentView()` - Returns the currently rendering view
- `extractSourceMarkers()` - Parses HTML for source tracking comments
- `removeSourceMarkers()` - Cleans source markers from final output
- `isValidSourcePath()` - Validates paths for security (prevents path traversal)

**Blade Directives Added:**
```blade
@cmsSourceStart    {{-- Marks beginning of trackable section --}}
@cmsSourceEnd      {{-- Marks end of trackable section --}}
@cmsSource($slot)  {{-- Wraps content with source markers --}}
```

### 2. Middleware Integration

**File:** `src/Http/Middleware/InjectEditableMarkers.php`

**Changes:**
1. **Extract source mapping** before DOM processing:
   ```php
   $sourceMap = $this->extractSourceMapping($html);
   ```

2. **Add source attributes** to editable elements:
   ```php
   $element->setAttribute('data-cms-source', $sourcePath);
   ```

3. **Remove source markers** from final HTML (keeps output clean)

**New Methods:**
- `extractSourceMapping($html)` - Extracts source metadata from HTML comments
- `addSourceAttributes($element, $sourceMap, $dom)` - Adds source data to DOM elements

### 3. Frontend Integration

**File:** `resources/views/toolbar.blade.php`

**Changes:**

1. **Extract source metadata** in `handleContentChanged()`:
   ```javascript
   const sourceFile = change.element.getAttribute('data-cms-source');
   const sourceLine = change.element.getAttribute('data-cms-line');
   ```

2. **Send source metadata** in `saveContentChange()`:
   ```javascript
   const requestBody = {
       element_id: change.id,
       content: change.content,
       file_hint: fileHint,      // From data-cms-source
       line_hint: lineHint,      // From data-cms-line (future)
       // ... other fields
   };
   ```

3. **Fallback behavior**: If no source metadata exists, falls back to route inspection

### 4. Configuration

**File:** `config/cms.php`

Added feature flag:
```php
'features' => [
    'component_source_mapping' => env('CMS_COMPONENT_SOURCE_MAPPING', false),
],
```

**To enable:**
```env
CMS_COMPONENT_SOURCE_MAPPING=true
```

### 5. Service Provider Registration

**File:** `src/CMSServiceProvider.php`

**Changes:**
1. Registered `BladeSourceTracker` as singleton
2. Boot source tracker when feature is enabled:
   ```php
   if (config('cms.features.component_source_mapping')) {
       $this->app->make(\Webook\LaravelCMS\Services\BladeSourceTracker::class)->register();
   }
   ```

## Data Flow

### Rendering Phase

```
1. View starts rendering
   ↓
2. BladeSourceTracker hooks into view composition
   ↓
3. @cmsSourceStart directive injects: <!--[CMS:source:path/to/file.blade.php]-->
   ↓
4. Content is rendered
   ↓
5. @cmsSourceEnd directive injects: <!--[CMS:source:end]-->
   ↓
6. InjectEditableMarkers middleware processes HTML
   ↓
7. Source markers are extracted and mapped to DOM elements
   ↓
8. data-cms-source attribute is added to editable elements
   ↓
9. Source markers are removed from final HTML
   ↓
10. Clean HTML is sent to browser with data attributes
```

### Editing Phase

```
1. User clicks editable element
   ↓
2. JavaScript reads data-cms-source attribute
   ↓
3. User makes changes
   ↓
4. handleContentChanged extracts source metadata
   ↓
5. Source metadata is stored in pendingChanges
   ↓
6. User clicks Save
   ↓
7. saveContentChange sends file_hint from source metadata
   ↓
8. Backend receives precise file path
   ↓
9. Content is updated in correct component file
```

## Usage Examples

### Basic Component

```blade
{{-- resources/views/components/alert.blade.php --}}
@cmsSourceStart
<div class="alert alert-{{ $type ?? 'info' }}">
    <h4>{{ $title }}</h4>
    <p>{{ $slot }}</p>
</div>
@cmsSourceEnd
```

### Using the Component

```blade
{{-- resources/views/welcome.blade.php --}}
<x-alert type="success" title="Welcome">
    This content will be tracked back to the alert component.
</x-alert>
```

### Partial Include

```blade
{{-- resources/views/partials/header.blade.php --}}
@cmsSourceStart
<header>
    <h1>Site Header</h1>
    <nav>Navigation</nav>
</header>
@cmsSourceEnd
```

### Wrapped Content

```blade
@cmsSource($slot)
```

## Security Features

### Path Validation

The `BladeSourceTracker::isValidSourcePath()` method ensures:

1. **No path traversal**: Blocks `..` sequences
2. **Within project root**: All paths must resolve within `base_path()`
3. **Blade files only**: Must end with `.blade.php`
4. **File existence**: Path must exist on filesystem

**Example:**
```php
// Valid
isValidSourcePath('resources/views/components/alert.blade.php') // ✓

// Invalid
isValidSourcePath('../../etc/passwd')                           // ✗
isValidSourcePath('resources/views/file.php')                   // ✗
isValidSourcePath('http://evil.com/hack.blade.php')            // ✗
```

## Testing

### Test Files Created

1. **Component:** `resources/views/components/alert.blade.php`
2. **Test Page:** `resources/views/examples/component-test.blade.php`
3. **Partial:** `resources/views/examples/partial-test.blade.php`

### Manual Testing Steps

1. **Enable the feature:**
   ```env
   CMS_COMPONENT_SOURCE_MAPPING=true
   ```

2. **Create a route** to the test page:
   ```php
   Route::get('/test-source-mapping', function () {
       return view('examples.component-test');
   });
   ```

3. **Visit the test page** and enable CMS edit mode

4. **Open browser console** to see logging

5. **Click on component content** to edit

6. **Check console output** for:
   ```
   Using source mapping metadata: resources/views/components/alert.blade.php
   ```

7. **Save changes** and verify the component file is updated

### Expected Behavior

| Content Location | data-cms-source Attribute |
|-----------------|---------------------------|
| Main view text | `resources/views/examples/component-test.blade.php` |
| Alert component text | `resources/views/components/alert.blade.php` |
| Partial content | `resources/views/examples/partial-test.blade.php` |

## Browser Console Output

When editing with source mapping enabled:

```
Using source mapping metadata: resources/views/components/alert.blade.php undefined
```

When editing without source mapping:

```
File hint resolved via route inspection: resources/views/welcome.blade.php
```

## Future Enhancements (Not Yet Implemented)

### Line Number Tracking

Currently, only file paths are tracked. Future versions could add:

```html
<p data-cms-source="components/alert.blade.php"
   data-cms-line="5">
   Content here
</p>
```

This would enable:
- Jump-to-line in editor integrations
- More precise content matching for updates
- Better handling of duplicate content

### Nested Component Tracking

Track parent-child component relationships:

```html
<div data-cms-source="layouts/app.blade.php">
  <div data-cms-source="components/header.blade.php">
    <h1 data-cms-source="components/logo.blade.php">Logo</h1>
  </div>
</div>
```

### Slot Source Tracking

Distinguish between slot definition and slot content:

```blade
{{-- Component definition --}}
<div data-cms-source="components/card.blade.php">
  {{ $slot }} {{-- Content from parent view --}}
</div>
```

## Performance Considerations

### Overhead

- **Rendering:** Minimal (only adds HTML comments during composition)
- **Processing:** Moderate (regex parsing of source markers)
- **Output Size:** Zero (markers are removed before sending to browser)
- **Attribute Size:** ~50-100 bytes per editable element

### Optimization Tips

1. **Use @cmsSourceStart/End sparingly** - Only wrap component boundaries
2. **Cache compiled views** - Laravel's view cache still works
3. **Enable only in development** - Can disable in production if not needed

## Troubleshooting

### Source attributes not appearing

**Check:**
1. Feature flag is enabled in .env
2. Blade directives are present in components
3. Browser console for errors
4. View cache is cleared: `php artisan view:clear`

### Wrong file being updated

**Possible causes:**
1. Source markers not properly closed (@cmsSourceEnd missing)
2. Nested components without proper marking
3. Cache not cleared after changes

### Security concerns

**Mitigations in place:**
- Path validation prevents directory traversal
- Only .blade.php files allowed
- Paths must be within project root
- Real path validation ensures file exists

## Files Modified

1. ✅ `config/cms.php` - Added feature flag
2. ✅ `src/CMSServiceProvider.php` - Registered service
3. ✅ `src/Services/BladeSourceTracker.php` - Created new service
4. ✅ `src/Http/Middleware/InjectEditableMarkers.php` - Added source mapping
5. ✅ `resources/views/toolbar.blade.php` - Updated JavaScript

## Files Created

1. ✅ `resources/views/components/alert.blade.php` - Test component
2. ✅ `resources/views/examples/component-test.blade.php` - Test page
3. ✅ `resources/views/examples/partial-test.blade.php` - Test partial
4. ✅ `docs/phase1-component-source-mapping.md` - This documentation

## Next Steps (Phase 2)

After Phase 1 is tested and stable:

1. **Server-Side Handling** - Update `ContentController::save` to use `file_hint`
2. **FileUpdater Enhancement** - Support component file updates
3. **Line Number Tracking** - Implement precise line tracking
4. **Automated Tests** - Add PHPUnit tests for source tracking

## References

- Original Action Plan: `docs/component-translation-action-plan.md`
- ContentController: `src/Http/Controllers/ContentController.php:89-225`
- FileUpdater: `src/Services/FileUpdater.php:22-206`
