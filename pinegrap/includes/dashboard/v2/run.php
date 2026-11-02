<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's card loader behind api.php's dashboard_cards action:
 * the data of the listed cards in one answer. Each card lives in
 * cards/<id>.php and defines
 *
 *     function pg_dashboard_v2_card_<id>($user, $period, $compare)
 *
 * returning array('status' => 'success'|'denied'|'error', 'data' => array(...)):
 * figures, rows, points and labels, never HTML, texts already through
 * lang(). A card checks its own gate and never echoes or exits; this loader
 * also answers denied for a card the person may not see, so a request for
 * one loads nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');

/**
 * At most this many cards in one request: every card of a layout fits.
 */
if (!defined('PG_DASHBOARD_V2_MAX_BATCH')) {
    define('PG_DASHBOARD_V2_MAX_BATCH', 40);
}

/**
 * The requested cards, cleaned: known ids only, each once, period and
 * compare held to 0-2 (a card without a period takes 0).
 *
 * @param mixed $asked the request's cards list
 * @return array id => array(per, cmp)
 */
function pg_dashboard_v2_cards_asked($asked)
{
    $cards = pg_dashboard_v2_cards();
    $out = array();

    if (!is_array($asked)) {
        return $out;
    }

    foreach ($asked as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id']) || !isset($cards[$entry['id']])) {
            continue;
        }

        $id = $entry['id'];

        if (isset($out[$id])) {
            continue;
        }

        $per = isset($entry['per']) ? (int) $entry['per'] : (int) $cards[$id]['period'];
        $cmp = isset($entry['cmp']) ? (int) $entry['cmp'] : 0;

        $out[$id] = array(
            'per' => ($cards[$id]['period'] === null) ? 0 : min(2, max(0, $per)),
            'cmp' => min(2, max(0, $cmp)),
        );

        if (count($out) >= PG_DASHBOARD_V2_MAX_BATCH) {
            break;
        }
    }

    return $out;
}

/**
 * One card's answer.
 *
 * @param string $id
 * @param array  $user
 * @param int    $per
 * @param int    $cmp
 * @return array status, data
 */
function pg_dashboard_v2_card_run($id, $user, $per, $cmp)
{
    $cards = pg_dashboard_v2_cards();

    if (!isset($cards[$id]) || !$cards[$id]['built'] || !pg_dashboard_v2_gate($cards[$id]['gate'], $user)) {
        return array('status' => 'denied', 'data' => array());
    }

    $file = PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $id . '.php';
    $function = 'pg_dashboard_v2_card_' . $id;

    if (!is_file($file)) {
        return array('status' => 'error', 'data' => array());
    }

    require_once($file);

    if (!function_exists($function)) {
        return array('status' => 'error', 'data' => array());
    }

    // A card that fails must not take the others in the answer with it.
    try {
        $answer = $function($user, (int) $per, (int) $cmp);
    } catch (Throwable $e) {
        return array('status' => 'error', 'data' => array());
    }

    if (!is_array($answer) || !isset($answer['status']) || !in_array($answer['status'], array('success', 'denied', 'error'), true)) {
        return array('status' => 'error', 'data' => array());
    }

    return array('status' => $answer['status'], 'data' => (isset($answer['data']) && is_array($answer['data'])) ? $answer['data'] : array());
}

/**
 * The answer to dashboard_cards.
 *
 * @param array $request the decoded API request
 * @param array $user
 * @return array status, cards (id => status, data)
 */
function pg_dashboard_v2_cards_run($request, $user)
{
    $out = array();

    foreach (pg_dashboard_v2_cards_asked($request['cards'] ?? null) as $id => $asked) {
        $out[$id] = pg_dashboard_v2_card_run($id, $user, $asked['per'], $asked['cmp']);
    }

    return array('status' => 'success', 'cards' => $out);
}

/**
 * The change from one figure to the one it is compared with, as a whole
 * percentage and a tone; null when there is nothing to compare with.
 *
 * @param int|float $now
 * @param int|float $before
 * @return array|null pct (signed int), tone (good, bad or ''), text
 */
function pg_dashboard_v2_change($now, $before)
{
    if ((float) $before <= 0) {
        return null;
    }

    $pct = (int) round((((float) $now - (float) $before) / (float) $before) * 100);

    if ($pct > 0) {
        return array('pct' => $pct, 'tone' => 'good', 'text' => lang(array('string' => 'up {var:1}%', 'vars' => pg_format_number($pct, 0))));
    }

    if ($pct < 0) {
        return array('pct' => $pct, 'tone' => 'bad', 'text' => lang(array('string' => 'down {var:1}%', 'vars' => pg_format_number(-$pct, 0))));
    }

    return array('pct' => 0, 'tone' => '', 'text' => lang('no change'));
}
