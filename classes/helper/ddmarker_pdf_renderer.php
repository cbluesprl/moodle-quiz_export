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
 * PDF renderer for ddmarker questions.
 *
 * @package   quiz_export
 * @copyright 2026 CBlue SRL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_export\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Render ddmarker (drag-and-drop markers) questions for PDF export.
 *
 * The renderer uses a fixed canvas coordinate system regardless of the
 * source background image size: the image is always normalised to a
 * predefined width with constant padding, and every UI primitive (marker
 * dot, label font, connector line) is sized in canvas-points so the result
 * looks the same whether the source image is 176×104 or 2000×1500.
 *
 * Each marker is rendered in two parts:
 *  - a coloured dot at the exact pixel where the student dropped it,
 *  - a separate text label placed next to the dot using a greedy placement
 *    algorithm that avoids overlapping other markers or labels already
 *    placed on the canvas.
 *
 * The dot/label split makes the precise marker location unambiguous, which
 * is the priority for grading review.
 */
class ddmarker_pdf_renderer extends abstract_qtype_pdf_renderer {

    /** @var float Image render width inside the canvas, in points. */
    private const IMAGE_WIDTH_PT = 480.0;

    /** @var float Padding around the image inside the canvas, in points. */
    private const CANVAS_PADDING_PT = 60.0;

    /** @var float Marker dot radius in canvas points. */
    private const DOT_RADIUS_PT = 4.5;

    /** @var float Font size for marker labels in canvas points. */
    private const FONT_SIZE_PT = 10.0;

    /** @var float Distance between the dot edge and the label box, in canvas points. */
    private const LABEL_OFFSET_PT = 8.0;

    /** @var float Stroke width for the dot-to-label connector line, in canvas points. */
    private const CONNECTOR_WIDTH_PT = 0.6;

    /** @var float Approximate average character width as a fraction of the font size. */
    private const CHAR_WIDTH_RATIO = 0.55;

    public function render_for_pdf(): string {
        $questionattempt = $this->questionattempt;
        $question = $questionattempt->get_question();
        if (empty($question->choices) || empty($question->choices[1])) {
            return $this->questionhtml;
        }

        $bgimagedata = $this->get_image_data_uri('bgimage', $this->get_question_id());
        if ($bgimagedata === null) {
            return $this->questionhtml;
        }

        list($imagewidth, $imageheight) = $this->get_background_image_size();
        if ($imagewidth === 0 || $imageheight === 0) {
            return $this->questionhtml;
        }

        $canvas = $this->build_canvas_geometry($imagewidth, $imageheight);
        $svgcontent = $this->build_background_svg($bgimagedata, $canvas);

        $markers = $this->collect_markers($canvas);
        $occupiedboxes = $this->initial_occupied_boxes($markers);

        foreach ($markers as $marker) {
            $svgcontent .= $this->render_marker($marker, $occupiedboxes);
        }

        $svgblock = '<div class="quiz_export-dd-svg" style="text-align:center;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'viewBox="0 0 ' . $canvas['totalw'] . ' ' . $canvas['totalh'] . '" '
            . 'width="' . $canvas['totalw'] . '" '
            . 'preserveAspectRatio="xMidYMid meet">'
            . $svgcontent
            . '</svg></div>';

        return $this->replace_div_with_class('ddarea', $svgblock);
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

    /**
     * Build the background image element placed inside the canvas.
     */
    private function build_background_svg(string $bgimagedata, array $canvas): string {
        return '<image x="' . $canvas['imgx'] . '" y="' . $canvas['imgy'] . '" '
            . 'width="' . $canvas['imgw'] . '" height="' . $canvas['imgh'] . '" '
            . 'href="' . $bgimagedata . '" '
            . 'xlink:href="' . $bgimagedata . '" />';
    }

    /**
     * Collect every marker placed by the student and pre-compute its dot
     * position in canvas points along with the label width estimate.
     *
     * @return array<int, array{cx:float, cy:float, label:string, iscorrect:bool, textwidth:float}>
     */
    private function collect_markers(array $canvas): array {
        $markers = [];
        $question = $this->questionattempt->get_question();
        $orderedchoices = $question->get_ordered_choices(1);

        foreach ($orderedchoices as $choiceno => $drag) {
            $coordstring = (string) $this->questionattempt->get_last_qt_var('c' . $choiceno);
            if (trim($coordstring) === '') {
                continue;
            }

            foreach (explode(';', $coordstring) as $rawcoord) {
                $rawcoord = trim($rawcoord);
                if ($rawcoord === '') {
                    continue;
                }
                $xy = explode(',', $rawcoord);
                if (count($xy) !== 2 || !is_numeric($xy[0]) || !is_numeric($xy[1])) {
                    continue;
                }
                $point = [(int) round((float) $xy[0]), (int) round((float) $xy[1])];

                $label = strip_tags((string) $drag->text);
                $markers[] = [
                    'cx' => $canvas['imgx'] + ($point[0] * $canvas['scale']),
                    'cy' => $canvas['imgy'] + ($point[1] * $canvas['scale']),
                    'label' => $label,
                    'iscorrect' => $this->is_marker_correct($choiceno, $point),
                    'textwidth' => $this->estimate_text_width($label),
                ];
            }
        }
        return $markers;
    }

    /**
     * Build the list of bounding boxes the label-placement algorithm must
     * avoid. We pre-populate it with every marker dot so labels never sit
     * directly on top of another marker.
     *
     * @param array $markers
     * @return array<int, array{0:float,1:float,2:float,3:float}>
     */
    private function initial_occupied_boxes(array $markers): array {
        $boxes = [];
        $r = self::DOT_RADIUS_PT + 1;
        foreach ($markers as $marker) {
            $boxes[] = [$marker['cx'] - $r, $marker['cy'] - $r, $marker['cx'] + $r, $marker['cy'] + $r];
        }
        return $boxes;
    }

    /**
     * Render a marker (dot + connector line + halo label + correctness icon).
     *
     * @param array $marker Marker descriptor produced by collect_markers().
     * @param array<int, array{0:float,1:float,2:float,3:float}> &$occupiedboxes Already-placed bounding boxes.
     * @return string
     */
    private function render_marker(array $marker, array &$occupiedboxes): string {
        $cx = $marker['cx'];
        $cy = $marker['cy'];

        $dotsvg = '<circle cx="' . $cx . '" cy="' . $cy . '" '
            . 'r="' . self::DOT_RADIUS_PT . '" '
            . 'fill="#cc6600" stroke="#ffffff" stroke-width="1.5"/>';

        $labelplacement = $this->find_label_position($cx, $cy, $marker['textwidth'], $occupiedboxes);
        $occupiedboxes[] = $labelplacement['box'];

        $connectorsvg = $this->build_connector_line($cx, $cy, $labelplacement);
        $labelsvg = $this->build_halo_label(
            $labelplacement['tx'], $labelplacement['ty'], $labelplacement['anchor'],
            $marker['label']
        );

        $iconsvg = '';
        if ($this->should_show_correctness()) {
            $iconanchor = $this->compute_correctness_icon_anchor($labelplacement, $marker['textwidth']);
            $iconsvg = $this->build_correctness_svg(
                $marker['iscorrect'],
                $iconanchor[0], $iconanchor[1],
                self::FONT_SIZE_PT
            );
        }

        return $connectorsvg . $dotsvg . $labelsvg . $iconsvg;
    }

    /**
     * Search for a label position around the dot that does not collide with
     * boxes already placed on the canvas. Order of preference: below, above,
     * right, left. Falls back to "below" if everything collides.
     *
     * @return array{tx:float, ty:float, anchor:string, side:string, box:array{0:float,1:float,2:float,3:float}}
     */
    private function find_label_position(float $cx, float $cy, float $textwidth, array $occupiedboxes): array {
        $candidates = $this->build_label_candidates($cx, $cy, $textwidth);
        foreach ($candidates as $candidate) {
            if (!$this->box_collides($candidate['box'], $occupiedboxes)) {
                return $candidate;
            }
        }
        return $candidates[0];
    }

    /**
     * Build the ordered list of candidate label positions around a dot.
     *
     * Each entry holds the SVG text anchor coordinates plus a bounding box
     * used for collision detection.
     */
    private function build_label_candidates(float $cx, float $cy, float $textwidth): array {
        $font = self::FONT_SIZE_PT;
        $halfwidth = $textwidth / 2;
        $halfheight = $font / 2;
        $offset = self::DOT_RADIUS_PT + self::LABEL_OFFSET_PT;
        $textbaselineadjust = $font * 0.3;

        $candidates = [
            // Below: text-anchor middle, baseline below the dot.
            [
                'side' => 'below',
                'tx' => $cx,
                'ty' => $cy + $offset + $font,
                'anchor' => 'middle',
                'box' => [$cx - $halfwidth - 2, $cy + $offset, $cx + $halfwidth + 2, $cy + $offset + $font + 4],
            ],
            // Above
            [
                'side' => 'above',
                'tx' => $cx,
                'ty' => $cy - $offset - $textbaselineadjust,
                'anchor' => 'middle',
                'box' => [$cx - $halfwidth - 2, $cy - $offset - $font - 4, $cx + $halfwidth + 2, $cy - $offset],
            ],
            // Right
            [
                'side' => 'right',
                'tx' => $cx + $offset,
                'ty' => $cy + $textbaselineadjust,
                'anchor' => 'start',
                'box' => [$cx + $offset, $cy - $halfheight - 2, $cx + $offset + $textwidth + 4, $cy + $halfheight + 2],
            ],
            // Left
            [
                'side' => 'left',
                'tx' => $cx - $offset,
                'ty' => $cy + $textbaselineadjust,
                'anchor' => 'end',
                'box' => [$cx - $offset - $textwidth - 4, $cy - $halfheight - 2, $cx - $offset, $cy + $halfheight + 2],
            ],
        ];

        return $candidates;
    }

    /**
     * Check whether a candidate bounding box overlaps any of the occupied boxes.
     *
     * @param array{0:float,1:float,2:float,3:float} $box
     * @param array<int, array{0:float,1:float,2:float,3:float}> $occupiedboxes
     */
    private function box_collides(array $box, array $occupiedboxes): bool {
        list($x1, $y1, $x2, $y2) = $box;
        foreach ($occupiedboxes as $other) {
            list($ox1, $oy1, $ox2, $oy2) = $other;
            if ($x1 < $ox2 && $x2 > $ox1 && $y1 < $oy2 && $y2 > $oy1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Draw the thin grey connector line between the dot and the label box.
     */
    private function build_connector_line(float $cx, float $cy, array $labelplacement): string {
        list($x1, $y1, $x2, $y2) = $labelplacement['box'];

        switch ($labelplacement['side']) {
            case 'above':
                $endx = ($x1 + $x2) / 2;
                $endy = $y2;
                break;
            case 'right':
                $endx = $x1;
                $endy = ($y1 + $y2) / 2;
                break;
            case 'left':
                $endx = $x2;
                $endy = ($y1 + $y2) / 2;
                break;
            case 'below':
            default:
                $endx = ($x1 + $x2) / 2;
                $endy = $y1;
                break;
        }

        return '<line x1="' . $cx . '" y1="' . $cy . '" '
            . 'x2="' . $endx . '" y2="' . $endy . '" '
            . 'stroke="#999999" stroke-width="' . self::CONNECTOR_WIDTH_PT . '" '
            . 'stroke-dasharray="2,1.5"/>';
    }

    /**
     * Render a label with a white halo behind bold orange text.
     */
    private function build_halo_label(float $tx, float $ty, string $anchor, string $label): string {
        $strokewidth = self::FONT_SIZE_PT * 0.4;
        $common = 'x="' . $tx . '" y="' . $ty . '" '
            . 'text-anchor="' . $anchor . '" '
            . 'font-family="Helvetica, Arial, sans-serif" '
            . 'font-size="' . self::FONT_SIZE_PT . '" '
            . 'font-weight="bold"';
        $escaped = $this->svg_escape($label);
        return '<text ' . $common . ' fill="#ffffff" stroke="#ffffff" '
            . 'stroke-width="' . $strokewidth . '" stroke-linejoin="round">' . $escaped . '</text>'
            . '<text ' . $common . ' fill="#cc6600">' . $escaped . '</text>';
    }

    /**
     * Where to anchor the correctness icon relative to the label box.
     *
     * @return array{0:float,1:float} Icon centre coordinates.
     */
    private function compute_correctness_icon_anchor(array $labelplacement, float $textwidth): array {
        list($x1, $y1, $x2, $y2) = $labelplacement['box'];
        $cy = ($y1 + $y2) / 2;
        return [$x2 + (self::FONT_SIZE_PT * 0.6), $cy];
    }

    /**
     * Determine whether a marker placed at the given image-space coordinates
     * falls inside any drop zone whose right answer is this choice.
     *
     * @param int $choiceno
     * @param array{0:int,1:int} $point Marker coordinates in original image pixels.
     */
    private function is_marker_correct(int $choiceno, array $point): bool {
        $question = $this->questionattempt->get_question();
        if (empty($question->places)) {
            return false;
        }
        foreach ($question->places as $placeno => $place) {
            $rightchoice = $question->get_right_choice_for($placeno);
            if ((int) $rightchoice !== $choiceno) {
                continue;
            }
            if ($place->drop_hit($point)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Conservative estimate of the rendered width of a label in canvas points.
     */
    private function estimate_text_width(string $label): float {
        $charcount = max(1, mb_strlen($label));
        return $charcount * (self::FONT_SIZE_PT * self::CHAR_WIDTH_RATIO);
    }
}