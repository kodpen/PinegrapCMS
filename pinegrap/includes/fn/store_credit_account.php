<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Store credit on My Account (2026.11.0): the customer's balance, their
 * cards with where each came from, its last day and the orders that spent
 * it, and the reward points conversion form. Read by the built-in my account
 * templates (get_my_account.php) and the designer my_account widget's
 * store_credit section.
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
 * The orders that spent each card, grouped by card (pure). Rows arrive
 * newest first and keep that order.
 *
 * @param array $rows  gift_card_id, order_id, order_number, order_date, amount
 * @return array  card id => list of order_id, order_number, order_date, amount
 */
function pg_credit_account_group_uses(array $rows)
{
    $by_card = array();

    foreach ($rows as $row) {
        $card_id = (int) ($row['gift_card_id'] ?? 0);
        $amount = (int) round((float) ($row['amount'] ?? 0));

        if (($card_id <= 0) || ($amount <= 0)) {
            continue;
        }

        $by_card[$card_id][] = array(
            'order_id'     => (int) ($row['order_id'] ?? 0),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'order_date'   => (int) ($row['order_date'] ?? 0),
            'amount'       => $amount,
        );
    }

    return $by_card;
}

/**
 * The customer's cards as My Account lists them (pure): kind, reason,
 * amount, balance, last day (Y-m-d, '' for never), state (open, used or
 * expired) and the orders that spent it.
 *
 * @param array $cards  gift_cards rows, newest first
 * @param array $uses   pg_credit_account_group_uses()
 * @param int   $now
 * @return array
 */
function pg_credit_account_cards(array $cards, array $uses, $now)
{
    $out = array();

    foreach ($cards as $card) {
        $id = (int) ($card['id'] ?? 0);
        $expires = (int) ($card['expires_at'] ?? 0);
        $date = (string) ($card['expiration_date'] ?? '');

        // The checkout stops a card at whichever comes first: expires_at,
        // or an expiration_date staff set on the gift card screen.
        $last_day = ($expires > 0) ? pg_credit_expiration_date($expires) : '';
        if (($date !== '') && ($date !== '0000-00-00') && (($last_day === '') || ($date < $last_day))) {
            $last_day = $date;
        }

        $out[] = array(
            'id'       => $id,
            'kind'     => (string) ($card['kind'] ?? 'gift'),
            'reason'   => (string) ($card['reason'] ?? ''),
            'amount'   => (int) ($card['amount'] ?? 0),
            'balance'  => (int) ($card['balance'] ?? 0),
            'last_day' => $last_day,
            'state'    => pg_credit_staff_card_state($card, (int) $now),
            'uses'     => isset($uses[$id]) ? $uses[$id] : array(),
        );
    }

    return $out;
}

/**
 * Whether My Account has anything to say about store credit (pure): a card,
 * or points enough to convert.
 *
 * @param array $cards    pg_credit_account_cards()
 * @param array $convert  pg_credit_account_convert()
 * @return bool
 */
function pg_credit_account_shown(array $cards, array $convert)
{
    return !empty($cards) || !empty($convert['shown']);
}

/**
 * The reward points conversion offer with what its form needs: the target,
 * the token, the page to come back to and what all the points are worth.
 * 'shown' is true when the form is drawn: a points value is set and the
 * customer has at least the minimum.
 *
 * @param int $points  user.user_reward_points (0 when the program is off)
 * @return array  pg_credit_points_offer() plus shown, url, token_field,
 *                send_to, value_info
 */
function pg_credit_account_convert($points)
{
    $offer = pg_credit_points_offer((int) $points);
    $offer['shown'] = $offer['enabled'] && ($offer['points'] > 0) && ($offer['points'] >= $offer['minimum']);
    $offer['url'] = '';
    $offer['token_field'] = '';
    $offer['send_to'] = '';
    $offer['value_info'] = '';

    if ($offer['enabled']) {
        $offer['url'] = PATH . SOFTWARE_DIRECTORY . '/convert_reward_points.php';
        $offer['token_field'] = get_token_field();
        $offer['send_to'] = get_request_uri();
        $offer['value_info'] = pg_format_money($offer['value'] / 100, BASE_CURRENCY_SYMBOL);
    }

    return $offer;
}

/**
 * The signed-in customer's reward points, 0 when the program is off.
 *
 * @param int $user_id
 * @return int
 */
function pg_credit_account_points($user_id)
{
    if (!defined('ECOMMERCE_REWARD_PROGRAM') || !ECOMMERCE_REWARD_PROGRAM || ((int) $user_id <= 0)) {
        return 0;
    }

    return (int) db_value("SELECT user_reward_points FROM user WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * The orders of this customer that spent their cards, newest first.
 *
 * @param int   $user_id
 * @param array $card_ids
 * @return array  pg_credit_account_group_uses()
 */
function pg_credit_account_uses($user_id, array $card_ids)
{
    $ids = array();

    foreach ($card_ids as $id) {
        if ((int) $id > 0) {
            $ids[] = (int) $id;
        }
    }

    if (empty($ids)) {
        return array();
    }

    // Only the customer's own placed orders: a cart's rows hold no amount
    // yet, and a card bound to them is spent by nobody else.
    $rows = db_items(
        "SELECT
            applied_gift_cards.gift_card_id,
            applied_gift_cards.amount,
            orders.id AS order_id,
            orders.order_number,
            orders.order_date
        FROM applied_gift_cards
        INNER JOIN orders ON orders.id = applied_gift_cards.order_id
        WHERE
            applied_gift_cards.gift_card_id IN (" . implode(',', $ids) . ")
            AND applied_gift_cards.givex = '0'
            AND orders.user_id = '" . (int) $user_id . "'
            AND orders.status IN ('complete', 'exported', 'cancelled')
        ORDER BY orders.order_date DESC, applied_gift_cards.id DESC");

    return pg_credit_account_group_uses(is_array($rows) ? $rows : array());
}

/**
 * Everything My Account shows about the customer's store credit, with the
 * money and dates written out for the page as plain text (escape them).
 *
 * @param int    $user_id
 * @param string $view_order_url  the view order page, '' for none
 * @param int    $now
 * @return array  shown, balance, balance_info, cards (each with
 *                kind_label, amount_info, balance_info, last_day_info,
 *                state_label and uses with amount_info, date_info,
 *                view_url), convert (pg_credit_account_convert())
 */
function pg_credit_account_view($user_id, $view_order_url, $now)
{
    $user_id = (int) $user_id;
    $empty = array('shown' => false, 'balance' => 0, 'balance_info' => '', 'cards' => array(),
        'convert' => array('enabled' => false, 'shown' => false));

    if (($user_id <= 0) || !pg_credit_ready()) {
        return $empty;
    }

    $rows = pg_credit_staff_cards($user_id);
    $card_ids = array();

    foreach ($rows as $row) {
        $card_ids[] = (int) $row['id'];
    }

    $cards = pg_credit_account_cards($rows, pg_credit_account_uses($user_id, $card_ids), $now);
    $convert = pg_credit_account_convert(pg_credit_account_points($user_id));

    // Plain text: the symbol may be stored as an HTML entity, and the
    // templates escape what they print.
    $money = function ($cents) {
        return html_entity_decode(pg_format_money((int) $cents / 100, BASE_CURRENCY_SYMBOL), ENT_QUOTES, 'UTF-8');
    };
    $kinds = pg_credit_kinds();
    $states = array('open' => lang('Open'), 'used' => lang('Used'), 'expired' => lang('Expired'));

    foreach ($cards as $key => $card) {
        $card['kind_label'] = isset($kinds[$card['kind']]) ? $kinds[$card['kind']] : lang('Gift card');
        $card['amount_info'] = $money($card['amount']);
        $card['balance_info'] = $money($card['balance']);
        $card['last_day_info'] = ($card['last_day'] !== '')
            ? get_absolute_time(array('timestamp' => strtotime($card['last_day']), 'type' => 'date', 'format' => 'plain_text'))
            : '';
        $card['state_label'] = $states[$card['state']];

        foreach ($card['uses'] as $use_key => $use) {
            $use['amount_info'] = $money($use['amount']);
            $use['date_info'] = get_absolute_time(array('timestamp' => $use['order_date'], 'type' => 'date', 'format' => 'plain_text'));
            $use['view_url'] = ($view_order_url !== '') ? $view_order_url . '?id=' . $use['order_id'] : '';
            $card['uses'][$use_key] = $use;
        }

        $cards[$key] = $card;
    }

    $balance = pg_credit_balance($user_id, $now);

    return array(
        'shown'        => pg_credit_account_shown($cards, $convert),
        'balance'      => $balance,
        'balance_info' => $money($balance),
        'cards'        => $cards,
        'convert'      => $convert,
    );
}

/**
 * The my_account widget's store_credit section (site output, Bootstrap
 * 5.3.8): the balance, the conversion form and the cards with the orders
 * that spent them. '' when there is nothing to show.
 *
 * @param array $view  pg_credit_account_view()
 * @return string
 */
function pg_credit_account_section_html(array $view)
{
    if (empty($view['shown'])) {
        return '';
    }

    $badges = array('open' => 'text-bg-success', 'used' => 'text-bg-secondary', 'expired' => 'text-bg-warning');

    $html = '<div class="pg-sw-store-credit">'
        . '<p class="mb-3">' . h(lang('Balance')) . ': <span class="fw-semibold">' . h($view['balance_info']) . '</span></p>';

    $convert = $view['convert'];

    if (!empty($convert['shown'])) {
        $html .= '<form action="' . h($convert['url']) . '" method="post" class="mb-3">'
            . $convert['token_field']
            . '<input type="hidden" name="send_to" value="' . h($convert['send_to']) . '">'
            . '<label for="pg_sw_reward_points_convert" class="form-label">'
            . h(lang(array('string' => 'Reward points: {var:1}', 'vars' => array(pg_format_number($convert['points'], 0))))) . '</label>'
            . '<div class="input-group" style="max-width: 24rem">'
            . '<input type="number" id="pg_sw_reward_points_convert" name="points" class="form-control" min="' . (int) $convert['minimum'] . '" max="' . (int) $convert['points'] . '" step="1" value="' . (int) $convert['points'] . '" required>'
            . '<button type="submit" class="btn btn-outline-primary">' . h(lang('Convert to store credit')) . '</button>'
            . '</div>'
            . '<div class="form-text">' . h(lang(array('string' => 'All your points are worth {var:1}.', 'vars' => array(html_entity_decode($convert['value_info'], ENT_QUOTES, 'UTF-8'))))) . '</div>'
            . '</form>';
    }

    if (!empty($view['cards'])) {
        $html .= '<div class="list-group">';

        foreach ($view['cards'] as $card) {
            $html .= '<div class="list-group-item">'
                . '<div class="d-flex justify-content-between align-items-start gap-2">'
                . '<span><span class="fw-semibold">' . h($card['kind_label']) . '</span>'
                . ' <span class="badge ' . $badges[$card['state']] . ' ms-1">' . h($card['state_label']) . '</span>'
                . (($card['reason'] !== '') ? '<span class="d-block small text-body-secondary text-break">' . h($card['reason']) . '</span>' : '')
                . '</span>'
                . '<span class="text-end"><span class="fw-semibold">' . h($card['balance_info']) . '</span>'
                . '<span class="d-block small text-body-secondary">' . h(lang(array('string' => 'of {var:1}', 'vars' => array($card['amount_info'])))) . '</span></span>'
                . '</div>'
                . (($card['last_day_info'] !== '') ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'Last day: {var:1}', 'vars' => array($card['last_day_info'])))) . '</div>' : '');

            if (!empty($card['uses'])) {
                $html .= '<ul class="list-unstyled small mb-0 mt-1">';

                foreach ($card['uses'] as $use) {
                    $number = '#' . h($use['order_number']);
                    $html .= '<li>' . (($use['view_url'] !== '') ? '<a href="' . h($use['view_url']) . '">' . $number . '</a>' : $number)
                        . ' <span class="text-body-secondary">' . h($use['date_info']) . '</span> −' . h($use['amount_info']) . '</li>';
                }

                $html .= '</ul>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
    }

    return $html . '</div>';
}
