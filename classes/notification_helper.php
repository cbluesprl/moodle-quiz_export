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

/**
 * Helper class for sending export complete notifications.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_helper {

    /**
     * Send a notification to the user that their export is ready for download.
     *
     * @param int $userid The user ID to notify.
     * @param string $filename The name of the exported file.
     * @param string $downloadurl The URL to download the file.
     * @return int|false The message ID on success, false on failure.
     */
    public static function send_export_complete(int $userid, string $filename, string $downloadurl) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $message = new \core\message\message();
        $message->component = 'quiz_export';
        $message->name = 'exportcomplete';
        $message->notification = 1;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('exportcompletesubject', 'quiz_export');
        $message->fullmessage = get_string('exportcomplete', 'quiz_export') . "\n\n" . $filename . "\n" . $downloadurl;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = \html_writer::tag('p', get_string('exportcomplete', 'quiz_export'))
            . \html_writer::link($downloadurl, get_string('downloadexport', 'quiz_export'));
        $message->smallmessage = get_string('exportcomplete', 'quiz_export');
        $message->contexturl = $downloadurl;
        $message->contexturlname = get_string('downloadexport', 'quiz_export');

        return message_send($message);
    }

    /**
     * @param int $userid The user ID to notify.
     * @param string $reason Technical reason of the failure, shown as a support detail.
     * @param string $reporturl The URL of the export report.
     * @return int|false The message ID on success, false on failure.
     */
    public static function send_export_failed(int $userid, string $reason, string $reporturl) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $intro = get_string('exportfailed', 'quiz_export');
        $hint = get_string('exportfailhint', 'quiz_export');

        $message = new \core\message\message();
        $message->component = 'quiz_export';
        $message->name = 'exportfailed';
        $message->notification = 1;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('exportfailedsubject', 'quiz_export');
        $message->fullmessage = $intro . "\n\n" . $hint . "\n\n" . $reason . "\n" . $reporturl;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = \html_writer::tag('p', $intro)
            . \html_writer::tag('p', $hint)
            . \html_writer::tag('p', s($reason), ['style' => 'color: #6c757d; font-size: 0.875em;']);
        $message->smallmessage = $intro;
        $message->contexturl = $reporturl;
        $message->contexturlname = get_string('previousexports', 'quiz_export');

        return message_send($message);
    }
}
