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

namespace quiz_export\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to clean up old quiz export files.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_exports extends \core\task\scheduled_task {

    /**
     * Return the name of this task.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('taskcleanupexports', 'quiz_export');
    }

    /**
     * Execute the task: delete export files older than the configured retention period.
     */
    public function execute(): void {
        global $DB;

        $retentiondays = (int) get_config('quiz_export', 'retentiondays');
        if ($retentiondays <= 0) {
            $retentiondays = 30;
        }

        $cutoff = time() - ($retentiondays * DAYSECS);

        $sql = "SELECT f.id
                  FROM {files} f
                 WHERE f.component = :component
                   AND f.filearea = :filearea
                   AND f.timecreated < :cutoff";

        $params = [
            'component' => 'quiz_export',
            'filearea' => 'export',
            'cutoff' => $cutoff,
        ];

        $fileids = $DB->get_fieldset_sql($sql, $params);

        if (empty($fileids)) {
            return;
        }

        $fs = get_file_storage();
        $count = 0;

        foreach ($fileids as $fileid) {
            $file = $fs->get_file_by_id($fileid);
            if ($file) {
                $file->delete();
                $count++;
            }
        }

        mtrace("quiz_export cleanup: deleted {$count} expired export file(s).");
    }
}
