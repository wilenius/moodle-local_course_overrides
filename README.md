# Course Quiz Overrides - Moodle Local Plugin

A Moodle local plugin that provides centralized management of quiz overrides for all quizzes within a course. This plugin allows instructors to efficiently create, view, and manage quiz overrides for users and groups across multiple quizzes from a single interface.

## Features

- **Centralized Override Management**: View and manage all quiz overrides for a course from one location
- **Bulk Override Creation**: Create the same override settings across multiple quizzes simultaneously
- **Create overrides for individual users**
- **Create Time limit overrides**
- **Update Existing Overrides**: Option to update existing overrides or skip them when creating bulk overrides
- **Integration with Course Navigation**: Available in the course main navigation menu
- **Respects the mod/quiz:manageoverrides capability**

### Not implemented yet 

- **Group Support**
- **More Override Types**:
  - Opening times
  - Closing times
  - Number of attempts

## Requirements

- Tested on Moodle 5.x, might work on older versions too
- PHP 7.4 or higher

## Installation

1. Download or clone the plugin to your Moodle installation:
   ```bash
   cd /path/to/moodle
   git clone [repository-url] local/course_overrides
   ```

2. Complete the installation through the Moodle admin interface:
   - Log in as an administrator
   - Go to Site Administration → Notifications
   - Follow the installation prompts

## Usage

Self-explanatory really. Go to the course administration menu, and look for "Course quiz overrides".

## File Structure

```
local/course_overrides/
├── README.md
├── version.php              # Plugin version and metadata
├── lib.php                  # Core library functions and navigation
├── index.php                # Main override management interface
├── bulk_override.php        # Bulk override creation and management
├── override.php             # Individual override handling
├── lang/
│   └── en/
│       └── local_course_overrides.php  # English language strings
└── classes/                 # Plugin classes (if any)
```

## Language Support

The plugin includes English language strings and follows Moodle's internationalization standards. Additional language packs can be added in the `lang/` directory.

## Development

### Version Information
- **Current Version**: 1.0
- **Maturity**: ALPHA
- **Component**: local_course_overrides

### Contributing

This version was created in 25 minutes with Claude Code and Claude 4.x models during the Moodle AI workshop in MoodleMoot Global 2025. The code is completely not human-examined. So at the moment, you definitely should not install this on your production server. Code reviews and pull requests most welcome! 

## License

This plugin is licensed under the GNU General Public License v3.
