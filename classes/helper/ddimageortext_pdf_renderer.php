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

    /** @var float Default text size in canvas points; mirrors Moodle's 13px/1.231 arial draghome font. */
    private const BASE_FONT_PT = 11.0;

    /** @var float Lower bound for auto-shrinking when the text is too wide for its dropzone. */
    private const MIN_FONT_PT = 7.5;

    /** @var float Inner horizontal padding of the dropzone, in canvas points. */
    private const DROPZONE_PADDING_X = 5.0;

    /** @var float Inner vertical padding of the dropzone, in canvas points. */
    private const DROPZONE_PADDING_Y = 4.0;

    /** @var float Minimum drop zone width when no choice has been measured yet. */
    private const MIN_DROPZONE_WIDTH = 32.0;

    /** @var float Minimum drop zone height when no choice has been measured yet. */
    private const MIN_DROPZONE_HEIGHT = 18.0;

    /** @var float Line height multiplier used both for measuring and rendering. */
    private const LINE_HEIGHT_RATIO = 1.231;

    private const BORDER_CORRECT = '#2a8a2a';
    private const BORDER_INCORRECT = '#c83737';
    private const BORDER_NEUTRAL = '#888888';

    /** @var float Border stroke width in canvas points; mirrors Moodle's 1px border. */
    private const BORDER_WIDTH_PT = 1.0;

    /** @var float Fill opacity used for an empty drop zone (mirrors Moodle .dropzone opacity:0.5). */
    private const EMPTY_FILL_OPACITY = 0.5;

    /** @var string Text colour inside the placed draghome (Moodle uses default body colour). */
    private const TEXT_COLOR = '#000000';

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

        $unplacedsvg = $this->render_unplaced_svg($responses, $groupdims, $canvas);

        return $this->replace_div_with_class('ddarea', $svgblock . $unplacedsvg);
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
            // Empty drop zone: render Moodle's `.dropzone` (white at 0.5 opacity, neutral border).
            $bordercolor = self::BORDER_NEUTRAL;
            $fillcolor = '#ffffff';
            $fillopacity = self::EMPTY_FILL_OPACITY;
        } else {
            // Filled drop zone: render Moodle's `.draghome.placed.groupX` (group colour, full opacity)
            // with the correctness border on top.
            $iscorrect = $this->is_correct_response($placeno, $responsevalue);
            $bordercolor = $iscorrect ? self::BORDER_CORRECT : self::BORDER_INCORRECT;
            $fillcolor = $this->get_group_fill_color($group);
            $fillopacity = 1.0;
        }

        $svg = '<rect x="' . $dzleft . '" y="' . $dztop . '" '
            . 'width="' . $dzwidth . '" height="' . $dzheight . '" '
            . 'fill="' . $fillcolor . '" fill-opacity="' . $fillopacity . '" '
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
     * Map a Moodle ddimageortext group number to the background colour applied
     * by the corresponding `.groupN` rule in question/type/ddimageortext/styles.css.
     */
    private function get_group_fill_color(int $group): string {
        static $colors = [
            1 => '#ffffff',
            2 => '#b0c4de',
            3 => '#dcdcdc',
            4 => '#d8bfd8',
            5 => '#87cefa',
            6 => '#daa520',
            7 => '#ffd700',
            8 => '#f0e68c',
        ];
        return $colors[$group] ?? '#ffffff';
    }

    /**
     * Convert a choice's HTML text into a list of lines, honouring `<br>` tags
     * exactly as Moodle's runtime renders them inside a draghome.
     *
     * @return array<int, string> Plain-text lines, empty when the choice has no text.
     */
    private function extract_text_lines(string $html): array {
        $normalized = preg_replace('#<br\s*/?>#i', "\n", $html);
        $stripped = strip_tags((string) $normalized);
        $lines = preg_split('/\r?\n/', $stripped);
        if ($lines === false) {
            return [];
        }
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn($line) => $line !== ''));
        return $lines;
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
        $lines = $this->extract_text_lines((string) ($choice->text ?? ''));
        if (empty($lines)) {
            return [0.0, 0.0];
        }
        $maxchars = 0;
        foreach ($lines as $line) {
            $maxchars = max($maxchars, mb_strlen($line));
        }
        $maxchars = max(1, $maxchars);
        $textwidth = $maxchars * (self::BASE_FONT_PT * self::CHAR_WIDTH_RATIO);
        $textheight = count($lines) * self::BASE_FONT_PT * self::LINE_HEIGHT_RATIO;
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
        $lines = $this->extract_text_lines((string) ($choice->text ?? ''));
        return $this->render_text_in_dropzone($lines, $dzleft, $dztop, $dzwidth, $dzheight);
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

    /**
     * Render the placed draghome text using the same multi-line/font behaviour
     * as Moodle's `.draghome` (`<br>`-separated lines, Arial-family, normal weight).
     *
     * @param array<int, string> $lines Plain-text lines extracted from the choice.
     */
    private function render_text_in_dropzone(
        array $lines,
        float $dzleft,
        float $dztop,
        float $dzwidth,
        float $dzheight
    ): string {
        if (empty($lines)) {
            return '';
        }
        $fontsize = $this->compute_label_fontsize($lines, $dzwidth, $dzheight);

        $lineheight = $fontsize * self::LINE_HEIGHT_RATIO;
        $totalheight = count($lines) * $lineheight;
        $cx = $dzleft + ($dzwidth / 2);
        // Vertically centre the block of lines and use the typical 0.8 ascent ratio
        // so the first baseline sits close to the visual top of the first line.
        $blocktop = $dztop + (($dzheight - $totalheight) / 2);
        $firstbaseline = $blocktop + ($fontsize * 0.8);

        $svg = '';
        foreach ($lines as $i => $line) {
            $y = $firstbaseline + ($i * $lineheight);
            $svg .= '<text x="' . $cx . '" y="' . $y . '" '
                . 'text-anchor="middle" '
                . 'font-family="Arial, Helvetica, sans-serif" '
                . 'font-size="' . $fontsize . '" '
                . 'fill="' . self::TEXT_COLOR . '">'
                . $this->svg_escape($line)
                . '</text>';
        }
        return $svg;
    }

    /**
     * Auto-shrink the choice text font size so the longest line fits the
     * available width and the full block fits the available height. Shared
     * between the SVG drop zones and the HTML unplaced pills so a given
     * choice ends up with the exact same on-page font size in both places.
     *
     * @param array<int, string> $lines Plain-text lines of the choice content.
     * @param float $dzwidth  Outer drop zone width in canvas points.
     * @param float $dzheight Outer drop zone height in canvas points.
     */
    private function compute_label_fontsize(array $lines, float $dzwidth, float $dzheight): float {
        $availw = max(1.0, $dzwidth - (self::DROPZONE_PADDING_X * 2));
        $availh = max(1.0, $dzheight - (self::DROPZONE_PADDING_Y * 2));

        $maxchars = 0;
        foreach ($lines as $line) {
            $maxchars = max($maxchars, mb_strlen($line));
        }
        $maxchars = max(1, $maxchars);
        $linecount = max(1, count($lines));

        $widthbasedsize = $availw / ($maxchars * self::CHAR_WIDTH_RATIO);
        $heightbasedsize = $availh / ($linecount * self::LINE_HEIGHT_RATIO);
        return max(self::MIN_FONT_PT, min(self::BASE_FONT_PT, $widthbasedsize, $heightbasedsize));
    }

    /**
     * Render the unplaced choices as a dedicated SVG block laid out in a
     * grid below the main image. Reuses the very same drop-zone primitives
     * (`<rect>` + auto-shrunk multi-line `<text>`) so an unplaced pill is
     * visually identical to a placed one — same dimensions, same font, same
     * multi-line behaviour — and we sidestep mPDF's flaky `<br>` /
     * `inline-block` HTML interactions entirely.
     */
    private function render_unplaced_svg(array $responses, array $groupdims, array $canvas): string {
        $items = $this->collect_unplaced_items($responses, $groupdims);
        if (empty($items)) {
            return '';
        }

        // Use a uniform pill size per row, taken from the largest group dims
        // present in the unplaced set. Keeps rows aligned even with multiple groups.
        $pillw = 0.0;
        $pillh = 0.0;
        foreach ($items as $item) {
            $pillw = max($pillw, $item['dims']['width']);
            $pillh = max($pillh, $item['dims']['height']);
        }
        $pillw = max($pillw, self::MIN_DROPZONE_WIDTH);
        $pillh = max($pillh, self::MIN_DROPZONE_HEIGHT);

        $hgap = 8.0;
        $vgap = 6.0;
        $vmargin = 12.0;
        $totalwidth = $canvas['totalw'];

        $cols = max(1, min(count($items), (int) floor($totalwidth / ($pillw + $hgap))));
        $rows = (int) ceil(count($items) / $cols);

        $gridwidth = $cols * $pillw + ($cols - 1) * $hgap;
        $startx = ($totalwidth - $gridwidth) / 2;
        $totalheight = ($vmargin * 2) + ($rows * $pillh) + (($rows - 1) * $vgap);

        $svgcontent = '';
        foreach ($items as $i => $item) {
            $col = $i % $cols;
            $row = (int) floor($i / $cols);
            $x = $startx + $col * ($pillw + $hgap);
            $y = $vmargin + $row * ($pillh + $vgap);
            $svgcontent .= $this->render_unplaced_pill_svg(
                $item, $x, $y, $pillw, $pillh, $canvas['scale']
            );
        }

        return '<div style="margin-top:8pt; text-align:center;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'viewBox="0 0 ' . $totalwidth . ' ' . $totalheight . '" '
            . 'width="' . $totalwidth . '" '
            . 'preserveAspectRatio="xMidYMid meet">'
            . $svgcontent
            . '</svg></div>';
    }

    /**
     * Build the list of unplaced choices to render, each annotated with its
     * group identifier and the matching drop-zone dimensions.
     *
     * @param array<int, int> $responses Map placeno => choiceorder index.
     * @param array<int, array{width:float, height:float}> $groupdims
     * @return array<int, array{choice:object, group:int, dims:array{width:float, height:float}}>
     */
    private function collect_unplaced_items(array $responses, array $groupdims): array {
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

        $items = [];
        foreach ($question->choices as $groupid => $groupchoices) {
            $groupid = (int) $groupid;
            $dims = $groupdims[$groupid]
                ?? ['width' => self::MIN_DROPZONE_WIDTH, 'height' => self::MIN_DROPZONE_HEIGHT];
            foreach ($groupchoices as $choicekey => $choice) {
                if (isset($usedkeys[$groupid][(int) $choicekey])) {
                    continue;
                }
                $items[] = [
                    'choice' => $choice,
                    'group' => $groupid,
                    'dims' => $dims,
                ];
            }
        }
        return $items;
    }

    /**
     * Render a single unplaced pill in the SVG grid, reusing the exact same
     * primitives that render placed drop zones (group-coloured rect + image
     * or auto-shrunk multi-line text).
     */
    private function render_unplaced_pill_svg(array $item, float $x, float $y, float $w, float $h, float $scale): string {
        $bgcolor = $this->get_group_fill_color($item['group']);
        $svg = '<rect x="' . $x . '" y="' . $y . '" '
            . 'width="' . $w . '" height="' . $h . '" '
            . 'fill="' . $bgcolor . '" '
            . 'stroke="#000000" stroke-width="' . self::BORDER_WIDTH_PT . '"/>';

        $choice = $item['choice'];
        $info = !empty($choice->id) ? $this->get_image_info('dragimage', (int) $choice->id) : null;
        if ($info !== null) {
            $svg .= $this->render_image_in_dropzone($info, $x, $y, $w, $h, $scale);
        } else {
            $lines = $this->extract_text_lines((string) ($choice->text ?? ''));
            $svg .= $this->render_text_in_dropzone($lines, $x, $y, $w, $h);
        }
        return $svg;
    }
}
