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
$string['exportreport'] = 'Bericht Testversuch-Export'; // Used by mod/quiz/settings.php as the settings page title.

$string['exportselected'] = 'Ausgewählte Versuche exportieren';

$string['exportattemptcheck'] = 'Ausgewählte Versuche exportieren?';

$string['exportattempt'] = 'Versuch exportieren';

// export_form
$string['exportsettings'] = 'Exporteinstellungen';
$string['exportsettingsinfo'] = 'Diese Einstellungen gelten für die Exporte, die auf dieser Seite gestartet werden, sowohl für die Links zum Einzelexport als auch für den Sammelexport. Übernehmen Sie sie, bevor Sie exportieren.';
$string['applysettings'] = 'Einstellungen übernehmen';
$string['pagemode'] = 'Seitenwechsel (Modus beim Wechsel der Seiten während Erstellung der PDF-Datei)';
$string['exportmodetruepage'] = 'Momentane Frage-Seiten-Zuordnung';
$string['exportmodequestionperpage'] = 'Eine Frage pro Seite';
$string['exportmodesinglepage'] = 'Alle Fragen auf einer Seite';
$string['hidegeneralfeedback'] = 'Allgemeines Feedback ausblenden';
$string['hidegeneralfeedback_help'] = 'Wenn aktiviert, wird das allgemeine Feedback jeder Frage, also die Erläuterung, die allen Teilnehmenden nach Abschluss der Frage angezeigt wird, nicht in die PDF-Datei übernommen.';
$string['hiderightanswer'] = 'Musterlösungen ausblenden';
$string['hiderightanswer_help'] = 'Wenn aktiviert, wird die automatisch erzeugte richtige Antwort jeder Frage nicht in die PDF-Datei übernommen, damit die Teilnehmenden die Lösungen nicht einsehen können.';
$string['hideresponsehistory'] = 'Antworten-Rückblick ausblenden';
$string['hideresponsehistory_help'] = 'Wenn aktiviert, wird die Tabelle mit dem Antworten-Rückblick nicht in die PDF-Datei übernommen. Die Teilnehmenden können dadurch nicht sehen, wann eine Lehrperson die Bewertung geändert oder einen Kommentar hinzugefügt hat.';

// Settings.
$string['timelimit'] = 'PHP-Zeitlimit für die PDF-Erzeugung';
$string['timelimitdesc'] = 'Maximale Ausführungszeit in Sekunden für die PDF-Erzeugung. Diese Einstellung gilt nur für synchrone Exporte, die im Webrequest laufen: asynchrone Exporte laufen über den Cron und werden durch diese Einstellung nicht begrenzt. Erhöhen Sie diesen Wert, wenn synchrone PDF-Exporte bei umfangreichen Tests abbrechen. 0 bedeutet unbegrenzt (nicht empfohlen). Standard: 600 Sekunden (10 Minuten).';

// Async export settings.
$string['asyncsingle'] = 'Asynchronen Einzelexport aktivieren';
$string['asyncsingledesc'] = 'Wenn aktiviert, werden einzelne PDF-Exporte im Hintergrund über eine geplante Aufgabe verarbeitet. Die Nutzerin oder der Nutzer erhält eine Mitteilung, sobald die Datei zum Download bereitsteht.';
$string['asyncbulk'] = 'Asynchronen Sammelexport aktivieren';
$string['asyncbulkdesc'] = 'Wenn aktiviert, werden Sammelexporte (ZIP) im Hintergrund über eine geplante Aufgabe verarbeitet. Die Nutzerin oder der Nutzer erhält eine Mitteilung, sobald die Datei zum Download bereitsteht.';

// Async export notifications.
$string['exportqueued'] = 'Ihr Export wird vorbereitet. Sie erhalten eine Mitteilung, sobald er fertig ist.';
$string['exportcomplete'] = 'Ihr Testexport steht zum Download bereit.';
$string['exportcompletesubject'] = 'Testexport fertig';
$string['downloadexport'] = 'Export herunterladen';

// Adhoc task names.
$string['taskexportsingle'] = 'Einzelnen Testversuch als PDF exportieren';
$string['taskexportattempts'] = 'Testversuche als PDF exportieren (Sammelexport)';

// Message provider.
$string['messageprovider:exportcomplete'] = 'Mitteilung über abgeschlossenen Testexport';
$string['messageprovider:exportfailed'] = 'Mitteilung über fehlgeschlagenen Testexport';

// Export history.
$string['previousexports'] = 'Frühere Exporte';
$string['noexportsyet'] = 'Es wurden noch keine Exporte erzeugt.';
$string['exportdate'] = 'Datum';
$string['exportfilename'] = 'Dateiname';
$string['exportfilesize'] = 'Größe';
$string['exportdownload'] = 'Herunterladen';
$string['exportstatus'] = 'Status';
$string['exportstatuspending'] = 'Ausstehend';
$string['exportstatusinprogress'] = 'In Bearbeitung';
$string['exportstatusretrying'] = 'Fehlgeschlagen - weiterer Versuch geplant';
$string['exportstatusfailed'] = 'Fehlgeschlagen';
$string['exportstatusstalled'] = 'Abgebrochen';
$string['exportstatuscomplete'] = 'Abgeschlossen';
$string['exportpending'] = 'Export wird vorbereitet…';
$string['exportnofile'] = 'Keine Datei erzeugt';

// Export status details.
$string['exportdetailpending'] = 'Vor {$a} eingereiht, wartet auf den nächsten Durchlauf der geplanten Aufgaben.';
$string['exportdetailpendinglate'] = 'Vor {$a} eingereiht und bis jetzt nicht übernommen. Möglicherweise werden die geplanten Aufgaben auf dieser Plattform nicht ausgeführt.';
$string['exportdetailinprogress'] = 'Vor {$a} gestartet.';
$string['exportdetailretrying'] = 'Der Export ist fehlgeschlagen. Nächster Versuch: {$a->nextrun} (noch {$a->attempts} Versuch(e)).';
$string['exportdetailretryingnocount'] = 'Der Export ist fehlgeschlagen. Nächster Versuch: {$a}.';
$string['exportdetailfailed'] = 'Der Export ist fehlgeschlagen und wird nicht erneut versucht.';
$string['exportdetailstalled'] = 'Vor {$a} gestartet und immer noch nicht abgeschlossen. Die Verarbeitung wurde höchstwahrscheinlich abgebrochen.';
$string['exportfailhint'] = 'Das passiert meist, wenn der Test sehr viele Versuche enthält und der Export die maximale Ausführungszeit überschreitet. Exportieren Sie weniger Versuche auf einmal, oder bitten Sie Ihre Administration, das Zeitlimit für den Export zu erhöhen.';
$string['exporterrordetails'] = 'Technische Details';

// Failure notification.
$string['exportfailed'] = 'Ihr Testexport konnte nicht abgeschlossen werden.';
$string['exportfailedsubject'] = 'Testexport fehlgeschlagen';
$string['exportfailedinterrupted'] = 'Der Export wurde abgebrochen, bevor er abgeschlossen werden konnte (Ausführungszeit oder Speicherlimit überschritten).';

// Retention settings.
$string['retentiondays'] = 'Aufbewahrung der Exporte (Tage)';
$string['retentiondaysdesc'] = 'Anzahl der Tage, die Exportdateien vor der automatischen Bereinigung aufbewahrt werden. 0 bedeutet unbegrenzte Aufbewahrung. Standard: 30 Tage.';

// Scheduled task.
$string['taskcleanupexports'] = 'Abgelaufene Testexport-Dateien bereinigen';

// Settings.

// Async export settings.

// Async export notifications.

// Adhoc task names.

// Message provider.

// Export history.

// Retention settings.

// Scheduled task.

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Zusammenfassung des Versuchs von {$a->firstname} {$a->lastname}';
$string['unplacedlabels'] = 'Nicht platzierte Etiketten:';
