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
 * Download endpoint for asynchronously generated quiz export files.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$fileid = required_param('fileid', PARAM_INT);

require_login();

$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);

if (!$file || $file->get_component() !== 'quiz_export' || $file->get_filearea() !== 'export') {
    throw new \moodle_exception('filenotfound', 'error');
}

// Verify the file belongs to the current user.
if ((int) $file->get_userid() !== (int) $USER->id) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('downloadexport', 'quiz_export'));
}

send_stored_file($file, 0, 0, true);
