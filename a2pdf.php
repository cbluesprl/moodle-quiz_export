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

require_once(__DIR__ . '/../../../../config.php');

global $CFG, $USER;

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

raise_memory_limit(MEMORY_HUGE);
set_time_limit(600);

$attemptid = required_param('attempt', PARAM_INT);
$pagemode = optional_param('pagemode', quiz_export_engine::PAGEMODE_TRUEPAGE, PARAM_INT);
$inline = optional_param('inline', 0, PARAM_INT);

// Get attempt object
$attemptobj = quiz_attempt::create($attemptid);
$attemptobj->preload_all_attempt_step_users();

// Check login and permissions
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
if (!$attemptobj->is_review_allowed() && $attemptobj->get_userid() != $USER->id) {
    throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noreviewattempt');
}

// Log this export.
// add_to_log($attemptobj->get_courseid(), 'quiz', 'export', 'a2pdf.php?attempt=' .
// $attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());

$exporter = new quiz_export_engine();
$pdf_file = $exporter->a2pdf($attemptobj, $pagemode);

header("Content-Type: application/pdf");
$info = $exporter->get_additionnal_informations($attemptobj);
$filename = $info['firstname'] . ' ' . $info['lastname'] . '.pdf';
if ($inline) {
    header("Content-Disposition: inline; filename=\"" . $filename . "\"");
} else {
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
}

readfile($pdf_file);
unlink($pdf_file);
