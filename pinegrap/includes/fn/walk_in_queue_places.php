<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's places and desks as staff set them up (Calendars ›
 * Queue, view_queue_places.php and edit_queue_place.php): a place's
 * services, hours, ways to take a number, notices, order rules and keep
 * period read from the Set-up form and cleaned, what keeps a place from
 * opening, and the reads and writes of waiting_places and waiting_desks.
 * The cleaning and the checks are pure; the rest needs pg_waiting_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Services a place may have: one Latin letter each. */
const PG_WAITING_SERVICES_MAX = 26;

/** Priority groups a place may have. */
const PG_WAITING_PRIORITY_MAX = 10;

/**
 * A place's settings before anything is set. Pure.
 *
 * @return array
 */
function pg_waiting_place_defaults()
{
    return array(
        'cap'              => 0,
        'closed_text'      => '',
        'ways'             => array('qr' => 1, 'site' => 1, 'kiosk' => 0),
        'party_size'       => 0,
        'notices'          => array('sms' => 1, 'whatsapp' => 0, 'email' => 1, 'ahead' => 3, 'texts' => array('taken' => '', 'ahead' => '', 'called' => '')),
        'priority'         => array(),
        'booking_staff'    => array(),
        'grace_minutes'    => 10,
        'not_here_minutes' => 3,
        'move_back'        => 0,
        'staff'            => array(),
        'keep_days'        => PG_WAITING_KEEP_DAYS,
    );
}

/**
 * A whole number within its bounds; the fallback for anything not a number.
 * Pure.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @param int   $fallback
 * @return int
 */
function pg_waiting_int($value, $min, $max, $fallback)
{
    if (!is_scalar($value) || (preg_match('/^\s*\d+\s*$/', (string) $value) !== 1)) {
        return (int) $fallback;
    }

    return max((int) $min, min((int) $max, (int) $value));
}

/**
 * A text trimmed and cut to its length; '' for anything not a text. Pure.
 *
 * @param mixed $value
 * @param int   $length
 * @return string
 */
function pg_waiting_text($value, $length)
{
    return is_scalar($value) ? mb_substr(trim((string) $value), 0, (int) $length) : '';
}

/**
 * A list of keyed rows (services, priority groups) cleaned: a row keeps its
 * key when it has the prefix and is not taken yet, else gets the next free
 * one, so a key named by numbers already taken never moves to another row.
 * Rows without a name are dropped. Pure.
 *
 * @param mixed  $rows   list of array(key, name, …)
 * @param string $prefix 's' for services, 'p' for priority groups
 * @param int    $max    rows kept at most
 * @param int    $length a name's length at most
 * @return array list of array(key, name) plus the row's other fields
 */
function pg_waiting_keyed_rows($rows, $prefix, $max, $length)
{
    $rows = is_array($rows) ? array_values($rows) : array();
    $kept = array();
    $taken = array();
    $top = 0;

    foreach ($rows as $row) {
        $key = (is_array($row) && isset($row['key'])) ? (string) $row['key'] : '';

        if (preg_match('/^' . $prefix . '(\d{1,5})$/', $key, $match) === 1) {
            $top = max($top, (int) $match[1]);
        }
    }

    foreach ($rows as $row) {
        $name = is_array($row) ? pg_waiting_text($row['name'] ?? '', $length) : '';

        if (($name === '') || (count($kept) >= $max)) {
            continue;
        }

        $key = (string) ($row['key'] ?? '');

        if ((preg_match('/^' . $prefix . '\d{1,5}$/', $key) !== 1) || isset($taken[$key])) {
            $key = $prefix . (++$top);
        }

        $taken[$key] = true;
        $kept[] = array('key' => $key, 'name' => $name) + $row;
    }

    return $kept;
}

/**
 * A place's services cleaned: key, name, letter (one Latin capital) and the
 * minutes the estimate starts with (1 to 240, 10 when not given). Pure.
 *
 * @param mixed $services JSON text or list
 * @return array list of array(key, name, letter, minutes)
 */
function pg_waiting_services_clean($services)
{
    if (is_string($services)) {
        $services = json_decode($services, true);
    }

    $clean = array();

    foreach (pg_waiting_keyed_rows($services, 's', PG_WAITING_SERVICES_MAX, 60) as $service) {
        $clean[] = array(
            'key'     => $service['key'],
            'name'    => $service['name'],
            'letter'  => pg_waiting_letter_clean($service['letter'] ?? ''),
            'minutes' => pg_waiting_int($service['minutes'] ?? '', 1, 240, 10),
        );
    }

    return $clean;
}

/**
 * The first letter no service of the list has, 'A' to 'Z'; '' when all are
 * taken. Pure.
 *
 * @param array $services
 * @return string
 */
function pg_waiting_free_letter($services)
{
    $taken = array();

    foreach ((array) $services as $service) {
        $taken[(string) ($service['letter'] ?? '')] = true;
    }

    foreach (range('A', 'Z') as $letter) {
        if (!isset($taken[$letter])) {
            return $letter;
        }
    }

    return '';
}

/**
 * A place's settings cleaned onto the defaults: every value within its
 * bounds, the staff list kept as stored. Pure.
 *
 * @param mixed $settings JSON text or array
 * @return array
 */
function pg_waiting_place_settings($settings)
{
    if (is_string($settings)) {
        $settings = json_decode($settings, true);
    }

    $in = is_array($settings) ? $settings : array();
    $out = pg_waiting_place_defaults();
    $flag = function ($value) {
        return empty($value) ? 0 : 1;
    };

    $out['cap'] = pg_waiting_int($in['cap'] ?? '', 0, 9999, 0);
    $out['closed_text'] = pg_waiting_text($in['closed_text'] ?? '', 500);
    $out['party_size'] = $flag($in['party_size'] ?? 0);

    foreach ($out['ways'] as $way => $on) {
        $out['ways'][$way] = isset($in['ways'][$way]) ? $flag($in['ways'][$way]) : $on;
    }

    $notices = (isset($in['notices']) && is_array($in['notices'])) ? $in['notices'] : array();

    foreach (array('sms', 'whatsapp', 'email') as $channel) {
        $out['notices'][$channel] = isset($notices[$channel]) ? $flag($notices[$channel]) : $out['notices'][$channel];
    }

    $out['notices']['ahead'] = pg_waiting_int($notices['ahead'] ?? '', 1, 20, 3);

    foreach (array_keys($out['notices']['texts']) as $text) {
        $out['notices']['texts'][$text] = pg_waiting_text($notices['texts'][$text] ?? '', 500);
    }

    $out['priority'] = array_map(function ($group) {
        return array('key' => $group['key'], 'name' => $group['name']);
    }, pg_waiting_keyed_rows($in['priority'] ?? array(), 'p', PG_WAITING_PRIORITY_MAX, 60));
    $out['booking_staff'] = array_values(array_unique(array_filter(array_map('intval', (array) ($in['booking_staff'] ?? array())))));
    $out['grace_minutes'] = pg_waiting_int($in['grace_minutes'] ?? '', 0, 60, 10);
    $out['not_here_minutes'] = pg_waiting_int($in['not_here_minutes'] ?? '', 1, 60, 3);
    $out['move_back'] = $flag($in['move_back'] ?? 0);
    $out['staff'] = (isset($in['staff']) && is_array($in['staff'])) ? $in['staff'] : array();
    $out['keep_days'] = pg_waiting_keep_days($in);

    return $out;
}

/**
 * A place read from the Set-up form: name, address, services, hours and
 * exceptions (the bookings' grid and rows, booking_settings.php) and the
 * settings, with the errors that keep it from being saved. Pure.
 *
 * @param array  $post  the form's fields
 * @param array  $place the stored place (its staff list is kept)
 * @param string $today 'YYYY-MM-DD' in the site's zone
 * @return array place => array(name, address, services, hours, exceptions, settings), errors => list of texts
 */
function pg_waiting_place_input($post, $place, $today)
{
    $post = is_array($post) ? $post : array();
    $errors = array();
    $column = function ($name, $index) use ($post) {
        return (isset($post[$name][$index]) && is_scalar($post[$name][$index])) ? (string) $post[$name][$index] : '';
    };

    $services = array();

    foreach (array_keys(is_array($post['service_name'] ?? null) ? $post['service_name'] : array()) as $i) {
        $services[] = array('key' => $column('service_key', $i), 'name' => $column('service_name', $i), 'letter' => $column('service_letter', $i), 'minutes' => $column('service_minutes', $i));
    }

    $services = pg_waiting_services_clean($services);
    $letters = array();

    foreach ($services as $service) {
        if ($service['letter'] === '') {
            $errors[] = lang(array('string' => 'Give "{var:1}" a letter from A to Z.', 'vars' => array($service['name'])));
        } elseif (isset($letters[$service['letter']])) {
            $errors[] = lang(array('string' => 'Two services have the letter {var:1}: each needs its own.', 'vars' => array($service['letter'])));
        }

        $letters[$service['letter']] = true;
    }

    $hours = pg_booking_settings_hours_from_fields($post['hours'] ?? array());
    $exceptions = pg_booking_settings_exceptions_from_rows($post['x_from'] ?? array(), $post['x_to'] ?? array(), $post['x_open'] ?? array(), $post['x_close'] ?? array(), $today);

    if ($hours['bad']) {
        $errors[] = lang('An hour of the week is not a time, or it ends before it starts.');
    }

    if ($exceptions['bad']) {
        $errors[] = lang('A row of days off has a wrong day or hour.');
    }

    $priority = array();

    foreach (array_keys(is_array($post['priority_name'] ?? null) ? $post['priority_name'] : array()) as $i) {
        $priority[] = array('key' => $column('priority_key', $i), 'name' => $column('priority_name', $i));
    }

    $stored = pg_waiting_place_settings($place['settings'] ?? null);
    $settings = pg_waiting_place_settings(array(
        'cap'              => ($post['cap'] ?? '') === '' ? 0 : $post['cap'],
        'closed_text'      => $post['closed_text'] ?? '',
        'ways'             => array('qr' => !empty($post['way_qr']), 'site' => !empty($post['way_site']), 'kiosk' => !empty($post['way_kiosk'])),
        'party_size'       => !empty($post['party_size']),
        'notices'          => array(
            'sms'      => !empty($post['notice_sms']),
            'whatsapp' => !empty($post['notice_whatsapp']),
            'email'    => !empty($post['notice_email']),
            'ahead'    => $post['notice_ahead'] ?? '',
            'texts'    => is_array($post['notice_text'] ?? null) ? $post['notice_text'] : array(),
        ),
        'priority'         => $priority,
        'booking_staff'    => $post['booking_staff'] ?? array(),
        'grace_minutes'    => $post['grace_minutes'] ?? '',
        'not_here_minutes' => $post['not_here_minutes'] ?? '',
        'move_back'        => !empty($post['move_back']),
        'staff'            => $stored['staff'],
        'keep_days'        => $post['keep_days'] ?? '',
    ));

    $name = pg_waiting_text($post['name'] ?? '', 150);

    if ($name === '') {
        $errors[] = lang('Give the place a name.');
    }

    return array(
        'place'  => array(
            'name'       => $name,
            'address'    => pg_waiting_text($post['address'] ?? '', 255),
            'services'   => $services,
            'hours'      => $hours['hours'],
            'exceptions' => $exceptions['exceptions'],
            'settings'   => $settings,
        ),
        'errors' => $errors,
    );
}

/**
 * What keeps a place from opening, in the order Set-up shows it: services
 * (none), hours (none in the week), ways (no way to take a number), desks
 * (none). Pure.
 *
 * @param array $place      services, hours, settings as cleaned
 * @param int   $desk_count
 * @return string[]
 */
function pg_waiting_place_missing($place, $desk_count)
{
    $missing = array();
    $settings = pg_waiting_place_settings($place['settings'] ?? null);

    if (!pg_waiting_services_clean($place['services'] ?? array())) {
        $missing[] = 'services';
    }

    if (!pg_booking_hours_clean($place['hours'] ?? array())) {
        $missing[] = 'hours';
    }

    if (!array_filter($settings['ways'])) {
        $missing[] = 'ways';
    }

    if ((int) $desk_count <= 0) {
        $missing[] = 'desks';
    }

    return $missing;
}

/**
 * A desk read from its drawer: a name and the place's services it serves
 * (at least one). Pure.
 *
 * @param array $post
 * @param array $services the place's services
 * @return array name, services (keys), error ('' when it may be added)
 */
function pg_waiting_desk_input($post, $services)
{
    $known = array_column((array) $services, 'key');
    $chosen = array_values(array_intersect($known, array_map('strval', (array) ($post['desk_services'] ?? array()))));
    $name = pg_waiting_text($post['desk_name'] ?? '', 100);
    $error = '';

    if ($name === '') {
        $error = lang('Give the desk a name.');
    } elseif (!$chosen) {
        $error = lang('Choose the services the desk serves.');
    }

    return array('name' => $name, 'services' => $chosen, 'error' => $error);
}

/**
 * The public number page of a place (queue.php); the QR poster's link
 * carries from=qr, so a number taken through it is known as such.
 *
 * @param int    $place_id
 * @param string $from     'qr' for the poster, '' for the site
 * @return string
 */
function pg_waiting_place_url($place_id, $from = '')
{
    return URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/queue.php?place=' . (int) $place_id . (($from === 'qr') ? '&from=qr' : '');
}

/**
 * A place's tabs, the same strip on each of its screens: Set-up and Desks
 * (edit_queue_place.php), Today (queue_desk.php), Report (queue_report.php).
 *
 * @param int    $place_id
 * @param string $active setup, desks, today or report
 * @param int    $desks  the place's desks, counted on the Desks tab
 * @return string HTML
 */
function pg_waiting_place_tabs($place_id, $active, $desks)
{
    $id = (int) $place_id;
    $tabs = array(
        'setup'  => array('edit_queue_place.php?id=' . $id, lang('Set-up')),
        'desks'  => array('edit_queue_place.php?id=' . $id . '&view=desks', lang('Desks')),
        'today'  => array('queue_desk.php?place=' . $id, lang('Today')),
        'report' => array('queue_report.php?place=' . $id, lang('Report')),
    );
    $html = '';

    foreach ($tabs as $key => $tab) {
        $html .= '<a class="pg-seg-item' . (($key === $active) ? ' is-active" aria-current="page' : '') . '" href="' . h($tab[0]) . '">' . h($tab[1])
            . (($key === 'desks') ? ' <span class="pg-seg-count">' . (int) $desks . '</span>' : '') . '</a>';
    }

    return '<span class="pg-seg" role="group" aria-label="' . h(lang('Place')) . '">' . $html . '</span>';
}

/**
 * A place's state as its pill, in the same words on every screen: Open
 * (teal), Paused (amber), Closed (grey).
 *
 * @param string $status
 * @return string HTML
 */
function pg_waiting_place_pill($status)
{
    $pills = array(
        'open'   => array(lang('Open'), 'pg-tone-teal'),
        'paused' => array(lang('Paused'), 'pg-tone-amber'),
        'closed' => array(lang('Closed'), 'pg-tone-gray'),
    );
    $pill = $pills[$status] ?? $pills['closed'];

    return '<span class="pg-pill ' . $pill[1] . '">' . h($pill[0]) . '</span>';
}

/**
 * Today as a number's day: YYYYMMDD in the site's zone.
 *
 * @param int|null $now
 * @return int
 */
function pg_waiting_today($now = null)
{
    return (int) date('Ymd', ($now === null) ? time() : (int) $now);
}

/**
 * A stored place row decoded: services, hours and exceptions cleaned, the
 * settings onto their defaults.
 *
 * @param array $row
 * @return array
 */
function pg_waiting_place_decode($row)
{
    return array(
        'id'         => (int) $row['id'],
        'name'       => (string) $row['name'],
        'address'    => (string) $row['address'],
        'status'     => in_array($row['status'], array('open', 'paused'), true) ? $row['status'] : 'closed',
        'services'   => pg_waiting_services_clean($row['services']),
        'hours'      => pg_booking_hours_clean($row['hours']),
        'exceptions' => pg_booking_exceptions_clean($row['exceptions']),
        'settings'   => pg_waiting_place_settings($row['settings']),
    );
}

/**
 * One place, null when there is none of the id.
 *
 * @param int $id
 * @return array|null
 */
function pg_waiting_place_get($id)
{
    $row = db_item("SELECT * FROM waiting_places WHERE id = '" . (int) $id . "'");

    return $row ? pg_waiting_place_decode($row) : null;
}

/**
 * Every place for the list, with its desks open and all and the numbers
 * waiting and taken today.
 *
 * @return array list of places with desks_open, desks, waiting, today
 */
function pg_waiting_place_list()
{
    $day = pg_waiting_today();
    $desks = array();
    $tickets = array();

    foreach (db_items("SELECT place_id, COUNT(*) AS desks, SUM(state = 'open') AS open FROM waiting_desks GROUP BY place_id") as $row) {
        $desks[(int) $row['place_id']] = array((int) $row['desks'], (int) $row['open']);
    }

    foreach (db_items("SELECT place_id, COUNT(*) AS today, SUM(state = 'waiting') AS waiting FROM waiting_tickets WHERE day = '" . $day . "' GROUP BY place_id") as $row) {
        $tickets[(int) $row['place_id']] = array((int) $row['today'], (int) $row['waiting']);
    }

    $places = array();

    foreach (db_items("SELECT * FROM waiting_places ORDER BY name, id") as $row) {
        $place = pg_waiting_place_decode($row);
        $place['desks'] = $desks[$place['id']][0] ?? 0;
        $place['desks_open'] = $desks[$place['id']][1] ?? 0;
        $place['today'] = $tickets[$place['id']][0] ?? 0;
        $place['waiting'] = $tickets[$place['id']][1] ?? 0;
        $places[] = $place;
    }

    return $places;
}

/**
 * A new place, closed, with its name and first service (letter A) and the
 * default settings; its priority groups start as the three usual ones.
 *
 * @param string $name
 * @param string $service the first service's name
 * @param int    $user_id
 * @return int the new place's id, 0 when it was not written
 */
function pg_waiting_place_create($name, $service, $user_id)
{
    $settings = pg_waiting_place_defaults();
    $settings['priority'] = array(
        array('key' => 'p1', 'name' => lang('Elderly')),
        array('key' => 'p2', 'name' => lang('Disabled person')),
        array('key' => 'p3', 'name' => lang('Pregnant')),
    );
    $services = array(array('key' => 's1', 'name' => $service, 'letter' => 'A', 'minutes' => 10));
    $now = time();

    $written = db_q("INSERT INTO waiting_places (name, status, services, hours, exceptions, settings, created_by, created_at, updated_at) VALUES (?, 'closed', ?, '{}', '{}', ?, ?, ?, ?)",
        array($name, json_encode($services, JSON_UNESCAPED_UNICODE), json_encode($settings, JSON_UNESCAPED_UNICODE), (int) $user_id, $now, $now));

    return ($written === false) ? 0 : (int) mysqli_insert_id(db::$con);
}

/**
 * A place's Set-up written: as pg_waiting_place_input() read it.
 *
 * @param int   $id
 * @param array $values name, address, services, hours, exceptions, settings
 * @return bool
 */
function pg_waiting_place_save($id, $values)
{
    return db_q("UPDATE waiting_places SET name = ?, address = ?, services = ?, hours = ?, exceptions = ?, settings = ?, updated_at = ? WHERE id = ?", array(
        $values['name'],
        $values['address'],
        json_encode($values['services'], JSON_UNESCAPED_UNICODE),
        json_encode((object) $values['hours']),
        json_encode((object) $values['exceptions']),
        json_encode($values['settings'], JSON_UNESCAPED_UNICODE),
        time(),
        (int) $id,
    )) !== false;
}

/**
 * A place opened or closed.
 *
 * @param int    $id
 * @param string $status 'open' or 'closed'
 * @return bool
 */
function pg_waiting_place_set_status($id, $status)
{
    return db_q("UPDATE waiting_places SET status = ?, updated_at = ? WHERE id = ?", array(($status === 'open') ? 'open' : 'closed', time(), (int) $id)) !== false;
}

/**
 * A place and its desks deleted, only while no number was taken there
 * today. Its earlier numbers stay for the report and are cleared at the
 * default keep period.
 *
 * @param int $id
 * @return bool false when a number was taken today or nothing was deleted
 */
function pg_waiting_place_delete($id)
{
    if ((int) db_value("SELECT COUNT(*) FROM waiting_tickets WHERE place_id = '" . (int) $id . "' AND day = '" . pg_waiting_today() . "'") > 0) {
        return false;
    }

    if (db_q("DELETE FROM waiting_places WHERE id = ?", array((int) $id)) === false) {
        return false;
    }

    db_q("DELETE FROM waiting_desks WHERE place_id = ?", array((int) $id));

    return true;
}

/**
 * A place's desks, by name, with the name of whoever has one open.
 *
 * @param int $place_id
 * @return array list of array(id, name, services, state, user_id, person)
 */
function pg_waiting_desks($place_id)
{
    $desks = array();

    foreach (db_items("SELECT waiting_desks.*, user.user_username, contacts.first_name, contacts.last_name
        FROM waiting_desks
        LEFT JOIN user ON user.user_id = waiting_desks.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE waiting_desks.place_id = '" . (int) $place_id . "'
        ORDER BY waiting_desks.name, waiting_desks.id") as $row) {
        $services = json_decode((string) $row['services'], true);
        $desks[] = array(
            'id'       => (int) $row['id'],
            'name'     => (string) $row['name'],
            'services' => is_array($services) ? array_values(array_map('strval', $services)) : array(),
            'state'    => (string) $row['state'],
            'user_id'  => (int) $row['user_id'],
            'person'   => ((int) $row['user_id'] > 0) ? pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']) : '',
        );
    }

    return $desks;
}

/**
 * A desk added to a place, closed.
 *
 * @param int      $place_id
 * @param string   $name
 * @param string[] $services the service keys it serves
 * @return bool
 */
function pg_waiting_desk_add($place_id, $name, $services)
{
    $now = time();

    return db_q("INSERT INTO waiting_desks (place_id, name, services, state, created_at, updated_at) VALUES (?, ?, ?, 'closed', ?, ?)",
        array((int) $place_id, $name, json_encode(array_values($services)), $now, $now)) !== false;
}

/**
 * Whether a desk may be removed: only a closed one. A paused desk is still
 * someone's for their shift and may have a number called to it. Pure.
 *
 * @param string $state the desk's state
 * @return bool
 */
function pg_waiting_desk_removable($state)
{
    return $state === 'closed';
}

/**
 * A place's desk removed, only while it is closed.
 *
 * @param int $place_id
 * @param int $desk_id
 * @return bool
 */
function pg_waiting_desk_remove($place_id, $desk_id)
{
    if (db_q("DELETE FROM waiting_desks WHERE id = ? AND place_id = ? AND state = 'closed'", array((int) $desk_id, (int) $place_id)) === false) {
        return false;
    }

    return mysqli_affected_rows(db::$con) > 0;
}
