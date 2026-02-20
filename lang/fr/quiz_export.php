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

// export_form
$string['exportsettings'] = 'Paramètres d\'export';
$string['pagemode'] = 'Mode de pagination (La manière dont vont être gérés les sauts de page lors de l\'export PDF)';
$string['exportmodetruepage'] = 'Les pages de questions réellement posées';
$string['exportmodequestionperpage'] = 'Une seule question par page';
$string['exportmodesinglepage'] = 'Toutes les questions à la suite';

// Settings.
$string['timelimit'] = 'Limite de temps PHP pour la génération PDF';
$string['timelimitdesc'] = 'Temps d\'exécution maximum en secondes autorisé pour la génération des PDF. Augmentez cette valeur si les exports PDF échouent sur les quiz volumineux. Mettre à 0 pour illimité (non recommandé). Par défaut : 600 secondes (10 minutes).';

// Async export settings.
$string['asyncsingle'] = 'Activer l\'export individuel asynchrone';
$string['asyncsingledesc'] = 'Lorsque cette option est activée, les exports PDF individuels sont traités en arrière-plan via une tâche planifiée. L\'utilisateur reçoit une notification lorsque le fichier est prêt à être téléchargé.';
$string['asyncbulk'] = 'Activer l\'export groupé asynchrone';
$string['asyncbulkdesc'] = 'Lorsque cette option est activée, les exports PDF groupés (ZIP) sont traités en arrière-plan via une tâche planifiée. L\'utilisateur reçoit une notification lorsque le fichier est prêt à être téléchargé.';

// Async export notifications.
$string['exportqueued'] = 'Votre export est en cours de préparation. Vous recevrez une notification lorsqu\'il sera prêt.';
$string['exportcomplete'] = 'Votre export de quiz est prêt à être téléchargé.';
$string['exportcompletesubject'] = 'Export de quiz prêt';
$string['downloadexport'] = 'Télécharger l\'export';

// Adhoc task names.
$string['taskexportsingle'] = 'Exporter une tentative de quiz en PDF';
$string['taskexportattempts'] = 'Exporter des tentatives de quiz en PDF (groupé)';

// Message provider.
$string['messageprovider:exportcomplete'] = 'Notification d\'export de quiz terminé';

// Export history.
$string['previousexports'] = 'Exports précédents';
$string['noexportsyet'] = 'Aucun export n\'a encore été généré.';
$string['exportdate'] = 'Date';
$string['exportfilename'] = 'Nom du fichier';
$string['exportfilesize'] = 'Taille';
$string['exportdownload'] = 'Télécharger';
$string['exportstatus'] = 'Statut';
$string['exportstatuspending'] = 'En attente';
$string['exportstatusinprogress'] = 'En cours';
$string['exportstatuscomplete'] = 'Terminé';
$string['exportpending'] = 'Export en préparation…';

// Retention settings.
$string['retentiondays'] = 'Rétention des exports (jours)';
$string['retentiondaysdesc'] = 'Nombre de jours de conservation des fichiers d\'export avant suppression automatique. Mettre à 0 pour conserver les fichiers indéfiniment. Par défaut : 30 jours.';

// Scheduled task.
$string['taskcleanupexports'] = 'Nettoyage des fichiers d\'export de quiz expirés';

// Inside the pdf
$string['documenttitle'] = '{$a->coursename} <br> {$a->quizname} <br> - <br> Réponses de {$a->firstname} {$a->lastname}';
