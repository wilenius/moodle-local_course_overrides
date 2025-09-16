### Moodle TinyMCE Plugin Structure

Every Moodle TinyMCE editor plugin MUST follow this structure:
```
tiny_[pluginname]/
├── classes/
│   ├── plugininfo.php             # Main plugin class (REQUIRED)
│   ├── privacy/
│   │   └── provider.php           # Privacy API implementation
│   ├── external/                  # External API classes (optional)
│   └── form/                      # Form classes (optional)
├── version.php                    # Version and dependencies (REQUIRED)
├── lang/                          # Language strings (REQUIRED)
│   └── en/
│       └── tiny_[pluginname].php
├── db/                            # Database and capabilities
│   ├── access.php                 # Capabilities definition
│   ├── install.xml                # Database schema (optional)
│   └── upgrade.php                # Database upgrades (optional)
├── amd/src/                       # AMD modules (REQUIRED)
│   ├── plugin.js                  # Main TinyMCE plugin (REQUIRED)
│   ├── common.js                  # Common constants and utilities
│   ├── commands.js                # Button/menu command handlers
│   ├── configuration.js           # Plugin configuration
│   ├── options.js                 # TinyMCE options registration
│   └── [feature]/                 # Feature-specific modules
├── templates/                     # Mustache templates (optional)
│   └── [dialogname].mustache     # Modal dialog templates
├── pix/                           # Icons (optional)
│   └── [iconname].svg            # SVG icons for buttons
├── styles.css                     # Plugin-specific styles (optional)
├── tests/                         # Unit tests
│   ├── behat/                     # Behat tests
│   └── [test_files].php          # Unit tests
└── manage.php                     # Management UI (optional)
```

### Key Files to Examine in Moodle Core

When developing, examine these reference implementations in the Moodle core:

1. **Base TinyMCE architecture:**
   - `../moodle/lib/editor/tiny/classes/plugin.php` - Abstract base class
   - `../moodle/lib/editor/tiny/classes/plugin_with_*` - Interface definitions

2. **Simple plugin patterns:**
   - `../moodle/lib/editor/tiny/plugins/autosave/` - Configuration-only plugin
   - `../moodle/lib/editor/tiny/plugins/noautolink/` - Simple behavior plugin
   - `../moodle/lib/editor/tiny/plugins/html/` - Simple dialog plugin

3. **Button and toolbar plugins:**
   - `../moodle/lib/editor/tiny/plugins/link/` - Link insertion with dialog
   - `../moodle/lib/editor/tiny/plugins/media/` - Media insertion with file picker
   - `../moodle/lib/editor/tiny/plugins/equation/` - Mathematical equations

4. **Advanced integration plugins:**
   - `../moodle/lib/editor/tiny/plugins/recordrtc/` - WebRTC recording
   - `../moodle/lib/editor/tiny/plugins/h5p/` - H5P content integration
   - `../moodle/lib/editor/tiny/plugins/accessibilitychecker/` - Accessibility validation

5. **AI and premium plugins:**
   - `../moodle/lib/editor/tiny/plugins/aiplacement/` - AI content generation
   - `../moodle/lib/editor/tiny/plugins/premium/` - Premium TinyMCE features

## Development Guidelines

### 1. Main Plugin Class (`classes/plugininfo.php`)

The main class MUST:
- Be in the `tiny_[pluginname]` namespace
- Extend `\editor_tiny\plugin` abstract class
- Implement appropriate interfaces for functionality

```php
namespace tiny_yourplugin;

use context;
use editor_tiny\editor;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_menuitems;
use editor_tiny\plugin_with_configuration;

class plugininfo extends plugin implements
    plugin_with_buttons,
    plugin_with_menuitems,
    plugin_with_configuration {

    // Override if plugin has specific enabling requirements
    public static function is_enabled(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null
    ): bool {
        // Check if user has capability to use this plugin
        return has_capability('tiny/yourplugin:use', $context);
    }

    // Define toolbar buttons (if plugin_with_buttons implemented)
    public static function get_available_buttons(): array {
        return [
            'tiny_yourplugin/yourbutton',
            'tiny_yourplugin/anotherbutton',
        ];
    }

    // Define menu items (if plugin_with_menuitems implemented)
    public static function get_available_menuitems(): array {
        return [
            'tiny_yourplugin/yourmenuitem',
        ];
    }

    // Provide configuration to JavaScript (if plugin_with_configuration implemented)
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null
    ): array {
        return [
            'contextid' => $context->id,
            'permissions' => [
                'canupload' => has_capability('moodle/file:upload', $context),
                'canmanage' => has_capability('tiny/yourplugin:manage', $context),
            ],
            'settings' => [
                'feature_enabled' => get_config('tiny_yourplugin', 'feature_enabled'),
                'max_items' => get_config('tiny_yourplugin', 'max_items'),
            ],
        ];
    }

    // For external API access (if plugin_with_configuration_for_external implemented)
    public static function get_plugin_configuration_for_external(context $context): array {
        $config = self::get_plugin_configuration_for_context($context, [], []);
        return [
            'permissions' => json_encode($config['permissions']),
            'settings' => json_encode($config['settings']),
        ];
    }
}
```

### 2. Version File (`version.php`)

Must define:
```php
$plugin->component = 'tiny_[pluginname]';
$plugin->version = 2024010100;  // YYYYMMDDXX format
$plugin->requires = 2025040800; // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';

// Optional: Dependencies on other plugins
$plugin->dependencies = [
    'editor_tiny' => 2025040800,  // Always depends on TinyMCE
];
```

### 3. Language Strings (`lang/en/tiny_[pluginname].php`)

Minimum required:
```php
$string['pluginname'] = 'Your TinyMCE Plugin';

// Button and menu item labels
$string['yourbutton'] = 'Your Button';
$string['yourmenuitem'] = 'Your Menu Item';

// Dialog and form strings
$string['insert'] = 'Insert';
$string['cancel'] = 'Cancel';
$string['title'] = 'Title';
$string['description'] = 'Description';

// Help and instruction strings
$string['help'] = 'Help for your plugin';
$string['helplinktext'] = 'Help with plugin';

// Capabilities
$string['yourplugin:use'] = 'Use the plugin';
$string['yourplugin:manage'] = 'Manage plugin content';

// Privacy
$string['privacy:metadata'] = 'The [Plugin Name] plugin does not store any personal data.';

// Error messages
$string['error:invalidformat'] = 'Invalid format provided.';
$string['error:nopermission'] = 'You do not have permission to use this feature.';
```

### 4. Capabilities (`db/access.php`)

Define plugin capabilities:
```php
$capabilities = [
    'tiny/yourplugin:use' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ]
    ],

    'tiny/yourplugin:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ]
    ],
];
```

### 5. Main JavaScript Plugin (`amd/src/plugin.js`)

The TinyMCE plugin entry point:
```javascript
// Import required modules
import {getTinyMCE} from 'editor_tiny/loader';
import {getPluginMetadata} from 'editor_tiny/utils';

import {component, pluginName} from './common';
import * as Commands from './commands';
import * as Configuration from './configuration';
import * as Options from './options';

// Register the plugin with TinyMCE
export default new Promise(async(resolve) => {
    const [
        tinyMCE,
        setupCommands,
        pluginMetadata,
    ] = await Promise.all([
        getTinyMCE(),
        Commands.getSetup(),
        getPluginMetadata(component, pluginName),
    ]);

    tinyMCE.PluginManager.add(`${component}/plugin`, (editor) => {
        // Register plugin options
        Options.register(editor);

        // Setup commands (buttons, menu items)
        setupCommands(editor);

        // Return plugin metadata
        return pluginMetadata;
    });

    resolve([`${component}/plugin`]);
});
```

### 6. Common Constants (`amd/src/common.js`)

Define plugin constants:
```javascript
export const component = 'tiny_yourplugin';
export const pluginName = `${component}/plugin`;

// Button/menu item identifiers
export const buttonName = `${component}_yourbutton`;
export const menuItemName = `${component}_yourmenuitem`;

// Icon identifiers
export const icon = 'your-icon';
```

### 7. Commands Setup (`amd/src/commands.js`)

Define buttons and menu items:
```javascript
import {getButtonImage} from 'editor_tiny/utils';
import {get_string as getString} from 'core/str';

import {component, buttonName, menuItemName, icon} from './common';
import {handleYourAction} from './yourfeature';

export const getSetup = async() => {
    const [
        buttonText,
        menuItemText,
        buttonImage,
    ] = await Promise.all([
        getString('yourbutton', component),
        getString('yourmenuitem', component),
        getButtonImage('icon', component),
    ]);

    return (editor) => {
        // Register the button
        editor.ui.registry.addButton(buttonName, {
            icon,
            tooltip: buttonText,
            onAction: () => handleYourAction(editor),
        });

        // Register the menu item
        editor.ui.registry.addMenuItem(menuItemName, {
            icon,
            text: menuItemText,
            onAction: () => handleYourAction(editor),
        });
    };
};
```

### 8. Plugin Configuration (`amd/src/configuration.js`)

Handle Moodle configuration:
```javascript
import {component} from './common';

const getDataset = (tinymce, name) => {
    const editor = tinymce.get(name);
    if (!editor || !editor.plugins || !editor.plugins[component]) {
        return {};
    }
    return editor.plugins[component].dataset || {};
};

export const getCurrentConfig = (editor) => {
    const dataset = getDataset(editor.editorManager, editor.id);
    return dataset.config || {};
};

export const getPermissions = (editor) => {
    const config = getCurrentConfig(editor);
    return config.permissions || {};
};

export const getSetting = (editor, settingName, defaultValue = null) => {
    const config = getCurrentConfig(editor);
    return config.settings?.[settingName] ?? defaultValue;
};
```

### 9. Options Registration (`amd/src/options.js`)

Register TinyMCE options:
```javascript
import {component} from './common';

const getOptionName = (optionName) => `${component}_${optionName}`;

export const register = (editor) => {
    const registerOption = editor.options.register;

    // Register boolean option
    registerOption(getOptionName('enabled'), {
        processor: 'boolean',
        default: true,
    });

    // Register string option
    registerOption(getOptionName('mode'), {
        processor: 'string',
        default: 'default',
    });

    // Register number option
    registerOption(getOptionName('max_items'), {
        processor: 'number',
        default: 10,
    });
};

export const getEnabled = (editor) => editor.options.get(getOptionName('enabled'));
export const getMode = (editor) => editor.options.get(getOptionName('mode'));
export const getMaxItems = (editor) => editor.options.get(getOptionName('max_items'));
```

## Plugin Types and Patterns

### 🎯 Button/Toolbar Plugins
- **Purpose**: Add buttons to TinyMCE toolbar for specific actions
- **Examples**: Bold, italic, link insertion, media insertion
- **Implementation**: Implement `plugin_with_buttons`, register button actions
- **Key Features**: Icon, tooltip, action handler

```javascript
// Button registration
editor.ui.registry.addButton(buttonName, {
    icon: 'bold',
    tooltip: 'Bold',
    onAction: () => editor.execCommand('mceToggleFormat', false, 'bold'),
});
```

### 🎯 Dialog/Modal Plugins
- **Purpose**: Open dialogs for complex input or configuration
- **Examples**: Link dialog, image properties, table insertion
- **Implementation**: Modal management, form handling, data validation
- **Key Features**: Dynamic content, form validation, context awareness

```javascript
// Dialog registration
const openDialog = async(editor) => {
    const modalObject = await Modal.create({
        type: Modal.types.DEFAULT,
        title: await getString('dialogtitle', component),
        body: await Templates.render('tiny_yourplugin/dialog', {}),
        large: true,
    });

    modalObject.show();

    // Handle form submission
    modalObject.getRoot().on('click', '[data-action="save"]', (e) => {
        e.preventDefault();
        const formData = getFormData(modalObject);
        insertContent(editor, formData);
        modalObject.destroy();
    });
};
```

### 🎯 Content Transformation Plugins
- **Purpose**: Automatically transform content as user types
- **Examples**: Auto-linking, emoticons, mathematical notation
- **Implementation**: Content filters, regex patterns, real-time processing
- **Key Features**: Non-intrusive, performance optimized, reversible

```javascript
// Content transformation
editor.on('BeforeGetContent', (e) => {
    if (e.format === 'html') {
        e.content = transformContent(e.content);
    }
});

editor.on('SetContent', (e) => {
    if (e.format === 'html') {
        e.content = reverseTransform(e.content);
    }
});
```

### 🎯 Integration Plugins
- **Purpose**: Integrate with external Moodle features or services
- **Examples**: File picker, H5P content, AI services, recording
- **Implementation**: External API calls, service integration, permission management
- **Key Features**: Authentication, error handling, progress feedback

```javascript
// External service integration
const handleExternalIntegration = async(editor) => {
    try {
        // Show loading state
        editor.setProgressState(true);

        // Call external service
        const result = await Ajax.call([{
            methodname: 'tiny_yourplugin_process_content',
            args: {
                content: editor.getContent(),
                contextid: getContextId(editor),
            }
        }])[0];

        // Insert result
        editor.insertContent(result.html);

    } catch (error) {
        // Handle errors gracefully
        Notification.exception(error);
    } finally {
        // Hide loading state
        editor.setProgressState(false);
    }
};
```

### 🎯 Configuration/Behavior Plugins
- **Purpose**: Modify TinyMCE behavior or provide configuration
- **Examples**: Autosave, accessibility checker, spell checker
- **Implementation**: Event handlers, background processing, user preferences
- **Key Features**: Non-visible functionality, performance monitoring, user settings

```javascript
// Behavior modification
export default new Promise(async(resolve) => {
    const tinyMCE = await getTinyMCE();

    tinyMCE.PluginManager.add(`${component}/plugin`, (editor) => {
        // Modify editor behavior
        editor.on('init', () => {
            setupBehaviorModifications(editor);
        });

        // Background processing
        setInterval(() => {
            performBackgroundTask(editor);
        }, 30000); // Every 30 seconds

        return getPluginMetadata(component, pluginName);
    });

    resolve([`${component}/plugin`]);
});
```

## External API Integration

### WebService Definition (`db/services.php`)
```php
$functions = [
    'tiny_yourplugin_process_content' => [
        'classname' => 'tiny_yourplugin\external\process_content',
        'methodname' => 'execute',
        'description' => 'Process content with plugin',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
```

### External API Class (`classes/external/process_content.php`)
```php
namespace tiny_yourplugin\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class process_content extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content' => new external_value(PARAM_RAW, 'Content to process'),
            'contextid' => new external_value(PARAM_INT, 'Context ID'),
        ]);
    }

    public static function execute(string $content, int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'content' => $content,
            'contextid' => $contextid,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);

        require_capability('tiny/yourplugin:use', $context);

        // Process the content
        $processedcontent = self::process_content_internal($params['content']);

        return [
            'html' => $processedcontent,
            'success' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Processed HTML'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }

    private static function process_content_internal(string $content): string {
        // Implement your content processing logic here
        return $content;
    }
}
```

## Testing Checklist

#### Claude Code Responsibilities (Can Verify During Development)

These items can be checked by Claude Code during development:

1. **[AUTOMATED]** ✅ PHP syntax is valid (no parse errors)
   - Claude Code can verify using PHP linter if available

2. **[AUTOMATED]** ✅ Required files exist with correct naming
   - `classes/plugininfo.php`, `version.php`, language files, AMD modules

3. **[AUTOMATED]** ✅ File structure follows Moodle conventions
   - Correct directory structure and file placement

4. **[AUTOMATED]** ✅ Code follows Moodle coding standards
   - Can check basic standards (indentation, naming)

5. **[AUTOMATED]** ✅ Required interfaces are implemented correctly
   - Plugin class extends correct base and implements needed interfaces

6. **[AUTOMATED]** ✅ Language string keys match expected patterns
   - Verify button/menu item strings exist

7. **[AUTOMATED]** ✅ Version file has all required fields
   - Component name, version number, requirements

8. **[AUTOMATED]** ✅ GPL license headers are present
   - Check all PHP and JS files have proper headers

9. **[AUTOMATED]** ✅ AMD module structure is correct
   - Main plugin.js exists and follows pattern

10. **[AUTOMATED]** ✅ No obvious security issues
    - Check for XSS vulnerabilities, capability checks

#### Manual Testing Required

These must be tested by installing in Moodle:

11. **[MANUAL]** ⚠️ Plugin installs without errors
12. **[MANUAL]** ⚠️ Plugin appears in TinyMCE editor
13. **[MANUAL]** ⚠️ Buttons appear in toolbar (if applicable)
14. **[MANUAL]** ⚠️ Menu items appear in menus (if applicable)
15. **[MANUAL]** ⚠️ Plugin functionality works correctly
16. **[MANUAL]** ⚠️ Dialogs open and close properly (if applicable)
17. **[MANUAL]** ⚠️ Content is inserted/modified correctly
18. **[MANUAL]** ⚠️ External integrations work (if applicable)
19. **[MANUAL]** ⚠️ Capability restrictions work correctly
20. **[MANUAL]** ⚠️ No JavaScript errors in browser console
21. **[MANUAL]** ⚠️ Performance is acceptable
22. **[MANUAL]** ⚠️ Works in different browsers
23. **[MANUAL]** ⚠️ Responsive design works on mobile
24. **[MANUAL]** ⚠️ Accessibility requirements are met

## Common Patterns to Reference

### Simple Button Plugin
Look at: `../moodle/lib/editor/tiny/plugins/html/`

### File Integration Plugin
Look at: `../moodle/lib/editor/tiny/plugins/media/`

### Dialog-based Plugin
Look at: `../moodle/lib/editor/tiny/plugins/link/`

### Configuration-only Plugin
Look at: `../moodle/lib/editor/tiny/plugins/autosave/`

### Advanced Integration Plugin
Look at: `../moodle/lib/editor/tiny/plugins/equation/`

### External Service Plugin
Look at: `../moodle/lib/editor/tiny/plugins/aiplacement/`

## Important Notes for Claude Code

1. **Never modify files in the Moodle core repository** - only read them for reference
2. **Always check existing TinyMCE plugins** for patterns before implementing
3. **Test what you can programmatically** - Use available tools to validate syntax
4. **Document what needs manual testing** - TinyMCE plugins require browser testing
5. **Follow the naming convention strictly** - Component names must match exactly
6. **Check capability definitions** - All used capabilities must be defined
7. **AMD modules are required** - TinyMCE plugins cannot work without JavaScript
8. **Consider mobile compatibility** - Editor must work on touch devices
9. **Accessibility is critical** - Screen readers and keyboard navigation
10. **Performance matters** - Editor should remain responsive

## Resources

- Moodle 5.0 Developer Documentation: https://moodledev.io/
- TinyMCE Plugin API: https://moodledev.io/docs/apis/core/editor/tiny
- Plugin Development: https://moodledev.io/docs/apis/plugintypes
- TinyMCE Official Documentation: https://www.tiny.cloud/docs/
- AMD Module Development: https://moodledev.io/docs/guides/javascript/modules

## Questions to Ask Before Starting

1. What functionality should this plugin provide?
2. Should it add buttons, menu items, or modify behavior?
3. Does it need to open dialogs or work inline?
4. Should it integrate with Moodle features (files, users, courses)?
5. Does it need to call external APIs or services?
6. What user permissions should it require?
7. Should it work on mobile devices?
8. Does it need to store any data or settings?
9. Should it modify existing content or add new content?
10. Are there accessibility requirements to consider?