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

// Prepare quiz data with overrides for rendering
$quizzes = $DB->get_records('quiz', array('course' => $courseid), 'name ASC');
$accessible_quizzes = array();

foreach ($quizzes as $quiz) {
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
    if (!$cm || !$cm->visible) {
        continue;
    }

    $quizcontext = context_module::instance($cm->id);
    if (!has_capability('mod/quiz:manageoverrides', $quizcontext)) {
        continue;
    }

    $overrides = $DB->get_records('quiz_overrides', array('quiz' => $quiz->id), 'userid ASC, groupid ASC');

    // Add courseid to each override for delete URLs
    foreach ($overrides as $override) {
        $override->courseid = $courseid;
    }

    $quiz->overrides = $overrides;
    $accessible_quizzes[] = $quiz;
}

// Use renderer to output the page
$renderer = $PAGE->get_renderer('local_course_overrides');
echo $renderer->render_course_overrides($accessible_quizzes, $courseid);

echo $OUTPUT->footer();