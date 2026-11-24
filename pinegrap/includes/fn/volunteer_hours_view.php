<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Hours tab of a volunteer programme (edit_volunteer_programme.php): the
 * coordinator's list of the day (each signed volunteer with Check in, Check
 * out and "Did not come"), the rows waiting for approval with Approve, the
 * places' QR posters, and the printable poster of one place. Loaded by that
 * screen only.
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
 * A sign-up's state on the day's list. Pure.
 *
 * @param array $row outcome, check_in, check_out
 * @return array label, tone, time ('' or the time it names)
 */
function pg_volunteer_day_state(array $row)
{
    if ((string) ($row['check_out'] ?? '') !== '') {
        return array(lang('Checked out'), 'gray', (string) $row['check_out']);
    }

    if ((string) ($row['check_in'] ?? '') !== '') {
        return array(lang('Checked in'), 'teal', (string) $row['check_in']);
    }

    if ((string) ($row['outcome'] ?? '') === 'no_show') {
        return array(lang('Did not come'), 'rose', '');
    }

    return array(lang('Not here yet'), 'amber', '');
}

/**
 * The list of the day, a card a shift, a row a volunteer with the buttons
 * that fit the row now. The buttons post the screen's hours form.
 *
 * @param array  $programme
 * @param array  $rows pg_volunteer_day_rows()
 * @param string $now
 * @return string HTML
 */
function pg_volunteer_day_html(array $programme, array $rows, $now)
{
    $title = lang(array('string' => 'Today, {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => strtotime(substr($now, 0, 10)), 'type' => 'date', 'format' => 'plain_text')))));

    if (!$rows) {
        return '<section class="pg-card p-5 mb-5"><h2 class="pg-title-section mb-0">' . h($title) . '</h2><p class="pg-card-sub mb-0">' . h(lang('Nobody is signed up for a shift today.')) . '</p></section>';
    }

    $shifts = array();

    foreach ($rows as $row) {
        $shifts[$row['shift']['id']][] = $row;
    }

    $html = '<h2 class="pg-title-section mb-3">' . h($title) . '</h2>';

    foreach ($shifts as $people) {
        $shift = $people[0]['shift'];
        $lines = '';

        foreach ($people as $row) {
            $state = pg_volunteer_day_state($row);
            $time = ($state[2] !== '') ? ' ' . date('H:i', strtotime($state[2])) : '';
            $buttons = '';
            $locked = ((int) $row['approved_at'] > 0);

            if (!$locked && ((string) $row['check_in'] === '')) {
                $buttons .= '<button type="submit" name="submit_check_in" value="' . (int) $row['id'] . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Check in')) . '</button>';
                $buttons .= ((string) $row['outcome'] !== 'no_show') && (strcmp($now, (string) $shift['start']) >= 0)
                    ? '<button type="submit" name="submit_no_show" value="' . (int) $row['id'] . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Did not come')) . '</button>' : '';
            } elseif (!$locked && ((string) $row['check_out'] === '')) {
                $buttons .= '<button type="submit" name="submit_check_out" value="' . (int) $row['id'] . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Check out')) . '</button>';
            }

            $lines .= '<div class="pg-card-inner-row flex-wrap gap-2"><span class="flex-grow-1 min-w-0"><span class="pg-text-2">' . h($row['name']) . '</span> <span class="pg-text-meta">' . h(pg_volunteer_role_name($programme, (string) $row['role'])) . '</span></span>'
                . '<span class="pg-pill pg-tone-' . $state[1] . '">' . h($state[0] . $time) . '</span>' . $buttons . '</div>';
        }

        $html .= '<section class="pg-card p-5 mb-5"><h3 class="pg-title-section mb-3"><span class="pg-tabular">' . h(date('H:i', strtotime($shift['start'])) . '–' . date('H:i', strtotime($shift['end']))) . '</span> · ' . h($shift['place']) . '</h3>'
            . '<div class="pg-card-inner">' . $lines . '</div></section>';
    }

    return $html;
}

/**
 * The rows waiting for approval, a tick and a quiet Approve each; the
 * toolbar's fill approves the ticked rows.
 *
 * @param array $programme
 * @param array $queue pg_volunteer_hours_queue()
 * @return string HTML
 */
function pg_volunteer_hours_queue_html(array $programme, array $queue)
{
    $html = '<section class="pg-card p-5 mb-5"><h2 class="pg-title-section mb-3">' . h(lang('Waiting for approval')) . ' <span class="pg-seg-count">' . count($queue) . '</span></h2>';

    if (!$queue) {
        return $html . '<p class="pg-text-meta mb-0">' . h(lang('No hours wait for approval.')) . '</p></section>';
    }

    $html .= '<div class="pg-card-inner">';

    foreach ($queue as $id => $row) {
        $what = is_array($row['shift'])
            ? pg_volunteer_shift_when($row['shift']) . ' · ' . pg_volunteer_role_name($programme, (string) $row['role'])
            : lang('Outside a shift') . ' · ' . get_absolute_time(array('timestamp' => strtotime((string) $row['worked_on']), 'type' => 'date', 'format' => 'plain_text'));
        $html .= '<div class="pg-card-inner-row flex-wrap gap-2">'
            . '<input type="checkbox" class="pg-checkbox" name="approve_hours[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Choose {var:1}', 'vars' => array($row['name'])))) . '">'
            . '<span class="flex-grow-1 min-w-0"><span class="pg-text-2 d-block">' . h($row['name']) . '</span><span class="pg-text-meta">' . h($what . (((string) $row['note'] !== '') ? ' · ' . $row['note'] : '')) . '</span></span>'
            . '<span class="pg-tabular pg-text-2">' . h(pg_volunteer_duration_text($row['minutes_worked'])) . '</span>'
            . '<button type="submit" name="submit_hours_approve" value="' . $id . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Approve')) . '</button></div>';
    }

    return $html . '</div></section>';
}

/**
 * The places' posters: a link each to its printable page.
 *
 * @param array $programme
 * @param array $places
 * @return string HTML
 */
function pg_volunteer_posters_html(array $programme, array $places)
{
    $html = '<section class="pg-card p-5 mb-5"><h2 class="pg-title-section mb-1">' . h(lang('QR posters')) . '</h2>'
        . '<p class="pg-card-sub mb-3">' . h(lang('Hang a place\'s poster where volunteers arrive: they scan it with their phone to check in and out.')) . '</p>';

    if (!$places) {
        return $html . '<p class="pg-text-meta mb-0">' . h(lang('Add a shift with a place first.')) . '</p></section>';
    }

    $html .= '<div class="d-flex flex-wrap gap-2">';

    foreach ($places as $place) {
        $html .= '<a class="pg-button pg-button-quiet pg-button-sm" target="_blank" rel="noopener" href="edit_volunteer_programme.php?id=' . (int) $programme['id'] . '&amp;view=poster&amp;place=' . h(rawurlencode($place)) . '"><i class="bi bi-qr-code" aria-hidden="true"></i>' . h($place) . '</a>';
    }

    return $html . '</div></section>';
}

/**
 * A place's printable poster: the programme, the place, the QR code of its
 * check-in address and how to use it. A whole page.
 *
 * @param array  $programme
 * @param string $place
 * @return string HTML
 */
function pg_volunteer_poster_page(array $programme, $place)
{
    $software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
    $qr = pg_qr_svg_data_uri(pg_volunteer_poster_url((int) $programme['id'], $place));

    return '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h($programme['name'] . ' · ' . $place) . '</title>
<style>@media print { .pg-poster-tools { display: none; } }</style>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-6/css/bootstrap.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-6/css/bootstrap.min.css') . '">
</head>
<body>
<main class="container text-center py-7" style="max-width:40rem">
    <p class="pg-poster-tools"><button type="button" onclick="window.print()">' . h(lang('Print or save as PDF')) . '</button></p>
    <h1 class="mb-2">' . h($place) . '</h1>
    <p class="mb-5" style="font-size:1.25rem">' . h($programme['name']) . '</p>
    <img src="' . h($qr) . '" alt="' . h(lang('QR code')) . '" style="width:min(80vw,22rem);height:auto">
    <p class="fw-semibold mt-5 mb-2" style="font-size:1.5rem">' . h(lang('Volunteers: scan to check in and out')) . '</p>
    <p class="mb-0">' . h(lang('Open your phone\'s camera and point it at the code. Open the link from the coordinator\'s e-mail once first.')) . '</p>
</main>
</body>
</html>';
}
