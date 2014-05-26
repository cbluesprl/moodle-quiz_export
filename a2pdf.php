<?php

/**
 * This file downloads a single quiz attempt.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

$attemptid = required_param('attempt', PARAM_INT);
$pagemode      = optional_param('pagemode', 1, PARAM_INT);
$inline      = optional_param('inline', 0, PARAM_INT);


// get attempt object
$attemptobj = quiz_attempt::create($attemptid);

// Check login and permissions
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
if (!$attemptobj->is_review_allowed()) {
	throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noreviewattempt');
}

// // Log this export.
// add_to_log($attemptobj->get_courseid(), 'quiz', 'export', 'a2pdf.php?attempt=' .
// 	$attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());

$exporter = new quiz_export_engine();
$pdf_file = $exporter->a2pdf($attemptobj, $pagemode);

header("Content-Type: application/pdf");
if ($inline) {
	header("Content-Disposition: inline; filename=\"quiz.pdf\"");
} else {
	header("Content-Disposition: attachment; filename=\"quiz.pdf\"");
}

readfile($pdf_file);
unlink($pdf_file);
