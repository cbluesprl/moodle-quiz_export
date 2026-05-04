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

defined('MOODLE_INTERNAL') || die();

/**
 * Replaces the interactive drop zones inside ddwtos question text by the
 * actual labels the student dragged into them, producing a PDF-friendly
 * static rendering.
 *
 * The default Moodle ddwtos renderer outputs empty placeholder spans that
 * are filled in by JavaScript; in the PDF, these would appear as the
 * "accesshide" texts ("Espace 1 Question N..."), which is unreadable. This
 * renderer rewrites them inline with the actual choice content. When the
 * review options expose correctness, each filled label is bordered green
 * (correct) or red (incorrect).
 */
class ddwtos_pdf_renderer extends abstract_qtype_pdf_renderer {

    /** @var string Border colour for correct responses. */
    private const BORDER_CORRECT = '#2a8a2a';

    /** @var string Background colour for correct responses. */
    private const BG_CORRECT = '#e6f4e6';

    /** @var string Border colour for incorrect responses. */
    private const BORDER_INCORRECT = '#c83737';

    /** @var string Background colour for incorrect responses. */
    private const BG_INCORRECT = '#fbe5e5';

    /** @var string Border colour when correctness should not be exposed. */
    private const BORDER_NEUTRAL = '#4285f4';

    /** @var string Background colour when correctness should not be exposed. */
    private const BG_NEUTRAL = '#e8f0fe';

    /** @var string Border width applied to filled markers. */
    private const BORDER_WIDTH = '2px';

    public function render_for_pdf(): string {
        $responses = $this->extract_responses_from_html();
        $choices = $this->extract_drag_choices_from_html();
        if (empty($choices)) {
            return $this->questionhtml;
        }

        $this->ensure_choiceorder_initialised();

        $unplacedhtml = $this->render_unplaced_section(
            $this->collect_unplaced_choice_contents($responses, $choices)
        );

        $html = $this->fill_drop_zones($this->questionhtml, $responses, $choices);
        $html = $this->replace_drag_homes_container($html, $unplacedhtml);
        return $html;
    }

    /**
     * Identify the draghome labels that were never used in any response and
     * return their visible HTML, ready to be wrapped in unplaced pills.
     *
     * @param array<int, array{group:int, value:int}> $responses
     * @param array<string, string> $choices Keyed by "group{G}-choice{N}".
     * @return array<int, string>
     */
    private function collect_unplaced_choice_contents(array $responses, array $choices): array {
        $usedkeys = [];
        foreach ($responses as $response) {
            if ((int) $response['value'] === 0) {
                continue;
            }
            $usedkeys['group' . $response['group'] . '-choice' . $response['value']] = true;
        }

        $unplaced = [];
        foreach ($choices as $key => $labelhtml) {
            if (isset($usedkeys[$key])) {
                continue;
            }
            $unplaced[] = $labelhtml;
        }
        return $unplaced;
    }

    /**
     * Read response values from the placeinput hidden inputs.
     *
     * @return array<int, array{group:int, value:int}> Map placeno => response data.
     */
    private function extract_responses_from_html(): array {
        $responses = [];
        if (!preg_match_all('#<input\b[^>]*\bclass="[^"]*\bplaceinput\b[^"]*"[^>]*>#is',
                $this->questionhtml, $matches)) {
            return $responses;
        }
        foreach ($matches[0] as $tag) {
            if (!preg_match('#\bclass="([^"]*)"#i', $tag, $classmatch)) {
                continue;
            }
            if (!preg_match('#\bplace(\d+)\b#', $classmatch[1], $placematch)) {
                continue;
            }
            if (!preg_match('#\bgroup(\d+)\b#', $classmatch[1], $groupmatch)) {
                continue;
            }
            $value = 0;
            if (preg_match('#\bvalue="([^"]*)"#i', $tag, $valuematch)) {
                $value = (int) $valuematch[1];
            }
            $responses[(int) $placematch[1]] = [
                'group' => (int) $groupmatch[1],
                'value' => $value,
            ];
        }
        return $responses;
    }

    /**
     * Build a lookup of available drag labels keyed by "group{G}-choice{N}".
     *
     * @return array<string, string> Map of key => visible label HTML.
     */
    private function extract_drag_choices_from_html(): array {
        $choices = [];
        if (!preg_match_all('#<span\b[^>]*\bclass="([^"]*\bdraghome\b[^"]*)"[^>]*>(.*?)</span>#is',
                $this->questionhtml, $matches, PREG_SET_ORDER)) {
            return $choices;
        }
        foreach ($matches as $match) {
            $classes = $match[1];
            if (!preg_match('#\bchoice(\d+)\b#', $classes, $choicematch)) {
                continue;
            }
            if (!preg_match('#\bgroup(\d+)\b#', $classes, $groupmatch)) {
                continue;
            }
            $choices['group' . $groupmatch[1] . '-choice' . $choicematch[1]] = trim($match[2]);
        }
        return $choices;
    }

    /**
     * Replace each <span class="placeN drop ..."> ... </span> by the matching
     * choice content. The accesshide label inside the placeholder is dropped.
     *
     * @param string $html Original question HTML.
     * @param array $responses Map of placeno => [group, value].
     * @param array $choices Map of "group{G}-choice{N}" => visible content.
     * @return string Transformed HTML.
     */
    private function fill_drop_zones(string $html, array $responses, array $choices): string {
        $pattern = '#<span\b[^>]*\bclass="([^"]*\bplace(\d+)\b[^"]*\bdrop\b[^"]*)"[^>]*>.*?</span>#is';
        return preg_replace_callback($pattern, function ($match) use ($responses, $choices) {
            $placeno = (int) $match[2];
            if (!isset($responses[$placeno])) {
                return $this->build_blank_marker();
            }
            $response = $responses[$placeno];
            if ((int) $response['value'] === 0) {
                return $this->build_blank_marker();
            }
            $key = 'group' . $response['group'] . '-choice' . $response['value'];
            if (!isset($choices[$key])) {
                return $this->build_blank_marker();
            }
            $iscorrect = $this->resolve_correctness_for($placeno, (int) $response['value']);
            return $this->build_filled_marker($choices[$key], $iscorrect);
        }, $html);
    }

    /**
     * Determine whether the student's response for a place is the right one.
     *
     * @return bool|null True when correct, false when incorrect, null when
     *                   correctness should not be displayed (display options
     *                   hide it, or the question API cannot decide).
     */
    private function resolve_correctness_for(int $placeno, int $responsevalue): ?bool {
        if (!$this->should_show_correctness()) {
            return null;
        }
        $question = $this->questionattempt->get_question();
        if (!method_exists($question, 'get_right_choice_for')) {
            return null;
        }
        $rightchoice = $question->get_right_choice_for($placeno);
        if ($rightchoice === null) {
            return null;
        }
        return ((int) $rightchoice) === $responsevalue;
    }

    /**
     * Defensive fallback: if the question was lazily fetched without
     * apply_attempt_state(), choiceorder may be empty, which breaks
     * get_right_choice_for(). Reapply the first step so correctness checks
     * return reliable data.
     */
    private function ensure_choiceorder_initialised(): void {
        $question = $this->questionattempt->get_question();
        if (!empty($question->choiceorder)) {
            return;
        }
        if (!method_exists($question, 'apply_attempt_state')) {
            return;
        }
        try {
            $firststep = $this->questionattempt->get_step(0);
            $question->apply_attempt_state($firststep);
        } catch (\Throwable $exception) {
            debugging('quiz_export ddwtos: choiceorder fallback failed: '
                . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Replace the answercontainer block (the available drag labels listed
     * below the question text by Moodle's runtime renderer) by the given
     * static content. Keeping this position means the unplaced labels appear
     * inside the formulation block (the coloured response panel) rather than
     * after the question's response history.
     *
     * @param string $html Source HTML.
     * @param string $replacement HTML to inject in place of the container.
     * @return string Transformed HTML.
     */
    private function replace_drag_homes_container(string $html, string $replacement): string {
        $pattern = '#<div\b[^>]*\bclass="[^"]*\banswercontainer\b[^"]*"[^>]*>.*?</div>\s*#is';
        $replaced = preg_replace_callback($pattern, fn() => $replacement, $html, 1);
        return $replaced !== null ? $replaced : $html;
    }

    /**
     * Build the inline marker used when a place has a response. Border
     * colour reflects correctness (green/red) or stays neutral blue when
     * correctness should not be revealed.
     *
     * @param string $contenthtml The rendered drag content to embed.
     * @param bool|null $iscorrect True/false drives green/red, null keeps neutral.
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

    /**
     * Build the inline marker used when a place has no response.
     */
    private function build_blank_marker(): string {
        return '<span class="quiz_export-ddwtos-blank" '
            . 'style="display:inline-block; min-width:30px; padding:0 4px; '
            . 'background:#f5f5f5; border:' . self::BORDER_WIDTH . ' dashed #999; border-radius:3px;">'
            . '&nbsp;&nbsp;&nbsp;'
            . '</span>';
    }
}