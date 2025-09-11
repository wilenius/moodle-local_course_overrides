### Moodle Block Plugin Structure

Every Moodle block plugin MUST follow this structure:
```
[blockname]/
├── block_[blockname].php       # Main block class file (REQUIRED)
├── version.php                  # Version and dependencies (REQUIRED)
├── lang/                        # Language strings (REQUIRED)
│   └── en/
│       └── block_[blockname].php
├── db/                          # Database and capabilities
│   ├── access.php              # Capabilities definition
│   ├── install.xml             # Database schema
│   └── upgrade.php             # Database upgrades
├── classes/                    # Autoloaded classes (PSR-4)
├── styles.css                  # Block-specific styles
├── settings.php                # Admin settings
└── README.md                   # Documentation
```

### Key Files to Examine in Moodle Core

When developing, examine these reference implementations in the Moodle core:

1. **Example blocks for patterns:**
   - `../moodle/blocks/html/` - Simple content block
   - `../moodle/blocks/navigation/` - Complex navigation block
   - `../moodle/blocks/recent_activity/` - Block with database queries

2. **Core block base class:**
   - `../moodle/blocks/moodleblock.class.php` - Base class all blocks extend

3. **Block library functions:**
   - `../moodle/lib/blocklib.php` - Core block functionality

## Development Guidelines

### 1. Main Block Class (`block_[blockname].php`)

The main class MUST:
- Extend `block_base` or `block_list`
- Implement required methods:
  ```php
  class block_[blockname] extends block_base {
      public function init() {
          $this->title = get_string('pluginname', 'block_[blockname]');
      }

      public function get_content() {
          // Return block content
      }

      public function applicable_formats() {
          // Define where block can be used
      }
  }
  ```

### 2. Version File (`version.php`)

Must define:
```php
$plugin->component = 'block_[blockname]';
$plugin->version = 2024010100;  // YYYYMMDDXX format
$plugin->requires = 2023100900; // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';
```

### 3. Language Strings (`lang/en/block_[blockname].php`)

Minimum required:
```php
$string['pluginname'] = 'Your Block Name';
$string['[blockname]:addinstance'] = 'Add a new Your Block Name';
$string['[blockname]:myaddinstance'] = 'Add Your Block Name to Dashboard';
```

### 4. Capabilities (`db/access.php`)

Define permissions:
```php
$capabilities = array(
    'block/[blockname]:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    )
);
```

### Testing Checklist

#### Claude Code Responsibilities (Can Verify During Development)

These items can be checked by Claude Code during development:

1. **[AUTOMATED]** ✅ PHP syntax is valid (no parse errors)
   - Claude Code can verify using PHP linter if shell/command MCP is available

2. **[AUTOMATED]** ✅ Required files exist with correct naming
   - `block_[blockname].php`, `version.php`, language files, etc.

3. **[AUTOMATED]** ✅ File structure follows Moodle conventions
   - Correct directory structure and file placement

4. **[AUTOMATED]** ✅ Code follows Moodle coding standards
   - Can check with `phpcs` if shell MCP is available
   - Otherwise, can verify basic standards (indentation, naming)

5. **[AUTOMATED]** ✅ Required methods are implemented
   - `init()`, `get_content()`, etc. in block class

6. **[AUTOMATED]** ✅ Language string keys match expected patterns
   - Verify 'pluginname' and capability strings exist

7. **[AUTOMATED]** ✅ Version file has all required fields
   - Component name, version number, requirements

8. **[AUTOMATED]** ✅ GPL license headers are present
   - Check all PHP files have proper headers

9. **[AUTOMATED]** ✅ No obvious security issues
   - Check for raw SQL, unescaped output, etc.

## Common Patterns to Reference

### Getting User Context
Look at: `../moodle/blocks/myoverview/block_myoverview.php`

### Database Queries
Look at: `../moodle/blocks/recent_activity/block_recent_activity.php`

### AJAX/JavaScript Integration
Look at: `../moodle/blocks/navigation/block_navigation.php`

### Settings Form
Look at: `../moodle/blocks/html/edit_form.php`

## Required Library Includes

**CRITICAL:** Always include required Moodle libraries to prevent "Class not found" errors.

### Common Required Includes

Add these includes at the top of your block class file **before** the class declaration:

```php
// For grade-related functionality
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');

// For course-related functionality
require_once($CFG->dirroot . '/course/lib.php');

// For user-related functionality
require_once($CFG->dirroot . '/user/lib.php');

// For group-related functionality
require_once($CFG->libdir . '/grouplib.php');

// For messaging functionality
require_once($CFG->dirroot . '/message/lib.php');

// For file/repository functionality
require_once($CFG->libdir . '/filelib.php');

// For backup/restore functionality
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
```

### When to Include Libraries

- **Grade classes** (`grade_item`, `grade_grade`, `grade_category`): Include `gradelib.php` and `grade/lib.php`
- **Course functions** (`get_course`, course manipulation): Include `course/lib.php`
- **User functions** (beyond basic `get_enrolled_users`): Include `user/lib.php`
- **Group functions** (`groups_get_*`): Include `grouplib.php`
- **File handling** (`file_storage`, `stored_file`): Include `filelib.php`
- **Messaging** (`message_send`): Include `message/lib.php`

### Auto-loading vs Manual Includes

Modern Moodle uses auto-loading for classes in the `/classes/` directory, but many core APIs still require manua
l includes. **Always include required libraries** when using:

- Any `grade_*` classes
- Course management functions
- Group management functions
- File/repository APIs
- Messaging APIs
- Backup/restore APIs

### Testing for Missing Includes

Add this to your automated testing checklist:
- **[AUTOMATED]** ✅ All required libraries are included
  - Check that classes used in the code have corresponding includes
  - Verify no "Class not found" errors during PHP syntax validation

## Debugging Tips

1. Enable debugging in Moodle:
   - Set `$CFG->debug = DEBUG_DEVELOPER;` in config.php
   - Set `$CFG->debugdisplay = 1;`

2. Check error logs:
   - PHP error log
   - Moodle's error log at `/admin/report/log/index.php`

3. Use debugging functions:
   - `debugging('message', DEBUG_DEVELOPER);`
   - `print_object($variable);`

## Important Notes for Claude Code

1. **Never modify files in the Moodle core repository** - only read them for reference
2. **Always check existing Moodle blocks** for patterns before implementing new features
3. **Test what you can programmatically** - Use available MCP servers to validate syntax and structure
4. **Document what needs manual testing** - Clearly indicate what the user needs to verify
5. **Follow the naming convention strictly** - Moodle is very particular about file and class names
6. **Check language string keys** - they must match expected patterns exactly
7. **Create a TEST_PLAN.md** - Document specific manual test cases for the user to execute

## Resources

- Moodle 5.0 Developer Documentation: https://moodledev.io/
- Block Development: https://moodledev.io/docs/apis/core/block
- Plugin Development: https://moodledev.io/docs/apis/plugintypes
- Moodle Tracker: https://tracker.moodle.org/

## Questions to Ask Before Starting

1. What is the specific purpose of this block?
2. What data should it display?
3. Should it have configuration options?
4. Which page types should it appear on?
5. Does it need database tables?
6. Does it need scheduled tasks?
7. Will it integrate with external services?
