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
 * This file downloads a single quiz attempt.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\quiz_attempt;

require_once(__DIR__ . '/../../../../config.php');

global $CFG, $USER;

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

$attemptid = required_param('attempt', PARAM_INT);
$inline = optional_param('inline', 0, PARAM_INT);
$exportoptions = \quiz_export\pdf_options::from_params();

// Get attempt object
$attemptobj = quiz_attempt::create($attemptid);
$attemptobj->preload_all_attempt_step_users();

// Check login and permissions
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
if (!$attemptobj->is_review_allowed() && $attemptobj->get_userid() != $USER->id) {
    throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noreviewattempt');
}

// Check if async export is enabled.
$asyncsingle = get_config('quiz_export', 'asyncsingle');

if (!empty($asyncsingle)) {
    // Queue an adhoc task for async export.
    $task = new \quiz_export\task\export_single_attempt();
    $task->set_custom_data(array_merge($exportoptions->to_array(), [
        'attemptid' => $attemptid,
        'inline' => $inline,
        'userid' => $USER->id,
        'cmid' => $attemptobj->get_cmid(),
    ]));
    $task->set_userid($USER->id);
    \core\task\manager::queue_adhoc_task($task);

    $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $attemptobj->get_cmid()]);
    redirect($quizurl, get_string('exportqueued', 'quiz_export'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Synchronous export (original behaviour).
    raise_memory_limit(MEMORY_HUGE);
    $timelimit = get_config('quiz_export', 'timelimit');
    set_time_limit($timelimit !== false ? (int) $timelimit : 600);

    $exporter = new quiz_export_engine();
    $pdf_file = $exporter->a2pdf($attemptobj, $exportoptions);

    $info = $exporter->get_additionnal_informations($attemptobj);
    $filename = $info['firstname'] . '_' . $info['lastname'] . '.pdf';

    // Store the file via File API so it appears in export history.
    $context = \context_module::instance($attemptobj->get_cmid());
    $fs = get_file_storage();
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'quiz_export',
        'filearea' => 'export',
        'itemid' => time(),
        'filepath' => '/',
        'filename' => $filename,
        'userid' => $USER->id,
    ];
    $fs->create_file_from_pathname($filerecord, $pdf_file);

    header("Content-Type: application/pdf");
    $displayfilename = $info['firstname'] . ' ' . $info['lastname'] . '.pdf';
    if ($inline) {
        header("Content-Disposition: inline; filename=\"" . $displayfilename . "\"");
    } else {
        header("Content-Disposition: attachment; filename=\"" . $displayfilename . "\"");
    }

    readfile($pdf_file);
    unlink($pdf_file);
}
