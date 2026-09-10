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
 * This file defines the quiz grades table.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use mod_quiz\local\reports\attempts_report_table;

defined('MOODLE_INTERNAL') || die();



/**
 * This is a table subclass for displaying the quiz export report.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_table extends attempts_report_table
{

    public function __construct($quiz, $context, $qmsubselect, quiz_export_options $options, $groupstudents, $students, $questions, $reporturl)
    {
        parent::__construct('mod-quiz-report-export-report', $quiz, $context,
            $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    public function build_table()
    {
        // Strange: parent class quiz_attempts_report_table uses this property but doesn't define it
        // So we have to do it here... just for quiz_attempts_report::add_time_columns
        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    /**
     * Generate the display of the user's full name column.
     * Adds an export Link
     * @param object $attempt the table row being output.
     * @return string HTML content to go inside the td.
     */
    public function col_fullname($attempt)
    {
        $html = parent::col_fullname($attempt);
        if ($this->is_downloading() || empty($attempt->attempt)) {
            return $html;
        }

        $urlparams = array_merge(
            array('attempt' => $attempt->attempt, 'inline' => 1, 'returnurl' => $this->get_return_url()),
            \quiz_export\pdf_options::from_data($this->options)->to_array()
        );

        return $html . html_writer::empty_tag('br') . html_writer::link(
                new moodle_url('/mod/quiz/report/export/a2pdf.php', $urlparams),
                get_string('exportattempt', 'quiz_export'), array('class' => 'reviewlink'));
    }

    /**
     * Returns the report page to come back to once a single attempt has been exported.
     *
     * @return string The page currently displayed, as a URL local to the wwwroot.
     */
    protected function get_return_url()
    {
        global $PAGE;

        $currenturl = qualified_me();

        return $currenturl === false ? $PAGE->url->out_as_local_url(false)
            : (new moodle_url($currenturl))->out_as_local_url(false);
    }

    protected function submit_buttons()
    {
        global $PAGE;
        echo '<input type="submit" id="exportattemptsbutton" name="export" value="' .
            get_string('exportselected', 'quiz_export') . '"/>';
        $PAGE->requires->event_handler('#exportattemptsbutton', 'click', 'M.util.show_confirm_dialog',
            array('message' => get_string('exportattemptcheck', 'quiz_export')));
    }
}
