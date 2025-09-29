<?php

namespace Webook\LaravelCMS\Services\UpdateStrategies;

/**
 * Blade Update Strategy
 *
 * Handles Laravel Blade template updates with awareness of Blade syntax,
 * directives, and template structure. Safely processes Blade-specific
 * constructs while preserving template functionality.
 */
class BladeStrategy extends AbstractUpdateStrategy
{
    protected array $bladeTokens = [];
    protected array $bladeStructure = [];

    /**
     * Check if this strategy can handle the content.
     *
     * @param string $content Content to check
     * @param array $context Additional context
     * @return bool True if can handle
     */
    public function canHandle(string $content, array $context = []): bool
    {
        // Check for Blade syntax patterns
        $bladePatterns = [
            '/\{\{.*?\}\}/',           // {{ }} expressions
            '/\{!!.*?!!\}/',          // {!! !!} raw output
            '/\@[a-zA-Z]+/',          // @ directives
            '/\@\{.*?\}/',            // @{} expressions
            '/\{\{\-\-.*?\-\-\}\}/',  // {{-- --}} comments
        ];

        foreach ($bladePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        // Check file extension context
        if (($context['file_extension'] ?? '') === 'blade.php') {
            return true;
        }

        return false;
    }

    /**
     * Update content with Blade awareness.
     *
     * @param string $content Original content
     * @param string $old Old value to replace
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateContent(string $content, string $old, string $new, array $context = []): string
    {
        $this->logOperation('updateContent', [
            'old_length' => strlen($old),
            'new_length' => strlen($new),
            'preserve_blade' => $context['preserve_blade'] ?? true,
        ]);

        // Tokenize Blade content for safe processing
        $this->bladeTokens = $this->tokenizeBladeFile($content);
        $this->bladeStructure = $this->parseBladeStructure($this->bladeTokens);

        // Determine update strategy based on content type
        $preserveBlade = $context['preserve_blade'] ?? true;

        if ($preserveBlade) {
            return $this->updateWithBladeAwareness($content, $old, $new, $context);
        } else {
            return $this->updateWithTextReplacement($content, $old, $new, $context);
        }
    }

    /**
     * Update by selector (Blade-specific selectors).
     *
     * @param string $content Original content
     * @param string $selector Blade selector
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateBySelector(string $content, string $selector, string $new, array $context = []): string
    {
        $this->logOperation('updateBySelector', [
            'selector' => $selector,
            'selector_type' => $this->detectSelectorType($selector),
        ]);

        $this->bladeTokens = $this->tokenizeBladeFile($content);

        $selectorType = $this->detectSelectorType($selector);

        return match ($selectorType) {
            'directive' => $this->updateBladeDirective($content, $selector, $new, $context),
            'variable' => $this->updateBladeVariable($content, $selector, $new, $context),
            'section' => $this->updateBladeSection($content, $selector, $new, $context),
            'component' => $this->updateBladeComponent($content, $selector, $new, $context),
            default => parent::updateBySelector($content, $selector, $new, $context),
        };
    }

    /**
     * Update attribute (Blade component attributes).
     *
     * @param string $content Original content
     * @param string $selector Component selector
     * @param string $attribute Attribute name
     * @param string $value New value
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateAttribute(string $content, string $selector, string $attribute, string $value, array $context = []): string
    {
        $this->logOperation('updateAttribute', [
            'selector' => $selector,
            'attribute' => $attribute,
        ]);

        // Handle Blade component attributes
        if (str_starts_with($selector, 'x-')) {
            return $this->updateComponentAttribute($content, $selector, $attribute, $value, $context);
        }

        // Fall back to DOM strategy for HTML attributes
        $domStrategy = new DOMStrategy($this->config);
        if ($domStrategy->canHandle($content, $context)) {
            return $domStrategy->updateAttribute($content, $selector, $attribute, $value, $context);
        }

        return $content;
    }

    /**
     * Tokenize Blade file into meaningful tokens.
     *
     * @param string $content Blade file content
     * @return array Array of tokens
     */
    private function tokenizeBladeFile(string $content): array
    {
        $tokens = [];
        $position = 0;
        $length = strlen($content);

        while ($position < $length) {
            $token = $this->getNextToken($content, $position);
            if ($token) {
                $tokens[] = $token;
                $position = $token['end'];
            } else {
                $position++;
            }
        }

        return $tokens;
    }

    /**
     * Get next token from content.
     *
     * @param string $content Content to tokenize
     * @param int $position Current position
     * @return array|null Token information
     */
    private function getNextToken(string $content, int $position): ?array
    {
        $patterns = [
            'comment' => '/\{\{\-\-(.*?)\-\-\}\}/s',
            'raw_output' => '/\{!!(.*?)!!\}/s',
            'escaped_output' => '/\{\{(.*?)\}\}/s',
            'directive' => '/\@([a-zA-Z_]+)(\([^)]*\))?/s',
            'php_block' => '/<\?php(.*?)\?>/s',
            'html_tag' => '/<([a-zA-Z][^>]*)>/s',
        ];

        $substring = substr($content, $position);

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $substring, $matches, PREG_OFFSET_CAPTURE)) {
                if ($matches[0][1] === 0) { // Match at current position
                    return [
                        'type' => $type,
                        'content' => $matches[0][0],
                        'start' => $position,
                        'end' => $position + strlen($matches[0][0]),
                        'matches' => $matches,
                    ];
                }
            }
        }

        // No specific token found, treat as text
        $nextSpecialPos = $this->findNextSpecialPosition($substring);
        $textLength = $nextSpecialPos > 0 ? $nextSpecialPos : strlen($substring);

        if ($textLength > 0) {
            return [
                'type' => 'text',
                'content' => substr($substring, 0, $textLength),
                'start' => $position,
                'end' => $position + $textLength,
                'matches' => [],
            ];
        }

        return null;
    }

    /**
     * Find next position where a special Blade construct begins.
     *
     * @param string $content Content to search
     * @return int Position or -1 if not found
     */
    private function findNextSpecialPosition(string $content): int
    {
        $positions = [];

        // Find positions of Blade constructs
        if (preg_match('/[\{\@<]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $positions[] = $matches[0][1];
        }

        return empty($positions) ? -1 : min($positions);
    }

    /**
     * Parse Blade structure from tokens.
     *
     * @param array $tokens Array of tokens
     * @return array Parsed structure
     */
    private function parseBladeStructure(array $tokens): array
    {
        $structure = [
            'directives' => [],
            'variables' => [],
            'sections' => [],
            'components' => [],
            'includes' => [],
        ];

        foreach ($tokens as $token) {
            switch ($token['type']) {
                case 'directive':
                    $structure['directives'][] = $this->parseDirective($token);
                    break;
                case 'escaped_output':
                case 'raw_output':
                    $structure['variables'][] = $this->parseVariable($token);
                    break;
                case 'html_tag':
                    if ($this->isBladeComponent($token['content'])) {
                        $structure['components'][] = $this->parseComponent($token);
                    }
                    break;
            }
        }

        return $structure;
    }

    /**
     * Parse directive token.
     *
     * @param array $token Token to parse
     * @return array Parsed directive
     */
    private function parseDirective(array $token): array
    {
        $matches = $token['matches'];
        $directive = $matches[1][0] ?? '';
        $parameters = isset($matches[2][0]) ? trim($matches[2][0], '()') : '';

        return [
            'name' => $directive,
            'parameters' => $parameters,
            'full_content' => $token['content'],
            'position' => $token['start'],
        ];
    }

    /**
     * Parse variable token.
     *
     * @param array $token Token to parse
     * @return array Parsed variable
     */
    private function parseVariable(array $token): array
    {
        $matches = $token['matches'];
        $expression = trim($matches[1][0] ?? '');

        return [
            'expression' => $expression,
            'type' => $token['type'],
            'full_content' => $token['content'],
            'position' => $token['start'],
        ];
    }

    /**
     * Parse component token.
     *
     * @param array $token Token to parse
     * @return array Parsed component
     */
    private function parseComponent(array $token): array
    {
        $content = $token['content'];
        preg_match('/<(x-[^>\s]+)([^>]*)>/i', $content, $matches);

        $name = $matches[1] ?? '';
        $attributes = $this->parseComponentAttributes($matches[2] ?? '');

        return [
            'name' => $name,
            'attributes' => $attributes,
            'full_content' => $content,
            'position' => $token['start'],
        ];
    }

    /**
     * Parse component attributes.
     *
     * @param string $attributeString Attribute string
     * @return array Parsed attributes
     */
    private function parseComponentAttributes(string $attributeString): array
    {
        $attributes = [];

        if (preg_match_all('/([a-zA-Z:_-]+)(?:=(["\'])([^"\']*)\2)?/', $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = $match[3] ?? '';
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Check if HTML tag is a Blade component.
     *
     * @param string $tag HTML tag
     * @return bool True if Blade component
     */
    private function isBladeComponent(string $tag): bool
    {
        return preg_match('/<x-[^>\s]+/i', $tag) === 1;
    }

    /**
     * Find elements in Blade AST.
     *
     * @param array $ast AST structure
     * @param string $search Search criteria
     * @return array|null Found elements
     */
    private function findInBladeAST(array $ast, string $search): ?array
    {
        // Search in directives
        foreach ($ast['directives'] as $directive) {
            if ($directive['name'] === $search) {
                return $directive;
            }
        }

        // Search in components
        foreach ($ast['components'] as $component) {
            if ($component['name'] === $search) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Validate Blade syntax.
     *
     * @param string $content Content to validate
     * @return bool True if valid
     */
    private function validateBladeSyntax(string $content): bool
    {
        // Check for balanced Blade constructs
        $patterns = [
            '/\{\{/' => '/\}\}/',           // {{ }}
            '/\{!!/' => '/!!\}/',          // {!! !!}
            '/\{\{\-\-/' => '/\-\-\}\}/',  // {{-- --}}
        ];

        foreach ($patterns as $open => $close) {
            $openCount = preg_match_all($open, $content);
            $closeCount = preg_match_all($close, $content);

            if ($openCount !== $closeCount) {
                return false;
            }
        }

        // Check for common directive pairs
        $directivePairs = [
            'if' => 'endif',
            'foreach' => 'endforeach',
            'for' => 'endfor',
            'while' => 'endwhile',
            'switch' => 'endswitch',
            'section' => 'endsection',
            'push' => 'endpush',
        ];

        foreach ($directivePairs as $start => $end) {
            $startCount = preg_match_all('/@' . $start . '\b/', $content);
            $endCount = preg_match_all('/@' . $end . '\b/', $content);

            if ($startCount !== $endCount) {
                return false;
            }
        }

        return true;
    }

    /**
     * Preserve Blade directives during updates.
     *
     * @param string $content Content to process
     * @return string Processed content
     */
    private function preserveBladeDirectives(string $content): string
    {
        $placeholder = '___BLADE_DIRECTIVE_%d___';
        $directives = [];
        $counter = 0;

        // Extract and replace directives with placeholders
        $patterns = [
            '/\{\{.*?\}\}/s',
            '/\{!!.*?!!\}/s',
            '/\@[a-zA-Z_]+(\([^)]*\))?/s',
            '/\{\{\-\-.*?\-\-\}\}/s',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, function ($matches) use (&$directives, &$counter, $placeholder) {
                $key = sprintf($placeholder, $counter++);
                $directives[$key] = $matches[0];
                return $key;
            }, $content);
        }

        // Store directives for later restoration
        $this->bladeDirectives = $directives;

        return $content;
    }

    /**
     * Restore Blade directives after updates.
     *
     * @param string $content Content with placeholders
     * @return string Content with restored directives
     */
    private function restoreBladeDirectives(string $content): string
    {
        if (empty($this->bladeDirectives)) {
            return $content;
        }

        return str_replace(array_keys($this->bladeDirectives), array_values($this->bladeDirectives), $content);
    }

    /**
     * Update with Blade awareness.
     *
     * @param string $content Original content
     * @param string $old Old value
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateWithBladeAwareness(string $content, string $old, string $new, array $context): string
    {
        // Preserve Blade directives
        $preservedContent = $this->preserveBladeDirectives($content);

        // Perform text replacement on preserved content
        $updatedContent = str_replace($old, $new, $preservedContent);

        // Restore Blade directives
        return $this->restoreBladeDirectives($updatedContent);
    }

    /**
     * Update with simple text replacement.
     *
     * @param string $content Original content
     * @param string $old Old value
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateWithTextReplacement(string $content, string $old, string $new, array $context): string
    {
        return str_replace($old, $new, $content);
    }

    /**
     * Detect selector type.
     *
     * @param string $selector Selector string
     * @return string Selector type
     */
    protected function detectSelectorType(string $selector): string
    {
        if (str_starts_with($selector, '@')) {
            return 'directive';
        }

        if (str_starts_with($selector, '{{') || str_starts_with($selector, '{!!')) {
            return 'variable';
        }

        if (str_starts_with($selector, 'section:')) {
            return 'section';
        }

        if (str_starts_with($selector, 'x-')) {
            return 'component';
        }

        return 'unknown';
    }

    /**
     * Update Blade directive.
     *
     * @param string $content Original content
     * @param string $selector Directive selector
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateBladeDirective(string $content, string $selector, string $new, array $context): string
    {
        $directiveName = ltrim($selector, '@');
        $pattern = '/@' . preg_quote($directiveName, '/') . '(\([^)]*\))?/';

        return preg_replace($pattern, $new, $content);
    }

    /**
     * Update Blade variable.
     *
     * @param string $content Original content
     * @param string $selector Variable selector
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateBladeVariable(string $content, string $selector, string $new, array $context): string
    {
        $escaped = preg_quote($selector, '/');
        return preg_replace('/' . $escaped . '/', $new, $content);
    }

    /**
     * Update Blade section.
     *
     * @param string $content Original content
     * @param string $selector Section selector
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateBladeSection(string $content, string $selector, string $new, array $context): string
    {
        $sectionName = str_replace('section:', '', $selector);
        $pattern = '/@section\s*\(\s*[\'"]' . preg_quote($sectionName, '/') . '[\'"]\s*\)(.*?)@endsection/s';

        return preg_replace($pattern, '@section(\'' . $sectionName . '\')' . $new . '@endsection', $content);
    }

    /**
     * Update Blade component.
     *
     * @param string $content Original content
     * @param string $selector Component selector
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateBladeComponent(string $content, string $selector, string $new, array $context): string
    {
        $pattern = '/<' . preg_quote($selector, '/') . '[^>]*>(.*?)<\/' . preg_quote($selector, '/') . '>/s';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $new, $content);
        }

        // Self-closing component
        $selfClosingPattern = '/<' . preg_quote($selector, '/') . '[^>]*\/>/';
        return preg_replace($selfClosingPattern, $new, $content);
    }

    /**
     * Update component attribute.
     *
     * @param string $content Original content
     * @param string $selector Component selector
     * @param string $attribute Attribute name
     * @param string $value New value
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateComponentAttribute(string $content, string $selector, string $attribute, string $value, array $context): string
    {
        $pattern = '/(<' . preg_quote($selector, '/') . '[^>]*?)(\s+' . preg_quote($attribute, '/') . '=(["\'])[^"\']*\3)([^>]*>)/';

        if (preg_match($pattern, $content)) {
            $replacement = '$1 ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES) . '"$4';
            return preg_replace($pattern, $replacement, $content);
        }

        // Add new attribute
        $pattern = '/(<' . preg_quote($selector, '/') . ')([^>]*>)/';
        $replacement = '$1 ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES) . '"$2';
        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Validate Blade content.
     *
     * @param string $content Content to validate
     * @param array $context Additional context
     * @return array Validation results
     */
    public function validate(string $content, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Check Blade syntax
        if (!$this->validateBladeSyntax($content)) {
            $errors[] = 'Blade syntax validation failed - unbalanced directives or expressions';
        }

        // Check for common Blade issues
        if (preg_match('/\{\{\s*\$[^}]*\}\}.*?\{\{\s*\$[^}]*\}\}/', $content)) {
            $warnings[] = 'Multiple Blade expressions on same line may cause issues';
        }

        // Check for potential XSS vulnerabilities
        if (preg_match('/\{!!\s*\$_[A-Z]+/', $content)) {
            $errors[] = 'Potential XSS vulnerability: unescaped superglobal variable';
        }

        // Validate PHP syntax in PHP blocks
        if (preg_match_all('/<\?php(.*?)\?>/s', $content, $matches)) {
            foreach ($matches[1] as $phpCode) {
                if (!$this->validatePhpSyntax($phpCode)) {
                    $errors[] = 'Invalid PHP syntax in PHP block';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate PHP syntax.
     *
     * @param string $code PHP code to validate
     * @return bool True if valid
     */
    protected function validatePhpSyntax(string $code): bool
    {
        $code = "<?php\n" . $code;
        return php_check_syntax($code, $code) !== false;
    }

    /**
     * Get strategy priority.
     *
     * @return int Priority level
     */
    public function getPriority(): int
    {
        return 90; // Highest priority for Blade content
    }

    /**
     * Get strategy name.
     *
     * @return string Strategy name
     */
    public function getName(): string
    {
        return 'BladeStrategy';
    }

    /**
     * Get default configuration.
     *
     * @return array Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'preserve_blade_comments' => true,
            'validate_php_syntax' => true,
            'strict_blade_validation' => true,
            'allow_raw_php' => false,
        ]);
    }
}