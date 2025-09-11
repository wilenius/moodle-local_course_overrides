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
    │   ├── block.md                 # Block plugin specifics
    │   ├── local.md                 # Local plugin specifics
    │   ├── qtype.md                 # Question type specifics
    │   ├── qbank.md                 # Question bank specifics
    │   ├── mod.md                   # Activity module specifics
    │   └── theme.md                 # Theme specifics
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
- tasks/bugfix.md
- patterns/database.md (if DB operations detected)
- core/security-checklist.md

### Example 3: Adding Tests
**Detected**: Request mentions "tests" or "phpunit"
**Loads**:
- core/base-instructions.md
- plugins/{detected_type}.md
- tasks/test.md
- core/security-checklist.md
