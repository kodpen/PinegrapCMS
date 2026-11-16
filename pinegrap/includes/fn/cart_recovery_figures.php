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

/**
 * Left carts of [from, to) no message touched: the carts completed in the
 * period with no touch (their channel '' row) and the carts still open that
 * were started in the period, are at least 6 hours old, hold a line and have
 * no touch.
 *
 * @param int $from
 * @param int $to
 * @param int $now
 * @return int
 */
function pg_cart_recovery_untouched($from, $to, $now)
{
    $completed = (int) pg_cart_recovery_first(
        "SELECT COUNT(*) FROM cart_recovery_touch
        WHERE (channel = '') AND (completed_at >= ?) AND (completed_at < ?)",
        array((string) (int) $from, (string) (int) $to));

    $until = min((int) $to, (int) $now - PG_CART_RECOVERY_LEFT_AFTER + 1);

    if ($until <= (int) $from) {
        return $completed;
    }

    $open = (int) pg_cart_recovery_first(
        "SELECT COUNT(*) FROM orders
        WHERE
            (orders.status = 'incomplete')
            AND (orders.order_date >= ?)
            AND (orders.order_date < ?)
            AND NOT EXISTS (
                SELECT 1 FROM cart_recovery_touch
                WHERE cart_recovery_touch.order_id = orders.id)
            AND EXISTS (
                SELECT 1 FROM order_items
                WHERE (order_items.order_id = orders.id)" . pg_order_item_active_sql('order_items') . ")",
        array((string) (int) $from, (string) $until));

    return $completed + $open;
}

/**
 * The touch rows of every cart with a touch sent in [from, to), each with all
 * its touches, so pg_cart_recovery_figures() can find the cart's first one.
 *
 * @param int $from
 * @param int $to
 * @return array
 */
function pg_cart_recovery_touch_rows($from, $to)
{
    $result = db_q(
        "SELECT order_id, channel, source_type, source_id, sent_at, clicked_at, restored_at, recovered, completed_total
        FROM cart_recovery_touch
        WHERE
            (channel <> '')
            AND order_id IN (
                SELECT order_id FROM cart_recovery_touch
                WHERE (channel <> '') AND (sent_at >= ?) AND (sent_at < ?))",
        array((string) (int) $from, (string) (int) $to));

    $rows = array();

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * A readable name for each message: an e-mail campaign's subject, else the
 * kind and its number. Messages whose source is gone keep the number.
 *
 * @param array $messages pg_cart_recovery_figures()['messages']
 * @return array the same, each with 'label'
 */
function pg_cart_recovery_message_labels(array $messages)
{
    $campaign_ids = array();

    foreach ($messages as $message) {
        if (($message['source_type'] === 'campaign') && ($message['source_id'] > 0)) {
            $campaign_ids[] = (int) $message['source_id'];
        }
    }

    $subjects = array();

    if ($campaign_ids) {
        $result = db_q(
            "SELECT id, subject FROM email_campaigns WHERE id IN (" . implode(',', array_fill(0, count($campaign_ids), '?')) . ")",
            array_map('strval', $campaign_ids));

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $subjects[(int) $row['id']] = (string) $row['subject'];
            }
        }
    }

    foreach ($messages as $key => $message) {
        $id = (int) $message['source_id'];

        if (($message['source_type'] === 'campaign') && isset($subjects[$id]) && (trim($subjects[$id]) !== '')) {
            $messages[$key]['label'] = $subjects[$id];
        } else {
            $messages[$key]['label'] = pg_cart_recovery_source_label($message['source_type'], $id);
        }
    }

    return $messages;
}

/**
 * The kind of a message and its number, when it has no name. Pure.
 *
 * @param string $source_type
 * @param int    $source_id
 * @return string
 */
function pg_cart_recovery_source_label($source_type, $source_id)
{
    $kinds = array(
        'campaign' => lang('E-mail campaign'),
        'template' => lang('Message template'),
        'popup'    => lang('Pop-up'),
    );

    $kind = isset($kinds[$source_type]) ? $kinds[$source_type] : lang('Message');

    return ((int) $source_id > 0) ? $kind . ' #' . (int) $source_id : $kind;
}

/**
 * The figures of [from, to) read from the database, the messages named;
 * null before the table exists.
 *
 * @param int      $from
 * @param int      $to
 * @param int|null $now unix time; now when null
 * @return array|null pg_cart_recovery_figures()
 */
function pg_cart_recovery_period($from, $to, $now = null)
{
    if (!pg_cart_recovery_ready()) {
        return null;
    }

    $now = ($now === null) ? time() : (int) $now;

    $figures = pg_cart_recovery_figures(
        pg_cart_recovery_touch_rows($from, $to),
        $from,
        $to,
        pg_cart_recovery_untouched($from, $to, $now)
    );

    $figures['messages'] = pg_cart_recovery_message_labels($figures['messages']);

    return $figures;
}
