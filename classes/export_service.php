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
 * Centralised export service for quiz_export.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export;

use mod_quiz\quiz_attempt;
use stored_file;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

/**
 * Generates quiz attempt PDF / ZIP exports and stores them via the File API.
 *
 * Single source of truth shared by the synchronous request handlers
 * (report.php, a2pdf.php) and the asynchronous adhoc tasks. Callers receive
 * the resulting stored_file and decide what to do with it (stream to the
 * browser or send a completion notification).
 */
class export_service {

    /**
     * Apply the memory and time limits configured for the plugin to the
     * current PHP process.
     */
    public function apply_runtime_limits(): void {
        raise_memory_limit(MEMORY_HUGE);
        $timelimit = get_config('quiz_export', 'timelimit');
        set_time_limit($timelimit !== false ? (int) $timelimit : 600);
    }

    /**
     * Export one quiz attempt as a PDF stored via the File API.
     *
     * @param int $attemptid Quiz attempt ID.
     * @param int $pagemode  Page break mode (quiz_export_engine constant).
     * @param int $userid    Owner of the resulting stored_file.
     * @param int $cmid      Quiz course module ID.
     * @return stored_file
     */
    public function export_single(int $attemptid, int $pagemode, int $userid, int $cmid): stored_file {
        $this->apply_runtime_limits();

        $attemptobj = quiz_attempt::create($attemptid);
        $attemptobj->preload_all_attempt_step_users();

        $exporter = new \quiz_export_engine();
        $pdffile = $exporter->a2pdf($attemptobj, $pagemode);

        $info = $exporter->get_additionnal_informations($attemptobj);
        $filename = $info['firstname'] . '_' . $info['lastname'] . '.pdf';

        try {
            return $this->store_file($cmid, $userid, $filename, $pdffile);
        } finally {
            if (file_exists($pdffile)) {
                unlink($pdffile);
            }
        }
    }

    /**
     * Export multiple quiz attempts as a ZIP stored via the File API.
     *
     * @param int[] $attemptids Quiz attempt IDs.
     * @param int   $pagemode   Page break mode.
     * @param int   $userid     Owner of the resulting stored_file.
     * @param int   $cmid       Quiz course module ID.
     * @return stored_file
     */
    public function export_bulk(array $attemptids, int $pagemode, int $userid, int $cmid): stored_file {
        global $DB;

        $this->apply_runtime_limits();

        $exporter = new \quiz_export_engine();
        $pdffiles = [];

        $tmpdir = sys_get_temp_dir();
        $tmpfile = tempnam($tmpdir, 'mdl-qexp_');
        $tmpzipfile = $tmpfile . '.zip';
        rename($tmpfile, $tmpzipfile);
        chmod($tmpzipfile, 0644);

        $zip = new \ZipArchive();
        $zip->open($tmpzipfile, \ZipArchive::OVERWRITE);

        try {
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
            return $this->store_file($cmid, $userid, $filename, $tmpzipfile);
        } finally {
            foreach ($pdffiles as $pdffile) {
                if (file_exists($pdffile)) {
                    unlink($pdffile);
                }
            }
            if (file_exists($tmpzipfile)) {
                unlink($tmpzipfile);
            }
        }
    }

    /**
     * Persist a generated file in the quiz_export filearea so it appears in
     * the user's export history.
     *
     * @param int    $cmid       Quiz course module ID.
     * @param int    $userid     Owner of the stored file.
     * @param string $filename   Final filename to store under.
     * @param string $sourcepath Absolute path to the temporary source file.
     * @return stored_file
     */
    protected function store_file(int $cmid, int $userid, string $filename, string $sourcepath): stored_file {
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'quiz_export',
            'filearea'  => 'export',
            'itemid'    => time(),
            'filepath'  => '/',
            'filename'  => $filename,
            'userid'    => $userid,
        ];

        return $fs->create_file_from_pathname($filerecord, $sourcepath);
    }
}