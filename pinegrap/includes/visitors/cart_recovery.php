<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Recovered carts section of the Visitors overview: carts left in the
 * period, how many a message reached, how many came back and how many it
 * recovered with their revenue, each against the earlier period; the same
 * as a funnel, by channel and by message. The figures are
 * pg_cart_recovery_period() (includes/fn/cart_recovery_figures.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * The section; '' without commerce or before the touch table exists.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_cart_recovery(array $period, array $user)
{
    if (!defined('ECOMMERCE') || (ECOMMERCE != true) || !pg_cart_recovery_ready()) {
        return '';
    }

    $now = pg_cart_recovery_period((int) $period['from_ts'], (int) $period['to_ts']);
    $before = $period['compare']
        ? pg_cart_recovery_period((int) $period['previous_from_ts'], (int) $period['previous_to_ts'])
        : null;

    $sections = pg_visitors_sections();
    $title = $sections['cart_recovery'];

    if ($now['left'] < 1) {
        return pg_visitors_section('cart_recovery', $title, pg_visitors_empty(lang('No cart was left in this period.')));
    }

    $tiles = '';

    foreach (pg_visitors_cart_recovery_tiles($now, $before) as $tile) {
        $tiles .= pg_visitors_tile(
            $tile['label'],
            ($tile['format'] === 'money') ? pg_visitors_cart_recovery_money($tile['value']) : pg_format_number($tile['value'], 0),
            ($before !== null) ? pg_visitors_change($tile['value'], $tile['before']) : '',
            $tile['note']
        );
    }

    $body = '<div class="pg-visitors-tiles">' . $tiles . '</div>';

    $body .= '<div class="pg-visitors-grid is-split">';

    $body .= pg_visitors_block(
        lang('From left to recovered'),
        pg_visitors_bars(pg_visitors_cart_recovery_funnel($now), array('label' => lang('From left to recovered'))),
        'is-half'
    );

    $body .= pg_visitors_block(
        lang('By channel'),
        pg_visitors_table(
            pg_visitors_cart_recovery_headings(lang('Channel')),
            pg_visitors_cart_recovery_channel_rows($now['channels']),
            array('caption' => lang('Recovered carts by channel'), 'hide_first' => true)
        ),
        'is-half'
    );

    $body .= pg_visitors_block(
        lang('By message'),
        pg_visitors_table(
            array_merge(
                array(lang('Message'), lang('Channel'), array('label' => lang('Sent'), 'end' => true)),
                array_slice(pg_visitors_cart_recovery_headings(''), 1)
            ),
            pg_visitors_cart_recovery_message_rows($now['messages']),
            array('caption' => lang('Recovered carts by message'), 'hide_first' => true, 'limit' => PG_VISITORS_LIST_ROWS)
        )
    );

    $body .= '</div>';

    $body .= '<p class="pg-visitors-note">' . h(lang('A cart is left 6 hours after it was started. It counts as recovered when it is completed within 7 days of the last message it got.')) . '</p>';

    return pg_visitors_section('cart_recovery', $title, $body);
}

/**
 * Money in minor units as the base currency's text.
 *
 * @param int $minor_units
 * @return string
 */
function pg_visitors_cart_recovery_money($minor_units)
{
    return pg_money_text((int) $minor_units / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '');
}

/**
 * The tiles: label, value, the earlier value, format and the note under it
 * (the share of the step before). Pure.
 *
 * @param array      $now    pg_cart_recovery_figures()
 * @param array|null $before the earlier period's, or null
 * @return array
 */
function pg_visitors_cart_recovery_tiles(array $now, $before)
{
    $earlier = function ($name) use ($before) {
        return is_array($before) ? (int) $before[$name] : null;
    };

    $share = function ($part, $base, $text) {
        $rate = pg_cart_recovery_rate($part, $base);

        return ($rate === null) ? '' : lang(array('string' => $text, 'vars' => array(pg_format_number($rate, 1))));
    };

    return array(
        array('label' => lang('Left carts'), 'value' => (int) $now['left'], 'before' => $earlier('left'), 'format' => 'number', 'note' => ''),
        array('label' => lang('Reached'), 'value' => (int) $now['reached'], 'before' => $earlier('reached'), 'format' => 'number', 'note' => $share($now['reached'], $now['left'], '{var:1}% of left carts')),
        array('label' => lang('Came back'), 'value' => (int) $now['returned'], 'before' => $earlier('returned'), 'format' => 'number', 'note' => $share($now['returned'], $now['reached'], '{var:1}% of reached')),
        array('label' => lang('Recovered'), 'value' => (int) $now['recovered'], 'before' => $earlier('recovered'), 'format' => 'number', 'note' => $share($now['recovered'], $now['reached'], '{var:1}% of reached')),
        array('label' => lang('Recovered revenue'), 'value' => (int) $now['revenue'], 'before' => $earlier('revenue'), 'format' => 'money', 'note' => ''),
    );
}

/**
 * The funnel as bar rows, each with its share of the left carts. Pure.
 *
 * @param array $now pg_cart_recovery_figures()
 * @return array rows for pg_visitors_bars()
 */
function pg_visitors_cart_recovery_funnel(array $now)
{
    $rows = array();

    foreach (array('left' => lang('Left carts'), 'reached' => lang('Reached'), 'returned' => lang('Came back'), 'recovered' => lang('Recovered')) as $name => $label) {
        $rate = pg_cart_recovery_rate($now[$name], $now['left']);

        $rows[] = array(
            'label' => $label,
            'value' => (int) $now[$name],
            'text'  => pg_format_number($now[$name], 0) . (($rate === null) ? '' : ' (' . pg_visitors_percent($rate, 0) . ')'),
        );
    }

    return $rows;
}

/**
 * The figure headings shared by the channel and the message tables.
 *
 * @param string $first the first column's heading
 * @return array
 */
function pg_visitors_cart_recovery_headings($first)
{
    return array(
        $first,
        array('label' => lang('Reached'), 'end' => true),
        array('label' => lang('Came back'), 'end' => true),
        array('label' => lang('Recovered'), 'end' => true),
        array('label' => lang('Revenue'), 'end' => true),
    );
}

/**
 * A channel's name. Pure.
 *
 * @param string $channel
 * @return string
 */
function pg_visitors_cart_recovery_channel_label($channel)
{
    $labels = array(
        'email'    => lang('E-mail'),
        'whatsapp' => lang('WhatsApp'),
        'sms'      => lang('SMS'),
        'page'     => lang('Offer on a page'),
    );

    return isset($labels[$channel]) ? $labels[$channel] : (string) $channel;
}

/**
 * The figure cells of a row: reached, came back, recovered, revenue. Pure.
 *
 * @param array $row
 * @return array
 */
function pg_visitors_cart_recovery_cells(array $row)
{
    return array(
        array('html' => h(pg_format_number($row['reached'], 0)), 'sort' => (int) $row['reached']),
        array('html' => h(pg_format_number($row['returned'], 0)), 'sort' => (int) $row['returned']),
        array('html' => h(pg_format_number($row['recovered'], 0)), 'sort' => (int) $row['recovered']),
        array('html' => h(pg_visitors_cart_recovery_money($row['revenue'])), 'sort' => (int) $row['revenue']),
    );
}

/**
 * The channel table's rows. Pure.
 *
 * @param array $channels pg_cart_recovery_figures()['channels']
 * @return array
 */
function pg_visitors_cart_recovery_channel_rows(array $channels)
{
    $rows = array();

    foreach ($channels as $channel => $row) {
        $rows[] = array_merge(array(pg_visitors_cart_recovery_channel_label($channel)), pg_visitors_cart_recovery_cells($row));
    }

    return $rows;
}

/**
 * The message table's rows, the most recovered revenue first, then the most
 * carts reached. Pure.
 *
 * @param array $messages pg_cart_recovery_period()['messages']
 * @return array
 */
function pg_visitors_cart_recovery_message_rows(array $messages)
{
    usort($messages, function ($a, $b) {
        if ((int) $a['revenue'] !== (int) $b['revenue']) {
            return ((int) $b['revenue'] < (int) $a['revenue']) ? -1 : 1;
        }

        return (int) $b['reached'] - (int) $a['reached'];
    });

    $rows = array();

    foreach ($messages as $message) {
        $rows[] = array_merge(
            array(
                isset($message['label']) ? (string) $message['label'] : '',
                pg_visitors_cart_recovery_channel_label($message['channel']),
                array('html' => h(pg_format_number($message['sent'], 0)), 'sort' => (int) $message['sent']),
            ),
            pg_visitors_cart_recovery_cells($message)
        );
    }

    return $rows;
}
