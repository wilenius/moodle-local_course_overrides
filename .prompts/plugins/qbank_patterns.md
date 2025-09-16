# qbank development, patterns and anti-patterns

## ⚠️ CRITICAL MOODLE 5.x PATTERNS & BUG PREVENTION

### 🚫 Context Access Anti-Patterns

**NEVER DO THIS in plugin_feature.php:**
```php
// ❌ WRONG - get_question_bank() doesn't exist in plugin_features_base
$context = $this->get_question_bank()->get_most_specific_context();

// ❌ WRONG - Circular dependency during initialization
$contextparams = $this->get_question_bank()->get_pagevars('context');
```

**✅ CORRECT Pattern for Context Access:**
```php
// In plugin_feature.php - Use global $PAGE
public function get_bulk_actions(view $qbank): array {
    global $PAGE, $USER;

    // ✅ CORRECT - Access context via $PAGE
    if (!has_capability('moodle/question:editall', $PAGE->context)) {
        return [];
    }

    // ✅ CORRECT - Use $PAGE->context->id for API calls
    $config = \some_api::get_config($USER, $PAGE->context->id, null, ['purpose']);

    return [new your_bulk_action($qbank)];
}
```

### 🚫 Bulk Action vs Navigation Anti-Patterns

**NEVER DO THIS - Wrong Integration Type:**
```php
// ❌ WRONG - Don't use navigation for bulk operations on multiple questions
public function get_navigation_node(): ?navigation_node_base {
    // This creates a separate tab - wrong for bulk operations!
    return new navigation();
}
```

**✅ CORRECT - Bulk Actions for Multi-Question Operations:**
```php
// ✅ CORRECT - Use bulk actions for operations on selected questions
public function get_bulk_actions(view $qbank): array {
    // This integrates with "With selected" dropdown - correct!
    return [new your_bulk_action($qbank)];
}
```

### 🚫 Moodle 5.x Database Anti-Patterns

**NEVER DO THIS - Old Database Structure:**
```php
// ❌ WRONG - These fields/joins don't exist in Moodle 5.x
FROM {question} q
JOIN {question_categories} qc ON q.category = qc.id  // q.category doesn't exist
JOIN {question_bank_entries} qbe ON q.id = qbe.questionid  // qbe.questionid doesn't exist
```

**✅ CORRECT - Moodle 5.x Shared Question Banks Structure:**
```php
// ✅ CORRECT - Modern Moodle 5.x database structure
FROM {question} q
JOIN {question_versions} qv ON qv.questionid = q.id
JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
WHERE qv.status <> 'hidden'
AND qv.version = (SELECT MAX(v.version)
                  FROM {question_versions} v
                  WHERE v.questionbankentryid = qbe.id)
```

### 🚫 Question Creation Anti-Patterns (NEW - Critical for Moodle 5.x)

**NEVER DO THIS - Incomplete Question Creation:**
```php
// ❌ WRONG - Only inserting into question table
$question->id = $DB->insert_record('question', $question);
// Missing question_bank_entries and question_versions!
```

**✅ CORRECT - Complete Moodle 5.x Question Creation:**
```php
// ✅ CORRECT - Create all required database entries
$question->id = $DB->insert_record('question', $question);

// Create question bank entry
$questionbankentry = new \stdClass();
$questionbankentry->questioncategoryid = $question->category;
$questionbankentry->idnumber = $idnumber; // Must be unique or null
$questionbankentry->ownerid = $question->createdby;
$questionbankentry->id = $DB->insert_record('question_bank_entries', $questionbankentry);

// Create question version
$questionversion = new \stdClass();
$questionversion->questionbankentryid = $questionbankentry->id;
$questionversion->questionid = $question->id;
$questionversion->version = 1;
$questionversion->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
$questionversion->id = $DB->insert_record('question_versions', $questionversion);
```

### 🚫 Question Bank URL Anti-Patterns (NEW - Moodle 5.x Breaking Change)

**NEVER DO THIS - Old URL Structure:**
```php
// ❌ WRONG - This URL no longer exists in Moodle 5.x
$returnurl = new \moodle_url('/question/bank/view.php', ['cmid' => $cmid]);
```

**✅ CORRECT - Moodle 5.x Question Bank URLs:**
```php
// ✅ CORRECT - New question bank URL structure
$returnurl = new \moodle_url('/question/edit.php', ['cmid' => $cmid]);

// For course question banks listing
$banksurl = new \moodle_url('/question/banks.php', ['courseid' => $courseid]);
```

### 🚫 Database Constraint Violation Anti-Patterns (NEW - PostgreSQL Issues)

**NEVER DO THIS - Duplicate idnumber Constraint Violations:**
```php
// ❌ WRONG - Multiple null/empty idnumbers violate unique constraint
$questionbankentry->idnumber = null; // Multiple nulls can cause constraint violation
$questionbankentry->idnumber = ''; // Multiple empty strings definitely violate constraint
```

**✅ CORRECT - Unique idnumber Generation:**
```php
// ✅ CORRECT - Generate unique idnumbers for created questions
$idnumber = $questiondata->idnumber ?? null;
if ($idnumber === null || (string)$idnumber === '') {
    // Generate unique idnumber
    $idnumber = 'ai_bulk_edit_' . $question->id . '_' . time();

    // Ensure uniqueness in category
    $counter = 1;
    $originalidnumber = $idnumber;
    while ($DB->record_exists('question_bank_entries',
            ['idnumber' => $idnumber, 'questioncategoryid' => $question->category])) {
        $idnumber = $originalidnumber . '_' . $counter;
        $counter++;
    }
}
$questionbankentry->idnumber = $idnumber;
```

### 🚫 GIFT Format Parser Anti-Patterns (NEW - Data Structure Issues)

**NEVER DO THIS - Wrong Answer Data Access:**
```php
// ❌ WRONG - Treating parser arrays as strings
$answer->answer = $answerdata; // $answerdata is ['text' => '...', 'format' => ...]
$answer->feedback = $feedbackdata; // Same issue - array treated as string
```

**✅ CORRECT - Handle GIFT Parser Array Format:**
```php
// ✅ CORRECT - Extract text from GIFT parser format
if (is_array($answerdata)) {
    $answer->answer = $answerdata['text'] ?? '';
    $answer->answerformat = $answerdata['format'] ?? FORMAT_HTML;
} else {
    $answer->answer = $answerdata;
    $answer->answerformat = FORMAT_HTML;
}

// Same for feedback
if (is_array($feedbackdata)) {
    $answer->feedback = $feedbackdata['text'] ?? '';
    $answer->feedbackformat = $feedbackdata['format'] ?? FORMAT_HTML;
} else {
    $answer->feedback = $feedbackdata;
    $answer->feedbackformat = FORMAT_HTML;
}
```

### 🚫 Progress System Anti-Patterns (NEW - Moodle Stored Progress)

**NEVER DO THIS - Wrong Progress Method Calls:**
```php
// ❌ WRONG - get_stored_progress() doesn't exist on task
$progressbar = $adhoctask->get_stored_progress()->get_content();
```

**✅ CORRECT - Proper Stored Progress Usage:**
```php
// ✅ CORRECT - Use stored_progress_bar static methods
$progressidnumber = \core\output\stored_progress_bar::convert_to_idnumber(
    \your_plugin\task\your_task::class,
    $adhoctask->get_id()
);
$storedprogressbar = \core\output\stored_progress_bar::get_by_idnumber($progressidnumber);
$progressbar = $storedprogressbar ? $storedprogressbar->get_content() : '';

// In task class - use stored_progress_task_trait
class your_task extends \core\task\adhoc_task {
    use \core\task\stored_progress_task_trait;

    public function execute() {
        $this->start_stored_progress();
        $this->progress->update($current, $total, $message);
        $this->progress->update_full(100, $completion_message);
    }
}
```

### 🚫 Form Processing Anti-Patterns (NEW - AJAX vs Traditional)

**NEVER DO THIS - Mixed AJAX/Traditional Processing:**
```php
// ❌ WRONG - AJAX processing with JSON responses mixed with HTML
header('Content-Type: application/json');
$adhoctask->set_initial_progress(); // Outputs HTML
echo json_encode(['success' => true]); // Mixed with HTML = broken
```

**✅ CORRECT - Choose One Pattern Consistently:**

**Option 1: Traditional Form Processing (Recommended):**
```php
// ✅ CORRECT - Handle form processing in same PHP file
if ($formsubmitted) {
    // Process form data
    $job = create_job($data);

    // Render progress page directly
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('your_plugin/progress', $data);
    echo $OUTPUT->footer();
    exit;
}

// Show form if not submitted
echo $OUTPUT->render_from_template('your_plugin/form', $data);
```

**Option 2: Pure AJAX (More Complex):**
```php
// ✅ CORRECT - Pure AJAX with proper output buffering
if ($formsubmitted) {
    header('Content-Type: application/json');

    // Buffer any HTML output
    ob_start();
    $adhoctask->set_initial_progress();
    ob_end_clean();

    echo json_encode(['success' => true, 'redirect' => $url]);
    exit;
}
```

### 🚫 SQL Parameter Anti-Patterns

**NEVER DO THIS - Mixed Parameter Types:**
```php
// ❌ WRONG - Mixing named and positional parameters
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED); // Named
$sql .= "AND q.qtype IN (" . implode(',', array_fill(0, count($types), '?')) . ")"; // Positional
$params = array_merge($params, $types); // Mixed - CAUSES ERROR!
```

**✅ CORRECT - Consistent Named Parameters:**
```php
// ✅ CORRECT - All parameters use named format
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);

$qtypeparams = [];
$qtypesql = [];
foreach ($types as $index => $qtype) {
    $paramname = 'qtype' . $index;
    $qtypeparams[$paramname] = $qtype;
    $qtypesql[] = ':' . $paramname;
}

$sql .= "AND q.qtype IN (" . implode(',', $qtypesql) . ")";
$params = array_merge($params, $qtypeparams);
```

### 🚫 PHP Syntax Anti-Patterns

**NEVER DO THIS - Method Chaining in Arrays:**
```php
// ❌ WRONG - Method chaining in array literals causes parse errors
$templatedata = [
    'returnurl' => new \moodle_url('/path', ['param' => $value])->out(false),
];
```

**✅ CORRECT - Separate Object Creation:**
```php
// ✅ CORRECT - Create objects first, then use in arrays
$returnurl = new \moodle_url('/path', ['param' => $value]);
$templatedata = [
    'returnurl' => $returnurl->out(false),
];
```

### 🚫 Required Files Anti-Patterns (NEW - Missing Dependencies)

**NEVER DO THIS - Missing Required Includes:**
```php
// ❌ WRONG - Missing required question library
[$module, $cm] = get_module_from_cmid($cmid); // Function not available!
```

**✅ CORRECT - Include Required Dependencies:**
```php
// ✅ CORRECT - Include required question library
require_once($CFG->dirroot . '/question/editlib.php');
[$module, $cm] = get_module_from_cmid($cmid);
```

### 🚫 Language String Anti-Patterns

**NEVER DO THIS - Cache Issues:**
```php
// ❌ WRONG - Adding strings without version bump
$string['new_string'] = 'New String';
// Plugin version stays same - strings not loaded!
```

**✅ CORRECT - Proper String Management:**
```php
// ✅ CORRECT - Always bump version when adding strings
// In version.php: increment version number
$plugin->version = 2025091102; // Increment this!

// In lang file: use clear, descriptive identifiers
$string['bulk_action_title'] = 'Bulk Action Title'; // Clear identifier
$string['your_feature_name'] = 'Feature Name'; // Not generic names
```

## Key Implementation Patterns

### 1. Main Plugin Feature Class (CORRECTED)

```php
namespace qbank_yourplugin;

use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;

class plugin_feature extends plugin_features_base {

    /**
     * ✅ CORRECT: Bulk actions for multi-question operations
     */
    public function get_bulk_actions(view $qbank): array {
        global $PAGE, $USER;

        // ✅ CORRECT: Use $PAGE->context, not get_question_bank()
        if (!has_capability('moodle/question:editall', $PAGE->context)) {
            return [];
        }

        // ✅ CORRECT: Check dependencies/configuration
        if (!$this->is_feature_available()) {
            return [];
        }

        return [
            new your_bulk_action($qbank),
        ];
    }

    /**
     * Single question actions (individual question operations)
     */
    public function get_question_actions($qbank): array {
        return [
            new your_single_action($qbank)
        ];
    }

    /**
     * Display columns (show data in question list)
     */
    public function get_question_columns(view $qbank): array {
        return [
            new your_column($qbank),
        ];
    }

    /**
     * Search filters (enable question filtering)
     */
    public function get_question_filters(?view $qbank = null): array {
        return [
            new your_condition($qbank),
        ];
    }

    /**
     * Helper method for dependency checks
     */
    private function is_feature_available(): bool {
        return class_exists('\required_plugin\class_name');
    }
}
```

### 2. Bulk Actions (CORRECTED for Multi-Question Operations)

```php
namespace qbank_yourplugin;

use core_question\local\bank\bulk_action_base;

class your_bulk_action extends bulk_action_base {

    public function get_bulk_action_title(): string {
        return get_string('bulk_action_title', 'qbank_yourplugin'); // ✅ Clear string key
    }

    public function get_key(): string {
        return 'your_bulk_action'; // ✅ Unique key for this action
    }

    /**
     * ✅ CORRECT: Simple static URL - handle context in target page
     */
    public function get_bulk_action_url(): \moodle_url {
        return new \moodle_url('/question/bank/yourplugin/bulk_action.php');
    }

    public function get_bulk_action_capabilities(): ?array {
        return [
            'moodle/question:editall',
            'your_plugin/capability:use',
        ];
    }

    public function initialise_javascript(): void {
        global $PAGE;
        $PAGE->requires->js_call_amd('qbank_yourplugin/bulk_action', 'init');
    }
}
```

### 3. Bulk Action Target Page (NEW - Essential Pattern)

```php
// File: bulk_action.php
<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');

// ✅ CORRECT: Get context from cmid parameter (bulk actions use cmid)
$cmid = required_param('cmid', PARAM_INT);
$your_action = optional_param('your_bulk_action', false, PARAM_BOOL);

// ✅ CORRECT: Standard Moodle pattern for context from cmid
[$module, $cm] = get_module_from_cmid($cmid);
require_login($cm->course, false, $cm);
$context = context_module::instance($cmid);

// Check capabilities
require_capability('moodle/question:editall', $context);
require_capability('your_plugin/capability:use', $context);

// ✅ CORRECT: Extract selected question IDs from bulk action form
$selectedquestions = [];
if ($your_action) {
    foreach ($_REQUEST as $key => $value) {
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $questionid = (int)$matches[1];
            $selectedquestions[] = $questionid;
            // ✅ IMPORTANT: Verify permission for each question
            question_require_capability_on($questionid, 'edit');
        }
    }
}

if (empty($selectedquestions)) {
    // ✅ CORRECT: Return to question bank if no questions selected (updated URL)
    $returnurl = new \moodle_url('/question/edit.php', ['cmid' => $cmid]);
    redirect($returnurl, 'No questions selected', null, \core\output\notification::NOTIFY_ERROR);
}

// ✅ CORRECT: Set up page with proper context
$PAGE->set_context($context);
$PAGE->set_url('/question/bank/yourplugin/bulk_action.php', ['cmid' => $cmid]);

// Continue with your bulk action logic...
$questions = your_manager::get_questions_by_ids($selectedquestions);
// Process the selected questions...
```

### 4. Question Manager Pattern (NEW - For Handling Selected Questions)

```php
namespace qbank_yourplugin\local;

class question_manager {

    /**
     * ✅ ESSENTIAL: Method to get questions by their IDs (for bulk actions)
     * Uses correct Moodle 5.x database structure
     */
    public static function get_questions_by_ids(array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        // ✅ Correct Moodle 5.x query structure
        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext,
                       qc.name as categoryname, qc.id as categoryid
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                WHERE q.id $insql
                AND q.parent = 0
                AND qv.status <> 'hidden'
                AND qv.version = (SELECT MAX(v.version)
                                 FROM {question_versions} v
                                 WHERE v.questionbankentryid = qbe.id)
                ORDER BY q.name";

        return $DB->get_records_sql($sql, $params);
    }
}
```

## Essential Quick Reference

### Key URL Changes in Moodle 5.x
- Old: `/question/bank/view.php` → New: `/question/edit.php`
- Always use `cmid` parameter for question bank access
- Use `/question/banks.php` for course-level question bank listing

### Question Creation Requirements (3-Table Structure)
1. **Insert into question table** with proper fields
2. **Create question_bank_entries record** with unique idnumber
3. **Create question_versions record** linking them
4. **Handle question type-specific data** (multichoice, etc.)
5. **Validate idnumber uniqueness** in category before insertion
