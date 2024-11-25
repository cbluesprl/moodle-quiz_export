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
 * This file defines the class to store the options for the quiz export report
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');


/**
 * Class to store the options for the quiz export report.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_options extends mod_quiz_attempts_report_options
{

    /** @var int Store the page mode for the quiz export report */
    public $pagemode = quiz_export_engine::PAGEMODE_QUESTIONPERPAGE;

    protected function get_url_params()
    {
        $params = parent::get_url_params();
        $params['pagemode'] = $this->pagemode;
        return $params;
    }

    public function get_initial_form_data()
    {
        $toform = parent::get_initial_form_data();
        $toform->pagemode = $this->pagemode;
        return $toform;
    }

    public function setup_from_form_data($fromform)
    {
        parent::setup_from_form_data($fromform);
        $this->pagemode = $fromform->pagemode;
    }

    public function setup_from_params()
    {
        parent::setup_from_params();
        $this->pagemode = optional_param('pagemode', $this->pagemode, PARAM_INT);
    }

    public function resolve_dependencies()
    {
        $this->checkboxcolumn = true;
    }
}
