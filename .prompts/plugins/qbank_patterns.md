# Question bank plugin patterns and anti-patterns

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
    // ✅ CORRECT: Return to question bank if no questions selected
    $returnurl = new \moodle_url('/question/bank/view.php', ['cmid' => $cmid]);
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
     */
    public static function get_questions_by_ids(array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext, qc.name as categoryname
                FROM {question} q
                JOIN {question_categories} qc ON q.category = qc.id
                WHERE q.id $insql
                AND q.parent = 0
                ORDER BY q.name";

        return $DB->get_records_sql($sql, $params);
    }
}
```


## ⚠️ CRITICAL PATTERNS TO AVOID COMMON BUGS

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
    $aiconfig = \some_api::get_config($USER, $PAGE->context->id, null, ['purpose']);
    
    return [new your_bulk_action($qbank)];
}
```
### 🚫 Navigation vs Bulk Action Anti-Patterns

**NEVER DO THIS - Wrong Integration Type:**
```php
// ❌ WRONG - Don't use navigation for bulk operations
public function get_navigation_node(): ?navigation_node_base {
    // This creates a separate tab, not a bulk action!
    return new navigation();
}
```

**✅ CORRECT - Bulk Actions for Multi-Question Operations:**
```php
// ✅ CORRECT - Use bulk actions for operations on selected questions
public function get_bulk_actions(view $qbank): array {
    // This integrates with "With selected" dropdown
    return [new your_bulk_action($qbank)];
}
```

### 🚫 URL Construction Anti-Patterns

**NEVER DO THIS in Navigation/Bulk Action URLs:**
```php
// ❌ WRONG - Trying to access question bank during initialization
public function get_navigation_url(): \moodle_url {
    $context = $this->get_question_bank()->get_most_specific_context(); // FAILS!
    return new \moodle_url('/path/page.php', ['contextid' => $context->id]);
}
```

**✅ CORRECT - Simple Static URLs:**
```php
// ✅ CORRECT - Keep URLs simple, handle context in target page
public function get_bulk_action_url(): \moodle_url {
    return new \moodle_url('/question/bank/yourplugin/bulk_action.php');
}
```


