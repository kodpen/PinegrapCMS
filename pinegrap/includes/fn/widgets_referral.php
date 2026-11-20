<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The my account widget's referral part (site output, Bootstrap 5.3.8): the
 * signed-in customer's referral link with Copy and share links, the friends'
 * orders that earned a reward with their state, the store credit earned,
 * and why a customer cannot refer yet. Section "referral", flags
 * has_referral and can_refer, link ^^__referral_url^^. Read through the
 * rules of referral.php; nothing here writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most referral rows the my account part lists. */
define('PG_REFERRAL_ACCOUNT_LIMIT', 50);

/**
 * What the part shows a customer: 'hidden' (programme off, upgrade not
 * run, no code or no account), 'affiliate' (an approved affiliate, who
 * shares the affiliate link instead), 'no_order' (no completed order yet)
 * or 'ready'. The same referrer rules as pg_referral_check(). Pure.
 *
 * @param array $facts on, ready, code, referrer (pg_referral_referrer() or null)
 * @return string
 */
function pg_referral_account_reason(array $facts)
{
    if (empty($facts['on']) || empty($facts['ready']) || ((string) ($facts['code'] ?? '') === '') || !is_array($facts['referrer'] ?? null)) {
        return 'hidden';
    }

    if ((int) ($facts['referrer']['affiliate_approved'] ?? 0) === 1) {
        return 'affiliate';
    }

    return ((int) ($facts['referrer']['orders'] ?? 0) > 0) ? 'ready' : 'no_order';
}

/**
 * A friend's initials from the order's billing name, "A. K.", '' without a
 * name. Pure.
 *
 * @param mixed $first
 * @param mixed $last
 * @return string
 */
function pg_referral_initials($first, $last)
{
    $parts = array();

    foreach (array($first, $last) as $name) {
        $name = trim((string) $name);

        if ($name !== '') {
            $parts[] = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') . '.';
        }
    }

    return implode(' ', $parts);
}

/**
 * The share links of a referral link, built by the site with no third-party
 * script: WhatsApp, e-mail, X and Facebook. Pure.
 *
 * @param string $link
 * @param string $message the words before the link
 * @return array rows of key, label, url; none without a link
 */
function pg_referral_share_links($link, $message)
{
    $link = (string) $link;

    if ($link === '') {
        return array();
    }

    $message = (string) $message;
    $text = trim($message . ' ' . $link);

    return array(
        array('key' => 'whatsapp', 'label' => lang('WhatsApp'), 'url' => 'https://wa.me/?text=' . rawurlencode($text)),
        array('key' => 'email', 'label' => lang('E-mail'), 'url' => 'mailto:?subject=' . rawurlencode($message) . '&body=' . rawurlencode($text)),
        array('key' => 'x', 'label' => lang('X'), 'url' => 'https://twitter.com/intent/tweet?text=' . rawurlencode($message) . '&url=' . rawurlencode($link)),
        array('key' => 'facebook', 'label' => lang('Facebook'), 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($link)),
    );
}

/**
 * The customer's referral rows, newest first: each friend's order that
 * earned a reward, with the friend's billing name for the initials.
 *
 * @param string $code the customer's referral code
 * @return array rows of id, status, amount, created_timestamp,
 *               billing_first_name, billing_last_name
 */
function pg_referral_account_rows($code)
{
    if (((string) $code === '') || !pg_referral_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT c.id, c.status, c.amount, c.created_timestamp, o.billing_first_name, o.billing_last_name
        FROM commissions AS c
        LEFT JOIN orders AS o ON o.id = c.order_id
        WHERE (c.affiliate_code = '" . e((string) $code) . "') AND " . pg_referral_row_sql('c.affiliate_code') . "
        ORDER BY c.created_timestamp DESC, c.id DESC
        LIMIT " . PG_REFERRAL_ACCOUNT_LIMIT);
}

/**
 * The part's view of those rows: the friend as initials, the date, the
 * state (a paid row rewarded, any other taken back) and the reward through
 * $money; with the counts and the credit the kept rewards earned. Pure but
 * for the formatter it is given.
 *
 * @param array    $rows        pg_referral_account_rows()
 * @param callable $money       minor units => text
 * @param string   $date_format
 * @return array rows (friend, date, state, state_label, tone, amount_info),
 *               rewarded, taken_back, earned (minor units), earned_info
 */
function pg_referral_account_view(array $rows, $money, $date_format)
{
    $view = array('rows' => array(), 'rewarded' => 0, 'taken_back' => 0, 'earned' => 0, 'earned_info' => '');

    foreach ($rows as $row) {
        $kept = ((string) $row['status'] === 'paid');
        $initials = pg_referral_initials($row['billing_first_name'] ?? '', $row['billing_last_name'] ?? '');

        if ($kept) {
            $view['rewarded']++;
            $view['earned'] += (int) $row['amount'];
        } else {
            $view['taken_back']++;
        }

        $view['rows'][] = array(
            'friend'      => ($initials !== '') ? $initials : lang('A friend'),
            'date'        => ((int) $row['created_timestamp'] > 0) ? date($date_format, (int) $row['created_timestamp']) : '',
            'state'       => $kept ? 'rewarded' : 'taken_back',
            'state_label' => $kept ? lang('Rewarded') : lang('Taken back'),
            'tone'        => $kept ? 'success' : 'secondary',
            'amount_info' => (string) $money((int) $row['amount']),
        );
    }

    $view['earned_info'] = (string) $money($view['earned']);

    return $view;
}

/**
 * The my account referral section (Bootstrap 5.3.8, the site's): what the
 * programme gives, the link with Copy and the share links while the
 * customer can refer, else the reason, and the referrals. Pure; the
 * designer canvas draws its twin (_sdAccountReferralHtml()).
 *
 * @param array $part reason, link, share, reward_info, smallest_info, view
 * @return string HTML, '' when hidden
 */
function pg_referral_account_html(array $part)
{
    $reason = (string) ($part['reason'] ?? 'hidden');

    if (!in_array($reason, array('ready', 'no_order', 'affiliate'), true)) {
        return '';
    }

    $html = '<div class="pg-sw-referral">';

    if ($reason === 'ready') {
        $html .= '<p class="mb-2">' . h(lang(array('string' => 'Share your link. When a friend\'s first order is complete, you earn {var:1} in store credit.',
            'vars' => array((string) $part['reward_info']))));

        if ((string) ($part['smallest_info'] ?? '') !== '') {
            $html .= ' ' . h(lang(array('string' => 'The order must be at least {var:1}.', 'vars' => array((string) $part['smallest_info']))));
        }

        // Copy selects the box first, so a browser without the clipboard
        // API still leaves the link ready to copy by hand.
        $html .= '</p><div class="input-group mb-2">'
            . '<input type="text" id="pg_sw_referral_link" class="form-control" value="' . h($part['link']) . '" readonly aria-label="' . h(lang('Your referral link')) . '" onclick="this.select()">'
            . '<button type="button" class="btn btn-outline-primary" data-pg-done="' . h(lang('Copied')) . '"'
            . ' onclick="var i=document.getElementById(\'pg_sw_referral_link\');i.select();if(navigator.clipboard){navigator.clipboard.writeText(i.value);}else{document.execCommand(\'copy\');}this.textContent=this.getAttribute(\'data-pg-done\');">'
            . h(lang('Copy')) . '</button></div>';

        if (!empty($part['share'])) {
            $html .= '<div class="d-flex flex-wrap gap-2 mb-3">';

            foreach ($part['share'] as $share) {
                $html .= '<a href="' . h($share['url']) . '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener nofollow">' . h($share['label']) . '</a>';
            }

            $html .= '</div>';
        }
    } elseif ($reason === 'no_order') {
        $html .= '<p class="mb-3 text-body-secondary">' . h(lang('You can refer friends once you have a completed order.')) . '</p>';
    } else {
        $html .= '<p class="mb-3 text-body-secondary">' . h(lang('As an affiliate, share your affiliate link instead: it earns you commission.')) . '</p>';
    }

    $view = (array) ($part['view'] ?? array());

    if (empty($view['rows'])) {
        return $html . ($reason === 'ready' ? '<p class="small text-body-secondary mb-0">' . h(lang('No referrals yet.')) . '</p>' : '') . '</div>';
    }

    $html .= '<p class="mb-2">' . h(lang(array('string' => 'Rewards earned: {var:1}', 'vars' => array((int) $view['rewarded']))))
        . '<span class="text-body-secondary mx-2">·</span>'
        . h(lang(array('string' => 'Store credit earned: {var:1}', 'vars' => array((string) $view['earned_info'])))) . '</p>'
        . '<div class="list-group pg-sw-referrals">';

    foreach ($view['rows'] as $row) {
        $html .= '<div class="list-group-item d-flex justify-content-between align-items-center gap-2">'
            . '<span><span class="fw-semibold">' . h($row['friend']) . '</span>'
            . '<span class="text-body-secondary small ms-2">' . h($row['date']) . '</span></span>'
            . '<span><span class="me-2">' . h($row['amount_info']) . '</span>'
            . '<span class="badge text-bg-' . h($row['tone']) . '">' . h($row['state_label']) . '</span></span>'
            . '</div>';
    }

    return $html . '</div></div>';
}

/**
 * The my account widget's referral part for the signed-in customer: the
 * section, the link and the flags; empty while hidden.
 *
 * @param int $user_id
 * @param int $contact_id
 * @return array section, link, has, can
 */
function pg_referral_account($user_id, $contact_id)
{
    $empty = array('section' => '', 'link' => '', 'has' => false, 'can' => false);

    if (((int) $user_id <= 0) || ((int) $contact_id <= 0)) {
        return $empty;
    }

    $settings = pg_referral_settings();
    $ready = $settings['on'] && pg_referral_ready();
    $code = $ready ? pg_referral_code_for_contact((int) $contact_id) : '';
    $referrer = ($code !== '') ? pg_referral_referrer((int) $contact_id) : null;
    $reason = pg_referral_account_reason(array('on' => $settings['on'], 'ready' => $ready, 'code' => $code, 'referrer' => $referrer));

    if ($reason === 'hidden') {
        return $empty;
    }

    // Plain text: the symbol may be stored as an HTML entity, and the
    // section escapes what it prints.
    $money = function ($cents) {
        return pg_money_text((int) $cents / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '');
    };
    $link = ($reason === 'ready')
        ? pg_affiliate_link(defined('URL_SCHEME') ? URL_SCHEME : '', defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', defined('PATH') ? PATH : '/', $code)
        : '';
    $message = ($settings['offer_id'] > 0)
        ? lang('Take a look at this shop: your first order gets an offer.')
        : lang('Take a look at this shop.');
    $part = array(
        'reason'        => $reason,
        'link'          => $link,
        'share'         => pg_referral_share_links($link, $message),
        'reward_info'   => $money($settings['reward']),
        'smallest_info' => ($settings['smallest'] > 0) ? $money($settings['smallest']) : '',
        'view'          => pg_referral_account_view(pg_referral_account_rows($code), $money, pg_sw_default_date_format('date')),
    );

    return array('section' => pg_referral_account_html($part), 'link' => $link, 'has' => true, 'can' => ($reason === 'ready'));
}
