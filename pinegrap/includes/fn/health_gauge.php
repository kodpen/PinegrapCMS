<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The System Status gauge and check chips, drawn by dashboard widget 2 and
 * by the new dashboard's System Status card alike: the half arc in the
 * score's colour band, the pixel matrix that twinkles through it, the glow
 * layers and the readout, and one chip per check linking where the check
 * says. The stylesheet (.pg-health*, .pg-check*) is global in
 * backend.src.css.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the System Status gauge and check chips.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The gauge: the .pg-health block and the script that pauses an off-screen
 * dial. Its SVG ids are fixed, so a page draws one gauge.
 *
 * @param int $health_score 0-100
 * @return string HTML
 */
function pg_health_gauge($health_score)
{
    $health_score = (int) $health_score;

    // Arc geometry. A half circle of r=52 about (70,70) --
    // the same shape and radius the performance widget draws,
    // so the two gauges on one dashboard read as one idea.
    // Circumference is 326.73 and half of it is 163.36; the
    // rest is the opening at the bottom, which is what leaves
    // room for the score to sit inside the arc rather than
    // under it.
    //
    // Drawn as a dashed circle rather than a path, so filling
    // it is one number instead of two arc endpoints in PHP.
    $health_arc = 163.36;

    // ── Colour band ─────────────────────────────────────
    //
    // Same gauge, same three-stop gradient, same glow. What
    // moves with the score is the hue: an arc that is the
    // identical violet at 12% and at 98% makes the reader work
    // the verdict out from the digits, and the colour is the
    // half that can be read across a room.
    //
    // Bands: red under 25, amber under 50, blue under 80,
    // green from 90. Eighty to ninety is the crossing between
    // the last two and is drawn as teal rather than lumped in
    // with either -- a site at 85 is neither "still wrong" nor
    // "finished".
    //
    // Each band is three stops of one family, light to dark,
    // plus one flat ink that the score, the caption and the
    // matrix behind them all take. The ink is an RGB triple
    // rather than a hex string because the matrix needs it at
    // several alphas.
    // ── Colour band, and where the gradient is laid ─────
    //
    // Four hues a band, and every band is a neon sweep in its
    // own right. What the score moves is the weight, not the
    // idea: the healthy end is cool -- lime into emerald into
    // cyan -- and the failing end is hot -- orange into rose
    // into violet. Deliberately not a traffic light. A flat
    // green arc and a flat red one would say what the number
    // already says, and would say it by throwing the gradient
    // away.
    //
    // All four sit at the same weight, and it matters. A pastel
    // first stop (#a7f3d0 was one) reads as washed-out white on
    // a dark card and a 600-weight last stop sinks into it, so
    // the bar came out bleached at one end and swallowed at the
    // other -- lightness doing the travelling instead of hue,
    // which is the one thing a gradient this small cannot
    // afford. Every stop is a 400: bright, saturated, and only
    // its hue different from its neighbour's.
    //
    // TWO sets of the four, because the same colours cannot
    // serve both themes. On a black card the arc has to be
    // bright to be seen; those same stops on a white one are
    // pastel, and a lime-into-mint sweep on white is barely a
    // sweep at all -- the four hues collapse into one wash.
    //
    // The light set is NOT the dark one darkened. Darkening
    // alone keeps the four hues as close together as they were
    // and merely makes them all deep, which on white reads as
    // one navy arc with a slight lean at each end. It is the
    // same journey travelled WIDER -- cyan to blue to violet to
    // fuchsia rather than cyan to sky to indigo to violet -- at
    // the 600/700 weights, where every stop clears three to one
    // against the page and no two neighbours are the same hue.
    //
    // Both are handed to CSS and the stylesheet picks; PHP has
    // no idea which theme it is rendering into.
    //
    // The ink is the flat colour the number takes, and it has
    // the same problem: a 32px amber number on white is under
    // three to one against the page.
    if ($health_score < 25) {
        $health_ink       = '236, 72, 153';
        $health_ink_text  = '190, 24, 93';
        $health_stops     = array('#fb923c', '#f43f5e', '#e879f9', '#a78bfa');
        $health_stops_lt  = array('#c2410c', '#be123c', '#a21caf', '#6d28d9');
    } elseif ($health_score < 50) {
        $health_ink       = '251, 146, 60';
        $health_ink_text  = '180, 83, 9';
        $health_stops     = array('#fde047', '#fb923c', '#fb7185', '#e879f9');
        $health_stops_lt  = array('#a16207', '#c2410c', '#be123c', '#9333ea');
    } elseif ($health_score < 80) {
        $health_ink       = '59, 130, 246';
        $health_ink_text  = '29, 78, 216';
        $health_stops     = array('#22d3ee', '#38bdf8', '#818cf8', '#c084fc');
        $health_stops_lt  = array('#0e7490', '#1d4ed8', '#6d28d9', '#a21caf');
    } elseif ($health_score < 90) {
        $health_ink       = '45, 212, 191';
        $health_ink_text  = '13, 148, 136';
        $health_stops     = array('#4ade80', '#2dd4bf', '#22d3ee', '#818cf8');
        $health_stops_lt  = array('#0d9488', '#0891b2', '#2563eb', '#6d28d9');
    } else {
        $health_ink       = '52, 211, 153';
        $health_ink_text  = '4, 120, 87';
        $health_stops     = array('#a3e635', '#34d399', '#22d3ee', '#818cf8');
        $health_stops_lt  = array('#65a30d', '#059669', '#0891b2', '#3730a3');
    }

    // The gradient is laid ALONG the drawn arc, not across the
    // circle's box. Across the box a site at 20% saw only the
    // first stop -- one flat colour, on exactly the card that
    // most needs to say something -- and even at 70% the last
    // hue never reached the bar. Anchored to the bar, the whole
    // sweep is on it at every score.
    //
    // Worked in the path's own space: an SVG circle starts at
    // three o'clock and runs clockwise, and the half turn in the
    // stylesheet is what puts the visible start at nine. So the
    // ends are computed here untransformed and rotate with
    // everything else.
    //
    // Clamped at a quarter turn: below it the two ends close on
    // each other, and a gradient whose axis has no length paints
    // as a single flat stop -- the very thing this prevents.
    $health_span  = max($health_score / 100, 0.25);
    $health_angle = M_PI * $health_span;
    $health_x2    = round(70 + (52 * cos($health_angle)), 2);
    $health_y2    = round(70 + (52 * sin($health_angle)), 2);

    // ── Where the stops go ──────────────────────────────
    //
    // Not evenly. A linear gradient changes colour evenly along
    // its AXIS, and the axis is the straight line between the
    // two ends of the arc -- so the arc is read by projecting
    // it onto that chord, and the projection is not even at
    // all. Near the ends of a half turn the arc runs almost
    // parallel to the chord and barely advances along it, so a
    // long stretch of bar gets a sliver of the gradient; at the
    // top it advances fastest and gets most of it. On a nearly
    // full arc that is exactly what you see: two flat legs and
    // every hue crammed into the crown.
    //
    // So each stop is placed at the axis position its own point
    // on the arc actually projects to. The four then arrive at
    // equal steps of ARC LENGTH, which is the thing being
    // looked at.
    $health_bx    = $health_x2 - 122;
    $health_by    = $health_y2 - 70;
    $health_len2  = ($health_bx * $health_bx) + ($health_by * $health_by);
    $health_marks = array();

    foreach (array(0, 1 / 3, 2 / 3, 1) as $health_u) {

        $health_t = $health_angle * $health_u;
        $health_qx = 70 + (52 * cos($health_t));
        $health_qy = 70 + (52 * sin($health_t));

        $health_o = ($health_len2 > 0)
            ? (((($health_qx - 122) * $health_bx) + (($health_qy - 70) * $health_by)) / $health_len2)
            : $health_u;

        if ($health_o < 0) { $health_o = 0; }
        if ($health_o > 1) { $health_o = 1; }

        $health_marks[] = round($health_o * 100, 2);
    }

    // The same axis mirrored through the centre, for the layer
    // that is written already turned. Rotating a point half a
    // turn about (70,70) is (140 - x, 140 - y).
    $health_mx2 = round(140 - $health_x2, 2);
    $health_my2 = round(140 - $health_y2, 2);

    // Everything the gauge is coloured from, in one place.
    $health_vars = '--pg-health-ink:' . $health_ink
        . ';--pg-health-ink-text:' . $health_ink_text;

    foreach (array(1, 2, 3, 4) as $health_stop) {
        $health_vars .= ';--pg-health-d' . $health_stop . ':' . $health_stops[$health_stop - 1]
            . ';--pg-health-l' . $health_stop . ':' . $health_stops_lt[$health_stop - 1];
    }

    // ── The cells ───────────────────────────────────────
    //
    // Real elements in the drawing, animated where they are
    // drawn. Nothing about the animation lives in <defs>: a
    // browser rasterises pattern and mask CONTENT into a cached
    // texture and stops refreshing it, so an animation put in
    // there goes on running while the picture sits still. What
    // IS in <defs> here is the clip, and a clip that never
    // changes is free to cache.
    //
    // Written in FINAL coordinates -- the half turn that used to
    // be done with a CSS transform on the group is done here, in
    // the numbers. The transform was not wrong, but it made the
    // group a different user space from everything else on the
    // gauge, and a clip or a mask on a transformed element is
    // resolved in the space the element was WRITTEN in, not the
    // space it ends up in. That cost two rounds of a bar with
    // squares only at its tips. With the rotation folded into
    // the coordinates there is one space and no question. The
    // gradient comes along: pg_health_gradient_m is the same
    // gradient with its axis mirrored through the centre, which
    // is what the rotation used to do to it.
    //
    // The squares are CUT by the bar rather than fitted inside
    // it. Whole squares chosen by their centres read as tiles
    // laid on top of an arc; squares clipped by the arc read as
    // a field of them showing THROUGH it, which is the picture
    // this is after -- and it lets the grid run right to both
    // rims instead of stopping a square short of each.
    $health_step = 2.6;
    $health_size = 2.05;
    $health_half = $health_size / 2;

    // The drawn half, in final coordinates: an SVG circle starts
    // at three o'clock and the stylesheet turns the arcs half a
    // turn, so the fill runs from nine o'clock over the top.
    $health_reach = M_PI * ($health_score / 100);
    $health_t0    = M_PI;
    $health_t1    = M_PI + $health_reach;

    $health_p0x = 70 + (52 * cos($health_t0));
    $health_p0y = 70 + (52 * sin($health_t0));
    $health_p1x = 70 + (52 * cos($health_t1));
    $health_p1y = 70 + (52 * sin($health_t1));

    // Wider than the ten units the arc is drawn with, so the
    // clip has whole squares to cut into halves at both rims.
    // The halo layer is NOT clipped, so this margin is also how
    // far the light spills past the bar.
    $health_edge = 6.6;

    $health_cells      = '';
    $health_glow_cells = '';
    $health_index      = 0;

    for ($health_gy = 8.0; $health_gy <= 76.0; $health_gy += $health_step) {
        for ($health_gx = 6.0; $health_gx <= 134.0; $health_gx += $health_step) {

            $health_cx = $health_gx + $health_half;
            $health_cy = $health_gy + $health_half;
            $health_dx = $health_cx - 70;
            $health_dy = $health_cy - 70;
            $health_rr = sqrt(($health_dx * $health_dx) + ($health_dy * $health_dy));

            // atan2 answers between -pi and pi; the fill runs
            // from pi to pi + reach, so the negative half is
            // brought round first.
            $health_ang = atan2($health_dy, $health_dx);
            if ($health_ang < 0) {
                $health_ang += 2 * M_PI;
            }

            if (($health_ang >= $health_t0) && ($health_ang <= $health_t1)) {

                $health_on = (abs($health_rr - 52) <= $health_edge);

            } else {

                // Past an end: inside the half disc the round
                // cap paints there.
                $health_c0x = $health_cx - $health_p0x;
                $health_c0y = $health_cy - $health_p0y;
                $health_c1x = $health_cx - $health_p1x;
                $health_c1y = $health_cy - $health_p1y;

                $health_on = ((($health_c0x * $health_c0x) + ($health_c0y * $health_c0y)) <= ($health_edge * $health_edge))
                    || ((($health_c1x * $health_c1x) + ($health_c1y * $health_c1y)) <= ($health_edge * $health_edge));
            }

            if (!$health_on) {
                continue;
            }

            $health_index++;

            // Slow, and no two squares the same length, so the
            // field never comes back round to a configuration it
            // has already been in.
            //
            // Two and a half to five and a half seconds for one
            // breath. It was four to nine, then three to seven:
            // both ends have come down each time the layers
            // under the squares got quieter, because the slower
            // a square breathes the more contrast it needs for
            // the change to be seen at all, and there is no
            // reason to spend contrast on it now that the
            // squares are the bar. Still slow enough that the
            // eye reads a surface quietly alive rather than a
            // thing blinking at it.
            $health_cell_time = round(2.6 + (fmod($health_index * 0.75488, 1) * 3.0), 2);

            // NEGATIVE delay. A positive one is a wait: until it
            // elapses the square has no animated value and sits
            // at full opacity, so the first seconds after a draw
            // were a cascade of squares dropping in one after
            // another -- a burst that has nothing to do with the
            // animation, and that made everything after it look
            // like the animation had died down. A negative delay
            // starts the cycle already part-way through: every
            // square is mid-breath from the first frame, and the
            // phases are spread from the first frame too.
            $health_cell_phase = round(fmod($health_index * 0.61803, 1) * $health_cell_time, 2);

            $health_box = ' x="' . round($health_gx, 2) . '" y="' . round($health_gy, 2) . '"'
                . ' width="' . $health_size . '" height="' . $health_size . '" rx="0.45"';

            // No class on the square: the animation is selected
            // through the group, which is a class name saved on
            // each of three hundred elements.
            $health_cells .= '<rect' . $health_box
                . ' style="animation-delay:-' . $health_cell_phase . 's;animation-duration:' . $health_cell_time . 's"/>';

            // The same square again for the halo below, without
            // the style, so the animation selector cannot reach
            // it -- see the note on that group.
            $health_glow_cells .= '<rect' . $health_box . '/>';
        }
    }

    // ── The shape that cuts them ────────────────────────
    //
    // The exact outline of the drawn bar: the outer rim, the
    // round cap at the far end, the inner rim back, the round
    // cap at the near end. A clipPath clips to the FILL of its
    // contents, so a stroked circle is no use here -- that would
    // clip to the disc, not to the band -- and the band has to be
    // written out as a closed path.
    $health_ax = round(70 + (57 * cos($health_t0)), 3);
    $health_ay = round(70 + (57 * sin($health_t0)), 3);
    $health_bx = round(70 + (57 * cos($health_t1)), 3);
    $health_by = round(70 + (57 * sin($health_t1)), 3);
    $health_ix = round(70 + (47 * cos($health_t1)), 3);
    $health_iy = round(70 + (47 * sin($health_t1)), 3);
    $health_jx = round(70 + (47 * cos($health_t0)), 3);
    $health_jy = round(70 + (47 * sin($health_t0)), 3);

    $health_clip = 'M' . $health_ax . ' ' . $health_ay
        . 'A57 57 0 0 1 ' . $health_bx . ' ' . $health_by
        . 'A5 5 0 0 1 ' . $health_ix . ' ' . $health_iy
        . 'A47 47 0 0 0 ' . $health_jx . ' ' . $health_jy
        . 'A5 5 0 0 1 ' . $health_ax . ' ' . $health_ay . 'Z';

    // ── The groove ─────────────────────────────────────
    //
    // The unfilled half only. It used to be the WHOLE half turn,
    // with the fill drawn over the top of it, and on a dark card
    // that is invisible -- black under a lit bar is nothing. On a
    // white one it is the theme's pale grey under every square,
    // and on white, opacity is also loss of SATURATION: a square
    // at the bottom of its breath was settling onto grey instead
    // of onto the page and giving up its colour, taking the
    // quieter half of the animation with it.
    //
    // A negative dash offset walks the pattern along the path, so
    // the groove starts where the fill stops. The round cap it
    // starts with reaches back under the fill's own cap, which
    // covers it.
    //
    // At a hundred there is nothing to groove, and a zero-length
    // dash with a round cap paints a dot -- so nothing is drawn.
    $health_dash   = round($health_arc * $health_score / 100, 2);
    $health_empty  = round($health_arc - $health_dash, 2);
    $health_groove = ($health_empty > 0.5)
        ? '<circle class="pg-health-track" cx="70" cy="70" r="52" stroke-width="10"'
            . ' stroke-dasharray="' . $health_empty . ' 326.73"'
            . ' stroke-dashoffset="-' . $health_dash . '"></circle>'
        : '';

    // The big score keeps its small % sign, on the side the
    // language's '{var:1}%' wording puts it (Turkish: %87).
    $health_score_html = (strpos(lang('{var:1}%'), '%') === 0)
        ? '<span>%</span>' . (int) $health_score
        : (int) $health_score . '<span>%</span>';

    return '
<div class="pg-health" style="' . $health_vars . '">
                    <div class="pg-health-dial">
                        <!--
                            Five SVGs stacked in one box, all on the same
                            viewBox, painted in this order: the groove with
                            the shared defs, the bloom, the glow, the static
                            halo, and on top the cells and the hairline. A
                            filter in the same SVG as animating content is
                            redrawn with it every frame, so the three blurs
                            live in layers of their own and only the top SVG,
                            which holds no filter, repaints as the cells
                            twinkle. url(#id) resolves across inline SVGs of
                            one document, so the layers above use the defs
                            written here.
                        -->
                        <svg class="pg-health-layer pg-health-layer-base" viewBox="8 8 124 72" aria-hidden="true" focusable="false">
                            <defs>
                                <!--
                                    userSpaceOnUse so the axis can sit
                                    on the drawn arc rather than on
                                    the circle, which is what keeps
                                    the whole sweep on the bar at
                                    every score. Every arc below --
                                    the wash, the two blurs, the
                                    hairline and the masked cells --
                                    is stroked with this one
                                    gradient, so all of them agree
                                    about what colour the bar is at
                                    any point along it.

                                    The stops arrive through custom
                                    properties instead of being
                                    written here: the same score
                                    needs different colours on a
                                    black card and a white one, and
                                    12P does not know which one it is
                                    rendering into. Both sets are
                                    declared on .pg-health and the
                                    stylesheet picks.
                                -->
                                <linearGradient id="pg_health_gradient" gradientUnits="userSpaceOnUse"
                                                x1="122" y1="70" x2="' . $health_x2 . '" y2="' . $health_y2 . '">
                                    <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                    <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                    <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                    <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                </linearGradient>
                                <!--
                                    The same gradient with its axis
                                    mirrored through the centre of
                                    the dial. The arcs are turned
                                    half a turn by the stylesheet and
                                    take their gradient round with
                                    them; the squares are written
                                    already turned, so theirs has to
                                    be turned here instead. Same
                                    colours, same stops, same
                                    direction on screen.
                                -->
                                <linearGradient id="pg_health_gradient_m" gradientUnits="userSpaceOnUse"
                                                x1="18" y1="70" x2="' . $health_mx2 . '" y2="' . $health_my2 . '">
                                    <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                    <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                    <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                    <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                </linearGradient>
                                <!--
                                    The glow is a blurred copy of the arc
                                    rather than a drop-shadow, because a
                                    drop-shadow takes one colour and the arc
                                    has four: blurring the stroke itself
                                    keeps the glow the colour of whatever it
                                    is under. .pg-health-layer is
                                    overflow:visible so the blur is not
                                    clipped at the viewBox edge.
                                -->
                                <filter id="pg_health_glow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur stdDeviation="4.5"/>
                                </filter>
                                <!--
                                    And a wider one under it. One blur
                                    gives an outline; two, at different
                                    radii, give the falloff that reads
                                    as light.
                                -->
                                <filter id="pg_health_bloom" x="-70%" y="-70%" width="240%" height="240%">
                                    <feGaussianBlur stdDeviation="9"/>
                                </filter>
                                <!--
                                    And a tight one for the squares
                                    themselves. The two above are the
                                    light AROUND the bar and are cut
                                    off by the panel; this is the
                                    light BETWEEN the squares, which
                                    is what makes a lit panel of them
                                    rather than a row of tiles with
                                    the card showing through the
                                    gaps. Small radius on purpose: at
                                    the radius the arc glows use, the
                                    squares merge and the matrix is
                                    gone.
                                -->
                                <filter id="pg_health_cell_glow" x="-40%" y="-40%" width="180%" height="180%">
                                    <feGaussianBlur stdDeviation="2.1"/>
                                </filter>
                            </defs>
                            ' . $health_groove . '
                        </svg>
                        <svg class="pg-health-layer pg-health-layer-bloom" viewBox="8 8 124 72" aria-hidden="true" focusable="false">
                            <circle class="pg-health-bloom" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                    filter="url(#pg_health_bloom)"
                                    stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                        </svg>
                        <svg class="pg-health-layer pg-health-layer-glow" viewBox="8 8 124 72" aria-hidden="true" focusable="false">
                            <circle class="pg-health-glow" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                    filter="url(#pg_health_glow)"
                                    stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                        </svg>
                        <svg class="pg-health-layer pg-health-layer-halo" viewBox="8 8 124 72" aria-hidden="true" focusable="false">
                            <!--
                                The halo, and it is NOT clipped: this is
                                the light the squares throw PAST the bar,
                                which is what makes them read as a field
                                showing through it rather than as tiles
                                sitting on it.

                                It does not breathe with them either: a
                                blurred copy of the animating squares would
                                re-run a blur over the whole bar every
                                frame. Held still, in its own SVG, it is
                                drawn once, and what it gives -- light in
                                the gaps -- is ambient anyway: it is the
                                panel being lit, not the pixel.
                            -->
                            <g class="pg-health-cell-glow" fill="url(#pg_health_gradient_m)"
                               filter="url(#pg_health_cell_glow)">' . $health_glow_cells . '</g>
                        </svg>
                        <svg class="pg-health-gauge" viewBox="8 8 124 72" role="img" aria-label="' . h(lang('Overall System Health')) . ' ' . h(lang(array('string' => '{var:1}%', 'vars' => (int) $health_score))) . '">
                            <defs>
                                <!--
                                    The bar itself, as a shape rather
                                    than as a stroke, so the grid of
                                    squares can be cut by it.
                                -->
                                <clipPath id="pg_health_cell_clip" clipPathUnits="userSpaceOnUse">
                                    <path d="' . $health_clip . '"/>
                                </clipPath>
                            </defs>
                            <g class="pg-health-cells" fill="url(#pg_health_gradient_m)"
                               clip-path="url(#pg_health_cell_clip)">' . $health_cells . '</g>
                            <!--
                                A second, hairline arc inside the thick one.
                                Its own radius means its own circumference,
                                so the fraction is recomputed rather than
                                reused: 2*pi*42 = 263.89, half of it 131.95.
                            -->
                            <circle class="pg-health-inner" cx="70" cy="70" r="42" stroke-width="2" stroke="url(#pg_health_gradient)"
                                    stroke-dasharray="' . round(131.95 * $health_score / 100, 2) . ' 263.89"></circle>
                        </svg>
                        <div class="pg-health-readout">
                            <div class="pg-health-score">' . $health_score_html . '</div>
                            <div class="pg-health-label">' . lang('Overall System Health') . '</div>
                        </div>
                    </div>
                </div>
                <script>(function(){
                  // An off-screen dial stops its twinkle and breathing. One
                  // observer for the page: a redraw of this widget disconnects
                  // the old one before watching the new markup. A hidden tab
                  // gets no animation frames anyway.
                  if (!("IntersectionObserver" in window)) { return; }
                  if (window.pgHealthObserver) { window.pgHealthObserver.disconnect(); }
                  window.pgHealthObserver = new IntersectionObserver(function(entries){
                    entries.forEach(function(entry){
                      entry.target.classList.toggle("is-paused", !entry.isIntersecting);
                    });
                  });
                  document.querySelectorAll(".pg-health-dial").forEach(function(dial){
                    window.pgHealthObserver.observe(dial);
                  });
                })();</script>';
}

/**
 * One row of a chip's or a job's detail panel. The dot is decoration; the
 * state word before the time is what tells a stalled task from a healthy
 * one, or a required missing rule from a recommended one. Info rows carry
 * no word: their time already says Never or Missing.
 *
 * @param array $detail_row label, state, when
 * @return string HTML
 */
function pg_system_status_detail_row($detail_row)
{
    $status_detail_words = array(
        'ok'   => lang('OK'),
        'warn' => lang('Warning'),
        'fail' => lang('Problem'),
    );

    $row_state = isset($detail_row['state']) ? (string) $detail_row['state'] : '';
    $row_tone = ($row_state === 'ok') ? 'success' : (($row_state === 'fail') ? 'danger' : 'secondary');
    $row_when = isset($detail_row['when']) ? (string) $detail_row['when'] : '';

    if (isset($status_detail_words[$row_state])) {
        $row_when = ($row_when === '')
            ? $status_detail_words[$row_state]
            : $status_detail_words[$row_state] . ' · ' . $row_when;
    }

    return '
                    <div class="pg-job-panel-row">
                        <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot fg-' . h($row_tone) . '" aria-hidden="true"></i>' . h($detail_row['label']) . '</span>
                        <span class="fg-secondary flex-shrink-0">' . h($row_when) . '</span>
                    </div>';
}

/**
 * The checks as chips, most urgent first: a chip with detail rows opens
 * them under itself, one with a link goes there, the rest are buttons that
 * show their explanation in a popover (welcome.php and the new dashboard
 * bind it by delegation).
 *
 * @param array $reading_checks checks from get_system_status_checks()
 * @param int   $role           the reader's role, for the links they may follow
 * @return string HTML
 */
function pg_system_status_chips($reading_checks, $role)
{
    // A chip each, not a tile each. Twelve tiles across half a
    // card put the label at nine pixels with the value at nine
    // more underneath, and at that size "Database" and
    // "Update status" were both an ellipsis -- a grid of boxes
    // whose labels had to be hovered to be read.
    //
    // The chip gets that width back by dropping the half that
    // was not information. A check that is fine says so with
    // its colour; printing "Tamam" nine times under nine green
    // labels is the colour said twice, in the space the label
    // needed. Only a check with something to report keeps its
    // value, which is also what makes those rows the ones the
    // eye lands on.
    //
    // Order is by state and then by weight: what is broken,
    // what is uncertain, then the three security checks that
    // lead when nothing is wrong, then the rest. usort() is
    // stable only from PHP 8.0, so position is carried into the
    // comparison -- without it the chips could reshuffle
    // between two draws of the same card.
    $check_ranks = array('fail' => 0, 'warn' => 1, 'info' => 3, 'ok' => 3);
    $check_order = array();

    foreach ($reading_checks as $check_index => $reading_check) {

        $check_rank = isset($check_ranks[$reading_check['state']])
            ? $check_ranks[$reading_check['state']]
            : 3;

        if (($check_rank === 3) && !empty($reading_check['priority'])) {
            $check_rank = 2;
        }

        $check_order[] = array($check_rank, (int) $check_index);
    }

    usort($check_order, function ($a, $b) {
        if ($a[0] === $b[0]) {
            return ($a[1] < $b[1]) ? -1 : 1;
        }
        return ($a[0] < $b[0]) ? -1 : 1;
    });

    // Bootstrap's own four, so a chip is the same red as every
    // other red on the screen.
    $check_tones = array('ok' => 'success', 'warn' => 'warning', 'fail' => 'danger', 'info' => 'secondary');

    $output_checks = '';
    $check_slot = 0;


    foreach ($check_order as $check_entry) {

        $health_check = $reading_checks[$check_entry[1]];
        $check_slot++;

        $check_tone = isset($check_tones[$health_check['state']])
            ? $check_tones[$health_check['state']]
            : 'secondary';

        $check_detail = (isset($health_check['detail']) && is_array($health_check['detail']))
            ? $health_check['detail']
            : array();

        // What the check actually reported ("3 eksik", "2026.4.4",
        // "4.2 MB") always shows. "OK", "Warning", "Problem" and
        // "Not applicable" are the words functions.php puts in a
        // check's mouth when it has none of its own: on a warn or
        // fail chip the word shows, since those are the chips worth
        // looking at and the tint alone does not tell a state to
        // someone who cannot see the colour; on a calm chip it is
        // read out but not drawn.
        if (empty($health_check['generic']) || in_array($health_check['state'], array('warn', 'fail'), true)) {
            $check_value = '<span class="pg-check-value">' . h($health_check['value']) . '</span>';
        } else {
            $check_value = '<span class="visually-hidden">' . h($health_check['value']) . '</span>';
        }

        $check_body = '
                <i class="bi ' . h($health_check['icon']) . '"></i>
                <span class="pg-check-name">' . h($health_check['label']) . '</span>' . $check_value;

        $check_panel = '';

        if ($check_detail) {

            // Rows the check brought with it. They exist nowhere
            // else, so the chip has to be able to show them
            // rather than point at a screen that does not have
            // the answer. The panel is a full-width child of the
            // same wrapping row, so it opens on the line under
            // its own chip instead of in a box at the foot of
            // the card that several chips would point at.
            //
            // Bootstrap's collapse data-api is delegated from
            // document, so it binds to markup this widget
            // injects after page load. Widget 2 is deliberately
            // absent from welcome.php's periodic refresh list,
            // so nothing re-renders the card and closes the
            // panel under the operator's hand.
            $check_id = 'system_status_detail_' . $check_slot;

            $check_rows = '';

            foreach ($check_detail as $detail_row) {
                $check_rows .= pg_system_status_detail_row($detail_row);
            }

            // The popover would fire on the same hover that
            // opens the panel, so a chip that expands carries
            // the collapse attributes instead and its
            // explanation is the panel.
            $output_checks .= '
                <button type="button" class="pg-check pg-check-' . $check_tone . '"
                        data-bs-toggle="collapse" data-bs-target="#' . $check_id . '"
                        aria-expanded="false" aria-controls="' . $check_id . '"
                        title="' . h($health_check['title']) . '">' . $check_body . '
                    <i class="bi bi-chevron-down pg-check-caret"></i>
                </button>
                <div class="collapse pg-job-slot" id="' . $check_id . '">
                    <div class="pg-job-panel">' . $check_rows . '</div>
                </div>';

        } else {

            // The popover is the only place the full
            // explanation fits, and welcome.php binds it by
            // delegation from document, so it survives the
            // markup being injected after page load. A chip
            // without a link is a button so the keyboard can
            // focus it, which is what opens the popover. The
            // chip has no popover toggle attribute: Bootstrap 6's
            // data API cancels every click on one, so a link chip
            // would never be followed. no-popover keeps the
            // generic title popover off the chip.
            //
            // The checks are cached for every reader, so the
            // Repair Software link is dropped here for whoever
            // that screen would refuse: anyone but an
            // administrator, and everyone on a hosted site.
            if (($health_check['href'] === 'software_repair.php')
                && !pg_software_repair_open_to($role, pg_hosted())) {
                $health_check['href'] = '';
            }

            $check_open = ($health_check['href'] !== '')
                ? '<a href="' . h($health_check['href']) . '"'
                : '<button type="button"';

            $output_checks .= $check_open . ' class="pg-check pg-check-' . $check_tone . ' status-popover no-popover"'
                . ' title="' . h($health_check['title']) . '"'
                . ' data-bs-content="' . h($health_check['message']) . '">' . $check_body
                . (($health_check['href'] !== '') ? '</a>' : '</button>');
        }
    }

    return $output_checks;
}
