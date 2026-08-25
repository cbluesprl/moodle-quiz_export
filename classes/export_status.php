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

namespace quiz_export;

use stdClass;

/**
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_status {

    const STATUS_PENDING = 'pending';
    const STATUS_INPROGRESS = 'inprogress';
    const STATUS_RETRYING = 'retrying';
    const STATUS_FAILED = 'failed';
    const STATUS_STALLED = 'stalled';
    const STALLED_THRESHOLD = 4 * HOURSECS;

    const LATE_PICKUP_THRESHOLD = 1800;

    protected $status;

    protected $task;

    /**
     * @param string $status One of the STATUS_* constants.
     * @param stdClass $task The task_adhoc record.
     */
    protected function __construct(string $status, stdClass $task) {
        $this->status = $status;
        $this->task = $task;
    }

    /**
     * @param stdClass $task A task_adhoc record with at least timestarted, faildelay, attemptsavailable,
     *                        nextruntime and timecreated.
     * @return self
     */
    public static function from_task_record(stdClass $task): self {
        $now = time();

        if (!empty($task->timestarted)) {
            if (($now - (int) $task->timestarted) > self::STALLED_THRESHOLD) {
                return new self(self::STATUS_STALLED, $task);
            }
            return new self(self::STATUS_INPROGRESS, $task);
        }

        if (!empty($task->faildelay)) {
            if (isset($task->attemptsavailable) && (int) $task->attemptsavailable <= 0) {
                return new self(self::STATUS_FAILED, $task);
            }
            return new self(self::STATUS_RETRYING, $task);
        }

        return new self(self::STATUS_PENDING, $task);
    }

    /**
     * @return string One of the STATUS_* constants.
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function is_failure(): bool {
        return in_array($this->status, [self::STATUS_RETRYING, self::STATUS_FAILED, self::STATUS_STALLED], true);
    }

    /**
     * @return string Translated, plain text.
     */
    public function get_label(): string {
        return get_string('exportstatus' . $this->status, 'quiz_export');
    }

    /**
     * Plugin owned CSS classes for the status badge.
     *
     * Bootstrap 4 badge-* classes were dropped in Bootstrap 5, so the plugin ships its own
     * classes in styles.css instead. This keeps the rendering identical from Moodle 4.4 to 5.2.
     *
     * @return string The full class attribute value of the badge.
     */
    public function get_badge_class(): string {
        switch ($this->status) {
            case self::STATUS_INPROGRESS:
            case self::STATUS_RETRYING:
                return 'quizexport-badge quizexport-badge-progress';
            case self::STATUS_FAILED:
            case self::STATUS_STALLED:
                return 'quizexport-badge quizexport-badge-error';
            default:
                return 'quizexport-badge quizexport-badge-pending';
        }
    }

    /**
     * @return string Translated, plain text. Empty when there is nothing worth saying.
     */
    public function get_detail(): string {
        $now = time();

        switch ($this->status) {
            case self::STATUS_INPROGRESS:
                return get_string('exportdetailinprogress', 'quiz_export',
                    format_time($now - (int) $this->task->timestarted));

            case self::STATUS_RETRYING:
                $nextrun = userdate((int) $this->task->nextruntime);
                if (!isset($this->task->attemptsavailable)) {
                    return get_string('exportdetailretryingnocount', 'quiz_export', $nextrun);
                }
                return get_string('exportdetailretrying', 'quiz_export', (object) [
                    'nextrun' => $nextrun,
                    'attempts' => (int) $this->task->attemptsavailable,
                ]);

            case self::STATUS_FAILED:
                return get_string('exportdetailfailed', 'quiz_export');

            case self::STATUS_STALLED:
                return get_string('exportdetailstalled', 'quiz_export',
                    format_time($now - (int) $this->task->timestarted));

            default:
                $waiting = $now - (int) $this->task->timecreated;
                if ($waiting > self::LATE_PICKUP_THRESHOLD) {
                    return get_string('exportdetailpendinglate', 'quiz_export', format_time($waiting));
                }
                return get_string('exportdetailpending', 'quiz_export', format_time($waiting));
        }
    }

    /**
     * @return string Translated, plain text. Empty when the export has not failed.
     */
    public function get_hint(): string {
        if (!$this->is_failure()) {
            return '';
        }
        return get_string('exportfailhint', 'quiz_export');
    }
}