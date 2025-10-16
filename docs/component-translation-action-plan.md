# CMS Component Editing and Translation Conversion Action Plan

## Summary of Findings

- The save endpoint resolves a single view file per page via an inspected route or by guessing from the URL, which breaks when edited markup originates from shared Blade components or partials because their source path is never surfaced to the client. 【F:src/Http/Controllers/ContentController.php†L89-L148】【F:src/Http/Controllers/ToolbarController.php†L181-L226】
- Front-end saves rely exclusively on the inspected route hint and do not capture per-element metadata such as the originating component file, so component content edits cannot be mapped back to their source. 【F:resources/views/toolbar.blade.php†L3186-L3258】【F:src/Http/Middleware/InjectEditableMarkers.php†L144-L158】
- `FileUpdater` only supports in-place HTML/Blade replacements and lacks utilities to promote hard-coded strings into translation calls or to touch translation files when the key does not yet exist. 【F:src/Services/FileUpdater.php†L22-L206】【F:src/Http/Controllers/TranslationController.php†L33-L125】
- The translation wrapper exposes editing for existing translation keys, but there is no UX or API surface for converting raw strings into translation entries and updating the corresponding language files. 【F:src/Services/TranslationWrapper.php†L20-L129】

## Proposed Work Plan

1. **Source Mapping for Components**
   - Investigate hooking into the Blade compiler (e.g., view composers or component resolver events) to emit lightweight markers (`data-cms-source`, `data-cms-line`) around rendered fragments so that runtime DOM nodes carry their originating view/component path. Update `InjectEditableMarkers` to preserve these attributes instead of overwriting them. 【F:src/Http/Middleware/InjectEditableMarkers.php†L133-L158】
   - Extend the toolbar script to read these attributes and send them as `file_hint` overrides when saving changes, falling back to the current route inspection only when no explicit source metadata exists. 【F:resources/views/toolbar.blade.php†L3186-L3258】

2. **Server-Side Handling for Component Files**
   - Enhance `ContentController::save` to trust element-level file hints and bypass the fallback URL resolver when a component path is present. Add validation to ensure hints stay inside the project root. 【F:src/Http/Controllers/ContentController.php†L89-L225】
   - Expand `FileUpdater` with helpers that can locate and update content inside Blade component templates, including support for slot placeholders, inline attributes, and repeated component instances. Add regression coverage with fixtures under `tests` to ensure component edits modify the correct file and respect backups. 【F:src/Services/FileUpdater.php†L22-L206】

3. **Hard-Coded String → Translation Conversion**
   - Introduce a toolbar action (inline editor button or contextual modal) that lets editors select “Convert to translation,” specify a key/namespace, and choose target locales. Persist the user’s intent via a new `/api/cms/translations/convert` endpoint.
   - On the backend, create a service method that replaces the original literal with a Blade translation directive (`@lang`/`__`) in the identified file and seeds the requested locales with the captured content. Reuse `TranslationController` utilities for PHP and JSON language files and ensure each change produces backups. 【F:src/Http/Controllers/TranslationController.php†L33-L125】
   - Update `TranslationWrapper` or Blade directives so newly created translation keys remain editable in-line immediately after conversion. 【F:src/Services/TranslationWrapper.php†L20-L129】

4. **Quality and Safety Nets**
   - Add automated tests covering: (a) editing text inside a Blade component; (b) converting a literal string to a translation and verifying both the view and language files change as expected; (c) ensuring backups are created for both view and translation files. Leverage existing logging/backups in `FileUpdater`. 【F:src/Services/FileUpdater.php†L22-L206】
   - Update documentation (`README` and new how-to guides) to describe component editing requirements and the translation conversion workflow, including any necessary configuration to enable multi-locale mode.

5. **Iterative Rollout**
   - Ship component source-mapping behind a configuration flag for early adopters, gather feedback, then enable by default once stability is confirmed.
   - After validating component editing, roll out the translation conversion UI with analytics/logging to monitor adoption and catch edge cases (e.g., nested Blade directives, escaped strings) before broad release.
