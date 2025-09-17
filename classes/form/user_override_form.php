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

namespace local_course_overrides\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing user quiz overrides.
 *
 * @package     local_course_overrides
 * @copyright   2025 Course Overrides Plugin
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_override_form extends moodleform {

    /**
     * Define the form
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $quiz = $this->_customdata['quiz'];
        $cm = $this->_customdata['cm'];
        $context = $this->_customdata['context'];
        $override = $this->_customdata['override'] ?? null;

        $mform->addElement('header', 'override', get_string('timelimitoverride', 'local_course_overrides'));

        if ($override && $override->userid) {
            $user = $DB->get_record('user', array('id' => $override->userid));
            $userchoices = array($override->userid => fullname($user));
            $mform->addElement('select', 'userid', get_string('overrideuser', 'quiz'), $userchoices);
            $mform->freeze('userid');
        } else {
            $userfieldsapi = \core_user\fields::for_identity($context)->with_userpic()->with_name();
            $extrauserfields = $userfieldsapi->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);

            $enrolledjoin = get_enrolled_with_capabilities_join($context, '', 'mod/quiz:attempt', 0, true);
            $userfieldsql = $userfieldsapi->get_sql('u', true, '', '', false);
            list($sort, $sortparams) = users_order_by_sql('u', null, $context, $userfieldsql->mappings);

            $users = $DB->get_records_sql("
                    SELECT DISTINCT $userfieldsql->selects
                      FROM {user} u
                      $enrolledjoin->joins
                      $userfieldsql->joins
                      LEFT JOIN {quiz_overrides} existingoverride ON
                                  existingoverride.userid = u.id AND existingoverride.quiz = :quizid
                     WHERE existingoverride.id IS NULL
                       AND $enrolledjoin->wheres
                  ORDER BY $sort
                    ", array_merge(['quizid' => $quiz->id], $userfieldsql->params, $enrolledjoin->params, $sortparams));

            $info = new \core_availability\info_module($cm);
            $users = $info->filter_user_list($users);

            if (empty($users)) {
                throw new \moodle_exception('usersnone', 'quiz');
            }

            $userchoices = array();
            foreach ($users as $id => $user) {
                $userchoices[$id] = fullname($user);
                foreach ($extrauserfields as $field) {
                    if (isset($user->$field) && $user->$field !== '') {
                        $userchoices[$id] .= ' (' . s($user->$field) . ')';
                        break;
                    }
                }
            }

            $mform->addElement('searchableselector', 'userid', get_string('overrideuser', 'quiz'), $userchoices);
            $mform->addRule('userid', get_string('required'), 'required', null, 'client');
        }

        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'), array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'quiz');
        $mform->setDefault('timelimit', $quiz->timelimit);

        $mform->addElement('hidden', 'quiz', $quiz->id);
        $mform->setType('quiz', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $quiz = $this->_customdata['quiz'];
        $context = $this->_customdata['context'];
        $override = $this->_customdata['override'] ?? null;

        if ($override) {
            $data['id'] = $override->id;
        }

        $manager = new \mod_quiz\local\override_manager($quiz, $context);
        $managererrors = $manager->validate_data($data);

        $errors = array_merge($errors, $managererrors);

        if (!empty($errors['general'])) {
            $errors['userid'] = $errors['userid'] ?? '' . $errors['general'];
        }

        return $errors;
    }
}