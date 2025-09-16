# Moodle Question Type Plugin Patterns and Anti-Patterns

This document provides patterns, anti-patterns, and best practices specifically for developing Moodle question type plugins based on analysis of core question types and real-world implementations.

## Good Patterns

### 1. Proper Question Type Class Structure

**Pattern**: Extend `question_type` and implement all required methods consistently.

```php
class qtype_yourplugin extends question_type {
    // ✅ Always override these core methods
    public function get_question_options($question) {
        global $DB;
        $question->options = $DB->get_record('qtype_yourplugin_options',
            ['questionid' => $question->id]);

        if ($question->options === false) {
            // ✅ Handle missing options gracefully
            $question->options = $this->create_default_options($question);
        }

        parent::get_question_options($question);
    }

    public function save_question_options($question) {
        global $DB;
        $result = new stdClass();

        // ✅ Validate data before saving
        if (!$this->validate_question_data($question)) {
            $result->error = get_string('invaliddata', 'qtype_yourplugin');
            return $result;
        }

        // ✅ Use transactions for data integrity
        $transaction = $DB->start_delegated_transaction();
        try {
            parent::save_question_options($question);
            $this->save_custom_options($question);
            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        return true;
    }

    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        return new qtype_yourplugin_question();
    }
}
```

### 2. Robust Question Definition Implementation

**Pattern**: Implement comprehensive response handling and grading logic.

```php
class qtype_yourplugin_question extends question_graded_automatically {

    public function get_expected_data() {
        // ✅ Define clear data structure
        return [
            'answer' => PARAM_RAW_TRIMMED,
            'confidence' => PARAM_INT,
        ];
    }

    public function summarise_response(array $response) {
        // ✅ Handle empty responses gracefully
        if (empty($response['answer'])) {
            return null;
        }
        return $response['answer'];
    }

    public function is_complete_response(array $response) {
        // ✅ Consider all required fields
        return !empty($response['answer']) &&
               isset($response['confidence']);
    }

    public function get_validation_error(array $response) {
        // ✅ Provide specific error messages
        if (empty($response['answer'])) {
            return get_string('pleaseenterananswer', 'qtype_yourplugin');
        }
        if (!isset($response['confidence'])) {
            return get_string('pleasesetconfidence', 'qtype_yourplugin');
        }
        return '';
    }

    public function grade_response(array $response) {
        // ✅ Handle edge cases
        if (!$this->is_complete_response($response)) {
            return [0, question_state::$gradedwrong];
        }

        $fraction = $this->calculate_grade($response);

        // ✅ Apply confidence penalties if applicable
        if (isset($response['confidence'])) {
            $fraction = $this->apply_confidence_penalty($fraction, $response['confidence']);
        }

        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }
}
```

### 3. Secure and Accessible Form Implementation

**Pattern**: Build forms with proper validation, accessibility, and user experience.

```php
class qtype_yourplugin_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        // ✅ Group related elements
        $mform->addElement('header', 'configheader',
            get_string('configuration', 'qtype_yourplugin'));

        // ✅ Add proper labels and help
        $mform->addElement('text', 'customfield',
            get_string('customfield', 'qtype_yourplugin'),
            ['size' => 50]);
        $mform->setType('customfield', PARAM_TEXT);
        $mform->addHelpButton('customfield', 'customfield', 'qtype_yourplugin');
        $mform->addRule('customfield', get_string('required'), 'required', null, 'client');

        // ✅ Use appropriate form elements
        $options = [
            'easy' => get_string('easy', 'qtype_yourplugin'),
            'medium' => get_string('medium', 'qtype_yourplugin'),
            'hard' => get_string('hard', 'qtype_yourplugin'),
        ];
        $mform->addElement('select', 'difficulty',
            get_string('difficulty', 'qtype_yourplugin'), $options);
        $mform->setDefault('difficulty', 'medium');

        // ✅ Add answer fields with proper structure
        $this->add_per_answer_fields($mform,
            get_string('answerno', 'qtype_yourplugin', '{no}'),
            question_bank::fraction_options(), 1, 1);

        // ✅ Include standard feedback fields
        $this->add_combined_feedback_fields(true);
        $this->add_interactive_settings(true, true);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // ✅ Validate business logic
        if (!empty($data['customfield'])) {
            if (strlen($data['customfield']) > 255) {
                $errors['customfield'] = get_string('toolong', 'qtype_yourplugin');
            }
            if (!$this->is_valid_format($data['customfield'])) {
                $errors['customfield'] = get_string('invalidformat', 'qtype_yourplugin');
            }
        }

        // ✅ Validate answer consistency
        $answercount = 0;
        $totalfraction = 0;
        foreach ($data['answer'] as $key => $answer) {
            if (trim($answer) !== '') {
                $answercount++;
                $totalfraction += (float)$data['fraction'][$key];
            }
        }

        if ($answercount < 1) {
            $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_yourplugin');
        }

        if (abs($totalfraction - 1.0) > 0.005) {
            $errors['fraction[0]'] = get_string('fractionsaddwrong', 'qtype_yourplugin');
        }

        return $errors;
    }
}
```

### 4. Responsive and Accessible Renderer

**Pattern**: Create renderers that work across devices and accessibility tools.

```php
class qtype_yourplugin_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        // ✅ Use semantic HTML structure
        $output = html_writer::start_div('qtype-yourplugin-question');

        // ✅ Render question text with proper formatting
        $questiontext = $question->format_questiontext($qa);
        $output .= html_writer::div($questiontext, 'qtext');

        // ✅ Create accessible form controls
        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = [
            'type' => 'text',
            'name' => $inputname,
            'id' => $inputname,
            'value' => $currentanswer,
            'class' => 'form-control',
            'size' => 50,
            'readonly' => $options->readonly,
            'aria-describedby' => $inputname . '_description',
        ];

        // ✅ Add proper labeling
        $label = html_writer::label(
            get_string('youranswer', 'qtype_yourplugin'),
            $inputname,
            false,
            ['class' => 'sr-only']
        );

        $input = html_writer::empty_tag('input', $inputattributes);

        // ✅ Provide context and instructions
        $description = html_writer::div(
            get_string('answerinstructions', 'qtype_yourplugin'),
            'answer-instructions',
            ['id' => $inputname . '_description']
        );

        $output .= html_writer::div($label . $input . $description, 'answer-container');
        $output .= html_writer::end_div();

        return $output;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        // ✅ Provide meaningful feedback
        $answer = $question->get_matching_answer($response);
        if (!$answer || !$answer->feedback) {
            return '';
        }

        return $question->format_text(
            $answer->feedback,
            $answer->feedbackformat,
            $qa, 'question', 'answerfeedback', $answer->id
        );
    }
}
```

### 5. Proper Database Schema Design

**Pattern**: Design efficient and maintainable database schemas.

```xml
<!-- ✅ Well-structured install.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="question/type/yourplugin/db" VERSION="2024011700">
  <TABLES>
    <TABLE NAME="qtype_yourplugin_options" COMMENT="Options for yourplugin questions">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="questionid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="customfield" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="difficulty" TYPE="char" LENGTH="10" NOTNULL="true" DEFAULT="medium"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <!-- ✅ Include standard feedback fields -->
        <FIELD NAME="correctfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="correctfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="partiallycorrectfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="partiallycorrectfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="incorrectfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="incorrectfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <!-- ✅ Enforce referential integrity -->
        <KEY NAME="questionid" TYPE="foreign-unique" FIELDS="questionid" REFTABLE="question" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <!-- ✅ Add indexes for common queries -->
        <INDEX NAME="difficulty" UNIQUE="false" FIELDS="difficulty"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

### 6. Comprehensive Testing Strategy

**Pattern**: Write thorough tests covering all functionality.

```php
class qtype_yourplugin_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // ✅ Test question creation
    public function test_question_creation() {
        $questiondata = test_question_maker::get_question_data('yourplugin');
        $formdata = test_question_maker::get_question_form_data('yourplugin');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();

        $question = $generator->create_question('yourplugin', null,
            ['category' => $cat->id]);

        $this->assertEquals('yourplugin', $question->qtype);
        $this->assertNotEmpty($question->options);
    }

    // ✅ Test grading logic
    public function test_grading_correct_answer() {
        $question = test_question_maker::make_question('yourplugin');
        $this->start_attempt_at_question($question, 'adaptive', 1);

        $this->process_submission(['answer' => 'correct answer']);
        $this->check_current_mark(1);
        $this->check_current_state(question_state::$gradedright);
    }

    // ✅ Test edge cases
    public function test_grading_empty_response() {
        $question = test_question_maker::make_question('yourplugin');
        $this->start_attempt_at_question($question, 'adaptive', 1);

        $this->process_submission(['answer' => '']);
        $this->check_current_mark(0);
        $this->check_current_state(question_state::$gradedwrong);
    }

    // ✅ Test form validation
    public function test_form_validation() {
        $form = new qtype_yourplugin_edit_form(new moodle_url('/'),
            new stdClass(), null, null);

        $errors = $form->validation([
            'customfield' => '',
            'answer' => [''],
            'fraction' => [0],
        ], []);

        $this->assertArrayHasKey('customfield', $errors);
        $this->assertArrayHasKey('answer[0]', $errors);
    }
}
```

## Anti-Patterns

### 1. ❌ Inadequate Error Handling

**Anti-Pattern**: Not handling database errors or missing data.

```php
// ❌ BAD: No error handling
public function get_question_options($question) {
    global $DB;
    $question->options = $DB->get_record('qtype_yourplugin_options',
        ['questionid' => $question->id]);
    // What if record doesn't exist?
    parent::get_question_options($question);
}

// ✅ GOOD: Proper error handling
public function get_question_options($question) {
    global $DB;
    $question->options = $DB->get_record('qtype_yourplugin_options',
        ['questionid' => $question->id]);

    if ($question->options === false) {
        debugging("Question ID {$question->id} missing options record");
        $question->options = $this->create_default_options($question);
    }

    parent::get_question_options($question);
}
```

### 2. ❌ Insecure Input Handling

**Anti-Pattern**: Not validating or sanitizing user input.

```php
// ❌ BAD: Direct database insertion without validation
public function save_question_options($question) {
    global $DB;
    $options = new stdClass();
    $options->questionid = $question->id;
    $options->customfield = $question->customfield; // Unsafe!
    $DB->insert_record('qtype_yourplugin_options', $options);
}

// ✅ GOOD: Proper validation and sanitization
public function save_question_options($question) {
    global $DB;

    // Validate input
    if (!isset($question->customfield) ||
        !$this->is_valid_custom_field($question->customfield)) {
        throw new coding_exception('Invalid custom field data');
    }

    $options = new stdClass();
    $options->questionid = clean_param($question->id, PARAM_INT);
    $options->customfield = clean_param($question->customfield, PARAM_TEXT);

    $DB->insert_record('qtype_yourplugin_options', $options);
}
```

### 3. ❌ Poor Performance Patterns

**Anti-Pattern**: N+1 queries and inefficient database access.

```php
// ❌ BAD: N+1 query problem
public function load_question_answers($questionids) {
    global $DB;
    $result = [];
    foreach ($questionids as $questionid) {
        $answers = $DB->get_records('question_answers',
            ['question' => $questionid]); // Multiple queries!
        $result[$questionid] = $answers;
    }
    return $result;
}

// ✅ GOOD: Single efficient query
public function load_question_answers($questionids) {
    global $DB;
    if (empty($questionids)) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal($questionids);
    $sql = "SELECT * FROM {question_answers} WHERE question $insql";
    $records = $DB->get_records_sql($sql, $params);

    // Group by question ID
    $result = [];
    foreach ($records as $record) {
        $result[$record->question][] = $record;
    }
    return $result;
}
```

### 4. ❌ Inconsistent State Management

**Anti-Pattern**: Not properly tracking question state or responses.

```php
// ❌ BAD: Inconsistent response handling
public function is_complete_response(array $response) {
    return !empty($response['answer']); // Too simplistic
}

public function grade_response(array $response) {
    // Doesn't check completeness!
    return [$this->calculate_grade($response['answer']),
            question_state::$gradedright];
}

// ✅ GOOD: Consistent state management
public function is_complete_response(array $response) {
    return !empty($response['answer']) &&
           $this->is_valid_answer_format($response['answer']);
}

public function grade_response(array $response) {
    if (!$this->is_complete_response($response)) {
        return [0, question_state::$gradedwrong];
    }

    $fraction = $this->calculate_grade($response['answer']);
    return [$fraction, question_state::graded_state_for_fraction($fraction)];
}
```

### 5. ❌ Accessibility Violations

**Anti-Pattern**: Creating inaccessible form controls and content.

```php
// ❌ BAD: Inaccessible form elements
public function formulation_and_controls(question_attempt $qa,
        question_display_options $options) {
    $input = '<input type="text" name="answer" value="' . $currentanswer . '">'; // No label!
    return '<div>' . $questiontext . $input . '</div>'; // No structure!
}

// ✅ GOOD: Accessible implementation
public function formulation_and_controls(question_attempt $qa,
        question_display_options $options) {
    $inputname = $qa->get_qt_field_name('answer');

    $label = html_writer::label(
        get_string('youranswer', 'qtype_yourplugin'),
        $inputname
    );

    $input = html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => $inputname,
        'id' => $inputname,
        'value' => $currentanswer,
        'aria-describedby' => $inputname . '_help'
    ]);

    $help = html_writer::div(
        get_string('answerhelp', 'qtype_yourplugin'),
        'help-text',
        ['id' => $inputname . '_help']
    );

    return html_writer::div($questiontext . $label . $input . $help,
                           'qtype-yourplugin-container');
}
```

### 6. ❌ Inadequate Data Migration

**Anti-Pattern**: Not handling version upgrades properly.

```php
// ❌ BAD: No upgrade handling
function xmldb_qtype_yourplugin_upgrade($oldversion) {
    // Empty function - data loss risk!
    return true;
}

// ✅ GOOD: Proper upgrade handling
function xmldb_qtype_yourplugin_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024011701) {
        // Add new field with default value
        $table = new xmldb_table('qtype_yourplugin_options');
        $field = new xmldb_field('newfield', XMLDB_TYPE_CHAR, '50',
                                null, XMLDB_NOTNULL, null, 'default');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024011701, 'qtype', 'yourplugin');
    }

    return true;
}
```

### 7. ❌ Missing Internationalization

**Anti-Pattern**: Hardcoding strings instead of using language files.

```php
// ❌ BAD: Hardcoded strings
public function get_validation_error(array $response) {
    if (empty($response['answer'])) {
        return 'Please enter an answer.'; // Not translatable!
    }
    return '';
}

// ✅ GOOD: Proper internationalization
public function get_validation_error(array $response) {
    if (empty($response['answer'])) {
        return get_string('pleaseenterananswer', 'qtype_yourplugin');
    }
    return '';
}
```

## Common Gotchas

### 1. File Handling Issues

```php
// ❌ Common mistake: Not handling file contexts properly
public function move_files($questionid, $oldcontextid, $newcontextid) {
    parent::move_files($questionid, $oldcontextid, $newcontextid);
    // Forgot to move custom file areas!
}

// ✅ Correct implementation
public function move_files($questionid, $oldcontextid, $newcontextid) {
    parent::move_files($questionid, $oldcontextid, $newcontextid);
    $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);

    $fs = get_file_storage();
    $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
        'qtype_yourplugin', 'attachments', $questionid);
}
```

### 2. Backup/Restore Oversights

```php
// ❌ Missing backup implementation leads to data loss
// Always implement backup/restore classes for custom data

// ✅ Implement proper backup/restore
class backup_qtype_yourplugin_plugin extends backup_qtype_plugin {
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $options = new backup_nested_element('yourplugin_options', ['id'], [
            'customfield', 'difficulty', 'correctfeedback'
        ]);
        $pluginwrapper->add_child($options);

        $options->set_source_table('qtype_yourplugin_options',
            ['questionid' => backup::VAR_PARENTID]);

        return $plugin;
    }
}
```

### 3. Testing Oversights

```php
// ❌ Insufficient test coverage
class qtype_yourplugin_test extends advanced_testcase {
    public function test_basic() {
        // Only tests happy path
        $this->assertTrue(true);
    }
}

// ✅ Comprehensive testing
class qtype_yourplugin_test extends advanced_testcase {
    public function test_question_creation() { /* ... */ }
    public function test_grading_correct() { /* ... */ }
    public function test_grading_incorrect() { /* ... */ }
    public function test_grading_partial() { /* ... */ }
    public function test_empty_response() { /* ... */ }
    public function test_form_validation() { /* ... */ }
    public function test_backup_restore() { /* ... */ }
    public function test_file_handling() { /* ... */ }
}
```

## Security Checklist

- [ ] All user input is validated and sanitized
- [ ] SQL queries use parameters (no string concatenation)
- [ ] File uploads are properly validated (type, size, content)
- [ ] Output is properly escaped for HTML context
- [ ] Capability checks are performed before sensitive operations
- [ ] CSRF tokens are used in forms
- [ ] File access is controlled through proper APIs
- [ ] Database transactions are used for multi-step operations

## Performance Checklist

- [ ] Database queries are optimized (no N+1 problems)
- [ ] Indexes are added for commonly queried fields
- [ ] Large datasets are paginated
- [ ] File operations are minimized
- [ ] Caching is used where appropriate
- [ ] JavaScript is minified and combined
- [ ] Images are optimized and properly sized

## Accessibility Checklist

- [ ] All form elements have proper labels
- [ ] Color is not the only means of conveying information
- [ ] Focus indicators are visible
- [ ] Content is navigable with keyboard only
- [ ] Images have alt text
- [ ] Semantic HTML is used appropriately
- [ ] ARIA attributes are used where needed
- [ ] Content structure is logical

Following these patterns will help you create robust, secure, and maintainable question type plugins that integrate well with Moodle's ecosystem and provide a great user experience.