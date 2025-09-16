# Question Bank Plugin Development Guide

You are developing a Moodle question bank (qbank) plugin. These plugins extend the question bank interface with additional columns, actions, filters, and controls.

## Plugin Architecture

### Core Components

1. **plugin_feature.php** - Main entry point extending `\core_question\local\bank\plugin_features_base`
2. **version.php** - Plugin metadata and version information
3. **Bulk action classes** - Extend `bulk_action_base` for multi-question operations
4. **Action classes** - Extend `question_action_base` for single question actions
5. **Column classes** - Extend `column_base` or `row_base` for data display
6. **Filter classes** - Extend filter base classes for search functionality

### Plugin Structure
```
qbank_yourplugin/
├── classes/
│   ├── plugin_feature.php              # Main feature registration
│   ├── [bulk_action_name]_action.php   # Bulk operations (multi-question)
│   ├── [action_name]_action.php        # Single question actions
│   ├── [column_name]_column.php        # Column displays
│   ├── [filter_name]_condition.php     # Search filters
│   ├── local/                          # Business logic classes
│   ├── task/                           # Background tasks
│   ├── privacy/provider.php            # Privacy API
│   └── output/
│       └── renderer.php                # Custom renderers
├── lang/en/
│   └── qbank_yourplugin.php            # Language strings
├── db/
│   ├── access.php                      # Capabilities
│   └── install.xml                     # Database schema
├── tests/
│   └── [test_files].php                # Unit tests
├── amd/src/                            # AMD modules (optional)
├── templates/                          # Mustache templates
├── [bulk_action_page].php              # Entry point for bulk actions
├── [action_page].php                   # Entry point for single actions
├── styles.css                          # Custom CSS
└── version.php
```

## Common Plugin Types & When to Use Each

### 🎯 Bulk Action Plugins (Multi-Question Operations)
- **Purpose**: Operations on multiple selected questions simultaneously
- **Examples**: Move questions, delete multiple questions, export selected questions, **bulk AI editing**
- **Integration**: "With selected" dropdown menu
- **Key Pattern**: Users select questions first, then choose bulk action
- **URL Receives**: `cmid` parameter + selected question IDs as `q[ID]` parameters

### 🎯 Single Action Plugins (Individual Question Operations) 
- **Purpose**: Actions on one question at a time
- **Examples**: Edit question, preview question, duplicate question
- **Integration**: Action icons next to each question
- **Key Pattern**: Direct action on specific question
- **URL Receives**: Question ID parameter

### 🎯 Column Plugins (Data Display)
- **Purpose**: Display additional question data/metadata  
- **Examples**: Question text preview, usage statistics, custom fields
- **Integration**: Additional columns in question list table

### 🎯 Filter Plugins (Search Enhancement)
- **Purpose**: Enable advanced question searching/filtering
- **Examples**: Filter by question type, tags, usage, custom criteria
- **Integration**: Filter controls above question list

## 🚨 Critical Implementation Checklist

### Context Access
- [ ] ✅ Use `global $PAGE; $PAGE->context` in `plugin_feature.php`
- [ ] ❌ Never call `$this->get_question_bank()` in `plugin_feature.php`
- [ ] ✅ Handle context detection in target pages using `cmid` parameter
- [ ] ✅ Use `get_module_from_cmid()` and `context_module::instance()` pattern

### Bulk Actions
- [ ] ✅ Use `get_bulk_actions()` method for multi-question operations
- [ ] ❌ Never use navigation tabs for bulk operations
- [ ] ✅ Create simple static URLs in `get_bulk_action_url()`
- [ ] ✅ Extract question IDs using `q([0-9]+)` pattern in target page
- [ ] ✅ Verify permissions for each selected question individually
- [ ] ✅ Redirect back to question bank with `cmid` parameter

### Language Strings & Caching
- [ ] ✅ Use clear, descriptive string identifiers (`bulk_action_title`, not `bulk_ai_edit`)
- [ ] ✅ Increment version number when adding new strings
- [ ] ✅ Test string loading after plugin installation/upgrade
- [ ] ✅ Include all required strings in language file

### Security
- [ ] ✅ Check capabilities at both plugin and individual question level
- [ ] ✅ Validate and sanitize all input parameters
- [ ] ✅ Use `question_require_capability_on()` for individual questions
- [ ] ✅ Use parameterized database queries

### Testing
- [ ] ✅ Test with no questions selected (should redirect gracefully)
- [ ] ✅ Test with multiple question types and large selections
- [ ] ✅ Test capability checking and permission scenarios
- [ ] ✅ Test plugin installation and language string loading


