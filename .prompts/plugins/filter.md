### Moodle Filter Plugin Structure

Every Moodle filter plugin MUST follow this structure:
```
filter_[filtername]/
├── classes/
│   ├── text_filter.php            # Main filter class (REQUIRED)
│   ├── privacy/
│   │   └── provider.php           # Privacy API implementation
│   └── external/                  # External functions (optional)
├── version.php                    # Version and dependencies (REQUIRED)
├── lang/                          # Language strings (REQUIRED)
│   └── en/
│       └── filter_[filtername].php
├── db/                            # Database and capabilities (optional)
│   ├── access.php                 # Capabilities definition
│   ├── install.xml                # Database schema
│   └── upgrade.php                # Database upgrades
├── amd/src/                       # AMD modules (optional)
├── templates/                     # Mustache templates (optional)
├── styles.css                     # Filter-specific styles (optional)
├── settings.php                   # Admin settings (optional)
├── tests/                         # Unit tests
└── filter.php                     # Legacy file (deprecated in 5.x)
```

### Key Files to Examine in Moodle Core

When developing, examine these reference implementations in the Moodle core:

1. **Base filter architecture:**
   - `../moodle/filter/classes/text_filter.php` - Abstract base class all filters extend
   - `../moodle/filter/classes/filter_manager.php` - Filter management system

2. **Example filters for patterns:**
   - `../moodle/filter/multilang/` - Simple text transformation filter
   - `../moodle/filter/glossary/` - Complex linking filter with caching
   - `../moodle/filter/emoticon/` - Icon replacement filter
   - `../moodle/filter/activitynames/` - Activity auto-linking filter
   - `../moodle/filter/urltolink/` - URL transformation filter

3. **Advanced examples:**
   - `../moodle/filter/tex/` - Mathematical notation filter
   - `../moodle/filter/mathjaxloader/` - JavaScript integration filter

## Development Guidelines

### 1. Main Filter Class (`classes/text_filter.php`)

The main class MUST:
- Be in the `filter_[filtername]` namespace
- Extend `\core_filters\text_filter`
- Implement the abstract `filter()` method

```php
namespace filter_[filtername];

class text_filter extends \core_filters\text_filter {

    // Required: Main filtering logic
    public function filter($text, array $options = []) {
        // Process and return filtered text
        return $this->process_text($text, $options);
    }

    // Optional: Setup page requirements (CSS, JS)
    public function setup($page, $context) {
        // Add any required page resources
        if ($page->requires->should_create_one_time_item_now('filter_[filtername]_setup')) {
            $page->requires->js_call_amd('filter_[filtername]/main', 'init');
        }
    }

    // Optional: Filter before format conversion
    public function filter_stage_pre_format(string $text, array $options): string {
        // Process text before HTML conversion
        return $text;
    }

    // Optional: Filter before cleaning
    public function filter_stage_pre_clean(string $text, array $options): string {
        // Process text before HTML sanitization
        return $text;
    }

    // Optional: Filter after cleaning (default implementation)
    public function filter_stage_post_clean(string $text, array $options): string {
        return $this->filter($text, $options);
    }

    // Optional: Filter strings (for simple text)
    public function filter_stage_string(string $text, array $options): string {
        return $this->filter($text, $options);
    }
}
```

### 2. Version File (`version.php`)

Must define:
```php
$plugin->component = 'filter_[filtername]';
$plugin->version = 2024010100;  // YYYYMMDDXX format
$plugin->requires = 2025040800; // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';

// Optional: Dependencies on other plugins
$plugin->dependencies = [
    'mod_glossary' => 2025040800,  // If depends on glossary module
];
```

### 3. Language Strings (`lang/en/filter_[filtername].php`)

Minimum required:
```php
$string['filtername'] = 'Your Filter Name';
$string['privacy:metadata'] = 'The [Filter Name] plugin does not store any personal data.';
```

### 4. Settings (Optional - `settings.php`)

If your filter needs admin configuration:
```php
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'filter_[filtername]/enabled',
        get_string('enabled', 'filter_[filtername]'),
        get_string('enabled_desc', 'filter_[filtername]'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'filter_[filtername]/setting',
        get_string('setting', 'filter_[filtername]'),
        get_string('setting_desc', 'filter_[filtername]'),
        'default_value'
    ));
}
```

### 5. Privacy API (`classes/privacy/provider.php`)

REQUIRED for all plugins:
```php
namespace filter_[filtername]\privacy;

class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
```

## Filter Types and Common Patterns

### 🎯 Text Transformation Filters
- **Purpose**: Transform or replace specific text patterns
- **Examples**: Multi-language content, emoticons, mathematical notation
- **Pattern**: Use regex or string replacement
- **Key Method**: Implement `filter()` with pattern matching

```php
public function filter($text, array $options = []) {
    if (empty($text) || is_numeric($text)) {
        return $text;
    }

    $search = '/pattern_to_find/i';
    return preg_replace_callback($search, [$this, 'replace_callback'], $text);
}

protected function replace_callback($matches) {
    // Process match and return replacement
    return $this->generate_replacement($matches[0]);
}
```

### 🎯 Auto-linking Filters
- **Purpose**: Create automatic links to content
- **Examples**: Glossary terms, activity names, external resources
- **Pattern**: Use `filter_phrases()` function with `filter_object`
- **Key Features**: Context-aware, performance optimized with caching

```php
public function filter($text, array $options = []) {
    $phrases = $this->get_linkable_phrases();

    if (empty($phrases)) {
        return $text;
    }

    return filter_phrases($text, $phrases);
}

protected function get_linkable_phrases() {
    // Build array of filter_object instances
    $phrases = [];
    foreach ($this->get_terms() as $term) {
        $phrases[] = new \core_filters\filter_object(
            $term->text,          // Text to match
            $link_url,            // URL to link to
            $link_title,          // Link title
            $term->casesensitive, // Case sensitive matching
            $term->fullmatch,     // Full word matching
            null,                 // Boundary regex
            [$this, 'link_callback'], // Callback function
            $term                 // Additional data
        );
    }
    return $phrases;
}
```

### 🎯 Content Enhancement Filters
- **Purpose**: Add interactive elements or enrich content
- **Examples**: Media players, interactive widgets, external content
- **Pattern**: JavaScript integration with AMD modules
- **Key Features**: Page setup with `setup()` method

```php
public function setup($page, $context) {
    if ($page->requires->should_create_one_time_item_now('filter_[filtername]_setup')) {
        $page->requires->js_call_amd('filter_[filtername]/enhancer', 'init');
        $page->requires->css('/filter/[filtername]/styles.css');
    }
}

public function filter($text, array $options = []) {
    // Add data attributes or placeholders for JavaScript to enhance
    return preg_replace_callback(
        '/pattern/',
        function($matches) {
            return '<div class="filter-[filtername]-widget" data-config="' .
                   htmlentities(json_encode($this->parse_config($matches[0]))) . '">' .
                   $matches[0] . '</div>';
        },
        $text
    );
}
```

## Performance and Caching

### Request-level Caching
```php
class text_filter extends \core_filters\text_filter {
    protected $cache = null;

    protected function get_cached_data() {
        if ($this->cache === null) {
            $this->cache = cache::make_from_params(
                cache_store::MODE_REQUEST,
                'filter',
                '[filtername]'
            );
        }

        $cached = $this->cache->get('data_key');
        if ($cached === false) {
            $cached = $this->build_expensive_data();
            $this->cache->set('data_key', $cached);
        }

        return $cached;
    }
}
```

### Context-aware Filtering
```php
public function filter($text, array $options = []) {
    // Get course context for course-specific filtering
    $coursectx = $this->context->get_course_context(false);
    if (!$coursectx) {
        return $text; // Skip if no course context
    }

    $courseid = $coursectx->instanceid;
    return $this->filter_for_course($text, $courseid);
}
```

## Testing Checklist

#### Claude Code Responsibilities (Can Verify During Development)

These items can be checked by Claude Code during development:

1. **[AUTOMATED]** ✅ PHP syntax is valid (no parse errors)
   - Claude Code can verify using PHP linter if available

2. **[AUTOMATED]** ✅ Required files exist with correct naming
   - `classes/text_filter.php`, `version.php`, language files, etc.

3. **[AUTOMATED]** ✅ File structure follows Moodle conventions
   - Correct directory structure and file placement

4. **[AUTOMATED]** ✅ Code follows Moodle coding standards
   - Can check basic standards (indentation, naming)

5. **[AUTOMATED]** ✅ Required methods are implemented
   - `filter()` method in text_filter class

6. **[AUTOMATED]** ✅ Language string keys match expected patterns
   - Verify 'filtername' and other required strings exist

7. **[AUTOMATED]** ✅ Version file has all required fields
   - Component name, version number, requirements

8. **[AUTOMATED]** ✅ GPL license headers are present
   - Check all PHP files have proper headers

9. **[AUTOMATED]** ✅ No obvious security issues
   - Check for raw SQL, unescaped output, XSS vulnerabilities

10. **[AUTOMATED]** ✅ Namespace declarations are correct
    - Verify `filter_[filtername]` namespace usage

#### Manual Testing Required

These must be tested by installing in Moodle:

11. **[MANUAL]** ⚠️ Plugin installs without errors
12. **[MANUAL]** ⚠️ Filter appears in filter management
13. **[MANUAL]** ⚠️ Filter can be enabled/disabled
14. **[MANUAL]** ⚠️ Filter processes text correctly
15. **[MANUAL]** ⚠️ No PHP errors in logs during operation
16. **[MANUAL]** ⚠️ Performance acceptable with large content
17. **[MANUAL]** ⚠️ Works correctly in different contexts
18. **[MANUAL]** ⚠️ Settings page works (if applicable)
19. **[MANUAL]** ⚠️ JavaScript integration works (if applicable)
20. **[MANUAL]** ⚠️ Database operations work correctly (if applicable)

## Security Considerations

### Input Sanitization
```php
public function filter($text, array $options = []) {
    // Always validate input
    if (!is_string($text) || empty($text)) {
        return $text;
    }

    // Use Moodle's functions for safe HTML generation
    $safehtml = html_writer::tag('span', s($content), [
        'class' => 'filter-output',
        'data-value' => s($datavalue)
    ]);

    return $safehtml;
}
```

### Database Security
```php
protected function get_terms_from_db() {
    global $DB;

    // Always use parameterized queries
    $sql = "SELECT * FROM {tablename} WHERE contextid = ? AND enabled = ?";
    return $DB->get_records_sql($sql, [$this->context->id, 1]);
}
```

### JavaScript Security
```php
public function filter($text, array $options = []) {
    // Escape data for JavaScript context
    $jsconfig = json_encode([
        'text' => $matches[0],
        'contextid' => $this->context->id
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return '<div data-config="' . s($jsconfig) . '">...</div>';
}
```

## Common Patterns to Reference

### Simple Text Replacement
Look at: `../moodle/filter/multilang/classes/text_filter.php`

### Auto-linking with Caching
Look at: `../moodle/filter/glossary/classes/text_filter.php`

### JavaScript Integration
Look at: `../moodle/filter/mathjaxloader/classes/text_filter.php`

### External API Integration
Look at: `../moodle/filter/mediaplugin/classes/text_filter.php`

## Important Notes for Claude Code

1. **Never modify files in the Moodle core repository** - only read them for reference
2. **Always check existing Moodle filters** for patterns before implementing new features
3. **Test what you can programmatically** - Use available tools to validate syntax and structure
4. **Document what needs manual testing** - Clearly indicate what the user needs to verify
5. **Follow the naming convention strictly** - Moodle is very particular about file and class names
6. **Check language string keys** - they must match expected patterns exactly
7. **Consider performance implications** - Filters run on ALL text content
8. **Be security-conscious** - Filters can introduce XSS vulnerabilities if not careful
9. **Use caching appropriately** - Don't make expensive operations on every filter call

## Filter Development Workflow

1. **Plan the filter purpose** - What text should be transformed and how?
2. **Choose the right pattern** - Text replacement, auto-linking, or content enhancement?
3. **Examine similar core filters** - Study existing implementations
4. **Create basic structure** - Set up files and basic class
5. **Implement core filtering logic** - Focus on the `filter()` method first
6. **Add performance optimizations** - Caching, early returns, etc.
7. **Implement settings if needed** - Admin configuration options
8. **Add JavaScript if needed** - AMD modules for client-side enhancement
9. **Write tests** - Both automated and manual test plans
10. **Security review** - Check for XSS, SQL injection, etc.

## Resources

- Moodle 5.0 Developer Documentation: https://moodledev.io/
- Filter API: https://moodledev.io/docs/apis/core/filters
- Plugin Development: https://moodledev.io/docs/apis/plugintypes
- Filter Management: https://moodledev.io/docs/apis/core/filters/management
- Moodle Tracker: https://tracker.moodle.org/

## Questions to Ask Before Starting

1. What specific text patterns should this filter process?
2. Should it create links, transform content, or add interactive elements?
3. Does it need to work with external APIs or services?
4. Should it have admin settings for configuration?
5. Does it need JavaScript for client-side functionality?
6. Will it need database storage for configuration or cache?
7. What contexts should it work in (course, system, user)?
8. Are there performance concerns with large content?