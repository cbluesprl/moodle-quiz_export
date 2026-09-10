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

use quiz_export\export_service;
use quiz_export\notification_helper;
use quiz_export\pdf_options;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to export a single quiz attempt as PDF asynchronously.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_single_attempt extends export_task_base {

    /**
     * Return the name of this task.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('taskexportsingle', 'quiz_export');
    }

    /**
     * Generate the PDF and store it via the File API.
     *
     * Expected custom data:
     * - attemptid (int): The quiz attempt ID.
     * - pagemode (int): The page break mode.
     * - hidegeneralfeedback (int): Leave the general feedback out of the PDF.
     * - hiderightanswer (int): Leave the correct answers out of the PDF.
     * - hideresponsehistory (int): Leave the response history out of the PDF.
     * - inline (int): Whether the PDF was requested inline.
     * - userid (int): The user who requested the export.
     * - cmid (int): The course module ID for the quiz.
     */
    protected function execute_export(): void {
        $data = $this->get_custom_data();

        $service = new export_service();
        $storedfile = $service->export_single(
            (int) $data->attemptid,
            pdf_options::from_data($data),
            (int) $data->userid,
            (int) $data->cmid
        );

        $reporturl = new \moodle_url('/mod/quiz/report.php', [
            'id' => $data->cmid,
            'mode' => 'export',
        ]);

        notification_helper::send_export_complete(
            (int) $data->userid,
            $storedfile->get_filename(),
            $reporturl->out(false)
        );
    }
}
