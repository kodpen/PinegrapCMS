<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's two screens outside the panel: the lobby screen on a
 * TV or monitor (queue_lobby.php: the numbers now served and their desks,
 * the last five calls, a chime on each call; numbers only, never a name)
 * and the kiosk on a tablet (queue_kiosk.php: the services as cards, then
 * the number taken). Each opens by a signed display link, an HMAC of the
 * place under the site's ENCRYPTION_KEY (pg_booking_key()), so neither
 * needs a column nor a signed-in user. The board and the links are pure;
 * the reads need pg_waiting_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Past calls the lobby lists under the numbers now served. */
const PG_WAITING_LOBBY_PAST = 5;

/** Seconds between two reads of the lobby screen. */
const PG_WAITING_LOBBY_READ_SECONDS = 5;

/** Seconds the kiosk waits untouched before it goes back to the start. */
const PG_WAITING_KIOSK_IDLE_SECONDS = 60;

/**
 * A place's display link token: HMAC-SHA256 of waiting-<kind>:<place> under
 * the site's key. The lobby's and the kiosk's differ, so the lobby's link,
 * shown on a public TV, never takes numbers. Pure.
 *
 * @param int    $place_id
 * @param string $kind lobby or kiosk
 * @param string $key  pg_booking_key()
 * @return string 64 hex characters, '' when it cannot be made
 */
function pg_waiting_screen_token($place_id, $kind, $key)
{
    $place_id = (int) $place_id;
    $key = (string) $key;

    if (($place_id <= 0) || ($key === '') || !in_array($kind, array('lobby', 'kiosk'), true)) {
        return '';
    }

    return hash_hmac('sha256', 'waiting-' . $kind . ':' . $place_id, $key);
}

/**
 * Whether a display link's token opens this place's screen. Pure.
 *
 * @param mixed  $token from the link
 * @param int    $place_id
 * @param string $kind lobby or kiosk
 * @param string $key  pg_booking_key()
 * @return bool
 */
function pg_waiting_screen_token_valid($token, $place_id, $kind, $key)
{
    $expected = pg_waiting_screen_token($place_id, $kind, $key);

    if (($expected === '') || !is_string($token) || (preg_match('/^[0-9a-f]{64}$/', $token) !== 1)) {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * The address of a place's lobby screen or kiosk, signed.
 *
 * @param int    $place_id
 * @param string $kind lobby or kiosk
 * @return string '' without a site key
 */
function pg_waiting_screen_url($place_id, $kind)
{
    $token = pg_waiting_screen_token($place_id, $kind, pg_booking_key());

    if ($token === '') {
        return '';
    }

    return URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/queue_' . $kind . '.php?place=' . (int) $place_id . '&k=' . $token;
}

/**
 * The place a screen's request opens: the place by its id when the link's
 * token is this place's for that screen, else null.
 *
 * @param array  $query $_GET or $_POST
 * @param string $kind  lobby or kiosk
 * @return array|null pg_waiting_place_get()
 */
function pg_waiting_screen_place($query, $kind)
{
    if (!pg_waiting_ready() || !is_scalar($query['place'] ?? null) || ((int) $query['place'] <= 0)) {
        return null;
    }

    if (!pg_waiting_screen_token_valid($query['k'] ?? null, (int) $query['place'], $kind, pg_booking_key())) {
        return null;
    }

    return pg_waiting_place_get((int) $query['place']);
}

/**
 * The lobby board from today's calls: the numbers now at a desk (state
 * called, the newest per desk, newest first) and the calls before them, at
 * most PG_WAITING_LOBBY_PAST. A number and a desk's name only: the board
 * never carries a person's name. The stamp changes with each call and
 * "Call again", and the screen chimes when it does. Pure.
 *
 * @param array $calls rows with id, letter, number, state, desk_id, called_at; numbers still waiting are left out
 * @param array $desk_names desk id => name
 * @return array now (list of number, desk), past (the same), stamp
 */
function pg_waiting_lobby_board($calls, $desk_names)
{
    $calls = array_values(array_filter((array) $calls, function ($row) {
        return ((int) ($row['called_at'] ?? 0) > 0) && (($row['state'] ?? '') !== 'waiting');
    }));

    usort($calls, function ($a, $b) {
        return array((int) $b['called_at'], (int) $b['id']) <=> array((int) $a['called_at'], (int) $a['id']);
    });

    $now = array();
    $past = array();
    $desks_shown = array();

    foreach ($calls as $row) {
        $item = array(
            'number' => pg_waiting_number($row['letter'], $row['number']),
            'desk'   => (string) ($desk_names[(int) $row['desk_id']] ?? ''),
        );
        $desk = (int) $row['desk_id'];

        if (($row['state'] === 'called') && !isset($desks_shown[$desk])) {
            $desks_shown[$desk] = true;
            $now[] = $item;
        } elseif (count($past) < PG_WAITING_LOBBY_PAST) {
            $past[] = $item;
        }
    }

    return array(
        'now'   => $now,
        'past'  => $past,
        'stamp' => $calls ? (int) $calls[0]['called_at'] . '-' . (int) $calls[0]['id'] : '',
    );
}

/**
 * The lobby board of a place now: today's calls and its desks' names.
 *
 * @param array    $place pg_waiting_place_get()
 * @param int|null $now
 * @return array pg_waiting_lobby_board() plus place and open (whether the queue is open)
 */
function pg_waiting_lobby_read($place, $now = null)
{
    $calls = (array) db_items("SELECT id, letter, number, state, desk_id, called_at FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND day = '" . pg_waiting_today($now) . "' AND called_at > 0 AND state <> 'waiting' ORDER BY called_at DESC, id DESC LIMIT 40");

    return array(
        'place' => (string) $place['name'],
        'open'  => ($place['status'] === 'open'),
    ) + pg_waiting_lobby_board($calls, array_column(pg_waiting_desks($place['id']), 'name', 'id'));
}

/**
 * Each service's line on the kiosk's card: how many wait for it, with the
 * estimate once there is one. Pure.
 *
 * @param int      $waiting the numbers waiting for the service
 * @param int|null $minutes pg_waiting_estimate_minutes(), null without one
 * @return string
 */
function pg_waiting_kiosk_line($waiting, $minutes)
{
    $waiting = max(0, (int) $waiting);

    if ($waiting === 0) {
        return lang('Nobody waiting');
    }

    $line = lang(array('string' => '{var:1} waiting', 'vars' => array($waiting)));

    return ($minutes === null) ? $line : $line . ' · ' . lang(array('string' => 'about {var:1} min', 'vars' => array((int) $minutes)));
}

/**
 * How many wait for each service of a place today. Pure.
 *
 * @param array $waiting pg_waiting_place_waiting() rows
 * @return array service key => count
 */
function pg_waiting_count_by_service($waiting)
{
    $counts = array();

    foreach ((array) $waiting as $row) {
        $key = (string) ($row['service'] ?? '');
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    return $counts;
}
