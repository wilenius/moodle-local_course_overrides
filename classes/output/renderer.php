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
 * Course overrides renderer.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_course_overrides\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for course overrides pages.
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the main course overrides page.
     *
     * @param array $quizzes Array of quiz objects with overrides
     * @param int $courseid Course ID
     * @return string HTML output
     */
    public function render_course_overrides($quizzes, $courseid) {
        $templatecontext = array(
            'courseid' => $courseid,
            'bulkurl' => (new \moodle_url('/local/course_overrides/bulk_override.php',
                array('courseid' => $courseid)))->out(),
            'quizzes' => array_map(array($this, 'prepare_quiz_context'), $quizzes),
            'hasquizzes' => !empty($quizzes)
        );

        return $this->render_from_template('local_course_overrides/course_overrides', $templatecontext);
    }

    /**
     * Prepare quiz context data for template.
     *
     * @param \stdClass $quiz Quiz object with overrides property
     * @return array Template context for quiz
     */
    private function prepare_quiz_context($quiz) {
        global $DB;

        $overrides = !empty($quiz->overrides) ? $quiz->overrides : array();

        return array(
            'id' => $quiz->id,
            'name' => format_string($quiz->name),
            'hasoverrides' => !empty($overrides),
            'overrides' => array_map(array($this, 'prepare_override_context'), $overrides)
        );
    }

    /**
     * Prepare override context data for template.
     *
     * @param \stdClass $override Override object
     * @return array Template context for override
     */
    private function prepare_override_context($override) {
        global $DB;

        $displayname = $this->get_override_displayname($override);
        $info = $this->get_override_info($override);

        return array(
            'id' => $override->id,
            'displayname' => $displayname,
            'info' => $info,
            'hasinfo' => !empty($info),
            'deleteurl' => (new \moodle_url('/local/course_overrides/bulk_override.php', array(
                'courseid' => $override->courseid ?? 0,
                'action' => 'delete',
                'overrideid' => $override->id,
                'sesskey' => sesskey()
            )))->out()
        );
    }

    /**
     * Get display name for override (user or group).
     *
     * @param \stdClass $override Override object
     * @return string Display name
     */
    private function get_override_displayname($override) {
        global $DB;

        if ($override->userid) {
            $user = $DB->get_record('user', array('id' => $override->userid));
            return $user ? fullname($user) : get_string('unknownuser', 'local_course_overrides');
        } else if ($override->groupid) {
            $group = $DB->get_record('groups', array('id' => $override->groupid));
            return $group ? format_string($group->name) . ' (' . get_string('group') . ')' :
                get_string('unknowngroup', 'local_course_overrides');
        }

        return get_string('unknown', 'local_course_overrides');
    }

    /**
     * Get formatted override information.
     *
     * @param \stdClass $override Override object
     * @return string Formatted override info
     */
    private function get_override_info($override) {
        $overrideinfo = array();

        if ($override->timelimit !== null) {
            $overrideinfo[] = get_string('timelimit', 'local_course_overrides') . ': ' . format_time($override->timelimit);
        }
        if ($override->timeopen !== null) {
            $overrideinfo[] = get_string('opens', 'local_course_overrides') . ': ' . userdate($override->timeopen);
        }
        if ($override->timeclose !== null) {
            $overrideinfo[] = get_string('closes', 'local_course_overrides') . ': ' . userdate($override->timeclose);
        }
        if ($override->attempts !== null) {
            $attempts = ($override->attempts == 0) ? get_string('unlimited') : $override->attempts;
            $overrideinfo[] = get_string('attempts', 'local_course_overrides') . ': ' . $attempts;
        }

        return implode(', ', $overrideinfo);
    }

    /**
     * Render overrides as a table (alternative to card layout).
     *
     * @param array $overrides Array of override objects
     * @param int $courseid Course ID
     * @return string HTML table
     */
    public function render_overrides_table($overrides, $courseid) {
        if (empty($overrides)) {
            return html_writer::div(
                get_string('nooverrides', 'local_course_overrides'),
                'alert alert-info'
            );
        }

        $table = new \html_table();
        $table->head = array(
            get_string('name'),
            get_string('quiz', 'quiz'),
            get_string('overrideinfo', 'local_course_overrides'),
            get_string('actions')
        );
        $table->align = array('left', 'left', 'left', 'center');
        $table->data = array();

        foreach ($overrides as $override) {
            $displayname = $this->get_override_displayname($override);
            $info = $this->get_override_info($override);

            $deleteurl = new \moodle_url('/local/course_overrides/bulk_override.php', array(
                'courseid' => $courseid,
                'action' => 'delete',
                'overrideid' => $override->id,
                'sesskey' => sesskey()
            ));

            $actions = html_writer::link(
                $deleteurl,
                get_string('delete'),
                array('class' => 'btn btn-sm btn-outline-danger')
            );

            $table->data[] = array(
                $displayname,
                format_string($override->quizname ?? ''),
                $info,
                $actions
            );
        }

        return html_writer::table($table);
    }
}