<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Shifts tab of a volunteer programme (edit_volunteer_programme.php):
 * the week as one group a day, a shift a row with its time, place and one
 * pill a role ("3 of 5": teal full, amber short, rose short and near); the
 * Add shift drawer (place, day, start and end, the places of each role, a
 * note, Repeat weekly); and a shift's drawer (signed up per role, the
 * waitlist, Call volunteers, Remove). Loaded by that screen only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A role's places as a pill in its colour.
 *
 * @param string $name
 * @param int    $filled
 * @param int    $count
 * @param string $tone pg_volunteer_role_tone()
 * @return string HTML
 */
function pg_volunteer_role_pill($name, $filled, $count, $tone)
{
    $tones = array('full' => 'pg-tone-teal', 'short' => 'pg-tone-amber', 'near' => 'pg-tone-rose');

    return '<span class="pg-pill ' . $tones[$tone] . '">' . h(lang(array('string' => '{var:1} {var:2} of {var:3}', 'vars' => array($name, (int) $filled, (int) $count)))) . '</span>';
}

/**
 * A role's name, or a stand-in for a role the programme no longer has.
 *
 * @param array  $programme
 * @param string $key
 * @return string
 */
function pg_volunteer_role_name(array $programme, $key)
{
    return (string) ($programme['roles'][$key]['name'] ?? lang('Removed role'));
}

/**
 * The board: one group a day of the week, each shift a row.
 *
 * @param array  $programme
 * @param array  $shifts  pg_volunteer_board_shifts()
 * @param string $monday
 * @param string $place   the place filter, '' for every place
 * @param string $now
 * @return string HTML
 */
function pg_volunteer_board_html(array $programme, array $shifts, $monday, $place, $now)
{
    $base = 'edit_volunteer_programme.php?id=' . $programme['id'] . '&amp;view=shifts&amp;week=' . $monday . (($place !== '') ? '&amp;place=' . rawurlencode($place) : '');

    if (!$shifts) {
        return '<section class="pg-card p-5 mb-5"><h2 class="pg-title-section mb-0">' . h(lang('No shifts this week')) . '</h2>'
            . '<p class="pg-card-sub mb-0">' . h($programme['roles'] ? lang('Add a shift with the places each role needs, or copy last week\'s shifts.') : lang('Add the programme\'s roles in Set-up first: a shift asks for places in them.')) . '</p></section>';
    }

    $html = '';

    foreach (pg_volunteer_board_days(array_values($shifts), $monday, $place) as $date => $day_shifts) {
        $title = get_absolute_time(array('timestamp' => strtotime($date), 'type' => 'date', 'format' => 'plain_text'));
        $rows = '';

        foreach ($day_shifts as $shift) {
            $pills = '';

            foreach ($shift['roles'] as $key => $count) {
                $filled = (int) ($shift['filled'][$key] ?? 0);
                $pills .= pg_volunteer_role_pill(pg_volunteer_role_name($programme, $key), $filled, $count, pg_volunteer_role_tone($filled, $count, $shift, $now, $programme['call_days']));
            }

            $waiting = array_sum($shift['waiting']);
            $rows .= '<div class="pg-card-inner-row flex-wrap">'
                . '<div class="flex-grow-1 min-width-0"><a class="pg-list-row-title d-block" href="' . $base . '&amp;shift=' . $shift['id'] . '"><span class="pg-tabular">' . h(date('H:i', strtotime($shift['start'])) . '–' . date('H:i', strtotime($shift['end']))) . '</span> · ' . h($shift['place']) . '</a>'
                . (($shift['note'] !== '' || $waiting > 0 || $shift['series_id'] > 0) ? '<div class="pg-text-meta">' . h(implode(' · ', array_filter(array(($shift['series_id'] > 0) ? lang('Weekly') : '', ($waiting > 0) ? lang(array('string' => '{var:1} waiting', 'vars' => array($waiting))) : '', $shift['note'])))) . '</div>' : '')
                . '</div><span class="d-flex flex-wrap gap-2">' . $pills . '</span></div>';
        }

        $html .= '<section class="pg-card p-5 mb-5" aria-label="' . h($title) . '"><h2 class="pg-title-section ' . (($rows !== '') ? 'mb-3' : 'mb-0') . '">' . h($title) . ' <span class="pg-seg-count">' . count($day_shifts) . '</span></h2>'
            . (($rows !== '') ? '<div class="pg-card-inner">' . $rows . '</div>' : '<p class="pg-text-meta mb-0">' . h(lang('No shifts.')) . '</p>')
            . '</section>';
    }

    return $html;
}

/**
 * The toolbar's week line: the week's days with arrows to the week before
 * and after.
 *
 * @param array  $programme
 * @param string $monday
 * @param string $place
 * @return string HTML
 */
function pg_volunteer_board_week_html(array $programme, $monday, $place)
{
    $link = function ($week, $icon, $label) use ($programme, $place) {
        return '<a class="pg-icon-button" href="edit_volunteer_programme.php?id=' . $programme['id'] . '&amp;view=shifts&amp;week=' . $week . (($place !== '') ? '&amp;place=' . rawurlencode($place) : '') . '" aria-label="' . h($label) . '"><span class="bi ' . $icon . '" aria-hidden="true"></span></a>';
    };
    $format = function ($date) {
        return get_absolute_time(array('timestamp' => strtotime($date), 'type' => 'date', 'format' => 'plain_text'));
    };

    return '<span class="d-inline-flex align-items-center gap-2">' . $link(pg_volunteer_add_days($monday, -7), 'bi-chevron-left', lang('Week before'))
        . '<span class="pg-text-2 pg-tabular text-nowrap">' . h($format($monday) . ' – ' . $format(pg_volunteer_add_days($monday, 6))) . '</span>'
        . $link(pg_volunteer_add_days($monday, 7), 'bi-chevron-right', lang('Week after')) . '</span>';
}

/**
 * The place filter as a segmented control; '' with fewer than two places.
 *
 * @param array  $programme
 * @param array  $places
 * @param string $monday
 * @param string $place
 * @return string HTML
 */
function pg_volunteer_board_places_html(array $programme, array $places, $monday, $place)
{
    if (count($places) < 2) {
        return '';
    }

    $html = '';

    foreach (array_merge(array(''), $places) as $option) {
        $html .= '<a class="pg-seg-item' . (($option === $place) ? ' is-active" aria-current="page' : '') . '" href="edit_volunteer_programme.php?id=' . $programme['id'] . '&amp;view=shifts&amp;week=' . $monday . (($option !== '') ? '&amp;place=' . rawurlencode($option) : '') . '">' . h(($option === '') ? lang('All places') : $option) . '</a>';
    }

    return '<span class="pg-seg" role="group" aria-label="' . h(lang('Place')) . '">' . $html . '</span>';
}

/**
 * The Add shift drawer.
 *
 * @param array  $programme
 * @param string $monday the week shown
 * @param array  $post   the posted fields after a refused add, else empty
 * @param bool   $open   whether it opens with the screen
 * @return string HTML
 */
function pg_volunteer_shift_add_html(array $programme, $monday, array $post, $open)
{
    $value = function ($name, $default = '') use ($post) {
        return h(is_scalar($post[$name] ?? null) ? (string) $post[$name] : $default);
    };
    $roles = '';

    foreach ($programme['roles'] as $key => $role) {
        $roles .= '<div class="pg-card-inner-row"><label class="flex-grow-1 min-width-0 pg-text-2" for="vs_count_' . h($key) . '">' . h($role['name']) . '</label>'
            . '<span class="d-inline-block" style="width:6rem"><input type="number" class="pg-input" id="vs_count_' . h($key) . '" name="count[' . h($key) . ']" min="0" max="' . PG_VOLUNTEER_ROLE_MAX . '" step="1" value="' . (int) ($post['count'][$key] ?? 0) . '"></span></div>';
    }

    $field = function ($id, $label, $input) {
        return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="' . $id . '">' . h($label) . '</label>' . $input . '</div>';
    };
    $time = function ($name, $label) use ($value) {
        return '<div class="col-6"><label class="form-label pg-field-label" for="vs_' . $name . '">' . h($label) . '</label><input type="time" data-pg-time="hh:mm" class="pg-input" id="vs_' . $name . '" name="' . $name . '" value="' . $value($name) . '" required></div>';
    };

    return '
    <dialog class="drawer drawer-end" id="vs_add" aria-labelledby="vs_add_title" style="width:min(480px,100%);--pg-drawer-height:85vh"' . ($open ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_volunteer_programme.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $programme['id'] . '"><input type="hidden" name="view" value="shifts"><input type="hidden" name="week" value="' . $monday . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="vs_add_title">' . h(lang('Add shift')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('Volunteers whose skills and documents fit a role can sign up for it.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                ' . $field('vs_place', lang('Place'), '<input type="text" class="pg-input" id="vs_place" name="place" maxlength="100" value="' . $value('place') . '" required>') . '
                ' . $field('vs_date', lang('Day'), '<input type="date" data-pg-date class="pg-input" id="vs_date" name="date" value="' . $value('date', $monday) . '" required>') . '
                <div class="row g-3 mb-5">' . $time('start_at', lang('Start')) . $time('end_at', lang('End')) . '<div class="col-12 pg-field-help">' . h(lang('An end before the start is the next day.')) . '</div></div>
                <h6 class="pg-field-label">' . h(lang('Places per role')) . '</h6>
                <div class="pg-card-inner mb-5">' . $roles . '</div>
                ' . $field('vs_note', lang('Note'), '<input type="text" class="pg-input" id="vs_note" name="note" maxlength="500" value="' . $value('note') . '">') . '
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="d-flex align-items-center gap-2"><input type="checkbox" class="pg-checkbox" id="vs_repeat" name="repeat" value="1"' . (!empty($post['repeat']) ? ' checked' : '') . '><label class="pg-text-2" for="vs_repeat">' . h(lang('Repeat weekly')) . '</label></span>
                    <span class="d-inline-flex align-items-center gap-2"><label class="pg-text-meta" for="vs_weeks">' . h(lang('Weeks')) . '</label><span class="d-inline-block" style="width:6rem"><input type="number" class="pg-input" id="vs_weeks" name="weeks" min="2" max="' . PG_VOLUNTEER_SERIES_WEEKS . '" step="1" value="' . $value('weeks', '4') . '"></span></span>
                </div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_shift_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add shift')) . '</button>
            </div>
        </form>
    </dialog>';
}

/**
 * A shift's drawer: who signed up per role, the waitlist, Call volunteers
 * with how many it asks, Remove with how many are told.
 *
 * @param array  $programme
 * @param array  $shift    pg_volunteer_shift_get()
 * @param int    $to_call  pg_volunteer_shift_call_ids() counted
 * @param string $back     the board's query string
 * @param string $now
 * @return string HTML
 */
function pg_volunteer_shift_drawer_html(array $programme, array $shift, $to_call, $back, $now)
{
    $roles = '';

    foreach ($shift['roles'] as $key => $count) {
        $filled = (int) ($shift['filled'][$key] ?? 0);
        $lines = '';

        foreach ($shift['signups'] as $signup) {
            if (($signup['role'] === (string) $key) && in_array($signup['state'], array('signed', 'waiting'), true)) {
                $lines .= '<div class="pg-card-inner-row"><span class="pg-text-2 flex-grow-1 min-width-0">' . h($signup['name']) . '</span>' . (($signup['state'] === 'waiting') ? '<span class="pg-pill pg-tone-gray">' . h(lang('Waitlist')) . '</span>' : '') . '</div>';
            }
        }

        $roles .= '<h6 class="pg-field-label d-flex align-items-center gap-2">' . h(pg_volunteer_role_name($programme, $key)) . ' ' . pg_volunteer_role_pill(lang('Places'), $filled, $count, pg_volunteer_role_tone($filled, $count, $shift, $now, $programme['call_days'])) . '</h6>'
            . (($lines !== '') ? '<div class="pg-card-inner mb-5">' . $lines . '</div>' : '<p class="pg-text-meta mb-5">' . h(lang('Nobody signed up yet.')) . '</p>');
    }

    $told = count(pg_volunteer_shift_people_told($shift['signups']));
    $kept = false;

    foreach ($shift['signups'] as $signup) {
        $kept = $kept || ($signup['check_in'] !== null) || ((int) $signup['approved_at'] > 0);
    }

    $call_hint = ($to_call > 0)
        ? lang(array('string' => 'Asks {var:1} volunteers who fit by e-mail.', 'vars' => array($to_call)))
        : lang('Nobody to ask: every role is full, the shift is not within the call days, or everyone who fits was asked.');

    return '
    <dialog class="drawer drawer-end" id="vs_shift" aria-labelledby="vs_shift_title" style="width:min(520px,100%);--pg-drawer-height:85vh" data-open="1">
        <form method="post" action="edit_volunteer_programme.php?' . $back . '" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="shift" value="' . $shift['id'] . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="vs_shift_title">' . h(pg_volunteer_shift_when($shift)) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(implode(' · ', array_filter(array(($shift['series_id'] > 0) ? lang('Weekly') : '', $shift['note'])))) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                ' . $roles . '
                <p class="pg-text-meta mb-0">' . h($call_hint) . '</p>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3 flex-wrap"><span class="flex-grow-1"></span>
                <button type="submit" name="submit_shift_remove" value="1" class="pg-button pg-button-danger"' . ($kept ? ' disabled title="' . h(lang('Hours were recorded on this shift: it stays.')) . '"' : '') . ' data-confirm-content="' . h(($told > 0) ? lang(array('string' => 'The shift is removed. {var:1} volunteers who signed up or wait are told by e-mail.', 'vars' => array($told))) : lang('The shift is removed. Nobody signed up for it.')) . '">' . h(lang('Remove')) . '</button>
                <button type="submit" name="submit_shift_call" value="1" class="pg-button pg-button-quiet"' . (($to_call > 0) ? '' : ' disabled') . '><i class="bi bi-megaphone" aria-hidden="true"></i>' . h(lang('Call volunteers')) . '</button>
            </div>
        </form>
    </dialog>';
}
