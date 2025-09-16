# Moodle Activity Module Plugin Patterns and Anti-Patterns

This document provides patterns, anti-patterns, and best practices specifically for developing Moodle activity module plugins based on analysis of core modules like Assignment and Page.

## Good Patterns

### 1. Proper Module Features Declaration

**Pattern**: Declare all supported features accurately in the `*_supports()` function.

```php
function yourmodule_supports($feature) {
    return match ($feature) {
        // ✅ Core archetype - be specific about your module type
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_ASSIGNMENT, // vs MOD_ARCHETYPE_RESOURCE

        // ✅ Standard features every module should consider
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,

        // ✅ Grading features - only if you support grading
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GRADE_OUTCOMES => true,
        FEATURE_ADVANCED_GRADING => true,

        // ✅ Group features - if you support groups
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,

        // ✅ Other specialized features as needed
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_PLAGIARISM => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT,

        default => null,
    };
}
```

### 2. Robust Instance Management

**Pattern**: Handle instance creation, updates, and deletion properly with error handling.

```php
function yourmodule_add_instance(stdClass $data, ?mod_yourmodule_mod_form $form = null) {
    global $DB;

    // ✅ Set timestamps
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // ✅ Handle file uploads with form context
    if ($form) {
        $data = file_postupdate_standard_editor($data, 'intro',
            yourmodule_get_editor_options(), $form->get_context(),
            'mod_yourmodule', 'intro', 0);

        $data = file_postupdate_standard_filemanager($data, 'attachments',
            yourmodule_get_filemanager_options(), $form->get_context(),
            'mod_yourmodule', 'attachments', 0);
    }

    // ✅ Use transactions for data integrity
    $transaction = $DB->start_delegated_transaction();
    try {
        $data->id = $DB->insert_record('yourmodule', $data);

        // ✅ Handle grading setup
        if (!empty($data->grade)) {
            yourmodule_grade_item_update($data);
        }

        // ✅ Process any additional data
        yourmodule_process_options($data);

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        throw $e;
    }

    return $data->id;
}

function yourmodule_delete_instance($id) {
    global $DB;

    if (!$yourmodule = $DB->get_record('yourmodule', ['id' => $id])) {
        return false;
    }

    // ✅ Use transactions for cascading deletes
    $transaction = $DB->start_delegated_transaction();
    try {
        // Delete in correct order (children first)
        $DB->delete_records('yourmodule_submissions', ['yourmodule' => $id]);
        $DB->delete_records('yourmodule_grades', ['yourmodule' => $id]);

        // Delete grade item
        yourmodule_grade_item_delete($yourmodule);

        // Delete files
        $cm = get_coursemodule_from_instance('yourmodule', $id);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_yourmodule');
        }

        // Finally delete the main record
        $DB->delete_records('yourmodule', ['id' => $id]);

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        return false;
    }

    return true;
}
```

### 3. Comprehensive Form Implementation

**Pattern**: Build forms with proper validation, file handling, and user experience.

```php
class mod_yourmodule_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE;
        $mform = $this->_form;

        // ✅ Standard sections in logical order
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // ✅ Required name field with proper validation
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');

        // ✅ Standard intro elements
        $this->standard_intro_elements();

        // ✅ Module-specific settings with help buttons
        $mform->addElement('header', 'contentsection', get_string('content', 'mod_yourmodule'));

        $mform->addElement('editor', 'instructions',
            get_string('instructions', 'mod_yourmodule'),
            ['rows' => 10], $this->get_editor_options());
        $mform->setType('instructions', PARAM_RAW);
        $mform->addHelpButton('instructions', 'instructions', 'mod_yourmodule');

        // ✅ Conditional fields with proper hiding
        $mform->addElement('selectyesno', 'allowresubmission',
            get_string('allowresubmission', 'mod_yourmodule'));
        $mform->setDefault('allowresubmission', 0);

        $options = [1 => 1, 2 => 2, 3 => 3, 5 => 5, 10 => 10, -1 => get_string('unlimited')];
        $mform->addElement('select', 'maxattempts',
            get_string('maxattempts', 'mod_yourmodule'), $options);
        $mform->setDefault('maxattempts', 1);
        $mform->hideIf('maxattempts', 'allowresubmission', 'eq', 0);

        // ✅ Standard elements in correct order
        $this->standard_grading_coursemodule_elements();
        $this->standard_grouping_access();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // ✅ Business logic validation
        if (!empty($data['availablefrom']) && !empty($data['availableuntil'])) {
            if ($data['availablefrom'] >= $data['availableuntil']) {
                $errors['availableuntil'] = get_string('availableuntilmustbeafter', 'mod_yourmodule');
            }
        }

        // ✅ Cross-field validation
        if ($data['allowresubmission'] && $data['maxattempts'] == 1) {
            $errors['maxattempts'] = get_string('maxattemptswithresubmission', 'mod_yourmodule');
        }

        return $errors;
    }

    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // ✅ Prepare file areas properly
        if ($this->current->instance) {
            $default_values = file_prepare_standard_editor($default_values, 'instructions',
                $this->get_editor_options(), $this->context,
                'mod_yourmodule', 'instructions', $this->current->instance);
        }
    }

    private function get_editor_options() {
        return [
            'subdirs' => 1,
            'maxbytes' => $this->course->maxbytes,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'changeformat' => 1,
            'context' => $this->context,
            'noclean' => 1,
            'trusttext' => 0
        ];
    }
}
```

### 4. Secure View Script Implementation

**Pattern**: Implement view.php with proper security checks and capability handling.

```php
<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/yourmodule/lib.php');
require_once($CFG->dirroot . '/mod/yourmodule/locallib.php');

// ✅ Handle both ID parameters properly
$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$y = optional_param('y', 0, PARAM_INT);   // Activity instance ID

// ✅ Flexible parameter handling
if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, 'yourmodule');
    $yourmodule = $DB->get_record('yourmodule', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($y) {
    $yourmodule = $DB->get_record('yourmodule', ['id' => $y], '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($yourmodule->id, 'yourmodule');
} else {
    throw new moodle_exception('missingparameter');
}

// ✅ Security checks in correct order
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/yourmodule:view', $context);

// ✅ Proper event logging
$event = \mod_yourmodule\event\course_module_viewed::create([
    'objectid' => $yourmodule->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('yourmodule', $yourmodule);
$event->trigger();

// ✅ Completion tracking
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ✅ Page setup
$PAGE->set_url('/mod/yourmodule/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($yourmodule->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// ✅ Availability checking
$yourmodule_instance = new \mod_yourmodule\yourmodule($cm, $course, $yourmodule);
if (!$yourmodule_instance->is_available()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notavailable', 'mod_yourmodule'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// ✅ Capability-based content rendering
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($yourmodule->name));

// ✅ Show intro with proper formatting
if (trim(strip_tags($yourmodule->intro))) {
    echo $OUTPUT->box_start('mod_introbox', 'yourmoduleintro');
    echo format_module_intro('yourmodule', $yourmodule, $cm->id);
    echo $OUTPUT->box_end();
}

// ✅ Role-based interface
if (has_capability('mod/yourmodule:submit', $context)) {
    $renderer = $PAGE->get_renderer('mod_yourmodule');
    echo $renderer->render_student_view($yourmodule_instance);
} else if (has_capability('mod/yourmodule:grade', $context)) {
    $renderer = $PAGE->get_renderer('mod_yourmodule');
    echo $renderer->render_teacher_view($yourmodule_instance);
} else {
    echo $OUTPUT->notification(get_string('nopermissions', 'error'), 'error');
}

echo $OUTPUT->footer();
```

### 5. Comprehensive Database Schema Design

**Pattern**: Design efficient and maintainable database schemas with proper relationships.

```xml
<!-- ✅ Well-structured install.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/yourmodule/db" VERSION="2024011700">
  <TABLES>
    <!-- ✅ Main activity table with all necessary fields -->
    <TABLE NAME="yourmodule" COMMENT="Main activity instances">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="course" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="name" TYPE="char" LENGTH="1333" NOTNULL="true"/>
        <!-- ✅ Standard intro fields -->
        <FIELD NAME="intro" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="introformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Activity-specific fields -->
        <FIELD NAME="instructions" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="instructionsformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Availability and grading fields -->
        <FIELD NAME="availablefrom" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="availableuntil" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="grade" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Standard timestamp fields -->
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="course" TYPE="foreign" FIELDS="course" REFTABLE="course" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <!-- ✅ Indexes for common queries -->
        <INDEX NAME="availablefrom" UNIQUE="false" FIELDS="availablefrom"/>
        <INDEX NAME="availableuntil" UNIQUE="false" FIELDS="availableuntil"/>
      </INDEXES>
    </TABLE>

    <!-- ✅ Submission table with proper constraints -->
    <TABLE NAME="yourmodule_submissions" COMMENT="Student submissions">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="yourmodule" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="content" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="contentformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Grading fields with proper precision -->
        <FIELD NAME="grade" TYPE="number" LENGTH="10" NOTNULL="false" DECIMALS="5"/>
        <FIELD NAME="feedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="feedbackformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Comprehensive timestamp tracking -->
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timegraded" TYPE="int" LENGTH="10" NOTNULL="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="yourmodule" TYPE="foreign" FIELDS="yourmodule" REFTABLE="yourmodule" REFFIELDS="id"/>
        <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <!-- ✅ Unique constraint to prevent duplicate submissions -->
        <INDEX NAME="unique_submission" UNIQUE="true" FIELDS="yourmodule, userid"/>
        <INDEX NAME="timecreated" UNIQUE="false" FIELDS="timecreated"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

### 6. Proper Event Logging

**Pattern**: Implement comprehensive event logging for all significant actions.

```php
namespace mod_yourmodule\event;

class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'yourmodule';
    }

    public static function get_name() {
        return get_string('eventcoursemoduleviewed', 'mod_yourmodule');
    }

    public function get_description() {
        return "The user with id '$this->userid' viewed the activity with " .
               "course module id '$this->contextinstanceid'.";
    }

    // ✅ Provide proper URL for navigation
    public function get_url() {
        return new \moodle_url('/mod/yourmodule/view.php', ['id' => $this->contextinstanceid]);
    }

    // ✅ Map legacy log data if needed
    public static function get_legacy_logdata() {
        return ['course', 'yourmodule', 'view', 'view.php?id=' . $this->contextinstanceid,
                $this->objectid, $this->contextinstanceid];
    }
}

class submission_created extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'yourmodule_submissions';
    }

    // ✅ Proper validation of event data
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    public static function get_name() {
        return get_string('eventsubmissioncreated', 'mod_yourmodule');
    }

    public function get_description() {
        return "The user with id '$this->userid' created a submission for " .
               "activity with course module id '$this->contextinstanceid'.";
    }
}
```

## Anti-Patterns

### 1. ❌ Inadequate Security Checks

**Anti-Pattern**: Missing or insufficient security validation.

```php
// ❌ BAD: No capability checks
function yourmodule_submit_assignment($data) {
    global $DB, $USER;
    // Direct database operation without permission check!
    return $DB->insert_record('yourmodule_submissions', $data);
}

// ✅ GOOD: Proper security implementation
function yourmodule_submit_assignment($cmid, $data) {
    global $DB, $USER;

    $cm = get_coursemodule_from_id('yourmodule', $cmid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    require_capability('mod/yourmodule:submit', $context);
    require_sesskey(); // CSRF protection

    // Additional business logic checks
    $yourmodule = $DB->get_record('yourmodule', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!yourmodule_is_available($yourmodule)) {
        throw new moodle_exception('notavailable', 'mod_yourmodule');
    }

    return $DB->insert_record('yourmodule_submissions', $data);
}
```

### 2. ❌ Poor Grade Integration

**Anti-Pattern**: Inconsistent or broken gradebook integration.

```php
// ❌ BAD: No grade item management
function yourmodule_add_instance($data) {
    global $DB;
    return $DB->insert_record('yourmodule', $data); // Missing grade setup!
}

function yourmodule_update_instance($data) {
    global $DB;
    return $DB->update_record('yourmodule', $data); // No grade updates!
}

// ✅ GOOD: Proper grade integration
function yourmodule_add_instance($data) {
    global $DB;

    $data->id = $DB->insert_record('yourmodule', $data);

    // Create grade item
    if ($data->grade) {
        yourmodule_grade_item_update($data);
    }

    return $data->id;
}

function yourmodule_update_instance($data) {
    global $DB;

    $result = $DB->update_record('yourmodule', $data);

    // Update grade item
    yourmodule_grade_item_update($data);

    return $result;
}

function yourmodule_grade_item_update($yourmodule, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($yourmodule->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => $yourmodule->grade,
        'grademin' => 0,
    ];

    return grade_update('mod/yourmodule', $yourmodule->course, 'mod',
                       'yourmodule', $yourmodule->id, 0, $grades, $item);
}
```

### 3. ❌ Incomplete File Handling

**Anti-Pattern**: Not properly managing file uploads and contexts.

```php
// ❌ BAD: No file handling in forms
class mod_yourmodule_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('editor', 'instructions', 'Instructions');
        // Missing file area setup!
    }

    public function data_preprocessing(&$default_values) {
        // No file preparation!
    }
}

// ✅ GOOD: Comprehensive file handling
class mod_yourmodule_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('editor', 'instructions', 'Instructions',
            ['rows' => 10], $this->get_editor_options());

        $mform->addElement('filemanager', 'attachments', 'Attachments',
            null, $this->get_filemanager_options());
    }

    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        if ($this->current->instance) {
            $default_values = file_prepare_standard_editor($default_values, 'instructions',
                $this->get_editor_options(), $this->context,
                'mod_yourmodule', 'instructions', $this->current->instance);

            $default_values = file_prepare_standard_filemanager($default_values, 'attachments',
                $this->get_filemanager_options(), $this->context,
                'mod_yourmodule', 'attachments', $this->current->instance);
        }
    }

    private function get_editor_options() {
        return [
            'subdirs' => 1,
            'maxbytes' => $this->course->maxbytes,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $this->context,
        ];
    }
}
```

### 4. ❌ Missing Completion Integration

**Anti-Pattern**: Not supporting activity completion properly.

```php
// ❌ BAD: No completion support
function yourmodule_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        // Missing completion features!
        default => null,
    };
}

// ✅ GOOD: Proper completion integration
function yourmodule_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        default => null,
    };
}

// ✅ Implement completion checking
function yourmodule_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $yourmodule = $DB->get_record('yourmodule', ['id' => $cm->instance], '*', MUST_EXIST);

    // Check if completion requires submission
    if ($yourmodule->completionsubmit) {
        $submission = $DB->get_record('yourmodule_submissions', [
            'yourmodule' => $yourmodule->id,
            'userid' => $userid
        ]);

        return !empty($submission);
    }

    return $type; // Manual completion
}
```

### 5. ❌ Poor Database Design

**Anti-Pattern**: Inefficient database schema and queries.

```xml
<!-- ❌ BAD: Poor schema design -->
<TABLE NAME="yourmodule_submissions">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="yourmodule" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="content" TYPE="text"/>
    <!-- Missing format fields, timestamps, indexes -->
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <!-- Missing foreign keys! -->
  </KEYS>
  <!-- No indexes for common queries! -->
</TABLE>
```

```php
// ❌ BAD: N+1 query problem
function yourmodule_get_all_submissions($yourmoduleid) {
    global $DB;

    $submissions = $DB->get_records('yourmodule_submissions', ['yourmodule' => $yourmoduleid]);
    foreach ($submissions as $submission) {
        $user = $DB->get_record('user', ['id' => $submission->userid]); // N+1!
        $submission->username = fullname($user);
    }
    return $submissions;
}

// ✅ GOOD: Efficient single query
function yourmodule_get_all_submissions($yourmoduleid) {
    global $DB;

    $sql = "SELECT s.*, u.firstname, u.lastname, u.email
            FROM {yourmodule_submissions} s
            JOIN {user} u ON u.id = s.userid
            WHERE s.yourmodule = ?
            ORDER BY u.lastname, u.firstname";

    return $DB->get_records_sql($sql, [$yourmoduleid]);
}
```

### 6. ❌ Inadequate Privacy Implementation

**Anti-Pattern**: Missing or incomplete GDPR compliance.

```php
// ❌ BAD: No privacy provider
// Missing classes/privacy/provider.php entirely!

// ✅ GOOD: Complete privacy implementation
namespace mod_yourmodule\privacy;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'yourmodule_submissions',
            [
                'userid' => 'privacy:metadata:submissions:userid',
                'content' => 'privacy:metadata:submissions:content',
                'grade' => 'privacy:metadata:submissions:grade',
                'timecreated' => 'privacy:metadata:submissions:timecreated',
            ],
            'privacy:metadata:submissions'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {yourmodule} y ON y.id = cm.instance
            INNER JOIN {yourmodule_submissions} ys ON ys.yourmodule = y.id
                 WHERE c.contextlevel = :contextlevel AND ys.userid = :userid";

        $params = [
            'modname' => 'yourmodule',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    // Implement other required methods...
}
```

### 7. ❌ Missing Event Logging

**Anti-Pattern**: Not logging significant user actions.

```php
// ❌ BAD: No event logging
function yourmodule_submit_assignment($data) {
    global $DB, $USER;

    $result = $DB->insert_record('yourmodule_submissions', $data);
    // No event logged!
    return $result;
}

// ✅ GOOD: Comprehensive event logging
function yourmodule_submit_assignment($cmid, $data) {
    global $DB, $USER;

    $cm = get_coursemodule_from_id('yourmodule', $cmid);
    $context = context_module::instance($cm->id);

    $result = $DB->insert_record('yourmodule_submissions', $data);

    // Log the event
    $event = \mod_yourmodule\event\submission_created::create([
        'objectid' => $result,
        'context' => $context,
        'userid' => $USER->id,
        'other' => [
            'yourmoduleid' => $data->yourmodule,
        ]
    ]);
    $event->trigger();

    return $result;
}
```

## Common Gotchas

### 1. File Context Issues

```php
// ❌ Common mistake: Wrong file context handling
function yourmodule_add_instance($data, $form = null) {
    global $DB;

    $data->id = $DB->insert_record('yourmodule', $data);

    // Wrong: Using module context before it exists
    $context = context_module::instance($data->coursemodule);
    $data = file_postupdate_standard_editor($data, 'intro',
        $options, $context, 'mod_yourmodule', 'intro', $data->id);
}

// ✅ Correct: Get context from form
function yourmodule_add_instance($data, $form = null) {
    global $DB;

    $data->id = $DB->insert_record('yourmodule', $data);

    if ($form) {
        $context = $form->get_context(); // Correct context
        $data = file_postupdate_standard_editor($data, 'intro',
            $options, $context, 'mod_yourmodule', 'intro', $data->id);
    }
}
```

### 2. Grade Calculation Errors

```php
// ❌ Common mistake: Incorrect grade scaling
function yourmodule_calculate_grade($submission, $maxgrade) {
    $score = $submission->correct_answers / $submission->total_questions;
    return $score * $maxgrade; // May exceed max grade!
}

// ✅ Correct: Proper grade clamping
function yourmodule_calculate_grade($submission, $maxgrade) {
    $score = $submission->correct_answers / $submission->total_questions;
    $grade = $score * $maxgrade;

    // Ensure grade is within bounds
    return max(0, min($grade, $maxgrade));
}
```

### 3. Capability Context Confusion

```php
// ❌ Common mistake: Wrong context for capability check
function yourmodule_can_grade($courseid) {
    $context = context_course::instance($courseid);
    return has_capability('mod/yourmodule:grade', $context); // Wrong context!
}

// ✅ Correct: Use module context
function yourmodule_can_grade($cmid) {
    $context = context_module::instance($cmid);
    return has_capability('mod/yourmodule:grade', $context);
}
```

### 4. Incomplete Reset Implementation

```php
// ❌ Missing reset functionality leads to data retention issues
function yourmodule_reset_userdata($data) {
    return []; // No implementation!
}

// ✅ Proper reset implementation
function yourmodule_reset_userdata($data) {
    global $DB;

    $status = [];

    if (!empty($data->reset_yourmodule_submissions)) {
        $DB->delete_records_select('yourmodule_submissions',
            'yourmodule IN (SELECT id FROM {yourmodule} WHERE course = ?)',
            [$data->courseid]);

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_yourmodule'),
            'item' => get_string('deleteallsubmissions', 'mod_yourmodule'),
            'error' => false
        ];
    }

    return $status;
}
```

## Security Checklist

- [ ] All user input is validated and sanitized
- [ ] Capability checks are performed before all actions
- [ ] CSRF tokens (sesskey) are verified for state-changing operations
- [ ] File uploads are properly validated and stored
- [ ] SQL queries use parameters (no concatenation)
- [ ] Context levels are correct for capability checks
- [ ] Guest access is properly handled
- [ ] Cross-site scripting (XSS) prevention is in place

## Performance Checklist

- [ ] Database queries are optimized (avoid N+1 problems)
- [ ] Proper indexes are defined for common queries
- [ ] File operations are minimized and efficient
- [ ] Large datasets are paginated
- [ ] Caching is used where appropriate
- [ ] Bulk operations are used instead of loops

## Accessibility Checklist

- [ ] All form elements have proper labels
- [ ] Content structure is semantic and logical
- [ ] Color is not the only means of conveying information
- [ ] Focus indicators are visible and logical
- [ ] Content is navigable with keyboard only
- [ ] Screen reader compatibility is maintained

## Testing Checklist

- [ ] Unit tests cover all lib.php functions
- [ ] Form validation is thoroughly tested
- [ ] Capability restrictions are verified
- [ ] File upload/download scenarios are tested
- [ ] Backup/restore functionality works correctly
- [ ] Privacy provider returns correct data
- [ ] Event logging captures all actions
- [ ] Cross-browser compatibility is verified

Following these patterns will help you create robust, secure, and well-integrated activity modules that provide excellent user experiences and maintain compatibility with Moodle's evolving architecture.