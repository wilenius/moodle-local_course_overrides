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
 * Override edit/create page.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$quizid = required_param('quizid', PARAM_INT);
$overrideid = optional_param('overrideid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login($courseid);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', array('id' => $quizid, 'course' => $courseid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quizid, $courseid, false, MUST_EXIST);
$cminfo = \cm_info::create($cm);
$context = context_module::instance($cm->id);

require_capability('mod/quiz:manageoverrides', $context);

$override = null;
if ($overrideid) {
    $override = $DB->get_record('quiz_overrides', array('id' => $overrideid, 'quiz' => $quizid), '*', MUST_EXIST);
}

$returnurl = new moodle_url('/local/course_overrides/index.php', array('courseid' => $courseid));

if ($action === 'delete' && $override) {
    require_sesskey();

    $manager = new \mod_quiz\local\override_manager($quiz, $context);
    $manager->delete_overrides_by_id(array($override->id));

    redirect($returnurl, get_string('overridedeleted', 'local_course_overrides'));
}

$PAGE->set_url('/local/course_overrides/override.php',
    array('courseid' => $courseid, 'quizid' => $quizid, 'overrideid' => $overrideid));

if ($override) {
    $title = get_string('editoverride', 'local_course_overrides');
} else {
    $title = get_string('addoverride', 'local_course_overrides');
}

$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(get_string('courseoverrides', 'local_course_overrides'), $returnurl);
$PAGE->navbar->add(format_string($quiz->name));
$PAGE->navbar->add($title);

require_once($CFG->dirroot . '/local/course_overrides/classes/form/user_override_form.php');

$form = new \local_course_overrides\form\user_override_form(null, array(
    'quiz' => $quiz,
    'cm' => $cminfo,
    'context' => $context,
    'override' => $override
));

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $manager = new \mod_quiz\local\override_manager($quiz, $context);

    if ($override) {
        $data->id = $override->id;
        $message = get_string('overrideupdated', 'local_course_overrides');
    } else {
        $message = get_string('overridecreated', 'local_course_overrides');
    }

    $manager->save_override((array)$data);
    redirect($returnurl, $message);
}

if ($override) {
    $form->set_data($override);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$form->display();

echo $OUTPUT->footer();