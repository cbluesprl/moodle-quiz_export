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
require_once($CFG->dirroot . '/mod/quiz/report/export/export_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/classes/export_status.php');

/**
 * Quiz report subclass for the export report.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_report extends attempts_report
{

    /** @var int Maximum number of characters of task output shown in the error details block. */
    const ERROR_OUTPUT_MAXLENGTH = 2000;

    /** @var object Store options for the quiz export report (page mode, etc.) */
    private $options;

    public function display($quiz, $cm, $course)
    {
        global $OUTPUT, $DB, $USER;

        // This inits the quiz_attempts_report (parent class) functionality
        list($currentgroup, $students, $groupstudents, $allowed) =
            $this->init('export', 'quiz_export_settings_form', $quiz, $cm, $course);

        // This creates a new options object and ...
        $this->options = new quiz_export_options('export', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $this->options->process_settings_from_form($fromform);
            redirect($this->options->get_url());
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

        // Display previous exports history.
        if (!$table->is_downloading()) {
            $this->display_export_history($this->context);
        }
    }

    /**
     * Display the export history table with download links and pending tasks.
     *
     * @param \context $context The current module context.
     */
    protected function display_export_history(\context $context): void {
        global $DB, $USER, $OUTPUT;

        // Fetch completed export files.
        $sql = "SELECT f.id, f.filename, f.filesize, f.timecreated
                  FROM {files} f
                 WHERE f.component = :component
                   AND f.filearea = :filearea
                   AND f.contextid = :contextid
                   AND f.userid = :userid
                   AND f.filename != '.'
              ORDER BY f.timecreated DESC";

        $params = [
            'component' => 'quiz_export',
            'filearea' => 'export',
            'contextid' => $context->id,
            'userid' => $USER->id,
        ];

        $files = $DB->get_records_sql($sql, $params);

        // Fetch pending/running adhoc tasks for this user.
        $pendingtasks = $this->get_pending_export_tasks($USER->id);

        echo $OUTPUT->heading(get_string('previousexports', 'quiz_export'), 3);

        if (empty($files) && empty($pendingtasks)) {
            echo $OUTPUT->notification(get_string('noexportsyet', 'quiz_export'), 'info');
            return;
        }

        $historytable = new \html_table();
        $historytable->head = [
            get_string('exportdate', 'quiz_export'),
            get_string('exportfilename', 'quiz_export'),
            get_string('exportfilesize', 'quiz_export'),
            get_string('exportstatus', 'quiz_export'),
            '',
        ];
        $historytable->attributes['class'] = 'generaltable';

        // Pending/running/failed tasks first.
        $showtechnicaldetails = has_capability('moodle/site:config', \context_system::instance());

        foreach ($pendingtasks as $task) {
            $status = \quiz_export\export_status::from_task_record($task);

            $statuscell = \html_writer::tag('span', $status->get_label(),
                ['class' => $status->get_badge_class()]);
            $statuscell .= \html_writer::div($status->get_detail(), 'quizexport-note');

            $hint = $status->get_hint();
            if ($hint !== '') {
                $statuscell .= \html_writer::div($hint, 'quizexport-note');
            }

            if ($showtechnicaldetails && $status->is_failure()) {
                $statuscell .= $this->render_task_error_details($task);
            }

            $filenamecell = $status->is_failure()
                ? get_string('exportnofile', 'quiz_export')
                : \html_writer::tag('em', get_string('exportpending', 'quiz_export'));

            $historytable->data[] = [
                userdate($task->timecreated),
                $filenamecell,
                '-',
                $statuscell,
                '',
            ];
        }

        // Completed export files.
        foreach ($files as $file) {
            $downloadurl = new \moodle_url('/mod/quiz/report/export/download.php', ['fileid' => $file->id]);
            $statuslabel = get_string('exportstatuscomplete', 'quiz_export');
            $row = [
                userdate($file->timecreated),
                s($file->filename),
                display_size($file->filesize),
                \html_writer::tag('span', $statuslabel, ['class' => 'quizexport-badge quizexport-badge-done']),
                \html_writer::link($downloadurl, get_string('downloadexport', 'quiz_export')),
            ];
            $historytable->data[] = $row;
        }

        echo \html_writer::table($historytable);
    }

    /**
     * Get pending or running export adhoc tasks for a given user.
     *
     * @param int $userid The user ID.
     * @return array List of pending task records.
     */
    protected function get_pending_export_tasks(int $userid): array {
        global $DB;

        $classnames = [
            '\\quiz_export\\task\\export_attempts',
            '\\quiz_export\\task\\export_single_attempt',
        ];

        list($insql, $inparams) = $DB->get_in_or_equal($classnames, SQL_PARAMS_NAMED);

        $sql = "SELECT *
                  FROM {task_adhoc}
                 WHERE classname {$insql}
                   AND userid = :userid
              ORDER BY timecreated DESC";

        $inparams['userid'] = $userid;

        return $DB->get_records_sql($sql, $inparams);
    }

    /**
     * @param \stdClass $task The task_adhoc record.
     * @return string HTML of a collapsed details block, empty when no failed log is available.
     */
    protected function render_task_error_details(\stdClass $task): string {
        global $DB, $USER;

        $since = max((int) $task->timecreated, (int) ($task->firststartingtime ?? 0));

        $sql = "SELECT id, output, timestart
                  FROM {task_log}
                 WHERE classname = :classname
                   AND userid = :userid
                   AND result = 1
                   AND timestart >= :since
              ORDER BY timestart DESC";

        $params = [
            'classname' => ltrim($task->classname, '\\'),
            'userid' => $USER->id,
            'since' => $since,
        ];

        $logs = $DB->get_records_sql($sql, $params, 0, 1);
        if (empty($logs)) {
            return '';
        }

        $log = reset($logs);
        $output = trim($log->output);
        if (\core_text::strlen($output) > self::ERROR_OUTPUT_MAXLENGTH) {
            $output = '…' . \core_text::substr($output, -self::ERROR_OUTPUT_MAXLENGTH);
        }

        return \html_writer::tag('details',
            \html_writer::tag('summary', get_string('exporterrordetails', 'quiz_export'))
            . \html_writer::tag('pre', s($output)),
            ['class' => 'quizexport-details']);
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
        global $USER;

        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('export', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    $asyncbulk = get_config('quiz_export', 'asyncbulk');

                    if (!empty($asyncbulk)) {
                        // Queue an adhoc task for async bulk export.
                        $task = new \quiz_export\task\export_attempts();
                        $task->set_custom_data(array_merge(
                            \quiz_export\pdf_options::from_data($this->options)->to_array(),
                            [
                                'attemptids' => $attemptids,
                                'userid' => $USER->id,
                                'cmid' => $cm->id,
                            ]
                        ));
                        $task->set_userid($USER->id);
                        \core\task\manager::queue_adhoc_task($task);

                        redirect($redirecturl, get_string('exportqueued', 'quiz_export'), null,
                            \core\output\notification::NOTIFY_SUCCESS);
                    } else {
                        // Synchronous export (original behaviour).
                        $this->export_attempts($quiz, $cm, $attemptids, $allowed);
                        redirect($redirecturl);
                    }
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
        global $USER;

        $service = new \quiz_export\export_service();
        $storedfile = $service->export_bulk(
            $attemptids,
            \quiz_export\pdf_options::from_data($this->options),
            $USER->id,
            $cm->id
        );

        $filename = $storedfile->get_filename();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $storedfile->readfile();
    }
}
