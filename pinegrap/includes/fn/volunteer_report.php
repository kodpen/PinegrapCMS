<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer programme's report (the Report tab of
 * edit_volunteer_programme.php): places filled of needed, "Did not come"
 * and late cancels by week, approved hours by week, role and volunteer,
 * active volunteers and those who left in the period; the same figures as
 * an Excel workbook (pg_xlsx_write_book()). A document never appears in it.
 * The figures are summed by the pure pg_volunteer_report_build().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_shifts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_certificate.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/xlsx_write.php');

/** The report's periods, in weeks up to and including this one. */
const PG_VOLUNTEER_REPORT_WEEKS = array(4, 12, 52);

/**
 * The report's period: whole weeks, Monday to Sunday, ending with this
 * week; an unknown length is the first. Pure.
 *
 * @param mixed  $weeks
 * @param string $today 'YYYY-MM-DD'
 * @return array weeks, from, to ('YYYY-MM-DD', both included)
 */
function pg_volunteer_report_period($weeks, $today)
{
    $weeks = (int) (is_scalar($weeks) ? $weeks : 0);
    $weeks = in_array($weeks, PG_VOLUNTEER_REPORT_WEEKS, true) ? $weeks : PG_VOLUNTEER_REPORT_WEEKS[0];
    $monday = pg_volunteer_week_monday((string) $today);

    return array('weeks' => $weeks, 'from' => pg_volunteer_add_days($monday, -7 * ($weeks - 1)), 'to' => pg_volunteer_add_days($monday, 6));
}

/**
 * Whether a cancel was late: at or after the programme's cut-off before
 * the shift's start (pg_volunteer_can_cancel() no longer allowed it). Pure.
 *
 * @param string   $cancelled    the cancel's wall-clock time, '' for none
 * @param string   $start        the shift's start
 * @param int|null $cutoff_hours
 * @return bool
 */
function pg_volunteer_late_cancel($cancelled, $start, $cutoff_hours)
{
    return ((string) $cancelled !== '') && (pg_volunteer_minutes($cancelled) !== null) && !pg_volunteer_can_cancel($start, $cancelled, $cutoff_hours);
}

/**
 * Sums the report's figures. Pure.
 *
 * A shift counts in the week of its start: its roles' places as needed,
 * signed places up to each role's count as filled, "Did not come" and late
 * cancels. Approved minutes count in the week of their day (the shift's
 * start day, or the day of hours outside a shift), by role ('' outside a
 * shift) and by volunteer.
 *
 * @param array    $shifts       id => start, roles (key => count)
 * @param array    $signups      rows: shift_id (null outside a shift),
 *                               volunteer_id, name, role, state, outcome,
 *                               cancelled (wall-clock, '' for none), day,
 *                               minutes, approved
 * @param int|null $cutoff_hours the programme's cut-off
 * @param string   $from         the period's first day
 * @param string   $to           its last day
 * @return array weeks (Monday => figures), roles (key => minutes),
 *               volunteers (id => name, minutes, came, no_show, late),
 *               total (needed, filled, no_show, late, minutes)
 */
function pg_volunteer_report_build(array $shifts, array $signups, $cutoff_hours, $from, $to)
{
    $blank = array('needed' => 0, 'filled' => 0, 'no_show' => 0, 'late' => 0, 'minutes' => 0);
    $weeks = array();

    for ($monday = pg_volunteer_week_monday((string) $from); ($monday !== null) && ($monday <= $to); $monday = pg_volunteer_add_days($monday, 7)) {
        $weeks[$monday] = $blank;
    }

    $total = $blank;
    $roles = array();
    $people = array();
    $signed = array();
    $person = function ($row) use (&$people) {
        $id = (int) $row['volunteer_id'];

        if (!isset($people[$id])) {
            $people[$id] = array('name' => (string) ($row['name'] ?? ''), 'minutes' => 0, 'came' => 0, 'no_show' => 0, 'late' => 0);
        }

        return $id;
    };

    foreach ($signups as $row) {
        $shift = ($row['shift_id'] !== null) ? ($shifts[(int) $row['shift_id']] ?? null) : null;

        if (($row['shift_id'] !== null) && ($shift === null)) {
            continue;
        }

        $week = pg_volunteer_week_monday(($shift !== null) ? substr((string) $shift['start'], 0, 10) : (string) ($row['day'] ?? ''));

        if (($week === null) || !isset($weeks[$week])) {
            continue;
        }

        $id = $person($row);

        if ($shift !== null) {
            if ($row['state'] === 'signed') {
                $signed[(int) $row['shift_id']][(string) $row['role']] = ($signed[(int) $row['shift_id']][(string) $row['role']] ?? 0) + 1;

                if ($row['outcome'] === 'no_show') {
                    $weeks[$week]['no_show']++;
                    $people[$id]['no_show']++;
                } elseif ($row['outcome'] === 'came') {
                    $people[$id]['came']++;
                }
            } elseif (($row['state'] === 'cancelled') && pg_volunteer_late_cancel((string) ($row['cancelled'] ?? ''), $shift['start'], $cutoff_hours)) {
                $weeks[$week]['late']++;
                $people[$id]['late']++;
            }
        }

        if (!empty($row['approved']) && ($row['state'] === 'signed')) {
            $minutes = max(0, (int) $row['minutes']);
            $weeks[$week]['minutes'] += $minutes;
            $people[$id]['minutes'] += $minutes;
            $role = ($shift !== null) ? (string) $row['role'] : '';
            $roles[$role] = ($roles[$role] ?? 0) + $minutes;
        }
    }

    foreach ($shifts as $shift_id => $shift) {
        $week = pg_volunteer_week_monday(substr((string) $shift['start'], 0, 10));

        if (($week === null) || !isset($weeks[$week])) {
            continue;
        }

        foreach ((array) $shift['roles'] as $role => $count) {
            $weeks[$week]['needed'] += max(0, (int) $count);
            $weeks[$week]['filled'] += min(max(0, (int) $count), $signed[(int) $shift_id][(string) $role] ?? 0);
        }
    }

    foreach ($weeks as $figures) {
        foreach ($figures as $key => $value) {
            $total[$key] += $value;
        }
    }

    uasort($people, function ($a, $b) {
        return ($b['minutes'] <=> $a['minutes']) ?: strcmp($a['name'], $b['name']);
    });
    arsort($roles);

    return array('weeks' => $weeks, 'roles' => $roles, 'volunteers' => $people, 'total' => $total);
}

/**
 * Minutes as decimal hours for a table or a cell. Pure.
 *
 * @param int $minutes
 * @return float
 */
function pg_volunteer_report_hours($minutes)
{
    return round(max(0, (int) $minutes) / 60, 2);
}

/**
 * The report's workbook sheets: Summary, Weeks, Roles, Volunteers, each a
 * head and its rows as pg_xlsx_write_book() typed cells (a number a
 * numeric cell, text never a formula). Pure.
 *
 * @param array $report pg_volunteer_report_build()
 * @param array $people active, left
 * @param array $period from, to
 * @param array $names  role key => name ('' is outside a shift)
 * @return array list of name, rows
 */
function pg_volunteer_report_sheets(array $report, array $people, array $period, array $names)
{
    $typed = function (array $rows) {
        $sheet = array();

        foreach (array_values($rows) as $index => $row) {
            $cells = array();

            foreach (array_values($row) as $value) {
                $cells[] = (is_int($value) || is_float($value)) ? array('t' => 'n', 'v' => (string) $value) : ((((string) $value) === '') ? null : array('t' => 's', 'v' => (string) $value));
            }

            $sheet[$index + 1] = $cells;
        }

        return $sheet;
    };
    $t = $report['total'];
    $weeks = array(array(lang('Week'), lang('Places needed'), lang('Places filled'), lang('Did not come'), lang('Late cancels'), lang('Approved hours')));

    foreach ($report['weeks'] as $monday => $w) {
        $weeks[] = array($monday, $w['needed'], $w['filled'], $w['no_show'], $w['late'], pg_volunteer_report_hours($w['minutes']));
    }

    $roles = array(array(lang('Role'), lang('Approved hours')));

    foreach ($report['roles'] as $key => $minutes) {
        $roles[] = array(($key === '') ? lang('Outside a shift') : ($names[$key] ?? (string) $key), pg_volunteer_report_hours($minutes));
    }

    $volunteers = array(array(lang('Volunteer'), lang('Approved hours'), lang('Shifts came'), lang('Did not come'), lang('Late cancels')));

    foreach ($report['volunteers'] as $v) {
        $volunteers[] = array($v['name'], pg_volunteer_report_hours($v['minutes']), $v['came'], $v['no_show'], $v['late']);
    }

    return array(
        array('name' => lang('Summary'), 'rows' => $typed(array(
            array('', lang('Total')),
            array(lang('Period'), $period['from'] . ' – ' . $period['to']),
            array(lang('Places needed'), $t['needed']),
            array(lang('Places filled'), $t['filled']),
            array(lang('Did not come'), $t['no_show']),
            array(lang('Late cancels'), $t['late']),
            array(lang('Approved hours'), pg_volunteer_report_hours($t['minutes'])),
            array(lang('Active volunteers'), (int) $people['active']),
            array(lang('Volunteers who left'), (int) $people['left']),
        ))),
        array('name' => lang('Weeks'), 'rows' => $typed($weeks)),
        array('name' => lang('Roles'), 'rows' => $typed($roles)),
        array('name' => lang('Volunteers'), 'rows' => $typed($volunteers)),
    );
}

/**
 * Reads a programme's shifts and sign-ups of a period and sums them.
 *
 * @param array $programme
 * @param array $period pg_volunteer_report_period()
 * @return array pg_volunteer_report_build() with 'people' (active, left)
 */
function pg_volunteer_report_load(array $programme, array $period)
{
    $id = (int) $programme['id'];
    $end = pg_volunteer_add_days($period['to'], 1);
    $shifts = array();

    foreach ((array) db_items("SELECT id, start_time, roles FROM volunteer_shifts WHERE programme_id = '" . $id . "' AND start_time >= '" . escape($period['from']) . "' AND start_time < '" . escape($end) . "'") as $row) {
        $roles = json_decode((string) $row['roles'], true);
        $shifts[(int) $row['id']] = array('start' => (string) $row['start_time'], 'roles' => is_array($roles) ? array_map('intval', $roles) : array());
    }

    $signups = array();

    foreach ((array) db_items("SELECT volunteer_signups.shift_id, volunteer_signups.volunteer_id, volunteer_signups.role, volunteer_signups.state, volunteer_signups.outcome, volunteer_signups.cancelled_at,
            volunteer_signups.worked_on, volunteer_signups.minutes, volunteer_signups.approved_at, contacts.first_name, contacts.last_name, contacts.email_address
        FROM volunteer_signups INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.programme_id = '" . $id . "' AND ((volunteer_signups.shift_id IS NULL AND volunteer_signups.worked_on >= '" . escape($period['from']) . "' AND volunteer_signups.worked_on <= '" . escape($period['to']) . "')
            OR (volunteer_shifts.start_time >= '" . escape($period['from']) . "' AND volunteer_shifts.start_time < '" . escape($end) . "'))") as $row) {
        $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
        $signups[] = array(
            'shift_id'     => ($row['shift_id'] !== null) ? (int) $row['shift_id'] : null,
            'volunteer_id' => (int) $row['volunteer_id'],
            'name'         => ($name !== '') ? $name : (string) $row['email_address'],
            'role'         => (string) $row['role'],
            'state'        => (string) $row['state'],
            'outcome'      => (string) $row['outcome'],
            // The cancel's stamp read as the site's wall clock, as a
            // shift's start is written.
            'cancelled'    => ((int) $row['cancelled_at'] > 0) ? date('Y-m-d H:i:s', (int) $row['cancelled_at']) : '',
            'day'          => (string) $row['worked_on'],
            'minutes'      => (int) $row['minutes'],
            'approved'     => (int) $row['approved_at'] > 0,
        );
    }

    $report = pg_volunteer_report_build($shifts, $signups, $programme['cutoff_hours'] ?? null, $period['from'], $period['to']);
    $report['people'] = array(
        'active' => (int) db_value("SELECT COUNT(*) FROM volunteers WHERE programme_id = '" . $id . "' AND state = 'active'"),
        'left'   => (int) db_value("SELECT COUNT(*) FROM volunteers WHERE programme_id = '" . $id . "' AND state = 'left' AND left_at >= '" . (int) strtotime($period['from'] . ' 00:00:00') . "' AND left_at < '" . (int) strtotime($end . ' 00:00:00') . "'"),
    );

    return $report;
}

/**
 * The role names of a programme for the report, '' standing for hours
 * outside a shift.
 *
 * @param array $programme
 * @return array key => name
 */
function pg_volunteer_report_role_names(array $programme)
{
    $names = array('' => lang('Outside a shift'));

    foreach ((array) ($programme['roles'] ?? array()) as $key => $role) {
        $names[(string) $key] = (string) ($role['name'] ?? $key);
    }

    return $names;
}

/**
 * Writes the report's workbook to a temporary file.
 *
 * @param array $report pg_volunteer_report_load()
 * @param array $period
 * @param array $names
 * @return string|false the file's path
 */
function pg_volunteer_report_xlsx(array $report, array $period, array $names)
{
    $sheets = pg_volunteer_report_sheets($report, $report['people'], $period, $names);
    $path = tempnam(sys_get_temp_dir(), 'pgv');

    if (($path === false) || !pg_xlsx_write_book(array_map(function ($sheet) {
        return array('name' => $sheet['name']);
    }, $sheets), function ($index) use ($sheets) {
        return $sheets[$index]['rows'];
    }, $path)) {
        if ($path !== false) {
            @unlink($path);
        }

        return false;
    }

    return $path;
}

/**
 * The Report tab: the period's segments and Excel, the figures, then the
 * weeks, the roles and the volunteers as report tables.
 *
 * @param array  $programme
 * @param array  $report pg_volunteer_report_load()
 * @param array  $period
 * @param string $link   the tab's address without the period
 * @return string HTML
 */
function pg_volunteer_report_html(array $programme, array $report, array $period, $link)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/report_view.php');
    $names = pg_volunteer_report_role_names($programme);
    $t = $report['total'];
    $hours = function ($minutes) {
        return h(pg_format_number(pg_volunteer_report_hours($minutes), 1));
    };
    $segments = '';

    foreach (PG_VOLUNTEER_REPORT_WEEKS as $weeks) {
        $segments .= '<a class="pg-seg-item' . (($weeks === $period['weeks']) ? ' is-active" aria-current="page' : '') . '" href="' . h($link . '&weeks=' . $weeks) . '">' . h(lang(array('string' => 'Last {var:1} weeks', 'vars' => array($weeks)))) . '</a>';
    }

    $frame = function ($id, $title, $note, array $head, $rows, $empty) {
        $cells = '';

        foreach ($head as $index => $label) {
            $cells .= '<th' . ($index ? ' class="text-end"' : '') . '>' . h($label) . '</th>';
        }

        return '<section class="pg-card pg-report-frame" aria-labelledby="' . $id . '">'
            . '<div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="' . $id . '">' . h($title) . '</h3><span class="pg-report-frame-note">' . h($note) . '</span></div>'
            . '<div class="pg-report-frame-body"><div class="table-responsive"><table class="pg-report-table"><thead><tr>' . $cells . '</tr></thead><tbody>'
            . (($rows !== '') ? $rows : '<tr><td colspan="' . count($head) . '" class="fg-secondary">' . h($empty) . '</td></tr>')
            . '</tbody></table></div></div></section>';
    };
    $range = $period['from'] . ' – ' . $period['to'];
    $week_rows = '';

    foreach (array_reverse($report['weeks'], true) as $monday => $w) {
        $week_rows .= '<tr><th scope="row">' . h($monday) . '</th><td class="text-end">' . (int) $w['filled'] . ' / ' . (int) $w['needed'] . '</td>'
            . '<td class="text-end">' . (int) $w['no_show'] . '</td><td class="text-end">' . (int) $w['late'] . '</td><td class="text-end">' . $hours($w['minutes']) . '</td></tr>';
    }

    $role_rows = '';

    foreach ($report['roles'] as $key => $minutes) {
        $role_rows .= '<tr><th scope="row">' . h($names[$key] ?? $key) . '</th><td class="text-end">' . $hours($minutes) . '</td></tr>';
    }

    $people_rows = '';

    foreach ($report['volunteers'] as $v) {
        $people_rows .= '<tr><th scope="row">' . h($v['name']) . '</th><td class="text-end">' . $hours($v['minutes']) . '</td><td class="text-end">' . (int) $v['came'] . '</td>'
            . '<td class="text-end">' . (int) $v['no_show'] . '</td><td class="text-end">' . (int) $v['late'] . '</td></tr>';
    }

    return '<div class="pg-report-tools">'
        . '<div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">' . $segments . '</div>'
        . '<span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h($range) . '</span>'
        . '<a class="pg-button pg-button-quiet ms-auto" href="' . h($link . '&weeks=' . $period['weeks'] . '&export=xlsx') . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>'
        . '</div>'
        . '<div class="mb-3">' . pg_report_figures_html(array(
            pg_report_view_figure(lang('Places filled'), $t['filled'] . ' / ' . $t['needed'], 'bi-people', 'teal', ($t['needed'] > 0) ? lang(array('string' => '{var:1}% of the places needed', 'vars' => array((int) round(100 * $t['filled'] / $t['needed'])))) : ''),
            pg_report_view_figure(lang('Approved hours'), pg_format_number(pg_volunteer_report_hours($t['minutes']), 1), 'bi-clock-history', 'blue'),
            pg_report_view_figure(lang('Did not come'), (string) $t['no_show'], 'bi-person-x', 'rose', lang(array('string' => '{var:1} late cancels', 'vars' => array($t['late'])))),
            pg_report_view_figure(lang('Active volunteers'), (string) $report['people']['active'], 'bi-person-heart', 'violet', lang(array('string' => '{var:1} left in the period', 'vars' => array($report['people']['left'])))),
        )) . '</div>'
        . $frame('vr_weeks', lang('By week'), $range, array(lang('Week'), lang('Places filled of needed'), lang('Did not come'), lang('Late cancels'), lang('Approved hours')), $week_rows, '')
        . $frame('vr_roles', lang('Hours by role'), $range, array(lang('Role'), lang('Approved hours')), $role_rows, lang('No hours were approved in this period.'))
        . $frame('vr_people', lang('By volunteer'), $range, array(lang('Volunteer'), lang('Approved hours'), lang('Shifts came'), lang('Did not come'), lang('Late cancels')), $people_rows, lang('No volunteer had a shift or hours in this period.'))
        . '<p class="fg-secondary small">' . h(lang('A shift counts in the week it starts; hours count once approved. A late cancel came after the programme\'s cut-off.')) . '</p>';
}
