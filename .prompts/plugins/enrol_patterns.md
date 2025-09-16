# Enrolment Plugin Development Patterns and Anti-Patterns

## ⚠️ CRITICAL MOODLE 5.x ENROLMENT PATTERNS & BUG PREVENTION

### 🚫 Plugin Class Implementation Anti-Patterns

**NEVER DO THIS - Wrong Class Structure:**
```php
// ❌ WRONG - Missing namespace, wrong class name, wrong parent class
class my_enrol_plugin extends moodle_plugin {  // Wrong parent class
    // Implementation
}

// OR
namespace enrol;  // Wrong namespace
class yourplugin_plugin extends enrol_plugin {  // Wrong class name pattern
    // Implementation
}
```

**✅ CORRECT - Proper Plugin Class Structure:**
```php
// ✅ CORRECT - No namespace needed for main plugin class, correct naming
// File: lib.php
defined('MOODLE_INTERNAL') || die();

class enrol_yourplugin_plugin extends enrol_plugin {
    // Correct implementation
    public function get_name() {
        // Will correctly return 'yourplugin'
        $words = explode('_', get_class($this));
        return $words[1];
    }
}
```

### 🚫 Enrolment Method Anti-Patterns

**NEVER DO THIS - Bypass Enrolment API:**
```php
// ❌ WRONG - Direct database manipulation bypasses events and validation
public function enrol_user_direct($userid, $courseid, $roleid) {
    global $DB;

    // This bypasses all Moodle enrolment logic - very dangerous!
    $ue = new stdClass();
    $ue->enrolid = $this->get_instance_id($courseid);
    $ue->userid = $userid;
    $ue->status = ENROL_USER_ACTIVE;
    $ue->timestart = 0;
    $ue->timeend = 0;
    $ue->timecreated = time();
    $ue->timemodified = time();

    // Missing events, validation, role assignment!
    $DB->insert_record('user_enrolments', $ue);
}
```

**✅ CORRECT - Use Parent Enrolment API:**
```php
// ✅ CORRECT - Always use parent enrol_user method
public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
    // Always call parent implementation first
    parent::enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, $recovergrades);

    // Add plugin-specific actions AFTER parent call
    $this->send_welcome_message($instance, $userid);
    $this->log_custom_enrolment($instance, $userid);
}

public function unenrol_user(stdClass $instance, $userid) {
    // Plugin-specific actions BEFORE parent call
    $this->send_goodbye_message($instance, $userid);

    // Always call parent implementation
    parent::unenrol_user($instance, $userid);
}
```

### 🚫 Capability Checking Anti-Patterns

**NEVER DO THIS - Missing or Incorrect Capability Checks:**
```php
// ❌ WRONG - No capability checking
public function can_add_instance($courseid) {
    // Anyone can add instances - security risk!
    return true;
}

// ❌ WRONG - Wrong context or capability
public function can_add_instance($courseid) {
    // Using system context instead of course context
    return has_capability('moodle/site:config', context_system::instance());
}

// ❌ WRONG - Plugin-specific capability without checking general capability
public function get_manual_enrol_link($instance) {
    // Only checking plugin capability, not general enrolment capability
    if (has_capability('enrol/yourplugin:enrol', context_course::instance($instance->courseid))) {
        return new moodle_url('/enrol/yourplugin/manage.php', ['id' => $instance->id]);
    }
    return null;
}
```

**✅ CORRECT - Proper Capability Checking:**
```php
// ✅ CORRECT - Check both general and plugin-specific capabilities
public function can_add_instance($courseid) {
    $context = context_course::instance($courseid, MUST_EXIST);

    // Check general capability AND plugin-specific capability
    return has_capability('moodle/course:enrolconfig', $context) &&
           has_capability('enrol/yourplugin:config', $context);
}

public function get_manual_enrol_link($instance) {
    // Check if plugin is enabled
    if (!enrol_is_enabled($this->get_name())) {
        return null;
    }

    $context = context_course::instance($instance->courseid, MUST_EXIST);

    // Check both general and specific capabilities
    if (!has_capability('moodle/course:enrolreviews', $context) ||
        !has_capability('enrol/yourplugin:enrol', $context)) {
        return null;
    }

    return new moodle_url('/enrol/yourplugin/manage.php', ['enrolid' => $instance->id]);
}
```

### 🚫 Self-Enrolment Validation Anti-Patterns

**NEVER DO THIS - Weak Validation Logic:**
```php
// ❌ WRONG - Incomplete validation, no error messages
public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
    // Only checking if enabled - missing many important checks
    return $instance->status == ENROL_INSTANCE_ENABLED;
}

// ❌ WRONG - Boolean return when string messages expected
public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
    if ($instance->status != ENROL_INSTANCE_ENABLED) {
        return false;  // Should return error message string
    }
    return true;
}
```

**✅ CORRECT - Comprehensive Validation:**
```php
// ✅ CORRECT - Complete validation with proper error messages
public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
    global $USER;

    // Check if instance is enabled
    if ($instance->status != ENROL_INSTANCE_ENABLED) {
        return get_string('canntenrol', 'enrol_yourplugin');
    }

    // Check enrolment period
    if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
        return get_string('enrolnotstarted', 'enrol_yourplugin', userdate($instance->enrolstartdate));
    }

    if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
        return get_string('enrolended', 'enrol_yourplugin', userdate($instance->enrolenddate));
    }

    // Check user authentication
    if (isguestuser() || !isloggedin()) {
        return get_string('noguestaccess', 'enrol');
    }

    // Check if user is already enrolled (if requested)
    if ($checkuserenrolment && $this->get_user_enrolment_status($instance) !== false) {
        return get_string('alreadyenrolled', 'enrol_yourplugin');
    }

    // Check plugin-specific requirements
    if (!$this->meets_plugin_requirements($instance, $USER)) {
        return get_string('requirementsnotmet', 'enrol_yourplugin');
    }

    // All checks passed
    return true;
}

protected function get_user_enrolment_status(stdClass $instance) {
    global $DB, $USER;

    return $DB->get_record('user_enrolments', [
        'enrolid' => $instance->id,
        'userid' => $USER->id
    ]);
}
```

### 🚫 Synchronization Anti-Patterns

**NEVER DO THIS - Unsafe Sync Implementation:**
```php
// ❌ WRONG - No error handling, no progress reporting, infinite loops risk
public function sync(progress_trace $trace, $courseid = null) {
    global $DB;

    $instances = $DB->get_records('enrol', ['enrol' => $this->get_name()]);

    // No error handling - if external API fails, sync breaks
    $external_users = $this->call_external_api();

    foreach ($instances as $instance) {
        foreach ($external_users as $user) {
            // No checks for existing enrolments - could create duplicates
            $this->enrol_user($instance, $user->id, $instance->roleid);
        }
    }

    // No return value - caller doesn't know if sync succeeded
}

// ❌ WRONG - Synchronous processing of large datasets
public function sync_large_dataset() {
    $users = $this->get_all_users_from_external_system();  // Could be millions

    // Processing everything in one go - memory exhaustion and timeouts
    foreach ($users as $user) {
        $this->process_user($user);
    }
}
```

**✅ CORRECT - Robust Sync Implementation:**
```php
// ✅ CORRECT - Proper error handling and progress reporting
public function sync(progress_trace $trace, $courseid = null) {
    global $DB;

    $trace->output('Starting synchronization for ' . $this->get_name());

    try {
        // Get instances to sync
        $conditions = ['enrol' => $this->get_name()];
        if ($courseid) {
            $conditions['courseid'] = $courseid;
        }
        $instances = $DB->get_records('enrol', $conditions);

        if (empty($instances)) {
            $trace->output('No instances found to synchronize');
            return 0;
        }

        $errors = 0;
        foreach ($instances as $instance) {
            try {
                $result = $this->sync_instance($trace, $instance);
                if ($result !== 0) {
                    $errors++;
                }
            } catch (Exception $e) {
                $trace->output("Error syncing instance {$instance->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $trace->output("Synchronization completed with $errors errors");
        return $errors > 0 ? 1 : 0;

    } catch (Exception $e) {
        $trace->output('Synchronization failed: ' . $e->getMessage());
        return 2;
    }
}

protected function sync_instance(progress_trace $trace, stdClass $instance) {
    try {
        // Get external data with timeout and error handling
        $external_users = $this->get_external_users_safe($instance);
        $enrolled_users = $this->get_enrolled_users($instance);

        $enrolled_count = 0;
        $unenrolled_count = 0;

        // Process enrolments
        foreach ($external_users as $external_user) {
            if (!isset($enrolled_users[$external_user->id])) {
                $this->enrol_user($instance, $external_user->id, $instance->roleid);
                $enrolled_count++;
            }
        }

        // Process unenrolments based on configuration
        $unenrol_action = $this->get_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);
        if ($unenrol_action != ENROL_EXT_REMOVED_KEEP) {
            foreach ($enrolled_users as $enrolled_user) {
                if (!isset($external_users[$enrolled_user->id])) {
                    if ($unenrol_action == ENROL_EXT_REMOVED_UNENROL) {
                        $this->unenrol_user($instance, $enrolled_user->id);
                    } else if ($unenrol_action == ENROL_EXT_REMOVED_SUSPEND) {
                        $this->update_user_enrol($instance, $enrolled_user->id, ENROL_USER_SUSPENDED);
                    }
                    $unenrolled_count++;
                }
            }
        }

        $trace->output("Instance {$instance->id}: enrolled $enrolled_count, processed $unenrolled_count changes");
        return 0;

    } catch (Exception $e) {
        $trace->output("Error in instance {$instance->id}: " . $e->getMessage());
        return 1;
    }
}

// ✅ CORRECT - Batched processing for large datasets
protected function sync_large_dataset(progress_trace $trace) {
    $batch_size = 100;
    $offset = 0;
    $total_processed = 0;

    do {
        // Process in batches to avoid memory issues
        $users = $this->get_users_batch($offset, $batch_size);

        foreach ($users as $user) {
            try {
                $this->process_user($user);
                $total_processed++;
            } catch (Exception $e) {
                $trace->output("Error processing user {$user->id}: " . $e->getMessage());
            }
        }

        $offset += $batch_size;
        $trace->output("Processed $total_processed users so far...");

        // Prevent memory leaks
        if ($total_processed % 1000 === 0) {
            gc_collect_cycles();
        }

    } while (count($users) === $batch_size);

    $trace->output("Total users processed: $total_processed");
}
```

### 🚫 Instance Management Anti-Patterns

**NEVER DO THIS - Missing Instance Validation:**
```php
// ❌ WRONG - No validation of instance data
public function add_instance($course, array $fields = null) {
    // No validation - could create invalid instances
    return parent::add_instance($course, $fields);
}

// ❌ WRONG - Allowing multiple instances when not supported
public function can_add_instance($courseid) {
    // Not checking for existing instances
    $context = context_course::instance($courseid);
    return has_capability('moodle/course:enrolconfig', $context);
}
```

**✅ CORRECT - Proper Instance Management:**
```php
// ✅ CORRECT - Validate instance data and constraints
public function can_add_instance($courseid) {
    global $DB;

    $context = context_course::instance($courseid, MUST_EXIST);

    // Check capabilities
    if (!has_capability('moodle/course:enrolconfig', $context) ||
        !has_capability('enrol/yourplugin:config', $context)) {
        return false;
    }

    // Check if plugin supports multiple instances
    if (!$this->supports_multiple_instances()) {
        if ($DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => $this->get_name()])) {
            return false;
        }
    }

    // Check course-specific constraints
    return $this->course_supports_plugin($courseid);
}

public function add_instance($course, array $fields = null) {
    // Validate fields before creating instance
    $fields = $this->validate_instance_fields($fields);

    // Set plugin-specific defaults
    if (!isset($fields['roleid'])) {
        $fields['roleid'] = $this->get_config('roleid', 0);
    }

    if (!isset($fields['enrolperiod'])) {
        $fields['enrolperiod'] = $this->get_config('enrolperiod', 0);
    }

    // Call parent implementation
    $instanceid = parent::add_instance($course, $fields);

    // Perform plugin-specific setup
    if ($instanceid) {
        $this->setup_instance_defaults($instanceid);
    }

    return $instanceid;
}

protected function validate_instance_fields(array $fields = null) {
    if ($fields === null) {
        $fields = [];
    }

    // Validate required fields
    if (isset($fields['customfield']) && !$this->validate_custom_field($fields['customfield'])) {
        throw new invalid_parameter_exception('Invalid custom field value');
    }

    // Sanitize input
    if (isset($fields['name'])) {
        $fields['name'] = clean_param($fields['name'], PARAM_TEXT);
    }

    return $fields;
}
```

### 🚫 Settings and Configuration Anti-Patterns

**NEVER DO THIS - Insecure or Invalid Settings:**
```php
// ❌ WRONG - No validation, wrong setting types, security issues
if ($ADMIN->fulltree) {
    // No validation - allows any input
    $settings->add(new admin_setting_configtext(
        'enrol_yourplugin/apikey',
        'API Key',
        'Enter API key',
        ''
    ));

    // Using text field for password - exposed in HTML
    $settings->add(new admin_setting_configtext(
        'enrol_yourplugin/secret',
        'Secret Key',
        'Secret for API',
        ''
    ));

    // Boolean as text instead of checkbox
    $settings->add(new admin_setting_configtext(
        'enrol_yourplugin/enabled',
        'Enable sync',
        'Enable synchronization',
        'no'
    ));
}
```

**✅ CORRECT - Secure and Validated Settings:**
```php
// ✅ CORRECT - Proper validation and security
if ($ADMIN->fulltree) {
    // Secure password field
    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_yourplugin/apikey',
        get_string('apikey', 'enrol_yourplugin'),
        get_string('apikey_desc', 'enrol_yourplugin'),
        ''
    ));

    // Proper checkbox for boolean values
    $settings->add(new admin_setting_configcheckbox(
        'enrol_yourplugin/autosync',
        get_string('autosync', 'enrol_yourplugin'),
        get_string('autosync_desc', 'enrol_yourplugin'),
        1
    ));

    // Validated select with options
    $options = [
        ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPEND => get_string('extremovedsuspend', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
    ];
    $settings->add(new admin_setting_configselect(
        'enrol_yourplugin/unenrolaction',
        get_string('extremovedaction', 'enrol_yourplugin'),
        get_string('extremovedaction_desc', 'enrol_yourplugin'),
        ENROL_EXT_REMOVED_KEEP,
        $options
    ));

    // Duration setting with validation
    $settings->add(new admin_setting_configduration(
        'enrol_yourplugin/enrolperiod',
        get_string('enrolperiod', 'enrol_yourplugin'),
        get_string('enrolperiod_desc', 'enrol_yourplugin'),
        0,
        HOURSECS  // Minimum 1 hour
    ));

    // Role selection with proper validation
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);

        $settings->add(new admin_setting_configselect(
            'enrol_yourplugin/roleid',
            get_string('defaultrole', 'enrol_yourplugin'),
            get_string('defaultrole_desc', 'enrol_yourplugin'),
            $student->id ?? 5,
            $options
        ));
    }
}
```

### 🚫 Database and External API Anti-Patterns

**NEVER DO THIS - Unsafe Database and API Operations:**
```php
// ❌ WRONG - SQL injection vulnerability
protected function get_users_by_email($email) {
    global $DB;

    // Direct string concatenation - SQL injection risk!
    $sql = "SELECT * FROM {user} WHERE email = '" . $email . "'";
    return $DB->get_records_sql($sql);
}

// ❌ WRONG - No error handling for external APIs
protected function get_external_users() {
    $api_url = $this->get_config('api_url');

    // No timeout, no error handling
    $response = file_get_contents($api_url);
    $data = json_decode($response);

    return $data->users;  // Could be null or invalid
}

// ❌ WRONG - Blocking operations during web requests
public function enrol_user_with_external_check(stdClass $instance, $userid) {
    // This could take 30+ seconds and block the web request
    $external_validation = $this->validate_with_external_api($userid);

    if ($external_validation) {
        parent::enrol_user($instance, $userid);
    }
}
```

**✅ CORRECT - Secure Database and API Operations:**
```php
// ✅ CORRECT - Parameterized queries
protected function get_users_by_email($email) {
    global $DB;

    $sql = "SELECT * FROM {user} WHERE email = ? AND deleted = 0";
    return $DB->get_records_sql($sql, [$email]);
}

// ✅ CORRECT - Robust external API calls
protected function get_external_users_safe($instance) {
    try {
        $api_url = $this->get_config('api_url');
        $api_key = $this->get_config('api_key');

        if (empty($api_url) || empty($api_key)) {
            throw new moodle_exception('missingconfig', 'enrol_yourplugin');
        }

        // Use cURL with proper timeout and error handling
        $curl = new curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ]
        ]);

        $response = $curl->get($api_url);
        $http_code = $curl->get_info()['http_code'];

        if ($http_code !== 200) {
            throw new moodle_exception('apierror', 'enrol_yourplugin', '', $http_code);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('invalidjson', 'enrol_yourplugin');
        }

        return $this->validate_external_data($data);

    } catch (Exception $e) {
        debugging('External API call failed: ' . $e->getMessage(), DEBUG_NORMAL);
        return [];  // Return empty array on failure
    }
}

// ✅ CORRECT - Asynchronous processing for slow operations
public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
    // Enrol immediately
    parent::enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, $recovergrades);

    // Queue external validation for background processing
    $this->queue_external_validation($instance, $userid);
}

protected function queue_external_validation($instance, $userid) {
    // Use adhoc task for background processing
    $task = new \enrol_yourplugin\task\validate_user();
    $task->set_custom_data([
        'instanceid' => $instance->id,
        'userid' => $userid
    ]);

    \core\task\manager::queue_adhoc_task($task);
}
```

## 🎯 PROVEN PATTERNS FOR COMMON ENROLMENT TYPES

### Manual Enrolment Pattern
```php
class enrol_yourplugin_plugin extends enrol_plugin {
    public function allow_enrol(stdClass $instance) {
        return true;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function get_manual_enrol_link($instance) {
        if (!enrol_is_enabled($this->get_name())) {
            return null;
        }

        $context = context_course::instance($instance->courseid, MUST_EXIST);
        if (!has_capability('enrol/yourplugin:enrol', $context)) {
            return null;
        }

        return new moodle_url('/enrol/yourplugin/manage.php', [
            'enrolid' => $instance->id,
            'id' => $instance->courseid
        ]);
    }
}
```

### Self-Enrolment Pattern
```php
class enrol_yourplugin_plugin extends enrol_plugin {
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        // Comprehensive validation as shown above
        return $this->validate_self_enrolment($instance, $checkuserenrolment);
    }

    public function get_enrol_link(stdClass $instance) {
        $result = $this->can_self_enrol($instance);
        if ($result !== true) {
            return null;
        }

        return new moodle_url('/enrol/yourplugin/enrol.php', ['id' => $instance->id]);
    }

    public function enrol_page_hook(stdClass $instance) {
        global $OUTPUT;

        $result = $this->can_self_enrol($instance);
        if ($result === true) {
            $form = new \enrol_yourplugin\form\enrol_form(null, $instance);
            return $OUTPUT->render($form);
        } else {
            return $OUTPUT->notification($result, 'error');
        }
    }
}
```

### Synchronization Pattern
```php
class enrol_yourplugin_plugin extends enrol_plugin {
    public function sync(progress_trace $trace, $courseid = null) {
        // Robust sync implementation as shown above
        return $this->perform_sync($trace, $courseid);
    }

    protected function supports_auto_sync() {
        return $this->get_config('autosync', 0);
    }

    public function cron() {
        if ($this->supports_auto_sync()) {
            $trace = new null_progress_trace();
            return $this->sync($trace);
        }
        return 0;
    }
}
```

## 🔒 SECURITY CHECKLIST

- [ ] ✅ All user input is validated and sanitized
- [ ] ✅ Database queries use parameters, not string concatenation
- [ ] ✅ Capabilities are checked at appropriate levels
- [ ] ✅ External API calls have timeouts and error handling
- [ ] ✅ Sensitive configuration uses password fields
- [ ] ✅ File operations use Moodle's file API
- [ ] ✅ Events are triggered for enrolment changes
- [ ] ✅ User data is handled according to privacy API
- [ ] ✅ Cross-site request forgery protection is in place
- [ ] ✅ Access controls prevent unauthorized enrolments

## 🚀 PERFORMANCE CHECKLIST

- [ ] ✅ Use database efficiently (avoid N+1 queries)
- [ ] ✅ External API calls are asynchronous or cached
- [ ] ✅ Large datasets are processed in batches
- [ ] ✅ Memory usage is controlled in long-running operations
- [ ] ✅ Unnecessary database queries are avoided
- [ ] ✅ Indexing is considered for custom database tables
- [ ] ✅ Caching is used where appropriate
- [ ] ✅ Background tasks are used for slow operations

## 🧪 TESTING PATTERNS

### Unit Test Pattern
```php
class enrol_yourplugin_test extends advanced_testcase {
    public function test_can_add_instance() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('yourplugin');

        // Test with proper capabilities
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = context_course::instance($course->id);
        $role = $this->getDataGenerator()->create_role();

        assign_capability('moodle/course:enrolconfig', CAP_ALLOW, $role, $context);
        assign_capability('enrol/yourplugin:config', CAP_ALLOW, $role, $context);
        role_assign($role, $user->id, $context);

        $this->assertTrue($plugin->can_add_instance($course->id));
    }

    public function test_enrol_user() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('yourplugin');

        $instance = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => 5  // Student role
        ]);

        $plugin->enrol_user($instance, $user->id);

        // Verify enrolment
        $this->assertTrue(is_enrolled(context_course::instance($course->id), $user));
    }
}
```

### Integration Test Pattern
```php
public function test_sync_with_external_system() {
    $this->resetAfterTest(true);

    // Mock external API
    $this->mock_external_api_response([
        'users' => [
            ['id' => 123, 'email' => 'user1@example.com'],
            ['id' => 456, 'email' => 'user2@example.com']
        ]
    ]);

    $plugin = enrol_get_plugin('yourplugin');
    $trace = new null_progress_trace();

    $result = $plugin->sync($trace);
    $this->assertEquals(0, $result);
}
```