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
 * Bulk override management page.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$overrideid = optional_param('overrideid', 0, PARAM_INT);

require_login($courseid);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('mod/quiz:manageoverrides', $context);

$returnurl = new moodle_url('/local/course_overrides/index.php', array('courseid' => $courseid));

if ($action === 'delete' && $overrideid) {
    require_sesskey();

    $override = $DB->get_record('quiz_overrides', array('id' => $overrideid), '*', MUST_EXIST);
    $quiz = $DB->get_record('quiz', array('id' => $override->quiz, 'course' => $courseid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid, false, MUST_EXIST);
    $quizcontext = context_module::instance($cm->id);

    if (has_capability('mod/quiz:manageoverrides', $quizcontext)) {
        // Add cmid to quiz object as required by override_manager
        $quiz->cmid = $cm->id;

        $manager = new \mod_quiz\local\override_manager($quiz, $quizcontext);
        $manager->delete_overrides_by_id(array($override->id));
        redirect($returnurl, get_string('overridedeleted', 'local_course_overrides'));
    }
}

$PAGE->set_url('/local/course_overrides/bulk_override.php', array('courseid' => $courseid));
$PAGE->set_title(get_string('addoverride', 'local_course_overrides'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(get_string('courseoverrides', 'local_course_overrides'), $returnurl);
$PAGE->navbar->add(get_string('addoverride', 'local_course_overrides'));

require_once($CFG->dirroot . '/local/course_overrides/classes/form/bulk_override_form.php');

$formurl = new moodle_url('/local/course_overrides/bulk_override.php', array('courseid' => $courseid));
$form = new \local_course_overrides\form\bulk_override_form($formurl, array(
    'courseid' => $courseid,
    'context' => $context
));

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $quizzes = $DB->get_records('quiz', array('course' => $courseid));
    $successcount = 0;
    $updatedcount = 0;
    $errors = array();
    $skipped = array();

    if (empty($quizzes)) {
        redirect($returnurl, 'No quizzes found in course');
    }

    foreach ($quizzes as $quiz) {
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
        if (!$cm || !$cm->visible) {
            $skipped[] = format_string($quiz->name) . ': Quiz not visible';
            continue;
        }

        $quizcontext = context_module::instance($cm->id);
        if (!has_capability('mod/quiz:manageoverrides', $quizcontext)) {
            $skipped[] = format_string($quiz->name) . ': No permission';
            continue;
        }

        // Check if override already exists for this user
        $existingoverride = $DB->get_record('quiz_overrides', array('quiz' => $quiz->id, 'userid' => $data->userid));
        if ($existingoverride && empty($data->updateexisting)) {
            $skipped[] = format_string($quiz->name) . ': Override already exists (use update option to modify). UpdateExisting value: ' . (isset($data->updateexisting) ? $data->updateexisting : 'not set');
            continue;
        }

        // Skip if the time limit is the same as the quiz default
        if ($data->timelimit == $quiz->timelimit) {
            $skipped[] = format_string($quiz->name) . ': Time limit matches quiz default (' . format_time($quiz->timelimit) . ')';
            continue;
        }

        try {
            // Add cmid to quiz object as required by override_manager
            $quiz->cmid = $cm->id;

            $manager = new \mod_quiz\local\override_manager($quiz, $quizcontext);

            $overridedata = array(
                'userid' => $data->userid,
                'quiz' => $quiz->id,
                'timelimit' => $data->timelimit
            );

            // If updating existing override, include the ID
            if ($existingoverride) {
                $overridedata['id'] = $existingoverride->id;
            }

            $manager->save_override($overridedata);
            if ($existingoverride) {
                $updatedcount++;
            } else {
                $successcount++;
            }
        } catch (Exception $e) {
            $errors[] = format_string($quiz->name) . ': ' . $e->getMessage();
        }
    }

    if ($successcount > 0 || $updatedcount > 0) {
        $message = '';
        if ($successcount > 0) {
            $message .= get_string('bulkoverridecreated', 'local_course_overrides', $successcount);
        }
        if ($updatedcount > 0) {
            if ($message) $message .= ' ';
            $message .= get_string('bulkoverrideupdated', 'local_course_overrides', $updatedcount);
        }
        if (!empty($errors)) {
            $message .= ' ' . get_string('bulkoverrideerrors', 'local_course_overrides', implode('<br>', $errors));
        }
        if (!empty($skipped)) {
            $message .= '<br>Skipped: ' . implode('<br>', $skipped);
        }
        redirect($returnurl, $message);
    } else {
        $debuginfo = '';
        if (!empty($errors)) {
            $debuginfo .= 'Errors: ' . implode('<br>', $errors) . '<br>';
        }
        if (!empty($skipped)) {
            $debuginfo .= 'Skipped: ' . implode('<br>', $skipped);
        }
        redirect($returnurl, get_string('bulkoverridefailed', 'local_course_overrides') . ($debuginfo ? '<br>' . $debuginfo : ''));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addoverride', 'local_course_overrides'));

$form->display();

echo $OUTPUT->footer();