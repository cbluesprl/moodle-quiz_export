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
 * Key design choices:
 *  - The background image is rendered on a fixed-size canvas (480pt wide
 *    + 60pt padding all around) regardless of the source image dimensions,
 *    so every PDF looks consistent.
 *  - Each drop zone is drawn at its real position with a translucent fill,
 *    so the underlying image stays visible. Border colour reflects
 *    correctness (green/red) or absence of response (grey).
 *  - The dropped item (text or image) renders inside the drop zone, centred
 *    and constrained to the zone bounds: text auto-fits, images preserve
 *    their aspect ratio.
 *  - Text labels longer than 16 characters are truncated with an ellipsis.
 *
 * Drop zone dimensions are computed per group: for each group of choices,
 * the zone size is the max bounding box needed by any choice in the group
 * (mirroring Moodle's runtime "resize all in group" JS behaviour).
 */
class ddimageortext_pdf_renderer extends abstract_qtype_pdf_renderer {

    /** @var float Image render width inside the canvas, in points. */
    private const IMAGE_WIDTH_PT = 480.0;

    /** @var float Padding around the image inside the canvas, in points. */
    private const CANVAS_PADDING_PT = 60.0;

    /** @var float Default font size for choice labels in canvas points. */
    private const BASE_FONT_PT = 12.0;

    /** @var float Minimum font size for auto-fit before truncation kicks in. */
    private const MIN_FONT_PT = 7.5;

    /** @var int Maximum label length before truncation with ellipsis. */
    private const MAX_LABEL_CHARS = 16;

    /** @var float Inner horizontal padding inside a drop zone. */
    private const DROPZONE_PADDING_X = 4.0;

    /** @var float Inner vertical padding inside a drop zone. */
    private const DROPZONE_PADDING_Y = 3.0;

    /** @var float Average character width as a fraction of font size. */
    private const CHAR_WIDTH_RATIO = 0.55;

    /** @var float Minimum drop zone width and height. */
    private const MIN_DROPZONE_WIDTH = 32.0;
    private const MIN_DROPZONE_HEIGHT = 18.0;

    /** @var string Border colour when the response is correct. */
    private const BORDER_CORRECT = '#2a8a2a';

    /** @var string Border colour when the response is incorrect. */
    private const BORDER_INCORRECT = '#c83737';

    /** @var string Border colour for empty drop zones. */
    private const BORDER_NEUTRAL = '#888888';

    /** @var float Drop zone border stroke width. */
    private const BORDER_WIDTH_PT = 2.0;

    /** @var string Translucent fill colour for drop zones. */
    private const DROPZONE_FILL = 'rgba(255, 255, 255, 0.5)';

    /** @var string Text colour used for label content. */
    private const TEXT_COLOR = '#cc6600';

    /** @var float Maximum size of the bottom-right correctness badge, in canvas points. */
    private const CORRECTNESS_ICON_SIZE_PT = 8.0;

    /** @var float Inset of the badge from the drop zone's bottom-right corner. */
    private const CORRECTNESS_ICON_INSET_PT = 3.0;

    public function render_for_pdf(): string {
        $bgimagedata = $this->get_image_data_uri('bgimage', $this->get_question_id());
        if ($bgimagedata === null) {
            return $this->questionhtml;
        }

        list($imagewidth, $imageheight) = $this->get_background_image_size();
        if ($imagewidth === 0 || $imageheight === 0) {
            return $this->questionhtml;
        }

        $places = $this->extract_places_from_html();
        $responses = $this->extract_responses_from_html();
        if (empty($places)) {
            return $this->questionhtml;
        }

        $this->ensure_choiceorder_initialised();

        $canvas = $this->build_canvas_geometry($imagewidth, $imageheight);
        $svgcontent = $this->build_background_svg($bgimagedata, $canvas);

        $groupdims = $this->compute_group_dropzone_dimensions($canvas['scale']);

        foreach ($places as $placeno => $place) {
            $svgcontent .= $this->render_dropzone($placeno, $place, $responses, $groupdims, $canvas);
        }

        $svgblock = '<div class="quiz_export-dd-svg" style="text-align:center;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'viewBox="0 0 ' . $canvas['totalw'] . ' ' . $canvas['totalh'] . '" '
            . 'width="' . $canvas['totalw'] . '" '
            . 'preserveAspectRatio="xMidYMid meet">'
            . $svgcontent
            . '</svg></div>';

        $unplacedchoices = $this->collect_unplaced_choices($places, $responses);
        $unplacedcontents = array_map(
            fn($entry) => $this->build_unplaced_pill_content($entry['choice']),
            $unplacedchoices
        );
        $unplacedhtml = $this->render_unplaced_section($unplacedcontents);

        return $this->replace_div_with_class('ddarea', $svgblock . $unplacedhtml);
    }

    /**
     * Produce the canvas geometry: total dimensions, image placement and the
     * scale factor to convert original image pixels into canvas points.
     *
     * @return array{
     *     totalw:float, totalh:float,
     *     imgx:float, imgy:float, imgw:float, imgh:float,
     *     padding:float, scale:float
     * }
     */
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

    /**
     * Render a single drop zone (rectangle + chosen content if any).
     */
    private function render_dropzone(int $placeno, array $place, array $responses, array $groupdims, array $canvas): string {
        $group = (int) $place['group'];
        $dims = $groupdims[$group] ?? ['width' => self::MIN_DROPZONE_WIDTH, 'height' => self::MIN_DROPZONE_HEIGHT];
        $dzwidth = $dims['width'];
        $dzheight = $dims['height'];

        $dzleft = $canvas['imgx'] + ((float) ($place['xy'][0] ?? 0) * $canvas['scale']);
        $dztop = $canvas['imgy'] + ((float) ($place['xy'][1] ?? 0) * $canvas['scale']);

        $responsevalue = (int) ($responses[$placeno] ?? 0);
        if ($responsevalue === 0) {
            $bordercolor = self::BORDER_NEUTRAL;
        } else {
            $iscorrect = $this->is_choice_correct($placeno, $responsevalue);
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
     * Build a small check / cross badge in the drop zone's bottom-right
     * corner so correctness is conveyed by shape, not colour alone (WCAG 1.4.1
     * — also useful for black-and-white printing).
     *
     * The badge consists of a white-filled circle with a coloured stroke
     * matching the border colour, plus the check / cross path centred inside.
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
     * @param float $scale Source pixel to canvas point scale factor.
     * @return array<int, array{width:float, height:float}>
     */
    private function compute_group_dropzone_dimensions(float $scale): array {
        $dimensions = [];
        $question = $this->questionattempt->get_question();
        if (empty($question->choices)) {
            return $dimensions;
        }

        foreach ($question->choices as $groupid => $choices) {
            $maxw = self::MIN_DROPZONE_WIDTH;
            $maxh = self::MIN_DROPZONE_HEIGHT;

            foreach ($choices as $choice) {
                list($contentw, $contenth) = $this->measure_choice($choice, $scale);
                $maxw = max($maxw, $contentw + (self::DROPZONE_PADDING_X * 2));
                $maxh = max($maxh, $contenth + (self::DROPZONE_PADDING_Y * 2));
            }

            $dimensions[(int) $groupid] = ['width' => $maxw, 'height' => $maxh];
        }
        return $dimensions;
    }

    /**
     * Measure the bounding size required to display a choice (text or image).
     *
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

    /**
     * Render the content of a chosen item inside its drop zone (centred,
     * fitted to the zone's inner area).
     */
    private function render_choice_inside_dropzone($choice, float $dzleft, float $dztop, float $dzwidth, float $dzheight, float $scale): string {
        $info = !empty($choice->id) ? $this->get_image_info('dragimage', (int) $choice->id) : null;
        if ($info !== null) {
            return $this->render_image_in_dropzone($info, $dzleft, $dztop, $dzwidth, $dzheight, $scale);
        }
        $label = $this->truncate_label(strip_tags((string) ($choice->text ?? '')));
        return $this->render_text_in_dropzone($label, $dzleft, $dztop, $dzwidth, $dzheight);
    }

    /**
     * Center an image inside the drop zone, scaled down to fit while
     * preserving its aspect ratio.
     */
    private function render_image_in_dropzone(array $info, float $dzleft, float $dztop, float $dzwidth, float $dzheight, float $scale): string {
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
     * Render a text label centered in the drop zone with a font size that
     * fits the available area.
     */
    private function render_text_in_dropzone(string $label, float $dzleft, float $dztop, float $dzwidth, float $dzheight): string {
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

    /**
     * Trim the label to MAX_LABEL_CHARS characters, appending a horizontal
     * ellipsis when truncation occurs.
     */
    private function truncate_label(string $label): string {
        if (mb_strlen($label) <= self::MAX_LABEL_CHARS) {
            return $label;
        }
        return mb_substr($label, 0, self::MAX_LABEL_CHARS - 3) . '...';
    }

    /**
     * Read drop zone descriptors from data-place-info JSON in the HTML.
     *
     * @return array<int, array{group:int, xy:array{0:int,1:int}}>
     */
    private function extract_places_from_html(): array {
        if (!preg_match('#\bdata-place-info=("|\')(.*?)\1#is', $this->questionhtml, $matches)) {
            return [];
        }
        $json = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $places = [];
        foreach ($decoded as $key => $entry) {
            if (!is_array($entry) || !isset($entry['xy']) || !is_array($entry['xy'])) {
                continue;
            }
            $placeno = isset($entry['no']) ? (int) $entry['no'] : (int) $key;
            $places[$placeno] = [
                'group' => isset($entry['group']) ? (int) $entry['group'] : 1,
                'xy' => [(int) $entry['xy'][0], (int) $entry['xy'][1]],
            ];
        }
        return $places;
    }

    /**
     * Read response values from the placeinput hidden inputs.
     *
     * @return array<int, int>
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
            $value = 0;
            if (preg_match('#\bvalue="([^"]*)"#i', $tag, $valuematch)) {
                $value = (int) $valuematch[1];
            }
            $responses[(int) $placematch[1]] = $value;
        }
        return $responses;
    }

    /**
     * Defensive fallback: if the question was lazily fetched without
     * apply_attempt_state(), choiceorder may be empty. Apply the first step
     * so resolve_choice() and get_right_choice_for() return consistent data.
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
            debugging('quiz_export ddimageortext: choiceorder fallback failed: '
                . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    private function resolve_choice(int $groupno, int $responsevalue) {
        $question = $this->questionattempt->get_question();
        if (isset($question->choiceorder[$groupno][$responsevalue])) {
            $choiceid = $question->choiceorder[$groupno][$responsevalue];
            if (isset($question->choices[$groupno][$choiceid])) {
                return $question->choices[$groupno][$choiceid];
            }
        }
        if (isset($question->choices[$groupno][$responsevalue])) {
            return $question->choices[$groupno][$responsevalue];
        }
        return null;
    }

    private function is_choice_correct(int $placeno, int $responsevalue): bool {
        $question = $this->questionattempt->get_question();
        if (!method_exists($question, 'get_right_choice_for')) {
            return false;
        }
        $rightchoice = $question->get_right_choice_for($placeno);
        if ($rightchoice === null) {
            return false;
        }
        return ((int) $rightchoice) === $responsevalue;
    }

    /**
     * Determine which choices the student did not drop on the image.
     *
     * Identification key: the array key in $question->choices[$group], which
     * per qtype_ddimageortext_base::initialise_question_instance() is the
     * dragdata->no (1 for the first item in a group, then increasing) — NOT
     * the choice DB id. The response value is a choiceorder index that maps
     * back to that same array key.
     *
     * Falls back to using the response value directly when choiceorder isn't
     * initialised on the question (observed when the question is loaded with
     * lazy initialisation). This mirrors resolve_choice() so the unplaced
     * computation stays consistent with how the dropzones are rendered.
     *
     * @param array<int, array{group:int,xy:array{0:int,1:int}}> $places
     * @param array<int, int> $responses
     * @return array<int, array{group:int, choice:object}>
     */
    private function collect_unplaced_choices(array $places, array $responses): array {
        $question = $this->questionattempt->get_question();
        if (empty($question->choices)) {
            return [];
        }

        $usedchoicekeys = [];
        foreach ($responses as $placeno => $responsevalue) {
            if ($responsevalue === 0 || !isset($places[$placeno])) {
                continue;
            }
            $group = (int) $places[$placeno]['group'];
            $choicekey = $this->resolve_choice_key($group, $responsevalue);
            if ($choicekey === null) {
                continue;
            }
            $usedchoicekeys[$group][$choicekey] = true;
        }

        $unplaced = [];
        foreach ($question->choices as $groupid => $groupchoices) {
            $groupid = (int) $groupid;
            foreach ($groupchoices as $choicekey => $choice) {
                if (isset($usedchoicekeys[$groupid][(int) $choicekey])) {
                    continue;
                }
                $unplaced[] = ['group' => $groupid, 'choice' => $choice];
            }
        }
        return $unplaced;
    }

    /**
     * Resolve a response value to the array key it points to in
     * $question->choices[$group]. Mirrors resolve_choice() but returns the
     * key instead of the choice object.
     */
    private function resolve_choice_key(int $groupno, int $responsevalue): ?int {
        $question = $this->questionattempt->get_question();
        if (isset($question->choiceorder[$groupno][$responsevalue])) {
            return (int) $question->choiceorder[$groupno][$responsevalue];
        }
        if (isset($question->choices[$groupno][$responsevalue])) {
            return $responsevalue;
        }
        return null;
    }

    /**
     * Build the inner HTML for an unplaced choice's pill: image thumbnail
     * for image-typed choices, plain escaped text otherwise.
     */
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