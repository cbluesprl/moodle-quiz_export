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
 * PDF renderer for ddimageortext questions.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Render ddimageortext (drag-and-drop onto image) questions for PDF export.
 *
 * Reads positions, choices and responses from the question API ($question->places,
 * $question->choices, $question->get_ordered_choices(), $qa->get_last_qt_data()),
 * then composes a static SVG that mirrors what Moodle's runtime JS would produce
 * during a live attempt review.
 */
class ddimageortext_pdf_renderer extends abstract_qtype_pdf_renderer {

    private const IMAGE_WIDTH_PT = 480.0;
    private const CANVAS_PADDING_PT = 60.0;
    private const BASE_FONT_PT = 12.0;
    private const MIN_FONT_PT = 7.5;
    private const MAX_LABEL_CHARS = 16;
    private const DROPZONE_PADDING_X = 4.0;
    private const DROPZONE_PADDING_Y = 3.0;
    private const CHAR_WIDTH_RATIO = 0.55;
    private const MIN_DROPZONE_WIDTH = 32.0;
    private const MIN_DROPZONE_HEIGHT = 18.0;
    private const BORDER_CORRECT = '#2a8a2a';
    private const BORDER_INCORRECT = '#c83737';
    private const BORDER_NEUTRAL = '#888888';
    private const BORDER_WIDTH_PT = 2.0;
    private const DROPZONE_FILL = 'rgba(255, 255, 255, 0.5)';
    private const TEXT_COLOR = '#cc6600';
    private const CORRECTNESS_ICON_SIZE_PT = 8.0;
    private const CORRECTNESS_ICON_INSET_PT = 3.0;

    public function render_for_pdf(): string {
        $this->ensure_question_state_applied();

        $question = $this->questionattempt->get_question();
        if (empty($question->places) || empty($question->choices)) {
            return $this->questionhtml;
        }

        $bgimagedata = $this->get_image_data_uri('bgimage', $question->id);
        if ($bgimagedata === null) {
            return $this->questionhtml;
        }

        [$imagewidth, $imageheight] = $this->get_background_image_size();
        if ($imagewidth === 0 || $imageheight === 0) {
            return $this->questionhtml;
        }

        $responses = $this->collect_responses();
        $canvas = $this->build_canvas_geometry($imagewidth, $imageheight);
        $svgcontent = $this->build_background_svg($bgimagedata, $canvas);
        $groupdims = $this->compute_group_dropzone_dimensions($canvas['scale']);

        foreach ($question->places as $placeno => $place) {
            $svgcontent .= $this->render_dropzone(
                (int) $placeno, $place,
                $responses[$placeno] ?? 0,
                $groupdims, $canvas
            );
        }

        $svgblock = '<div class="quiz_export-dd-svg" style="text-align:center;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'viewBox="0 0 ' . $canvas['totalw'] . ' ' . $canvas['totalh'] . '" '
            . 'width="' . $canvas['totalw'] . '" '
            . 'preserveAspectRatio="xMidYMid meet">'
            . $svgcontent
            . '</svg></div>';

        $unplacedhtml = $this->render_unplaced_section(
            $this->build_unplaced_pill_contents($responses)
        );

        return $this->replace_div_with_class('ddarea', $svgblock . $unplacedhtml);
    }

    /**
     * Read each place's response value, mirroring the per-field lookup
     * Moodle's own ddimageortext renderer performs.
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

    private function build_canvas_geometry(int $imagewidth, int $imageheight): array {
        $imgw = self::IMAGE_WIDTH_PT;
        $imgh = ($imageheight / $imagewidth) * $imgw;
        $padding = self::CANVAS_PADDING_PT;
        return [
            'totalw' => $imgw + (2 * $padding),
            'totalh' => $imgh + (2 * $padding),
            'imgx' => $padding,
            'imgy' => $padding,
            'imgw' => $imgw,
            'imgh' => $imgh,
            'padding' => $padding,
            'scale' => $imgw / $imagewidth,
        ];
    }

    private function build_background_svg(string $bgimagedata, array $canvas): string {
        return '<image x="' . $canvas['imgx'] . '" y="' . $canvas['imgy'] . '" '
            . 'width="' . $canvas['imgw'] . '" height="' . $canvas['imgh'] . '" '
            . 'href="' . $bgimagedata . '" '
            . 'xlink:href="' . $bgimagedata . '" />';
    }

    private function render_dropzone(
        int $placeno,
        $place,
        int $responsevalue,
        array $groupdims,
        array $canvas
    ): string {
        $group = (int) $place->group;
        $dims = $groupdims[$group] ?? ['width' => self::MIN_DROPZONE_WIDTH, 'height' => self::MIN_DROPZONE_HEIGHT];
        $dzwidth = $dims['width'];
        $dzheight = $dims['height'];

        $dzleft = $canvas['imgx'] + ((float) ($place->xy[0] ?? 0) * $canvas['scale']);
        $dztop = $canvas['imgy'] + ((float) ($place->xy[1] ?? 0) * $canvas['scale']);

        $iscorrect = false;
        if ($responsevalue === 0) {
            $bordercolor = self::BORDER_NEUTRAL;
        } else {
            $iscorrect = $this->is_correct_response($placeno, $responsevalue);
            $bordercolor = $iscorrect ? self::BORDER_CORRECT : self::BORDER_INCORRECT;
        }

        $svg = '<rect x="' . $dzleft . '" y="' . $dztop . '" '
            . 'width="' . $dzwidth . '" height="' . $dzheight . '" '
            . 'rx="2" ry="2" '
            . 'fill="' . self::DROPZONE_FILL . '" '
            . 'stroke="' . $bordercolor . '" stroke-width="' . self::BORDER_WIDTH_PT . '"/>';

        if ($responsevalue === 0) {
            return $svg;
        }

        $choice = $this->resolve_choice($group, $responsevalue);
        if ($choice !== null) {
            $svg .= $this->render_choice_inside_dropzone(
                $choice,
                $dzleft, $dztop, $dzwidth, $dzheight,
                $canvas['scale']
            );
        }

        $svg .= $this->build_corner_correctness_badge(
            $iscorrect,
            $dzleft, $dztop, $dzwidth, $dzheight
        );
        return $svg;
    }

    /**
     * Whether the student's response for a place matches the expected choice.
     */
    private function is_correct_response(int $placeno, int $responsevalue): bool {
        $question = $this->questionattempt->get_question();
        return (int) $question->get_right_choice_for($placeno) === $responsevalue;
    }

    /**
     * Resolve a response value to the actual choice object via Moodle's
     * shuffled choice order.
     */
    private function resolve_choice(int $group, int $responsevalue) {
        $question = $this->questionattempt->get_question();
        $ordered = $question->get_ordered_choices($group);
        return $ordered[$responsevalue] ?? null;
    }

    /**
     * Build a small check / cross badge in the drop zone's bottom-right
     * corner so correctness is conveyed by shape, not colour alone (WCAG 1.4.1).
     */
    private function build_corner_correctness_badge(
        bool $iscorrect,
        float $dzleft,
        float $dztop,
        float $dzwidth,
        float $dzheight
    ): string {
        $iconsize = min(self::CORRECTNESS_ICON_SIZE_PT, $dzwidth * 0.4, $dzheight * 0.55);
        $inset = min(self::CORRECTNESS_ICON_INSET_PT, $iconsize * 0.45);
        $cx = $dzleft + $dzwidth - $inset - ($iconsize / 2);
        $cy = $dztop + $dzheight - $inset - ($iconsize / 2);
        $color = $iscorrect ? self::BORDER_CORRECT : self::BORDER_INCORRECT;
        $bgradius = $iconsize * 0.7;

        $background = '<circle cx="' . $cx . '" cy="' . $cy . '" '
            . 'r="' . $bgradius . '" '
            . 'fill="#ffffff" '
            . 'stroke="' . $color . '" stroke-width="0.8"/>';

        return $background . $this->build_correctness_svg($iscorrect, $cx, $cy, $iconsize);
    }

    /**
     * Compute drop zone dimensions per group, sized to the largest content
     * any choice in that group needs to render.
     *
     * @return array<int, array{width:float, height:float}>
     */
    private function compute_group_dropzone_dimensions(float $scale): array {
        $dimensions = [];
        $question = $this->questionattempt->get_question();

        foreach ($question->choices as $groupid => $choices) {
            $maxw = self::MIN_DROPZONE_WIDTH;
            $maxh = self::MIN_DROPZONE_HEIGHT;

            foreach ($choices as $choice) {
                [$contentw, $contenth] = $this->measure_choice($choice, $scale);
                $maxw = max($maxw, $contentw + (self::DROPZONE_PADDING_X * 2));
                $maxh = max($maxh, $contenth + (self::DROPZONE_PADDING_Y * 2));
            }

            $dimensions[(int) $groupid] = ['width' => $maxw, 'height' => $maxh];
        }
        return $dimensions;
    }

    /**
     * @return array{0:float, 1:float} Width and height in canvas points.
     */
    private function measure_choice($choice, float $scale): array {
        $info = !empty($choice->id) ? $this->get_image_info('dragimage', (int) $choice->id) : null;
        if ($info !== null) {
            return [$info['width'] * $scale, $info['height'] * $scale];
        }
        $label = $this->truncate_label(strip_tags((string) ($choice->text ?? '')));
        $textwidth = max(1, mb_strlen($label)) * (self::BASE_FONT_PT * self::CHAR_WIDTH_RATIO);
        $textheight = self::BASE_FONT_PT * 1.3;
        return [$textwidth, $textheight];
    }

    private function render_choice_inside_dropzone(
        $choice,
        float $dzleft,
        float $dztop,
        float $dzwidth,
        float $dzheight,
        float $scale
    ): string {
        $info = !empty($choice->id) ? $this->get_image_info('dragimage', (int) $choice->id) : null;
        if ($info !== null) {
            return $this->render_image_in_dropzone($info, $dzleft, $dztop, $dzwidth, $dzheight, $scale);
        }
        $label = $this->truncate_label(strip_tags((string) ($choice->text ?? '')));
        return $this->render_text_in_dropzone($label, $dzleft, $dztop, $dzwidth, $dzheight);
    }

    private function render_image_in_dropzone(
        array $info,
        float $dzleft,
        float $dztop,
        float $dzwidth,
        float $dzheight,
        float $scale
    ): string {
        $availw = max(1.0, $dzwidth - (self::DROPZONE_PADDING_X * 2));
        $availh = max(1.0, $dzheight - (self::DROPZONE_PADDING_Y * 2));
        $srcw = $info['width'] * $scale;
        $srch = $info['height'] * $scale;
        if ($srcw <= 0 || $srch <= 0) {
            return '';
        }
        $ratio = min($availw / $srcw, $availh / $srch, 1.0);
        $rendw = $srcw * $ratio;
        $rendh = $srch * $ratio;
        $imgx = $dzleft + (($dzwidth - $rendw) / 2);
        $imgy = $dztop + (($dzheight - $rendh) / 2);
        return '<image x="' . $imgx . '" y="' . $imgy . '" '
            . 'width="' . $rendw . '" height="' . $rendh . '" '
            . 'preserveAspectRatio="xMidYMid meet" '
            . 'href="' . $info['data'] . '" xlink:href="' . $info['data'] . '" />';
    }

    private function render_text_in_dropzone(
        string $label,
        float $dzleft,
        float $dztop,
        float $dzwidth,
        float $dzheight
    ): string {
        $availw = max(1.0, $dzwidth - (self::DROPZONE_PADDING_X * 2));
        $availh = max(1.0, $dzheight - (self::DROPZONE_PADDING_Y * 2));
        $charcount = max(1, mb_strlen($label));

        $widthbasedsize = $availw / ($charcount * self::CHAR_WIDTH_RATIO);
        $heightbasedsize = $availh / 1.2;
        $fontsize = max(self::MIN_FONT_PT, min(self::BASE_FONT_PT, $widthbasedsize, $heightbasedsize));

        $cx = $dzleft + ($dzwidth / 2);
        $cy = $dztop + ($dzheight / 2);
        $textbaseline = $cy + ($fontsize * 0.3);

        return '<text x="' . $cx . '" y="' . $textbaseline . '" '
            . 'text-anchor="middle" '
            . 'font-family="Helvetica, Arial, sans-serif" '
            . 'font-size="' . $fontsize . '" '
            . 'font-weight="bold" '
            . 'fill="' . self::TEXT_COLOR . '">'
            . $this->svg_escape($label)
            . '</text>';
    }

    private function truncate_label(string $label): string {
        if (mb_strlen($label) <= self::MAX_LABEL_CHARS) {
            return $label;
        }
        return mb_substr($label, 0, self::MAX_LABEL_CHARS - 3) . '...';
    }

    /**
     * Build the inner HTML for each unplaced choice's pill.
     *
     * @param array<int, int> $responses Map placeno => choiceorder index.
     * @return array<int, string>
     */
    private function build_unplaced_pill_contents(array $responses): array {
        $question = $this->questionattempt->get_question();

        $usedkeys = [];
        foreach ($responses as $placeno => $responsevalue) {
            if (!isset($question->places[$placeno])) {
                continue;
            }
            $group = (int) $question->places[$placeno]->group;
            $choicekey = $this->lookup_choice_key($group, $responsevalue);
            if ($choicekey === null) {
                continue;
            }
            $usedkeys[$group][$choicekey] = true;
        }

        $contents = [];
        foreach ($question->choices as $groupid => $groupchoices) {
            $groupid = (int) $groupid;
            foreach ($groupchoices as $choicekey => $choice) {
                if (isset($usedkeys[$groupid][(int) $choicekey])) {
                    continue;
                }
                $contents[] = $this->build_unplaced_pill_content($choice);
            }
        }
        return $contents;
    }

    private function build_unplaced_pill_content($choice): string {
        $info = !empty($choice->id) ? $this->get_image_info('dragimage', (int) $choice->id) : null;
        if ($info !== null) {
            return '<img src="' . $info['data'] . '" '
                . 'style="max-height:24pt; vertical-align:middle;" alt=""/>';
        }
        $label = strip_tags((string) ($choice->text ?? ''));
        return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
