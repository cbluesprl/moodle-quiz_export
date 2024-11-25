<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the setting form for the quiz export report.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_form.php');

/**
 * Quiz export report settings form.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_settings_form extends mod_quiz_attempts_report_form
{

    protected function other_preference_fields(MoodleQuickForm $mform)
    {
        $mform->addElement('header', 'exportsettings',
            get_string('exportsettings', 'quiz_export'));

        $mform->addElement('select', 'pagemode', get_string('pagemode', 'quiz_export'), array(
            quiz_export_engine::PAGEMODE_TRUEPAGE => get_string('exportmodetruepage', 'quiz_export'),
            quiz_export_engine::PAGEMODE_QUESTIONPERPAGE => get_string('exportmodequestionperpage', 'quiz_export'),
            quiz_export_engine::PAGEMODE_SINGLEPAGE => get_string('exportmodesinglepage', 'quiz_export'),
        ));
    }
}
