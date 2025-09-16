# Modular Moodle Plugin Development Assistant (MoMoPDA)

This repository is a collection of prompts that can be combined in different ways in order to use agentic generative AI for software development in Moodle. Its first version is only tested with Claude Code, but with little modifications, it could be made work with other agent software and backends as well. Pull requests welcome!

## File Structure

```
├── PROMPT.md                        # Main orchestrator file
├── CLAUDE.md                        # Redirect to PROMPT.md
└── .prompts/  
    ├── core/
    │   ├── base-instructions.md     # Core Moodle development principles
    │   ├── security-checklist.md    # Security requirements
    │   └── quality-standards.md     # Code quality standards
    ├── plugins/
    │   ├── block.md                 # Block plugin development guide
    │   ├── enrol.md                 # Enrolment plugin development guide
    │   ├── enrol_patterns.md        # Enrolment plugin patterns and anti-patterns
    │   ├── filter.md                # Filter plugin development guide
    │   ├── filter_patterns.md       # Filter plugin patterns and anti-patterns
    │   ├── qbank.md                 # Question bank plugin development guide
    │   ├── qbank_patterns.md        # Question bank plugin patterns and anti-patterns
    │   ├── qtype.md                 # Question type plugin development guide
    │   ├── qtype_patterns.md        # Question type plugin patterns and anti-patterns
    │   ├── report.md                # Report plugin development guide
    │   ├── report_patterns.md       # Report plugin patterns and anti-patterns
    │   ├── tiny.md                  # TinyMCE editor plugin development guide
    │   └── tiny_patterns.md         # TinyMCE editor plugin patterns and anti-patterns
    ├── tasks/
    │   ├── create.md                # New plugin creation
    │   ├── bugfix.md                # Bug fixing workflow
    │   ├── test.md                  # Test creation
    │   ├── enhance.md               # Feature enhancement
    │   └── refactor.md              # Code refactoring
    └── patterns/
        ├── database.md              # Database operation patterns
        ├── forms.md                 # Moodle forms patterns
        ├── navigation.md            # Navigation integration
        └── api-usage.md             # Common API usage patterns
```
## Moodle Core Repository

The Moodle core repository should be cloned alongside this repository for reference:
```
../moodle/          # Moodle core repository
./                  # This plugin repository
```


## Usage Examples

### Example 1: New Block Plugin
**Detected**: `block_` repository name
**Loads**:
- core/base-instructions.md
- plugins/block.md  
- tasks/create.md
- core/security-checklist.md

### Example 2: Bug Fix in Question Type
**Detected**: `qtype` repository name + git branch "fix/calculation-error"
**Loads**:
- core/base-instructions.md
- plugins/qtype.md
- plugins/qtype_patterns.md
- tasks/bugfix.md
- patterns/database.md (if DB operations detected)
- core/security-checklist.md

### Example 3: New Enrolment Plugin
**Detected**: `enrol_` repository name
**Loads**:
- core/base-instructions.md
- plugins/enrol.md
- plugins/enrol_patterns.md
- tasks/create.md
- core/security-checklist.md

### Example 4: TinyMCE Editor Plugin Enhancement
**Detected**: `tiny_` repository name + request mentions "add feature"
**Loads**:
- core/base-instructions.md
- plugins/tiny.md
- plugins/tiny_patterns.md
- tasks/enhance.md
- patterns/forms.md (if form integration detected)
- core/security-checklist.md

### Example 5: Adding Tests
**Detected**: Request mentions "tests" or "phpunit"
**Loads**:
- core/base-instructions.md
- plugins/{detected_type}.md
- plugins/{detected_type}_patterns.md (if available)
- tasks/test.md
- core/security-checklist.md

## Supported Plugin Types

MoMoPDA provides comprehensive development guides and pattern documentation for the following Moodle plugin types:

### Core Plugin Types
- **Block Plugins** (`block_*`) - Custom dashboard and course blocks
- **Question Types** (`qtype_*`) - Custom question types for quizzes and assignments
- **Question Bank Plugins** (`qbank_*`) - Question bank management and organization tools

### Enrolment and User Management
- **Enrolment Plugins** (`enrol_*`) - Custom user enrolment methods and workflows

### Content and Filtering
- **Filter Plugins** (`filter_*`) - Content processing and transformation filters
- **TinyMCE Editor Plugins** (`tiny_*`) - Rich text editor extensions and tools

### Administration and Reporting
- **Report Plugins** (`report_*`) - Administrative reports and analytics dashboards

### Each Plugin Type Includes:
- **Development Guide** - Complete implementation instructions with code examples
- **Patterns & Anti-Patterns** - Best practices, common pitfalls, and security considerations
- **Testing Strategies** - Unit testing, integration testing, and quality assurance
- **Performance Guidelines** - Optimization techniques and database best practices
- **Security Checklists** - Vulnerability prevention and secure coding practices

All guides are based on analysis of Moodle 5.x core implementations and follow official Moodle development standards.
