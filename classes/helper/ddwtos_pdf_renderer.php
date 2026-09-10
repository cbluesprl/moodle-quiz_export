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
 * PDF renderer for ddwtos (drag-and-drop into text) questions.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export\helper;

use question_utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Replaces the interactive drop zones inside ddwtos question text by the
 * actual labels the student dragged into them, producing a static rendering
 * compatible with mPDF.
 *
 * Reads positions, groups, choices and responses from the Moodle question
 * API ($question->places, $question->choices, $question->get_ordered_choices(),
 * $qa->get_last_qt_data()), so shuffle and choice resolution stay consistent
 * with what Moodle's review page displays.
 */
class ddwtos_pdf_renderer extends abstract_qtype_pdf_renderer {

    private const BORDER_CORRECT = '#2a8a2a';
    private const BG_CORRECT = '#e6f4e6';
    private const BORDER_INCORRECT = '#c83737';
    private const BG_INCORRECT = '#fbe5e5';
    private const BORDER_NEUTRAL = '#4285f4';
    private const BG_NEUTRAL = '#e8f0fe';
    private const BORDER_WIDTH = '2px';

    public function render_for_pdf(): string {
        $this->ensure_question_state_applied();

        $question = $this->questionattempt->get_question();
        if (empty($question->places) || empty($question->choices)) {
            return $this->questionhtml;
        }

        $responses = $this->collect_responses();

        $html = $this->fill_drop_zones($this->questionhtml, $responses);
        $html = $this->replace_drag_homes_container(
            $html,
            $this->render_unplaced_section($this->build_unplaced_pills($responses))
        );
        return $html;
    }

    /**
     * Read each place's response value, mirroring the per-field lookup
     * Moodle's own ddwtos renderer performs.
     *
     * @return array<int, int> Map placeno => choiceorder index (0 means "no response").
     */
    private function collect_responses(): array {
        $question = $this->questionattempt->get_question();
        $responses = [];
        foreach ($question->places as $placeno => $unused) {
            $value = $this->questionattempt->get_last_qt_var($question->field($placeno));
            $responses[(int) $placeno] = $value !== null ? (int) $value : 0;
        }
        return $responses;
    }

    /**
     * Replace each <span class="placeN drop ..."> ... </span></span> wrapper
     * by the rendered label of the dragged choice. The depth-aware match
     * ($placeholder span contains an inner accesshide span emitted by Moodle)
     * uses a non-greedy match terminating at the second </span>.
     */
    private function fill_drop_zones(string $html, array $responses): string {
        $pattern = '#<span\b[^>]*\bclass="(?:[^"]*\s)?place(\d+)\b[^"]*\bdrop\b[^"]*"[^>]*>.*?</span>\s*</span>#is';
        return preg_replace_callback($pattern, function ($match) use ($responses) {
            $placeno = (int) $match[1];
            $responsevalue = $responses[$placeno] ?? 0;
            if ($responsevalue === 0) {
                return $this->build_blank_marker();
            }
            $contenthtml = $this->render_choice_content($placeno, $responsevalue);
            if ($contenthtml === null) {
                return $this->build_blank_marker();
            }
            return $this->build_filled_marker($contenthtml, $this->resolve_correctness($placeno, $responsevalue));
        }, $html);
    }

    /**
     * Render the visible HTML of the choice the student placed at $placeno,
     * matching how Moodle formats draghome content (filters applied).
     */
    private function render_choice_content(int $placeno, int $responsevalue): ?string {
        $question = $this->questionattempt->get_question();
        $group = (int) ($question->places[$placeno] ?? 0);
        if ($group === 0) {
            return null;
        }
        $ordered = $question->get_ordered_choices($group);
        if (!isset($ordered[$responsevalue])) {
            return null;
        }
        $context = \context::instance_by_id($question->contextid);
        return question_utils::format_question_fragment((string) $ordered[$responsevalue]->text, $context);
    }

    /**
     * Determine whether the student's placed choice is correct, or null when
     * correctness should be hidden by the display options.
     */
    private function resolve_correctness(int $placeno, int $responsevalue): ?bool {
        if (!$this->should_show_correctness()) {
            return null;
        }
        $question = $this->questionattempt->get_question();
        $rightchoice = $question->get_right_choice_for($placeno);
        if ($rightchoice === null) {
            return null;
        }
        return ((int) $rightchoice) === $responsevalue;
    }

    /**
     * Replace the answercontainer block (the available drag labels listed
     * below the question text by Moodle's runtime renderer) by the given
     * static content. Keeping this position means the unplaced labels appear
     * inside the formulation block (the coloured response panel) rather than
     * after the question's response history.
     */
    private function replace_drag_homes_container(string $html, string $replacement): string {
        $pattern = '#<div\b[^>]*\bclass="[^"]*\banswercontainer\b[^"]*"[^>]*>.*?</div>\s*#is';
        $replaced = preg_replace_callback($pattern, fn() => $replacement, $html, 1);
        return $replaced !== null ? $replaced : $html;
    }

    /**
     * Inline marker used when a place has a response. Border/background
     * reflect correctness (green/red) or neutral blue when correctness should
     * not be revealed.
     */
    private function build_filled_marker(string $contenthtml, ?bool $iscorrect): string {
        if ($iscorrect === true) {
            $border = self::BORDER_CORRECT;
            $background = self::BG_CORRECT;
        } else if ($iscorrect === false) {
            $border = self::BORDER_INCORRECT;
            $background = self::BG_INCORRECT;
        } else {
            $border = self::BORDER_NEUTRAL;
            $background = self::BG_NEUTRAL;
        }

        return '<span class="quiz_export-ddwtos-filled" '
            . 'style="display:inline-block; padding:0 4px; '
            . 'background:' . $background . '; '
            . 'border:' . self::BORDER_WIDTH . ' solid ' . $border . '; '
            . 'border-radius:3px; '
            . 'font-weight:bold;">'
            . $contenthtml
            . '</span>';
    }

    private function build_blank_marker(): string {
        return '<span class="quiz_export-ddwtos-blank" '
            . 'style="display:inline-block; min-width:30px; padding:0 4px; '
            . 'background:#f5f5f5; border:' . self::BORDER_WIDTH . ' dashed #999; border-radius:3px;">'
            . '&nbsp;&nbsp;&nbsp;'
            . '</span>';
    }

    /**
     * Build the list of fully-styled draghome-look pills for unplaced choices,
     * formatted via Moodle filters and wrapped in a span that mirrors the
     * `.draghome` runtime appearance (white background, 1px black border).
     *
     * @param array<int, int> $responses Map placeno => choiceorder index.
     * @return array<int, string>
     */
    private function build_unplaced_pills(array $responses): array {
        $question = $this->questionattempt->get_question();

        $usedkeys = [];
        foreach ($responses as $placeno => $responsevalue) {
            if (!isset($question->places[$placeno])) {
                continue;
            }
            $group = (int) $question->places[$placeno];
            $choicekey = $this->lookup_choice_key($group, $responsevalue);
            if ($choicekey === null) {
                continue;
            }
            $usedkeys[$group][$choicekey] = true;
        }

        $context = \context::instance_by_id($question->contextid);
        $pills = [];
        foreach ($question->choices as $groupid => $groupchoices) {
            $groupid = (int) $groupid;
            foreach ($groupchoices as $choicekey => $choice) {
                if (isset($usedkeys[$groupid][(int) $choicekey])) {
                    continue;
                }
                $contenthtml = question_utils::format_question_fragment((string) $choice->text, $context);
                $pills[] = $this->build_unplaced_pill($contenthtml);
            }
        }
        return $pills;
    }

    /**
     * Wrap an already-formatted choice fragment in a Moodle-style draghome
     * pill. Multi-line content is split on `<br>` and rendered as stacked
     * `<div>` lines, since mPDF does not flow `<br>` reliably inside an
     * `inline-block` container.
     */
    private function build_unplaced_pill(string $contenthtml): string {
        $contenthtml = trim($contenthtml);
        if ($contenthtml === '') {
            return '';
        }

        // Normalise to the XHTML self-closing form; with explicit dimensions on
        // the outer inline-block span, mPDF treats <br /> as an in-box line
        // break rather than splitting the container.
        $contenthtml = preg_replace('#<br\s*/?>#i', '<br />', $contenthtml);

        $style = 'display:inline-block; '
            . 'padding: 3pt 6pt; '
            . 'border: 1px solid #000; '
            . 'background-color: #ffffff; '
            . 'color: #000; '
            . 'font-family: Arial, Helvetica, sans-serif; '
            . 'font-size: 11pt; '
            . 'line-height: 1.231; '
            . 'margin: 3pt; '
            . 'text-align: center; '
            . 'vertical-align: middle;';
        return '<span style="' . $style . '">' . $contenthtml . '</span>';
    }
}
