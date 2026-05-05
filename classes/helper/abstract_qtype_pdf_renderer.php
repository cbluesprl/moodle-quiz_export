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
 * Abstract base for question-type specific PDF renderers used by quiz_export.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export\helper;

use question_attempt;
use question_attempt_step;
use question_display_options;

defined('MOODLE_INTERNAL') || die();

/**
 * Base contract for converting interactive drag-and-drop question HTML into
 * a static SVG fragment compatible with mPDF.
 *
 * mPDF only honours position:absolute on top-level blocks, which makes plain
 * HTML overlays unusable for nested layouts. SVG is rendered as a single
 * image-like unit by mPDF and gives us pixel-accurate placement.
 */
abstract class abstract_qtype_pdf_renderer {

    /** @var string Raw HTML of the outer question block (div.que.TYPE). */
    protected $questionhtml;

    /** @var question_attempt The attempt for this question slot. */
    protected $questionattempt;

    /** @var question_display_options|null Review display options. */
    protected $displayoptions;

    public function __construct(
        string $questionhtml,
        question_attempt $questionattempt,
        ?question_display_options $displayoptions = null
    ) {
        $this->questionhtml = $questionhtml;
        $this->questionattempt = $questionattempt;
        $this->displayoptions = $displayoptions;
    }

    /**
     * Produce the PDF-friendly HTML replacing the interactive question block.
     */
    abstract public function render_for_pdf(): string;

    /**
     * Whether correctness markers (right/wrong icons) should be displayed.
     */
    protected function should_show_correctness(): bool {
        if ($this->displayoptions === null) {
            return false;
        }
        return !empty($this->displayoptions->correctness)
            && $this->questionattempt->get_state()->is_finished();
    }

    /**
     * Build the SVG node carrying a check / cross icon.
     */
    protected function build_correctness_svg(bool $iscorrect, float $cx, float $cy, float $size): string {
        $color = $iscorrect ? '#2a8a2a' : '#c83737';
        $half = $size / 2;
        if ($iscorrect) {
            $path = 'M' . ($cx - $half * 0.6) . ' ' . $cy
                . ' L' . ($cx - $half * 0.1) . ' ' . ($cy + $half * 0.5)
                . ' L' . ($cx + $half * 0.7) . ' ' . ($cy - $half * 0.6);
        } else {
            $path = 'M' . ($cx - $half * 0.6) . ' ' . ($cy - $half * 0.6)
                . ' L' . ($cx + $half * 0.6) . ' ' . ($cy + $half * 0.6)
                . ' M' . ($cx + $half * 0.6) . ' ' . ($cy - $half * 0.6)
                . ' L' . ($cx - $half * 0.6) . ' ' . ($cy + $half * 0.6);
        }
        return '<path d="' . $path . '" stroke="' . $color . '" stroke-width="3" '
            . 'fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    /**
     * Replace the first <div> whose class attribute carries the given token
     * by the supplied HTML, preserving everything outside that block (info,
     * outcome, history…). Depth-aware to handle nested divs.
     */
    protected function replace_div_with_class(string $classname, string $replacement): string {
        $html = $this->questionhtml;
        $pattern = '#<div\b[^>]*\bclass="(?:[^"]*\s)?'
            . preg_quote($classname, '#')
            . '(?:\s[^"]*)?"#';
        if (!preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $tagstart = $match[0][1];

        $cursor = $tagstart;
        $depth = 0;
        $length = strlen($html);
        while ($cursor < $length) {
            $next = strpos($html, '<', $cursor);
            if ($next === false) {
                return $html;
            }
            if (substr($html, $next, 4) === '<div') {
                $depth++;
                $cursor = $next + 4;
                continue;
            }
            if (substr($html, $next, 6) === '</div>') {
                $depth--;
                $cursor = $next + 6;
                if ($depth === 0) {
                    return substr($html, 0, $tagstart)
                        . $replacement
                        . substr($html, $cursor);
                }
                continue;
            }
            $cursor = $next + 1;
        }
        return $html;
    }

    /**
     * Resolve a response value (1-based choiceorder index) to the actual
     * array key in $question->choices[$group], using the same data Moodle
     * stored at start_attempt() time.
     *
     * Reads $question->choiceorder when populated; otherwise falls back to
     * the `_choiceorder{G}` qt_var directly (robust to lazy-init flows where
     * apply_attempt_state has not been re-run yet); finally falls back to
     * identity when the question doesn't shuffle.
     *
     * Returns null when no mapping can be inferred safely.
     */
    protected function lookup_choice_key(int $group, int $responsevalue): ?int {
        if ($responsevalue === 0) {
            return null;
        }
        $question = $this->questionattempt->get_question();

        if (isset($question->choiceorder[$group][$responsevalue])) {
            return (int) $question->choiceorder[$group][$responsevalue];
        }

        $stored = $this->questionattempt->get_last_qt_var('_choiceorder' . $group);
        if ($stored !== null && $stored !== '') {
            $orderkeys = explode(',', $stored);
            if (isset($orderkeys[$responsevalue - 1])) {
                return (int) $orderkeys[$responsevalue - 1];
            }
        }

        if (empty($question->shufflechoices)
                && isset($question->choices[$group][$responsevalue])) {
            return $responsevalue;
        }

        return null;
    }

    /**
     * Bootstrap $question->choiceorder for gapselect-derived question types.
     *
     * Moodle stores the per-attempt shuffled order in a `_choiceorder{G}` qt_var
     * during start_attempt(). Calling apply_attempt_state() with a step that
     * carries those vars rehydrates $question->choiceorder so all the standard
     * methods (get_ordered_choices, get_right_choice_for…) return correct data.
     *
     * Reading via get_last_qt_var() (instead of get_step(0)) is robust to
     * lazy-init flows where the data lives in a non-zero step.
     */
    protected function ensure_question_state_applied(): void {
        $question = $this->questionattempt->get_question();
        if (!empty($question->choiceorder)) {
            return;
        }
        if (empty($question->choices) || !method_exists($question, 'apply_attempt_state')) {
            return;
        }

        $vars = [];
        foreach ($question->choices as $group => $groupchoices) {
            $stored = $this->questionattempt->get_last_qt_var('_choiceorder' . $group);
            if ($stored !== null && $stored !== '') {
                $vars['_choiceorder' . $group] = $stored;
            } else if (empty($question->shufflechoices)) {
                $vars['_choiceorder' . $group] = implode(',', array_keys($groupchoices));
            } else {
                return;
            }
        }

        try {
            $question->apply_attempt_state(new question_attempt_step($vars));
        } catch (\Throwable $exception) {
            debugging('quiz_export: apply_attempt_state failed: '
                . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Resolve the natural pixel size of the background image.
     *
     * @return array{0:int,1:int} Width and height in pixels (zeros when unknown).
     */
    protected function get_background_image_size(): array {
        $file = $this->find_first_file('bgimage', $this->get_question_id());
        if ($file === null) {
            return [0, 0];
        }
        $imageinfo = $file->get_imageinfo();
        if (!empty($imageinfo['width']) && !empty($imageinfo['height'])) {
            return [(int) $imageinfo['width'], (int) $imageinfo['height']];
        }
        return [0, 0];
    }

    /**
     * Build a base64 data URI for an image stored in a question filearea.
     */
    protected function get_image_data_uri(string $filearea, int $itemid): ?string {
        $file = $this->find_first_file($filearea, $itemid);
        if ($file === null) {
            return null;
        }
        $mimetype = $file->get_mimetype() ?: 'image/png';
        return 'data:' . $mimetype . ';base64,' . base64_encode($file->get_content());
    }

    /**
     * Build a data URI together with the natural pixel dimensions of an
     * image stored in a question filearea, in a single file_storage hit.
     *
     * @return array{data:string, width:int, height:int}|null
     */
    protected function get_image_info(string $filearea, int $itemid): ?array {
        $file = $this->find_first_file($filearea, $itemid);
        if ($file === null) {
            return null;
        }
        $imageinfo = $file->get_imageinfo();
        if (empty($imageinfo['width']) || empty($imageinfo['height'])) {
            return null;
        }
        $mimetype = $file->get_mimetype() ?: 'image/png';
        return [
            'data' => 'data:' . $mimetype . ';base64,' . base64_encode($file->get_content()),
            'width' => (int) $imageinfo['width'],
            'height' => (int) $imageinfo['height'],
        ];
    }

    protected function find_first_file(string $filearea, int $itemid) {
        $component = $this->get_question_component_name();
        $contextid = $this->get_question_context_id();
        if ($component === '' || $contextid === 0) {
            return null;
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'id', false);
        foreach ($files as $file) {
            if (!$file->is_directory()) {
                return $file;
            }
        }
        return null;
    }

    protected function get_question_id(): int {
        $question = $this->questionattempt->get_question();
        return isset($question->id) ? (int) $question->id : 0;
    }

    protected function get_question_context_id(): int {
        $question = $this->questionattempt->get_question();
        return isset($question->contextid) ? (int) $question->contextid : 0;
    }

    protected function get_question_component_name(): string {
        $question = $this->questionattempt->get_question();
        if (!isset($question->qtype)) {
            return '';
        }
        return (string) $question->qtype->plugin_name();
    }

    protected function svg_escape(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render the "unplaced labels" section: a centered block with a heading
     * and a row of bordered pills, each containing the supplied inner HTML.
     *
     * Subclasses are responsible for computing the unplaced contents in their
     * own format (qtype-specific) and passing them as already-safe HTML
     * fragments (text choices must be htmlspecialchars-escaped first).
     *
     * @param array<int, string> $innercontents Pre-built HTML to wrap in pills.
     */
    protected function render_unplaced_section(array $innercontents): string {
        if (empty($innercontents)) {
            return '';
        }
        $pillstyle = 'display:inline-block; padding:3pt 8pt; '
            . 'border:1px solid #999; border-radius:4px; '
            . 'margin:4pt 8pt; background:#f5f5f5; '
            . 'font-family: Helvetica, Arial, sans-serif; font-size:10pt;';
        $pills = [];
        foreach ($innercontents as $inner) {
            $pills[] = '<span style="' . $pillstyle . '">' . $inner . '</span>';
        }
        // mPDF can collapse margins on adjacent inline-blocks; an explicit
        // separator guarantees a visible gap between consecutive pills.
        $separator = '&nbsp;&nbsp;';
        $heading = htmlspecialchars(
            get_string('unplacedlabels', 'quiz_export'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        return '<div class="quiz_export-dd-unplaced" '
            . 'style="margin-top:8pt; text-align:center;">'
            . '<div style="font-weight:bold; color:#555; margin-bottom:4pt;">'
            . $heading . '</div>'
            . implode($separator, $pills)
            . '</div>';
    }
}
