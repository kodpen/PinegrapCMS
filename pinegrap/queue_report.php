<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's Report tab: the tools row (the last 7, 30 or 90
 * days, the wait target, Excel), the figures (numbers taken, the median
 * wait and handling time with their slowest tenth, people who left and who
 * were not here), a table by desk, staff member or service, arrivals by
 * weekday and hour, and the staffing line: the desks that would have kept
 * the wait under the target in each hour, beside the desks that called.
 * Roles 0-2. Helpers: includes/fn/walk_in_queue_report.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_report.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_waiting_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$qr_id = (int) ($_GET['place'] ?? 0);
$qr_place = ($qr_id > 0) ? pg_waiting_place_get($qr_id) : null;

if ($qr_place === null) {
    output_error(lang('The place was not found.'), 404);
}

$qr_days = pg_waiting_report_period($_GET['days'] ?? '');
$qr_target = pg_waiting_report_target($_GET['target'] ?? '');
$qr_groupings = array('desk' => lang('Desk'), 'staff' => lang('Staff member'), 'service' => lang('Service'));
$qr_group = (is_string($_GET['group'] ?? null) && isset($qr_groupings[$_GET['group']])) ? $_GET['group'] : 'desk';
$qr_range = pg_waiting_report_range($qr_days, time());
$qr_grace = (int) $qr_place['settings']['grace_minutes'] * 60;
$qr_acc = pg_waiting_report_read($qr_id, $qr_range['from'], $qr_range['to'], $qr_grace);
$qr_total = pg_waiting_report_figures($qr_acc['total']);
$qr_handling = pg_waiting_report_handling($qr_acc, $qr_place['services']);
$qr_staffing = pg_waiting_report_staffing($qr_acc, $qr_range['weekdays'], $qr_handling, $qr_target);
$qr_hours = pg_waiting_report_hours($qr_acc);
$qr_weekdays = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'), 5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));

// The names of each grouping's keys: desks of today and removed ones by id,
// staff by user, services by key with their letter.
$qr_names = array('desk' => array(), 'staff' => pg_waiting_report_staff_names(array_keys($qr_acc['staff'])), 'service' => array());

foreach (pg_waiting_desks($qr_id) as $desk) {
    $qr_names['desk'][$desk['id']] = $desk['name'];
}

foreach ($qr_place['services'] as $service) {
    $qr_names['service'][$service['key']] = $service['letter'] . ' · ' . $service['name'];
}

$qr_lines = array();

foreach ($qr_groupings as $key => $label) {
    $qr_lines[$key] = pg_waiting_report_lines($qr_acc[$key], $qr_names[$key]);
}

$qr_period = date('Y-m-d', strtotime((string) $qr_range['from'])) . ' – ' . date('Y-m-d', strtotime((string) $qr_range['to']));
$qr_head = array(lang('Numbers taken'), lang('Numbers served'), lang('Left the queue'), lang('Not here'), lang('Median wait'), lang('Slowest tenth wait'), lang('Median handling'), lang('Slowest tenth handling'));
$qr_arrivals_grid = pg_waiting_report_grid($qr_hours, $qr_weekdays, function ($weekday, $hour) use ($qr_acc) {
    return (int) ($qr_acc['arrive'][$weekday][$hour] ?? 0);
});
$qr_staffing_grid = pg_waiting_report_grid($qr_hours, $qr_weekdays, function ($weekday, $hour) use ($qr_staffing) {
    return $qr_staffing[$weekday][$hour] ?? null;
});

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
    $minutes = lang('minutes');
    $time_head = array();

    foreach ($qr_head as $index => $label) {
        $time_head[] = ($index >= 4) ? $label . ' (' . $minutes . ')' : $label;
    }

    $sheets = array();
    $names = array();
    $sheets[] = pg_waiting_report_sheet(array('', lang('Total')), array(
        array(lang('Period'), $qr_period),
        array(lang('Numbers taken'), $qr_total['taken']),
        array(lang('Numbers served'), $qr_total['served']),
        array(lang('Left the queue'), $qr_total['left']),
        array(lang('Not here'), $qr_total['no_show']),
        array(lang('Median wait') . ' (' . $minutes . ')', pg_waiting_report_minutes($qr_total['wait'])),
        array(lang('Slowest tenth wait') . ' (' . $minutes . ')', pg_waiting_report_minutes($qr_total['wait_slow'])),
        array(lang('Median handling') . ' (' . $minutes . ')', pg_waiting_report_minutes($qr_total['handle'])),
        array(lang('Slowest tenth handling') . ' (' . $minutes . ')', pg_waiting_report_minutes($qr_total['handle_slow'])),
        array(lang('Wait target') . ' (' . $minutes . ')', $qr_target),
    ));
    $names[] = array('name' => lang('Summary'));

    foreach ($qr_groupings as $key => $label) {
        $sheets[] = pg_waiting_report_sheet(array_merge(array($label), $time_head), array_map('pg_waiting_report_line_cells', $qr_lines[$key]));
        $names[] = array('name' => pg_xlsx_sheet_title($label));
    }

    $sheets[] = pg_waiting_report_sheet($qr_arrivals_grid[0], $qr_arrivals_grid[1]);
    $names[] = array('name' => lang('Arrivals'));
    $needed = pg_waiting_report_grid($qr_hours, $qr_weekdays, function ($weekday, $hour) use ($qr_staffing) {
        return isset($qr_staffing[$weekday][$hour]) ? $qr_staffing[$weekday][$hour]['needed'] : null;
    });
    $sheets[] = pg_waiting_report_sheet($needed[0], $needed[1]);
    $names[] = array('name' => lang('Desks needed'));
    $path = tempnam(sys_get_temp_dir(), 'pgq');

    if (($path === false) || !pg_xlsx_write_book($names, function ($index) use ($sheets) {
        return $sheets[$index];
    }, $path)) {
        if ($path !== false) {
            @unlink($path);
        }
        output_error(lang('The workbook could not be written.'));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="queue_report_' . $qr_id . '_' . $qr_range['from'] . '_' . $qr_range['to'] . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
$qr_state = array('place' => $qr_id, 'days' => $qr_days, 'target' => $qr_target, 'group' => $qr_group);
$qr_link = function ($changes) use ($qr_state) {
    return 'queue_report.php?' . http_build_query(array_merge($qr_state, $changes));
};
$qr_min = function ($seconds) {
    return ($seconds === null) ? '–' : h(lang(array('string' => '{var:1} min', 'vars' => array(pg_format_number($seconds / 60, 1)))));
};

$qr_figures = pg_report_figures_html(array(
    pg_report_view_figure(lang('Numbers taken'), pg_format_number($qr_total['taken'], 0), 'bi-ticket-perforated', 'blue', lang(array('string' => '{var:1} served', 'vars' => array(pg_format_number($qr_total['served'], 0))))),
    pg_report_view_figure(lang('Median wait'), ($qr_total['wait'] === null) ? '–' : lang(array('string' => '{var:1} min', 'vars' => array(pg_format_number($qr_total['wait'] / 60, 1)))), 'bi-hourglass-split', 'amber', ($qr_total['wait_slow'] === null) ? '' : lang(array('string' => 'Slowest tenth over {var:1} min', 'vars' => array(pg_format_number($qr_total['wait_slow'] / 60, 1))))),
    pg_report_view_figure(lang('Median handling'), ($qr_total['handle'] === null) ? '–' : lang(array('string' => '{var:1} min', 'vars' => array(pg_format_number($qr_total['handle'] / 60, 1)))), 'bi-stopwatch', 'teal', ($qr_total['handle_slow'] === null) ? '' : lang(array('string' => 'Slowest tenth over {var:1} min', 'vars' => array(pg_format_number($qr_total['handle_slow'] / 60, 1))))),
    pg_report_view_figure(lang('Left the queue'), pg_format_number($qr_total['left'], 0), 'bi-box-arrow-left', 'rose', lang(array('string' => '{var:1} not here', 'vars' => array(pg_format_number($qr_total['no_show'], 0))))),
));

// The tools row: the period, the wait target, Show and Excel.
$qr_period_items = '';

foreach (PG_WAITING_REPORT_PERIODS as $days) {
    $qr_period_items .= '<a class="pg-seg-item' . (($days === $qr_days) ? ' is-active" aria-current="page' : '') . '" href="' . h($qr_link(array('days' => $days))) . '">' . h(lang(array('string' => 'Last {var:1} days', 'vars' => array($days)))) . '</a>';
}

$qr_tools = '
    <form method="get" action="queue_report.php" class="pg-report-tools">
        <input type="hidden" name="place" value="' . $qr_id . '"><input type="hidden" name="days" value="' . $qr_days . '"><input type="hidden" name="group" value="' . h($qr_group) . '">
        <div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">' . $qr_period_items . '</div>
        <span class="pg-report-field"><label class="pg-report-label" for="qr_target">' . h(lang('Wait target in minutes')) . '</label><input type="number" class="form-control" id="qr_target" name="target" min="1" max="' . PG_WAITING_REPORT_TARGET_MAX . '" value="' . $qr_target . '" style="width:6rem"></span>
        <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Show')) . '</button>
        <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h($qr_period) . '</span>
        <a class="pg-button pg-button-quiet ms-auto" href="' . h($qr_link(array('export' => 'xlsx'))) . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>
    </form>';

// The table of the chosen grouping.
$qr_group_items = '';

foreach ($qr_groupings as $key => $label) {
    $qr_group_items .= '<a class="pg-seg-item' . (($key === $qr_group) ? ' is-active" aria-current="page' : '') . '" href="' . h($qr_link(array('group' => $key))) . '">' . h($label) . '</a>';
}

$qr_rows = '';

foreach ($qr_lines[$qr_group] as $line) {
    $f = $line['figures'];
    $qr_rows .= '<tr><th scope="row">' . h($line['name']) . '</th>'
        . '<td class="text-end">' . pg_format_number($f['taken'], 0) . '</td><td class="text-end">' . pg_format_number($f['served'], 0) . '</td>'
        . '<td class="text-end">' . pg_format_number($f['left'], 0) . '</td><td class="text-end">' . pg_format_number($f['no_show'], 0) . '</td>'
        . '<td class="text-end">' . $qr_min($f['wait']) . '</td><td class="text-end">' . $qr_min($f['wait_slow']) . '</td>'
        . '<td class="text-end">' . $qr_min($f['handle']) . '</td><td class="text-end">' . $qr_min($f['handle_slow']) . '</td></tr>';
}

if ($qr_rows === '') {
    $qr_rows = '<tr><td colspan="9" class="fg-secondary">' . h(lang('No number was taken in this period.')) . '</td></tr>';
}

$qr_table_head = '<th>' . h($qr_groupings[$qr_group]) . '</th>';

foreach ($qr_head as $label) {
    $qr_table_head .= '<th class="text-end">' . h($label) . '</th>';
}

// A weekday × hour grid as a report table.
$qr_grid_html = function ($grid, $cell_html) {
    $head = '';

    foreach ($grid[0] as $index => $label) {
        $head .= ($index === 0) ? '<th></th>' : '<th class="text-end">' . h($label) . '</th>';
    }

    $body = '';

    foreach ($grid[1] as $row) {
        $body .= '<tr><th scope="row">' . h(array_shift($row)) . '</th>';

        foreach ($row as $cell) {
            $body .= '<td class="text-end">' . $cell_html($cell) . '</td>';
        }

        $body .= '</tr>';
    }

    return '<div class="table-responsive"><table class="pg-report-table"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
};

$qr_frame = function ($id, $title, $note, $body) {
    return '<section class="pg-card pg-report-frame" aria-labelledby="' . $id . '">
        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="' . $id . '">' . h($title) . '</h3><span class="pg-report-frame-note">' . h($note) . '</span></div>
        <div class="pg-report-frame-body">' . $body . '</div>
    </section>';
};

if ($qr_hours) {
    $qr_arrivals_html = $qr_frame('qr_arrivals_title', lang('Arrivals by weekday and hour'), lang('Numbers taken in each hour, all the period\'s days together'), $qr_grid_html($qr_arrivals_grid, function ($cell) {
        return $cell ? pg_format_number($cell, 0) : '<span class="fg-secondary">·</span>';
    }));

    $qr_staffing_html = ($qr_handling === null)
        ? '<p class="fg-secondary small">' . h(lang('The desks needed show once a service has its starting minutes or 20 numbers were served.')) . '</p>'
        : $qr_frame('qr_staffing_title', lang('Desks needed'), lang(array('string' => 'To keep the wait under {var:1} min, at {var:2} min a number', 'vars' => array($qr_target, pg_format_number($qr_handling / 60, 1)))), $qr_grid_html($qr_staffing_grid, function ($cell) {
            if (!is_array($cell) || (($cell['needed'] === 0) && ($cell['worked'] == 0))) {
                return '<span class="fg-secondary">·</span>';
            }

            // Fewer desks called on average than needed: bold.
            $text = pg_format_number($cell['needed'], 0) . ' <span class="fg-secondary">/ ' . h(pg_format_number($cell['worked'], 1)) . '</span>';

            return ($cell['worked'] < $cell['needed']) ? '<strong>' . $text . '</strong>' : $text;
        }))
        . '<p class="fg-secondary small">' . h(lang('Each hour reads desks needed / desks that called, on an average such day. Bold: fewer desks called than needed. Worked out from the arrivals and the median handling time, so a busy hour\'s needs are seen even where nobody called.')) . '</p>';
} else {
    $qr_arrivals_html = '';
    $qr_staffing_html = '';
}

$qr_body = $qr_tools . '
    <div class="mb-3">' . $qr_figures . '</div>
    <div class="mb-3"><span class="pg-seg" role="group" aria-label="' . h(lang('Group by')) . '">' . $qr_group_items . '</span></div>
    ' . $qr_frame('qr_table_title', $qr_groupings[$qr_group], $qr_period, '<div class="table-responsive"><table class="pg-report-table"><thead><tr>' . $qr_table_head . '</tr></thead><tbody>' . $qr_rows . '</tbody></table></div>') . '
    ' . $qr_arrivals_html . $qr_staffing_html . '
    <p class="fg-secondary small">' . h(lang('The wait runs from taking the number, or from the booked time for a booking on time, to the call; handling from the call to Finish. The slowest tenth: a tenth of the numbers took longer.')) . '</p>';

echo pg_page_shell(array(
    'title' => $qr_place['name'] . ' · ' . lang('Report'),
    'extra classes' => 'calendar',
    'icon' => 'calendar',
    'help' => 'calendars',
    'heading' => h($qr_place['name']) . ' ' . pg_waiting_place_pill($qr_place['status']),
    'heading_description' => lang('How long people waited, how long each number took and how many desks each hour needs.'),
    'breadcrumb' => array(
        array('label' => lang('Queue'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_queue_places.php'),
        array('label' => $qr_place['name'], 'url' => 'edit_queue_place.php?id=' . $qr_id),
        array('label' => lang('Report')),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'view' => pg_waiting_place_tabs($qr_id, 'report', count($qr_names['desk'])),
    )) . '
    ' . $qr_body . '
</main>
' . output_footer();
