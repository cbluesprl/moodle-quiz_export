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
 * Strings for component 'quiz_export', language 'en'
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'QuizExport';
$string['export'] = 'Quiz exporting'; // displayed in the navigation menu
$string['exportreport'] = 'Quiz export report'; // Used by mod/quiz/settings.php as the settings page title.

$string['exportselected'] = 'Export selected attempts';

$string['exportattemptcheck'] = 'Export selected attempts?';

$string['exportattempt'] = 'Export attempt';

// export_form
$string['exportsettings'] = 'Export Settings';
$string['exportsettingsinfo'] = 'These settings apply to the exports started from this page, both the single attempt links and the bulk export. Apply them before you export.';
$string['applysettings'] = 'Apply settings';
$string['pagemode'] = 'Page mode (Page break mode while rendering quiz review before converting to PDF)';
$string['exportmodetruepage'] = 'Actual question page assignment';
$string['exportmodequestionperpage'] = 'One question per page';
$string['exportmodesinglepage'] = 'All questions on one page';
$string['hidegeneralfeedback'] = 'Hide general feedback';
$string['hidegeneralfeedback_help'] = 'When ticked, the general feedback of each question, that is the explanation shown to every student once the question is finished, is left out of the PDF.';
$string['hiderightanswer'] = 'Hide correct answers';
$string['hiderightanswer_help'] = 'When ticked, the automatically generated correct answer of each question is left out of the PDF, so that students cannot read the solutions.';
$string['hideresponsehistory'] = 'Hide response history';
$string['hideresponsehistory_help'] = 'When ticked, the response history table is left out of the PDF. Students therefore cannot see when a teacher changed the grade or added a comment.';

// Settings.
$string['timelimit'] = 'PHP time limit for PDF generation';
$string['timelimitdesc'] = 'Maximum execution time in seconds allowed for PDF generation. This only applies to synchronous exports, which run in the web request: asynchronous exports run under cron and are not limited by this setting. Increase this value if synchronous PDF exports time out on large quizzes. Set to 0 for unlimited (not recommended). Default: 600 seconds (10 minutes).';

// Async export settings.
$string['asyncsingle'] = 'Enable asynchronous single export';
$string['asyncsingledesc'] = 'When enabled, individual PDF exports are processed in the background via a scheduled task. The user receives a notification when the file is ready for download.';
$string['asyncbulk'] = 'Enable asynchronous bulk export';
$string['asyncbulkdesc'] = 'When enabled, bulk PDF exports (ZIP) are processed in the background via a scheduled task. The user receives a notification when the file is ready for download.';

// Async export notifications.
$string['exportqueued'] = 'Your export is being prepared. You will receive a notification when it\'s ready.';
$string['exportcomplete'] = 'Your quiz export is ready for download.';
$string['exportcompletesubject'] = 'Quiz export ready';
$string['downloadexport'] = 'Download export';

// Adhoc task names.
$string['taskexportsingle'] = 'Export single quiz attempt as PDF';
$string['taskexportattempts'] = 'Export quiz attempts as PDF (bulk)';

// Message provider.
$string['messageprovider:exportcomplete'] = 'Quiz export complete notification';

// Export history.
$string['previousexports'] = 'Previous exports';
$string['noexportsyet'] = 'No exports have been generated yet.';
$string['exportdate'] = 'Date';
$string['exportfilename'] = 'Filename';
$string['exportfilesize'] = 'Size';
$string['exportdownload'] = 'Download';
$string['exportstatus'] = 'Status';
$string['exportstatuspending'] = 'Pending';
$string['exportstatusinprogress'] = 'In progress';
$string['exportstatusretrying'] = 'Failed - will retry';
$string['exportstatusfailed'] = 'Failed';
$string['exportstatusstalled'] = 'Interrupted';
$string['exportstatuscomplete'] = 'Complete';
$string['exportpending'] = 'Export being prepared…';
$string['exportnofile'] = 'No file generated';

// Export status details.
$string['exportdetailpending'] = 'Queued {$a} ago, waiting for the next scheduled run.';
$string['exportdetailpendinglate'] = 'Queued {$a} ago and not picked up yet. The scheduled task runner may not be running on this site.';
$string['exportdetailinprogress'] = 'Started {$a} ago.';
$string['exportdetailretrying'] = 'The export failed. Next attempt: {$a->nextrun} ({$a->attempts} attempt(s) left).';
$string['exportdetailretryingnocount'] = 'The export failed. Next attempt: {$a}.';
$string['exportdetailfailed'] = 'The export failed and will not be retried.';
$string['exportdetailstalled'] = 'Started {$a} ago and still not finished. The process was most likely interrupted.';
$string['exportfailhint'] = 'This usually happens when the quiz has a large number of attempts and the export exceeds the maximum execution time. Try exporting fewer attempts at a time, or ask your administrator to raise the export time limit.';
$string['exporterrordetails'] = 'Technical details';

// Failure notification.
$string['exportfailed'] = 'Your quiz export could not be completed.';
$string['exportfailedsubject'] = 'Quiz export failed';
$string['exportfailedinterrupted'] = 'The export was interrupted before it could finish (execution time or memory limit reached).';
$string['messageprovider:exportfailed'] = 'Quiz export failure notification';

// Retention settings.
$string['retentiondays'] = 'Export retention (days)';
$string['retentiondaysdesc'] = 'Number of days to keep export files before automatic cleanup. Set to 0 to keep files indefinitely. Default: 30 days.';

// Scheduled task.
$string['taskcleanupexports'] = 'Clean up expired quiz export files';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Summary of {$a->firstname} {$a->lastname}\'s attempt';
