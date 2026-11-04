<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sending-domain health card of SMTP Settings: the SPF, DKIM and DMARC
 * records the domain publishes, judged against what the site sends through,
 * with the record text to copy when one is missing or wrong; and the bounce
 * and complaint rates of the last 30 days, which the operations alerts warn
 * about (code mail_reputation). The DNS answers are judged by pure functions;
 * a lookup that fails says "could not check", never red.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Mailbox providers start throttling a sender above these rates (Gmail and
// Yahoo's 2024 sender rules name 0.3 % complaints).
const PG_MAIL_REPUTATION_BOUNCE_LIMIT = 0.05;
const PG_MAIL_REPUTATION_COMPLAINT_LIMIT = 0.003;

// Below this many sends in the window a rate says nothing: one bounce of ten
// sends is not a reputation.
const PG_MAIL_REPUTATION_MIN_SENDS = 100;

const PG_MAIL_REPUTATION_WINDOW = 2592000;

/**
 * The SPF include each sending service asks its customers to publish.
 *
 * @return array transport key => domains, any of which covers it
 */
function pg_mail_spf_includes()
{
    return array(
        'postmark' => array('spf.mtasv.net'),
        'mailgun'  => array('mailgun.org'),
        'brevo'    => array('spf.brevo.com', 'spf.sendinblue.com'),
        'resend'   => array('amazonses.com'),
        'ses'      => array('amazonses.com'),
    );
}

/**
 * What the SPF record must name for the way the site sends.
 *
 * @param string $transport  pg_mail_transport(): a service key or 'smtp'
 * @param string $smtp_host  the system SMTP host name, '' for mail() or a
 *                           connected account
 * @return array array('includes' => domains, 'host' => host name or '');
 *               both empty: any SPF record will do
 */
function pg_mail_spf_expected($transport, $smtp_host)
{
    $includes = pg_mail_spf_includes();

    if (isset($includes[$transport])) {
        return array('includes' => $includes[$transport], 'host' => '');
    }

    $host = strtolower(trim((string) $smtp_host));

    // An address literal or a name without a dot (localhost) is not a host
    // an SPF record names.
    if (($host === '') || (strpos($host, '.') === false) || filter_var($host, FILTER_VALIDATE_IP)) {
        $host = '';
    }

    return array('includes' => array(), 'host' => $host);
}

/**
 * The text of a DNS TXT answer: dns_get_record() gives the strings of a long
 * record apart in 'entries' and joined in 'txt'.
 *
 * @param array|string $entry
 * @return string
 */
function pg_mail_dns_text($entry)
{
    if (is_string($entry)) {
        return trim($entry);
    }

    if (!is_array($entry)) {
        return '';
    }

    if (isset($entry['entries']) && is_array($entry['entries'])) {
        return trim(implode('', $entry['entries']));
    }

    return isset($entry['txt']) ? trim((string) $entry['txt']) : '';
}

/**
 * The TXT records at a name.
 *
 * @param string $name
 * @return string[]|null the records, an empty array when there is none, null
 *                       when the lookup could not be made
 */
function pg_mail_dns_txt($name)
{
    if (!function_exists('dns_get_record') || ($name === '')) {
        return null;
    }

    $answer = @dns_get_record($name, DNS_TXT);

    if (!is_array($answer)) {
        return null;
    }

    $records = array();

    foreach ($answer as $entry) {
        $text = pg_mail_dns_text($entry);

        if ($text !== '') {
            $records[] = $text;
        }
    }

    return $records;
}

/**
 * Whether the resolver knows the domain at all (its name servers or its
 * SOA). Some hosts answer every query with an empty list instead of an
 * error; without this every record would read as missing there.
 *
 * @param string $domain
 * @return bool
 */
function pg_mail_dns_answers($domain)
{
    if (!function_exists('dns_get_record')) {
        return false;
    }

    foreach (array(DNS_NS, DNS_SOA) as $type) {
        $answer = @dns_get_record($domain, $type);

        if (is_array($answer) && $answer) {
            return true;
        }
    }

    // A sending subdomain has no NS of its own: ask the domain above it.
    $parent = substr((string) strstr($domain, '.'), 1);

    return (strpos($parent, '.') !== false) ? pg_mail_dns_answers($parent) : false;
}

/**
 * The tag=value pairs of a DKIM or DMARC record, tag names lower-cased.
 *
 * @param string $record
 * @return array
 */
function pg_mail_dns_tags($record)
{
    $tags = array();

    foreach (explode(';', (string) $record) as $part) {
        $pair = explode('=', $part, 2);

        if (count($pair) === 2) {
            $tags[strtolower(trim($pair[0]))] = trim($pair[1]);
        }
    }

    return $tags;
}

/**
 * A judged line of the card.
 *
 * @param string $state   ok, warning, error, unknown
 * @param string $reason  a short code the card words
 * @param string $found   the record found, '' for none
 * @param string $copy    the record to publish, '' when nothing is to change
 * @return array
 */
function pg_mail_dns_line($state, $reason, $found = '', $copy = '')
{
    return array('state' => $state, 'reason' => $reason, 'found' => $found, 'copy' => $copy);
}

/**
 * Judges the SPF record of the sending domain.
 *
 * @param string[]|null $records   the domain's TXT records, null when the
 *                                 lookup failed
 * @param array         $expected  pg_mail_spf_expected()
 * @return array pg_mail_dns_line(); reason none, several, all_pass,
 *               covered, not_named, present
 */
function pg_mail_spf_check($records, array $expected)
{
    if (!is_array($records)) {
        return pg_mail_dns_line('unknown', 'lookup');
    }

    $spf = array();

    foreach ($records as $record) {
        if (preg_match('/^v=spf1(\s|$)/i', $record)) {
            $spf[] = $record;
        }
    }

    $mechanism = '';

    if ($expected['includes']) {
        $mechanism = 'include:' . $expected['includes'][0];
    } elseif ($expected['host'] !== '') {
        $mechanism = 'a:' . $expected['host'];
    }

    if (!$spf) {
        return pg_mail_dns_line('error', 'none', '', 'v=spf1 ' . (($mechanism !== '') ? $mechanism . ' ' : 'mx ') . '~all');
    }

    // A receiver treats two SPF records as a permanent error (RFC 7208, 4.5).
    if (count($spf) > 1) {
        return pg_mail_dns_line('error', 'several', implode("\n", $spf));
    }

    $record = $spf[0];
    $terms = preg_split('/\s+/', strtolower($record));

    if (in_array('+all', $terms, true) || in_array('all', $terms, true)) {
        return pg_mail_dns_line('warning', 'all_pass', $record);
    }

    if (($mechanism === '') || pg_mail_spf_covers($terms, $expected)) {
        return pg_mail_dns_line('ok', ($mechanism === '') ? 'present' : 'covered', $record);
    }

    return pg_mail_dns_line('warning', 'not_named', $record, pg_mail_spf_add($record, $mechanism));
}

/**
 * Whether one of the record's terms names the service's include or the SMTP
 * host (itself, a domain above it, or the sending domain's own a/mx when the
 * host is under it is not knowable without more lookups and counts as named).
 *
 * @param string[] $terms     the record's terms, lower case
 * @param array    $expected  pg_mail_spf_expected()
 * @return bool
 */
function pg_mail_spf_covers(array $terms, array $expected)
{
    foreach ($terms as $term) {
        $term = ltrim($term, '+?~-');

        if (!preg_match('/^(include:|a:|mx:|exists:|redirect=)([^\/\s]+)/', $term, $match)) {
            continue;
        }

        $domain = rtrim($match[2], '.');

        foreach ($expected['includes'] as $include) {
            if (($domain === $include) || (substr($domain, -strlen('.' . $include)) === '.' . $include)) {
                return true;
            }
        }

        if (($expected['host'] !== '') && (($domain === $expected['host']) || (substr($expected['host'], -strlen('.' . $domain)) === '.' . $domain))) {
            return true;
        }
    }

    return false;
}

/**
 * The SPF record with a mechanism added before its closing all or redirect.
 *
 * @param string $record
 * @param string $mechanism
 * @return string
 */
function pg_mail_spf_add($record, $mechanism)
{
    $terms = preg_split('/\s+/', trim($record));
    $at = count($terms);

    foreach ($terms as $i => $term) {
        if (preg_match('/^([+?~-]?all|redirect=.*)$/i', $term)) {
            $at = $i;
            break;
        }
    }

    array_splice($terms, $at, 0, array($mechanism));

    return implode(' ', $terms);
}

/**
 * The public key (p=) of a DKIM record, white space removed.
 *
 * @param string $record  the TXT value, or a zone line with it in quotes
 * @return string
 */
function pg_mail_dkim_key($record)
{
    $record = (string) $record;

    // pg_dkim_txt_record() writes a zone line: name IN TXT "v=DKIM1; …".
    if (preg_match('/"(.*)"/s', $record, $match)) {
        $record = str_replace(array('" "', '""'), '', $match[1]);
    }

    $tags = pg_mail_dns_tags($record);

    return isset($tags['p']) ? preg_replace('/\s+/', '', $tags['p']) : '';
}

/**
 * Judges the DKIM record at the site's selector.
 *
 * @param string[]|null $records   the TXT records at <selector>._domainkey.<domain>
 * @param string        $expected  the zone line pg_dkim_txt_record() made for
 *                                 the site's key, '' when no key was made
 * @param string        $transport a service key or 'smtp'
 * @return array pg_mail_dns_line(); reason service, no_key, none, other_key,
 *               match
 */
function pg_mail_dkim_check($records, $expected, $transport)
{
    // A sending service signs with the key set up in its portal.
    if ($transport !== 'smtp') {
        return pg_mail_dns_line('unknown', 'service');
    }

    $key = pg_mail_dkim_key($expected);

    if ($key === '') {
        return pg_mail_dns_line('warning', 'no_key');
    }

    if (!is_array($records)) {
        return pg_mail_dns_line('unknown', 'lookup');
    }

    $found = array();

    foreach ($records as $record) {
        $published = pg_mail_dkim_key($record);

        if ($published === '') {
            continue;
        }

        if ($published === $key) {
            return pg_mail_dns_line('ok', 'match', $record);
        }

        $found[] = $record;
    }

    return pg_mail_dns_line('error', $found ? 'other_key' : 'none', implode("\n", $found), (string) $expected);
}

/**
 * Judges the DMARC record of the sending domain.
 *
 * @param string[]|null $records  the TXT records at _dmarc.<domain>
 * @param string        $domain
 * @param string        $report   the address aggregate reports go to
 * @return array pg_mail_dns_line() with 'policy'; reason none, several,
 *               invalid, none_policy, enforced
 */
function pg_mail_dmarc_check($records, $domain, $report)
{
    if (!is_array($records)) {
        return pg_mail_dns_line('unknown', 'lookup') + array('policy' => '');
    }

    $dmarc = array();

    foreach ($records as $record) {
        if (preg_match('/^v\s*=\s*DMARC1\s*(;|$)/i', $record)) {
            $dmarc[] = $record;
        }
    }

    $suggest = function ($policy) use ($domain, $report) {
        return '_dmarc.' . $domain . ' IN TXT "v=DMARC1; p=' . $policy
            . ((filter_var($report, FILTER_VALIDATE_EMAIL) !== false) ? '; rua=mailto:' . $report : '') . '"';
    };

    if (!$dmarc) {
        return pg_mail_dns_line('error', 'none', '', $suggest('none')) + array('policy' => '');
    }

    // More than one record: the receiver applies none (RFC 7489, 6.6.3).
    if (count($dmarc) > 1) {
        return pg_mail_dns_line('error', 'several', implode("\n", $dmarc)) + array('policy' => '');
    }

    $tags = pg_mail_dns_tags($dmarc[0]);
    $policy = isset($tags['p']) ? strtolower($tags['p']) : '';

    if (!in_array($policy, array('none', 'quarantine', 'reject'), true)) {
        return pg_mail_dns_line('error', 'invalid', $dmarc[0], $suggest('none')) + array('policy' => $policy);
    }

    if ($policy === 'none') {
        return pg_mail_dns_line('warning', 'none_policy', $dmarc[0]) + array('policy' => $policy);
    }

    return pg_mail_dns_line('ok', 'enforced', $dmarc[0]) + array('policy' => $policy);
}

/**
 * The bounce and complaint rates and whether they are over the limits.
 *
 * @param int $sent
 * @param int $bounces
 * @param int $complaints
 * @return array sent, bounces, complaints, bounce_rate, complaint_rate (0-1),
 *               judged (enough sends to judge), over (bounces/complaints
 *               names over the limit), state ok, error or unknown
 */
function pg_mail_reputation_rates($sent, $bounces, $complaints)
{
    $sent = max(0, (int) $sent);
    $bounces = max(0, (int) $bounces);
    $complaints = max(0, (int) $complaints);

    $rates = array(
        'sent'           => $sent,
        'bounces'        => $bounces,
        'complaints'     => $complaints,
        'bounce_rate'    => $sent ? $bounces / $sent : 0.0,
        'complaint_rate' => $sent ? $complaints / $sent : 0.0,
        'judged'         => ($sent >= PG_MAIL_REPUTATION_MIN_SENDS),
        'over'           => array(),
        'state'          => 'unknown',
    );

    if (!$rates['judged']) {
        return $rates;
    }

    if ($rates['bounce_rate'] > PG_MAIL_REPUTATION_BOUNCE_LIMIT) {
        $rates['over'][] = 'bounces';
    }

    if ($rates['complaint_rate'] > PG_MAIL_REPUTATION_COMPLAINT_LIMIT) {
        $rates['over'][] = 'complaints';
    }

    $rates['state'] = $rates['over'] ? 'error' : 'ok';

    return $rates;
}

/**
 * A rate as the card and the alert write it: 2.35 %.
 *
 * @param float $rate 0-1
 * @return string
 */
function pg_mail_reputation_percent($rate)
{
    return rtrim(rtrim(number_format((float) $rate * 100, 2, '.', ''), '0'), '.') . ' %';
}

/**
 * The rates over their limit, as the alert names them.
 *
 * @param array $rates pg_mail_reputation_rates()
 * @return string[]
 */
function pg_mail_reputation_alert_parts(array $rates)
{
    $parts = array();

    if (in_array('bounces', $rates['over'], true)) {
        $parts[] = lang(array(
            'string' => 'Bounces {var:1} (limit {var:2})',
            'vars'   => array(pg_mail_reputation_percent($rates['bounce_rate']), pg_mail_reputation_percent(PG_MAIL_REPUTATION_BOUNCE_LIMIT)),
        ));
    }

    if (in_array('complaints', $rates['over'], true)) {
        $parts[] = lang(array(
            'string' => 'Complaints {var:1} (limit {var:2})',
            'vars'   => array(pg_mail_reputation_percent($rates['complaint_rate']), pg_mail_reputation_percent(PG_MAIL_REPUTATION_COMPLAINT_LIMIT)),
        ));
    }

    return $parts;
}

/**
 * The counts of the last 30 days. Sends: delivered campaign recipients of
 * campaigns started in the window, and the queue's sent messages (the queue
 * keeps those seven days, so older ones are not counted). Bounces: addresses
 * whose last bounce (hard or soft) is in the window; complaints likewise.
 *
 * @param int $now
 * @return array array(sent, bounces, complaints)
 */
function pg_mail_reputation_counts($now)
{
    $since = (int) $now - PG_MAIL_REPUTATION_WINDOW;
    $sent = 0;
    $bounces = 0;
    $complaints = 0;

    if (function_exists('pg_mail_queue_ready') && pg_mail_queue_ready()) {
        $sent += (int) db_value("SELECT COUNT(*) FROM mail_outbox WHERE (status = 'sent') AND (sent_at >= '" . $since . "')");
    }

    // email_campaigns.start_time is a DATETIME: compared with a bare Unix
    // timestamp, MySQL 8 refuses the query ("Incorrect DATETIME value").
    // FROM_UNIXTIME() uses the session time zone, as the NOW() the campaign
    // job compares the column with.
    $sent += (int) db_value(
        "SELECT COUNT(*)
        FROM email_recipients
        LEFT JOIN email_campaigns ON email_campaigns.id = email_recipients.email_campaign_id
        WHERE
            (email_recipients.complete = '1')
            AND (email_recipients.failed = '0')
            AND (email_campaigns.start_time >= FROM_UNIXTIME(" . $since . "))");

    if (function_exists('pg_mail_bounces_ready') && pg_mail_bounces_ready()) {
        $row = db_item(
            "SELECT
                SUM(kind IN ('hard', 'soft')) AS bounces,
                SUM(kind = 'complaint') AS complaints
            FROM mail_bounces
            WHERE last_at >= '" . $since . "'");

        if (is_array($row)) {
            $bounces = (int) $row['bounces'];
            $complaints = (int) $row['complaints'];
        }
    }

    return array('sent' => $sent, 'bounces' => $bounces, 'complaints' => $complaints);
}

/**
 * The domain the site's mail is sent from: the DKIM domain when set (and not
 * the sample of the default config), else the domain of the site's address.
 *
 * @param string $dkim_domain
 * @param string $email_address
 * @return string
 */
function pg_mail_sending_domain($dkim_domain, $email_address)
{
    $dkim_domain = strtolower(trim((string) $dkim_domain));

    if (($dkim_domain !== '') && ($dkim_domain !== 'example.com')) {
        return $dkim_domain;
    }

    $at = strrchr((string) $email_address, '@');

    return ($at !== false) ? strtolower(trim(substr($at, 1))) : '';
}

/**
 * Everything the card shows, looked up now.
 *
 * @return array domain, transport, spf, dkim, dmarc, rates
 */
function pg_mail_domain_health()
{
    $domain = pg_mail_sending_domain(
        defined('DKIM_DOMAIN') ? DKIM_DOMAIN : '',
        defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : ''
    );
    $selector = (defined('DKIM_SELECTOR') && DKIM_SELECTOR) ? (string) DKIM_SELECTOR : 'pinegrap';
    $transport = function_exists('pg_mail_transport') ? pg_mail_transport() : 'smtp';
    $connection = defined('SYSTEM_MAIL_CONNECTION') ? (string) SYSTEM_MAIL_CONNECTION : '';
    $smtp_host = (($connection === '') && defined('SYSTEM_SMTP_HOSTNAME')) ? (string) SYSTEM_SMTP_HOSTNAME : '';
    $dkim_record = (string) db_value("SELECT description FROM files WHERE name = 'dkim.key'");

    $health = array('domain' => $domain, 'selector' => $selector, 'transport' => $transport);

    if ($domain === '') {
        $health['spf'] = $health['dkim'] = $health['dmarc'] = pg_mail_dns_line('unknown', 'no_domain');
    } elseif (!pg_mail_dns_answers($domain)) {
        // A resolver that answers nothing at all would make every record
        // look missing.
        $health['spf'] = $health['dkim'] = $health['dmarc'] = pg_mail_dns_line('unknown', 'lookup');
    } else {
        $health['spf'] = pg_mail_spf_check(pg_mail_dns_txt($domain), pg_mail_spf_expected($transport, $smtp_host));
        $health['dkim'] = pg_mail_dkim_check(pg_mail_dns_txt($selector . '._domainkey.' . $domain), $dkim_record, $transport);
        $health['dmarc'] = pg_mail_dmarc_check(pg_mail_dns_txt('_dmarc.' . $domain), $domain, defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '');
    }

    $counts = pg_mail_reputation_counts(time());
    $health['rates'] = pg_mail_reputation_rates($counts['sent'], $counts['bounces'], $counts['complaints']);

    return $health;
}

/**
 * The sentence a judged line is shown with.
 *
 * @param string $record spf, dkim, dmarc
 * @param array  $line   pg_mail_dns_line()
 * @return string
 */
function pg_mail_dns_line_text($record, array $line)
{
    $texts = array(
        'lookup'      => lang('Could not check: the DNS lookup failed.'),
        'no_domain'   => lang('Could not check: the site has no sending domain. Fill in the DKIM domain or the site e-mail address.'),
        'none'        => ($record === 'dkim') ? lang('No record with the site key was found under the selector.') : lang('No record found.'),
        'several'     => lang('More than one record: receivers ignore them all. Keep one.'),
        'all_pass'    => lang('The record lets any server send for the domain (+all).'),
        'covered'     => lang('The record names the server the site sends through.'),
        'not_named'   => lang('The record does not name the server the site sends through.'),
        'present'     => lang('The record is published.'),
        'service'     => lang('The sending service signs the mail; check the domain in its portal.'),
        'no_key'      => lang('No DKIM key generated yet.'),
        'other_key'   => lang('The published key is not the site key.'),
        'match'       => lang('The published key matches the site key.'),
        'invalid'     => lang('The record has no valid policy (p=).'),
        'none_policy' => lang('Policy none: receivers only report, nothing is stopped.'),
        'enforced'    => lang(array('string' => 'Policy {var:1}.', 'vars' => array(isset($line['policy']) ? $line['policy'] : ''))),
    );

    return isset($texts[$line['reason']]) ? $texts[$line['reason']] : '';
}

/**
 * A state as a badge.
 *
 * @param string $state        ok, warning, error, unknown
 * @param string $error_label  the error badge's text instead of "Missing or wrong"
 * @return string HTML
 */
function pg_mail_health_badge($state, $error_label = '')
{
    $badges = array(
        'ok'      => array('theme-success', 'bi-check-circle', lang('Good')),
        'warning' => array('theme-warning', 'bi-exclamation-triangle', lang('Check')),
        'error'   => array('theme-danger', 'bi-x-circle', lang('Missing or wrong')),
        'unknown' => array('theme-secondary', 'bi-question-circle', lang('Could not check')),
    );
    $badge = isset($badges[$state]) ? $badges[$state] : $badges['unknown'];

    if (($state === 'error') && ($error_label !== '')) {
        $badge[2] = $error_label;
    }

    return '<span class="badge ' . $badge[0] . '"><i class="bi ' . $badge[1] . ' me-1" aria-hidden="true"></i>' . h($badge[2]) . '</span>';
}

/**
 * The "Sending domain" card of SMTP Settings.
 *
 * @param array $health pg_mail_domain_health()
 * @param bool $wrap_column true: wrapped in a .col-12 for a .row; false: the card alone
 * @return string HTML
 */
function pg_mail_domain_health_card(array $health, $wrap_column = true)
{
    $names = array(
        'spf'   => array('SPF', $health['domain']),
        'dkim'  => array('DKIM', $health['selector'] . '._domainkey.' . $health['domain']),
        'dmarc' => array('DMARC', '_dmarc.' . $health['domain']),
    );

    $rows = '';

    foreach ($names as $record => $name) {
        $line = $health[$record];
        $id = 'mail_domain_' . $record;

        $rows .= '
            <li class="list-group-item py-4">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <strong>' . h($name[0]) . '</strong>
                    ' . pg_mail_health_badge($line['state']) . '
                    <code class="fg-secondary">' . h($name[1]) . '</code>
                </div>
                <div class="mt-2">' . h(pg_mail_dns_line_text($record, $line)) . '</div>'
            . (($line['found'] !== '') ? '
                <div class="mt-2 small fg-secondary text-break"><span class="me-1">' . h(lang('Found:')) . '</span><code>' . h($line['found']) . '</code></div>' : '')
            . (($line['copy'] !== '') ? '
                <label for="' . $id . '" class="form-label mt-3 mb-1 small">' . h(lang('Publish this TXT record:')) . '</label>
                <div class="input-group">
                    <input type="text" id="' . $id . '" class="form-control font-monospace" value="' . h($line['copy']) . '" readonly>
                    <button type="button" class="btn btn-outline theme-secondary" data-pg-copy-record="' . $id . '" data-pg-copied="' . h(lang('Copied')) . '"><i class="bi bi-clipboard me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Copy')) . '</span></button>
                </div>' : '') . '
            </li>';
    }

    $rates = $health['rates'];

    if (!$rates['judged']) {
        $rate_text = lang(array(
            'string' => '{var:1} e-mail(s) sent in the last 30 days: too few to judge the rates.',
            'vars'   => array($rates['sent']),
        ));
    } else {
        $rate_text = lang(array(
            'string' => 'Last 30 days: {var:1} sent, bounces {var:2} (limit {var:3}), complaints {var:4} (limit {var:5}).',
            'vars'   => array(
                $rates['sent'],
                pg_mail_reputation_percent($rates['bounce_rate']),
                pg_mail_reputation_percent(PG_MAIL_REPUTATION_BOUNCE_LIMIT),
                pg_mail_reputation_percent($rates['complaint_rate']),
                pg_mail_reputation_percent(PG_MAIL_REPUTATION_COMPLAINT_LIMIT),
            ),
        ));
    }

    $rows .= '
            <li class="list-group-item py-4">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <strong>' . h(lang('Bounces and complaints')) . '</strong>
                    ' . pg_mail_health_badge($rates['state'], lang('Over the limit')) . '
                </div>
                <div class="mt-2">' . h($rate_text) . '</div>
            </li>';

    $card = '
            <div class="card mb-7" id="mail_domain_card">
                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Sending domain') . '</h2>
                <div class="card-body">
                    <p class="fg-secondary">' . h(lang(array(
                        'string' => 'The DNS records of {var:1} that tell mailbox providers the site may send for it. Changes to DNS can take hours to be seen.',
                        'vars'   => array(($health['domain'] !== '') ? $health['domain'] : '-'),
                    ))) . '</p>
                    <ul class="list-group">' . $rows . '
                    </ul>
                </div>
            </div>';

    // Inside a .row the card takes its own full-width column; a caller that
    // places it in a column of its own (the status column) gets the card alone.
    return $wrap_column ? '
        <div class="col-12">' . $card . '
        </div>' : $card;
}

/**
 * The card's copy buttons.
 *
 * @return string
 */
function pg_mail_domain_health_script()
{
    return '
    <script>
        document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-pg-copy-record]");

            if (!button || !navigator.clipboard) {
                return;
            }

            var field = document.getElementById(button.getAttribute("data-pg-copy-record"));
            var label = button.querySelector(".pg-btn-label");
            var before = label.textContent;

            navigator.clipboard.writeText(field.value).then(function () {
                label.textContent = button.getAttribute("data-pg-copied");
                setTimeout(function () { label.textContent = before; }, 1500);
            });
        });
    </script>';
}
