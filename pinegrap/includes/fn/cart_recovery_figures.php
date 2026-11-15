<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Recovered carts figures, read from cart_recovery_touch (written by
 * includes/fn/cart_recovery.php). A touched cart belongs to the period of its
 * first touch; for it the figures say whether it was reached, returned
 * (a touch clicked or restored) and recovered, with the recovered total, in
 * all, by channel and by message. Left = the touched carts plus the left
 * carts no message touched. pg_cart_recovery_figures() is the pure part;
 * pg_cart_recovery_period() reads the rows for a period.
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
 * The channels in the order the figures list them.
 *
 * @return string[]
 */
function pg_cart_recovery_channels()
{
    return array('email', 'whatsapp', 'sms', 'page');
}

/**
 * A share in percent with one decimal; null when the base is 0. Pure.
 *
 * @param int $part
 * @param int $base
 * @return float|null
 */
function pg_cart_recovery_rate($part, $base)
{
    $base = (int) $base;

    if ($base < 1) {
        return null;
    }

    return round((int) $part / $base * 100, 1);
}

/**
 * One touch of a cart marked on that cart's figures for a channel or a
 * message: reached once, returned and recovered once, the recovered total
 * added. Pure.
 *
 * @param array  $seen      name => figures of this cart
 * @param string $name
 * @param bool   $returned
 * @param bool   $recovered
 * @param int    $total
 * @return array
 */
function pg_cart_recovery_mark(array $seen, $name, $returned, $recovered, $total)
{
    if (!isset($seen[$name])) {
        $seen[$name] = array('reached' => 1, 'returned' => 0, 'recovered' => 0, 'revenue' => 0);
    }

    if ($returned) {
        $seen[$name]['returned'] = 1;
    }

    if ($recovered) {
        $seen[$name]['recovered'] = 1;
        $seen[$name]['revenue'] += (int) $total;
    }

    return $seen;
}

/**
 * The figures of a period, pure.
 *
 * @param array $touches   touch rows (order_id, channel, source_type, source_id,
 *                         sent_at, clicked_at, restored_at, recovered,
 *                         completed_total); rows of carts whose first touch
 *                         is outside [from, to) and channel '' rows are left out
 * @param int   $from      unix time, included
 * @param int   $to        unix time, excluded
 * @param int   $untouched left carts of the period no message touched
 * @return array{left: int, untouched: int, reached: int, returned: int,
 *               recovered: int, revenue: int, channels: array, messages: array}
 */
function pg_cart_recovery_figures(array $touches, $from, $to, $untouched)
{
    $from = (int) $from;
    $to = (int) $to;

    $carts = array();

    foreach ($touches as $touch) {
        if (((string) $touch['channel'] === '') || ((int) $touch['sent_at'] < 1)) {
            continue;
        }

        $carts[(int) $touch['order_id']][] = $touch;
    }

    $empty = array('reached' => 0, 'returned' => 0, 'recovered' => 0, 'revenue' => 0);

    $out = array(
        'left'      => 0,
        'untouched' => max(0, (int) $untouched),
        'reached'   => 0,
        'returned'  => 0,
        'recovered' => 0,
        'revenue'   => 0,
        'channels'  => array(),
        'messages'  => array(),
    );

    $channels = array();

    foreach ($carts as $rows) {
        $first = null;

        foreach ($rows as $row) {
            $first = ($first === null) ? (int) $row['sent_at'] : min($first, (int) $row['sent_at']);
        }

        if (($first < $from) || ($first >= $to)) {
            continue;
        }

        $out['reached']++;

        // What this cart did, per channel and per message, counted once each.
        $cart_returned = false;
        $by_channel = array();
        $by_message = array();

        foreach ($rows as $row) {
            $channel = (string) $row['channel'];
            $key = (string) $row['source_type'] . ':' . (int) $row['source_id'];
            $returned = ((int) $row['clicked_at'] > 0) || ((int) $row['restored_at'] > 0);
            $recovered = ((int) $row['recovered'] === 1);
            $total = $recovered ? (int) $row['completed_total'] : 0;

            if (!isset($out['messages'][$key])) {
                $out['messages'][$key] = array(
                    'source_type' => (string) $row['source_type'],
                    'source_id'   => (int) $row['source_id'],
                    'channel'     => $channel,
                    'sent'        => 0,
                ) + $empty;
            }

            $out['messages'][$key]['sent']++;

            $by_channel = pg_cart_recovery_mark($by_channel, $channel, $returned, $recovered, $total);
            $by_message = pg_cart_recovery_mark($by_message, $key, $returned, $recovered, $total);

            if ($returned) {
                $cart_returned = true;
            }

            if ($recovered) {
                $out['recovered']++;
                $out['revenue'] += $total;
            }
        }

        if ($cart_returned) {
            $out['returned']++;
        }

        foreach ($by_channel as $channel => $figures) {
            if (!isset($channels[$channel])) {
                $channels[$channel] = $empty;
            }

            foreach ($figures as $name => $value) {
                $channels[$channel][$name] += $value;
            }
        }

        foreach ($by_message as $key => $figures) {
            foreach ($figures as $name => $value) {
                $out['messages'][$key][$name] += $value;
            }
        }
    }

    foreach (pg_cart_recovery_channels() as $channel) {
        if (isset($channels[$channel])) {
            $out['channels'][$channel] = $channels[$channel];
        }
    }

    ksort($out['messages'], SORT_NATURAL);

    $out['left'] = $out['reached'] + $out['untouched'];

    return $out;
}
