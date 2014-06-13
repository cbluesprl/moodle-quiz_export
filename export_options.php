<?php

/**
 * Class to store the options for the quiz export report.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');


/**
 * Class to store the options for the quiz export report.
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_options extends mod_quiz_attempts_report_options {

	public $pagemode = quiz_export_config::PAGEMODE_DEFAULT;
	
	protected function get_url_params() {
		$params = parent::get_url_params();
		$params['pagemode'] = $this->pagemode;
		return $params;
	}

	public function get_initial_form_data() {
		$toform = parent::get_initial_form_data();
		$toform->pagemode = $this->pagemode;
		return $toform;
	}

	public function setup_from_form_data($fromform) {
		parent::setup_from_form_data($fromform);
		$this->pagemode = $fromform->pagemode;
	}

	public function setup_from_params() {
		parent::setup_from_params();
		$this->pagemode = optional_param('pagemode', $this->pagemode, PARAM_INT);
	}

	public function resolve_dependencies() {
		$this->checkboxcolumn = true;
	}
}
