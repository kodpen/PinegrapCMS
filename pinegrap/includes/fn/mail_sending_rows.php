<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail › E-mail sending as folded rows
 * (includes/settings/mail_sending.php, includes/settings/fold.php): the
 * rows' order, names and icons, each row's line of state and pill, the
 * sentence that says how a kind of mail leaves today, the ways sheet of
 * system e-mail and what its choice posts, and the last test's result.
 * Pure but for lang() and the two readers at the end.
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
 * The rows' keys in reading order.
 *
 * @return string[]
 */
function pg_mail_sending_fold_order()
{
    return array('leaves', 'domain', 'dkim', 'campaign', 'mailchimp', 'test');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_mail_sending_fold_order()
 * @return array name, icon, tone
 */
function pg_mail_sending_fold_face($key)
{
    switch ($key) {
        case 'leaves':
            return array('name' => lang('How e-mail leaves'), 'icon' => 'bi-send', 'tone' => 'pg-tone-blue');
        case 'domain':
            return array('name' => lang('Sending domain'), 'icon' => 'bi-patch-check', 'tone' => 'pg-tone-teal');
        case 'dkim':
            return array('name' => lang('DKIM TXT Record'), 'icon' => 'bi-key', 'tone' => 'pg-tone-teal');
        case 'campaign':
            return array('name' => lang('Campaign mail'), 'icon' => 'bi-megaphone', 'tone' => 'pg-tone-violet');
        case 'mailchimp':
            return array('name' => lang('MailChimp'), 'icon' => 'bi-envelope-paper', 'tone' => '');
        default:
            return array('name' => lang('Send a test'), 'icon' => 'bi-envelope-check', 'tone' => '');
    }
}

/**
 * How one kind of mail leaves today, in the order email() decides it: the
 * connected account chosen for the kind, the sending service switched on,
 * the kind's own mail server, PHP mail().
 *
 * @param array $facts account (null, or label, provider, provider_label,
 *                     status), service (the label of the service that
 *                     sends, ''), host (the kind's server, ''), mail
 *                     (PHP mail() is there)
 * @return array way (google, microsoft, service, server, mail, none), short
 *               (the account, service or server; '' for none), sentence,
 *               ok (it leaves), ask (an account to connect again)
 */
function pg_mail_way_now($facts)
{
    $account = $facts['account'] ?? null;

    if (is_array($account)) {
        $vars = array((string) $account['provider_label'], (string) $account['label']);

        if ((string) ($account['status'] ?? 'active') !== 'active') {
            return array('way' => (string) $account['provider'], 'short' => (string) $account['label'], 'ok' => false, 'ask' => true,
                'sentence' => lang(array('string' => 'The {var:1} account {var:2} must be connected again.', 'vars' => $vars)));
        }

        return array('way' => (string) $account['provider'], 'short' => (string) $account['label'], 'ok' => true, 'ask' => false,
            'sentence' => lang(array('string' => 'Leaves through the {var:1} account {var:2}.', 'vars' => $vars)));
    }

    if ((string) ($facts['service'] ?? '') !== '') {
        return array('way' => 'service', 'short' => (string) $facts['service'], 'ok' => true, 'ask' => false,
            'sentence' => lang(array('string' => 'Leaves through {var:1}.', 'vars' => array((string) $facts['service']))));
    }

    if ((string) ($facts['host'] ?? '') !== '') {
        return array('way' => 'server', 'short' => (string) $facts['host'], 'ok' => true, 'ask' => false,
            'sentence' => lang(array('string' => 'Leaves through the mail server {var:1}.', 'vars' => array((string) $facts['host']))));
    }

    if (!empty($facts['mail'])) {
        return array('way' => 'mail', 'short' => 'PHP mail()', 'ok' => true, 'ask' => false,
            'sentence' => lang('Leaves through PHP mail() on this server.'));
    }

    return array('way' => 'none', 'short' => '', 'ok' => false, 'ask' => false,
        'sentence' => lang('Cannot leave: no way to send is set up.'));
}

/**
 * The How e-mail leaves row's line and pill.
 *
 * @param array $system   pg_mail_way_now() of system e-mail
 * @param array $campaign pg_mail_way_now() of campaigns
 * @param bool  $ready    pg_mail_settings_ready()
 * @return array line, pill, attention
 */
function pg_mail_sending_fold_leaves($system, $campaign, $ready)
{
    if ($system['short'] === '') {
        $line = lang('No way to send is set up');
    } elseif (($system['way'] === $campaign['way']) && ($system['short'] === $campaign['short'])) {
        $line = lang(array('string' => 'System e-mail through {var:1} · campaigns the same way', 'vars' => array($system['short'])));
    } else {
        $line = lang(array('string' => 'System e-mail through {var:1} · campaigns through {var:2}', 'vars' => array($system['short'], ($campaign['short'] !== '') ? $campaign['short'] : lang('nothing'))));
    }

    if ($system['ask'] || $campaign['ask']) {
        return array('line' => $line, 'pill' => array(lang('Sign-in needed'), 'pg-tone-amber'), 'attention' => true);
    }

    if (!$ready || !$system['ok']) {
        return array('line' => $line, 'pill' => array(lang('Not set up'), 'pg-tone-gray'), 'attention' => true);
    }

    return array('line' => $line, 'pill' => array(lang('Ready'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The Sending domain row's line and pill, from the DNS lookup.
 *
 * @param array $health pg_mail_domain_health(): domain, and spf, dkim, dmarc
 *                      each with its state (ok, warning, error, unknown)
 * @return array line, pill, attention
 */
function pg_mail_sending_fold_domain($health)
{
    $domain = (string) ($health['domain'] ?? '');
    $look = array();
    $known = 0;

    foreach (array('spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC') as $record => $name) {
        $state = (string) ($health[$record]['state'] ?? 'unknown');

        $known += ($state !== 'unknown') ? 1 : 0;

        if (in_array($state, array('warning', 'error'), true)) {
            $look[] = $name;
        }
    }

    if ($look) {
        return array('line' => lang(array('string' => '{var:1} · {var:2} to check', 'vars' => array($domain, implode(', ', $look)))),
            'pill' => array(lang(array('string' => '{var:1} to check', 'vars' => count($look))), 'pg-tone-amber'), 'attention' => false);
    }

    if ($known === 0) {
        return array('line' => $domain, 'pill' => array(lang('Could not check'), 'pg-tone-gray'), 'attention' => false);
    }

    return array('line' => lang(array('string' => '{var:1} · SPF, DKIM and DMARC good', 'vars' => array($domain))),
        'pill' => array(lang('Good'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The DKIM row's line: the name the record is published under.
 *
 * @param string $selector DKIM_SELECTOR
 * @param string $domain   DKIM_DOMAIN
 * @param bool   $has_key  a key was generated
 * @return array line, pill, attention
 */
function pg_mail_sending_fold_dkim($selector, $domain, $has_key)
{
    if (!$has_key) {
        return array('line' => lang('No DKIM key generated yet.'), 'pill' => array(lang('No key'), 'pg-tone-gray'), 'attention' => false);
    }

    return array('line' => (($selector !== '') ? $selector : 'pinegrap') . '._domainkey.' . $domain, 'pill' => null, 'attention' => false);
}

/**
 * The Campaign mail row's line: the job, and how many it sends a run.
 *
 * @param bool       $on  the campaign job is switched on
 * @param int|string $max EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS
 * @return array line, pill, attention
 */
function pg_mail_sending_fold_campaign($on, $max)
{
    if (!$on) {
        return array('line' => lang('Scheduled sending off'), 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    $line = ((int) $max > 0)
        ? lang(array('string' => 'Scheduled sending on · at most {var:1} a run', 'vars' => array((int) $max)))
        : lang('Scheduled sending on');

    return array('line' => $line, 'pill' => array(lang('On'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The MailChimp row's line.
 *
 * @param bool   $on   MailChimp Sync
 * @param string $list the list's id
 * @return array line, pill, attention
 */
function pg_mail_sending_fold_mailchimp($on, $list)
{
    if (!$on) {
        return array('line' => lang('Sync off'), 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    return array('line' => ($list !== '') ? lang(array('string' => 'Sync on · list {var:1}', 'vars' => array($list))) : lang('Sync on'),
        'pill' => array(lang('On'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The last test e-mail as config_text keeps it.
 *
 * @param int    $time
 * @param bool   $ok     the server or service took it
 * @param string $reason why not
 * @return string JSON
 */
function pg_mail_test_result_encode($time, $ok, $reason)
{
    return (string) json_encode(array('time' => (int) $time, 'ok' => (bool) $ok, 'reason' => $ok ? '' : mb_substr((string) $reason, 0, 300, 'UTF-8')));
}

/**
 * The last test e-mail read back; null for none or a value that is not one.
 *
 * @param string $stored
 * @return array|null time, ok, reason
 */
function pg_mail_test_result_decode($stored)
{
    $value = json_decode((string) $stored, true);

    if (!is_array($value) || empty($value['time'])) {
        return null;
    }

    return array('time' => (int) $value['time'], 'ok' => !empty($value['ok']), 'reason' => (string) ($value['reason'] ?? ''));
}

/**
 * The Send a test row's line and pill, and the opened row's result line.
 *
 * @param array|null $last pg_mail_test_result_decode()
 * @param int        $now
 * @return array line, pill, attention, result (the opened row's sentence)
 */
function pg_mail_sending_fold_test($last, $now)
{
    if (!$last) {
        return array('line' => lang('No test sent yet'), 'pill' => null, 'attention' => false, 'result' => '');
    }

    $when = (date('Y-m-d', $last['time']) === date('Y-m-d', (int) $now)) ? date('H:i', $last['time']) : date('d.m.Y H:i', $last['time']);

    if ($last['ok']) {
        return array('line' => lang(array('string' => 'Last test {var:1} · accepted', 'vars' => array($when))), 'pill' => array(lang('Sent'), 'pg-tone-teal'), 'attention' => false,
            'result' => lang(array('string' => 'Last test {var:1}: accepted for delivery.', 'vars' => array($when))));
    }

    return array('line' => lang(array('string' => 'Last test {var:1} · not sent', 'vars' => array($when))), 'pill' => array(lang('Failed'), 'pg-tone-rose'), 'attention' => false,
        'result' => ($last['reason'] !== '') ? $last['reason'] : lang(array('string' => 'Last test {var:1}: not sent.', 'vars' => array($when))));
}

/**
 * The way the ways sheet of system e-mail starts on: the provider of the
 * account chosen for it, else a sending service when one is switched on,
 * else the mail server.
 *
 * @param array|null $account the account chosen for system e-mail
 * @param string     $service the service switched on, ''
 * @return string google, microsoft, service or server
 */
function pg_mail_way_chosen($account, $service)
{
    if (is_array($account) && in_array($account['provider'], array('google', 'microsoft'), true)) {
        return (string) $account['provider'];
    }

    return ($service !== '') ? 'service' : 'server';
}

/**
 * What a save of the ways sheet posts, from the way chosen: an account way
 * sends system e-mail from the account of its provider and leaves the
 * service as it is; a sending service sends without an account; the mail
 * server sends without an account or a service, as a service switched on
 * would take system e-mail before the server.
 *
 * @param array  $post     the request's fields, system_mail_way among them
 * @param string $enabled  the service switched on now, '' for none
 * @param array  $accounts account id => provider, the accounts for sending
 * @return array post (the fields to save), mark (null, or field, sentence)
 */
function pg_mail_way_post($post, $enabled, $accounts)
{
    $way = (string) ($post['system_mail_way'] ?? '');

    if (in_array($way, array('google', 'microsoft'), true)) {
        $id = (int) ($post['system_mail_connection'] ?? 0);

        if (!isset($accounts[$id]) || ($accounts[$id] !== $way)) {
            $sentence = ($way === 'google') ? lang('Choose a Google account, or connect one.') : lang('Choose a Microsoft account, or connect one.');

            return array('post' => $post, 'mark' => array('system_mail_way', $sentence));
        }

        $post['mail_transport'] = ($enabled !== '') ? $enabled : 'smtp';

        return array('post' => $post, 'mark' => null);
    }

    $post['system_mail_connection'] = '';

    if ($way !== 'service') {
        $post['mail_transport'] = 'smtp';
    }

    return array('post' => $post, 'mark' => null);
}

/**
 * The sending service switched on, complete or not; '' for none.
 *
 * @return string a pg_mail_transports() key or ''
 */
function pg_mail_way_enabled_service()
{
    foreach (array_keys(pg_mail_transports()) as $key) {
        if (pg_mail_transport_settings($key)['enabled']) {
            return $key;
        }
    }

    return '';
}

/**
 * How a kind of mail leaves today, read from the saved settings.
 *
 * @param string $kind system or campaign
 * @return array pg_mail_way_now()
 */
function pg_mail_way_read($kind)
{
    $account = pg_mail_connection($kind);
    $provider = is_array($account) ? pg_connect_provider($account['provider']) : null;
    $transport = pg_mail_transport();
    $transports = pg_mail_transports();
    $host = ($kind === 'campaign') ? 'CAMPAIGN_SMTP_HOSTNAME' : 'SYSTEM_SMTP_HOSTNAME';

    return pg_mail_way_now(array(
        'account' => is_array($account) ? array(
            'label'          => (string) $account['label'],
            'provider'       => (string) $account['provider'],
            'provider_label' => is_array($provider) ? (string) $provider['label'] : (string) $account['provider'],
            'status'         => (string) $account['status'],
        ) : null,
        'service' => isset($transports[$transport]) ? $transports[$transport]['label'] : '',
        'host'    => defined($host) ? (string) constant($host) : '',
        'mail'    => function_exists('mail'),
    ));
}

/**
 * The ways of system e-mail as choice cards (Design examples' Choice
 * cards), the chosen one checked. Each radio is joined to the settings form.
 *
 * @param string $chosen pg_mail_way_chosen()
 * @param string $form   the settings form's id
 * @return string HTML
 */
function pg_mail_way_cards($chosen, $form)
{
    $ways = array(
        'google'    => array('blue', 'bi-google', lang('A Google account'), lang('Leaves from your Gmail or Google Workspace address.')),
        'microsoft' => array('teal', 'bi-microsoft', lang('A Microsoft account'), lang('Leaves from your Microsoft 365 or Outlook address.')),
        'service'   => array('violet', 'bi-lightning-charge', lang('A sending service'), lang('With the key of a service such as Brevo, Mailgun or Amazon SES.')),
        'server'    => array('gray', 'bi-hdd-rack', lang('Your own mail server'), lang('With the server name, a user and a password.')),
    );
    $html = '';

    foreach ($ways as $key => $way) {
        $id = 'system_mail_way_' . $key;

        $html .= '<div class="form-field form-field-card">'
            . '<input class="radio" type="radio" name="system_mail_way" value="' . $key . '" id="' . $id . '" form="' . h($form) . '" aria-describedby="' . $id . '_help" data-mail-way' . (($key === $chosen) ? ' checked' : '') . '>'
            . '<label class="pg-start-head" for="' . $id . '"><span class="pg-icon-tile pg-tone-' . $way[0] . '" aria-hidden="true"><i class="bi ' . $way[1] . '"></i></span>'
            . '<strong>' . h($way[2]) . '</strong><span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span></label>'
            . '<div class="form-text" id="' . $id . '_help">' . h($way[3]) . '</div>'
            . '</div>';
    }

    return '<fieldset><legend class="visually-hidden">' . h(lang('How should system e-mail leave?')) . '</legend><div class="pg-start pg-start-choice">' . $html . '</div></fieldset>';
}

/**
 * The account select of one provider's way: its accounts for sending, the
 * stored one chosen. Only the chosen way's select is enabled, so the form
 * posts one system_mail_connection.
 *
 * @param string $provider google or microsoft
 * @param array  $accounts pg_mail_sending_accounts(), one connected for a
 *                         mailbox only marked as such
 * @param int    $current  the stored account
 * @param bool   $enabled  this way is the chosen one
 * @param string $form     the settings form's id
 * @return string HTML, '' when the provider has no account
 */
function pg_mail_way_account_select($provider, $accounts, $current, $enabled, $form)
{
    $options = '';

    foreach ($accounts as $account) {
        if ($account['provider'] !== $provider) {
            continue;
        }

        $options .= '<option value="' . (int) $account['id'] . '"' . (((int) $current === (int) $account['id']) ? ' selected' : '') . '>'
            . h($account['label'] . ((pg_mail_account_mark($account) !== '') ? ' · ' . pg_mail_account_mark($account) : '')
                . (($account['status'] !== 'active') ? ' - ' . lang('Connect again') : '')) . '</option>';
    }

    if ($options === '') {
        return '';
    }

    $id = ($provider === 'google') ? 'setting_system_mail_connection' : 'setting_system_mail_connection_' . $provider;

    return '<label for="' . $id . '" class="pg-field-label">' . h(lang('Account')) . '</label>'
        . '<select name="system_mail_connection" id="' . $id . '" class="pg-input" form="' . h($form) . '" data-mail-way-account="' . h($provider) . '"' . ($enabled ? '' : ' disabled') . '>' . $options . '</select>';
}
