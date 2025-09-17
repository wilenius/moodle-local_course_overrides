<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Main course overrides page.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('mod/quiz:manageoverrides', $context);

$PAGE->set_url('/local/course_overrides/index.php', array('courseid' => $courseid));
$PAGE->set_title(get_string('courseoverrides', 'local_course_overrides'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(get_string('courseoverrides', 'local_course_overrides'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseoverrides', 'local_course_overrides'));

$quizzes = $DB->get_records('quiz', array('course' => $courseid), 'name ASC');

if (empty($quizzes)) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_course_overrides'));
} else {
    // Add bulk override button
    $bulkurl = new moodle_url('/local/course_overrides/bulk_override.php', array('courseid' => $courseid));
    echo html_writer::div(
        html_writer::link($bulkurl, get_string('addoverride', 'local_course_overrides'),
            array('class' => 'btn btn-primary mb-3')),
        'text-center'
    );

    echo html_writer::start_tag('div', array('class' => 'course-overrides-container'));

    foreach ($quizzes as $quiz) {
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
        if (!$cm || !$cm->visible) {
            continue;
        }

        $quizcontext = context_module::instance($cm->id);

        if (!has_capability('mod/quiz:manageoverrides', $quizcontext)) {
            continue;
        }

        echo html_writer::start_tag('div', array('class' => 'quiz-override-section card mt-3'));
        echo html_writer::start_tag('div', array('class' => 'card-header'));
        echo html_writer::tag('h3', format_string($quiz->name), array('class' => 'mb-0'));
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'card-body'));

        $overrides = $DB->get_records('quiz_overrides', array('quiz' => $quiz->id), 'userid ASC, groupid ASC');

        if (empty($overrides)) {
            echo html_writer::tag('p', get_string('nooverrides', 'local_course_overrides'), array('class' => 'text-muted'));
        } else {
            echo html_writer::start_tag('ul', array('class' => 'list-group list-group-flush'));

            foreach ($overrides as $override) {
                $user = null;
                $displayname = '';

                if ($override->userid) {
                    $user = $DB->get_record('user', array('id' => $override->userid));
                    if ($user) {
                        $displayname = fullname($user);
                    }
                } else if ($override->groupid) {
                    $group = $DB->get_record('groups', array('id' => $override->groupid));
                    if ($group) {
                        $displayname = format_string($group->name) . ' (Group)';
                    }
                }

                echo html_writer::start_tag('li', array('class' => 'list-group-item d-flex justify-content-between align-items-center'));
                echo html_writer::tag('span', $displayname);

                $overrideinfo = array();
                if ($override->timelimit !== null) {
                    $overrideinfo[] = 'Time limit: ' . format_time($override->timelimit);
                }
                if ($override->timeopen !== null) {
                    $overrideinfo[] = 'Opens: ' . userdate($override->timeopen);
                }
                if ($override->timeclose !== null) {
                    $overrideinfo[] = 'Closes: ' . userdate($override->timeclose);
                }
                if ($override->attempts !== null) {
                    $overrideinfo[] = 'Attempts: ' . ($override->attempts == 0 ? get_string('unlimited') : $override->attempts);
                }

                if (!empty($overrideinfo)) {
                    echo html_writer::tag('small', implode(', ', $overrideinfo), array('class' => 'text-muted'));
                }

                $deleteurl = new moodle_url('/local/course_overrides/bulk_override.php',
                    array('courseid' => $courseid, 'action' => 'delete', 'overrideid' => $override->id, 'sesskey' => sesskey()));
                echo html_writer::link($deleteurl, get_string('delete'),
                    array('class' => 'btn btn-sm btn-outline-danger ml-2'));

                echo html_writer::end_tag('li');
            }

            echo html_writer::end_tag('ul');
        }

        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
    }

    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();