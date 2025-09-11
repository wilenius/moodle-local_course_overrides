# Question Bank Plugin Development Guide

You are developing a Moodle question bank (qbank) plugin. These plugins extend the question bank interface with additional columns, actions, filters, and controls.

## Plugin Architecture

### Core Components

1. **plugin_feature.php** - Main entry point extending `\core_question\local\bank\plugin_features_base`
2. **version.php** - Plugin metadata and version information
3. **Action classes** - Extend `question_action_base` for question actions
4. **Column classes** - Extend `column_base` or `row_base` for data display
5. **Filter classes** - Extend filter base classes for search functionality

### Plugin Structure
```
qbank_yourplugin/
├── classes/
│   ├── plugin_feature.php           # Main feature registration
│   ├── [action_name]_action.php     # Question actions
│   ├── [column_name]_column.php     # Column displays
│   ├── [filter_name]_condition.php  # Search filters
│   ├── privacy/provider.php         # Privacy API
│   └── output/
│       └── renderer.php             # Custom renderers
├── lang/en/
│   └── qbank_yourplugin.php         # Language strings
├── db/
│   ├── access.php                   # Capabilities
│   └── install.xml                  # Database schema
├── tests/
│   └── [test_files].php             # Unit tests
├── amd/src/                         # AMD modules (optional)
├── templates/                       # Mustache templates
├── styles.css                       # Custom CSS
└── version.php
```

## Key Implementation Patterns

### 1. Main Plugin Feature Class
```php
namespace qbank_yourplugin;

class plugin_feature extends \core_question\local\bank\plugin_features_base {
    
    public function get_question_actions($qbank): array {
        return [
            new your_action($qbank)
        ];
    }
    
    public function get_question_columns(view $qbank): array {
        return [
            new your_column($qbank),
        ];
    }
    
    public function get_question_bank_controls(view $qbank, context $context, int $categoryid): array {
        return [
            100 => new your_control($categoryid),
        ];
    }
    
    public function get_question_filters(?view $qbank = null): array {
        return [
            new your_condition($qbank),
        ];
    }
    
    public function get_bulk_actions(view $qbank): array {
        return [
            new your_bulk_action($qbank),
        ];
    }
}
```

### 2. Question Actions
Question actions appear as icons/buttons next to questions:
```php
namespace qbank_yourplugin;

use core_question\local\bank\question_action_base;

class your_action extends question_action_base {
    
    protected $actionstring;
    
    public function init(): void {
        parent::init();
        $this->actionstring = get_string('actionname', 'qbank_yourplugin');
    }
    
    public function get_menu_position(): int {
        return 300; // Higher numbers appear later in menu
    }
    
    protected function get_url_icon_and_label(\stdClass $question): array {
        if (!question_has_capability_on($question, 'edit')) {
            return [null, null, null];
        }
        
        $url = new \moodle_url('/question/bank/yourplugin/action.php', ['id' => $question->id]);
        return [$url, 'i/youricon', $this->actionstring];
    }
}
```

### 3. Display Columns
Columns show question data in the bank:
```php
namespace qbank_yourplugin;

use core_question\local\bank\column_base;

class your_column extends column_base {
    
    public function get_name(): string {
        return 'yourcolumn';
    }
    
    public function get_title(): string {
        return get_string('columnname', 'qbank_yourplugin');
    }
    
    protected function display_content($question, $rowclasses): void {
        echo s($question->yourfield);
    }
    
    public function get_required_fields(): array {
        return ['q.yourfield'];
    }
    
    public function is_sortable(): array {
        return [
            'yourfield' => ['field' => 'q.yourfield', 'title' => get_string('yourfield', 'qbank_yourplugin')],
        ];
    }
}
```

### 4. Search Filters
Filters enable advanced question searching:
```php
namespace qbank_yourplugin;

use core_question\local\bank\condition;

class your_condition extends condition {
    
    public function get_title(): string {
        return get_string('filtername', 'qbank_yourplugin');
    }
    
    public function get_filter_class(): string {
        return 'qbank_yourplugin-filter';
    }
    
    public function display_options(): void {
        $options = [
            0 => get_string('all'),
            1 => get_string('option1', 'qbank_yourplugin'),
        ];
        echo \html_writer::select($options, 'yourfilter', $this->get_initial_value());
    }
    
    public function get_condition(): array {
        $value = $this->get_filter_value();
        if ($value) {
            return ['q.yourfield = :yourparam', ['yourparam' => $value]];
        }
        return ['', []];
    }
}
```

### 5. Bulk Actions
Bulk actions allow batch operations on selected questions:
```php
namespace qbank_yourplugin;

use core_question\local\bank\bulk_action_base;

class your_bulk_action extends bulk_action_base {
    
    public function get_bulk_action_title(): string {
        return get_string('yourbulkaction', 'qbank_yourplugin');
    }
    
    public function get_key(): string {
        return 'youraction';
    }
    
    public function get_bulk_action_url(): \moodle_url {
        return new \moodle_url('/question/bank/yourplugin/bulk_action.php');
    }
    
    public function get_bulk_action_capabilities(): ?array {
        return [
            'moodle/question:editall',
        ];
    }
    
    public function initialise_javascript(): void {
        global $PAGE;
        // Initialize any required JavaScript for the bulk action
        $PAGE->requires->js_call_amd('qbank_yourplugin/bulk_action', 'init');
    }
}
```

## Common Plugin Types

### Action Plugins
- **Purpose**: Add clickable actions to questions (edit, preview, copy, etc.)
- **Examples**: `qbank_editquestion`, `qbank_previewquestion`
- **Key methods**: `get_url_icon_and_label()`, `get_menu_position()`

### Column Plugins  
- **Purpose**: Display additional question data/metadata
- **Examples**: `qbank_viewquestiontext`, `qbank_viewquestionname`
- **Key methods**: `display_content()`, `get_required_fields()`, `is_sortable()`

### Filter Plugins
- **Purpose**: Enable advanced question searching/filtering
- **Examples**: Based on question properties, tags, usage statistics
- **Key methods**: `display_options()`, `get_condition()`

### Control Plugins
- **Purpose**: Add interface elements to question bank header
- **Examples**: "Add new question", format toggles, bulk actions
- **Key methods**: Return renderable objects with appropriate priority

### Bulk Action Plugins
- **Purpose**: Enable batch operations on multiple selected questions
- **Examples**: `qbank_bulkmove` (move questions between categories)
- **Key methods**: `get_bulk_action_title()`, `get_bulk_action_url()`, `get_bulk_action_capabilities()`

## Security Considerations

1. **Capability Checks**: Always verify user permissions before actions
2. **Input Validation**: Sanitize all user inputs and parameters
3. **Context Verification**: Ensure user has access to question context
4. **SQL Injection Prevention**: Use parameterized queries
5. **XSS Protection**: Escape output with `s()` or appropriate functions

## Database Integration

Question bank plugins commonly interact with:
- `{question}` - Core question data
- `{question_categories}` - Question organization
- `{question_bank_entries}` - Question bank metadata
- `{question_versions}` - Version history
- Custom plugin tables as needed

## Testing Requirements

Include comprehensive tests:
- **Unit tests**: Test individual methods and logic
- **Integration tests**: Test plugin integration with question bank
- **Behat tests**: Test user workflows and UI interactions
- **Privacy tests**: Verify privacy API compliance

## Language Strings

Provide clear, accessible language strings:
```php
// lang/en/qbank_yourplugin.php
$string['pluginname'] = 'Your Plugin Name';
$string['privacy:metadata'] = 'This plugin does not store personal data.';
$string['actionname'] = 'Perform Action';
$string['columnname'] = 'Column Title';
```

## Performance Considerations

- Minimize database queries in column display methods
- Use appropriate caching for expensive operations
- Consider impact on question bank loading time
- Implement efficient filtering algorithms
- Only load required data fields via `get_required_fields()`

## Development Workflow

1. Plan plugin functionality and user interface
2. Create plugin structure with version.php
3. Implement plugin_feature.php as main entry point
4. Develop individual components (actions, columns, filters)
5. Add language strings and privacy compliance
6. Write comprehensive tests
7. Test with various question types and contexts
8. Document installation and configuration