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

use quiz_export\notification_helper;

/**
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class export_task_base extends \core\task\adhoc_task {
    const DEFAULT_TIME_LIMIT = 600;
    protected $completed = false;
    protected $notified = false;

    /**
     * @return void
     */
    final public function execute(): void {
        raise_memory_limit(MEMORY_HUGE);
        $this->raise_time_limit();

        $attemptsleft = $this->count_attempts_left();

        \core_shutdown_manager::register_function(function() use ($attemptsleft) {
            if (!$this->completed) {
                $this->notify_failure(get_string('exportfailedinterrupted', 'quiz_export'), $attemptsleft);
            }
        });

        try {
            $this->execute_export();
            $this->completed = true;
        } catch (\Throwable $e) {
            $this->notify_failure($e->getMessage(), $attemptsleft);
            throw $e;
        }
    }

    /**
     * @return void
     */
    abstract protected function execute_export(): void;

    /**
     * @return int Attempts remaining, or PHP_INT_MAX when the platform does not track them.
     */
    protected function count_attempts_left(): int {
        if (!method_exists($this, 'get_attempts_available')) {
            return PHP_INT_MAX;
        }
        return $this->get_attempts_available();
    }

    /**
     * @return void
     */
    protected function raise_time_limit(): void {
        $timelimit = get_config('quiz_export', 'timelimit');
        \core_php_time_limit::raise($timelimit !== false ? (int) $timelimit : self::DEFAULT_TIME_LIMIT);
    }

    /**
     * @param string $reason Technical reason, included in the message for support purposes.
     * @param int $attemptsleft Attempts remaining when this run started.
     * @return void
     */
    protected function notify_failure(string $reason, int $attemptsleft): void {
        if ($this->notified || $attemptsleft > 1) {
            return;
        }
        $this->notified = true;

        $data = $this->get_custom_data();
        if (empty($data->userid) || empty($data->cmid)) {
            return;
        }

        $reporturl = new \moodle_url('/mod/quiz/report.php', [
            'id' => $data->cmid,
            'mode' => 'export',
        ]);

        notification_helper::send_export_failed($data->userid, $reason, $reporturl->out(false));
    }
}