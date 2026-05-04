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
$string['export'] = 'Export Testversuche'; // displayed in the navigation menu
$string['exportreport'] = 'QuizExport settings'; // displayed in the admin menu

$string['exportselected'] = 'Ausgewählte Versuche exportieren';

$string['exportattemptcheck'] = 'Ausgewählte Versuche exportieren?';

$string['exportattempt'] = 'Versuch exportieren';

// export_form
$string['exportsettings'] = 'Exporteinstellungen';
$string['pagemode'] = 'Seitenwechsel (Modus beim Wechsel der Seiten während Erstellung der PDF-Datei)';
$string['exportmodetruepage'] = 'Momentane Frage-Seiten-Zuordnung';
$string['exportmodequestionperpage'] = 'Eine Frage pro Seite';
$string['exportmodesinglepage'] = 'Alle Fragen auf einer Seite';

// Settings.
$string['timelimit'] = 'PHP-Zeitlimit für die PDF-Erstellung';
$string['timelimitdesc'] = 'Maximal zulässige Ausführungszeit in Sekunden für die PDF-Erstellung. Erhöhen Sie diesen Wert, wenn PDF-Exporte bei großen Tests fehlschlagen. Auf 0 setzen für unbegrenzt (nicht empfohlen). Standard: 600 Sekunden (10 Minuten).';

// Async export settings.
$string['asyncsingle'] = 'Asynchronen Einzelexport aktivieren';
$string['asyncsingledesc'] = 'Wenn aktiviert, werden einzelne PDF-Exporte im Hintergrund über eine geplante Aufgabe verarbeitet. Der Benutzer erhält eine Benachrichtigung, sobald die Datei zum Download bereit ist.';
$string['asyncbulk'] = 'Asynchronen Massenexport aktivieren';
$string['asyncbulkdesc'] = 'Wenn aktiviert, werden Massen-PDF-Exporte (ZIP) im Hintergrund über eine geplante Aufgabe verarbeitet. Der Benutzer erhält eine Benachrichtigung, sobald die Datei zum Download bereit ist.';

// Async export notifications.
$string['exportqueued'] = 'Ihr Export wird vorbereitet. Sie erhalten eine Benachrichtigung, sobald er bereit ist.';
$string['exportcomplete'] = 'Ihr Testexport steht zum Download bereit.';
$string['exportcompletesubject'] = 'Testexport bereit';
$string['downloadexport'] = 'Export herunterladen';

// Adhoc task names.
$string['taskexportsingle'] = 'Einzelnen Testversuch als PDF exportieren';
$string['taskexportattempts'] = 'Testversuche als PDF exportieren (Massenexport)';

// Message provider.
$string['messageprovider:exportcomplete'] = 'Benachrichtigung über abgeschlossenen Testexport';

// Export history.
$string['previousexports'] = 'Frühere Exporte';
$string['noexportsyet'] = 'Es wurden noch keine Exporte erstellt.';
$string['exportdate'] = 'Datum';
$string['exportfilename'] = 'Dateiname';
$string['exportfilesize'] = 'Größe';
$string['exportdownload'] = 'Herunterladen';
$string['exportstatus'] = 'Status';
$string['exportstatuspending'] = 'Ausstehend';
$string['exportstatusinprogress'] = 'In Bearbeitung';
$string['exportstatuscomplete'] = 'Abgeschlossen';
$string['exportpending'] = 'Export wird vorbereitet…';

// Retention settings.
$string['retentiondays'] = 'Aufbewahrung der Exporte (Tage)';
$string['retentiondaysdesc'] = 'Anzahl der Tage, die Exportdateien vor der automatischen Bereinigung aufbewahrt werden. Auf 0 setzen, um Dateien unbegrenzt aufzubewahren. Standard: 30 Tage.';

// Scheduled task.
$string['taskcleanupexports'] = 'Bereinigung abgelaufener Testexport-Dateien';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Zusammenfassung des Versuchs von {$a->firstname} {$a->lastname}';
$string['unplacedlabels'] = 'Nicht platzierte Etiketten:';
