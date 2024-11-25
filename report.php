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
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use mod_quiz\local\reports\attempts_report;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

/**
 * Quiz report subclass for the export report.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_report extends quiz_attempts_report
{

    /** @var object Store options for the quiz export report (page mode, etc.) */
    private $options;

    public function display($quiz, $cm, $course)
    {
        global $OUTPUT;

        // This inits the quiz_attempts_report (parent class) functionality
        list($currentgroup, $students, $groupstudents, $allowed) =
            $this->init('export', 'quiz_export_settings_form', $quiz, $cm, $course);

        // This creates a new options object and ...
        $this->options = new quiz_export_options('export', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $this->options->process_settings_from_form($fromform);
        } else {
            $this->options->process_settings_from_params();
        }
        // write the information from options back to form (in case options changed due to params)
        $this->form->set_data($this->options->get_initial_form_data());

        $questions = quiz_report_get_significant_questions($quiz);

        $table = new quiz_export_table($quiz, $this->context, $this->qmsubselect,
            $this->options, $groupstudents, $students, $questions, $this->options->get_url());

        // Downloading?
        // $table->is_downloading('csv', 'filename', 'Sheettitle');

        // Set layout e.g. for hiding navigation
        // Nothing but content
        // $PAGE->set_pagelayout('embedded');
        // Just breadcrump bar and title
        // $PAGE->set_pagelayout('print');

        // Process actions
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $this->options->get_url());

        // Start output.

        // Print moodle headers (header, navigation, etc.) only if not downloading
        if (!$table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);
        }

        // No idea what this operated
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector
            groups_print_activity_menu($cm, $this->options->get_url());
        }

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$students) {
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        } else if ($currentgroup && !$groupstudents) {
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
        }

        $this->form->display();

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $this->options->attempts == self::ALL_WITH)) {
            list($fields, $from, $where, $params) = $table->base_sql($allowed);
            // Function documentation says we don't need to do this
            // $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $this->options->checkboxcolumn) {
                $columnname = 'checkbox';
                $headers[] = $table->checkbox_col_header($columnname);
            }

            // Display a checkbox column for bulk export
            $columns[] = 'checkbox';
            $headers[] = null;

            $this->add_user_columns($table, $columns, $headers);

            // $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            // Set up the table.
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $this->options, false);
            // $table->set_attribute('class', 'generaltable generalbox grades');
            // Print the table
            $table->out($this->options->pagesize, true);
        }
    }

    /**
     * Process any submitted actions.
     * @param object $quiz the quiz settings.
     * @param object $cm the cm object for the quiz.
     * @param int $currentgroup the currently selected group.
     * @param array $groupstudents the students in the current group.
     * @param array $allowed the users whose attempt this user is allowed to modify.
     * @param moodle_url $redirecturl where to redircet to after a successful action.
     */
    protected function process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl)
    {
        // parent::process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl);

        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('export', 0, PARAM_BOOL) && confirm_sesskey()) {
                raise_memory_limit(MEMORY_HUGE);
                set_time_limit(600);
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    // require_capability('mod/quiz:deleteattempts', $this->context);
                    $this->export_attempts($quiz, $cm, $attemptids, $allowed);
                    redirect($redirecturl);
                }
            }
        }
    }

    /**
     * Export the quiz attempts
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param array $attemptids the list of attempt ids to export.
     * @param array $allowed This list of userids that are visible in the report.
     *      Users can only export attempts that they are allowed to see in the report.
     *      Empty means all users.
     */
    protected function export_attempts($quiz, $cm, $attemptids, $allowed)
    {
        global $DB;

        $pdf_files = array();
        $exporter = new quiz_export_engine();

        $tmp_dir = sys_get_temp_dir();
        $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
        $tmp_zip_file = $tmp_file . ".zip";
        rename($tmp_file, $tmp_zip_file);
        chmod($tmp_zip_file, 0644);

        $zip = new ZipArchive;
        $zip->open($tmp_zip_file, ZipArchive::OVERWRITE);

        foreach ($attemptids as $attemptid) {
            $attemptobj = quiz_attempt::create($attemptid);
            $attemptobj->preload_all_attempt_step_users();
            $pdf_file = $exporter->a2pdf($attemptobj, $this->options->pagemode);
            $pdf_files[] = $pdf_file;
            $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
            $zip->addFile($pdf_file, fullname($student, true) . "_" . $attemptid . '.pdf');
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"quiz_export.zip\"");
        readfile($tmp_zip_file);

        // Cleanup
        foreach ($pdf_files as $pdf_file) {
            unlink($pdf_file);
        }
        unset($zip);
        unlink($tmp_zip_file);
    }
}
