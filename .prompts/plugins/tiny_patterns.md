# TinyMCE Plugin Development Patterns and Anti-Patterns

## ⚠️ CRITICAL MOODLE 5.x TINYMCE PATTERNS & BUG PREVENTION

### 🚫 Plugin Class Implementation Anti-Patterns

**NEVER DO THIS - Wrong Plugin Structure:**
```php
// ❌ WRONG - Missing namespace, wrong class name, wrong parent class
class tiny_yourplugin extends moodle_plugin {  // Wrong parent class
    // Implementation
}

// OR
namespace tiny;  // Wrong namespace
class yourplugin_plugin extends plugin {  // Wrong class name pattern
    // Implementation
}

// OR
namespace tiny_yourplugin;
class plugin extends \editor_tiny\plugin {  // Wrong class name
    // Implementation
}
```

**✅ CORRECT - Proper Plugin Class Structure:**
```php
// ✅ CORRECT - Correct namespace and class naming
namespace tiny_yourplugin;

use context;
use editor_tiny\editor;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;

class plugininfo extends plugin implements plugin_with_buttons {
    // Correct implementation
    public static function get_available_buttons(): array {
        return ['tiny_yourplugin/yourbutton'];
    }
}
```

### 🚫 Interface Implementation Anti-Patterns

**NEVER DO THIS - Missing Interface Methods:**
```php
// ❌ WRONG - Implementing interface but missing required methods
class plugininfo extends plugin implements plugin_with_buttons {
    // Missing get_available_buttons() method - will cause fatal error

    public static function is_enabled(context $context, array $options, array $fpoptions, ?editor $editor = null): bool {
        return true;
    }
}

// ❌ WRONG - Wrong method signature
class plugininfo extends plugin implements plugin_with_configuration {
    // Wrong parameters - missing ?editor parameter
    public static function get_plugin_configuration_for_context(context $context, array $options): array {
        return [];
    }
}
```

**✅ CORRECT - Proper Interface Implementation:**
```php
// ✅ CORRECT - All required methods with correct signatures
class plugininfo extends plugin implements
    plugin_with_buttons,
    plugin_with_menuitems,
    plugin_with_configuration {

    public static function get_available_buttons(): array {
        return ['tiny_yourplugin/yourbutton'];
    }

    public static function get_available_menuitems(): array {
        return ['tiny_yourplugin/yourmenuitem'];
    }

    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null
    ): array {
        return [
            'contextid' => $context->id,
            'permissions' => $this->get_permissions($context),
        ];
    }
}
```

### 🚫 Capability Checking Anti-Patterns

**NEVER DO THIS - Missing or Weak Capability Checks:**
```php
// ❌ WRONG - No capability checking
public static function is_enabled(context $context, array $options, array $fpoptions, ?editor $editor = null): bool {
    // Anyone can use this plugin - security risk!
    return true;
}

// ❌ WRONG - Using wrong context or generic capabilities
public static function is_enabled(context $context, array $options, array $fpoptions, ?editor $editor = null): bool {
    // Using system context instead of actual context
    return has_capability('moodle/site:config', context_system::instance());
}

// ❌ WRONG - Not checking if user is guest
public static function is_enabled(context $context, array $options, array $fpoptions, ?editor $editor = null): bool {
    return has_capability('tiny/yourplugin:use', $context);
    // Guest users might have capabilities but shouldn't use editor plugins
}
```

**✅ CORRECT - Proper Capability Checking:**
```php
// ✅ CORRECT - Comprehensive capability and user checking
public static function is_enabled(
    context $context,
    array $options,
    array $fpoptions,
    ?editor $editor = null
): bool {
    // Check user authentication first
    if (!isloggedin() || isguestuser()) {
        return false;
    }

    // Check specific capability for this plugin
    if (!has_capability('tiny/yourplugin:use', $context)) {
        return false;
    }

    // Check additional requirements (e.g., file upload for media plugins)
    if (!empty($options['maxfiles']) && !has_capability('moodle/file:upload', $context)) {
        return false;
    }

    return true;
}
```

### 🚫 JavaScript AMD Module Anti-Patterns

**NEVER DO THIS - Synchronous or Blocking Code:**
```javascript
// ❌ WRONG - Synchronous TinyMCE loading (blocks browser)
import {component} from './common';

const tinyMCE = require('editor_tiny/loader');  // Synchronous require
const metadata = require('editor_tiny/utils');  // Will block

tinyMCE.PluginManager.add(`${component}/plugin`, (editor) => {
    // Plugin logic
});

// ❌ WRONG - Not handling Promise properly
export default getTinyMCE().then((tinyMCE) => {
    // This doesn't return the plugin array correctly
    tinyMCE.PluginManager.add(`${component}/plugin`, (editor) => {
        return {};
    });
});
```

**✅ CORRECT - Proper Async Module Pattern:**
```javascript
// ✅ CORRECT - Proper async loading with Promise.all
import {getTinyMCE} from 'editor_tiny/loader';
import {getPluginMetadata} from 'editor_tiny/utils';

import {component, pluginName} from './common';
import * as Commands from './commands';
import * as Options from './options';

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
        Options.register(editor);
        setupCommands(editor);
        return pluginMetadata;
    });

    // CRITICAL: Must resolve with plugin array
    resolve([`${component}/plugin`]);
});
```

### 🚫 Button and Command Registration Anti-Patterns

**NEVER DO THIS - Inconsistent Naming or Missing Registration:**
```javascript
// ❌ WRONG - Inconsistent button naming
export const getSetup = async() => {
    return (editor) => {
        // Button name doesn't match component pattern
        editor.ui.registry.addButton('myButton', {  // Should be tiny_yourplugin_buttonname
            onAction: () => doSomething(editor),
        });
    };
};

// ❌ WRONG - Missing icon or tooltip
editor.ui.registry.addButton(buttonName, {
    // No icon or tooltip - poor UX
    onAction: () => doSomething(editor),
});

// ❌ WRONG - Unsafe action handlers
editor.ui.registry.addButton(buttonName, {
    icon: 'bold',
    onAction: () => {
        // No error handling - can crash editor
        const content = editor.getContent();
        const result = dangerousOperation(content);  // Might throw
        editor.setContent(result);
    },
});
```

**✅ CORRECT - Proper Button Registration:**
```javascript
// ✅ CORRECT - Consistent naming and proper setup
import {getButtonImage} from 'editor_tiny/utils';
import {get_string as getString} from 'core/str';
import {exception as displayException} from 'core/notification';

import {component, buttonName, icon} from './common';

export const getSetup = async() => {
    const [
        buttonText,
        buttonImage,
    ] = await Promise.all([
        getString('buttonlabel', component),
        getButtonImage(icon, component),
    ]);

    return (editor) => {
        editor.ui.registry.addButton(buttonName, {
            icon,
            tooltip: buttonText,
            onAction: async() => {
                try {
                    await handleButtonAction(editor);
                } catch (error) {
                    displayException(error);
                }
            },
        });
    };
};

const handleButtonAction = async(editor) => {
    // Safe action implementation with proper error handling
    const content = editor.getContent();

    if (!content.trim()) {
        // Handle empty content case
        return;
    }

    // Process content safely
    const result = await processContent(content);
    editor.insertContent(result);
};
```

### 🚫 Configuration and Data Handling Anti-Patterns

**NEVER DO THIS - Unsafe Data Access or Circular Dependencies:**
```javascript
// ❌ WRONG - Direct access to undefined properties
export const getCurrentConfig = (editor) => {
    // Will throw if dataset doesn't exist
    return editor.plugins[component].dataset.config;
};

// ❌ WRONG - Circular import dependencies
// In configuration.js
import {handleAction} from './commands';  // Creates circular dependency

// In commands.js
import {getConfig} from './configuration';  // Circular!

// ❌ WRONG - No validation of configuration data
export const getSetting = (editor, settingName) => {
    const config = getCurrentConfig(editor);
    return config.settings[settingName];  // Might be undefined
};
```

**✅ CORRECT - Safe Configuration Access:**
```javascript
// ✅ CORRECT - Safe property access with fallbacks
import {component} from './common';

const getDataset = (tinymce, editorId) => {
    const editor = tinymce.get(editorId);
    if (!editor?.plugins?.[component]?.dataset) {
        return {};
    }
    return editor.plugins[component].dataset;
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
    const settings = config.settings || {};
    return settings.hasOwnProperty(settingName) ? settings[settingName] : defaultValue;
};

export const hasPermission = (editor, permissionName) => {
    const permissions = getPermissions(editor);
    return Boolean(permissions[permissionName]);
};
```

### 🚫 Modal and Dialog Anti-Patterns

**NEVER DO THIS - Poor Modal Management:**
```javascript
// ❌ WRONG - Not cleaning up modals
const openDialog = async(editor) => {
    const modal = await Modal.create({
        title: 'Dialog',
        body: 'Content',
    });

    modal.show();
    // Missing modal.destroy() - memory leak!
};

// ❌ WRONG - No form validation
modalObject.getRoot().on('click', '[data-action="save"]', (e) => {
    const formData = getFormData(modalObject);
    // No validation - might insert invalid data
    editor.insertContent(formData.content);
    modalObject.destroy();
});

// ❌ WRONG - Synchronous modal creation
const openDialog = (editor) => {
    // This blocks the UI thread
    const modal = Modal.create({
        title: 'Dialog',
        body: Templates.render('template', {}),  // Synchronous render
    });
};
```

**✅ CORRECT - Proper Modal Management:**
```javascript
// ✅ CORRECT - Async modal with proper cleanup and validation
import {Modal} from 'core/modal';
import {Templates} from 'core/templates';
import {get_string as getString} from 'core/str';
import {exception as displayException} from 'core/notification';

const openDialog = async(editor) => {
    try {
        const [title, body] = await Promise.all([
            getString('dialogtitle', component),
            Templates.render('tiny_yourplugin/dialog', {
                contextid: getContextId(editor),
            }),
        ]);

        const modalObject = await Modal.create({
            type: Modal.types.DEFAULT,
            title,
            body,
            large: true,
        });

        modalObject.show();

        // Handle save action with validation
        modalObject.getRoot().on('click', '[data-action="save"]', async(e) => {
            e.preventDefault();

            try {
                const formData = getFormData(modalObject);

                // Validate form data
                const validation = validateFormData(formData);
                if (!validation.valid) {
                    displayValidationErrors(validation.errors);
                    return;
                }

                // Process and insert content
                const content = await processFormData(formData);
                editor.insertContent(content);
                modalObject.destroy();

            } catch (error) {
                displayException(error);
            }
        });

        // Handle cancel action
        modalObject.getRoot().on('click', '[data-action="cancel"]', () => {
            modalObject.destroy();
        });

        // Cleanup on modal destruction
        modalObject.getRoot().on(Modal.events.hidden, () => {
            modalObject.destroy();
        });

    } catch (error) {
        displayException(error);
    }
};

const validateFormData = (formData) => {
    const errors = [];

    if (!formData.title?.trim()) {
        errors.push('Title is required');
    }

    if (!formData.content?.trim()) {
        errors.push('Content is required');
    }

    return {
        valid: errors.length === 0,
        errors,
    };
};
```

### 🚫 External API Integration Anti-Patterns

**NEVER DO THIS - Unsafe External Calls:**
```javascript
// ❌ WRONG - No error handling or loading states
const processWithAPI = async(editor, content) => {
    // No loading indicator
    const response = await fetch('/api/process', {
        method: 'POST',
        body: JSON.stringify({content}),
    });

    // No error checking
    const result = await response.json();
    editor.insertContent(result.html);  // Might be undefined
};

// ❌ WRONG - Blocking the editor during API calls
const handleAPICall = (editor) => {
    // This locks up the editor UI
    const result = Ajax.call([{
        methodname: 'process_content',
        args: {content: editor.getContent()},
    }])[0];  // Synchronous call

    editor.insertContent(result.html);
};

// ❌ WRONG - No timeout or retry logic
const callExternalService = async(data) => {
    // No timeout - might hang forever
    const response = await fetch('https://external-api.com/process', {
        method: 'POST',
        body: JSON.stringify(data),
    });

    return response.json();
};
```

**✅ CORRECT - Robust External API Integration:**
```javascript
// ✅ CORRECT - Proper async API integration with error handling
import {Ajax} from 'core/ajax';
import {Notification} from 'core/notification';
import {get_string as getString} from 'core/str';

const processWithAPI = async(editor, content) => {
    let progressState = false;

    try {
        // Show loading state
        editor.setProgressState(true);
        progressState = true;

        // Validate input
        if (!content?.trim()) {
            throw new Error(await getString('error:emptycontent', component));
        }

        // Make API call with timeout
        const response = await Promise.race([
            Ajax.call([{
                methodname: 'tiny_yourplugin_process_content',
                args: {
                    content: content,
                    contextid: getContextId(editor),
                },
            }])[0],
            new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Request timeout')), 30000)
            ),
        ]);

        // Validate response
        if (!response?.html) {
            throw new Error(await getString('error:invalidresponse', component));
        }

        // Insert content
        editor.insertContent(response.html);

        // Show success message
        Notification.addNotification({
            message: await getString('success:contentprocessed', component),
            type: 'success',
        });

    } catch (error) {
        // Handle different error types
        let errorMessage;

        if (error.message === 'Request timeout') {
            errorMessage = await getString('error:timeout', component);
        } else if (error.message.includes('capability')) {
            errorMessage = await getString('error:nopermission', component);
        } else {
            errorMessage = error.message || await getString('error:unknown', component);
        }

        Notification.addNotification({
            message: errorMessage,
            type: 'error',
        });

        // Log error for debugging
        window.console?.error('TinyMCE plugin API error:', error);

    } finally {
        // Always hide loading state
        if (progressState) {
            editor.setProgressState(false);
        }
    }
};

// ✅ CORRECT - External service with retry logic
const callExternalService = async(data, retries = 3) => {
    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout

            const response = await fetch('https://external-api.com/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
                signal: controller.signal,
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();

        } catch (error) {
            if (attempt === retries) {
                throw error; // Final attempt failed
            }

            // Wait before retrying (exponential backoff)
            await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
        }
    }
};
```

### 🚫 Performance Anti-Patterns

**NEVER DO THIS - Performance-Killing Operations:**
```javascript
// ❌ WRONG - Heavy operations on every keystroke
editor.on('keyup', () => {
    // This runs on EVERY keystroke - terrible performance!
    const content = editor.getContent();
    const processedContent = heavyProcessingOperation(content);
    updatePreview(processedContent);
});

// ❌ WRONG - Memory leaks from unremoved listeners
const setupPlugin = (editor) => {
    // Event listeners never removed - memory leak!
    document.addEventListener('click', handleGlobalClick);
    window.addEventListener('resize', handleResize);

    // No cleanup when editor is destroyed
};

// ❌ WRONG - Blocking DOM operations
const insertContent = (editor, data) => {
    // This blocks the UI thread
    for (let i = 0; i < data.items.length; i++) {
        const html = generateComplexHTML(data.items[i]);
        editor.insertContent(html);
        // Triggers reflow on each insertion
    }
};
```

**✅ CORRECT - Performance-Optimized Operations:**
```javascript
// ✅ CORRECT - Debounced operations and efficient event handling
import {debounce} from 'core/utils';

const setupPerformantListeners = (editor) => {
    // Debounce expensive operations
    const debouncedUpdate = debounce(async() => {
        const content = editor.getContent();
        const processedContent = await processContentAsync(content);
        updatePreview(processedContent);
    }, 500); // Wait 500ms after last keystroke

    editor.on('keyup', debouncedUpdate);

    // Use passive listeners where possible
    const handleScroll = (e) => {
        // Lightweight scroll handling
        updateScrollIndicator(e);
    };

    editor.getContainer().addEventListener('scroll', handleScroll, {passive: true});

    // Cleanup on destroy
    editor.on('remove', () => {
        // Remove global listeners
        document.removeEventListener('click', handleGlobalClick);
        window.removeEventListener('resize', handleResize);
        editor.getContainer().removeEventListener('scroll', handleScroll);

        // Cancel any pending operations
        debouncedUpdate.cancel();
    });
};

// ✅ CORRECT - Batch DOM operations
const insertContent = async(editor, data) => {
    if (!data.items?.length) {
        return;
    }

    // Process items in batches to avoid blocking
    const batchSize = 10;
    const htmlParts = [];

    for (let i = 0; i < data.items.length; i += batchSize) {
        const batch = data.items.slice(i, i + batchSize);

        // Process batch
        const batchHtml = batch.map(item => generateHTML(item)).join('');
        htmlParts.push(batchHtml);

        // Yield control to browser if processing large dataset
        if (i > 0 && i % 50 === 0) {
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    }

    // Single DOM insertion to avoid multiple reflows
    editor.insertContent(htmlParts.join(''));
};

// ✅ CORRECT - Efficient content processing
const processContentAsync = async(content) => {
    return new Promise((resolve) => {
        // Use setTimeout to avoid blocking UI
        setTimeout(() => {
            const result = heavyProcessingOperation(content);
            resolve(result);
        }, 0);
    });
};
```

## 🎯 PROVEN PATTERNS FOR COMMON PLUGIN TYPES

### Simple Button Plugin Pattern
```javascript
// Minimal button plugin structure
export default new Promise(async(resolve) => {
    const [tinyMCE, buttonText] = await Promise.all([
        getTinyMCE(),
        getString('buttonlabel', component),
    ]);

    tinyMCE.PluginManager.add(`${component}/plugin`, (editor) => {
        editor.ui.registry.addButton(buttonName, {
            icon: 'bold',
            tooltip: buttonText,
            onAction: () => editor.execCommand('mceToggleFormat', false, 'bold'),
        });

        return getPluginMetadata(component, pluginName);
    });

    resolve([`${component}/plugin`]);
});
```

### Dialog-based Plugin Pattern
```javascript
// Plugin with modal dialog
const openDialog = async(editor) => {
    const modalObject = await Modal.create({
        type: Modal.types.DEFAULT,
        title: await getString('dialogtitle', component),
        body: await Templates.render('tiny_yourplugin/dialog', getDialogContext(editor)),
        large: true,
    });

    modalObject.show();
    setupDialogHandlers(modalObject, editor);
};

export const getSetup = async() => {
    const buttonText = await getString('buttonlabel', component);

    return (editor) => {
        editor.ui.registry.addButton(buttonName, {
            icon: 'edit',
            tooltip: buttonText,
            onAction: () => openDialog(editor),
        });
    };
};
```

### Configuration Plugin Pattern
```javascript
// Plugin that only provides configuration
class plugininfo extends plugin implements plugin_with_configuration {
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null
    ): array {
        return [
            'setting1' => get_config('tiny_yourplugin', 'setting1'),
            'setting2' => get_config('tiny_yourplugin', 'setting2'),
            'permissions' => [
                'canuse' => has_capability('tiny/yourplugin:use', $context),
            ],
        ];
    }
}
```

### External Service Integration Pattern
```javascript
// Plugin with external API calls
const handleExternalAction = async(editor) => {
    try {
        editor.setProgressState(true);

        const content = editor.getContent();
        const response = await Ajax.call([{
            methodname: 'tiny_yourplugin_process',
            args: {
                content: content,
                contextid: getContextId(editor),
            },
        }])[0];

        if (response.success) {
            editor.insertContent(response.html);
        } else {
            throw new Error(response.error);
        }

    } catch (error) {
        Notification.exception(error);
    } finally {
        editor.setProgressState(false);
    }
};
```

## 🔒 SECURITY CHECKLIST

- [ ] ✅ All user input is properly sanitized before insertion
- [ ] ✅ Capability checks are performed on both PHP and JavaScript sides
- [ ] ✅ External API calls include proper authentication
- [ ] ✅ Content insertion uses safe TinyMCE methods
- [ ] ✅ File uploads respect Moodle's file API security
- [ ] ✅ Cross-site scripting (XSS) prevention is in place
- [ ] ✅ JSON data is properly encoded for JavaScript context
- [ ] ✅ Error messages don't expose sensitive information

## 🚀 PERFORMANCE CHECKLIST

- [ ] ✅ AMD modules load asynchronously
- [ ] ✅ Heavy operations are debounced or throttled
- [ ] ✅ Event listeners are properly cleaned up
- [ ] ✅ DOM operations are batched to avoid reflows
- [ ] ✅ External API calls don't block the UI
- [ ] ✅ Memory leaks are prevented (modal cleanup, etc.)
- [ ] ✅ Large datasets are processed in chunks
- [ ] ✅ Plugin doesn't interfere with editor responsiveness

## 🧪 TESTING PATTERNS

### JavaScript Unit Test Pattern
```javascript
// AMD module for testing
define(['tiny_yourplugin/plugin'], function(Plugin) {
    QUnit.module('tiny_yourplugin');

    QUnit.test('Plugin loads correctly', function(assert) {
        assert.expect(1);

        Plugin.then(function(plugins) {
            assert.equal(plugins.length, 1, 'Plugin array contains one plugin');
        });
    });
});
```

### Behat Test Pattern
```gherkin
# features/plugin_functionality.feature
@javascript @editor_tiny @tiny_yourplugin
Feature: TinyMCE Your Plugin functionality
  In order to use plugin features
  As a user
  I need to be able to access plugin functionality

  Background:
    Given I log in as "admin"
    And I navigate to "Appearance > TinyMCE editor > General settings" in site administration

  Scenario: Plugin button appears in toolbar
    When I set the field "Toolbar config" to "bold italic | tiny_yourplugin_yourbutton"
    And I press "Save changes"
    And I navigate to course "Course 1"
    And I add a "Page" to section "1"
    Then I should see "Your Button" button in the TinyMCE editor toolbar
```

### PHP Unit Test Pattern
```php
class tiny_yourplugin_test extends advanced_testcase {
    public function test_plugin_is_enabled() {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $this->setUser($user);

        // Test capability requirement
        $this->assertFalse(plugininfo::is_enabled($context, [], []));

        // Assign capability and test again
        $role = $this->getDataGenerator()->create_role();
        assign_capability('tiny/yourplugin:use', CAP_ALLOW, $role, $context);
        role_assign($role, $user->id, $context);

        $this->assertTrue(plugininfo::is_enabled($context, [], []));
    }
}
```