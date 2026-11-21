<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor journey as staff read it: one contact's visits, newsletter
 * yeses, campaign e-mails opened and downloads (the journey's own rows),
 * merged by time with the forms, bookings, orders and course steps read from
 * their own tables. Drawn as the "Journey" card of the contact screen and the
 * folded "Journey" row of the Workspace customer card. Also the keep
 * period's trim of the journey's rows, run by the general job.
 *
 * The merge, the day groups and the sentences are pure; the *_read()
 * functions query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey.php');

/** contact_journey.kind of a download (a file the person took). */
const PG_LEAD_JOURNEY_DOWNLOAD = 2;

/** Rows the journey reads at most from each source. */
const PG_LEAD_JOURNEY_READ = 200;

/** Rows the contact screen shows before "Show earlier", and adds each time. */
const PG_LEAD_JOURNEY_PAGE = 20;

/** Rows the Workspace customer card shows. */
const PG_LEAD_JOURNEY_CARD_ROWS = 5;

/** Rows the keep period's trim deletes from one table in one run. */
const PG_LEAD_TRIM_BATCH = 2000;

/**
 * Each kind of journey row: its icon, its tone and the filter it falls
 * under (visits, forms, orders; '' only under All). Pure.
 *
 * @return array kind => array(icon, tone, filter)
 */
function pg_lead_journey_kinds()
{
    return array(
        'visit'          => array('bi-globe2', 'blue', 'visits'),
        'form'           => array('bi-ui-checks', 'teal', 'forms'),
        'newsletter'     => array('bi-envelope-paper', 'violet', ''),
        'campaign_click' => array('bi-envelope-open', 'amber', ''),
        'download'       => array('bi-download', 'gray', ''),
        'booking'        => array('bi-calendar-check', 'teal', 'forms'),
        'order'          => array('bi-bag', 'shop', 'orders'),
        'course'         => array('bi-mortarboard', 'violet', ''),
    );
}

/**
 * The journey row a contact_journey row stands for: a newsletter yes or a
 * campaign e-mail opened (both kept as the tie they made), a download; null
 * for a tie whose act has its own row elsewhere (a form, an order) or none
 * (a sign-in). Pure.
 *
 * @param array $row kind, detail
 * @return string|null a kind of pg_lead_journey_kinds()
 */
function pg_lead_journey_row_kind(array $row)
{
    $kind = (int) ($row['kind'] ?? 0);
    $detail = (string) ($row['detail'] ?? '');

    if ($kind === PG_LEAD_JOURNEY_DOWNLOAD) {
        return 'download';
    }

    if (($kind === PG_LEAD_JOURNEY_TIE) && in_array($detail, array('newsletter', 'campaign_click'), true)) {
        return $detail;
    }

    return null;
}

/**
 * A visit's pages as the journey counts them: how many, and the minutes
 * spent, rounded. Pure.
 *
 * @param mixed $pages lead_visits.pages: JSON of array(page, seconds, at)
 * @return array pages (list of array(page, seconds)), count, minutes
 */
function pg_lead_journey_visit_pages($pages)
{
    $list = is_string($pages) ? json_decode($pages, true) : $pages;
    $out = array();
    $seconds = 0;

    foreach (is_array($list) ? $list : array() as $page) {
        if (!is_array($page) || !isset($page[0]) || ((string) $page[0] === '')) {
            continue;
        }

        $out[] = array((string) $page[0], max(0, (int) ($page[1] ?? 0)));
        $seconds += max(0, (int) ($page[1] ?? 0));
    }

    return array('pages' => $out, 'count' => count($out), 'minutes' => (int) round($seconds / 60));
}

/**
 * The sources merged into one list, newest first; rows at the same second
 * keep the order of pg_lead_journey_kinds(), then their id. Pure.
 *
 * @param array[] $rows each kind, at, id
 * @return array[]
 */
function pg_lead_journey_merge(array $rows)
{
    $order = array_flip(array_keys(pg_lead_journey_kinds()));

    $rows = array_values(array_filter($rows, function ($row) use ($order) {
        return isset($order[$row['kind'] ?? '']) && ((int) ($row['at'] ?? 0) > 0);
    }));

    usort($rows, function ($a, $b) use ($order) {
        if ((int) $a['at'] !== (int) $b['at']) {
            return ((int) $a['at'] < (int) $b['at']) ? 1 : -1;
        }

        if ($order[$a['kind']] !== $order[$b['kind']]) {
            return $order[$a['kind']] - $order[$b['kind']];
        }

        return (int) ($b['id'] ?? 0) - (int) ($a['id'] ?? 0);
    });

    return $rows;
}

/**
 * The merged rows a day a group, in their order. Pure.
 *
 * @param array[] $rows   pg_lead_journey_merge()
 * @param string  $timezone '' = PHP's default
 * @return array list of array(day 'Y-m-d', rows)
 */
function pg_lead_journey_days(array $rows, $timezone = '')
{
    $zone = new DateTimeZone(($timezone !== '') ? $timezone : date_default_timezone_get());
    $days = array();

    foreach ($rows as $row) {
        $day = (new DateTime('@' . (int) $row['at']))->setTimezone($zone)->format('Y-m-d');
        $last = count($days) - 1;

        if (($last >= 0) && ($days[$last]['day'] === $day)) {
            $days[$last]['rows'][] = $row;
        } else {
            $days[] = array('day' => $day, 'rows' => array($row));
        }
    }

    return $days;
}

/**
 * Where the contact first came from: the earliest visit's channel,
 * campaign and landing page. Pure.
 *
 * @param array[] $visits lead_visits rows
 * @return array|null channel, campaign, landing_page, at
 */
function pg_lead_journey_first_touch(array $visits)
{
    $first = null;

    foreach ($visits as $visit) {
        if (((int) ($visit['started_at'] ?? 0) > 0) && (($first === null) || ((int) $visit['started_at'] < (int) $first['started_at']))) {
            $first = $visit;
        }
    }

    if ($first === null) {
        return null;
    }

    return array(
        'channel'      => (string) ($first['channel'] ?? ''),
        'campaign'     => (string) ($first['campaign'] ?? ''),
        'landing_page' => (string) ($first['landing_page'] ?? ''),
        'at'           => (int) $first['started_at'],
    );
}

/**
 * A journey row's one sentence, in the panel's words.
 *
 * @param array $row kind and the fields its source gave
 * @return string
 */
function pg_lead_journey_sentence(array $row)
{
    $name = trim((string) ($row['name'] ?? ''));

    switch ($row['kind']) {
        case 'visit':
            $count = (int) $row['count'];
            $minutes = (int) $row['minutes'];

            if ($minutes < 1) {
                return ($count === 1) ? lang('Visited 1 page') : lang(array('string' => 'Visited {var:1} pages', 'vars' => array($count)));
            }

            return ($count === 1)
                ? lang(array('string' => 'Visited 1 page, {var:1} minutes', 'vars' => array($minutes)))
                : lang(array('string' => 'Visited {var:1} pages, {var:2} minutes', 'vars' => array($count, $minutes)));

        case 'form':
            return ($name !== '') ? lang(array('string' => 'Sent the form {var:1}', 'vars' => array($name))) : lang('Sent a form');

        case 'newsletter':
            return lang('Subscribed to the newsletter');

        case 'campaign_click':
            return ($name !== '') ? lang(array('string' => 'Opened the campaign e-mail {var:1}', 'vars' => array($name))) : lang('Opened a campaign e-mail');

        case 'download':
            return lang(array('string' => 'Downloaded {var:1}', 'vars' => array($name)));

        case 'booking':
            return ($name !== '') ? lang(array('string' => 'Booked {var:1}', 'vars' => array($name))) : lang('Made a booking');

        case 'order':
            return lang(array('string' => 'Placed order {var:1}', 'vars' => array($name)));

        case 'course':
            return !empty($row['complete'])
                ? lang(array('string' => 'Completed the course {var:1}', 'vars' => array($name)))
                : lang(array('string' => 'Finished a lesson of {var:1}', 'vars' => array($name)));
    }

    return '';
}

/**
 * Whether a contact belongs to someone with panel work (staff): such a
 * contact gets no journey (J14).
 *
 * @param int $contact_id
 * @return bool
 */
function pg_lead_journey_contact_is_staff($contact_id)
{
    foreach ((array) db_values("SELECT user_id FROM user WHERE user_contact = '" . (int) $contact_id . "'") as $user_id) {
        if (function_exists('waf_user_id_is_staff') && waf_user_id_is_staff((int) $user_id)) {
            return true;
        }
    }

    return false;
}

/**
 * One contact's journey: its visits (nothing when it has none), where it
 * first came from and every row merged, newest first. Forms and orders only
 * for a reader with their right; a form only in a folder the reader may
 * open.
 *
 * @param int   $contact_id
 * @param array $rights orders (bool), forms (bool), folder (callable: page folder => bool)
 * @return array|null first, rows; null when no visit is tied to the contact
 */
function pg_lead_journey_read($contact_id, array $rights)
{
    $contact_id = (int) $contact_id;
    $limit = PG_LEAD_JOURNEY_READ;
    $visits = (array) db_items("SELECT id, started_at, channel, campaign, landing_page, pages FROM lead_visits
        WHERE contact_id = '" . $contact_id . "' ORDER BY started_at DESC, id DESC LIMIT " . $limit);

    if (!$visits) {
        return null;
    }

    $rows = array();

    foreach ($visits as $visit) {
        $pages = pg_lead_journey_visit_pages($visit['pages']);
        $rows[] = array('kind' => 'visit', 'id' => (int) $visit['id'], 'at' => (int) $visit['started_at']) + $pages;
    }

    $campaigns = array();

    foreach ((array) db_items("SELECT id, kind, at, ref_id, detail FROM contact_journey
        WHERE contact_id = '" . $contact_id . "' ORDER BY at DESC, id DESC LIMIT " . $limit) as $row) {
        $kind = pg_lead_journey_row_kind($row);

        if ($kind !== null) {
            $rows[] = array('kind' => $kind, 'id' => (int) $row['id'], 'at' => (int) $row['at'], 'ref_id' => (int) $row['ref_id'], 'name' => ($kind === 'download') ? (string) $row['detail'] : '');
            $campaigns[] = ($kind === 'campaign_click') ? (int) $row['ref_id'] : 0;
        }
    }

    // The campaign e-mails by their subject.
    $campaigns = array_values(array_filter(array_unique($campaigns)));
    $subjects = $campaigns ? array_column((array) db_items("SELECT id, subject FROM email_campaigns WHERE id IN (" . implode(',', $campaigns) . ")"), 'subject', 'id') : array();

    foreach ($rows as $index => $row) {
        if ($row['kind'] === 'campaign_click') {
            $rows[$index]['name'] = (string) ($subjects[$row['ref_id']] ?? '');
        }
    }

    if (!empty($rights['forms'])) {
        foreach ((array) db_items("SELECT f.id, f.submitted_timestamp, p.page_folder, c.form_name FROM forms f
            LEFT JOIN page p ON p.page_id = f.page_id
            LEFT JOIN custom_form_pages c ON c.page_id = f.page_id
            WHERE f.contact_id = '" . $contact_id . "' AND f.complete = 1 ORDER BY f.submitted_timestamp DESC LIMIT " . $limit) as $form) {
            if (!isset($rights['folder']) || call_user_func($rights['folder'], (int) $form['page_folder'])) {
                $rows[] = array('kind' => 'form', 'id' => (int) $form['id'], 'at' => (int) $form['submitted_timestamp'], 'name' => (string) $form['form_name'], 'url' => 'edit_submitted_form.php?id=' . (int) $form['id']);
            }
        }
    }

    if (!empty($rights['orders'])) {
        foreach ((array) db_items("SELECT id, order_number, order_date, total FROM orders
            WHERE contact_id = '" . $contact_id . "' AND status IN ('complete', 'exported') ORDER BY order_date DESC LIMIT " . $limit) as $order) {
            $rows[] = array('kind' => 'order', 'id' => (int) $order['id'], 'at' => (int) $order['order_date'], 'name' => (string) $order['order_number'], 'url' => 'view_order.php?id=' . (int) $order['id'], 'amount' => (int) $order['total']);
        }
    }

    // Bookings and course steps belong to the contact's account.
    $user_ids = array_map('intval', (array) db_values("SELECT user_id FROM user WHERE user_contact = '" . $contact_id . "'"));

    if ($user_ids && function_exists('pg_booking_ready') && pg_booking_ready()) {
        foreach ((array) db_items("SELECT b.id, b.created_at, s.name FROM bookings b LEFT JOIN booking_services s ON s.id = b.service_id
            WHERE b.user_id IN (" . implode(',', $user_ids) . ") AND b.status <> 'held' ORDER BY b.created_at DESC LIMIT " . $limit) as $booking) {
            $rows[] = array('kind' => 'booking', 'id' => (int) $booking['id'], 'at' => (int) $booking['created_at'], 'name' => (string) $booking['name']);
        }
    }

    if ($user_ids && function_exists('pg_course_ready') && pg_course_ready()) {
        foreach ((array) db_items("SELECT p.course_id, p.lesson_id, p.kind, p.created_at, c.name FROM course_progress p LEFT JOIN course c ON c.id = p.course_id
            WHERE p.user_id IN (" . implode(',', $user_ids) . ") AND p.kind IN (" . PG_COURSE_KIND_DONE . ", " . PG_COURSE_KIND_COMPLETE . ") ORDER BY p.created_at DESC LIMIT " . $limit) as $step) {
            $rows[] = array('kind' => 'course', 'id' => (int) $step['lesson_id'], 'at' => (int) $step['created_at'], 'name' => (string) $step['name'], 'complete' => ((int) $step['kind'] === PG_COURSE_KIND_COMPLETE));
        }
    }

    return array('first' => pg_lead_journey_first_touch($visits), 'rows' => pg_lead_journey_merge($rows));
}

/**
 * A channel's name as the visitor reports show it.
 *
 * @param string $channel
 * @return string
 */
function pg_lead_journey_channel_label($channel)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/visitors/sources.php');

    $labels = pg_visitors_sources_channel_labels();

    return $labels[$channel] ?? (string) $channel;
}

/**
 * The journey as the screens draw it: the first-came-from line's parts and
 * each row with its sentence, its time and the texts of its day.
 *
 * @param array  $journey pg_lead_journey_read()
 * @param string $base    the panel's address, ending in /
 * @return array first (channel, campaign, landing_page), rows (kind, icon,
 *               tone, filter, text, time_text, day, day_text, url, amount_text, pages)
 */
function pg_lead_journey_view(array $journey, $base)
{
    $kinds = pg_lead_journey_kinds();
    $first = $journey['first'];
    $rows = array();

    foreach (pg_lead_journey_days($journey['rows']) as $day) {
        $day_text = get_absolute_time(array('timestamp' => (int) $day['rows'][0]['at'], 'type' => 'date', 'format' => 'plain_text'));

        foreach ($day['rows'] as $row) {
            $pages = array();

            foreach ($row['pages'] ?? array() as $page) {
                $pages[] = array('page' => $page[0], 'time_text' => ($page[1] >= 60) ? lang(array('string' => '{var:1} min', 'vars' => array((int) round($page[1] / 60)))) : lang(array('string' => '{var:1} s', 'vars' => array($page[1]))));
            }

            $rows[] = array(
                'kind'        => $row['kind'],
                'icon'        => $kinds[$row['kind']][0],
                'tone'        => $kinds[$row['kind']][1],
                'filter'      => $kinds[$row['kind']][2],
                'text'        => pg_lead_journey_sentence($row),
                'time_text'   => get_absolute_time(array('timestamp' => (int) $row['at'], 'type' => 'time', 'format' => 'plain_text')),
                'day'         => $day['day'],
                'day_text'    => $day_text,
                'url'         => isset($row['url']) ? $base . $row['url'] : '',
                'amount_text' => isset($row['amount']) ? pg_money_text($row['amount'] / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '') : '',
                'pages'       => $pages,
            );
        }
    }

    return array(
        'first' => ($first === null) ? null : array(
            'channel'      => pg_lead_journey_channel_label($first['channel']),
            'campaign'     => $first['campaign'],
            'landing_page' => $first['landing_page'],
        ),
        'rows' => $rows,
    );
}

/**
 * The "First came from" line: the channel as a pill, the campaign, the
 * landing page.
 *
 * @param array $first pg_lead_journey_view()'s first
 * @return string HTML
 */
function pg_lead_journey_first_html(array $first)
{
    return '<div class="d-flex flex-wrap align-items-center gap-3 mb-5"><span class="pg-text-2">' . h(lang('First came from')) . '</span>'
        . '<span class="pg-pill pg-tone-blue">' . h($first['channel']) . '</span>'
        . (($first['campaign'] !== '') ? '<span class="pg-text-2">' . h($first['campaign']) . '</span>' : '')
        . (($first['landing_page'] !== '') ? '<span class="pg-text-meta text-break">' . h($first['landing_page']) . '</span>' : '')
        . '</div>';
}

/**
 * One journey row as gathered rows draw it: the icon tile, the sentence,
 * the time; a visit's pages as plain lines under a toggle.
 *
 * @param array $row pg_lead_journey_view()'s row
 * @param bool  $hidden drawn hidden, for "Show earlier"
 * @return string HTML
 */
function pg_lead_journey_row_html(array $row, $hidden = false)
{
    $title = ($row['url'] !== '') ? '<a href="' . h($row['url']) . '">' . h($row['text']) . '</a>' : h($row['text']);
    $pages = '';

    if ($row['pages']) {
        foreach ($row['pages'] as $page) {
            $pages .= '<div class="pg-gather-line"><span class="text-break">' . h($page['page']) . '</span><span class="pg-gather-line-end">' . h($page['time_text']) . '</span></div>';
        }

        $pages = '<div class="pg-gather" hidden data-lead-pages>' . $pages . '</div>'
            . '<button type="button" class="pg-gather-toggle" aria-expanded="false" data-lead-toggle>' . h(lang('Pages')) . '<span class="bi bi-chevron-down" aria-hidden="true"></span></button>';
    }

    return '<div class="pg-list-row is-read" data-lead-row data-lead-filter="' . h($row['filter']) . '" data-lead-day="' . h($row['day']) . '"' . ($hidden ? ' hidden' : '') . '>'
        . '<span class="pg-icon-tile pg-tone-' . h($row['tone']) . '" aria-hidden="true"><span class="bi ' . h($row['icon']) . '"></span></span>'
        . '<div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">' . $title . '</span><span class="pg-list-row-time">' . h($row['time_text']) . '</span></div>'
        . (($row['amount_text'] !== '') ? '<div class="pg-list-row-text"><b>' . h($row['amount_text']) . '</b></div>' : '')
        . $pages . '</div></div>';
}

/**
 * The contact screen's "Journey" card, or its one grey line when no visit
 * is tied to the contact; '' for a contact with panel work or before the
 * journey's tables are there.
 *
 * @param int   $contact_id
 * @param array $user validate_user()
 * @return string HTML
 */
function pg_lead_journey_contact_card($contact_id, array $user)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready() || pg_lead_journey_contact_is_staff($contact_id)) {
        return '';
    }

    $journey = pg_lead_journey_read($contact_id, array(
        'orders' => (ECOMMERCE === true) && (($user['role'] < 3) || !empty($user['manage_ecommerce'])),
        'forms'  => ($user['role'] < 3) || !empty($user['manage_forms']),
        'folder' => function ($folder) use ($user) {
            return ($user['role'] < 3) || check_edit_access($folder);
        },
    ));

    if ($journey === null) {
        return '<p class="pg-text-meta mb-7">' . h(lang('No visits are tied to this contact: marketing cookies were not accepted, or there was no visit since.')) . '</p>';
    }

    $view = pg_lead_journey_view($journey, OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/');
    $segments = '';

    foreach (array('' => lang('All'), 'visits' => lang('Visits'), 'forms' => lang('Forms and bookings'), 'orders' => lang('Orders')) as $key => $label) {
        $segments .= '<button type="button" class="pg-seg-item" aria-pressed="' . (($key === '') ? 'true' : 'false') . '" data-lead-filter-button="' . h($key) . '">' . h($label) . '</button>';
    }

    $rows = '';
    $day = '';

    foreach ($view['rows'] as $index => $row) {
        $hidden = ($index >= PG_LEAD_JOURNEY_PAGE);

        if ($row['day'] !== $day) {
            $day = $row['day'];
            $rows .= '<h3 class="pg-title-status mt-5 mb-2" data-lead-day-head="' . h($day) . '"' . ($hidden ? ' hidden' : '') . '>' . h($row['day_text']) . '</h3>';
        }

        $rows .= pg_lead_journey_row_html($row, $hidden);
    }

    return '
                            <section class="pg-card p-5 mb-7" id="lead_journey" aria-labelledby="lead_journey_title" data-lead-page="' . PG_LEAD_JOURNEY_PAGE . '">
                                <h2 class="pg-title-section mb-1" id="lead_journey_title">' . h(lang('Journey')) . '</h2>
                                <p class="pg-card-sub mb-5">' . h(lang('Visits and acts of this contact, newest first.')) . '</p>
                                ' . (($view['first'] !== null) ? pg_lead_journey_first_html($view['first']) : '') . '
                                <span class="pg-seg mb-3" role="group" aria-label="' . h(lang('Filter')) . '">' . $segments . '</span>
                                <div data-lead-rows>' . $rows . '</div>
                                <p class="pg-text-meta mt-3" data-lead-none hidden>' . h(lang('Nothing of this kind yet.')) . '</p>
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm mt-3" data-lead-more' . ((count($view['rows']) > PG_LEAD_JOURNEY_PAGE) ? '' : ' hidden') . '>' . h(lang('Show earlier')) . '</button>
                            </section>
                            <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/lead_journey.js?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/lead_journey.js') . '" defer></script>';
}

/**
 * The journey on the Workspace customer card: the first-came-from line and
 * the newest rows; null when the card's contact has no visit tied, has
 * panel work, or the reader may not open the contact.
 *
 * @param array $viewer the Workspace viewer
 * @param int   $contact_id the card's contact, only when open to the reader
 * @param string $base
 * @return array|null first, rows, contact_url
 */
function pg_lead_journey_card($viewer, $contact_id, $base)
{
    if (((int) $contact_id <= 0) || !function_exists('pg_lead_ready') || !pg_lead_ready() || pg_lead_journey_contact_is_staff($contact_id)) {
        return null;
    }

    $journey = pg_lead_journey_read($contact_id, array(
        'orders' => !empty($viewer['ecommerce']),
        'forms'  => !empty($viewer['forms']),
        'folder' => function ($folder) use ($viewer) {
            return ws_viewer_folder_open($viewer, $folder);
        },
    ));

    if ($journey === null) {
        return null;
    }

    $journey['rows'] = array_slice($journey['rows'], 0, PG_LEAD_JOURNEY_CARD_ROWS);
    $view = pg_lead_journey_view($journey, $base);
    $view['contact_url'] = (((int) $viewer['role'] < 3) || !empty($viewer['contacts'])) ? $base . 'edit_contact.php?id=' . (int) $contact_id : '';

    return $view;
}

/**
 * The keep period's trim (J13): visits and journey rows older than the
 * keep period, and browsers last seen before it, whose key ran out. A
 * batch a table each run; the general job calls it every tick.
 *
 * @param int $now
 * @return int rows deleted
 */
function pg_lead_trim_run($now)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready()) {
        return 0;
    }

    $start = pg_lead_keep_start($now);
    $deleted = 0;

    foreach (array('lead_visits' => 'started_at', 'contact_journey' => 'at', 'lead_browsers' => 'last_seen_at') as $table => $column) {
        if (!pg_schema_has($table, $column)) {
            continue;
        }

        if (db("DELETE FROM " . $table . " WHERE " . $column . " < '" . (int) $start . "' LIMIT " . PG_LEAD_TRIM_BATCH) !== false) {
            $deleted += (int) mysqli_affected_rows(db::$con);
        }
    }

    return $deleted;
}
