<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GitHub repositories linked to Workspace channels (ws_channel_links): saving
 * a link adds a webhook to the repository through the GitHub account
 * connected for the channel_links purpose, signed with a secret of the
 * link's own; removing the link, or the account, deletes the hook again.
 * github_webhook.php receives the hook's deliveries here and writes them
 * into the channel as the "GitHub" application, once per delivery id
 * (ws_channel_link_deliveries). The lines and the other rules that need no
 * database are in includes/fn/github_links.php.
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
 * The largest delivery the receiver reads; GitHub caps a payload at 25 MB,
 * the events a link follows are far smaller.
 */
define('WS_GITHUB_WEBHOOK_MAX_BODY', 1048576);

/**
 * Deliveries one address may post in a minute.
 */
define('WS_GITHUB_WEBHOOK_PER_MINUTE', 120);

/**
 * How long a delivery id is kept against a second copy.
 */
define('WS_GITHUB_DELIVERY_DAYS', 7);

/**
 * Whether the link tables are there.
 *
 * @return bool
 */
function ws_github_links_ready()
{
    return pg_schema_has('ws_channel_links') && pg_schema_has('ws_channel_link_deliveries');
}

/**
 * A link row as the screens and the receiver read it; the sealed secret is
 * left out.
 *
 * @param array $row
 * @return array
 */
function ws_github_link_public($row)
{
    return array(
        'id'            => (int) $row['id'],
        'channel_id'    => (int) $row['channel_id'],
        'connection_id' => (int) $row['connection_id'],
        'repository'    => (string) $row['repository'],
        'events'        => pg_github_link_events_clean((string) $row['events']),
        'labels'        => pg_github_labels_clean((string) $row['labels']),
        'hook_id'       => (int) $row['hook_id'],
        'last_error'    => (string) $row['last_error'],
        'last_error_at' => (int) $row['last_error_at'],
        'created_by'    => (int) $row['created_by'],
        'created_at'    => (int) $row['created_at'],
    );
}

/**
 * @param int $id
 * @return array|null
 */
function ws_github_link($id)
{
    $row = ws_github_links_ready() ? db_item("SELECT * FROM ws_channel_links WHERE id = '" . (int) $id . "' AND provider = 'github'") : null;

    return is_array($row) ? ws_github_link_public($row) : null;
}

/**
 * Every GitHub link, or those made through one connected account.
 *
 * @param int $connection_id 0 for all
 * @return array[]
 */
function ws_github_links($connection_id = 0)
{
    if (!ws_github_links_ready()) {
        return array();
    }

    $links = array();

    foreach (db_items("SELECT * FROM ws_channel_links WHERE provider = 'github'"
        . (((int) $connection_id > 0) ? " AND connection_id = '" . (int) $connection_id . "'" : '') . "
        ORDER BY repository, id") as $row) {
        $links[] = ws_github_link_public($row);
    }

    return $links;
}

/**
 * The GitHub accounts a link can be made through.
 *
 * @return array[] pg_connect_account()
 */
function ws_github_link_accounts()
{
    $accounts = array();

    foreach (pg_connect_accounts(array('provider' => 'github', 'purpose' => 'channel_links')) as $account) {
        if ($account['status'] === 'active') {
            $accounts[] = $account;
        }
    }

    return $accounts;
}

/**
 * May this person link repositories at all: the Workspace settings and the
 * site's connections (a manager or above).
 *
 * @param array $viewer
 * @return bool
 */
function ws_github_links_can_manage($viewer)
{
    return !empty($viewer['member']) && !empty($viewer['settings']) && ((int) $viewer['role'] <= 2);
}

/**
 * The channels this person may link a repository to: the ones they may post
 * in, a private channel only as its member.
 *
 * @param array $viewer
 * @return array[] ws_channels rows
 */
function ws_github_link_channels($viewer)
{
    $channels = array();

    // ws_channels_for() gives the sidebar's short form, which has no
    // archived_at; the access check reads the channel's own row.
    foreach (ws_channels_for($viewer) as $brief) {
        if (in_array($brief['kind'], array('public', 'private'), true) && ws_can_post_channel($viewer, ws_channel($brief['id']))) {
            $channels[] = $brief;
        }
    }

    return $channels;
}

/**
 * The application the lines are written as: an api_apps row named GitHub,
 * with no scope and no secret, so it can sign in nowhere. Its id is kept as
 * a service entry of the connected accounts; made again when it was deleted.
 *
 * @param int    $owner_id who the application is made for when it is missing
 * @param string $username
 * @return int 0 when it could not be made
 */
function ws_github_app_id($owner_id = 0, $username = '')
{
    $stored = pg_connect_service_get('github_channel_app');
    $app_id = (int) ($stored['app_id'] ?? 0);

    if (($app_id > 0) && ((string) db_value("SELECT status FROM api_apps WHERE id = '" . $app_id . "'") === 'active')) {
        return $app_id;
    }

    if ((int) $owner_id <= 0) {
        return 0;
    }

    require_once PG_FUNCTIONS_DIR . '/includes/api/keys.php';

    $made = api_app_create('GitHub', lang('Writes the events of the GitHub repositories linked to Workspace channels. It has no secret and cannot sign in.'), array(), (int) $owner_id, false, (string) $username);

    if (!$made || !pg_connect_service_put('github_channel_app', 'GitHub', array('app_id' => (int) $made['id']))) {
        return 0;
    }

    return (int) $made['id'];
}

/**
 * Checks a link before it is saved.
 *
 * @param array $viewer
 * @param array $input channel_id, connection_id, repository, events, labels
 * @return array ok, error, field, and the clean values
 */
function ws_github_link_check($viewer, $input)
{
    $refuse = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    if (!ws_github_links_ready()) {
        return $refuse(lang('The database has to be updated first.'), '');
    }

    if (!ws_github_links_can_manage($viewer)) {
        return $refuse(lang('Only a manager who may change the Workspace settings can link repositories.'), '');
    }

    if (!pg_connect_can_seal()) {
        return $refuse(lang('The site has no encryption key, so the webhook\'s secret cannot be kept.'), '');
    }

    $account = pg_connect_account((int) ($input['connection_id'] ?? 0));

    if (!is_array($account) || ($account['provider'] !== 'github') || !in_array('channel_links', (array) $account['purposes'], true) || ($account['status'] !== 'active')) {
        return $refuse(lang('Choose a GitHub account connected for channel links.'), 'connection_id');
    }

    $channel = ws_channel((int) ($input['channel_id'] ?? 0));

    if (!is_array($channel) || !in_array($channel['kind'], array('public', 'private'), true) || !ws_can_post_channel($viewer, $channel)) {
        return $refuse(lang('Choose a channel you can post in.'), 'channel_id');
    }

    $repository = pg_github_repo_parse((string) ($input['repository'] ?? ''));

    if ($repository === '') {
        return $refuse(lang('Enter the repository as owner/name.'), 'repository');
    }

    $events = pg_github_link_events_clean($input['events'] ?? array());

    if (empty($events)) {
        return $refuse(lang('Choose at least one event.'), 'events');
    }

    $labels = pg_github_labels_clean((string) ($input['labels'] ?? ''));

    if (in_array('label', $events, true) && empty($labels)) {
        return $refuse(lang('Enter the labels to follow, such as ready.'), 'labels');
    }

    if (db_value("SELECT id FROM ws_channel_links
        WHERE channel_id = '" . (int) $channel['id'] . "' AND provider = 'github' AND repository = '" . e($repository) . "'") !== null) {
        return $refuse(lang('This repository is already linked to that channel.'), 'repository');
    }

    return array(
        'ok'         => true,
        'error'      => '',
        'field'      => '',
        'channel'    => $channel,
        'account'    => $account,
        'repository' => $repository,
        'events'     => $events,
        'labels'     => in_array('label', $events, true) ? $labels : array(),
    );
}

/**
 * Saves a link: the row first (its id is in the hook's address), then the
 * hook on GitHub; a refused hook takes the row back.
 *
 * @param array  $viewer
 * @param array  $input    ws_github_link_check()
 * @param string $username
 * @return array ok, error, field, id
 */
function ws_github_link_save($viewer, $input, $username)
{
    $checked = ws_github_link_check($viewer, $input);

    if (!$checked['ok']) {
        return $checked + array('id' => 0);
    }

    if (ws_github_app_id((int) $viewer['id'], $username) <= 0) {
        return array('ok' => false, 'error' => lang('The GitHub application could not be made.'), 'field' => '', 'id' => 0);
    }

    $secret = bin2hex(random_bytes(32));
    $now = time();

    $written = db("INSERT INTO ws_channel_links
        (channel_id, provider, connection_id, repository, events, labels, hook_id, secret, last_error, last_error_at, created_by, created_at, updated_at)
        VALUES (
            '" . (int) $checked['channel']['id'] . "',
            'github',
            '" . (int) $checked['account']['id'] . "',
            '" . e($checked['repository']) . "',
            '" . e(implode(',', $checked['events'])) . "',
            '" . e(implode(',', $checked['labels'])) . "',
            '0',
            '" . e(pg_connect_seal(array('secret' => $secret))) . "',
            '', '0',
            '" . (int) $viewer['id'] . "',
            '" . $now . "',
            '" . $now . "'
        )");

    $id = (int) mysqli_insert_id(db::$con);

    if (($written === false) || ($id <= 0)) {
        return array('ok' => false, 'error' => lang('The link could not be saved.'), 'field' => '', 'id' => 0);
    }

    $base = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY;
    $result = pg_connect_api($checked['account']['id'], 'POST', 'https://api.github.com/repos/' . $checked['repository'] . '/hooks', array(
        'body'    => json_encode(pg_github_hook_request(pg_github_hook_url($base, $id), $secret, $checked['events'])),
        'headers' => array('Accept: application/vnd.github+json', 'Content-Type: application/json', 'X-GitHub-Api-Version: 2022-11-28'),
        'timeout' => 15,
    ));

    $hook_id = (is_array($result['data']) && isset($result['data']['id'])) ? (int) $result['data']['id'] : 0;

    if (((int) $result['status'] !== 201) || ($hook_id <= 0)) {
        db("DELETE FROM ws_channel_links WHERE id = '" . $id . "'");

        return array('ok' => false, 'error' => pg_github_api_problem($result['status'], $result['data'], $result['error']), 'field' => 'repository', 'id' => 0);
    }

    db("UPDATE ws_channel_links SET hook_id = '" . $hook_id . "' WHERE id = '" . $id . "'");

    log_activity(lang(array(
        'string' => 'the GitHub repository {var:1} was linked to the channel #{var:2}',
        'vars'   => array($checked['repository'], (string) $checked['channel']['name']))), (string) $username);

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id);
}

/**
 * Removes a link: the hook on GitHub first (best effort: a hook already gone,
 * or an account that no longer answers, does not keep the link), then the
 * row and its delivery ids.
 *
 * @param array  $link     ws_github_link()
 * @param string $username
 * @return bool
 */
function ws_github_link_remove($link, $username)
{
    if (($link['hook_id'] > 0) && ($link['connection_id'] > 0)) {
        pg_connect_api($link['connection_id'], 'DELETE', 'https://api.github.com/repos/' . $link['repository'] . '/hooks/' . (int) $link['hook_id'], array(
            'headers' => array('Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'),
            'timeout' => 10,
        ));
    }

    db("DELETE FROM ws_channel_link_deliveries WHERE link_id = '" . (int) $link['id'] . "'");
    $deleted = db("DELETE FROM ws_channel_links WHERE id = '" . (int) $link['id'] . "'");

    log_activity(lang(array(
        'string' => 'the link of the GitHub repository {var:1} to a channel was removed',
        'vars'   => $link['repository'])), (string) $username);

    return $deleted !== false;
}

/**
 * Removes the links made through a connected account that is being ended,
 * while its token still works to delete their hooks
 * (pg_connect_disconnect()).
 *
 * @param int $connection_id
 */
function ws_github_links_account_removed($connection_id)
{
    foreach (ws_github_links((int) $connection_id) as $link) {
        ws_github_link_remove($link, $_SESSION['sessionusername'] ?? '');
    }
}

/**
 * Notes why a delivery could not be written, for the link's card; '' clears it.
 *
 * @param int    $link_id
 * @param string $error
 */
function ws_github_link_note($link_id, $error)
{
    db("UPDATE ws_channel_links
        SET last_error = '" . e(mb_substr((string) $error, 0, 255)) . "', last_error_at = '" . (($error !== '') ? time() : 0) . "'
        WHERE id = '" . (int) $link_id . "'");
}

/**
 * Writes one delivery's line into the link's channel, as the GitHub
 * application acting for the person who made the link.
 *
 * @param array  $link ws_github_link()
 * @param string $line
 * @return string '' when it was written, else why not
 */
function ws_github_link_write($link, $line)
{
    $channel = ws_channel($link['channel_id']);

    if (!is_array($channel)) {
        return lang('The channel no longer exists.');
    }

    $row = pg_load_user_row($link['created_by']);
    $actor = is_array($row) ? ws_viewer($row) : null;

    if (!$actor || !$actor['member'] || !ws_can_post_channel($actor, $channel)) {
        return lang('The person who made the link can no longer post in the channel. Link the repository again.');
    }

    $app_id = ws_github_app_id();

    if ($app_id <= 0) {
        return lang('The GitHub application was deleted. Link the repository again.');
    }

    $sent = ws_message_send($actor, $channel, $line, array('app_id' => $app_id));

    return $sent['ok'] ? '' : (string) $sent['error'];
}

/**
 * Answers GitHub and stops.
 *
 * @param int   $http
 * @param array $data
 */
function ws_github_webhook_answer($http, $data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data);

    exit;
}

/**
 * Whether the calling address is over its allowance (own bucket
 * github_webhook); logged and registered as a burst offence when it is.
 *
 * @return bool
 */
function ws_github_webhook_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || !waf_ip_is_counted($ip) || !waf_rate_exceeded(waf_ip_subject($ip), 'github_webhook', WS_GITHUB_WEBHOOK_PER_MINUTE, 60)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-github-webhook', 'rate', 5, 'github_webhook.php', WS_GITHUB_WEBHOOK_PER_MINUTE . '/1min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-github-webhook'));
    }

    if (function_exists('waf_register_offence') && function_exists('waf_mode')) {
        waf_register_offence($ip, 5, (waf_mode() === 'block'), true);
    }

    return true;
}

/**
 * A wrong link or signature, as an offence for the firewall.
 */
function ws_github_webhook_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * Answers one delivery: github_webhook.php?link=<id>.
 *
 * 405 not POST; 429 over the allowance; 503 the database not upgraded or the
 * Workspace off; 401 no such link or a wrong X-Hub-Signature-256 (an offence
 * for the firewall); 413 too large; 400 not JSON or no delivery id; else 200:
 * a ping, a delivery already written, an event the link does not follow, or
 * the line written. A line that could not be written answers 500, so GitHub
 * shows the delivery as failed, and the reason goes on the link's card.
 */
function ws_github_webhook_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

    if ($method !== 'POST') {
        header('Allow: POST');
        ws_github_webhook_answer(405, array('status' => 'method_not_allowed'));
    }

    if (ws_github_webhook_throttled()) {
        header('Retry-After: 60');
        ws_github_webhook_answer(429, array('status' => 'rate_limited'));
    }

    if (!ws_github_links_ready() || !ws_enabled()) {
        ws_github_webhook_answer(503, array('status' => 'not_ready'));
    }

    $link_id = (isset($_GET['link']) && is_string($_GET['link']) && ctype_digit($_GET['link'])) ? (int) $_GET['link'] : 0;
    $row = ($link_id > 0) ? db_item("SELECT * FROM ws_channel_links WHERE id = '" . $link_id . "' AND provider = 'github'") : null;
    $secret = is_array($row) ? (string) (pg_connect_unseal($row['secret'])['secret'] ?? '') : '';

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $body = ($length > WS_GITHUB_WEBHOOK_MAX_BODY) ? false : @file_get_contents('php://input', false, null, 0, WS_GITHUB_WEBHOOK_MAX_BODY + 1);

    if (($body === false) || (strlen($body) > WS_GITHUB_WEBHOOK_MAX_BODY)) {
        ws_github_webhook_answer(413, array('status' => 'too_large'));
    }

    // An unknown link and a wrong signature answer alike, so the address
    // tells nobody which links exist.
    if (!pg_github_signature_valid($secret, $body, (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''))) {
        ws_github_webhook_offence();
        ws_github_webhook_answer(401, array('status' => 'wrong_signature'));
    }

    $link = ws_github_link_public($row);
    $hook = strtolower(trim((string) ($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '')));
    $delivery = pg_github_delivery_id((string) ($_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? ''));
    $payload = json_decode((string) $body, true);

    if (!is_array($payload) || ($delivery === '')) {
        ws_github_webhook_answer(400, array('status' => 'unreadable'));
    }

    if ($hook === 'ping') {
        ws_github_link_note($link['id'], '');
        ws_github_webhook_answer(200, array('status' => 'pong'));
    }

    $line = pg_github_line($hook, $payload, $link);

    if ($line === '') {
        ws_github_webhook_answer(200, array('status' => 'ignored'));
    }

    // The delivery id is taken before the line is written: a copy sent at
    // the same moment finds it and writes nothing.
    if (mt_rand(1, 50) === 1) {
        db("DELETE FROM ws_channel_link_deliveries WHERE received_at < '" . (time() - WS_GITHUB_DELIVERY_DAYS * 86400) . "'");
    }

    db("INSERT IGNORE INTO ws_channel_link_deliveries (link_id, delivery_id, received_at)
        VALUES ('" . $link['id'] . "', '" . e($delivery) . "', '" . time() . "')");

    if (mysqli_affected_rows(db::$con) !== 1) {
        ws_github_webhook_answer(200, array('status' => 'duplicate'));
    }

    $error = ws_github_link_write($link, $line);

    if ($error !== '') {
        // Given back, so GitHub's Redeliver can write it once fixed.
        db("DELETE FROM ws_channel_link_deliveries WHERE link_id = '" . $link['id'] . "' AND delivery_id = '" . e($delivery) . "'");
        ws_github_link_note($link['id'], $error);
        ws_github_webhook_answer(500, array('status' => 'not_written'));
    }

    if ($link['last_error'] !== '') {
        ws_github_link_note($link['id'], '');
    }

    ws_github_webhook_answer(200, array('status' => 'written'));
}

/**
 * The card's Save and Remove (ws_action github_link_save, github_link_remove
 * on Workspace Settings; in Site Settings the named buttons
 * ws_github_link_save and ws_github_link_remove, value the link's id, with
 * the drawer's fields ws_github[...]).
 *
 * @param array         $viewer
 * @param string        $action
 * @param liveform      $liveform
 * @param array|null    $input  link_id, github_connection_id,
 *                              github_channel_id, github_repository,
 *                              github_events, github_labels (null: what
 *                              was posted)
 * @param callable|null $refuse takes a refusal (null: the liveform's error)
 */
function ws_github_links_settings_post($viewer, $action, $liveform, $input = null, $refuse = null)
{
    $post = is_array($input) ? $input : $_POST;
    $username = $_SESSION['sessionusername'] ?? '';
    $refuse = is_callable($refuse) ? $refuse : function ($sentence) use ($liveform) {
        $liveform->add_error($sentence);
    };

    if ($action === 'github_link_remove') {
        $link = ws_github_link(is_scalar($post['link_id'] ?? 0) ? (int) ($post['link_id'] ?? 0) : 0);
        $channel = $link ? ws_channel($link['channel_id']) : null;

        // A link to a channel the person cannot post in (a private channel
        // they are not in) is not theirs to remove; one whose channel is
        // gone is anyone's who may link.
        if (!$link || !ws_github_links_can_manage($viewer) || (is_array($channel) && !ws_can_post_channel($viewer, $channel))) {
            $refuse(lang('That link could not be found.'));
            return;
        }

        ws_github_link_remove($link, $username);
        $liveform->add_notice(lang('The link was removed and its webhook deleted from the repository.'));

        return;
    }

    $saved = ws_github_link_save($viewer, array(
        'connection_id' => is_scalar($post['github_connection_id'] ?? 0) ? ($post['github_connection_id'] ?? 0) : 0,
        'channel_id'    => is_scalar($post['github_channel_id'] ?? 0) ? ($post['github_channel_id'] ?? 0) : 0,
        'repository'    => is_string($post['github_repository'] ?? null) ? $post['github_repository'] : '',
        'events'        => is_array($post['github_events'] ?? null) ? $post['github_events'] : array(),
        'labels'        => is_string($post['github_labels'] ?? null) ? $post['github_labels'] : '',
    ), $username);

    if (!$saved['ok']) {
        $refuse($saved['error']);
        return;
    }

    $liveform->add_notice(lang('The repository was linked. GitHub sends a test delivery now; the channel gets the events from here on.'));
}

/**
 * The drawer's fields of Site Settings under the names Workspace Settings
 * posts (github_repository, ...). Pure.
 *
 * @param array $post $_POST
 * @return array
 */
function ws_github_links_settings_dialog_input($post)
{
    $fields = (isset($post['ws_github']) && is_array($post['ws_github'])) ? $post['ws_github'] : array();
    $input = array();

    foreach (array('connection_id', 'channel_id', 'repository', 'events', 'labels') as $field) {
        if (array_key_exists($field, $fields)) {
            $input['github_' . $field] = $fields[$field];
        }
    }

    return $input;
}

/**
 * The channels a person may link a repository to, by id.
 *
 * @param array $viewer
 * @return array id => name
 */
function ws_github_links_settings_channels($viewer)
{
    $names = array();

    foreach (ws_github_link_channels($viewer) as $channel) {
        $names[(int) $channel['id']] = (string) $channel['name'];
    }

    return $names;
}

/**
 * "Link a repository": the account, the repository, the channel, the events
 * and the labels; without a GitHub account, where to connect one. In Site
 * Settings ($form) it has no form of its own: its fields are ws_github[...]
 * joined to the settings form, its button ws_github_link_save, and nothing
 * is required (the save checks it).
 *
 * @param string      $self_url
 * @param array       $names ws_github_links_settings_channels()
 * @param string|null $form  the Site Settings form's id
 * @return string
 */
function ws_github_links_settings_form($self_url, $names, $form = null)
{
    $accounts = ws_github_link_accounts();
    $connections_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/connections.php';

    if (empty($accounts)) {
        return '<p class="small mb-0">' . h(lang('Connect a GitHub account under Settings › Connections first.')) . ' <a href="' . h($connections_url) . '">' . h(lang('Connections')) . '</a></p>';
    }

    $name = function ($field, $list = false) use ($form) {
        return ($form === null)
            ? 'name="github_' . $field . ($list ? '[]' : '') . '"'
            : 'name="ws_github[' . $field . ']' . ($list ? '[]' : '') . '" form="' . h($form) . '"';
    };
    $account_options = '';

    foreach ($accounts as $account) {
        $account_options .= '<option value="' . (int) $account['id'] . '">' . h($account['label']) . '</option>';
    }

    $channel_options = '';

    foreach ($names as $id => $channel_name) {
        $channel_options .= '<option value="' . (int) $id . '">#' . h($channel_name) . '</option>';
    }

    $boxes = '';

    foreach (pg_github_link_events() as $key => $event) {
        $boxes .= '
                <div class="form-field">
                    <input class="check" type="checkbox" ' . $name('events', true) . ' value="' . h($key) . '" id="ws_github_event_' . h($key) . '" checked>
                    <label for="ws_github_event_' . h($key) . '">' . h($event['label']) . '</label>
                </div>';
    }

    $fields = '
                    <div class="mb-4">
                        <label class="form-label" for="ws_github_connection_id">' . h(lang('GitHub account')) . '</label>
                        <select class="form-control form-control-sm" ' . $name('connection_id') . ' id="ws_github_connection_id">' . $account_options . '</select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ws_github_repository">' . h(lang('Repository')) . '</label>
                        <input class="form-control form-control-sm" type="text" ' . $name('repository') . ' id="ws_github_repository" placeholder="owner/name" maxlength="150"' . (($form === null) ? ' required' : '') . '>
                        <div class="form-text">' . h(lang('A private repository is typed as owner/name; the account only manages its webhooks and never reads its code.')) . '</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ws_github_channel_id">' . h(lang('Channel')) . '</label>
                        <select class="form-control form-control-sm" ' . $name('channel_id') . ' id="ws_github_channel_id">' . $channel_options . '</select>
                    </div>
                    <div class="mb-4">
                        <div class="form-label">' . h(lang('Events')) . '</div>
                        ' . $boxes . '
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ws_github_labels">' . h(lang('Labels to follow')) . '</label>
                        <input class="form-control form-control-sm" type="text" ' . $name('labels') . ' id="ws_github_labels" value="ready" maxlength="400">
                        <div class="form-text">' . h(lang('Comma separated. A label put on a pull request writes a line that starts with it, such as [ready].')) . '</div>
                    </div>';
    $button = '<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Link'));

    if ($form !== null) {
        return $fields . '
                    <button type="submit" name="ws_github_link_save" value="1" form="' . h($form) . '" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . $button . '</button>';
    }

    return '
                <form method="post" action="' . h($self_url) . '" class="mt-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="github_link_save">
                    ' . $fields . '
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . $button . '</button>
                </form>';
}

/**
 * The "GitHub repositories" card of the Workspace settings. In Site Settings
 * (Channels, mail and voice, with $form) Remove is a named submit button of
 * the settings form (ws_github_link_remove, value the link's id) and Link a
 * repository opens the drawer ws_settings_github_link_drawer() draws.
 *
 * @param string      $self_url
 * @param array       $viewer
 * @param string|null $form the Site Settings form's id
 * @return string
 */
function ws_github_links_settings_card($self_url, $viewer, $form = null)
{
    if (!ws_github_links_ready() || !ws_github_links_can_manage($viewer)) {
        return '';
    }

    $names = ws_github_links_settings_channels($viewer);
    $catalogue = pg_github_link_events();
    $list = '';
    $ask = 'return confirm(' . h(json_encode(lang('Remove this link and delete its webhook from the repository?'))) . ')';

    foreach (ws_github_links() as $link) {
        $channel = ws_channel($link['channel_id']);

        // A private channel the person is not in stays out of sight.
        if (is_array($channel) && !isset($names[$link['channel_id']]) && ($channel['kind'] !== 'public')) {
            continue;
        }

        $events = array();

        foreach ($link['events'] as $event) {
            $events[] = ($event === 'label') ? lang('Labels') . ': ' . implode(', ', $link['labels']) : $catalogue[$event]['label'];
        }

        $line = '
                <div class="ws-grow">
                    <div><i class="bi bi-github me-1" aria-hidden="true"></i><span class="fw-semibold">' . h($link['repository']) . '</span>
                        <span class="fg-secondary">→ #' . h(is_array($channel) ? (string) $channel['name'] : lang('deleted channel')) . '</span></div>
                    <div class="small fg-secondary">' . h(implode(' · ', $events)) . '</div>
                    ' . (($link['last_error'] !== '') ? '<div class="small fg-danger">' . h(date('d.m.Y H:i', $link['last_error_at'])) . ': ' . h($link['last_error']) . '</div>' : '') . '
                </div>';

        if ($form !== null) {
            $list .= '
            <div class="d-flex align-items-start gap-3 border-bottom py-3">' . $line . '
                <button type="submit" name="ws_github_link_remove" value="' . (int) $link['id'] . '" form="' . h($form) . '" class="btn btn-sm btn-outline theme-danger rounded-pill" onclick="' . $ask . '">' . h(lang('Remove')) . '</button>
            </div>';
            continue;
        }

        $list .= '
            <form method="post" action="' . h($self_url) . '" class="d-flex align-items-start gap-3 border-bottom py-3" id="ws-github-link-' . $link['id'] . '">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="github_link_remove">
                <input type="hidden" name="link_id" value="' . $link['id'] . '">' . $line . '
                <button type="submit" class="btn btn-sm btn-outline theme-danger rounded-pill" onclick="' . $ask . '">' . h(lang('Remove')) . '</button>
            </form>';
    }

    $none = ($list === '');

    if ($none) {
        $list = '<p class="small fg-secondary mb-0">' . h(lang('No repository is linked yet.')) . '</p>';
    }

    $intro = '<p class="small fg-secondary">' . h(lang('A linked repository writes its CI results, pull requests and labels into a channel. The site adds the webhook itself; nothing is set up in the repository.')) . '</p>';

    if ($form !== null) {
        return '
    <div id="pgset-ws-github" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0 ws-grow">' . h(lang('GitHub repositories')) . '</span>
                <button type="button" class="btn btn-sm btn-outline theme-primary rounded-pill" data-bs-toggle="drawer" data-bs-target="#pgset-ws-github-link" aria-controls="pgset-ws-github-link"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Link a repository')) . '</button>
            </div>
            <div class="card-body">
                ' . $intro . '
                ' . $list . '
            </div>
        </div>
    </div>';
    }

    return '
        <div class="card mt-7" id="ws-github">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-github me-1" aria-hidden="true"></i>' . h(lang('GitHub repositories')) . '</h2></div>
            <div class="card-body">
                ' . $intro . '
                ' . $list . '
                <details class="mt-5"' . ($none ? ' open' : '') . '>
                    <summary class="small fw-semibold">' . h(lang('Link a repository')) . '</summary>
                    ' . ws_github_links_settings_form($self_url, $names) . '
                </details>
            </div>
        </div>';
}

/**
 * The Site Settings drawer of the GitHub repositories card: Link a
 * repository.
 *
 * @param array  $viewer
 * @param string $form      the settings form's id
 * @param bool   $in_dialog pg_settings_drawer()'s
 * @return string HTML
 */
function ws_settings_github_link_drawer($viewer, $form, $in_dialog)
{
    return pg_settings_drawer('pgset-ws-github-link', h(lang('Link a repository')),
        ws_github_links_settings_form('', ws_github_links_settings_channels($viewer), $form), $in_dialog);
}
