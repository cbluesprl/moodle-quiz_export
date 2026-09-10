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
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
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

    $redirecturl = $returnurl !== '' ? new moodle_url($returnurl)
        : new moodle_url('/mod/quiz/report.php', ['id' => $attemptobj->get_cmid(), 'mode' => 'export']);
    redirect($redirecturl, get_string('exportqueued', 'quiz_export'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Synchronous export (original behaviour).
    $service = new \quiz_export\export_service();
    $storedfile = $service->export_single($attemptid, $exportoptions, $USER->id, $attemptobj->get_cmid());

    // Compose a human-friendly display filename (spaces) distinct from the
    // stored filename (underscores) used in the export history.
    $exporter = new quiz_export_engine();
    $info = $exporter->get_additionnal_informations($attemptobj);
    $displayfilename = $info['firstname'] . ' ' . $info['lastname'] . '.pdf';

    header('Content-Type: application/pdf');
    if ($inline) {
        header('Content-Disposition: inline; filename="' . $displayfilename . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $displayfilename . '"');
    }

    $storedfile->readfile();
}
