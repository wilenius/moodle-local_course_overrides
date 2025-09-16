### Moodle Report Plugin Structure

Every Moodle report plugin MUST follow this structure:
```
report_[reportname]/
├── index.php                      # Main report page (REQUIRED)
├── version.php                    # Version and dependencies (REQUIRED)
├── lang/                          # Language strings (REQUIRED)
│   └── en/
│       └── report_[reportname].php
├── lib.php                        # Navigation and hook functions (REQUIRED)
├── db/                            # Database and capabilities
│   ├── access.php                 # Capabilities definition
│   ├── install.xml                # Database schema (optional)
│   └── upgrade.php                # Database upgrades (optional)
├── classes/                       # Autoloaded classes (PSR-4)
│   ├── privacy/
│   │   └── provider.php           # Privacy API implementation
│   ├── external/                  # External API classes (optional)
│   ├── form/                      # Form classes (optional)
│   ├── task/                      # Background tasks (optional)
│   └── output/                    # Renderable classes (optional)
├── locallib.php                   # Local helper functions (optional)
├── settings.php                   # Admin settings (optional)
├── user.php                       # User-specific report page (optional)
├── amd/src/                       # AMD modules (optional)
├── templates/                     # Mustache templates (optional)
├── styles.css                     # Report-specific styles (optional)
├── tests/                         # Unit tests
│   ├── behat/                     # Behat tests
│   └── [test_files].php          # Unit tests
└── cli/                           # Command line scripts (optional)
```

### Key Files to Examine in Moodle Core

When developing, examine these reference implementations in the Moodle core:

1. **Base report architecture:**
   - `../moodle/lib/classes/plugininfo/report.php` - Plugin info class
   - `../moodle/lib/classes/report_helper.php` - Report helper functions
   - `../moodle/report/view.php` - Site-level report listing

2. **Simple report patterns:**
   - `../moodle/report/outline/` - Course activity outline report
   - `../moodle/report/performance/` - System performance report
   - `../moodle/report/configlog/` - Configuration changes log

3. **Interactive report patterns:**
   - `../moodle/report/participation/` - User participation analysis
   - `../moodle/report/log/` - Activity logs with filtering
   - `../moodle/report/completion/` - Course completion tracking

4. **Advanced report patterns:**
   - `../moodle/report/stats/` - Site statistics with charts
   - `../moodle/report/insights/` - Analytics insights report
   - `../moodle/report/competency/` - Competency progress tracking

5. **User-specific patterns:**
   - `../moodle/report/outline/user.php` - Individual user reports
   - `../moodle/report/log/user.php` - User-specific activity logs

## Development Guidelines

### 1. Main Report Page (`index.php`)

The main report page structure:
```php
<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Get parameters
$id = optional_param('id', 0, PARAM_INT);        // Course ID (for course reports)
$format = optional_param('format', 'html', PARAM_ALPHA); // Export format

// Security and context setup
if ($id) {
    // Course-level report
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('report/yourreport:view', $context);

    $PAGE->set_course($course);
    $PAGE->set_context($context);
    $PAGE->set_url('/report/yourreport/index.php', array('id' => $id));
    $PAGE->set_title(get_string('pluginname', 'report_yourreport'));
    $PAGE->set_heading($course->fullname);

} else {
    // Site-level report
    require_once($CFG->libdir.'/adminlib.php');
    admin_externalpage_setup('reportyourreport');
    $context = context_system::instance();
}

// Report-specific setup
$PAGE->set_pagelayout('report');

// Add report selector (for course reports)
if ($id) {
    \core\report_helper::print_report_selector('yourreport');
}

// Generate report data
$reportdata = generate_report_data($context, $id);

// Handle different output formats
if ($format === 'csv') {
    export_csv_report($reportdata);
    exit;
} else if ($format === 'excel') {
    export_excel_report($reportdata);
    exit;
}

// HTML output
echo $OUTPUT->header();

if (empty($reportdata)) {
    echo $OUTPUT->notification(get_string('noreportdata', 'report_yourreport'));
} else {
    // Display filters/options form if needed
    $filterform = new \report_yourreport\form\filter_form();
    $filterform->display();

    // Display report content
    echo $OUTPUT->render_from_template('report_yourreport/report_table', [
        'data' => $reportdata,
        'canexport' => has_capability('report/yourreport:export', $context),
    ]);
}

echo $OUTPUT->footer();
```

### 2. Version File (`version.php`)

Must define:
```php
$plugin->component = 'report_[reportname]';
$plugin->version = 2024010100;  // YYYYMMDDXX format
$plugin->requires = 2025040800; // Moodle 5.0 version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0';

// Optional: Dependencies on other plugins
$plugin->dependencies = [
    'mod_quiz' => 2025040800,  // If report analyzes quiz data
];
```

### 3. Navigation and Hooks (`lib.php`)

Required navigation functions:
```php
/**
 * Add navigation links for course-level reports
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param stdClass $context The course context
 */
function report_yourreport_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/yourreport:view', $context)) {
        $url = new moodle_url('/report/yourreport/index.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'report_yourreport'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Add navigation links for user-specific reports
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user The user object
 * @param stdClass $course The course object
 */
function report_yourreport_extend_navigation_user($navigation, $user, $course) {
    if (report_yourreport_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/yourreport/user.php', [
            'id' => $user->id,
            'course' => $course->id
        ]);
        $navigation->add(
            get_string('userreport', 'report_yourreport'),
            $url
        );
    }
}

/**
 * Check if current user can access user-specific report
 *
 * @param stdClass $user The user whose report is being viewed
 * @param stdClass $course The course context
 * @return bool
 */
function report_yourreport_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    // Users can view their own reports if course allows
    if ($user->id == $USER->id) {
        if ($course->showreports && (is_viewing($coursecontext, $USER) || is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }

    // Check if user can view others' reports
    if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports && (is_viewing($coursecontext, $user) || is_enrolled($coursecontext, $user))) {
            return true;
        }
    }

    // Check group access
    if (!groups_user_groups_visible($course, $user->id)) {
        return false;
    }

    // Check report-specific capability
    return has_capability('report/yourreport:viewuserreport', $coursecontext);
}

/**
 * Add report to user profile page
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user User object
 * @param bool $iscurrentuser Is current user
 * @param stdClass $course Course object
 */
function report_yourreport_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course
) {
    if (empty($course)) {
        $course = get_fast_modinfo(SITEID)->get_course();
    }

    if (report_yourreport_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/yourreport/user.php', [
            'id' => $user->id,
            'course' => $course->id
        ]);
        $node = new \core_user\output\myprofile\node(
            'reports',
            'yourreport',
            get_string('userreport', 'report_yourreport'),
            null,
            $url
        );
        $tree->add_node($node);
    }
}

/**
 * Define page types for this report
 *
 * @param string $pagetype Current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_yourreport_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return [
        '*' => get_string('page-x', 'pagetype'),
        'report-*' => get_string('page-report-x', 'pagetype'),
        'report-yourreport-*' => get_string('page-report-yourreport-x', 'report_yourreport'),
        'report-yourreport-index' => get_string('page-report-yourreport-index', 'report_yourreport'),
        'report-yourreport-user' => get_string('page-report-yourreport-user', 'report_yourreport'),
    ];
}

/**
 * Check if report supports given log store
 *
 * @param string $instance Log store instance
 * @return bool
 */
function report_yourreport_supports_logstore($instance) {
    if ($instance instanceof \core\log\sql_internal_table_reader) {
        return true;
    }
    return false;
}
```

### 4. Language Strings (`lang/en/report_[reportname].php`)

Minimum required:
```php
$string['pluginname'] = 'Your Report Name';

// Capabilities
$string['yourreport:view'] = 'View report';
$string['yourreport:viewuserreport'] = 'View user reports';
$string['yourreport:export'] = 'Export report data';

// Report content
$string['reporttitle'] = 'Report Title';
$string['noreportdata'] = 'No data available for this report';
$string['generatedtime'] = 'Generated: {$a}';

// Filters and options
$string['filteroptions'] = 'Filter options';
$string['daterange'] = 'Date range';
$string['fromdate'] = 'From date';
$string['todate'] = 'To date';
$string['showfilters'] = 'Show filters';
$string['applyfilters'] = 'Apply filters';

// Export options
$string['exportcsv'] = 'Export as CSV';
$string['exportexcel'] = 'Export as Excel';

// User reports
$string['userreport'] = 'User report';
$string['selectuser'] = 'Select user';

// Privacy
$string['privacy:metadata'] = 'The [Report Name] plugin does not store any personal data.';

// Page types
$string['page-report-yourreport-x'] = 'Any report page';
$string['page-report-yourreport-index'] = 'Main report page';
$string['page-report-yourreport-user'] = 'User report page';

// Errors
$string['nocapability'] = 'You do not have permission to view this report';
$string['invaliduser'] = 'Invalid user specified';
$string['invalidcourse'] = 'Invalid course specified';
```

### 5. Capabilities (`db/access.php`)

Define report capabilities:
```php
$capabilities = [
    'report/yourreport:view' => [
        'riskbitmask' => RISK_PERSONAL,  // If report shows personal data
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    'report/yourreport:viewuserreport' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'report/yourreport:view',
    ],

    'report/yourreport:export' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
```

### 6. Settings (Optional - `settings.php`)

For site-level reports:
```php
defined('MOODLE_INTERNAL') || die;

// Add report to admin menu
$ADMIN->add('reports', new admin_externalpage(
    'reportyourreport',
    get_string('pluginname', 'report_yourreport'),
    $CFG->wwwroot . '/report/yourreport/index.php',
    'report/yourreport:view'
));

// Plugin-specific settings
if ($hassiteconfig) {
    $settings = new admin_settingpage('report_yourreport_settings',
        get_string('settings', 'report_yourreport'));

    $settings->add(new admin_setting_configcheckbox(
        'report_yourreport/enabled',
        get_string('enabled', 'report_yourreport'),
        get_string('enabled_desc', 'report_yourreport'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'report_yourreport/maxrecords',
        get_string('maxrecords', 'report_yourreport'),
        get_string('maxrecords_desc', 'report_yourreport'),
        1000,
        PARAM_INT
    ));

    $ADMIN->add('reports', $settings);
}
```

## Report Types and Patterns

### 🎯 Course Activity Reports
- **Purpose**: Analyze user activity within courses
- **Examples**: Outline, participation, completion tracking
- **Key Features**: Course context, user filtering, activity analysis
- **Implementation**: Focus on course data, student progress, activity logs

```php
protected function generate_course_activity_data($courseid, $filters = []) {
    global $DB;

    $course = get_course($courseid);
    $context = context_course::instance($courseid);

    // Get course modules
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->get_cms();

    // Get enrolled users
    $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);

    $reportdata = [];
    foreach ($users as $user) {
        $userdata = [
            'user' => $user,
            'activities' => [],
        ];

        foreach ($cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $activity = [
                'cm' => $cm,
                'viewed' => $this->get_user_activity_status($user->id, $cm->id),
                'completed' => $this->get_user_completion_status($user->id, $cm->id),
            ];

            $userdata['activities'][] = $activity;
        }

        $reportdata[] = $userdata;
    }

    return $reportdata;
}
```

### 🎯 System-Level Reports
- **Purpose**: Monitor system performance, configuration, security
- **Examples**: Performance metrics, configuration changes, user sessions
- **Key Features**: System context, administrative data, monitoring
- **Implementation**: Focus on system tables, configuration, logs

```php
protected function generate_system_performance_data() {
    global $DB, $CFG;

    $data = [];

    // Database performance
    $data['database'] = [
        'type' => $CFG->dbtype,
        'queries' => $DB->perf_get_reads() + $DB->perf_get_writes(),
        'time' => $DB->perf_get_time(),
    ];

    // Memory usage
    $data['memory'] = [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit'),
    ];

    // Cache performance
    if ($cache = cache::make('core', 'config')) {
        $data['cache'] = [
            'hits' => $cache->get_stats()['hits'] ?? 0,
            'misses' => $cache->get_stats()['misses'] ?? 0,
        ];
    }

    // Server info
    $data['server'] = [
        'php_version' => PHP_VERSION,
        'web_server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'os' => PHP_OS,
    ];

    return $data;
}
```

### 🎯 User-Specific Reports
- **Purpose**: Individual user progress and activity tracking
- **Examples**: User activity logs, progress reports, personal analytics
- **Key Features**: Personal context, privacy-aware, detailed tracking
- **Implementation**: Focus on individual user data, respect privacy settings

```php
protected function generate_user_report_data($userid, $courseid = 0) {
    global $DB;

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    if ($courseid) {
        $course = get_course($courseid);
        $context = context_course::instance($courseid);
    } else {
        $context = context_system::instance();
    }

    // Check privacy settings
    if (!$this->can_view_user_data($user, $context)) {
        throw new required_capability_exception($context, 'report/yourreport:viewuserreport', 'nopermissions', '');
    }

    $data = [
        'user' => $user,
        'courses' => [],
        'activities' => [],
        'summary' => [],
    ];

    if ($courseid) {
        // Course-specific user data
        $data['courses'][] = $this->get_user_course_data($userid, $courseid);
    } else {
        // All courses for user
        $enrolledcourses = enrol_get_users_courses($userid, true);
        foreach ($enrolledcourses as $course) {
            $data['courses'][] = $this->get_user_course_data($userid, $course->id);
        }
    }

    return $data;
}
```

### 🎯 Analytics and Insights Reports
- **Purpose**: Data analysis, trends, predictions
- **Examples**: Learning analytics, performance trends, risk indicators
- **Key Features**: Statistical analysis, visualizations, predictive data
- **Implementation**: Complex queries, data aggregation, chart integration

```php
protected function generate_analytics_data($context, $timeframe = 30) {
    global $DB;

    $data = [
        'trends' => [],
        'statistics' => [],
        'insights' => [],
    ];

    // Activity trends over time
    $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date,
                   COUNT(*) as activity_count
            FROM {logstore_standard_log}
            WHERE contextid = ? AND timecreated > ?
            GROUP BY DATE(FROM_UNIXTIME(timecreated))
            ORDER BY date";

    $timestart = time() - ($timeframe * 24 * 60 * 60);
    $trends = $DB->get_records_sql($sql, [$context->id, $timestart]);

    foreach ($trends as $trend) {
        $data['trends'][] = [
            'date' => $trend->date,
            'count' => $trend->activity_count,
        ];
    }

    // Statistical summaries
    $data['statistics'] = [
        'total_users' => $this->count_active_users($context, $timestart),
        'total_activities' => array_sum(array_column($data['trends'], 'count')),
        'average_daily' => count($data['trends']) > 0
            ? array_sum(array_column($data['trends'], 'count')) / count($data['trends'])
            : 0,
    ];

    return $data;
}
```

### 🎯 Export and Download Reports
- **Purpose**: Allow users to download report data
- **Examples**: CSV exports, Excel files, PDF reports
- **Key Features**: Multiple formats, large dataset handling
- **Implementation**: Streaming output, memory management, proper headers

```php
protected function export_csv_report($data, $filename = 'report') {
    $filename = clean_filename($filename . '_' . date('Y-m-d') . '.csv');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Write BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Write headers
    if (!empty($data)) {
        fputcsv($output, array_keys((array)$data[0]));

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, (array)$row);
        }
    }

    fclose($output);
}

protected function export_excel_report($data, $filename = 'report') {
    global $CFG;
    require_once($CFG->libdir . '/excellib.class.php');

    $filename = clean_filename($filename . '_' . date('Y-m-d') . '.xlsx');

    $workbook = new MoodleExcelWorkbook($filename);
    $worksheet = $workbook->add_worksheet('Report Data');

    $row = 0;

    // Write headers
    if (!empty($data)) {
        $headers = array_keys((array)$data[0]);
        $col = 0;
        foreach ($headers as $header) {
            $worksheet->write_string($row, $col, $header);
            $col++;
        }
        $row++;

        // Write data
        foreach ($data as $datarow) {
            $col = 0;
            foreach ((array)$datarow as $value) {
                if (is_numeric($value)) {
                    $worksheet->write_number($row, $col, $value);
                } else {
                    $worksheet->write_string($row, $col, $value);
                }
                $col++;
            }
            $row++;
        }
    }

    $workbook->close();
}
```

## Testing Checklist

#### Claude Code Responsibilities (Can Verify During Development)

These items can be checked by Claude Code during development:

1. **[AUTOMATED]** ✅ PHP syntax is valid (no parse errors)
   - Claude Code can verify using PHP linter if available

2. **[AUTOMATED]** ✅ Required files exist with correct naming
   - `index.php`, `lib.php`, `version.php`, language files

3. **[AUTOMATED]** ✅ File structure follows Moodle conventions
   - Correct directory structure and file placement

4. **[AUTOMATED]** ✅ Code follows Moodle coding standards
   - Can check basic standards (indentation, naming)

5. **[AUTOMATED]** ✅ Required functions are implemented
   - Navigation extension functions in lib.php

6. **[AUTOMATED]** ✅ Language string keys match expected patterns
   - Verify 'pluginname' and capability strings exist

7. **[AUTOMATED]** ✅ Version file has all required fields
   - Component name, version number, requirements

8. **[AUTOMATED]** ✅ GPL license headers are present
   - Check all PHP files have proper headers

9. **[AUTOMATED]** ✅ No obvious security issues
   - Check for raw SQL, unescaped output, capability checks

10. **[AUTOMATED]** ✅ Capability definitions are complete
    - All used capabilities are defined in db/access.php

#### Manual Testing Required

These must be tested by installing in Moodle:

11. **[MANUAL]** ⚠️ Plugin installs without errors
12. **[MANUAL]** ⚠️ Report appears in course/site navigation
13. **[MANUAL]** ⚠️ Report page loads without errors
14. **[MANUAL]** ⚠️ Data is displayed correctly
15. **[MANUAL]** ⚠️ Filtering and search work (if applicable)
16. **[MANUAL]** ⚠️ Export functions work correctly (if applicable)
17. **[MANUAL]** ⚠️ User-specific reports work (if applicable)
18. **[MANUAL]** ⚠️ Capability restrictions work correctly
19. **[MANUAL]** ⚠️ No PHP errors in logs during operation
20. **[MANUAL]** ⚠️ Performance is acceptable with large datasets
21. **[MANUAL]** ⚠️ Report selector works (for course reports)
22. **[MANUAL]** ⚠️ Mobile view is functional
23. **[MANUAL]** ⚠️ Accessibility requirements are met

## Common Patterns to Reference

### Simple Course Report
Look at: `../moodle/report/outline/index.php`

### User-Specific Report
Look at: `../moodle/report/outline/user.php`

### Interactive Report with Filtering
Look at: `../moodle/report/participation/index.php`

### System-Level Report
Look at: `../moodle/report/performance/index.php`

### Log-Based Report
Look at: `../moodle/report/log/index.php`

### Report with Export Options
Look at: `../moodle/report/completion/index.php`

## Important Notes for Claude Code

1. **Never modify files in the Moodle core repository** - only read them for reference
2. **Always check existing Moodle reports** for patterns before implementing
3. **Test what you can programmatically** - Use available tools to validate syntax
4. **Document what needs manual testing** - Reports require real data to test properly
5. **Follow the naming convention strictly** - Function and file names must match patterns
6. **Check capability definitions** - All used capabilities must be defined
7. **Consider performance implications** - Reports can process large datasets
8. **Privacy is critical** - Reports often show personal data
9. **Mobile compatibility** - Reports should work on mobile devices
10. **Accessibility matters** - Screen readers and keyboard navigation

## Resources

- Moodle 5.0 Developer Documentation: https://moodledev.io/
- Report Development: https://moodledev.io/docs/apis/plugintypes/report
- Plugin Development: https://moodledev.io/docs/apis/plugintypes
- Navigation API: https://moodledev.io/docs/apis/core/navigation
- Privacy API: https://moodledev.io/docs/apis/core/privacy
- Moodle Tracker: https://tracker.moodle.org/

## Questions to Ask Before Starting

1. What type of report is this (course activity, system metrics, user tracking)?
2. Who should have access to view this report?
3. What data sources will the report use (logs, database tables, external APIs)?
4. Should it support filtering or search functionality?
5. Does it need export capabilities (CSV, Excel, PDF)?
6. Should it have user-specific views?
7. Does it need to work at both course and site levels?
8. Are there performance considerations with large datasets?
9. What visualizations or charts are needed?
10. Are there privacy or compliance requirements?