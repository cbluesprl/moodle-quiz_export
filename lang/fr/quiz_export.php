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
$string['export'] = 'Export de quiz'; // displayed in the navigation menu

$string['exportselected'] = 'Exporter les tentatives sélectionnées';

$string['exportattemptcheck'] = 'Exporter les tentatives sélectionnées ?';

$string['exportattempt'] = 'Exporter cette tentative en pdf';

// Privacy.
$string['privacy:metadata'] = 'Le plugin QuizExport ne stocke aucune donnée personnelle.';

// export_form
$string['exportsettings'] = 'Paramètres d\'export';
$string['pagemode'] = 'Mode de pagination (La manière dont vont être gérés les sauts de page lors de l\'export PDF)';
$string['exportmodetruepage'] = 'Les pages de questions réellement posées';
$string['exportmodequestionperpage'] = 'Une seule question par page';
$string['exportmodesinglepage'] = 'Toutes les questions à la suite';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Réponses de {$a->firstname} {$a->lastname}';
