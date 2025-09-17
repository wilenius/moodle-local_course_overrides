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
 * Course overrides plugin library functions.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the course overrides menu to the course administration menu.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_course_overrides_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    if ($context->contextlevel == CONTEXT_COURSE && has_capability('mod/quiz:manageoverrides', $context)) {
        if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
            $str = get_string('pluginname', 'local_course_overrides');
            $url = new moodle_url('/local/course_overrides/index.php', array('courseid' => $PAGE->course->id));
            $node = navigation_node::create(
                $str,
                $url,
                navigation_node::NODETYPE_LEAF,
                'local_course_overrides',
                'local_course_overrides',
                new pix_icon('i/settings', $str)
            );

            if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
                $node->make_active();
            }
            $settingnode->add_node($node);
        }
    }
}