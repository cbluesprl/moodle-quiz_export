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

    /** @var float Full size (width and height) of the marker crosshair, in canvas points. */
    private const CROSSHAIR_SIZE_PT = 12.5;

    /** @var float Font size for marker labels in canvas points. */
    private const FONT_SIZE_PT = 15.5;

    /** @var float Distance between the crosshair edge and the label box, in canvas points. */
    private const LABEL_OFFSET_PT = 6.0;

    /** @var float Stroke width for the crosshair-to-label connector line, in canvas points. */
    private const CONNECTOR_WIDTH_PT = 0.6;

    /** @var float Pill corner radius in canvas points. */
    private const PILL_BORDER_RADIUS_PT = 4.0;

    /** @var float Pill border stroke width in canvas points. */
    private const PILL_BORDER_WIDTH_PT = 0.7;

    /** @var float Gap between the markertext and the correctness icon inside the pill. */
    private const CORRECTNESS_ICON_GAP_PT = 4.0;

    /** @var float Font size of the expected-answer label drawn over a missed drop zone. */
    private const ZONE_LABEL_FONT_PT = 9.0;

    /** @var float Inner padding (horizontal & vertical) of the expected-answer pill, in canvas points. */
    private const ZONE_LABEL_PADDING_PT = 3.0;

    public function render_for_pdf(): string {
        $this->ensure_question_state_applied();

        $question = $this->questionattempt->get_question();
        if (empty($question->choices) || empty($question->choices[1])) {
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

        $response = $this->collect_response();
        $chosenhits = $this->compute_chosen_hits($response);

        $canvas = $this->build_canvas_geometry($imagewidth, $imageheight);
        $svgcontent = $this->build_background_svg($bgimagedata, $canvas);

        if ($this->should_show_misplaced_zones()) {
            foreach ($question->get_drop_zones_without_hit($response) as $zone) {
                $svgcontent .= $this->render_expected_zone($zone, $canvas);
            }
        }

        $markers = $this->collect_markers($canvas, $chosenhits);
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

        $unplacedpills = array_map(
            fn($drag) => $this->build_unplaced_pill(strip_tags((string) $drag->text)),
            $this->collect_unplaced_choices()
        );
        $unplacedhtml = $this->render_unplaced_section($unplacedpills);

        return $this->replace_div_with_class('ddarea', $svgblock . $unplacedhtml);
    }

    /**
     * Build the response array in the format expected by Moodle's grading
     * methods: ['c1' => 'x1,y1;x2,y2', 'c2' => 'x,y', ...].
     */
    private function collect_response(): array {
        $response = [];
        foreach ($this->questionattempt->get_question()->get_ordered_choices(1) as $choiceno => $unused) {
            $value = $this->questionattempt->get_last_qt_var('c' . $choiceno);
            if ($value !== null && trim((string) $value) !== '') {
                $response['c' . $choiceno] = (string) $value;
            }
        }
        return $response;
    }

    /**
     * Re-implement qtype_ddmarker_question::choose_hits() (protected upstream).
     * Mirrors the reference algorithm so per-marker correctness in our SVG
     * matches what Moodle's review page reports.
     *
     * @param array $response
     * @return array<int, string> Map placeno => "$choice $itemno".
     */
    private function compute_chosen_hits(array $response): array {
        $question = $this->questionattempt->get_question();

        $hits = [];
        foreach ($question->places as $placeno => $place) {
            $rightchoice = $question->get_right_choice_for($placeno);
            if ($rightchoice === null) {
                continue;
            }
            $rightchoicekey = $question->choice($rightchoice);
            if (!array_key_exists($rightchoicekey, $response)) {
                continue;
            }
            foreach (explode(';', $response[$rightchoicekey]) as $itemno => $coord) {
                if (trim($coord) === '') {
                    continue;
                }
                $xy = explode(',', $coord);
                if (count($xy) !== 2) {
                    continue;
                }
                $point = [(int) round((float) $xy[0]), (int) round((float) $xy[1])];
                if ($place->drop_hit($point)) {
                    $hits[$placeno][$itemno] = $coord;
                }
            }
        }
        uasort($hits, fn($a, $b) => count($a) - count($b));

        $chosenhits = [];
        foreach ($hits as $placeno => $placehits) {
            $rightchoice = $question->get_right_choice_for($placeno);
            foreach ($placehits as $itemno => $unused) {
                $choiceitem = "$rightchoice $itemno";
                if (!in_array($choiceitem, $chosenhits, true)) {
                    $chosenhits[$placeno] = $choiceitem;
                    break;
                }
            }
        }
        return $chosenhits;
    }

    /**
     * Whether the question is configured to highlight expected drop zones
     * for misplaced markers (Moodle's `showmisplaced` setting), and the
     * attempt is finished.
     */
    private function should_show_misplaced_zones(): bool {
        $question = $this->questionattempt->get_question();
        return !empty($question->showmisplaced)
            && $this->questionattempt->get_state()->is_finished();
    }

    /**
     * Render an "expected drop zone" overlay for a place the student missed,
     * using the shape's natural geometry, and overlay the expected answer
     * label inside the zone on a yellow translucent highlight pill so the
     * grader can see at a glance which marker was supposed to be dropped
     * there. Mirrors what Moodle's runtime JS draws based on
     * `data-visibled-dropzones`.
     */
    private function render_expected_zone($zone, array $canvas): string {
        $shape = (string) ($zone->shape ?? '');
        $coords = (string) ($zone->coords ?? '');
        $markertext = strip_tags((string) ($zone->markertext ?? ''));
        $stroke = '#000000';
        $fill = 'rgba(255, 213, 79, 0.4)';
        $strokewidth = 1.5;

        $offset = function (array $xy) use ($canvas): array {
            return [
                $canvas['imgx'] + ($xy[0] * $canvas['scale']),
                $canvas['imgy'] + ($xy[1] * $canvas['scale']),
            ];
        };

        if ($shape === 'circle') {
            $parts = explode(';', $coords);
            if (count($parts) !== 2) {
                return '';
            }
            $centre = explode(',', $parts[0]);
            if (count($centre) !== 2) {
                return '';
            }
            [$cx, $cy] = $offset([(float) $centre[0], (float) $centre[1]]);
            $r = ((float) $parts[1]) * $canvas['scale'];
            $shapesvg = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" '
                . 'fill="' . $fill . '" stroke="' . $stroke . '" '
                . 'stroke-width="' . $strokewidth . '"/>';
            $centroid = [$cx, $cy];
        } else if ($shape === 'rectangle') {
            $parts = explode(';', $coords);
            if (count($parts) !== 2) {
                return '';
            }
            $topleft = explode(',', $parts[0]);
            $size = explode(',', $parts[1]);
            if (count($topleft) !== 2 || count($size) !== 2) {
                return '';
            }
            [$x, $y] = $offset([(float) $topleft[0], (float) $topleft[1]]);
            $w = ((float) $size[0]) * $canvas['scale'];
            $h = ((float) $size[1]) * $canvas['scale'];
            $shapesvg = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" '
                . 'fill="' . $fill . '" stroke="' . $stroke . '" '
                . 'stroke-width="' . $strokewidth . '"/>';
            $centroid = [$x + ($w / 2), $y + ($h / 2)];
        } else if ($shape === 'polygon') {
            $points = [];
            $sumx = 0.0;
            $sumy = 0.0;
            $count = 0;
            foreach (explode(';', $coords) as $pair) {
                $xy = explode(',', $pair);
                if (count($xy) !== 2) {
                    continue;
                }
                [$x, $y] = $offset([(float) $xy[0], (float) $xy[1]]);
                $points[] = $x . ',' . $y;
                $sumx += $x;
                $sumy += $y;
                $count++;
            }
            if (empty($points)) {
                return '';
            }
            $shapesvg = '<polygon points="' . implode(' ', $points) . '" '
                . 'fill="' . $fill . '" stroke="' . $stroke . '" '
                . 'stroke-width="' . $strokewidth . '"/>';
            $centroid = [$sumx / $count, $sumy / $count];
        } else {
            return '';
        }

        if ($markertext !== '') {
            $shapesvg .= $this->build_expected_zone_label($centroid[0], $centroid[1], $markertext);
        }

        return $shapesvg;
    }

    /**
     * Render the expected-answer label centred on a missed drop zone.
     *
     * The label is drawn as a rounded rectangle filled with a translucent
     * yellow (highlighter feel) and a darker yellow border, with the marker
     * text in dark amber bold for readability over both the underlying zone
     * and the background image.
     */
    private function build_expected_zone_label(float $cx, float $cy, string $text): string {
        $font = self::ZONE_LABEL_FONT_PT;
        $padding = self::ZONE_LABEL_PADDING_PT;
        $textwidth = mb_strlen($text) * $font * self::CHAR_WIDTH_RATIO;

        $rectw = $textwidth + (2 * $padding);
        $recth = $font + (2 * $padding);
        $rectx = $cx - ($rectw / 2);
        $recty = $cy - ($recth / 2);

        // Approximate baseline correction so the text appears vertically centred.
        $ty = $cy + ($font * 0.35);
        $escaped = $this->svg_escape($text);

        return '<rect x="' . $rectx . '" y="' . $recty . '" '
            . 'width="' . $rectw . '" height="' . $recth . '" '
            . 'fill="#ffeb3b" fill-opacity="0.75" '
            . 'stroke="#cca300" stroke-opacity="0.9" stroke-width="0.5" rx="2" ry="2"/>'
            . '<text x="' . $cx . '" y="' . $ty . '" '
            . 'text-anchor="middle" '
            . 'font-family="Helvetica, Arial, sans-serif" '
            . 'font-size="' . $font . '" '
            . 'font-weight="bold" '
            . 'fill="#5c4d00">' . $escaped . '</text>';
    }

    /**
     * Identify the marker labels the student did not drop anywhere on the
     * image. A choice is considered placed when its c{choiceno} qt_var holds
     * at least one parseable "x,y" pair.
     *
     * @return array<int, object> Drag descriptors as returned by get_ordered_choices.
     */
    private function collect_unplaced_choices(): array {
        $unplaced = [];
        foreach ($this->questionattempt->get_question()->get_ordered_choices(1) as $choiceno => $drag) {
            if (!$this->is_choice_placed((int) $choiceno)) {
                $unplaced[] = $drag;
            }
        }
        return $unplaced;
    }

    /**
     * Build a Moodle-style markertext pill for an unplaced marker label.
     * Mirrors `.que.ddmarker .draghomes .marker span.markertext` (white bg,
     * 2px black border with 10px radius, 0.6 opacity).
     */
    private function build_unplaced_pill(string $label): string {
        if (trim($label) === '') {
            return '';
        }
        $style = 'display:inline-block; '
            . 'padding: 4pt 8pt; '
            . 'border: 1.5pt solid #000; '
            . 'background-color: #ffffff; '
            . 'color: #000; '
            . 'border-radius: 8pt; '
            . 'font-family: Arial, Helvetica, sans-serif; '
            . 'font-size: ' . self::FONT_SIZE_PT . 'pt; '
            . 'margin: 3pt; '
            . 'opacity: 0.7; '
            . 'vertical-align: middle;';
        $escaped = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<span style="' . $style . '">' . $escaped . '</span>';
    }

    /**
     * Whether a choice has at least one valid (x,y) placement recorded.
     */
    private function is_choice_placed(int $choiceno): bool {
        $coordstring = (string) $this->questionattempt->get_last_qt_var('c' . $choiceno);
        if (trim($coordstring) === '') {
            return false;
        }
        foreach (explode(';', $coordstring) as $rawcoord) {
            $rawcoord = trim($rawcoord);
            if ($rawcoord === '') {
                continue;
            }
            $xy = explode(',', $rawcoord);
            if (count($xy) === 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect every marker placed by the student and pre-compute its dot
     * position in canvas points along with the label width estimate.
     *
     * @param array $canvas Canvas geometry produced by build_canvas_geometry().
     * @param array<int, string> $chosenhits Map placeno => "$choice $itemno"
     *      from compute_chosen_hits(); used to label each marker correct/wrong.
     * @return array<int, array{cx:float, cy:float, label:string, iscorrect:bool, textwidth:float}>
     */
    private function collect_markers(array $canvas, array $chosenhits): array {
        $markers = [];
        $hitset = array_flip($chosenhits);
        $question = $this->questionattempt->get_question();

        foreach ($question->get_ordered_choices(1) as $choiceno => $drag) {
            $coordstring = (string) $this->questionattempt->get_last_qt_var('c' . $choiceno);
            if (trim($coordstring) === '') {
                continue;
            }

            foreach (explode(';', $coordstring) as $itemno => $rawcoord) {
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
                    'iscorrect' => isset($hitset["$choiceno $itemno"]),
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
        $r = (self::CROSSHAIR_SIZE_PT / 2) + 1;
        foreach ($markers as $marker) {
            $boxes[] = [$marker['cx'] - $r, $marker['cy'] - $r, $marker['cx'] + $r, $marker['cy'] + $r];
        }
        return $boxes;
    }

    /**
     * Render a marker, mirroring Moodle's native runtime display:
     * a Font Awesome crosshair target at the dropped position plus a white
     * translucent rounded pill carrying the markertext (and, when correctness
     * is shown, the check / cross icon inside the pill, to the right of the
     * text).
     *
     * @param array $marker Marker descriptor produced by collect_markers().
     * @param array<int, array{0:float,1:float,2:float,3:float}> &$occupiedboxes Already-placed bounding boxes.
     * @return string
     */
    private function render_marker(array $marker, array &$occupiedboxes): string {
        $cx = $marker['cx'];
        $cy = $marker['cy'];

        $crosshairsvg = $this->build_crosshair_svg($cx, $cy);

        $showcorrect = $this->should_show_correctness();
        $textwidth = $marker['textwidth'];
        $totalwidth = $textwidth;
        if ($showcorrect) {
            $totalwidth += self::CORRECTNESS_ICON_GAP_PT + self::FONT_SIZE_PT;
        }

        $labelplacement = $this->find_label_position($cx, $cy, $totalwidth, $occupiedboxes);
        $occupiedboxes[] = $labelplacement['box'];

        $connectorsvg = $this->build_connector_line($cx, $cy, $labelplacement);
        $pillsvg = $this->build_pill_label(
            $labelplacement['box'], $marker['label'], $textwidth, $showcorrect, $marker['iscorrect']
        );

        return $connectorsvg . $crosshairsvg . $pillsvg;
    }

    /**
     * Render a crosshair target (Font Awesome `crosshairs` icon) centred on
     * the dropped position. The path is inlined to avoid hitting Moodle's
     * theme image endpoint at PDF generation time (which would require the
     * user session and break async exports).
     */
    private function build_crosshair_svg(float $cx, float $cy): string {
        $size = self::CROSSHAIR_SIZE_PT;
        $half = $size / 2;
        $scale = $size / 512;
        $tx = $cx - $half;
        $ty = $cy - $half;
        $path = 'M256 0c17.7 0 32 14.3 32 32V42.4c93.7 13.9 167.7 88 181.6 181.6H480'
            . 'c17.7 0 32 14.3 32 32s-14.3 32-32 32H469.6c-13.9 93.7-88 167.7-181.6 181.6V480'
            . 'c0 17.7-14.3 32-32 32s-32-14.3-32-32V469.6C130.3 455.7 56.3 381.7 42.4 288H32'
            . 'c-17.7 0-32-14.3-32-32s14.3-32 32-32H42.4C56.3 130.3 130.3 56.3 224 42.4V32'
            . 'c0-17.7 14.3-32 32-32zM107.4 288c12.5 58.3 58.4 104.1 116.6 116.6V384'
            . 'c0-17.7 14.3-32 32-32s32 14.3 32 32v20.6c58.3-12.5 104.1-58.4 116.6-116.6H384'
            . 'c-17.7 0-32-14.3-32-32s14.3-32 32-32h20.6C392.1 165.7 346.3 119.9 288 107.4V128'
            . 'c0 17.7-14.3 32-32 32s-32-14.3-32-32V107.4C165.7 119.9 119.9 165.7 107.4 224H128'
            . 'c17.7 0 32 14.3 32 32s-14.3 32-32 32H107.4zM256 224a32 32 0 1 1 0 64 32 32 0 1 1 0-64z';
        return '<g transform="translate(' . $tx . ' ' . $ty . ') scale(' . $scale . ')" '
            . 'fill="#000000">'
            . '<path d="' . $path . '"/>'
            . '</g>';
    }

    /**
     * Search for a pill position around the crosshair that does not collide
     * with boxes already placed on the canvas. Order of preference: below,
     * above, right, left. Falls back to "below" when everything collides.
     *
     * @return array{side:string, box:array{0:float,1:float,2:float,3:float}}
     */
    private function find_label_position(float $cx, float $cy, float $totalwidth, array $occupiedboxes): array {
        $candidates = $this->build_label_candidates($cx, $cy, $totalwidth);
        foreach ($candidates as $candidate) {
            if (!$this->box_collides($candidate['box'], $occupiedboxes)) {
                return $candidate;
            }
        }
        return $candidates[0];
    }

    /**
     * Build the ordered list of candidate pill positions around a crosshair.
     *
     * Each entry holds a 'side' (used by the connector line) and a 'box'
     * (used for collision detection and as the pill's drawing area).
     *
     * @return array<int, array{side:string, box:array{0:float,1:float,2:float,3:float}}>
     */
    private function build_label_candidates(float $cx, float $cy, float $totalwidth): array {
        $font = self::FONT_SIZE_PT;
        $halfwidth = $totalwidth / 2;
        $halfheight = $font / 2;
        $offset = (self::CROSSHAIR_SIZE_PT / 2) + self::LABEL_OFFSET_PT;
        $hpad = 4;
        $vpad = 3;

        return [
            [
                'side' => 'below',
                'box' => [
                    $cx - $halfwidth - $hpad, $cy + $offset,
                    $cx + $halfwidth + $hpad, $cy + $offset + $font + (2 * $vpad),
                ],
            ],
            [
                'side' => 'above',
                'box' => [
                    $cx - $halfwidth - $hpad, $cy - $offset - $font - (2 * $vpad),
                    $cx + $halfwidth + $hpad, $cy - $offset,
                ],
            ],
            [
                'side' => 'right',
                'box' => [
                    $cx + $offset, $cy - $halfheight - $vpad,
                    $cx + $offset + $totalwidth + (2 * $hpad), $cy + $halfheight + $vpad,
                ],
            ],
            [
                'side' => 'left',
                'box' => [
                    $cx - $offset - $totalwidth - (2 * $hpad), $cy - $halfheight - $vpad,
                    $cx - $offset, $cy + $halfheight + $vpad,
                ],
            ],
        ];
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
     * Render the Moodle-style markertext pill: a white translucent rounded
     * rectangle with a black border carrying the marker label, and, when
     * correctness display is enabled, the check / cross icon to the right of
     * the text inside the same pill.
     *
     * @param array{0:float,1:float,2:float,3:float} $box Pill bounding box.
     * @param string $text       The markertext.
     * @param float  $textwidth  Estimated rendered width of $text alone.
     * @param bool   $showcorrect Whether the correctness icon must be drawn.
     * @param bool   $iscorrect  Whether the marker is correctly placed.
     */
    private function build_pill_label(
        array $box,
        string $text,
        float $textwidth,
        bool $showcorrect,
        bool $iscorrect
    ): string {
        [$x1, $y1, $x2, $y2] = $box;
        $pillw = $x2 - $x1;
        $pillh = $y2 - $y1;
        $cy = ($y1 + $y2) / 2;
        $textbaseline = $cy + (self::FONT_SIZE_PT * 0.3);
        $escaped = $this->svg_escape($text);

        $svg = '<rect x="' . $x1 . '" y="' . $y1 . '" '
            . 'width="' . $pillw . '" height="' . $pillh . '" '
            . 'rx="' . self::PILL_BORDER_RADIUS_PT . '" '
            . 'ry="' . self::PILL_BORDER_RADIUS_PT . '" '
            . 'fill="#ffffff" fill-opacity="0.6" '
            . 'stroke="#000000" stroke-opacity="0.7" '
            . 'stroke-width="' . self::PILL_BORDER_WIDTH_PT . '"/>';

        if ($showcorrect) {
            // Layout inside the pill: [text][gap][icon], horizontally centred.
            $contentw = $textwidth + self::CORRECTNESS_ICON_GAP_PT + self::FONT_SIZE_PT;
            $startx = $x1 + (($pillw - $contentw) / 2);
            $iconcx = $startx + $textwidth + self::CORRECTNESS_ICON_GAP_PT + (self::FONT_SIZE_PT / 2);

            $svg .= '<text x="' . $startx . '" y="' . $textbaseline . '" '
                . 'text-anchor="start" '
                . 'font-family="Helvetica, Arial, sans-serif" '
                . 'font-size="' . self::FONT_SIZE_PT . '" '
                . 'fill="#000000">' . $escaped . '</text>'
                . $this->build_correctness_svg($iscorrect, $iconcx, $cy, self::FONT_SIZE_PT);
        } else {
            $tx = ($x1 + $x2) / 2;
            $svg .= '<text x="' . $tx . '" y="' . $textbaseline . '" '
                . 'text-anchor="middle" '
                . 'font-family="Helvetica, Arial, sans-serif" '
                . 'font-size="' . self::FONT_SIZE_PT . '" '
                . 'fill="#000000">' . $escaped . '</text>';
        }

        return $svg;
    }

    /**
     * Conservative estimate of the rendered width of a label in canvas points.
     */
    private function estimate_text_width(string $label): float {
        $charcount = max(1, mb_strlen($label));
        return $charcount * (self::FONT_SIZE_PT * self::CHAR_WIDTH_RATIO);
    }
}