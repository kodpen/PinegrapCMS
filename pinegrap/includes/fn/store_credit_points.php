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

    if (pg_credit_points_value((int) $points, $rate, $smallest) <= 0) {
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

    // round() rounds half up, so half a minor unit short of the target is
    // already enough; step up from the estimate until the value is reached.
    $target = max(1, (int) $smallest);
    $points = max(1, (int) floor(($target - 0.5) * $rate / 100));

    while (pg_credit_points_value($points, $rate, $smallest) < $target) {
        $points++;
    }

    while (($points > 1) && (pg_credit_points_value($points - 1, $rate, $smallest) >= $target)) {
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
        'value'   => $enabled ? pg_credit_points_value($balance, $settings['rate'], $settings['smallest']) : 0,
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

    $points = (int) $points;
    $value = pg_credit_points_value($points, $settings['rate'], $settings['smallest']);

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
