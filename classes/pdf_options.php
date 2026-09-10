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

use question_display_options;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/export/export.php');

/**
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_options {

    public $pagemode;

    public $hidegeneralfeedback;

    public $hiderightanswer;

    public $hideresponsehistory;

    /**
     * @param int $pagemode Page break mode, one of the \quiz_export_engine::PAGEMODE_* constants.
     * @param bool $hidegeneralfeedback Leave the general feedback out of the PDF.
     * @param bool $hiderightanswer Leave the automatically generated correct answer out of the PDF.
     * @param bool $hideresponsehistory Leave the response history table out of the PDF.
     */
    public function __construct(int $pagemode = \quiz_export_engine::PAGEMODE_TRUEPAGE,
            bool $hidegeneralfeedback = false, bool $hiderightanswer = false, bool $hideresponsehistory = false) {
        $this->pagemode = $pagemode;
        $this->hidegeneralfeedback = $hidegeneralfeedback;
        $this->hiderightanswer = $hiderightanswer;
        $this->hideresponsehistory = $hideresponsehistory;
    }

    /**
     * @param int|self|null $options A pdf_options instance, a legacy page mode, or null for the defaults.
     * @return self
     */
    public static function create_from($options): self {
        if ($options instanceof self) {
            return $options;
        }
        if (is_null($options)) {
            return new self();
        }
        return new self((int) $options);
    }

    /**
     * @param stdClass|object $data Any object exposing the option properties.
     * @return self
     */
    public static function from_data($data): self {
        return new self(
            isset($data->pagemode) ? (int) $data->pagemode : \quiz_export_engine::PAGEMODE_TRUEPAGE,
            !empty($data->hidegeneralfeedback),
            !empty($data->hiderightanswer),
            !empty($data->hideresponsehistory)
        );
    }

    /**
     * @return self
     */
    public static function from_params(): self {
        return new self(
            optional_param('pagemode', \quiz_export_engine::PAGEMODE_TRUEPAGE, PARAM_INT),
            (bool) optional_param('hidegeneralfeedback', 0, PARAM_BOOL),
            (bool) optional_param('hiderightanswer', 0, PARAM_BOOL),
            (bool) optional_param('hideresponsehistory', 0, PARAM_BOOL)
        );
    }

    /**
     * @return array Parameter name => value.
     */
    public function to_array(): array {
        return [
            'pagemode' => $this->pagemode,
            'hidegeneralfeedback' => (int) $this->hidegeneralfeedback,
            'hiderightanswer' => (int) $this->hiderightanswer,
            'hideresponsehistory' => (int) $this->hideresponsehistory,
        ];
    }

    /**
     * Applies the options to the review rendering, dropping the grading link which cannot be followed from a PDF.
     *
     * @param question_display_options $displayoptions The display options of the attempt being exported.
     * @return void
     */
    public function apply(question_display_options $displayoptions): void {
        $displayoptions->manualcommentlink = null;
        if ($this->hidegeneralfeedback) {
            $displayoptions->generalfeedback = question_display_options::HIDDEN;
        }
        if ($this->hiderightanswer) {
            $displayoptions->rightanswer = question_display_options::HIDDEN;
        }
        if ($this->hideresponsehistory) {
            $displayoptions->history = question_display_options::HIDDEN;
        }
    }
}