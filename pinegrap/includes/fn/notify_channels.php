<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Chat notification channels: Slack and Microsoft Teams incoming webhooks and
 * a Telegram bot as targets of operations alerts (includes/fn/ops_alerts.php)
 * and chosen Workspace notifications. This file holds the pure part: one
 * message shape, one body builder per kind and the check of a target's
 * address or token. Each builder answers the request to send (address, JSON
 * body, headers) and sends nothing.
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
 * The target kinds, with what each needs.
 *
 * @return array<string,array> kind => array('label', 'fields')
 */
function pg_notify_channel_kinds()
{
    return array(
        'slack'    => array('label' => 'Slack', 'fields' => array('url')),
        'teams'    => array('label' => 'Microsoft Teams', 'fields' => array('url')),
        'telegram' => array('label' => 'Telegram', 'fields' => array('token', 'chat_id')),
    );
}

/**
 * Whether a target's address or token is one its service gives out. Pure.
 *
 * Slack: https://hooks.slack.com/services/…; Teams: an incoming webhook or a
 * Workflows address (webhook.office.com, logic.azure.com,
 * api.powerplatform.com); Telegram: a bot token "<digits>:<key>" and a chat
 * id (a number, negative for a group, or an @channel name).
 *
 * @param string $kind
 * @param array  $target url, or token and chat_id
 * @return bool
 */
function pg_notify_target_valid($kind, $target)
{
    $get = function ($key) use ($target) {
        return (is_array($target) && isset($target[$key]) && is_string($target[$key])) ? trim($target[$key]) : '';
    };

    if ($kind === 'telegram') {
        return (preg_match('/^\d{3,20}:[A-Za-z0-9_-]{30,64}$/', $get('token')) === 1)
            && (preg_match('/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{4,31})$/', $get('chat_id')) === 1);
    }

    $url = $get('url');

    if (($url === '') || (strlen($url) > 2000) || preg_match('/[\s"<>\\\\]/', $url)) {
        return false;
    }

    $parts = parse_url($url);

    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || (strtolower($parts['scheme']) !== 'https')
        || isset($parts['user']) || isset($parts['port'])) {
        return false;
    }

    $host = strtolower($parts['host']);
    $path = isset($parts['path']) ? $parts['path'] : '';

    if ($kind === 'slack') {
        return ($host === 'hooks.slack.com') && (strpos($path, '/services/') === 0);
    }

    if ($kind === 'teams') {
        foreach (array('.webhook.office.com', '.logic.azure.com', '.api.powerplatform.com') as $suffix) {
            if (substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
    }

    return false;
}

/**
 * One notification in the shape every builder reads. Pure.
 *
 * @param string $title one line
 * @param string $text  more lines, may be ''
 * @param string $url   an absolute https or http address to open, or ''
 * @param string $level 'error', 'warning' or 'info'
 * @param string $site  the site's name or host
 * @return array
 */
function pg_notify_message($title, $text, $url, $level, $site)
{
    $url = (string) $url;

    return array(
        'title' => trim(preg_replace('/\s+/u', ' ', (string) $title)),
        'text'  => trim(str_replace("\r\n", "\n", (string) $text)),
        'url'   => preg_match('#^https?://[^\s"<>]+$#i', $url) ? $url : '',
        'level' => in_array($level, array('error', 'warning', 'info'), true) ? $level : 'info',
        'site'  => trim((string) $site),
    );
}

/**
 * An operations alert as a message. Pure but for the alert labels.
 *
 * @param string $code   a key of pg_ops_alert_codes()
 * @param array  $codes  pg_ops_alert_codes()
 * @param string $detail what the alert noted, '' for none
 * @param string $url    the absolute address of the screen that deals with it
 * @param string $site
 * @return array|null null for a code no alert has
 */
function pg_notify_alert_message($code, $codes, $detail, $url, $site)
{
    if (!isset($codes[$code])) {
        return null;
    }

    return pg_notify_message($codes[$code]['label'], $detail, $url, ($codes[$code]['type'] === 'error') ? 'error' : 'warning', $site);
}

/**
 * Slack's incoming webhook body: a text fallback and Block Kit blocks. Slack's
 * mrkdwn reads &, < and > as markup, so they are escaped. Pure.
 *
 * @param array $message pg_notify_message()
 * @return array
 */
function pg_notify_slack_body($message)
{
    $esc = function ($text) {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $text);
    };
    $mark = array('error' => ':red_circle:', 'warning' => ':warning:', 'info' => ':information_source:');
    $line = $mark[$message['level']] . ' *' . $esc($message['title']) . '*';
    $blocks = array(
        array('type' => 'section', 'text' => array('type' => 'mrkdwn', 'text' => $line . (($message['text'] !== '') ? "\n" . $esc($message['text']) : ''))),
    );

    if ($message['site'] !== '') {
        $blocks[] = array('type' => 'context', 'elements' => array(array('type' => 'mrkdwn', 'text' => $esc($message['site']))));
    }

    if ($message['url'] !== '') {
        $blocks[] = array('type' => 'actions', 'elements' => array(array(
            'type' => 'button',
            'text' => array('type' => 'plain_text', 'text' => lang('Open')),
            'url'  => $message['url'],
        )));
    }

    return array('text' => $message['title'] . (($message['site'] !== '') ? ' (' . $message['site'] . ')' : ''), 'blocks' => $blocks);
}

/**
 * Teams' body: a message carrying one Adaptive Card (1.4), as incoming
 * webhooks and Workflows both read it. Card text is plain. Pure.
 *
 * @param array $message pg_notify_message()
 * @return array
 */
function pg_notify_teams_body($message)
{
    $colour = array('error' => 'Attention', 'warning' => 'Warning', 'info' => 'Default');
    $body = array(
        array('type' => 'TextBlock', 'text' => $message['title'], 'weight' => 'Bolder', 'size' => 'Medium', 'wrap' => true, 'color' => $colour[$message['level']]),
    );

    if ($message['text'] !== '') {
        $body[] = array('type' => 'TextBlock', 'text' => $message['text'], 'wrap' => true);
    }

    if ($message['site'] !== '') {
        $body[] = array('type' => 'TextBlock', 'text' => $message['site'], 'isSubtle' => true, 'size' => 'Small', 'wrap' => true);
    }

    $card = array(
        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
        'type'    => 'AdaptiveCard',
        'version' => '1.4',
        'body'    => $body,
    );

    if ($message['url'] !== '') {
        $card['actions'] = array(array('type' => 'Action.OpenUrl', 'title' => lang('Open'), 'url' => $message['url']));
    }

    return array(
        'type'        => 'message',
        'attachments' => array(array('contentType' => 'application/vnd.microsoft.card.adaptive', 'contentUrl' => null, 'content' => $card)),
    );
}

/**
 * Telegram's sendMessage body, in its HTML mode (only <, > and & are
 * escaped there), at most 4096 characters. Pure.
 *
 * @param array  $message pg_notify_message()
 * @param string $chat_id
 * @return array
 */
function pg_notify_telegram_body($message, $chat_id)
{
    $esc = function ($text) {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    };
    $mark = array('error' => "\xF0\x9F\x94\xB4", 'warning' => "\xE2\x9A\xA0\xEF\xB8\x8F", 'info' => "\xE2\x84\xB9\xEF\xB8\x8F");
    $text = $mark[$message['level']] . ' <b>' . $esc($message['title']) . '</b>';

    if ($message['text'] !== '') {
        $text .= "\n" . $esc($message['text']);
    }

    if ($message['site'] !== '') {
        $text .= "\n<i>" . $esc($message['site']) . '</i>';
    }

    if ($message['url'] !== '') {
        $text .= "\n" . '<a href="' . htmlspecialchars($message['url'], ENT_QUOTES, 'UTF-8') . '">' . $esc(lang('Open')) . '</a>';
    }

    if (function_exists('mb_strlen') && (mb_strlen($text, 'UTF-8') > 4096)) {
        // Cut on the plain text so no tag is left open.
        $text = mb_substr($mark[$message['level']] . ' ' . $message['title'] . "\n" . $message['text'], 0, 4000, 'UTF-8');
        return array('chat_id' => (string) $chat_id, 'text' => $text, 'disable_web_page_preview' => true);
    }

    return array('chat_id' => (string) $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true);
}

/**
 * The request that sends a message to a target. Pure.
 *
 * @param string $kind
 * @param array  $target  url, or token and chat_id
 * @param array  $message pg_notify_message()
 * @return array|null array('url', 'body' (JSON), 'headers'); null for a target
 *                    that does not pass pg_notify_target_valid()
 */
function pg_notify_request($kind, $target, $message)
{
    if (!pg_notify_target_valid($kind, $target)) {
        return null;
    }

    if ($kind === 'telegram') {
        $url = 'https://api.telegram.org/bot' . trim($target['token']) . '/sendMessage';
        $body = pg_notify_telegram_body($message, trim($target['chat_id']));
    } else {
        $url = trim($target['url']);
        $body = ($kind === 'slack') ? pg_notify_slack_body($message) : pg_notify_teams_body($message);
    }

    return array(
        'url'     => $url,
        'body'    => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'headers' => array('Content-Type: application/json; charset=utf-8'),
    );
}

/**
 * The Workspace events a target can carry, as pg_announce() names them. A
 * mention is passed on only from a public channel; an approval is announced
 * only there; a failed run names its flow only where its words may go out.
 *
 * @return array<string,string> event => label
 */
function pg_notify_events()
{
    return array(
        'workspace.app.mentioned'      => lang('An application is mentioned in a public channel'),
        'workspace.flow.run.failed'    => lang('A run of a flow failed'),
        'workspace.approval.requested' => lang('An approval is requested in a public channel'),
    );
}

/**
 * A Workspace event as a message. Pure but for the labels.
 *
 * @param string $event a key of pg_notify_events()
 * @param array  $data  what pg_announce() was given
 * @param string $base  the panel's absolute address, ending in '/'
 * @param string $site
 * @return array|null null for an event no target carries, or a mention outside a public channel
 */
function pg_notify_event_message($event, $data, $base, $site)
{
    $channel = '#' . (isset($data['channel']) ? (string) $data['channel'] : '');
    $channel_url = $base . 'workspace.php?channel=' . (int) ($data['channel_id'] ?? 0)
        . (((int) ($data['message_id'] ?? ($data['id'] ?? 0)) > 0) ? '&message=' . (int) ($data['message_id'] ?? $data['id']) : '');

    if ($event === 'workspace.app.mentioned') {
        if ((string) ($data['channel_kind'] ?? '') !== 'public') {
            return null;
        }

        $text = (string) ($data['text'] ?? '');

        if (function_exists('mb_strlen') && (mb_strlen($text, 'UTF-8') > 500)) {
            $text = mb_substr($text, 0, 499, 'UTF-8') . "\xE2\x80\xA6";
        }

        return pg_notify_message(lang(array('string' => 'An application is mentioned in {var:1}', 'vars' => array($channel))), $text, $channel_url, 'info', $site);
    }

    if ($event === 'workspace.approval.requested') {
        return pg_notify_message(lang(array('string' => 'An approval is requested in {var:1}', 'vars' => array($channel))), (string) ($data['title'] ?? ''), $channel_url, 'info', $site);
    }

    if ($event === 'workspace.flow.run.failed') {
        $flow_id = (int) ($data['flow_id'] ?? 0);

        return pg_notify_message(
            lang('A run of a flow failed'),
            isset($data['flow_name']) ? (string) $data['flow_name'] : '',
            $base . 'workspace_flows.php' . (($flow_id > 0) ? '?flow=' . $flow_id . '&run=' . (int) ($data['id'] ?? 0) : ''),
            'error',
            $site
        );
    }

    return null;
}

/**
 * A target as it is kept: the input cleaned, alert codes and events only from
 * the known lists. Pure.
 *
 * @param array    $input  id, kind, label, url / token, chat_id, codes, events, failure
 * @param string[] $codes  the known alert codes
 * @param string[] $events the known Workspace events
 * @return array|null null for an unknown kind or an address the service does not give out
 */
function pg_notify_target_clean($input, $codes, $events)
{
    $kinds = pg_notify_channel_kinds();
    $kind = isset($input['kind']) ? (string) $input['kind'] : '';

    if (!isset($kinds[$kind])) {
        return null;
    }

    $target = array(
        'id'     => preg_match('/^[a-f0-9]{8,32}$/', (string) ($input['id'] ?? '')) ? (string) $input['id'] : '',
        'kind'   => $kind,
        'label'  => trim(function_exists('mb_substr') ? mb_substr(preg_replace('/\s+/u', ' ', (string) ($input['label'] ?? '')), 0, 100, 'UTF-8') : substr((string) ($input['label'] ?? ''), 0, 100)),
        'codes'  => array_values(array_intersect($codes, array_map('strval', (array) ($input['codes'] ?? array())))),
        'events' => array_values(array_intersect($events, array_map('strval', (array) ($input['events'] ?? array())))),
    );

    foreach ($kinds[$kind]['fields'] as $field) {
        $target[$field] = trim((string) ($input[$field] ?? ''));
    }

    if (!pg_notify_target_valid($kind, $target)) {
        return null;
    }

    if ($target['label'] === '') {
        $target['label'] = $kinds[$kind]['label'];
    }

    if (isset($input['failure']) && is_array($input['failure'])) {
        $target['failure'] = $input['failure'];
    }

    return $target;
}

/**
 * Where a target sends, as much as a screen, a log line or an error may show:
 * the scheme and the host. Pure.
 *
 * @param string $kind
 * @param array  $target
 * @return string
 */
function pg_notify_target_where($kind, $target)
{
    if ($kind === 'telegram') {
        return 'https://api.telegram.org';
    }

    $host = parse_url((string) ($target['url'] ?? ''), PHP_URL_HOST);

    return is_string($host) ? 'https://' . strtolower($host) : '';
}

/**
 * A text with the target's address and token taken out, the address left as
 * its scheme and host. Every error and log line about a target goes through
 * it: the URL of a Slack or Teams webhook and a Telegram token are the
 * credential. Pure.
 *
 * @param string $text
 * @param array  $target
 * @return string
 */
function pg_notify_mask($text, $target)
{
    $text = (string) $text;
    $where = pg_notify_target_where((string) ($target['kind'] ?? ''), $target);

    foreach (array('url', 'token') as $field) {
        $secret = trim((string) ($target[$field] ?? ''));

        if ($secret !== '') {
            $text = str_replace($secret, ($field === 'url') ? $where : '***', $text);
        }
    }

    // A URL the target no longer holds (changed since the row was queued),
    // or a token in an address the provider echoed.
    $text = preg_replace('#https://hooks\.slack\.com/services/\S+#i', 'https://hooks.slack.com', $text);
    $text = preg_replace('#(https://[a-z0-9.-]+\.(?:webhook\.office\.com|logic\.azure\.com|api\.powerplatform\.com))/\S*#i', '$1', $text);
    $text = preg_replace('#bot\d{3,20}:[A-Za-z0-9_-]{30,64}#', 'bot***', $text);

    return $text;
}

/**
 * The short reason a provider gave with a refusal: Telegram's description,
 * Slack's or Teams' plain answer, cut to 200 characters. Pure.
 *
 * @param string $body
 * @return string
 */
function pg_notify_reason($body)
{
    $decoded = json_decode((string) $body, true);

    if (is_array($decoded)) {
        foreach (array('description', 'error', 'message') as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                $body = (string) $decoded[$key];
                break;
            }
        }

        if (isset($decoded['error']['message']) && is_scalar($decoded['error']['message'])) {
            $body = (string) $decoded['error']['message'];
        }
    }

    $body = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $body)));

    return function_exists('mb_substr') ? mb_substr($body, 0, 200, 'UTF-8') : substr($body, 0, 200);
}

// ----------------------------------------------------------------------------
// The targets, sealed
// ----------------------------------------------------------------------------

/**
 * The targets, by id: one sealed service entry (connect_accounts, provider
 * 'service', key notify_channels). Read once per request.
 *
 * @param bool $reload read it again
 * @return array<string,array>
 */
function pg_notify_targets($reload = false)
{
    static $targets = null;

    if (($targets === null) || $reload) {
        $data = function_exists('pg_connect_service_get') ? pg_connect_service_get('notify_channels') : array();
        $targets = (isset($data['targets']) && is_array($data['targets'])) ? $data['targets'] : array();
    }

    return $targets;
}

/**
 * @param array<string,array> $targets
 * @return bool false when the site cannot seal (no encryption key, no tables)
 */
function pg_notify_targets_save($targets)
{
    if (empty($targets)) {
        pg_connect_service_delete('notify_channels');
        pg_notify_targets(true);
        return true;
    }

    $saved = pg_connect_service_put('notify_channels', lang('Chat notifications'), array('targets' => $targets));
    pg_notify_targets(true);

    return $saved;
}

/**
 * Notes a target's last failure, or clears it (null).
 *
 * @param string     $id
 * @param array|null $failure status, at, reason (masked)
 */
function pg_notify_target_note($id, $failure)
{
    $targets = pg_notify_targets(true);

    if (!isset($targets[$id]) || (($failure === null) && !isset($targets[$id]['failure']))) {
        return;
    }

    if ($failure === null) {
        unset($targets[$id]['failure']);
    } else {
        $targets[$id]['failure'] = $failure;
    }

    pg_notify_targets_save($targets);
}

// ----------------------------------------------------------------------------
// Sending
// ----------------------------------------------------------------------------

/**
 * The site's name as a message shows it.
 *
 * @return string
 */
function pg_notify_site()
{
    return (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== '')) ? (string) ORGANIZATION_NAME : (string) HOSTNAME_SETTING;
}

/**
 * Queues a message for every target that carries the alert code or the
 * Workspace event: rows of the outbound webhook queue with webhook_id 0, the
 * payload {"target": id, "body": the built body}. The address and the token
 * stay in the sealed entry; the general job's webhook pass sends them
 * (api_webhook_deliver()).
 *
 * @param string $code    an alert code or a Workspace event
 * @param array  $message pg_notify_message()
 * @return int rows queued
 */
function pg_notify_enqueue($code, $message)
{
    $targets = pg_notify_targets();

    if (empty($targets) || !is_array($message) || !pg_schema_has('api_webhook_queue')) {
        return 0;
    }

    $queued = 0;

    foreach ($targets as $id => $target) {
        if (!in_array($code, array_merge((array) ($target['codes'] ?? array()), (array) ($target['events'] ?? array())), true)) {
            continue;
        }

        $request = pg_notify_request((string) ($target['kind'] ?? ''), $target, $message);

        if ($request === null) {
            continue;
        }

        $payload = json_encode(array('target' => (string) $id, 'body' => json_decode($request['body'], true)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        db("INSERT INTO api_webhook_queue (webhook_id, event, payload, attempts, next_attempt_at, created_timestamp)
            VALUES ('0', '" . e(substr($code, 0, 100)) . "', '" . e($payload) . "', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");

        $queued++;
    }

    return $queued;
}

/**
 * Posts a built body to a target, at once. The address is checked again
 * (the service's own hosts) and resolved once with the resolved address
 * pinned (check_address); no redirect is followed.
 *
 * @param array  $target
 * @param string $body JSON
 * @return array ok, status, error (masked: never the URL or the token)
 */
function pg_notify_post($target, $body)
{
    $kind = (string) ($target['kind'] ?? '');

    if (!pg_notify_target_valid($kind, $target)) {
        return array('ok' => false, 'status' => 0, 'error' => lang('The address or token of this target is not valid.'));
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $url = ($kind === 'telegram') ? 'https://api.telegram.org/bot' . trim($target['token']) . '/sendMessage' : trim($target['url']);

    $outcome = pg_curl_run('POST', $url, array(
        'body'          => (string) $body,
        'headers'       => array('Content-Type: application/json; charset=utf-8'),
        'timeout'       => 10,
        'check_address' => true,
    ));

    $error = '';

    if (!$outcome['ok']) {
        $error = ($outcome['status'] > 0)
            ? trim('HTTP ' . $outcome['status'] . ' ' . pg_notify_reason($outcome['body']))
            : (($outcome['error'] !== '') ? $outcome['error'] : 'HTTP 0');
    }

    return array('ok' => (bool) $outcome['ok'], 'status' => (int) $outcome['status'], 'error' => pg_notify_mask($error, $target));
}

/**
 * Sends one queued chat row (webhook_id 0), for api_webhook_deliver(): the
 * same retry ladder as a webhook; given up, the failure is noted on the
 * target, never on a subscription.
 *
 * @param array $row id, event, payload, attempts
 * @return bool true when done with the row (sent, dropped or given up)
 */
function pg_notify_deliver($row)
{
    $payload = json_decode((string) $row['payload'], true);
    $id = is_array($payload) ? (string) ($payload['target'] ?? '') : '';
    $targets = pg_notify_targets(true);

    // A target deleted since the row was queued: nowhere to go, as a
    // subscription that is gone.
    if (!isset($targets[$id]) || !isset($payload['body'])) {
        db("DELETE FROM api_webhook_queue WHERE id = '" . (int) $row['id'] . "'");
        return true;
    }

    $target = $targets[$id];
    $outcome = pg_notify_post($target, json_encode($payload['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if ($outcome['ok']) {
        db("DELETE FROM api_webhook_queue WHERE id = '" . (int) $row['id'] . "'");
        pg_notify_target_note($id, null);
        return true;
    }

    $attempts = (int) $row['attempts'] + 1;
    $retry_in = api_http_should_retry($outcome) ? api_webhook_backoff($attempts) : 0;
    $exhausted = (($retry_in === 0) || ($attempts >= api_webhook_max_attempts()));

    db("UPDATE api_webhook_queue SET
            attempts = '" . $attempts . "',
            last_status_code = '" . (int) $outcome['status'] . "',
            last_error = '" . e(substr($outcome['error'], 0, 500)) . "',
            next_attempt_at = '" . ($exhausted ? 0 : (time() + $retry_in)) . "'
        WHERE id = '" . (int) $row['id'] . "'");

    if (!$exhausted) {
        return false;
    }

    pg_notify_target_note($id, array('status' => (int) $outcome['status'], 'at' => time(), 'reason' => substr($outcome['error'], 0, 300)));

    $kinds = pg_notify_channel_kinds();

    log_activity(lang(array(
        'string' => 'A chat notification to {var:1} ({var:2}) was given up after {var:3} attempts: {var:4}',
        'vars'   => array($target['label'], $kinds[$target['kind']]['label'], $attempts, $outcome['error']),
    )), 'SYSTEM');

    return true;
}

/**
 * Sends a test message to a target at once, outside the queue. A success
 * clears the failure noted on it.
 *
 * @param string $id
 * @return array ok, status, error
 */
function pg_notify_test($id)
{
    $targets = pg_notify_targets(true);

    if (!isset($targets[$id])) {
        return array('ok' => false, 'status' => 0, 'error' => lang('This target no longer exists.'));
    }

    $message = pg_notify_message(
        lang('Test message from Pinegrap'),
        lang('This target receives the notifications chosen for it in Site Settings.'),
        URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/welcome.php',
        'info',
        pg_notify_site()
    );

    $request = pg_notify_request((string) $targets[$id]['kind'], $targets[$id], $message);

    if ($request === null) {
        return array('ok' => false, 'status' => 0, 'error' => lang('The address or token of this target is not valid.'));
    }

    $outcome = pg_notify_post($targets[$id], $request['body']);

    if ($outcome['ok']) {
        pg_notify_target_note($id, null);
    }

    return $outcome;
}

/**
 * An operations alert to the targets that carry its code (pg_ops_alert(),
 * once the send is claimed).
 *
 * @param string $code
 * @param string $detail plain text
 * @return int rows queued
 */
function pg_notify_ops_alert($code, $detail)
{
    if (empty(pg_notify_targets())) {
        return 0;
    }

    $message = pg_notify_alert_message($code, pg_ops_alert_codes(), $detail, pg_ops_alert_url($code, true), pg_notify_site());

    return ($message === null) ? 0 : pg_notify_enqueue($code, $message);
}

/**
 * A Workspace event to the targets that carry it (pg_announce()). Every
 * other event is settled without a query.
 *
 * @param string $event
 * @param array  $data
 * @return int rows queued
 */
function pg_notify_announced($event, $data)
{
    if (!in_array($event, array('workspace.app.mentioned', 'workspace.flow.run.failed', 'workspace.approval.requested'), true) || empty(pg_notify_targets())) {
        return 0;
    }

    // A message that mentions two applications is announced once for each;
    // the chat hears about it once.
    static $told = array();
    $key = $event . ':' . (int) ($data['id'] ?? 0);

    if (($event === 'workspace.app.mentioned') && isset($told[$key])) {
        return 0;
    }

    $told[$key] = true;

    $message = pg_notify_event_message($event, (array) $data, URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/', pg_notify_site());

    return ($message === null) ? 0 : pg_notify_enqueue($event, $message);
}
