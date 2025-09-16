# Filter Development Patterns and Anti-Patterns

## ⚠️ CRITICAL MOODLE 5.x FILTER PATTERNS & BUG PREVENTION

### 🚫 Text Filter Implementation Anti-Patterns

**NEVER DO THIS - Missing Security:**
```php
// ❌ WRONG - Unsafe HTML generation with user input
class text_filter extends \core_filters\text_filter {
    public function filter($text, array $options = []) {
        return preg_replace_callback('/\[widget\](.*?)\[\/widget\]/', function($matches) {
            // Direct HTML output without escaping - XSS vulnerability!
            return '<div class="widget">' . $matches[1] . '</div>';
        }, $text);
    }
}
```

**✅ CORRECT - Secure Text Processing:**
```php
// ✅ CORRECT - Properly escaped output
class text_filter extends \core_filters\text_filter {
    public function filter($text, array $options = []) {
        return preg_replace_callback('/\[widget\](.*?)\[\/widget\]/', function($matches) {
            // Always escape user content
            return html_writer::tag('div', s($matches[1]), [
                'class' => 'widget',
                'data-original' => s($matches[0])
            ]);
        }, $text);
    }
}
```

### 🚫 Namespace Anti-Patterns

**NEVER DO THIS - Wrong Namespace:**
```php
// ❌ WRONG - Missing or incorrect namespace
namespace filter;  // Too generic
// OR
namespace my_filter;  // Wrong naming convention
// OR
// No namespace declaration at all
```

**✅ CORRECT - Proper Namespace:**
```php
// ✅ CORRECT - Follow Moodle naming conventions
namespace filter_yourfiltername;

use core\output\html_writer;
use core_filters\filter_object;
```

### 🚫 Performance Anti-Patterns

**NEVER DO THIS - Expensive Operations in Filter:**
```php
// ❌ WRONG - Database queries on every filter call
class text_filter extends \core_filters\text_filter {
    public function filter($text, array $options = []) {
        global $DB;

        // This runs on EVERY piece of text - terrible performance!
        $records = $DB->get_records('some_table');

        foreach ($records as $record) {
            $text = str_replace($record->pattern, $record->replacement, $text);
        }
        return $text;
    }
}
```

**✅ CORRECT - Cached Operations:**
```php
// ✅ CORRECT - Use request-level caching
class text_filter extends \core_filters\text_filter {
    protected $cache = null;

    public function filter($text, array $options = []) {
        $patterns = $this->get_cached_patterns();

        if (empty($patterns)) {
            return $text;
        }

        return $this->apply_patterns($text, $patterns);
    }

    protected function get_cached_patterns() {
        if ($this->cache === null) {
            $this->cache = cache::make_from_params(
                cache_store::MODE_REQUEST,
                'filter',
                'yourfiltername'
            );
        }

        $patterns = $this->cache->get('patterns');
        if ($patterns === false) {
            global $DB;
            $patterns = $DB->get_records('your_patterns_table');
            $this->cache->set('patterns', $patterns);
        }

        return $patterns;
    }
}
```

### 🚫 Auto-linking Anti-Patterns

**NEVER DO THIS - Manual String Replacement for Links:**
```php
// ❌ WRONG - Manual replacement misses edge cases
public function filter($text, array $options = []) {
    $terms = $this->get_terms();

    foreach ($terms as $term) {
        // This approach has many problems:
        // - No boundary checking
        // - Breaks HTML structure
        // - Poor performance
        $text = str_replace(
            $term->name,
            '<a href="' . $term->url . '">' . $term->name . '</a>',
            $text
        );
    }

    return $text;
}
```

**✅ CORRECT - Use Moodle's filter_phrases():**
```php
// ✅ CORRECT - Proper auto-linking with filter_object
public function filter($text, array $options = []) {
    $phrases = $this->build_filter_objects();

    if (empty($phrases)) {
        return $text;
    }

    // filter_phrases() handles HTML safety, boundaries, performance
    return filter_phrases($text, $phrases);
}

protected function build_filter_objects() {
    $terms = $this->get_cached_terms();
    $phrases = [];

    foreach ($terms as $term) {
        $phrases[] = new \core_filters\filter_object(
            $term->name,              // Text to match
            null,                     // Link URL (handled by callback)
            null,                     // Link title (handled by callback)
            $term->casesensitive,     // Case sensitivity
            $term->fullmatch,         // Full word matching
            null,                     // Custom boundary regex
            [$this, 'link_callback'], // Replacement callback
            $term                     // Data for callback
        );
    }

    return filter_prepare_phrases_for_filtering($phrases);
}

public function link_callback($term) {
    $url = new moodle_url('/your/link/page.php', ['id' => $term->id]);
    $attributes = [
        'href' => $url,
        'title' => s($term->description),
        'class' => 'filter-yourname-link'
    ];

    return [
        html_writer::start_tag('a', $attributes),
        '</a>',
        null
    ];
}
```

### 🚫 JavaScript Integration Anti-Patterns

**NEVER DO THIS - Inline JavaScript:**
```php
// ❌ WRONG - Inline JavaScript and direct output
public function filter($text, array $options = []) {
    return preg_replace_callback('/\[interactive\]/', function($matches) {
        // Inline JavaScript is bad practice and security risk
        return '<div onclick="alert(\'clicked\')" class="interactive">...</div>';
    }, $text);
}
```

**✅ CORRECT - AMD Module Integration:**
```php
// ✅ CORRECT - Proper AMD module setup
public function setup($page, $context) {
    // Only load once per page
    if ($page->requires->should_create_one_time_item_now('filter_yourname_setup')) {
        $page->requires->js_call_amd('filter_yourname/interactive', 'init');
        $page->requires->css('/filter/yourname/styles.css');
    }
}

public function filter($text, array $options = []) {
    return preg_replace_callback('/\[interactive\](.*?)\[\/interactive\]/', function($matches) {
        // Generate safe data attributes for JavaScript to pick up
        $config = [
            'content' => $matches[1],
            'contextid' => $this->context->id
        ];

        return html_writer::div(
            s($matches[1]),
            'filter-yourname-interactive',
            ['data-config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP)]
        );
    }, $text);
}
```

### 🚫 Database Anti-Patterns

**NEVER DO THIS - Unsafe Database Queries:**
```php
// ❌ WRONG - SQL injection vulnerability
protected function get_terms($search) {
    global $DB;

    // Direct string concatenation - SQL injection risk!
    $sql = "SELECT * FROM {filter_terms} WHERE name LIKE '%" . $search . "%'";
    return $DB->get_records_sql($sql);
}
```

**✅ CORRECT - Parameterized Queries:**
```php
// ✅ CORRECT - Safe parameterized queries
protected function get_terms($search) {
    global $DB;

    $sql = "SELECT * FROM {filter_terms} WHERE name LIKE ? AND contextid = ?";
    $params = ['%' . $DB->sql_like_escape($search) . '%', $this->context->id];

    return $DB->get_records_sql($sql, $params);
}
```

### 🚫 Version File Anti-Patterns

**NEVER DO THIS - Incorrect Version File:**
```php
// ❌ WRONG - Missing fields or wrong naming
$plugin->name = 'My Filter';           // Should be 'component'
$plugin->version = '1.0';              // Should be YYYYMMDDXX format
$plugin->requires = '3.9';             // Should be full version number
$plugin->component = 'my_filter';      // Wrong naming convention
```

**✅ CORRECT - Proper Version File:**
```php
// ✅ CORRECT - Complete and correctly formatted
$plugin->component = 'filter_yourfiltername';  // Correct naming
$plugin->version = 2024010100;                 // YYYYMMDDXX format
$plugin->requires = 2025040800;                // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0.0';

// Optional dependencies
$plugin->dependencies = [
    'mod_glossary' => 2025040800
];
```

### 🚫 Settings Anti-Patterns

**NEVER DO THIS - Insecure Settings:**
```php
// ❌ WRONG - No validation or unsafe defaults
if ($ADMIN->fulltree) {
    // No validation - allows any input
    $settings->add(new admin_setting_configtext(
        'filter_yourname/apikey',
        'API Key',
        'Enter your API key',
        ''  // Empty default for sensitive data
    ));

    // Boolean setting with wrong type
    $settings->add(new admin_setting_configtext(  // Should be configcheckbox
        'filter_yourname/enabled',
        'Enable feature',
        'Check to enable',
        'yes'  // String instead of boolean
    ));
}
```

**✅ CORRECT - Secure Settings:**
```php
// ✅ CORRECT - Proper validation and types
if ($ADMIN->fulltree) {
    // Secure password field with validation
    $settings->add(new admin_setting_configpasswordunmask(
        'filter_yourname/apikey',
        get_string('apikey', 'filter_yourname'),
        get_string('apikey_desc', 'filter_yourname'),
        ''
    ));

    // Proper boolean setting
    $settings->add(new admin_setting_configcheckbox(
        'filter_yourname/enabled',
        get_string('enabled', 'filter_yourname'),
        get_string('enabled_desc', 'filter_yourname'),
        1  // Boolean default
    ));

    // Select with validated options
    $settings->add(new admin_setting_configselect(
        'filter_yourname/mode',
        get_string('mode', 'filter_yourname'),
        get_string('mode_desc', 'filter_yourname'),
        'default',
        ['default' => 'Default', 'advanced' => 'Advanced']
    ));
}
```

### 🚫 Language String Anti-Patterns

**NEVER DO THIS - Missing or Wrong Language Strings:**
```php
// ❌ WRONG - Hardcoded strings
public function filter($text, array $options = []) {
    return '<div title="Click me">Content</div>';  // Hardcoded text
}

// ❌ WRONG - Wrong language file location or naming
// File: lang/en/my_filter.php  (should be filter_filtername.php)
$string['name'] = 'Filter Name';  // Should be 'filtername'
```

**✅ CORRECT - Proper Internationalization:**
```php
// ✅ CORRECT - Use get_string()
public function filter($text, array $options = []) {
    $title = get_string('clickme', 'filter_yourname');
    return html_writer::div('Content', '', ['title' => $title]);
}

// ✅ CORRECT - Proper language file
// File: lang/en/filter_yourname.php
$string['filtername'] = 'Your Filter Name';
$string['clickme'] = 'Click me';
$string['privacy:metadata'] = 'The Your Filter Name plugin does not store any personal data.';
```

### 🚫 Context Handling Anti-Patterns

**NEVER DO THIS - Context Misuse:**
```php
// ❌ WRONG - Assuming specific context type
public function filter($text, array $options = []) {
    // This will break if filter runs in system or user context!
    $course = get_course($this->context->instanceid);

    // Processing assuming course context...
}
```

**✅ CORRECT - Context-Aware Processing:**
```php
// ✅ CORRECT - Handle different context types
public function filter($text, array $options = []) {
    // Check context type before processing
    $coursectx = $this->context->get_course_context(false);

    if (!$coursectx) {
        // Handle system/user context differently
        return $this->filter_global($text);
    }

    $courseid = $coursectx->instanceid;
    return $this->filter_course($text, $courseid);
}
```

### 🚫 Error Handling Anti-Patterns

**NEVER DO THIS - Broken Error Handling:**
```php
// ❌ WRONG - Silent failures or exposed errors
public function filter($text, array $options = []) {
    try {
        return $this->process_complex_operation($text);
    } catch (Exception $e) {
        // Silent failure - users won't know what happened
        return $text;
        // OR worse - expose error details
        return '<div class="error">' . $e->getMessage() . '</div>';
    }
}
```

**✅ CORRECT - Proper Error Handling:**
```php
// ✅ CORRECT - Log errors, return safe fallback
public function filter($text, array $options = []) {
    try {
        return $this->process_complex_operation($text);
    } catch (Exception $e) {
        // Log the error for debugging
        debugging('Filter processing failed: ' . $e->getMessage(), DEBUG_NORMAL);

        // Return original text as safe fallback
        return $text;
    }
}
```

## 🎯 PROVEN PATTERNS FOR COMMON FILTER TYPES

### Text Replacement Pattern
```php
class text_filter extends \core_filters\text_filter {
    public function filter($text, array $options = []) {
        if (empty($text) || is_numeric($text)) {
            return $text;
        }

        $patterns = $this->get_replacement_patterns();

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }
}
```

### Auto-linking Pattern with Caching
```php
class text_filter extends \core_filters\text_filter {
    protected $cache = null;

    public function filter($text, array $options = []) {
        $phrases = $this->get_linkable_phrases();

        if (empty($phrases)) {
            return $text;
        }

        return filter_phrases($text, $phrases);
    }

    protected function get_linkable_phrases() {
        $cached = $this->get_cached_terms();
        $phrases = [];

        foreach ($cached as $term) {
            $phrases[] = new \core_filters\filter_object(
                $term->text,
                null,
                null,
                $term->casesensitive,
                $term->fullmatch,
                null,
                [$this, 'generate_link'],
                $term
            );
        }

        return filter_prepare_phrases_for_filtering($phrases);
    }
}
```

### JavaScript Enhancement Pattern
```php
class text_filter extends \core_filters\text_filter {
    public function setup($page, $context) {
        if ($page->requires->should_create_one_time_item_now('filter_yourname_init')) {
            $page->requires->js_call_amd('filter_yourname/enhancer', 'init');
        }
    }

    public function filter($text, array $options = []) {
        return preg_replace_callback(
            '/\[enhance\](.*?)\[\/enhance\]/',
            [$this, 'create_enhancement_placeholder'],
            $text
        );
    }

    protected function create_enhancement_placeholder($matches) {
        return html_writer::div(
            s($matches[1]),
            'filter-enhancement',
            ['data-enhance' => json_encode(['content' => $matches[1]])]
        );
    }
}
```

## 🔒 SECURITY CHECKLIST

- [ ] ✅ All user input is properly escaped with `s()` or `html_writer`
- [ ] ✅ Database queries use parameters, not string concatenation
- [ ] ✅ JSON data for JavaScript is properly encoded with flags
- [ ] ✅ No inline JavaScript or event handlers
- [ ] ✅ External URLs are validated before use
- [ ] ✅ File operations use Moodle's file API
- [ ] ✅ Context capabilities are checked before processing
- [ ] ✅ Error messages don't expose sensitive information

## 🚀 PERFORMANCE CHECKLIST

- [ ] ✅ Use request-level caching for expensive operations
- [ ] ✅ Early return for empty or numeric text
- [ ] ✅ Avoid database queries in main filter() method
- [ ] ✅ Use filter_phrases() instead of manual string replacement
- [ ] ✅ Compile regex patterns once, not per use
- [ ] ✅ Sort filter objects by length (longest first)
- [ ] ✅ Check context type to avoid unnecessary processing

## 🧪 TESTING PATTERNS

### Unit Test Pattern
```php
class filter_test extends \advanced_testcase {
    public function test_basic_filtering() {
        $this->resetAfterTest(true);

        $filter = new \filter_yourname\text_filter(
            \context_system::instance(),
            []
        );

        $input = 'Test input [tag]content[/tag]';
        $expected = 'Test input <div class="filtered">content</div>';

        $this->assertEquals($expected, $filter->filter($input));
    }
}
```

### Integration Test Pattern
```php
public function test_filter_in_course_context() {
    $this->resetAfterTest(true);

    $course = $this->getDataGenerator()->create_course();
    $context = \context_course::instance($course->id);

    $filter = new \filter_yourname\text_filter($context, []);

    // Test course-specific behavior
    $result = $filter->filter('Course content');
    $this->assertStringContains('course-specific', $result);
}
```