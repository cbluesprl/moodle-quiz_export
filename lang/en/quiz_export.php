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

$string['exportselected'] = 'Export selected attempts';

$string['exportattemptcheck'] = 'Export selected attempts?';

$string['exportattempt'] = 'Export attempt';

// Privacy.
$string['privacy:metadata'] = 'QuizExport plugin does not store any personal data.';

// export_form
$string['exportsettings'] = 'Export Settings';
$string['pagemode'] = 'Page mode (Page break mode while rendering quiz review before converting to PDF)';
$string['exportmodetruepage'] = 'Actual question page assignment';
$string['exportmodequestionperpage'] = 'One question per page';
$string['exportmodesinglepage'] = 'All questions on one page';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Summary of {$a->firstname} {$a->lastname}\'s attempt';
