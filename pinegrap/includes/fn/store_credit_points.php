<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reward points turned into store credit (2026.11.0).
 *
 * Two settings, each a config_text row so the config row keeps its room:
 * credit_points_per_unit (how many points make one unit of money, '' = off)
 * and credit_points_smallest (the smallest credit a conversion may give, in
 * minor units). A customer converts from My Account: the points leave
 * user.user_reward_points in one guarded UPDATE, then a card of kind
 * 'points' is issued through pg_credit_issue(). The reward goal is not
 * touched: it still fires on the order that crosses it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Highest points-per-unit rate the setting keeps. */
define('PG_CREDIT_POINTS_RATE_MAX', 1000000);

/** Most points one conversion takes (user.user_reward_points is INT UNSIGNED). */
define('PG_CREDIT_POINTS_MAX', 4294967295);

/**
 * The points-per-unit rate typed in Site Settings, as it is stored: a plain
 * number with a dot ("10", "2.5", at most 4 decimals), '' for empty, zero or
 * not a number (conversion off). Read as pg_parse_amount() reads amounts, so
 * "1.000" on a Turkish panel is a thousand.
 *
 * @param mixed $raw
 * @return string
 */
function pg_credit_points_parse_rate($raw)
{
    if (!is_scalar($raw) || (trim((string) $raw) === '')) {
        return '';
    }

    $rate = round(pg_parse_amount($raw), 4);

    if ($rate <= 0) {
        return '';
    }

    $rate = min((float) PG_CREDIT_POINTS_RATE_MAX, $rate);

    return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');
}

/**
 * The smallest conversion typed in Site Settings (an amount of money) in
 * minor units, rounded with round(); 0 for empty, negative or not a number.
 *
 * @param mixed $raw
 * @return int
 */
function pg_credit_points_parse_smallest($raw)
{
    if (!is_scalar($raw)) {
        return 0;
    }

    $minor = round(pg_parse_amount($raw) * 100);

    if ($minor <= 0) {
        return 0;
    }

    return ($minor >= 4294967295) ? 4294967295 : (int) $minor;
}

/**
 * The rate as the settings screen shows it, with the panel's decimal mark.
 *
 * @param string $stored  pg_credit_points_parse_rate()'s answer
 * @return string
 */
function pg_credit_points_rate_for_input($stored)
{
    $stored = (string) $stored;

    if (!is_numeric($stored)) {
        return '';
    }

    $s = pg_number_separators();

    return str_replace('.', $s['decimal'], $stored);
}

/**
 * The conversion settings. Points are only earned while the reward program
 * is on, so with it off there is nothing to convert either.
 *
 * @return array  rate (float, 0 = off), smallest (int, minor units)
 */
function pg_credit_points_settings()
{
    $rate = (defined('ECOMMERCE_REWARD_PROGRAM') && ECOMMERCE_REWARD_PROGRAM) ? pg_config_text('credit_points_per_unit') : '';
    $smallest = pg_config_text('credit_points_smallest');

    return array(
        'rate'     => is_numeric($rate) ? max(0.0, (float) $rate) : 0.0,
        'smallest' => ctype_digit($smallest) ? (int) $smallest : 0,
    );
}

/**
 * What converting $points gives and how many of them it spends. The value is
 * the whole minor units the points are fully worth (never rounded up), and
 * only the points that value needs are spent; the rest stay with the
 * customer. Rounding half up here would let a customer split a conversion
 * into small pieces that each round up and get more than the points are
 * worth. Under the smallest conversion (or one minor unit) nothing is
 * converted.
 *
 * @param int       $points
 * @param int|float $rate      points per unit of money
 * @param int       $smallest  minor units
 * @return array  value (minor units), points (spent)
 */
function pg_credit_points_take($points, $rate, $smallest = 0)
{
    $points = (int) $points;
    // The rate is stored with at most four decimals
    // (pg_credit_points_parse_rate()), so counted in ten-thousandths it is a
    // whole number and the division below is exact. Float division puts a
    // large value (61033 points at 0.28) a minor unit off.
    $rate = (int) round((float) $rate * 10000);
    $none = array('value' => 0, 'points' => 0);

    if (($points <= 0) || ($rate <= 0)) {
        return $none;
    }

    $value = intdiv($points * 1000000, $rate);

    if ($value < max(1, (int) $smallest)) {
        return $none;
    }

    $spent = intdiv($value * $rate + 999999, 1000000);

    return array('value' => $value, 'points' => max(1, min($points, $spent)));
}

/**
 * Why a conversion of $points out of $balance is refused, '' when it may go
 * ahead.
 *
 * @param mixed     $points    the points the customer asked to convert
 * @param int       $balance   user.user_reward_points
 * @param int|float $rate      points per unit, 0 = conversion off
 * @param int       $smallest  minor units
 * @return string  '', 'off', 'points', 'balance' or 'small'
 */
function pg_credit_points_check($points, $balance, $rate, $smallest)
{
    if ((float) $rate <= 0) {
        return 'off';
    }

    if (is_int($points)) {
        $points = (string) $points;
    }

    if (!is_string($points) || !ctype_digit($points) || ((int) $points <= 0) || ((float) $points > PG_CREDIT_POINTS_MAX)) {
        return 'points';
    }

    if ((int) $points > max(0, (int) $balance)) {
        return 'balance';
    }

    if (pg_credit_points_take((int) $points, $rate, $smallest)['value'] <= 0) {
        return 'small';
    }

    return '';
}

/**
 * The fewest points that give the smallest conversion (and at least one
 * minor unit), for the sentence under the button.
 *
 * @param int|float $rate
 * @param int       $smallest  minor units
 * @return int  0 when conversion is off
 */
function pg_credit_points_minimum($rate, $smallest)
{
    $rate = (float) $rate;

    if ($rate <= 0) {
        return 0;
    }

    $target = max(1, (int) $smallest);
    $points = max(1, (int) ceil(($target * $rate / 100) - 1e-9));

    while (pg_credit_points_take($points, $rate, $smallest)['value'] < $target) {
        $points++;
    }

    while (($points > 1) && (pg_credit_points_take($points - 1, $rate, $smallest)['value'] >= $target)) {
        $points--;
    }

    return $points;
}

/**
 * What a customer sees about converting on My Account.
 *
 * @param int $balance  user.user_reward_points
 * @return array  enabled (bool), points (all of them), value (minor units of
 *                all of them, 0 under the smallest), minimum (points)
 */
function pg_credit_points_offer($balance)
{
    $settings = pg_credit_points_settings();
    $balance = max(0, (int) $balance);
    $enabled = ($settings['rate'] > 0) && pg_credit_ready();

    return array(
        'enabled' => $enabled,
        'points'  => $balance,
        'value'   => $enabled ? pg_credit_points_take($balance, $settings['rate'], $settings['smallest'])['value'] : 0,
        'minimum' => $enabled ? pg_credit_points_minimum($settings['rate'], $settings['smallest']) : 0,
    );
}

/**
 * Convert a customer's reward points into store credit. The points leave the
 * user row in one UPDATE guarded by the balance, so two requests at once can
 * never spend the same points; when the card cannot be written the points
 * are put back.
 *
 * @param int   $user_id
 * @param mixed $points
 * @return array  error ('' on success, a pg_credit_points_check() code, or
 *                'busy' when the guard missed, 'issue' when no card was
 *                written), card_id, value (minor units), points
 */
function pg_credit_points_convert($user_id, $points)
{
    $user_id = (int) $user_id;
    $answer = array('error' => 'off', 'card_id' => 0, 'value' => 0, 'points' => 0);

    if (($user_id <= 0) || !pg_credit_ready()) {
        return $answer;
    }

    $settings = pg_credit_points_settings();
    $balance = (int) db_value("SELECT user_reward_points FROM user WHERE user_id = " . $user_id);
    $error = pg_credit_points_check($points, $balance, $settings['rate'], $settings['smallest']);

    if ($error !== '') {
        $answer['error'] = $error;
        return $answer;
    }

    $take = pg_credit_points_take((int) $points, $settings['rate'], $settings['smallest']);
    $points = $take['points'];
    $value = $take['value'];

    $spent = db_q(
        "UPDATE user SET user_reward_points = user_reward_points - ? WHERE user_id = ? AND user_reward_points >= ?",
        array($points, $user_id, $points));

    if (($spent === false) || (mysqli_affected_rows(db::$con) !== 1)) {
        $answer['error'] = 'busy';
        return $answer;
    }

    $card_id = pg_credit_issue(
        $user_id,
        $value,
        'points',
        lang(array('string' => '{var:1} reward points converted', 'vars' => array(pg_format_number($points, 0)))),
        0,
        0);

    if ($card_id <= 0) {
        db_q("UPDATE user SET user_reward_points = user_reward_points + ? WHERE user_id = ?", array($points, $user_id));
        $answer['error'] = 'issue';
        return $answer;
    }

    return array('error' => '', 'card_id' => $card_id, 'value' => $value, 'points' => $points);
}

/**
 * The sentence for a refused conversion.
 *
 * @param string $error  a pg_credit_points_convert() code
 * @return string
 */
function pg_credit_points_error_text($error)
{
    switch ($error) {
        case 'points':
            return lang('Enter a whole number of points.');
        case 'balance':
            return lang('You do not have that many reward points.');
        case 'small':
            return lang('That many points is under the smallest conversion.');
        case 'busy':
            return lang('Your reward points changed meanwhile. Please try again.');
        case 'issue':
            return lang('The store credit could not be created. Your points were not spent.');
    }

    return lang('Reward points cannot be converted to store credit.');
}

/**
 * The reward points column for the user screen's UPDATE, with a comma after
 * it, or '' to leave the balance alone. The screen posts the balance it was
 * opened with beside the field; the balance is written only when staff
 * changed the field, so a save of a record opened before a conversion or an
 * order does not put back the points they moved.
 *
 * @param mixed $posted    the reward_points field
 * @param mixed $original  the reward_points_original field (null on an older form)
 * @return string
 */
function pg_credit_points_staff_sql($posted, $original)
{
    if (!is_scalar($posted)) {
        return '';
    }

    $posted = trim((string) $posted);

    if (is_scalar($original) && (trim((string) $original) === $posted)) {
        return '';
    }

    $points = ctype_digit($posted) ? min((float) $posted, (float) PG_CREDIT_POINTS_MAX) : 0;

    return "user_reward_points = " . number_format($points, 0, '.', '') . ",";
}

/**
 * The reward points of an order's lines, recomputed with the function the
 * order's completion granted them with (pg_order_line_reward_points()), at
 * the products' points of today. With $quantities (order item id =>
 * quantity) only those lines count, each with at most its own quantity.
 * Pure.
 *
 * @param array      $lines       order_item_id, reward_points, quantity
 * @param array|null $quantities  null for every line with its own quantity
 * @param bool       $program_on  ECOMMERCE_REWARD_PROGRAM
 * @return int
 */
function pg_credit_points_of_lines(array $lines, $quantities, $program_on)
{
    $points = 0;

    foreach ($lines as $line) {
        $quantity = (int) $line['quantity'];

        if (is_array($quantities)) {
            $id = (int) $line['order_item_id'];
            $quantity = isset($quantities[$id]) ? min($quantity, max(0, (int) $quantities[$id])) : 0;
        }

        if ($quantity > 0) {
            $points += pg_order_line_reward_points(array('reward_points' => $line['reward_points'], 'quantity' => $quantity), $program_on);
        }
    }

    return max(0, $points);
}

/**
 * The note an order screen refund by lines leaves on its order_refunds row
 * when it took reward points back, so the cancellation that may follow does
 * not take them a second time. Pure.
 *
 * @param int $points
 * @return string  '' for none
 */
function pg_credit_points_marker($points)
{
    return ((int) $points > 0) ? 'Reward points taken back: ' . (int) $points : '';
}

/**
 * The reward points the markers in these order_refunds notes took back. Pure.
 *
 * @param array $notes
 * @return int
 */
function pg_credit_points_marked(array $notes)
{
    $points = 0;

    foreach ($notes as $note) {
        if (preg_match_all('/Reward points taken back: (\d+)/', (string) $note, $found)) {
            foreach ($found[1] as $number) {
                $points += (int) $number;
            }
        }
    }

    return $points;
}

/**
 * How a take-back of $points is covered: first from the points balance,
 * never below zero; the points the customer no longer has were converted, so
 * their worth at today's rate comes off the store credit, rounded up to the
 * minor unit (a conversion rounds down, so the customer never keeps more
 * than the points were worth). With no rate nothing can be valued. Pure.
 *
 * @param int       $balance  user.user_reward_points
 * @param int       $points   the points to take back
 * @param int|float $rate     points per unit of money, 0 = off
 * @return array  points (off the balance), short (points not there), value
 *                (minor units off the credit)
 */
function pg_credit_points_take_back_plan($balance, $points, $rate)
{
    $points = max(0, (int) $points);
    $from_balance = min(max(0, (int) $balance), $points);
    $short = $points - $from_balance;
    // In ten-thousandths of a point, as pg_credit_points_take() counts, so
    // the rounding up is exact on large values too.
    $rate = (int) round((float) $rate * 10000);
    $value = (($short > 0) && ($rate > 0)) ? intdiv($short * 1000000 + $rate - 1, $rate) : 0;

    return array('points' => $from_balance, 'short' => $short, 'value' => $value);
}

/**
 * Which of a customer's open points cards give $value, newest first: the
 * newest card is reduced or closed, then the one before it. Pure.
 *
 * @param array $cards  id, balance
 * @param int   $value  minor units
 * @return array  card id => minor units to take off
 */
function pg_credit_points_card_cuts(array $cards, $value)
{
    $value = (int) $value;
    $cuts = array();

    usort($cards, function ($a, $b) {
        return (int) $b['id'] - (int) $a['id'];
    });

    foreach ($cards as $card) {
        if ($value <= 0) {
            break;
        }

        $cut = min($value, (int) $card['balance']);

        if ($cut > 0) {
            $cuts[(int) $card['id']] = $cut;
            $value -= $cut;
        }
    }

    return $cuts;
}

/**
 * An order's lines with their products' reward points (the lines saved for
 * later left out, as the completion reads them).
 *
 * @param int $order_id
 * @return array  order_item_id, product_id, reward_points, quantity
 */
function pg_credit_points_order_lines($order_id)
{
    $rows = db_items(
        "SELECT order_items.id AS order_item_id, order_items.product_id, order_items.quantity, COALESCE(products.reward_points, 0) AS reward_points
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_items.order_id = '" . (int) $order_id . "'" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC");

    return is_array($rows) ? $rows : array();
}

/**
 * The quantities of an order's lines its refunded returns gave back (a
 * return closed after its refund included), and the points the order
 * screen's refunds by lines already took back.
 *
 * @param int $order_id
 * @return array  returned (order item id => quantity), marked (points)
 */
function pg_credit_points_order_refunded($order_id)
{
    $order_id = (int) $order_id;
    $returned = array();

    if (pg_returns_ready()) {
        $rows = db_items(
            "SELECT order_return_items.order_item_id, SUM(order_return_items.quantity) AS quantity
            FROM order_return_items
            JOIN order_returns ON order_returns.id = order_return_items.return_id
            WHERE order_returns.order_id = '" . $order_id . "' AND order_returns.refunded_at > 0
            GROUP BY order_return_items.order_item_id");

        foreach ((array) $rows as $row) {
            $returned[(int) $row['order_item_id']] = (int) $row['quantity'];
        }
    }

    $notes = pg_schema_has('order_refunds', 'notes')
        ? db_values("SELECT notes FROM order_refunds WHERE order_id = '" . $order_id . "' AND notes LIKE 'Reward points taken back:%'")
        : array();

    return array('returned' => $returned, 'marked' => pg_credit_points_marked(is_array($notes) ? $notes : array()));
}

/**
 * Take an order's reward points back from its customer: off the points
 * balance (one guarded UPDATE, never below zero), and for the points no
 * longer there because they were converted, their worth off the newest
 * points cards through pg_credit_reduce(), each with a note naming the
 * order. One activity line says what was taken.
 *
 * @param int    $user_id
 * @param int    $points
 * @param string $order_number  as the log names it (pg_order_log_number())
 * @return array  points (off the balance), value (minor units off the credit),
 *                total (the points taken back, $points once written)
 */
function pg_credit_points_take_back($user_id, $points, $order_number)
{
    $user_id = (int) $user_id;
    $points = (int) $points;
    $taken = array('points' => 0, 'value' => 0, 'total' => 0);

    if (($user_id <= 0) || ($points <= 0)) {
        return $taken;
    }

    $settings = pg_credit_points_settings();
    $plan = null;

    // The balance may move between the read and the write (a conversion, an
    // order); the guarded UPDATE then misses and the plan is made again.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $balance = db_value("SELECT user_reward_points FROM user WHERE user_id = " . $user_id);

        if ($balance === null || $balance === false) {
            return $taken;
        }

        $plan = pg_credit_points_take_back_plan((int) $balance, $points, $settings['rate']);

        if ($plan['points'] === 0) {
            break;
        }

        $written = db_q(
            "UPDATE user SET user_reward_points = user_reward_points - ? WHERE user_id = ? AND user_reward_points >= ?",
            array($plan['points'], $user_id, $plan['points']));

        if ($written && (mysqli_affected_rows(db::$con) === 1)) {
            break;
        }

        $plan = null;
    }

    if ($plan === null) {
        return $taken;
    }

    $taken['points'] = $plan['points'];
    $taken['total'] = $points;

    if (($plan['value'] > 0) && pg_credit_ready()) {
        $cards = db_items(
            "SELECT id, balance FROM gift_cards
            WHERE " . pg_credit_open_sql($user_id, time()) . " AND kind = 'points'
            ORDER BY id DESC");

        $note = lang(array(
            'string' => '{var:1} taken off on {var:2}: the reward points of order #{var:3} were taken back after they had been converted.',
            'vars'   => array(pg_order_log_money($plan['value']), date('Y-m-d'), $order_number)));

        foreach (pg_credit_points_card_cuts(is_array($cards) ? $cards : array(), $plan['value']) as $card_id => $cut) {
            $taken['value'] += pg_credit_reduce($card_id, $cut, $note);
        }
    }

    if (($taken['points'] > 0) || ($plan['short'] > 0)) {
        log_activity(pg_log_lang(array(
            'string' => 'Reward points of order #{var:1} taken back: {var:2} points off the balance, {var:3} off the store credit.',
            'vars'   => array($order_number, $taken['points'], pg_order_log_money($taken['value'])))));
    }

    return $taken;
}

/**
 * A cancellation takes back the reward points the order gave, less the
 * lines its refunded returns and the order screen's refunds by lines took
 * back already. A cart (never completed) and a guest order gave none.
 *
 * @param array $order  id, user_id, status (before the cancellation),
 *                      order_number
 * @return array  pg_credit_points_take_back()'s
 */
function pg_credit_points_order_cancelled(array $order)
{
    if ((ECOMMERCE_REWARD_PROGRAM != true) || ((int) ($order['user_id'] ?? 0) <= 0) || ((string) ($order['status'] ?? '') === 'incomplete')) {
        return array('points' => 0, 'value' => 0, 'total' => 0);
    }

    $lines = pg_credit_points_order_lines($order['id']);
    $refunded = pg_credit_points_order_refunded($order['id']);
    $points = pg_credit_points_of_lines($lines, null, true)
        - pg_credit_points_of_lines($lines, $refunded['returned'], true)
        - $refunded['marked'];

    return pg_credit_points_take_back($order['user_id'], max(0, $points), pg_order_log_number($order));
}

/**
 * A refund of some of an order's lines (a return, the order screen's refund
 * by lines) takes back those lines' reward points. A cancelled order's
 * points went back with the cancellation.
 *
 * @param int   $order_id
 * @param array $quantities  order item id => quantity refunded
 * @return array  pg_credit_points_take_back()'s
 */
function pg_credit_points_lines_refunded($order_id, array $quantities)
{
    $none = array('points' => 0, 'value' => 0, 'total' => 0);

    if (ECOMMERCE_REWARD_PROGRAM != true) {
        return $none;
    }

    $order = db_item("SELECT id, user_id, status, order_number FROM orders WHERE id = '" . (int) $order_id . "'");

    if (!is_array($order) || ((int) $order['user_id'] <= 0) || in_array((string) $order['status'], array('incomplete', 'cancelled'), true)) {
        return $none;
    }

    $points = pg_credit_points_of_lines(pg_credit_points_order_lines($order['id']), $quantities, true);

    return pg_credit_points_take_back($order['user_id'], $points, pg_order_log_number($order));
}

/**
 * The order lines and quantities an order screen refund by lines chose: the
 * ERP refund lines (erp_order_refund_lines()) mapped back to the order's
 * lines as a return maps them (pg_return_erp_line_map()). Empty when they do
 * not line up.
 *
 * @param int   $order_id
 * @param array $source      erp_order_refund_lines()
 * @param array $quantities  refund line id => quantity (erp_order_refund_amount())
 * @return array  order item id => quantity
 */
function pg_credit_points_erp_quantities($order_id, $source, array $quantities)
{
    $items = array();

    foreach (pg_credit_points_order_lines($order_id) as $line) {
        $items[(int) $line['order_item_id']] = (int) $line['product_id'];
    }

    $map = pg_return_erp_line_map($items, $source);
    $chosen = array();

    foreach ((array) $map as $item_id => $line_id) {
        if (isset($quantities[$line_id]) && ((int) $quantities[$line_id] > 0)) {
            $chosen[(int) $item_id] = (int) $quantities[$line_id];
        }
    }

    return $chosen;
}
