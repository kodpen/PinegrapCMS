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
