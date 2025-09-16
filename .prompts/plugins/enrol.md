### Moodle Enrolment Plugin Structure

Every Moodle enrolment plugin MUST follow this structure:
```
enrol_[enrolname]/
├── lib.php                        # Main plugin class (REQUIRED)
├── version.php                    # Version and dependencies (REQUIRED)
├── lang/                          # Language strings (REQUIRED)
│   └── en/
│       └── enrol_[enrolname].php
├── db/                            # Database and capabilities
│   ├── access.php                 # Capabilities definition
│   ├── install.xml                # Database schema
│   ├── upgrade.php                # Database upgrades
│   └── services.php               # Web services (optional)
├── classes/                       # Autoloaded classes (PSR-4)
│   ├── privacy/
│   │   └── provider.php           # Privacy API
│   ├── external/                  # External API classes
│   ├── form/                      # Form classes
│   ├── task/                      # Background tasks
│   └── output/                    # Renderable classes
├── settings.php                   # Admin settings (optional)
├── enrol.php                      # Enrolment UI page (optional)
├── unenrol.php                    # Unenrolment UI page (optional)
├── manage.php                     # Management UI page (optional)
├── externallib.php                # Web services (optional)
├── locallib.php                   # Local helper functions (optional)
├── amd/src/                       # AMD modules (optional)
├── templates/                     # Mustache templates (optional)
├── pix/                           # Icons (optional)
├── tests/                         # Unit tests
└── cli/                           # Command line scripts (optional)
```

### Key Files to Examine in Moodle Core

When developing, examine these reference implementations in the Moodle core:

1. **Base enrolment architecture:**
   - `../moodle/lib/enrollib.php` - Base `enrol_plugin` abstract class
   - `../moodle/enrol/locallib.php` - Enrolment management functions

2. **Simple enrolment patterns:**
   - `../moodle/enrol/manual/` - Manual enrolment by teachers
   - `../moodle/enrol/guest/` - Guest access without enrolment
   - `../moodle/enrol/category/` - Automatic role assignment

3. **Self-enrolment patterns:**
   - `../moodle/enrol/self/` - Self-enrolment with optional password
   - `../moodle/enrol/cohort/` - Automatic cohort synchronization

4. **External integration patterns:**
   - `../moodle/enrol/ldap/` - LDAP synchronization
   - `../moodle/enrol/database/` - External database sync
   - `../moodle/enrol/lti/` - LTI tool provider enrolments

5. **Payment patterns:**
   - `../moodle/enrol/paypal/` - PayPal payment integration
   - `../moodle/enrol/fee/` - Fee-based enrolment

## Development Guidelines

### 1. Main Plugin Class (`lib.php`)

The main class MUST:
- Extend `enrol_plugin` abstract class
- Be named `enrol_[pluginname]_plugin`
- Implement required methods for enrolment functionality

```php
class enrol_yourplugin_plugin extends enrol_plugin {

    // Core functionality methods
    public function get_instance_name($instance) {
        // Return display name for this enrolment instance
        return get_string('pluginname', 'enrol_yourplugin');
    }

    public function roles_protected() {
        // Whether roles assigned by this plugin are protected from changes
        return false;
    }

    public function allow_enrol(stdClass $instance) {
        // Whether this plugin allows enrolment
        return true;
    }

    public function allow_unenrol(stdClass $instance) {
        // Whether this plugin allows unenrolment
        return true;
    }

    public function allow_manage(stdClass $instance) {
        // Whether this plugin allows management of enrolments
        return true;
    }

    // Instance management
    public function can_add_instance($courseid) {
        // Whether a new instance can be added to this course
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('moodle/course:enrolconfig', $context);
    }

    public function can_delete_instance($instance) {
        // Whether this instance can be deleted
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/yourplugin:config', $context);
    }

    public function can_hide_show_instance($instance) {
        // Whether this instance can be enabled/disabled
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/yourplugin:config', $context);
    }

    // Enrolment interface
    public function get_enrol_link(stdClass $instance) {
        // Return URL for enrolment page (for self-enrolment plugins)
        if (!$this->can_self_enrol($instance)) {
            return null;
        }
        return new moodle_url('/enrol/yourplugin/enrol.php', ['id' => $instance->id]);
    }

    public function enrol_page_hook(stdClass $instance) {
        // Add content to course enrolment page
        // Return HTML or renderable object
        return null;
    }

    // User enrolment methods
    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        // Enrol a user - calls parent implementation
        parent::enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, $recovergrades);

        // Add any plugin-specific post-enrolment actions
        $this->notify_user_enrolled($instance, $userid);
    }

    public function unenrol_user(stdClass $instance, $userid) {
        // Unenrol a user - calls parent implementation
        parent::unenrol_user($instance, $userid);

        // Add any plugin-specific post-unenrolment actions
        $this->notify_user_unenrolled($instance, $userid);
    }

    // Synchronization (for external plugins)
    public function sync(progress_trace $trace, $courseid = null) {
        // Synchronize with external system
        // Return 0 for success, error code for failure
        return 0;
    }

    // Capabilities and permissions
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        // Check if current user can self-enrol
        if ($checkuserenrolment) {
            if (isguestuser() or !isloggedin()) {
                return get_string('noguestaccess', 'enrol');
            }
            if ($this->get_user_enrolment_status($instance) !== false) {
                return get_string('canntenrol', 'enrol_yourplugin');
            }
        }
        return true;
    }

    // Configuration and settings
    public function get_newinstance_defaults() {
        // Default values for new instances
        return [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $this->get_config('roleid', 0),
            'enrolperiod' => $this->get_config('enrolperiod', 0),
            'expirynotify' => $this->get_config('expirynotify', 0),
            'notifyall' => $this->get_config('notifyall', 0),
            'expirythreshold' => $this->get_config('expirythreshold', 86400),
        ];
    }

    public function add_instance($course, array $fields = null) {
        // Add new enrolment instance
        return parent::add_instance($course, $fields);
    }

    public function update_instance($instance, $data) {
        // Update existing instance
        return parent::update_instance($instance, $data);
    }
}
```

### 2. Version File (`version.php`)

Must define:
```php
$plugin->component = 'enrol_[pluginname]';
$plugin->version = 2024010100;  // YYYYMMDDXX format
$plugin->requires = 2025040800; // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';

// Optional: Dependencies on other plugins
$plugin->dependencies = [
    'auth_ldap' => 2025040800,  // If depends on LDAP authentication
];
```

### 3. Language Strings (`lang/en/enrol_[pluginname].php`)

Minimum required:
```php
$string['pluginname'] = 'Your Enrolment Plugin';
$string['pluginname_desc'] = 'Description of your enrolment plugin.';

// Capabilities
$string['yourplugin:config'] = 'Configure enrolment instances';
$string['yourplugin:enrol'] = 'Enrol users';
$string['yourplugin:manage'] = 'Manage user enrolments';
$string['yourplugin:unenrol'] = 'Unenrol users';
$string['yourplugin:unenrolself'] = 'Unenrol self from course';

// Common strings
$string['status'] = 'Enable enrolments';
$string['status_desc'] = 'Allow enrolments via this method.';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Role that will be assigned to users enrolled via this plugin.';

// Privacy
$string['privacy:metadata'] = 'The [Plugin Name] enrolment plugin does not store any personal data.';
```

### 4. Capabilities (`db/access.php`)

Define plugin capabilities:
```php
$capabilities = [
    'enrol/yourplugin:config' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ]
    ],

    'enrol/yourplugin:enrol' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ]
    ],

    'enrol/yourplugin:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ]
    ],

    'enrol/yourplugin:unenrol' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ]
    ],

    'enrol/yourplugin:unenrolself' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ]
    ],
];
```

### 5. Settings (Optional - `settings.php`)

If your plugin needs admin configuration:
```php
if ($ADMIN->fulltree) {
    // Enable/disable plugin
    $settings->add(new admin_setting_configcheckbox(
        'enrol_yourplugin/defaultenrol',
        get_string('defaultenrol', 'enrol'),
        get_string('defaultenrol_desc', 'enrol'),
        0
    ));

    // Default role assignment
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_yourplugin/roleid',
            get_string('defaultrole', 'enrol_yourplugin'),
            get_string('defaultrole_desc', 'enrol_yourplugin'),
            $student->id,
            $options
        ));
    }

    // Plugin-specific settings
    $settings->add(new admin_setting_configtext(
        'enrol_yourplugin/apikey',
        get_string('apikey', 'enrol_yourplugin'),
        get_string('apikey_desc', 'enrol_yourplugin'),
        ''
    ));

    $settings->add(new admin_setting_configduration(
        'enrol_yourplugin/enrolperiod',
        get_string('enrolperiod', 'enrol_yourplugin'),
        get_string('enrolperiod_desc', 'enrol_yourplugin'),
        0
    ));
}
```

## Enrolment Plugin Types and Patterns

### 🎯 Manual Enrolment Plugins
- **Purpose**: Teachers manually enrol specific users
- **Examples**: Manual enrolment, invitation-based enrolment
- **Key Features**: Management interface, bulk operations
- **Implementation**: Focus on `allow_enrol()`, `allow_manage()`, management UI

```php
public function get_manual_enrol_link($instance) {
    if (!$this->allow_enrol($instance)) {
        return null;
    }
    $context = context_course::instance($instance->courseid);
    if (!has_capability('enrol/yourplugin:enrol', $context)) {
        return null;
    }
    return new moodle_url('/enrol/yourplugin/manage.php', ['enrolid' => $instance->id]);
}
```

### 🎯 Self-Enrolment Plugins
- **Purpose**: Users enrol themselves in courses
- **Examples**: Self-enrolment with key, payment-based enrolment
- **Key Features**: Public enrolment interface, validation
- **Implementation**: Focus on `can_self_enrol()`, `get_enrol_link()`, enrolment page

```php
public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
    if ($instance->status != ENROL_INSTANCE_ENABLED) {
        return get_string('canntenrol', 'enrol_yourplugin');
    }

    if ($instance->enrolstartdate != 0 and $instance->enrolstartdate > time()) {
        return get_string('enrolnotstarted', 'enrol_yourplugin');
    }

    if ($instance->enrolenddate != 0 and $instance->enrolenddate < time()) {
        return get_string('enrolended', 'enrol_yourplugin');
    }

    if (!$this->validate_user_requirements($instance)) {
        return get_string('requirementsnotmet', 'enrol_yourplugin');
    }

    return true;
}

public function get_enrol_link(stdClass $instance) {
    if (!$this->can_self_enrol($instance)) {
        return null;
    }
    return new moodle_url('/enrol/yourplugin/enrol.php', ['id' => $instance->id]);
}
```

### 🎯 Automatic Enrolment Plugins
- **Purpose**: Automatically enrol users based on external criteria
- **Examples**: Cohort sync, LDAP sync, database sync
- **Key Features**: Background synchronization, external integration
- **Implementation**: Focus on `sync()` method, scheduled tasks

```php
public function sync(progress_trace $trace, $courseid = null) {
    global $DB;

    $trace->output('Starting synchronization...');

    if ($courseid) {
        $instances = $DB->get_records('enrol', ['enrol' => $this->get_name(), 'courseid' => $courseid]);
    } else {
        $instances = $DB->get_records('enrol', ['enrol' => $this->get_name()]);
    }

    foreach ($instances as $instance) {
        $this->sync_instance($trace, $instance);
    }

    $trace->output('Synchronization completed.');
    return 0;
}

protected function sync_instance(progress_trace $trace, stdClass $instance) {
    $external_users = $this->get_external_users($instance);
    $enrolled_users = $this->get_enrolled_users($instance);

    // Enrol new users
    foreach ($external_users as $external_user) {
        if (!isset($enrolled_users[$external_user->id])) {
            $this->enrol_user($instance, $external_user->id, $instance->roleid);
            $trace->output("Enrolled user {$external_user->username}");
        }
    }

    // Unenrol removed users (if configured)
    if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL) {
        foreach ($enrolled_users as $enrolled_user) {
            if (!isset($external_users[$enrolled_user->id])) {
                $this->unenrol_user($instance, $enrolled_user->id);
                $trace->output("Unenrolled user {$enrolled_user->username}");
            }
        }
    }
}
```

### 🎯 Payment/Authentication Plugins
- **Purpose**: Enrol users after payment or external authentication
- **Examples**: PayPal, Stripe, OAuth-based enrolment
- **Key Features**: External API integration, callback handling
- **Implementation**: External callbacks, payment verification

```php
public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
    // Check basic enrolment conditions
    $result = parent::can_self_enrol($instance, $checkuserenrolment);
    if ($result !== true) {
        return $result;
    }

    // Check payment/authentication specific requirements
    if (!$this->user_meets_payment_requirements()) {
        return get_string('paymentrequired', 'enrol_yourplugin');
    }

    return true;
}

public function process_payment_callback($data) {
    // Verify payment authenticity
    if (!$this->verify_payment($data)) {
        throw new moodle_exception('invalidpayment', 'enrol_yourplugin');
    }

    // Get instance and user info
    $instance = $this->get_instance_from_payment($data);
    $userid = $this->get_userid_from_payment($data);

    // Enrol the user
    $this->enrol_user($instance, $userid, $instance->roleid);

    // Send confirmation
    $this->send_payment_confirmation($instance, $userid, $data);
}
```

## Background Tasks and Synchronization

### Scheduled Task Pattern
```php
// File: classes/task/sync_enrolments.php
namespace enrol_yourplugin\task;

class sync_enrolments extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('synctask', 'enrol_yourplugin');
    }

    public function execute() {
        $trace = new \text_progress_trace();
        $plugin = enrol_get_plugin('yourplugin');
        $result = $plugin->sync($trace);

        if ($result !== 0) {
            throw new \moodle_exception('syncfailed', 'enrol_yourplugin');
        }
    }
}

// Register in db/tasks.php
$tasks = [
    [
        'classname' => 'enrol_yourplugin\task\sync_enrolments',
        'blocking' => 0,
        'minute' => '*/15',  // Every 15 minutes
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
];
```

## Testing Checklist

#### Claude Code Responsibilities (Can Verify During Development)

These items can be checked by Claude Code during development:

1. **[AUTOMATED]** ✅ PHP syntax is valid (no parse errors)
   - Claude Code can verify using PHP linter if available

2. **[AUTOMATED]** ✅ Required files exist with correct naming
   - `lib.php`, `version.php`, language files, etc.

3. **[AUTOMATED]** ✅ File structure follows Moodle conventions
   - Correct directory structure and file placement

4. **[AUTOMATED]** ✅ Code follows Moodle coding standards
   - Can check basic standards (indentation, naming)

5. **[AUTOMATED]** ✅ Required methods are implemented
   - Main plugin class extends `enrol_plugin`

6. **[AUTOMATED]** ✅ Language string keys match expected patterns
   - Verify 'pluginname' and capability strings exist

7. **[AUTOMATED]** ✅ Version file has all required fields
   - Component name, version number, requirements

8. **[AUTOMATED]** ✅ GPL license headers are present
   - Check all PHP files have proper headers

9. **[AUTOMATED]** ✅ No obvious security issues
   - Check for raw SQL, unescaped output, capability checks

10. **[AUTOMATED]** ✅ Capability definitions are complete
    - All used capabilities are defined in db/access.php

#### Manual Testing Required

These must be tested by installing in Moodle:

11. **[MANUAL]** ⚠️ Plugin installs without errors
12. **[MANUAL]** ⚠️ Plugin appears in enrolment methods admin page
13. **[MANUAL]** ⚠️ Can add instances to courses
14. **[MANUAL]** ⚠️ Enrolment process works correctly
15. **[MANUAL]** ⚠️ User roles are assigned properly
16. **[MANUAL]** ⚠️ Unenrolment works correctly
17. **[MANUAL]** ⚠️ Settings page works (if applicable)
18. **[MANUAL]** ⚠️ External integration works (if applicable)
19. **[MANUAL]** ⚠️ Synchronization works (if applicable)
20. **[MANUAL]** ⚠️ Events are triggered correctly
21. **[MANUAL]** ⚠️ No PHP errors in logs during operation
22. **[MANUAL]** ⚠️ Performance is acceptable with large user bases
23. **[MANUAL]** ⚠️ Capability restrictions work correctly

## Common Patterns to Reference

### Simple Manual Enrolment
Look at: `../moodle/enrol/manual/lib.php`

### Self-Enrolment with Validation
Look at: `../moodle/enrol/self/lib.php`

### External System Synchronization
Look at: `../moodle/enrol/ldap/lib.php`

### Cohort-based Automatic Enrolment
Look at: `../moodle/enrol/cohort/lib.php`

### Payment Integration
Look at: `../moodle/enrol/paypal/lib.php`

### Guest Access (No Enrolment)
Look at: `../moodle/enrol/guest/lib.php`

## Required Library Includes

**CRITICAL:** Always include required Moodle libraries to prevent "Class not found" errors.

### Common Required Includes

Add these includes when needed:

```php
// For user-related functionality
require_once($CFG->dirroot . '/user/lib.php');

// For group-related functionality
require_once($CFG->libdir . '/grouplib.php');

// For role-related functionality
require_once($CFG->dirroot . '/role/lib.php');

// For course-related functionality
require_once($CFG->dirroot . '/course/lib.php');

// For messaging functionality
require_once($CFG->dirroot . '/message/lib.php');

// For external API integration
require_once($CFG->libdir . '/filelib.php');
```

## Important Notes for Claude Code

1. **Never modify files in the Moodle core repository** - only read them for reference
2. **Always check existing Moodle enrolment plugins** for patterns before implementing
3. **Test what you can programmatically** - Use available tools to validate syntax
4. **Document what needs manual testing** - Clearly indicate what requires Moodle installation
5. **Follow the naming convention strictly** - Class names must match file structure exactly
6. **Check capability definitions** - All used capabilities must be defined
7. **Consider synchronization implications** - External plugins need robust sync methods
8. **Handle errors gracefully** - Users should understand when enrolment fails
9. **Security is critical** - Enrolment plugins have high-privilege operations

## Resources

- Moodle 5.0 Developer Documentation: https://moodledev.io/
- Enrolment API: https://moodledev.io/docs/apis/core/enrol
- Plugin Development: https://moodledev.io/docs/apis/plugintypes
- Enrolment Management: https://moodledev.io/docs/features/enrolment
- Moodle Tracker: https://tracker.moodle.org/

## Questions to Ask Before Starting

1. What type of enrolment method is this (manual, self, automatic, payment)?
2. Who should be able to enrol users (teachers, users themselves, system)?
3. Are there any prerequisites or validation requirements?
4. Does it need to integrate with external systems or APIs?
5. Should it support bulk operations?
6. Does it need custom enrolment periods or restrictions?
7. Are there any payment or authentication requirements?
8. Should it support guest access without enrolment?
9. Does it need scheduled synchronization?
10. What roles should be assigned by default?