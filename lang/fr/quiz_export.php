<?php

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

// export_form
$string['exportsettings'] = 'Paramètres d\'export';
$string['pagemode'] = 'Mode de pagination (La manière dont vont être gérés les sauts de page lors de l\'export PDF)';
$string['exportmodetruepage'] = 'Les pages de questions réellement posées';
$string['exportmodequestionperpage'] = 'Une seule question par page';
$string['exportmodesinglepage'] = 'Toutes les questions à la suite';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Réponses de {$a->firstname} {$a->lastname}';
