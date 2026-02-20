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
 * Adhoc task to export multiple quiz attempts as a ZIP file asynchronously.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_attempts extends \core\task\adhoc_task {

    /**
     * Return the name of this task.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('taskexportattempts', 'quiz_export');
    }

    /**
     * Execute the task: generate PDFs, create ZIP, store via File API.
     *
     * Expected custom data:
     * - attemptids (array): List of quiz attempt IDs.
     * - pagemode (int): The page break mode.
     * - userid (int): The user who requested the export.
     * - cmid (int): The course module ID for the quiz.
     */
    public function execute(): void {
        global $CFG, $DB;

        raise_memory_limit(MEMORY_HUGE);
        $timelimit = get_config('quiz_export', 'timelimit');
        set_time_limit($timelimit !== false ? (int) $timelimit : 600);

        $data = $this->get_custom_data();
        $attemptids = $data->attemptids;
        $pagemode = $data->pagemode;
        $userid = $data->userid;
        $cmid = $data->cmid;

        $exporter = new \quiz_export_engine();
        $pdffiles = [];

        $tmpdir = sys_get_temp_dir();
        $tmpfile = tempnam($tmpdir, 'mdl-qexp_');
        $tmpzipfile = $tmpfile . '.zip';
        rename($tmpfile, $tmpzipfile);
        chmod($tmpzipfile, 0644);

        $zip = new \ZipArchive();
        $zip->open($tmpzipfile, \ZipArchive::OVERWRITE);

        foreach ($attemptids as $attemptid) {
            $attemptobj = quiz_attempt::create($attemptid);
            $attemptobj->preload_all_attempt_step_users();
            $pdffile = $exporter->a2pdf($attemptobj, $pagemode);
            $pdffiles[] = $pdffile;
            $student = $DB->get_record('user', ['id' => $attemptobj->get_userid()]);
            $zip->addFile($pdffile, fullname($student, true) . '_' . $attemptid . '.pdf');
        }
        $zip->close();

        $filename = 'quiz_export_' . date('Ymd_His') . '.zip';

        // Store the ZIP file via File API.
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

        $storedfile = $fs->create_file_from_pathname($filerecord, $tmpzipfile);

        // Cleanup temporary files.
        foreach ($pdffiles as $pdffile) {
            unlink($pdffile);
        }
        unlink($tmpzipfile);

        // Build report URL and send notification.
        $reporturl = new \moodle_url('/mod/quiz/report.php', [
            'id' => $cmid,
            'mode' => 'export',
        ]);

        notification_helper::send_export_complete($userid, $filename, $reporturl->out(false));
    }
}
