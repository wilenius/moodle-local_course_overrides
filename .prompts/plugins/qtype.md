# Moodle Question Type Plugin Development Guide

You are an expert Moodle developer specializing in question type plugins for Moodle 5.x. This guide covers developing custom question types that integrate with Moodle's question engine.

## Question Type Plugin Architecture

Question type plugins extend Moodle's question engine to support different types of questions beyond the built-in types. They handle question creation, rendering, grading, and data management.

### Core Components

Every question type plugin consists of these essential components:

1. **Question Type Class** (`questiontype.php`) - Main plugin class extending `question_type`
2. **Question Definition Class** (`question.php`) - Question instance logic extending `question_definition`
3. **Edit Form** (`edit_*.php`) - Question editing interface extending `question_edit_form`
4. **Renderer** (`renderer.php`) - Question display logic extending `qtype_renderer`
5. **Language Files** (`lang/en/qtype_*.php`) - Internationalization strings
6. **Database Schema** (`db/install.xml`, `db/upgrade.php`) - Data structure definitions
7. **Version File** (`version.php`) - Plugin metadata and versioning

### File Structure

```
question/type/yourplugin/
├── questiontype.php          # Main question type class
├── question.php              # Question definition classes
├── edit_yourplugin_form.php  # Question editing form
├── renderer.php              # Question renderer
├── backup/                   # Backup and restore functionality
├── db/
│   ├── install.xml          # Database schema
│   ├── upgrade.php          # Database upgrades
│   └── access.php           # Capability definitions
├── lang/en/
│   └── qtype_yourplugin.php # Language strings
├── tests/                   # Unit and integration tests
├── version.php              # Plugin version and dependencies
└── README.md               # Plugin documentation
```

## Main Question Type Class

The main class extends `question_type` and handles:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin extends question_type {

    public function get_question_options($question) {
        global $DB;
        // Load question-specific options from database
        $question->options = $DB->get_record('qtype_yourplugin_options',
            ['questionid' => $question->id]);
        parent::get_question_options($question);
    }

    public function save_question_options($question) {
        global $DB;
        // Save question-specific options to database
        // Handle answers, feedback, and configuration
        parent::save_question_options($question);
    }

    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        return new qtype_yourplugin_question();
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        // Initialize question instance with specific data
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        // Clean up question-specific data
        parent::delete_question($questionid, $contextid);
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        // Handle file area moves for custom file areas
    }
}
```

## Question Definition Classes

Question definitions handle runtime question logic:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin_question extends question_definition {

    public function get_expected_data() {
        // Define expected response data structure
        return ['answer' => PARAM_RAW_TRIMMED];
    }

    public function summarise_response(array $response) {
        // Create human-readable summary of response
        return isset($response['answer']) ? $response['answer'] : null;
    }

    public function is_complete_response(array $response) {
        // Check if response is complete
        return !empty($response['answer']);
    }

    public function get_validation_error(array $response) {
        // Validate response and return error if invalid
        if (!$this->is_complete_response($response)) {
            return get_string('pleaseenterananswer', 'qtype_yourplugin');
        }
        return '';
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Compare two responses for equality
        return question_utils::arrays_same_at_key_missing_is_blank(
            $prevresponse, $newresponse, 'answer');
    }

    public function grade_response(array $response) {
        // Grade the response and return fraction (0-1)
        if (!$this->is_complete_response($response)) {
            return [0, question_state::$gradedwrong];
        }

        $fraction = $this->calculate_grade($response['answer']);
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    public function compute_final_grade($responses, $totaltries) {
        // Calculate final grade considering all attempts
        return $this->get_last_grade($responses);
    }
}
```

### Grading Strategies

Different question types use different grading approaches:

- **Automatically Graded**: Extend `question_graded_automatically`
- **Manually Graded**: Extend `question_manually_gradable`
- **With Responses**: Extend `question_with_responses`

```php
// For automatically graded questions
class qtype_yourplugin_question extends question_graded_automatically {
    // Implementation for automatic grading
}

// For manually graded questions
class qtype_yourplugin_question extends question_manually_gradable {
    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }
}
```

## Edit Form Implementation

Question editing forms extend `question_edit_form`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        // Add question-specific form elements

        $mform->addElement('header', 'optionsheader',
            get_string('options', 'qtype_yourplugin'));

        $mform->addElement('text', 'customfield',
            get_string('customfield', 'qtype_yourplugin'));
        $mform->setType('customfield', PARAM_TEXT);
        $mform->addHelpButton('customfield', 'customfield', 'qtype_yourplugin');

        // Add answer fields
        $this->add_per_answer_fields($mform,
            get_string('answerno', 'qtype_yourplugin', '{no}'),
            question_bank::fraction_options(), 1, 1);

        // Add combined feedback fields
        $this->add_combined_feedback_fields(true);

        // Add hint fields
        $this->add_interactive_settings(true, true);
    }

    protected function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $repeated = [];

        $repeated[] = $mform->createElement('text', 'answer',
                $label, ['size' => 40]);
        $repeated[] = $mform->createElement('select', 'fraction',
                get_string('grade'), $gradeoptions);
        $repeated[] = $mform->createElement('editor', 'feedback',
                get_string('feedback'), ['rows' => 5], $this->editoroptions);

        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';

        return $repeated;
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (empty($question->options)) {
            return $question;
        }

        // Preprocess question-specific data
        $question->customfield = $question->options->customfield;

        // Preprocess answers
        if (!empty($question->options->answers)) {
            $key = 0;
            foreach ($question->options->answers as $answer) {
                $question->answer[$key] = $answer->answer;
                $question->fraction[$key] = $answer->fraction;
                $question->feedback[$key] = [
                    'text' => $answer->feedback,
                    'format' => $answer->feedbackformat,
                ];
                $key++;
            }
        }

        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Add custom validation
        if (empty($data['customfield'])) {
            $errors['customfield'] = get_string('required');
        }

        // Validate answers
        $answercount = 0;
        foreach ($data['answer'] as $key => $answer) {
            if (trim($answer) !== '') {
                $answercount++;
            }
        }

        if ($answercount < 1) {
            $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_yourplugin');
        }

        return $errors;
    }
}
```

## Question Renderer

Renderers control question display:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        $questiontext = $question->format_questiontext($qa);

        $input = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => $qa->get_qt_field_name('answer'),
            'value' => $currentanswer,
            'size' => 80,
            'class' => 'form-control',
            'readonly' => $options->readonly,
        ]);

        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);
        $result .= html_writer::start_tag('div', ['class' => 'ablock']);
        $result .= html_writer::tag('label',
            get_string('answercolon', 'qtype_yourplugin', $input),
            ['for' => $qa->get_qt_field_name('answer')]);
        $result .= html_writer::end_tag('div');

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $answer = $question->get_matching_answer($qa->get_last_qt_var('answer'));

        if (!$answer) {
            return '';
        }

        return $question->format_text($answer->feedback, $answer->feedbackformat,
                $qa, 'question', 'answerfeedback', $answer->id);
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $answer = $question->get_correct_answer();

        if (!$answer) {
            return '';
        }

        return get_string('correctansweris', 'qtype_yourplugin',
            s($answer->answer));
    }
}
```

## Database Schema

Define database tables in `db/install.xml`:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="question/type/yourplugin/db" VERSION="2024011700">
  <TABLES>
    <TABLE NAME="qtype_yourplugin_options" COMMENT="Options for yourplugin questions">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="questionid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="customfield" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="correctfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="correctfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="partiallycorrectfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="partiallycorrectfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="incorrectfeedback" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="incorrectfeedbackformat" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="questionid" TYPE="foreign-unique" FIELDS="questionid" REFTABLE="question" REFFIELDS="id"/>
      </KEYS>
    </TABLE>
  </TABLES>
</XMLDB>
```

## Version File

Define plugin metadata in `version.php`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_yourplugin';
$plugin->version   = 2024011700;
$plugin->requires  = 2024041600; // Moodle 5.0
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [];
```

## Language Files

Define strings in `lang/en/qtype_yourplugin.php`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Your Plugin';
$string['pluginname_help'] = 'Description of your question type';
$string['pluginnameadding'] = 'Adding a Your Plugin question';
$string['pluginnameediting'] = 'Editing a Your Plugin question';
$string['pluginnamesummary'] = 'Brief description for question bank';

$string['customfield'] = 'Custom field';
$string['customfield_help'] = 'Help text for custom field';
$string['answercolon'] = 'Answer: {$a}';
$string['correctansweris'] = 'The correct answer is: {$a}';
$string['pleaseenterananswer'] = 'Please enter an answer.';
$string['notenoughanswers'] = 'This type of question requires at least one answer.';

$string['privacy:metadata'] = 'The Your Plugin question type plugin does not store any personal data.';
```

## Advanced Features

### File Handling

For questions with file uploads:

```php
// In question type class
public function response_file_areas() {
    return ['attachments', 'answer'];
}

public function move_files($questionid, $oldcontextid, $newcontextid) {
    parent::move_files($questionid, $oldcontextid, $newcontextid);
    $fs = get_file_storage();
    $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
        'qtype_yourplugin', 'attachments', $questionid);
}

protected function delete_files($questionid, $contextid) {
    parent::delete_files($questionid, $contextid);
    $fs = get_file_storage();
    $fs->delete_area_files($contextid, 'qtype_yourplugin', 'attachments', $questionid);
}
```

### Custom Grading

For complex grading logic:

```php
private function calculate_grade($response) {
    // Implement custom grading algorithm
    $score = 0;

    // Example: Partial credit for close answers
    foreach ($this->answers as $answer) {
        $similarity = $this->calculate_similarity($response, $answer->answer);
        if ($similarity >= 0.8) {
            $score = max($score, $answer->fraction * $similarity);
        }
    }

    return $score;
}
```

### Interactive Elements

For questions with dynamic behavior:

```php
// In renderer
public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
    // Add JavaScript for interactivity
    $this->page->requires->js_call_amd('qtype_yourplugin/interactive', 'init', [
        $qa->get_slot()
    ]);

    // Render interactive elements
    return $this->render_interactive_question($qa, $options);
}
```

## Testing Requirements

### Unit Tests

Create comprehensive unit tests in `tests/`:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin_test extends advanced_testcase {

    public function test_question_creation() {
        $this->resetAfterTest();

        $questiondata = test_question_maker::get_question_data('yourplugin');
        $formdata = test_question_maker::get_question_form_data('yourplugin');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();

        $question = $generator->create_question('yourplugin', null, ['category' => $cat->id]);

        $this->assertEquals('yourplugin', $question->qtype);
        $this->assertNotEmpty($question->options);
    }

    public function test_grading() {
        $this->resetAfterTest();

        $question = test_question_maker::make_question('yourplugin');
        $this->start_attempt_at_question($question, 'adaptive', 1);

        // Test correct answer
        $this->process_submission(['answer' => 'correct answer']);
        $this->check_current_mark(1);

        // Test incorrect answer
        $this->process_submission(['answer' => 'wrong answer']);
        $this->check_current_mark(0);
    }
}
```

### Question Maker

Implement test question generation:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class qtype_yourplugin_test_helper extends question_test_helper {

    public function get_test_questions() {
        return ['basic'];
    }

    public function make_yourplugin_question_basic() {
        question_bank::load_question_definition_classes('yourplugin');
        $q = new qtype_yourplugin_question();

        test_question_maker::initialise_a_question($q);
        $q->name = 'Your Plugin Question';
        $q->questiontext = 'What is the answer?';
        $q->generalfeedback = 'General feedback';

        // Set up answers
        $q->answers = [
            13 => new question_answer(13, 'correct answer', 1.0, 'Correct!', FORMAT_HTML),
            14 => new question_answer(14, 'wrong answer', 0.0, 'Incorrect.', FORMAT_HTML),
        ];

        return $q;
    }
}
```

## Security Considerations

1. **Input Validation**: Always validate and sanitize user input
2. **XSS Prevention**: Use proper output escaping with `s()`, `format_text()`
3. **SQL Injection**: Use parameterized queries and Moodle's DB API
4. **File Security**: Validate file types and sizes for uploads
5. **Capability Checks**: Verify user permissions before operations
6. **CSRF Protection**: Use Moodle's form API for CSRF tokens

```php
// Input validation example
public function validation($data, $files) {
    $errors = parent::validation($data, $files);

    // Validate required fields
    if (empty($data['customfield'])) {
        $errors['customfield'] = get_string('required');
    }

    // Validate data format
    if (!empty($data['customfield']) && !preg_match('/^[a-zA-Z0-9\s]+$/', $data['customfield'])) {
        $errors['customfield'] = get_string('invalidformat', 'qtype_yourplugin');
    }

    return $errors;
}
```

## Performance Optimization

1. **Database Queries**: Use efficient queries and avoid N+1 problems
2. **Caching**: Cache expensive calculations and database lookups
3. **File Loading**: Lazy load large resources
4. **JavaScript**: Minimize and optimize client-side code

```php
// Efficient data loading
public function get_question_options($question) {
    global $DB;

    // Load options and answers in single query
    $sql = "SELECT o.*, a.id as answerid, a.answer, a.fraction, a.feedback
            FROM {qtype_yourplugin_options} o
            LEFT JOIN {question_answers} a ON a.question = o.questionid
            WHERE o.questionid = ?
            ORDER BY a.id";

    $records = $DB->get_records_sql($sql, [$question->id]);
    // Process records efficiently...
}
```

## Best Practices

1. **Follow Moodle Coding Standards**: Use proper indentation, naming conventions
2. **Internationalization**: Make all strings translatable
3. **Accessibility**: Ensure WCAG compliance in HTML output
4. **Mobile Compatibility**: Test on mobile devices and responsive themes
5. **Backup/Restore**: Implement proper backup and restore functionality
6. **Documentation**: Provide comprehensive user and developer documentation
7. **Version Control**: Use semantic versioning and maintain changelogs
8. **Testing**: Write comprehensive unit and integration tests

Remember to test your question type thoroughly across different Moodle configurations, themes, and use cases before releasing it to production environments.