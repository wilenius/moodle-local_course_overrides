# Moodle Activity Module Plugin Development Guide

You are an expert Moodle developer specializing in activity module plugins for Moodle 5.x. This guide covers developing custom activities that integrate with Moodle's course structure, grading system, and learning management features.

## Activity Module Plugin Architecture

Activity module plugins extend Moodle's course structure by adding new types of learning activities. They integrate with the gradebook, completion tracking, and provide rich interactions for students and teachers.

### Core Components

Every activity module plugin consists of these essential components:

1. **Module Library** (`lib.php`) - Core API functions and Moodle hooks
2. **Local Library** (`locallib.php`) - Module-specific functions and classes
3. **Configuration Form** (`mod_form.php`) - Activity settings interface
4. **View Script** (`view.php`) - Main display page for the activity
5. **Database Schema** (`db/install.xml`, `db/upgrade.php`) - Data structure definitions
6. **Language Files** (`lang/en/mod_*.php`) - Internationalization strings
7. **Version File** (`version.php`) - Plugin metadata and versioning
8. **Settings** (`settings.php`) - Admin configuration options

### File Structure

```
mod/yourmodule/
├── lib.php                    # Core Moodle hooks and API functions
├── locallib.php               # Module-specific classes and functions
├── mod_form.php               # Activity configuration form
├── view.php                   # Main activity display page
├── index.php                  # Course activity listing page
├── settings.php               # Admin settings page
├── backup/                    # Backup and restore functionality
├── classes/                   # Modern PHP classes and services
│   ├── external/             # Web service API classes
│   ├── event/                # Event classes for logging
│   ├── privacy/              # GDPR privacy provider
│   └── task/                 # Scheduled tasks
├── db/
│   ├── install.xml           # Database schema
│   ├── upgrade.php           # Database upgrades
│   ├── access.php            # Capability definitions
│   ├── services.php          # Web service definitions
│   └── tasks.php             # Scheduled task definitions
├── lang/en/
│   └── mod_yourmodule.php    # Language strings
├── pix/                      # Icons and images
├── templates/                # Mustache templates
├── tests/                    # Unit and integration tests
├── version.php               # Plugin version and dependencies
└── README.md                # Plugin documentation
```

## Core Library Implementation (lib.php)

The lib.php file contains essential Moodle hooks and API functions:

```php
<?php
defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in your module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function yourmodule_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_ASSIGNMENT, // or MOD_ARCHETYPE_RESOURCE
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GRADE_OUTCOMES => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_ADVANCED_GRADING => true,
        FEATURE_PLAGIARISM => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT, // or MOD_PURPOSE_CONTENT
        default => null,
    };
}

/**
 * Add activity instance
 * @param stdClass $data
 * @param mod_yourmodule_mod_form $form
 * @return int The instance id of the new activity
 */
function yourmodule_add_instance(stdClass $data, ?mod_yourmodule_mod_form $form = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // Handle file uploads
    if ($form) {
        $data = file_postupdate_standard_editor($data, 'intro',
            yourmodule_get_editor_options(), $form->get_context(),
            'mod_yourmodule', 'intro', 0);
    }

    $data->id = $DB->insert_record('yourmodule', $data);

    // Handle grading
    if ($data->grade) {
        yourmodule_grade_item_update($data);
    }

    return $data->id;
}

/**
 * Update activity instance
 * @param stdClass $data
 * @param mod_yourmodule_mod_form $form
 * @return bool
 */
function yourmodule_update_instance(stdClass $data, ?mod_yourmodule_mod_form $form = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    // Handle file uploads
    if ($form) {
        $data = file_postupdate_standard_editor($data, 'intro',
            yourmodule_get_editor_options(), $form->get_context(),
            'mod_yourmodule', 'intro', $data->id);
    }

    $result = $DB->update_record('yourmodule', $data);

    // Update grading
    yourmodule_grade_item_update($data);

    return $result;
}

/**
 * Delete activity instance
 * @param int $id
 * @return bool
 */
function yourmodule_delete_instance($id) {
    global $DB;

    if (!$yourmodule = $DB->get_record('yourmodule', ['id' => $id])) {
        return false;
    }

    // Delete grades
    yourmodule_grade_item_delete($yourmodule);

    // Delete files
    $cm = get_coursemodule_from_instance('yourmodule', $id);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_yourmodule');

    // Delete related data
    $DB->delete_records('yourmodule_submissions', ['yourmodule' => $id]);
    $DB->delete_records('yourmodule', ['id' => $id]);

    return true;
}

/**
 * Return user outline for grade report
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $yourmodule
 * @return stdClass|null
 */
function yourmodule_user_outline($course, $user, $mod, $yourmodule) {
    global $DB;

    $submission = $DB->get_record('yourmodule_submissions', [
        'yourmodule' => $yourmodule->id,
        'userid' => $user->id
    ]);

    if ($submission) {
        $result = new stdClass();
        $result->info = get_string('submitted', 'mod_yourmodule');
        $result->time = $submission->timemodified;
        return $result;
    }

    return null;
}

/**
 * Return user complete information for grade report
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $yourmodule
 */
function yourmodule_user_complete($course, $user, $mod, $yourmodule) {
    // Display detailed user activity information
    $outline = yourmodule_user_outline($course, $user, $mod, $yourmodule);
    if ($outline) {
        echo $outline->info . ' (' . userdate($outline->time) . ')';
    } else {
        echo get_string('nosubmission', 'mod_yourmodule');
    }
}

/**
 * Create or update grade item
 * @param stdClass $yourmodule
 * @param mixed $grades
 * @return int
 */
function yourmodule_grade_item_update($yourmodule, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($yourmodule->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => $yourmodule->grade,
        'grademin' => 0,
    ];

    if ($yourmodule->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $yourmodule->grade;
        $item['grademin'] = 0;
    } else if ($yourmodule->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$yourmodule->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/yourmodule', $yourmodule->course, 'mod',
                       'yourmodule', $yourmodule->id, 0, $grades, $item);
}

/**
 * Delete grade item
 * @param stdClass $yourmodule
 * @return int
 */
function yourmodule_grade_item_delete($yourmodule) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/yourmodule', $yourmodule->course, 'mod',
                       'yourmodule', $yourmodule->id, 0, null, ['deleted' => 1]);
}

/**
 * Update grades in the gradebook
 * @param stdClass $yourmodule
 * @param int $userid
 * @param bool $nullifnone
 */
function yourmodule_update_grades($yourmodule, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($yourmodule->grade == 0) {
        yourmodule_grade_item_update($yourmodule);
        return;
    }

    if ($userid) {
        $grades = yourmodule_get_user_grades($yourmodule, $userid);
    } else {
        $grades = yourmodule_get_user_grades($yourmodule);
    }

    if ($grades) {
        yourmodule_grade_item_update($yourmodule, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        yourmodule_grade_item_update($yourmodule, $grade);
    } else {
        yourmodule_grade_item_update($yourmodule);
    }
}

/**
 * Get user grades
 * @param stdClass $yourmodule
 * @param int $userid
 * @return array
 */
function yourmodule_get_user_grades($yourmodule, $userid = 0) {
    global $DB;

    $where = 'yourmodule = :yourmodule';
    $params = ['yourmodule' => $yourmodule->id];

    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $submissions = $DB->get_records_select('yourmodule_submissions', $where, $params);
    $grades = [];

    foreach ($submissions as $submission) {
        if ($submission->grade >= 0) {
            $grades[$submission->userid] = new stdClass();
            $grades[$submission->userid]->userid = $submission->userid;
            $grades[$submission->userid]->rawgrade = $submission->grade;
            $grades[$submission->userid]->dategraded = $submission->timemodified;
        }
    }

    return $grades;
}

/**
 * Reset user data
 * @param stdClass $data
 * @return array
 */
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

/**
 * Get editor options for intro field
 * @return array
 */
function yourmodule_get_editor_options() {
    return [
        'subdirs' => 1,
        'maxbytes' => 0,
        'maxfiles' => -1,
        'changeformat' => 1,
        'context' => null,
        'noclean' => 1,
        'trusttext' => 0
    ];
}
```

## Configuration Form (mod_form.php)

Activity configuration forms extend `moodleform_mod`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_yourmodule_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        // General settings
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');

        $this->standard_intro_elements();

        // Activity-specific settings
        $mform->addElement('header', 'contentsection',
            get_string('content', 'mod_yourmodule'));

        $mform->addElement('editor', 'instructions',
            get_string('instructions', 'mod_yourmodule'),
            ['rows' => 10], $this->get_editor_options());
        $mform->setType('instructions', PARAM_RAW);
        $mform->addHelpButton('instructions', 'instructions', 'mod_yourmodule');

        $mform->addElement('filemanager', 'attachments',
            get_string('attachments', 'mod_yourmodule'),
            null, $this->get_filemanager_options());

        // Availability settings
        $mform->addElement('header', 'availability',
            get_string('availability', 'mod_yourmodule'));

        $mform->addElement('date_time_selector', 'availablefrom',
            get_string('availablefrom', 'mod_yourmodule'),
            ['optional' => true]);
        $mform->addHelpButton('availablefrom', 'availablefrom', 'mod_yourmodule');

        $mform->addElement('date_time_selector', 'availableuntil',
            get_string('availableuntil', 'mod_yourmodule'),
            ['optional' => true]);
        $mform->addHelpButton('availableuntil', 'availableuntil', 'mod_yourmodule');

        // Submission settings
        $mform->addElement('header', 'submissionsettings',
            get_string('submissionsettings', 'mod_yourmodule'));

        $mform->addElement('selectyesno', 'allowresubmission',
            get_string('allowresubmission', 'mod_yourmodule'));
        $mform->setDefault('allowresubmission', 0);
        $mform->addHelpButton('allowresubmission', 'allowresubmission', 'mod_yourmodule');

        $options = [1 => 1, 2 => 2, 3 => 3, 5 => 5, 10 => 10, -1 => get_string('unlimited')];
        $mform->addElement('select', 'maxattempts',
            get_string('maxattempts', 'mod_yourmodule'), $options);
        $mform->setDefault('maxattempts', 1);
        $mform->hideIf('maxattempts', 'allowresubmission', 'eq', 0);

        // Grading settings
        $this->standard_grading_coursemodule_elements();

        // Group settings
        $this->standard_grouping_access();

        // Common module settings
        $this->standard_coursemodule_elements();

        // Action buttons
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate dates
        if (!empty($data['availablefrom']) && !empty($data['availableuntil'])) {
            if ($data['availablefrom'] >= $data['availableuntil']) {
                $errors['availableuntil'] = get_string('availableuntilmustbeafter', 'mod_yourmodule');
            }
        }

        // Validate grade settings
        if (!empty($data['grade'])) {
            if ($data['grade'] < 0 && $data['grade'] != -1) {
                $errors['grade'] = get_string('invalidgrade', 'mod_yourmodule');
            }
        }

        return $errors;
    }

    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Prepare editor content
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
            'changeformat' => 1,
            'context' => $this->context,
            'noclean' => 1,
            'trusttext' => 0
        ];
    }

    private function get_filemanager_options() {
        return [
            'subdirs' => 0,
            'maxbytes' => $this->course->maxbytes,
            'maxfiles' => 10,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        ];
    }
}
```

## View Script (view.php)

The main display page for the activity:

```php
<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/yourmodule/lib.php');
require_once($CFG->dirroot . '/mod/yourmodule/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$y = optional_param('y', 0, PARAM_INT);   // Activity instance ID

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, 'yourmodule');
    $yourmodule = $DB->get_record('yourmodule', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $yourmodule = $DB->get_record('yourmodule', ['id' => $y], '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($yourmodule->id, 'yourmodule');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/yourmodule:view', $context);

// Trigger module viewed event
$event = \mod_yourmodule\event\course_module_viewed::create([
    'objectid' => $yourmodule->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('yourmodule', $yourmodule);
$event->trigger();

// Mark module as viewed for completion
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Set up page
$PAGE->set_url('/mod/yourmodule/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($yourmodule->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Check availability
$yourmodule_instance = new \mod_yourmodule\yourmodule($cm, $course, $yourmodule);
if (!$yourmodule_instance->is_available()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notavailable', 'mod_yourmodule'), 'error');
    echo $OUTPUT->footer();
    die();
}

// Display content
echo $OUTPUT->header();

// Show activity name and description
echo $OUTPUT->heading(format_string($yourmodule->name));

if (trim(strip_tags($yourmodule->intro))) {
    echo $OUTPUT->box_start('mod_introbox', 'yourmoduleintro');
    echo format_module_intro('yourmodule', $yourmodule, $cm->id);
    echo $OUTPUT->box_end();
}

// Check user capability and display appropriate content
if (has_capability('mod/yourmodule:submit', $context)) {
    // Student view - show submission interface
    $renderer = $PAGE->get_renderer('mod_yourmodule');
    echo $renderer->render_student_view($yourmodule_instance);
} else if (has_capability('mod/yourmodule:grade', $context)) {
    // Teacher view - show grading interface
    $renderer = $PAGE->get_renderer('mod_yourmodule');
    echo $renderer->render_teacher_view($yourmodule_instance);
} else {
    // Guest view - show read-only content
    echo $OUTPUT->notification(get_string('guestnosubmit', 'mod_yourmodule'), 'info');
}

echo $OUTPUT->footer();
```

## Local Library (locallib.php)

Module-specific classes and functions:

```php
<?php
defined('MOODLE_INTERNAL') || die();

namespace mod_yourmodule;

/**
 * Main activity class
 */
class yourmodule {
    private $cm;
    private $course;
    private $yourmodule;
    private $context;

    public function __construct($cm, $course, $yourmodule) {
        $this->cm = $cm;
        $this->course = $course;
        $this->yourmodule = $yourmodule;
        $this->context = \context_module::instance($cm->id);
    }

    /**
     * Check if activity is available to current user
     * @return bool
     */
    public function is_available() {
        $now = time();

        if ($this->yourmodule->availablefrom && $now < $this->yourmodule->availablefrom) {
            return false;
        }

        if ($this->yourmodule->availableuntil && $now > $this->yourmodule->availableuntil) {
            return false;
        }

        return true;
    }

    /**
     * Get user submission
     * @param int $userid
     * @return stdClass|false
     */
    public function get_user_submission($userid = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        return $DB->get_record('yourmodule_submissions', [
            'yourmodule' => $this->yourmodule->id,
            'userid' => $userid
        ]);
    }

    /**
     * Save user submission
     * @param stdClass $data
     * @return bool
     */
    public function save_submission($data) {
        global $DB, $USER;

        $submission = $this->get_user_submission($USER->id);

        if ($submission) {
            // Update existing submission
            $submission->content = $data->content;
            $submission->timemodified = time();
            $result = $DB->update_record('yourmodule_submissions', $submission);
        } else {
            // Create new submission
            $submission = new \stdClass();
            $submission->yourmodule = $this->yourmodule->id;
            $submission->userid = $USER->id;
            $submission->content = $data->content;
            $submission->timecreated = time();
            $submission->timemodified = time();
            $result = $DB->insert_record('yourmodule_submissions', $submission);
        }

        if ($result) {
            // Trigger submission event
            $event = \mod_yourmodule\event\submission_created::create([
                'objectid' => $submission->id ?? $result,
                'context' => $this->context,
                'userid' => $USER->id,
            ]);
            $event->trigger();

            // Update completion
            $completion = new \completion_info($this->course);
            $completion->update_state($this->cm, COMPLETION_COMPLETE, $USER->id);
        }

        return $result;
    }

    /**
     * Grade submission
     * @param int $userid
     * @param float $grade
     * @param string $feedback
     * @return bool
     */
    public function grade_submission($userid, $grade, $feedback = '') {
        global $DB;

        $submission = $this->get_user_submission($userid);
        if (!$submission) {
            return false;
        }

        $submission->grade = $grade;
        $submission->feedback = $feedback;
        $submission->timegraded = time();

        $result = $DB->update_record('yourmodule_submissions', $submission);

        if ($result) {
            // Update gradebook
            yourmodule_update_grades($this->yourmodule, $userid);

            // Trigger grading event
            $event = \mod_yourmodule\event\submission_graded::create([
                'objectid' => $submission->id,
                'context' => $this->context,
                'relateduserid' => $userid,
            ]);
            $event->trigger();
        }

        return $result;
    }

    /**
     * Get activity statistics
     * @return stdClass
     */
    public function get_statistics() {
        global $DB;

        $stats = new \stdClass();

        $stats->total_participants = $DB->count_records('user_enrolments', [
            'enrolid' => $DB->get_field('enrol', 'id', ['courseid' => $this->course->id])
        ]);

        $stats->total_submissions = $DB->count_records('yourmodule_submissions', [
            'yourmodule' => $this->yourmodule->id
        ]);

        $stats->graded_submissions = $DB->count_records_select('yourmodule_submissions',
            'yourmodule = ? AND grade IS NOT NULL', [$this->yourmodule->id]);

        $stats->average_grade = $DB->get_field_sql(
            'SELECT AVG(grade) FROM {yourmodule_submissions} WHERE yourmodule = ? AND grade IS NOT NULL',
            [$this->yourmodule->id]
        );

        return $stats;
    }
}
```

## Database Schema

Define your database tables in `db/install.xml`:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/yourmodule/db" VERSION="2024011700">
  <TABLES>
    <TABLE NAME="yourmodule" COMMENT="Main activity table">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="course" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="name" TYPE="char" LENGTH="1333" NOTNULL="true"/>
        <FIELD NAME="intro" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="introformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="instructions" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="instructionsformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="availablefrom" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="availableuntil" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="allowresubmission" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="maxattempts" TYPE="int" LENGTH="6" NOTNULL="true" DEFAULT="1"/>
        <FIELD NAME="grade" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="course" TYPE="foreign" FIELDS="course" REFTABLE="course" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="availablefrom" UNIQUE="false" FIELDS="availablefrom"/>
        <INDEX NAME="availableuntil" UNIQUE="false" FIELDS="availableuntil"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="yourmodule_submissions" COMMENT="Student submissions">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="yourmodule" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="content" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="contentformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="grade" TYPE="number" LENGTH="10" NOTNULL="false" DECIMALS="5"/>
        <FIELD NAME="feedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="feedbackformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="0"/>
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
        <INDEX NAME="unique_submission" UNIQUE="true" FIELDS="yourmodule, userid"/>
        <INDEX NAME="timecreated" UNIQUE="false" FIELDS="timecreated"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

## Capabilities and Permissions

Define capabilities in `db/access.php`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/yourmodule:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    'mod/yourmodule:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/yourmodule:submit' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],

    'mod/yourmodule:grade' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/yourmodule:viewreports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
```

## Event Classes

Create event classes in `classes/event/`:

```php
<?php
namespace mod_yourmodule\event;

defined('MOODLE_INTERNAL') || die();

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

    public function get_url() {
        return new \moodle_url('/mod/yourmodule/view.php', ['id' => $this->contextinstanceid]);
    }
}

class submission_created extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'yourmodule_submissions';
    }

    public static function get_name() {
        return get_string('eventsubmissioncreated', 'mod_yourmodule');
    }

    public function get_description() {
        return "The user with id '$this->userid' created a submission for " .
               "activity with course module id '$this->contextinstanceid'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/yourmodule/view.php', ['id' => $this->contextinstanceid]);
    }
}
```

## Privacy API Implementation

Implement GDPR compliance in `classes/privacy/provider.php`:

```php
<?php
namespace mod_yourmodule\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'yourmodule_submissions',
            [
                'userid' => 'privacy:metadata:yourmodule_submissions:userid',
                'content' => 'privacy:metadata:yourmodule_submissions:content',
                'grade' => 'privacy:metadata:yourmodule_submissions:grade',
                'feedback' => 'privacy:metadata:yourmodule_submissions:feedback',
                'timecreated' => 'privacy:metadata:yourmodule_submissions:timecreated',
                'timemodified' => 'privacy:metadata:yourmodule_submissions:timemodified',
            ],
            'privacy:metadata:yourmodule_submissions'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {yourmodule} y ON y.id = cm.instance
            INNER JOIN {yourmodule_submissions} ys ON ys.yourmodule = y.id
                 WHERE ys.userid = :userid";

        $params = [
            'modname' => 'yourmodule',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        // Implementation for exporting user data
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        // Implementation for deleting all user data in context
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Implementation for deleting specific user data
    }
}
```

## Testing Strategy

Create comprehensive tests in `tests/`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class mod_yourmodule_lib_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_yourmodule_add_instance() {
        $course = $this->getDataGenerator()->create_course();

        $data = new stdClass();
        $data->course = $course->id;
        $data->name = 'Test Activity';
        $data->intro = 'Test description';
        $data->grade = 100;

        $instanceid = yourmodule_add_instance($data);

        $this->assertNotEmpty($instanceid);
        $this->assertIsInt($instanceid);

        // Verify record was created
        $instance = $DB->get_record('yourmodule', ['id' => $instanceid]);
        $this->assertEquals($data->name, $instance->name);
        $this->assertEquals($data->course, $instance->course);
    }

    public function test_yourmodule_supports() {
        $this->assertTrue(yourmodule_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(yourmodule_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(yourmodule_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(yourmodule_supports('unsupported_feature'));
    }

    public function test_submission_workflow() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Create activity
        $activity = $this->getDataGenerator()->create_module('yourmodule', [
            'course' => $course->id,
            'grade' => 100
        ]);

        $cm = get_coursemodule_from_instance('yourmodule', $activity->id);
        $yourmodule_instance = new \mod_yourmodule\yourmodule($cm, $course, $activity);

        $this->setUser($user);

        // Test submission
        $data = new stdClass();
        $data->content = 'Test submission content';

        $result = $yourmodule_instance->save_submission($data);
        $this->assertTrue($result);

        // Verify submission was saved
        $submission = $yourmodule_instance->get_user_submission($user->id);
        $this->assertNotEmpty($submission);
        $this->assertEquals($data->content, $submission->content);
    }
}
```

## Best Practices

1. **Follow Moodle Coding Standards**: Use proper indentation, naming conventions
2. **Implement All Required Functions**: Ensure lib.php contains all necessary hooks
3. **Handle Permissions Properly**: Check capabilities before allowing actions
4. **Support Backup/Restore**: Implement backup and restore functionality
5. **Add Comprehensive Logging**: Use events to log all significant actions
6. **Implement Privacy API**: Ensure GDPR compliance with proper data handling
7. **Support Mobile App**: Consider mobile compatibility in design
8. **Test Thoroughly**: Write unit tests for all functionality
9. **Document Everything**: Provide clear documentation for users and developers
10. **Consider Accessibility**: Ensure your module is accessible to all users

Activity modules are complex components that integrate deeply with Moodle's course structure. Take time to understand existing modules like Assignment and Page before building your own custom activities.