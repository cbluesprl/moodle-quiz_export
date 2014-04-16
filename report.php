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
 * This file defines the quiz export report class.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_form.php');
// require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_table.php');

/**
 * Quiz report subclass for the export report.
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// class quiz_export_report extends quiz_default_report {
class quiz_export_report extends quiz_attempts_report {

    public function display($quiz, $cm, $course) {
        // ToDo: some globals?
        
        // this inits the quiz_attempts_report (parent class) functionality
        list($currentgroup, $students, $groupstudents, $allowed) =
            $this->init('export', 'quiz_export_settings_form', $quiz, $cm, $course);

        // this creates a new options object and ...
        $options = new quiz_export_options('export', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);
        } else {
            $options->process_settings_from_params();
        }
        // write the information from options back to form (in case options changed due to params)
        $this->form->set_data($options->get_initial_form_data());

        // 
        $questions = quiz_report_get_significant_questions($quiz);

        // 
        $table = new quiz_export_table('mod-quiz-report-export-report', $quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());

        // Start output.

        // print moodle headers (header, navigation, etc.)
        $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);

        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector
            groups_print_activity_menu($cm, $options->get_url());
        }

        $hasquestions = quiz_questions_in_quiz($quiz->questions);
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$students) {
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        } else if ($currentgroup && !$groupstudents) {
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
        }

        $this->form->display();

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            list($fields, $from, $where, $params) = $table->base_sql($allowed);
            // function documentation says we don't need to do this
            // $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();
            $this->add_user_columns($table, $columns, $headers);
            // $this->add_state_column($columns, $headers);
            // $this->add_time_columns($columns, $headers);

            // Set up the table.
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);

            // Print the table
            $table->out($options->pagesize, true);
        }

    }
}
