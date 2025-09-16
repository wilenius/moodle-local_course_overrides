# Report Plugin Development Patterns and Anti-Patterns

## ⚠️ CRITICAL MOODLE 5.x REPORT PATTERNS & BUG PREVENTION

### 🚫 Navigation and Hook Function Anti-Patterns

**NEVER DO THIS - Missing or Incorrect Navigation Functions:**
```php
// ❌ WRONG - Missing required navigation function
// report_yourreport/lib.php

// Missing function means report won't appear in navigation!
// function report_yourreport_extend_navigation_course() is REQUIRED

// ❌ WRONG - Wrong function signature
function report_yourreport_extend_navigation_course($navigation, $course) {
    // Missing $context parameter - function won't be called correctly
}

// ❌ WRONG - No capability checking
function report_yourreport_extend_navigation_course($navigation, $course, $context) {
    // Anyone can see the report link - security risk!
    $url = new moodle_url('/report/yourreport/index.php', ['id' => $course->id]);
    $navigation->add(get_string('pluginname', 'report_yourreport'), $url);
}
```

**✅ CORRECT - Proper Navigation Implementation:**
```php
// ✅ CORRECT - Complete navigation function with capability checking
function report_yourreport_extend_navigation_course($navigation, $course, $context) {
    // Always check capabilities before adding navigation
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

// ✅ CORRECT - User navigation with proper access checking
function report_yourreport_extend_navigation_user($navigation, $user, $course) {
    if (report_yourreport_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/yourreport/user.php', [
            'id' => $user->id,
            'course' => $course->id
        ]);
        $navigation->add(get_string('userreport', 'report_yourreport'), $url);
    }
}

// ✅ CORRECT - Comprehensive access checking function
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

    // Check capability to view others' reports
    if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports && (is_viewing($coursecontext, $user) || is_enrolled($coursecontext, $user))) {
            return true;
        }
    }

    // Check group visibility
    if (!groups_user_groups_visible($course, $user->id)) {
        return false;
    }

    // Check report-specific capability
    return has_capability('report/yourreport:viewuserreport', $coursecontext);
}
```

### 🚫 Security and Context Anti-Patterns

**NEVER DO THIS - Poor Security Implementation:**
```php
// ❌ WRONG - No security checks at all
// index.php
$id = optional_param('id', 0, PARAM_INT);
// Directly processing without any capability checks!

// ❌ WRONG - Wrong context usage
$context = context_system::instance();  // Always using system context
require_capability('report/yourreport:view', $context);  // Wrong context level

// ❌ WRONG - Missing parameter validation
$userid = $_GET['userid'];  // Direct $_GET access - injection risk!
$user = $DB->get_record('user', ['id' => $userid]);  // Could be SQL injection

// ❌ WRONG - No login requirement
// Missing require_login() call - anonymous access possible!
```

**✅ CORRECT - Proper Security Implementation:**
```php
// ✅ CORRECT - Complete security setup for course report
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Validate and sanitize parameters
$id = required_param('id', PARAM_INT);  // Use required_param for mandatory params
$userid = optional_param('userid', 0, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

// Security and context setup
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);

// Check capabilities
require_capability('report/yourreport:view', $context);

// Additional security for user-specific data
if ($userid) {
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    if (!report_yourreport_can_access_user_report($user, $course)) {
        throw new required_capability_exception($context, 'report/yourreport:viewuserreport', 'nopermissions', '');
    }
}

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url('/report/yourreport/index.php', ['id' => $id]);

// ✅ CORRECT - System-level report security
if (!$id) {
    require_once($CFG->libdir.'/adminlib.php');
    admin_externalpage_setup('reportyourreport');  // Handles admin access checking
    $context = context_system::instance();
}
```

### 🚫 Database Query Anti-Patterns

**NEVER DO THIS - Unsafe Database Operations:**
```php
// ❌ WRONG - SQL injection vulnerability
function get_user_activity($userid, $courseid) {
    global $DB;

    // Direct string concatenation - SQL injection risk!
    $sql = "SELECT * FROM {logstore_standard_log}
            WHERE userid = $userid AND courseid = $courseid";
    return $DB->get_records_sql($sql);
}

// ❌ WRONG - No performance optimization
function get_all_course_data($courseid) {
    global $DB;

    // This could return millions of records - memory exhaustion!
    $sql = "SELECT * FROM {logstore_standard_log} WHERE courseid = ?";
    return $DB->get_records_sql($sql, [$courseid]);  // No LIMIT
}

// ❌ WRONG - Missing context filtering
function get_user_grades($userid) {
    global $DB;

    // Returns grades from ALL courses - privacy violation!
    return $DB->get_records('grade_grades', ['userid' => $userid]);
}
```

**✅ CORRECT - Safe and Efficient Database Operations:**
```php
// ✅ CORRECT - Parameterized queries with context filtering
function get_user_activity($userid, $courseid, $timestart = 0, $limit = 1000) {
    global $DB;

    // Use parameterized queries
    $sql = "SELECT l.*, u.firstname, u.lastname
            FROM {logstore_standard_log} l
            JOIN {user} u ON u.id = l.userid
            WHERE l.userid = ?
            AND l.courseid = ?
            AND l.timecreated >= ?
            ORDER BY l.timecreated DESC";

    // Always use limits for large datasets
    return $DB->get_records_sql($sql, [$userid, $courseid, $timestart], 0, $limit);
}

// ✅ CORRECT - Efficient aggregation queries
function get_course_activity_summary($courseid, $timeframe = 30) {
    global $DB;

    $timestart = time() - ($timeframe * 24 * 60 * 60);

    $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date,
                   COUNT(*) as activity_count,
                   COUNT(DISTINCT userid) as unique_users
            FROM {logstore_standard_log}
            WHERE courseid = ?
            AND timecreated >= ?
            AND action != 'viewed'  -- Exclude simple page views
            GROUP BY DATE(FROM_UNIXTIME(timecreated))
            ORDER BY date DESC";

    return $DB->get_records_sql($sql, [$courseid, $timestart], 0, $timeframe);
}

// ✅ CORRECT - Context-aware data retrieval
function get_user_grades_for_course($userid, $courseid) {
    global $DB;

    // Only get grades for specific course context
    $sql = "SELECT gg.*, gi.itemname, gi.itemtype, gi.itemmodule
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gg.userid = ?
            AND gi.courseid = ?
            AND gg.finalgrade IS NOT NULL
            ORDER BY gi.sortorder";

    return $DB->get_records_sql($sql, [$userid, $courseid]);
}
```

### 🚫 Data Processing and Memory Anti-Patterns

**NEVER DO THIS - Memory and Performance Issues:**
```php
// ❌ WRONG - Loading all data into memory at once
function generate_course_report($courseid) {
    // This could load millions of records into memory!
    $users = get_enrolled_users(context_course::instance($courseid));
    $activities = get_all_activities($courseid);
    $logs = get_all_course_logs($courseid);  // Potentially huge!

    $report = [];
    foreach ($users as $user) {
        foreach ($activities as $activity) {
            // Nested loops processing huge datasets - very slow!
            $userdata = process_user_activity($user, $activity, $logs);
            $report[] = $userdata;
        }
    }

    return $report;
}

// ❌ WRONG - Synchronous processing of large datasets
function export_large_report($data) {
    // Processing 100,000+ records synchronously - will timeout!
    foreach ($data as $record) {
        $processedRecord = heavy_processing($record);
        write_to_export($processedRecord);
    }
}
```

**✅ CORRECT - Memory-Efficient Processing:**
```php
// ✅ CORRECT - Batch processing with pagination
function generate_course_report($courseid, $page = 0, $perpage = 50) {
    $context = context_course::instance($courseid);

    // Process users in batches
    $users = get_enrolled_users($context, '', 0, 'u.*', null, $page * $perpage, $perpage);

    if (empty($users)) {
        return ['data' => [], 'hasmore' => false];
    }

    $report = [];
    foreach ($users as $user) {
        $userdata = $this->get_user_summary_data($user->id, $courseid);
        $report[] = $userdata;

        // Free memory periodically
        if (count($report) % 100 === 0) {
            gc_collect_cycles();
        }
    }

    return [
        'data' => $report,
        'hasmore' => count($users) === $perpage
    ];
}

// ✅ CORRECT - Streaming export for large datasets
function export_large_report($courseid, $format = 'csv') {
    $filename = "course_report_{$courseid}_" . date('Y-m-d') . ".$format";

    // Stream output directly to browser
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $output = fopen('php://output', 'w');

        // Write headers
        fputcsv($output, ['User', 'Activity', 'Date', 'Status']);

        // Process in chunks to avoid memory issues
        $page = 0;
        $perpage = 100;

        do {
            $batch = $this->get_report_batch($courseid, $page, $perpage);

            foreach ($batch['data'] as $row) {
                fputcsv($output, [
                    $row['username'],
                    $row['activity'],
                    userdate($row['date']),
                    $row['status']
                ]);
            }

            $page++;

            // Yield control to prevent timeouts
            if ($page % 10 === 0) {
                flush();
            }

        } while ($batch['hasmore']);

        fclose($output);
        exit;
    }
}
```

### 🚫 User Interface and Output Anti-Patterns

**NEVER DO THIS - Poor UI Implementation:**
```php
// ❌ WRONG - No pagination or filtering for large datasets
echo '<table>';
foreach ($allusers as $user) {  // Could be thousands of users!
    echo '<tr><td>' . $user->firstname . '</td></tr>';
}
echo '</table>';

// ❌ WRONG - Hardcoded HTML instead of templates
echo '<div class="report-container">';
echo '<h2>Report Title</h2>';
echo '<table border="1">';  // Inline styling, no CSS classes
// ... more hardcoded HTML

// ❌ WRONG - No export options or accessibility
echo '<table>';  // No table headers, summary, or proper markup
foreach ($data as $row) {
    echo '<tr><td>' . $row->value . '</td></tr>';  // No escaping!
}
echo '</table>';
```

**✅ CORRECT - Proper UI Implementation:**
```php
// ✅ CORRECT - Pagination and filtering
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;
$filters = [
    'datestart' => optional_param('datestart', 0, PARAM_INT),
    'dateend' => optional_param('dateend', 0, PARAM_INT),
    'groupid' => optional_param('groupid', 0, PARAM_INT),
];

$totalcount = count_report_records($courseid, $filters);
$reportdata = get_report_data($courseid, $filters, $page, $perpage);

// Display filters form
$filterform = new \report_yourreport\form\filter_form(null, [
    'courseid' => $courseid,
    'filters' => $filters
]);
$filterform->display();

// Display pagination
echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

// Use template for table output
echo $OUTPUT->render_from_template('report_yourreport/report_table', [
    'headers' => ['User', 'Activity', 'Date', 'Status'],
    'rows' => $reportdata,
    'canexport' => has_capability('report/yourreport:export', $context),
    'exporturl' => new moodle_url('/report/yourreport/export.php', ['id' => $courseid]),
]);

// ✅ CORRECT - Template file (templates/report_table.mustache)
/*
<div class="report-container">
    <div class="report-header">
        <h2>{{#str}}reporttitle, report_yourreport{{/str}}</h2>
        {{#canexport}}
        <div class="export-options">
            <a href="{{exporturl}}&format=csv" class="btn btn-secondary">
                {{#str}}exportcsv, report_yourreport{{/str}}
            </a>
        </div>
        {{/canexport}}
    </div>

    <table class="table table-striped" role="table"
           aria-label="{{#str}}reportdata, report_yourreport{{/str}}">
        <thead>
            <tr>
                {{#headers}}
                <th scope="col">{{.}}</th>
                {{/headers}}
            </tr>
        </thead>
        <tbody>
            {{#rows}}
            <tr>
                <td>{{username}}</td>
                <td>{{activity}}</td>
                <td>{{formatteddate}}</td>
                <td>{{status}}</td>
            </tr>
            {{/rows}}
            {{^rows}}
            <tr>
                <td colspan="{{headers.length}}" class="text-center">
                    {{#str}}nodataavailable, report_yourreport{{/str}}
                </td>
            </tr>
            {{/rows}}
        </tbody>
    </table>
</div>
*/
```

### 🚫 Privacy and Data Protection Anti-Patterns

**NEVER DO THIS - Privacy Violations:**
```php
// ❌ WRONG - Exposing personal data without proper checks
function get_all_user_activity() {
    global $DB;

    // Returns ALL user data including deleted users, private info!
    return $DB->get_records_sql("
        SELECT u.*, l.action, l.target, l.timecreated
        FROM {user} u
        JOIN {logstore_standard_log} l ON l.userid = u.id
    ");
}

// ❌ WRONG - No anonymization options
function display_user_report($users) {
    foreach ($users as $user) {
        // Always shows real names and emails - no privacy protection!
        echo $user->firstname . ' ' . $user->lastname . ' (' . $user->email . ')';
    }
}

// ❌ WRONG - Missing privacy API implementation
// No classes/privacy/provider.php file - GDPR compliance issue!
```

**✅ CORRECT - Privacy-Compliant Implementation:**
```php
// ✅ CORRECT - Respect user privacy settings and context
function get_course_user_activity($courseid, $context) {
    global $DB, $USER;

    // Only get users visible to current user
    $enrolledusers = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);

    if (empty($enrolledusers)) {
        return [];
    }

    $userids = array_keys($enrolledusers);
    list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $params['courseid'] = $courseid;

    $sql = "SELECT l.userid, l.action, l.target, l.timecreated,
                   u.firstname, u.lastname
            FROM {logstore_standard_log} l
            JOIN {user} u ON u.id = l.userid
            WHERE l.courseid = :courseid
            AND l.userid $insql
            AND u.deleted = 0";

    return $DB->get_records_sql($sql, $params);
}

// ✅ CORRECT - Anonymization support
function display_user_data($user, $context, $anonymize = false) {
    if ($anonymize || !has_capability('moodle/user:viewdetails', $context)) {
        // Anonymize user data when appropriate
        return [
            'name' => get_string('anonymoususer', 'report_yourreport'),
            'id' => 'user_' . substr(md5($user->id . 'salt'), 0, 8),
            'email' => '',
        ];
    }

    return [
        'name' => fullname($user, has_capability('moodle/site:viewfullnames', $context)),
        'id' => $user->id,
        'email' => (has_capability('moodle/user:viewdetails', $context)) ? $user->email : '',
    ];
}

// ✅ CORRECT - Privacy API implementation
// File: classes/privacy/provider.php
namespace report_yourreport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'report_yourreport_data',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'data' => 'privacy:metadata:data',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:report_yourreport_data'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                FROM {context} ctx
                JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                JOIN {report_yourreport_data} rd ON rd.courseid = c.id
                WHERE rd.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid
        ]);

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        // Implementation for exporting user data
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        // Implementation for deleting data
    }
}
```

### 🚫 Export and Download Anti-Patterns

**NEVER DO THIS - Poor Export Implementation:**
```php
// ❌ WRONG - No security checks for exports
if (isset($_GET['export'])) {
    // Anyone can export data - no capability checking!
    export_all_data();
}

// ❌ WRONG - Unsafe filename handling
$filename = $_GET['filename'];  // Could contain path traversal!
file_put_contents($filename, $data);  // Arbitrary file write vulnerability!

// ❌ WRONG - Memory issues with large exports
function export_all_users() {
    $allusers = $DB->get_records('user');  // Could be millions of records!

    $csv = '';
    foreach ($allusers as $user) {
        $csv .= $user->firstname . ',' . $user->lastname . "\n";  // String concatenation - memory explosion!
    }

    echo $csv;  // Huge string in memory
}
```

**✅ CORRECT - Secure Export Implementation:**
```php
// ✅ CORRECT - Proper export security and streaming
function handle_export_request($courseid, $format) {
    $course = get_course($courseid);
    $context = context_course::instance($courseid);

    require_login($course);
    require_capability('report/yourreport:export', $context);

    // Validate format
    $allowedformats = ['csv', 'excel'];
    if (!in_array($format, $allowedformats)) {
        throw new invalid_parameter_exception('Invalid export format');
    }

    // Generate secure filename
    $filename = clean_filename("report_yourreport_{$courseid}_" . date('Y-m-d'));

    if ($format === 'csv') {
        export_csv_streaming($courseid, $context, $filename);
    } else if ($format === 'excel') {
        export_excel($courseid, $context, $filename);
    }
}

function export_csv_streaming($courseid, $context, $basefilename) {
    $filename = $basefilename . '.csv';

    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Stream directly to output
    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Write headers
    fputcsv($output, ['User', 'Activity', 'Date', 'Status']);

    // Process in batches to avoid memory issues
    $page = 0;
    $perpage = 100;

    do {
        $data = get_export_data_batch($courseid, $context, $page, $perpage);

        foreach ($data['records'] as $record) {
            fputcsv($output, [
                s($record->username),
                s($record->activity),
                userdate($record->timecreated),
                s($record->status)
            ]);
        }

        $page++;

        // Flush output buffer periodically
        if ($page % 10 === 0) {
            flush();
        }

    } while ($data['hasmore']);

    fclose($output);
    exit;
}
```

## 🎯 PROVEN PATTERNS FOR COMMON REPORT TYPES

### Course Activity Report Pattern
```php
class course_activity_report {
    protected $courseid;
    protected $context;

    public function __construct($courseid) {
        $this->courseid = $courseid;
        $this->context = context_course::instance($courseid);
    }

    public function generate_data($filters = []) {
        $users = $this->get_enrolled_users($filters);
        $activities = $this->get_course_activities();

        $report = [];
        foreach ($users as $user) {
            $userdata = [
                'user' => $user,
                'progress' => $this->calculate_user_progress($user->id),
                'activities' => $this->get_user_activities($user->id, $activities),
            ];
            $report[] = $userdata;
        }

        return $report;
    }

    protected function get_enrolled_users($filters) {
        $enrolledusers = get_enrolled_users($this->context, '', 0, 'u.*', null, 0, 0, true);

        // Apply filters
        if (!empty($filters['groupid'])) {
            $groupusers = groups_get_members($filters['groupid'], 'u.id');
            $enrolledusers = array_intersect_key($enrolledusers, $groupusers);
        }

        return $enrolledusers;
    }
}
```

### System Performance Report Pattern
```php
class system_performance_report {
    public function generate_performance_data() {
        return [
            'database' => $this->get_database_metrics(),
            'memory' => $this->get_memory_metrics(),
            'cache' => $this->get_cache_metrics(),
            'server' => $this->get_server_metrics(),
        ];
    }

    protected function get_database_metrics() {
        global $DB;

        return [
            'type' => $DB->get_dbfamily(),
            'reads' => $DB->perf_get_reads(),
            'writes' => $DB->perf_get_writes(),
            'time' => round($DB->perf_get_time(), 4),
        ];
    }

    protected function get_memory_metrics() {
        return [
            'current' => display_size(memory_get_usage(true)),
            'peak' => display_size(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }
}
```

### User Analytics Report Pattern
```php
class user_analytics_report {
    public function generate_user_insights($userid, $courseid = 0) {
        $data = [
            'engagement' => $this->calculate_engagement_score($userid, $courseid),
            'progress' => $this->get_progress_data($userid, $courseid),
            'activity_patterns' => $this->analyze_activity_patterns($userid),
            'recommendations' => $this->generate_recommendations($userid),
        ];

        return $data;
    }

    protected function calculate_engagement_score($userid, $courseid) {
        // Complex analytics calculation
        $recent_activity = $this->get_recent_activity_count($userid, $courseid);
        $completion_rate = $this->get_completion_rate($userid, $courseid);
        $interaction_quality = $this->calculate_interaction_quality($userid);

        return ($recent_activity * 0.4) + ($completion_rate * 0.4) + ($interaction_quality * 0.2);
    }
}
```

## 🔒 SECURITY CHECKLIST

- [ ] ✅ All page access requires proper authentication
- [ ] ✅ Capability checks are performed for all report views
- [ ] ✅ User data access respects privacy settings
- [ ] ✅ Context-appropriate data filtering is applied
- [ ] ✅ Export functions have additional capability checks
- [ ] ✅ Database queries use parameterized statements
- [ ] ✅ File exports use secure filename generation
- [ ] ✅ Group restrictions are properly enforced
- [ ] ✅ Personal data is anonymized when required
- [ ] ✅ Privacy API is implemented for GDPR compliance

## 🚀 PERFORMANCE CHECKLIST

- [ ] ✅ Large datasets are processed in batches
- [ ] ✅ Database queries include appropriate LIMIT clauses
- [ ] ✅ Pagination is implemented for large result sets
- [ ] ✅ Export functions stream data to avoid memory issues
- [ ] ✅ Expensive operations are cached where possible
- [ ] ✅ Database indexes support common query patterns
- [ ] ✅ Memory usage is monitored and optimized
- [ ] ✅ Background processing is used for complex reports

## 🧪 TESTING PATTERNS

### Unit Test Pattern
```php
class report_yourreport_test extends advanced_testcase {
    public function test_course_report_generation() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);

        $report = new \report_yourreport\course_report($course->id);
        $data = $report->generate_data();

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('users', $data);
    }

    public function test_capability_requirements() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);

        $this->expectException(required_capability_exception::class);

        // Should fail without proper capability
        $this->load_report_page($course->id);
    }
}
```

### Behat Test Pattern
```gherkin
@javascript @report_yourreport
Feature: Report access and functionality
  In order to monitor course progress
  As a teacher
  I need to access course reports

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |

  Scenario: Teacher can access course report
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "Reports > Your Report" in current page administration
    Then I should see "Course Report"
    And I should see "Student One"
```