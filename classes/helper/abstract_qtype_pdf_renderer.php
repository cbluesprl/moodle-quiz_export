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
use question_display_options;

defined('MOODLE_INTERNAL') || die();

/**
 * Base contract for converting interactive drag-and-drop question HTML into
 * a static SVG fragment compatible with mPDF.
 *
 * mPDF only honours position:absolute on top-level blocks, which makes plain
 * HTML overlays unusable for nested layouts. SVG is rendered as a single
 * image-like unit by mPDF and gives us pixel-accurate placement, so concrete
 * implementations build an inline SVG containing the background image plus
 * absolutely positioned overlays.
 */
abstract class abstract_qtype_pdf_renderer {

    /** @var string Raw HTML of the outer question block (div.que.TYPE). */
    protected $questionhtml;

    /** @var question_attempt The attempt for this question slot. */
    protected $questionattempt;

    /** @var question_display_options|null Review display options. */
    protected $displayoptions;

    /**
     * @param string $questionhtml Raw HTML for the outer div.que block.
     * @param question_attempt $questionattempt The attempt for this slot.
     * @param question_display_options|null $displayoptions Display options.
     */
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
     *
     * @return string Static HTML compatible with mPDF.
     */
    abstract public function render_for_pdf(): string;

    /**
     * Detect if correctness markers (right/wrong icons) should be displayed.
     *
     * @return bool
     */
    protected function should_show_correctness(): bool {
        if ($this->displayoptions === null) {
            return false;
        }
        return !empty($this->displayoptions->correctness)
            && $this->questionattempt->get_state()->is_finished();
    }

    /**
     * Build the SVG node carrying a check / cross icon next to a dropped item.
     *
     * @param bool $iscorrect Whether the answer is correct.
     * @param float $cx Centre x in viewBox coordinates.
     * @param float $cy Centre y in viewBox coordinates.
     * @param float $size Icon size in viewBox units.
     * @return string SVG markup.
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
     * Pull the qtext div from the question HTML keeping the original
     * formatting. Uses a depth-aware scan so nested divs inside the question
     * text are handled correctly.
     *
     * @return string The qtext block (including its outer div) or empty
     *                string when not found.
     */
    protected function extract_qtext_html(): string {
        $needle = 'class="qtext"';
        $html = $this->questionhtml;

        $position = strpos($html, $needle);
        if ($position === false) {
            return '';
        }
        $tagstart = strrpos(substr($html, 0, $position), '<div');
        if ($tagstart === false) {
            return '';
        }

        $cursor = $tagstart;
        $depth = 0;
        $length = strlen($html);
        while ($cursor < $length) {
            $next = strpos($html, '<', $cursor);
            if ($next === false) {
                return '';
            }
            $opening = substr($html, $next, 4) === '<div';
            $closing = substr($html, $next, 6) === '</div>';
            if ($opening) {
                $depth++;
                $cursor = $next + 4;
                continue;
            }
            if ($closing) {
                $depth--;
                $cursor = $next + 6;
                if ($depth === 0) {
                    return substr($html, $tagstart, $cursor - $tagstart);
                }
                continue;
            }
            $cursor = $next + 1;
        }
        return '';
    }

    /**
     * Replace, in $this->questionhtml, the first <div> whose class attribute
     * contains the given class token by the supplied replacement HTML.
     *
     * Uses a depth-aware scan to handle nested <div> inside the targeted
     * block. The surrounding HTML (in particular the .info and .outcome
     * blocks emitted by Moodle's question renderer chrome) is preserved.
     *
     * @param string $classname Bare class token to match (e.g. "ddarea").
     * @param string $replacement HTML to inject in place of the matched div.
     * @return string The modified HTML; original HTML when the div is not found.
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
            $opening = substr($html, $next, 4) === '<div';
            $closing = substr($html, $next, 6) === '</div>';
            if ($opening) {
                $depth++;
                $cursor = $next + 4;
                continue;
            }
            if ($closing) {
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
     * Build a base64 data URI from a question file area, suitable for being
     * embedded as an SVG <image href="..."> attribute.
     *
     * @param string $filearea The Moodle filearea (bgimage, dragimage...).
     * @param int $itemid The itemid for the area.
     * @return string|null Data URI or null when no file was found.
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
     * image stored in a question file area, in a single file_storage hit.
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

    /**
     * Locate the first non-directory file for the given area on the current
     * question.
     *
     * @param string $filearea Filearea to inspect.
     * @param int $itemid Item id within that area.
     * @return \stored_file|null
     */
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

    /**
     * Get the question id for file lookups.
     */
    protected function get_question_id(): int {
        $question = $this->questionattempt->get_question();
        return isset($question->id) ? (int) $question->id : 0;
    }

    /**
     * Get the question context id for file lookups.
     */
    protected function get_question_context_id(): int {
        $question = $this->questionattempt->get_question();
        return isset($question->contextid) ? (int) $question->contextid : 0;
    }

    /**
     * Get the qtype frankenstyle component name for file lookups.
     */
    protected function get_question_component_name(): string {
        $question = $this->questionattempt->get_question();
        if (!isset($question->qtype)) {
            return '';
        }
        return (string) $question->qtype->plugin_name();
    }

    /**
     * Escape text content for safe inclusion in an SVG element.
     *
     * @param string $value Raw text.
     * @return string XML-safe text.
     */
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
     * @return string Section HTML, or empty string when nothing to display.
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
