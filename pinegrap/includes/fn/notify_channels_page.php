<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Chat notification targets on Connections: Slack's, Microsoft Teams' and
 * Telegram's pages list that service's targets (name, alert codes,
 * Workspace events, Save, "Save and send a test", Delete) and add one; the
 * Chat notifications card of Site Settings › Updates and maintenance only
 * leads there. The targets stay in the sealed notify_channels entry
 * (includes/fn/notify_channels.php); the queue and the test are unchanged.
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
 * The address of a chat service's page on Connections. Pure.
 *
 * @param string $kind slack, teams or telegram
 * @return string
 */
function pg_notify_connections_url($kind)
{
    return (defined('PATH') ? PATH : '') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '')
        . '/connections.php?service=' . rawurlencode((string) $kind);
}

/**
 * The alert codes in the groups a target's block lists them under: the
 * software's own, the sales watch's and the deliveries'. A group without a
 * code is left out. Pure.
 *
 * @param array $codes pg_ops_alert_codes(): code => array('label', 'group'?)
 * @return array list of array('title', 'options' => code => label)
 */
function pg_notify_alert_groups($codes)
{
    $groups = array(
        'ops'      => array('title' => lang('Operations alerts'), 'options' => array()),
        'sales'    => array('title' => lang('Sales watch'), 'options' => array()),
        'shipping' => array('title' => lang('Deliveries'), 'options' => array()),
    );

    foreach ((array) $codes as $code => $alert) {
        $group = (isset($alert['group']) && isset($groups[$alert['group']]) && ($alert['group'] !== 'ops')) ? $alert['group'] : 'ops';
        $groups[$group]['options'][(string) $code] = isset($alert['label']) ? (string) $alert['label'] : (string) $code;
    }

    return array_values(array_filter($groups, function ($group) {
        return !empty($group['options']);
    }));
}

/**
 * What a write on a chat service's page does to the targets. Only a target
 * of that service is saved or deleted; the others are kept as they are. A
 * new target whose address or token the service does not give out is
 * marked and nothing is written. Pure.
 *
 * @param string $action  notify_save, notify_add or notify_delete
 * @param array  $post    notify_target (id), notify_label, notify_codes,
 *                        notify_events; notify_new_label, notify_new_url,
 *                        notify_new_token, notify_new_chat_id,
 *                        notify_new_codes, notify_new_events; connection_id
 *                        (the id to delete)
 * @param string $kind    the page's service
 * @param array  $targets pg_notify_targets()
 * @param array  $codes   the known alert codes
 * @param array  $events  the known Workspace events
 * @param string $new_id  the id a new target gets
 * @return array targets (what to keep), marks (field => message), id (the
 *               target written, '' for none), changed (bool)
 */
function pg_notify_page_apply($action, $post, $kind, $targets, $codes, $events, $new_id)
{
    $get = function ($key) use ($post) {
        return (isset($post[$key]) && is_string($post[$key])) ? $post[$key] : '';
    };
    $list = function ($key) use ($post) {
        return (isset($post[$key]) && is_array($post[$key])) ? $post[$key] : array();
    };

    $targets = (array) $targets;
    $out = array('targets' => $targets, 'marks' => array(), 'id' => '', 'changed' => false);
    $own = function ($id) use ($targets, $kind) {
        return ($id !== '') && isset($targets[$id]) && is_array($targets[$id]) && (($targets[$id]['kind'] ?? '') === $kind);
    };

    if ($action === 'notify_add') {

        $new = pg_notify_target_clean(array(
            'id'      => $new_id,
            'kind'    => $kind,
            'label'   => $get('notify_new_label'),
            'url'     => $get('notify_new_url'),
            'token'   => $get('notify_new_token'),
            'chat_id' => $get('notify_new_chat_id'),
            'codes'   => $list('notify_new_codes'),
            'events'  => $list('notify_new_events'),
        ), $codes, $events);

        if (($new === null) || ($new['id'] === '')) {
            $out['marks'][($kind === 'telegram') ? 'notify_new_token' : 'notify_new_url']
                = lang('This is not an address or token the chosen service gives out. Check the bot token and the chat ID for Telegram.');
            return $out;
        }

        $out['targets'][$new['id']] = $new;
        $out['id'] = $new['id'];
        $out['changed'] = true;

        return $out;
    }

    if ($action === 'notify_delete') {

        $id = $get('connection_id');

        if ($own($id)) {
            unset($out['targets'][$id]);
            $out['id'] = $id;
            $out['changed'] = true;
        }

        return $out;
    }

    if ($action === 'notify_save') {

        $id = $get('notify_target');

        if (!$own($id)) {
            return $out;
        }

        $clean = pg_notify_target_clean(array(
            'label'  => $get('notify_label'),
            'codes'  => $list('notify_codes'),
            'events' => $list('notify_events'),
        ) + $targets[$id], $codes, $events);

        $out['id'] = $id;

        // Compared without the order of the keys: a stored target may hold
        // them in another order than the clean one.
        $before = $targets[$id];
        $after = $clean;

        if ($after !== null) {
            ksort($before);
            ksort($after);
        }

        if (($clean !== null) && ($after !== $before)) {
            $out['targets'][$id] = $clean;
            $out['changed'] = true;
        }
    }

    return $out;
}

/**
 * A list of check boxes, two a row on a wide screen.
 *
 * @param string $name    the field's name, without []
 * @param string $prefix  the boxes' id prefix
 * @param array  $options value => label
 * @param array  $chosen  the values ticked
 * @return string
 */
function pg_notify_page_checks($name, $prefix, $options, $chosen)
{
    $out = '';

    foreach ($options as $value => $label) {
        $id = $prefix . '_' . substr(md5((string) $value), 0, 8);
        $out .= '
						<div class="col-12 md:col-6 d-flex align-items-center gap-2">
							<input type="checkbox" class="pg-checkbox" name="' . h($name) . '[]" value="' . h($value) . '" id="' . h($id) . '"' . (in_array((string) $value, array_map('strval', (array) $chosen), true) ? ' checked' : '') . '>
							<label class="pg-text-2" for="' . h($id) . '">' . h($label) . '</label>
						</div>';
    }

    return $out;
}

/**
 * The alert codes and Workspace events of a block, each group under its
 * label.
 *
 * @param string $prefix  the field names' prefix (notify_ or notify_new_)
 * @param string $id      the boxes' id part
 * @param array  $groups  pg_notify_alert_groups()
 * @param array  $events  pg_notify_events()
 * @param array  $codes   the codes ticked
 * @param array  $chosen  the events ticked
 * @return string
 */
function pg_notify_page_choices($prefix, $id, $groups, $events, $codes, $chosen)
{
    $out = '';

    foreach ($groups as $group) {
        $out .= '
				<div>
					<div class="pg-field-label">' . h($group['title']) . '</div>
					<div class="row gy-2">' . pg_notify_page_checks($prefix . 'codes', $id . '_code', $group['options'], $codes) . '
					</div>
				</div>';
    }

    if (!empty($events)) {
        $out .= '
				<div>
					<div class="pg-field-label">' . h(lang('Workspace')) . '</div>
					<div class="row gy-2">' . pg_notify_page_checks($prefix . 'events', $id . '_event', $events, $chosen) . '
					</div>
				</div>';
    }

    return $out;
}

/**
 * One target's block: its own form with its name, alert codes and
 * Workspace events, Save, "Save and send a test" and Delete. Its address or
 * token is never drawn back, only where it sends.
 *
 * @param string $kind
 * @param array  $target
 * @param array  $groups pg_notify_alert_groups()
 * @param array  $events pg_notify_events()
 * @return string
 */
function pg_notify_page_target_form($kind, $target, $groups, $events)
{
    $kinds = pg_notify_channel_kinds();
    $id = (string) ($target['id'] ?? '');
    $label = (string) ($target['label'] ?? '');
    $failure = (isset($target['failure']) && is_array($target['failure'])) ? $target['failure'] : null;

    return '
			<form method="post" action="connections.php" class="pg-card-inner p-4 mb-4 connections-app-form" id="notify-target-' . h($id) . '">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="notify_save">
				<input type="hidden" name="connections_return" value="' . h($kind) . '">
				<input type="hidden" name="notify_target" value="' . h($id) . '">
				<div>
					<label class="pg-field-label" for="notify_label_' . h($id) . '">' . h(lang('Name')) . '</label>
					<input type="text" class="pg-input" id="notify_label_' . h($id) . '" name="notify_label" value="' . h($label) . '" maxlength="100">
					<div class="pg-field-help">' . h($kinds[$kind]['label'] . ' · ' . pg_notify_target_where($kind, $target)) . '</div>
				</div>' . (($failure !== null) ? '
				<div class="alert theme-danger small py-3 mb-0">' . lang(array(
					'string' => 'Last failure {var:1}: {var:2}',
					'vars'   => array(h(date('Y-m-d H:i', (int) ($failure['at'] ?? 0))), h((string) ($failure['reason'] ?? ''))),
				)) . '</div>' : '')
				. pg_notify_page_choices('notify_', 'notify_' . $id, $groups, $events, (array) ($target['codes'] ?? array()), (array) ($target['events'] ?? array())) . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
					<button type="submit" name="submit_test" value="1" class="pg-button pg-button-sm pg-button-quiet"><i class="bi bi-send me-1" aria-hidden="true"></i>' . h(lang('Save and send a test')) . '</button>
					<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action" data-action="notify_delete" data-id="' . h($id) . '"
						data-confirm="' . h(lang(array('string' => '{var:1} is deleted: its alerts and Workspace events no longer go to this chat.', 'vars' => $label))) . '">
						<i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Delete')) . '</button>
				</div>
			</form>';
}

/**
 * Add a target of the page's service: its name, its webhook address or bot
 * token and chat ID (password boxes), the alert codes (all ticked) and the
 * Workspace events.
 *
 * @param string $kind
 * @param array  $groups pg_notify_alert_groups()
 * @param array  $events pg_notify_events()
 * @return string
 */
function pg_notify_page_add_form($kind, $groups, $events)
{
    $all = array();

    foreach ($groups as $group) {
        $all = array_merge($all, array_map('strval', array_keys($group['options'])));
    }

    if ($kind === 'telegram') {
        $where = '
				<div>
					<label class="pg-field-label" for="notify_new_token">' . h(lang('Bot token')) . '</label>
					<input type="password" class="pg-input font-monospace" id="notify_new_token" name="notify_new_token" value="" maxlength="100" autocomplete="new-password" spellcheck="false">
				</div>
				<div>
					<label class="pg-field-label" for="notify_new_chat_id">' . h(lang('Chat ID')) . '</label>
					<input type="text" class="pg-input font-monospace" id="notify_new_chat_id" name="notify_new_chat_id" value="" maxlength="40" autocomplete="off" spellcheck="false">
					<div class="pg-field-help">' . h(lang('A number (negative for a group) or @channelname. The bot has to be a member of the chat.')) . '</div>
				</div>';
    } else {
        $where = '
				<div>
					<label class="pg-field-label" for="notify_new_url">' . h(lang('Webhook address')) . '</label>
					<input type="password" class="pg-input font-monospace" id="notify_new_url" name="notify_new_url" value="" maxlength="2000" autocomplete="new-password" spellcheck="false">
					<div class="pg-field-help">' . h(lang('Slack: an incoming webhook (https://hooks.slack.com/services/…). Teams: an incoming webhook or a Workflows address. The address is a password: it is kept encrypted and not shown again.')) . '</div>
				</div>';
    }

    return '
			<form method="post" action="connections.php" class="connections-app-form" id="notify-add">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="notify_add">
				<input type="hidden" name="connections_return" value="' . h($kind) . '">
				<div class="pg-group-label">' . h(lang('Add a target')) . '</div>
				<div>
					<label class="pg-field-label" for="notify_new_label">' . h(lang('Name')) . '</label>
					<input type="text" class="pg-input" id="notify_new_label" name="notify_new_label" value="" maxlength="100">
				</div>' . $where
				. pg_notify_page_choices('notify_new_', 'notify_new', $groups, $events, $all, array()) . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Add a target')) . '</button>
				</div>
			</form>';
}

/**
 * "Connection details" on a chat service's page: that service's targets,
 * each in its own block, then Add a target. A site that cannot seal gets a
 * note instead; a site whose Webhook delivery job is off is told so.
 *
 * @param string $kind
 * @param array  $targets pg_notify_targets()
 * @param array  $groups  pg_notify_alert_groups()
 * @param array  $events  pg_notify_events()
 * @param bool   $ready   the targets can be sealed
 * @param bool   $job_on  the Webhook delivery job is selected
 * @return string
 */
function pg_notify_page_details($kind, $targets, $groups, $events, $ready, $job_on)
{
    $body = '';

    if (!$ready) {

        $body = '<div class="alert theme-secondary small py-3 mb-0">' . h(lang('Chat notifications keep their addresses and tokens encrypted, which needs the site\'s encryption key and the connected accounts tables. Run the upgrade, and check that the encryption key is set.')) . '</div>';

    } else {

        if (!$job_on) {
            $body .= '<div class="alert theme-warning small py-3 mb-4">' . h(lang('Messages are sent by the Webhook delivery job, which has to be selected under Cron Jobs. The test button sends at once.')) . '</div>';
        }

        foreach ((array) $targets as $target) {
            if (is_array($target) && (($target['kind'] ?? '') === $kind) && (($target['id'] ?? '') !== '')) {
                $body .= pg_notify_page_target_form($kind, $target, $groups, $events);
            }
        }

        $body .= pg_notify_page_add_form($kind, $groups, $events);
    }

    return '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('Operations alerts and chosen Workspace events can also go to a Slack or Microsoft Teams channel through an incoming webhook, or to a Telegram chat through a bot. Messages go out with the webhook deliveries and are retried the same way.')) . '</p>
			' . $body . '
		</section>';
}

/**
 * How many targets each chat service has. Pure.
 *
 * @param array $targets pg_notify_targets()
 * @return array kind => count, every kind present
 */
function pg_notify_target_counts($targets)
{
    $counts = array_fill_keys(array_keys(pg_notify_channel_kinds()), 0);

    foreach ((array) $targets as $target) {
        $kind = is_array($target) ? (string) ($target['kind'] ?? '') : '';

        if (isset($counts[$kind])) {
            $counts[$kind]++;
        }
    }

    return $counts;
}

/**
 * The Chat notifications card of Site Settings › Updates and maintenance:
 * no target here, a line for each service with how many targets it has and
 * the way to its page on Connections. Pure.
 *
 * @param array $counts pg_notify_target_counts()
 * @return string
 */
function pg_notify_settings_card_html($counts)
{
    $out = '
                    <div class="col-12 form-text">' . h(lang('Operations alerts and chosen Workspace events can also go to a Slack or Microsoft Teams channel through an incoming webhook, or to a Telegram chat through a bot. Messages go out with the webhook deliveries and are retried the same way.')) . '</div>';

    foreach (pg_notify_channel_kinds() as $kind => $row) {
        $count = isset($counts[$kind]) ? (int) $counts[$kind] : 0;

        $out .= '
                    <div class="col-12" id="pgsub-chat-notify-' . h($kind) . '">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="fw-semibold">' . h($row['label']) . '</span>' . (($count > 0)
                            ? '<span class="pg-pill pg-tone-teal">' . h(($count === 1) ? lang('1 target') : lang(array('string' => '{var:1} targets', 'vars' => (string) $count))) . '</span>'
                            : '<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>') . '
                            <a class="pg-button pg-button-quiet pg-button-sm" href="' . h(pg_notify_connections_url($kind)) . '">' . h(($count > 0) ? lang('Manage in Connections') : lang('Connect in Connections')) . '</a>
                        </div>
                    </div>';
    }

    $out .= '
                    <div class="col-12 form-text">' . h(lang('The targets, their alerts and Workspace events, and the test message are set on each service\'s page of Connections.')) . '</div>';

    return $out;
}
