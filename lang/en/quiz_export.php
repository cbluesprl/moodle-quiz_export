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
$string['pagemode'] = 'Page mode (Page break mode while rendering quiz review before converting to PDF)';
$string['exportmodetruepage'] = 'Actual question page assignment';
$string['exportmodequestionperpage'] = 'One question per page';
$string['exportmodesinglepage'] = 'All questions on one page';

// Settings.
$string['timelimit'] = 'PHP time limit for PDF generation';
$string['timelimitdesc'] = 'Maximum execution time in seconds allowed for PDF generation. Increase this value if PDF exports time out on large quizzes. Set to 0 for unlimited (not recommended). Default: 600 seconds (10 minutes).';

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
$string['exportstatuscomplete'] = 'Complete';
$string['exportpending'] = 'Export being prepared…';

// Retention settings.
$string['retentiondays'] = 'Export retention (days)';
$string['retentiondaysdesc'] = 'Number of days to keep export files before automatic cleanup. Set to 0 to keep files indefinitely. Default: 30 days.';

// Scheduled task.
$string['taskcleanupexports'] = 'Clean up expired quiz export files';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Summary of {$a->firstname} {$a->lastname}\'s attempt';
