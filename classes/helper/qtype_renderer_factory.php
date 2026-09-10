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
 * Factory locating per-qtype PDF renderers for quiz_export.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export\helper;

use mod_quiz\quiz_attempt;
use question_display_options;

defined('MOODLE_INTERNAL') || die();

/**
 * Detects supported drag-and-drop question blocks in a rendered review HTML
 * page and replaces them by a static, mPDF-friendly rendering.
 *
 * The mapping qtype => helper class is the single extension point: adding a
 * new supported question type only requires registering a new entry here, no
 * other code change is needed (Open-Closed principle).
 */
class qtype_renderer_factory {

    /**
     * Mapping of supported question type names to their renderer class.
     *
     * @var array<string, class-string<abstract_qtype_pdf_renderer>>
     */
    private static $renderers = [
        'ddimageortext' => ddimageortext_pdf_renderer::class,
        'ddmarker' => ddmarker_pdf_renderer::class,
        'ddwtos' => ddwtos_pdf_renderer::class,
    ];

    /**
     * Replace every supported question block in the given HTML by its PDF
     * compatible counterpart. Untouched HTML is returned as-is.
     *
     * @param string $html Full review page HTML.
     * @param quiz_attempt $attemptobj Quiz attempt providing question_attempts.
     * @param question_display_options|null $displayoptions Optional display options.
     * @return string Transformed HTML.
     */
    public static function transform(
        string $html,
        quiz_attempt $attemptobj,
        ?question_display_options $displayoptions = null
    ): string {
        if (trim($html) === '' || empty(self::$renderers)) {
            return $html;
        }

        foreach ($attemptobj->get_slots() as $slot) {
            $questionattempt = $attemptobj->get_question_attempt($slot);
            $qtype = $questionattempt->get_question(false)->get_type_name();

            if (!isset(self::$renderers[$qtype])) {
                continue;
            }

            $blockid = $questionattempt->get_outer_question_div_unique_id();
            $blockhtml = self::extract_question_block($html, $blockid);
            if ($blockhtml === null) {
                continue;
            }

            /** @var abstract_qtype_pdf_renderer $renderer */
            $rendererclass = self::$renderers[$qtype];
            $renderer = new $rendererclass($blockhtml, $questionattempt, $displayoptions);

            try {
                $replacement = $renderer->render_for_pdf();
            } catch (\Throwable $exception) {
                debugging('quiz_export DD renderer failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
                continue;
            }

            $html = str_replace($blockhtml, $replacement, $html);
        }

        return $html;
    }

    /**
     * Register a new qtype renderer at runtime.
     *
     * @param string $qtype Question type machine name (e.g. "ddimageortext").
     * @param string $rendererclass Fully qualified class extending abstract_qtype_pdf_renderer.
     */
    public static function register(string $qtype, string $rendererclass): void {
        self::$renderers[$qtype] = $rendererclass;
    }

    /**
     * Extract the full outer div for a given question id.
     *
     * Uses a manual depth-aware scan to handle the nested <div> structure
     * produced by Moodle question renderers without the noisy warnings of
     * DOMDocument when fed partial HTML.
     *
     * @param string $html Full HTML to search.
     * @param string $blockid The id attribute of the outer div.
     * @return string|null Block HTML or null when missing.
     */
    private static function extract_question_block(string $html, string $blockid): ?string {
        $needle = 'id="' . $blockid . '"';
        $position = strpos($html, $needle);
        if ($position === false) {
            return null;
        }

        $tagstart = strrpos(substr($html, 0, $position), '<div');
        if ($tagstart === false) {
            return null;
        }

        $cursor = $tagstart;
        $depth = 0;
        $length = strlen($html);
        while ($cursor < $length) {
            $next = strpos($html, '<', $cursor);
            if ($next === false) {
                return null;
            }
            $closing = substr($html, $next, 6) === '</div>';
            $opening = substr($html, $next, 4) === '<div';
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
        return null;
    }
}
