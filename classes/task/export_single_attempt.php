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

namespace quiz_export\task;

use mod_quiz\quiz_attempt;
use quiz_export\notification_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

/**
 * Adhoc task to export a single quiz attempt as PDF asynchronously.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_single_attempt extends \core\task\adhoc_task {

    /**
     * Return the name of this task.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('taskexportsingle', 'quiz_export');
    }

    /**
     * Execute the task: generate a PDF and store it via File API.
     *
     * Expected custom data:
     * - attemptid (int): The quiz attempt ID.
     * - pagemode (int): The page break mode.
     * - inline (int): Whether the PDF was requested inline.
     * - userid (int): The user who requested the export.
     * - cmid (int): The course module ID for the quiz.
     */
    public function execute(): void {
        global $CFG;

        raise_memory_limit(MEMORY_HUGE);
        $timelimit = get_config('quiz_export', 'timelimit');
        set_time_limit($timelimit !== false ? (int) $timelimit : 600);

        $data = $this->get_custom_data();
        $attemptid = $data->attemptid;
        $pagemode = $data->pagemode;
        $userid = $data->userid;
        $cmid = $data->cmid;

        $attemptobj = quiz_attempt::create($attemptid);
        $attemptobj->preload_all_attempt_step_users();

        $exporter = new \quiz_export_engine();
        $pdffile = $exporter->a2pdf($attemptobj, $pagemode);

        $info = $exporter->get_additionnal_informations($attemptobj);
        $filename = $info['firstname'] . '_' . $info['lastname'] . '.pdf';

        // Store the file via File API.
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'quiz_export',
            'filearea' => 'export',
            'itemid' => time(),
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ];

        $storedfile = $fs->create_file_from_pathname($filerecord, $pdffile);

        // Cleanup temporary file.
        unlink($pdffile);

        // Build report URL and send notification.
        $reporturl = new \moodle_url('/mod/quiz/report.php', [
            'id' => $cmid,
            'mode' => 'export',
        ]);

        notification_helper::send_export_complete($userid, $filename, $reporturl->out(false));
    }
}
