<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Mailchimp's page of Connections (connections.php?service=mailchimp): the
 * sync switch, the API key (never drawn back), the list, the store, how far
 * back and how much a run syncs, and automation. They are config row
 * columns, read by mailchimp.php. A save checks the key, the list and the
 * store at Mailchimp first and writes nothing while one is refused; a store
 * Mailchimp does not have yet is created with the site's name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mailchimp_key.php');

/**
 * The settings as stored. The store id falls back to the site's host name,
 * the name the store is created with.
 *
 * @return array enabled, key_stored, list, store, days, limit, automation
 */
function pg_mailchimp_settings_before()
{
    $row = db_item("SELECT mailchimp, mailchimp_key, mailchimp_list_id, mailchimp_store_id, mailchimp_sync_days, mailchimp_sync_limit, mailchimp_automation FROM config");
    $row = is_array($row) ? $row : array();

    $store = isset($row['mailchimp_store_id']) ? (string) $row['mailchimp_store_id'] : '';

    return array(
        'enabled'    => !empty($row['mailchimp']),
        'key_stored' => isset($row['mailchimp_key']) && ((string) $row['mailchimp_key'] !== ''),
        'list'       => isset($row['mailchimp_list_id']) ? (string) $row['mailchimp_list_id'] : '',
        'store'      => ($store !== '') ? $store : (defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : ''),
        'days'       => isset($row['mailchimp_sync_days']) ? (int) $row['mailchimp_sync_days'] : 0,
        'limit'      => isset($row['mailchimp_sync_limit']) ? (int) $row['mailchimp_sync_limit'] : 0,
        'automation' => !empty($row['mailchimp_automation']),
    );
}

/**
 * What a save would write, and the fields refused before Mailchimp is
 * asked. Pure but for lang().
 *
 * @param array  $post       the request's fields
 * @param string $stored_key config.mailchimp_key
 * @return array enabled, key, list, store, days, limit, automation, marks (field => sentence)
 */
function pg_mailchimp_settings_decide(array $post, $stored_key)
{
    $text = function ($name) use ($post) {
        return (isset($post[$name]) && is_string($post[$name])) ? trim($post[$name]) : '';
    };

    $decision = array(
        'enabled'    => !empty($post['mailchimp']),
        // The key is never drawn back: an empty box keeps the stored key,
        // the tick removes it.
        'key'        => pg_mailchimp_key_for_save($text('mailchimp_key'), (string) $stored_key, !empty($post['mailchimp_key_clear'])),
        'list'       => $text('mailchimp_list_id'),
        'store'      => $text('mailchimp_store_id'),
        'days'       => pg_parse_whole_number($text('mailchimp_sync_days'), 65535),
        'limit'      => pg_parse_whole_number($text('mailchimp_sync_limit'), 4294967295),
        'automation' => !empty($post['mailchimp_automation']),
        'marks'      => array(),
    );

    if (!$decision['enabled']) {
        return $decision;
    }

    if ($decision['key'] === '') {
        $decision['marks']['mailchimp_key'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('API Key')));

    // The data center at the end of the key is the host the key is sent
    // to; a key without a plain one is refused before any request.
    } elseif (pg_mailchimp_dc($decision['key']) === '') {
        $decision['marks']['mailchimp_key'] = lang('The API key must end with its data center, like -us6.');
    }

    if ($decision['list'] === '') {
        $decision['marks']['mailchimp_list_id'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('List ID')));
    }

    if ($decision['store'] === '') {
        $decision['marks']['mailchimp_store_id'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('Store ID')));
    }

    return $decision;
}

/**
 * The decision of a save, the key, the list and the store asked about at
 * Mailchimp when the sync is on. A refusal is marked on its field.
 *
 * @param array  $post
 * @param object $liveform
 * @return array the decision of pg_mailchimp_settings_decide()
 */
function pg_mailchimp_settings_check(array $post, $liveform)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');

    $decision = pg_mailchimp_settings_decide($post, (string) db_value("SELECT mailchimp_key FROM config"));

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    if (!$decision['enabled'] || $liveform->check_form_errors()) {
        return $decision;
    }

    require_once(PG_FUNCTIONS_DIR . '/mailchimp.php');

    $key = $decision['key'];
    $reason = function ($answer) use ($key) {
        return h(pg_mail_settings_mailchimp_reason($answer['message'], $key));
    };

    // Is the key a key at all.
    $answer = mailchimp_request(array('path' => '/ping', 'key' => $key));

    if ($answer['status'] == 'error') {
        $liveform->mark_error('mailchimp_key', lang('Sorry, the API key is not valid') . '. ' . $reason($answer));

        return $decision;
    }

    $answer = mailchimp_request(array('path' => '/lists/' . $decision['list'] . '?fields=id', 'key' => $key));

    if ($answer['status'] == 'error') {
        $liveform->mark_error('mailchimp_list_id', lang('Sorry, the List ID is not valid') . '. ' . $reason($answer));

        return $decision;
    }

    // A store that is not there yet is not an error: it is created here.
    $answer = mailchimp_request(array(
        'path'  => '/ecommerce/stores/' . $decision['store'] . '?fields=id,is_syncing',
        'key'   => $key,
        'quiet' => true));

    $missing = (($answer['status'] == 'error') && ($answer['mailchimp_response']['status'] == 404));

    if (($answer['status'] == 'error') && !$missing) {
        $liveform->mark_error('mailchimp_store_id', lang('Sorry, the Store ID is not valid') . '. ' . $reason($answer));

        return $decision;
    }

    if ($missing) {
        $store = array(
            'id'            => $decision['store'],
            'list_id'       => $decision['list'],
            'name'          => HOSTNAME_SETTING,
            'platform'      => 'Pinegrap',
            'domain'        => HOSTNAME_SETTING,
            'is_syncing'    => !$decision['automation'],
            'email_address' => EMAIL_ADDRESS,
            'currency_code' => BASE_CURRENCY_CODE,
        );

        $answer = mailchimp_request(array('method' => 'post', 'path' => '/ecommerce/stores', 'data' => $store, 'key' => $key));

        if ($answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_store_id', lang('Sorry, the store could not be created') . '. ' . $reason($answer));
        }

        return $decision;
    }

    // is_syncing is the opposite of automation: while the store is syncing
    // its history, Mailchimp holds the automations back.
    $store = $answer['mailchimp_response'];

    if (isset($store['is_syncing']) && ($store['is_syncing'] == $decision['automation'])) {
        $store['is_syncing'] = !$decision['automation'];

        $answer = mailchimp_request(array('method' => 'patch', 'path' => '/ecommerce/stores/' . $decision['store'], 'data' => $store, 'key' => $key));

        if ($answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_automation', lang('Sorry, automation could not be updated') . '. ' . $reason($answer));
        }
    }

    return $decision;
}

/**
 * Writes a decision to the config row.
 *
 * @param array $decision of pg_mailchimp_settings_decide()
 * @param int   $user_id
 */
function pg_mailchimp_settings_save(array $decision, $user_id)
{
    db("UPDATE config
        SET
            mailchimp = '" . ($decision['enabled'] ? 1 : 0) . "',
            mailchimp_key = '" . escape($decision['key']) . "',
            mailchimp_list_id = '" . escape($decision['list']) . "',
            mailchimp_store_id = '" . escape($decision['store']) . "',
            mailchimp_sync_days = '" . (int) $decision['days'] . "',
            mailchimp_sync_limit = '" . (int) $decision['limit'] . "',
            mailchimp_automation = '" . ($decision['automation'] ? 1 : 0) . "',
            last_modified_user_id = '" . (int) $user_id . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");
}

/**
 * Disconnect: the sync is switched off and the key removed; the list and
 * the store, which are no secrets, stay for a later connection.
 *
 * @param int $user_id
 */
function pg_mailchimp_settings_forget($user_id)
{
    db("UPDATE config
        SET
            mailchimp = '0',
            mailchimp_key = '',
            last_modified_user_id = '" . (int) $user_id . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");
}

/**
 * The fields of "Connection details", in the look of Connections' pages.
 *
 * @param array $app of pg_mailchimp_settings_before()
 * @return string HTML
 */
function pg_mailchimp_settings_fields(array $app)
{
    return '
                <div class="d-flex align-items-start gap-3">
                    <input type="checkbox" class="pg-switch" role="switch" name="mailchimp" value="1" id="mailchimp"' . ($app['enabled'] ? ' checked' : '') . '>
                    <div>
                        <label class="fw-semibold" for="mailchimp">' . h(lang('MailChimp Sync')) . '</label>
                        <div class="pg-field-help mt-0">' . h(lang('Auto-export customers, orders, & products to MailChimp regularly. Requires cron job (job.php).')) . '</div>
                    </div>
                </div>
                <div>
                    <label class="pg-field-label" for="mailchimp_key">' . h(lang('API Key')) . '</label>
                    <input type="password" class="pg-input font-monospace" id="mailchimp_key" name="mailchimp_key" value="" maxlength="100" autocomplete="new-password" spellcheck="false"' . ($app['key_stored'] ? ' placeholder="' . h(lang('Leave blank to keep the stored key')) . '"' : '') . '>
                    <div class="pg-field-help">' . h($app['key_stored'] ? lang('A secret is saved (never shown here). Type a new value only to replace it.') : lang('The API key must end with its data center, like -us6.')) . '</div>' . ($app['key_stored'] ? '
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <input value="1" class="pg-checkbox" type="checkbox" id="mailchimp_key_clear" name="mailchimp_key_clear">
                        <label for="mailchimp_key_clear">' . h(lang('Remove the stored key')) . '</label>
                    </div>' : '') . '
                </div>
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="pg-field-label" for="mailchimp_list_id">' . h(lang('List ID')) . '</label>
                        <input type="text" class="pg-input" id="mailchimp_list_id" name="mailchimp_list_id" value="' . h($app['list']) . '" maxlength="100" spellcheck="false">
                    </div>
                    <div class="col-md-6">
                        <label class="pg-field-label" for="mailchimp_store_id">' . h(lang('Store ID')) . '</label>
                        <input type="text" class="pg-input" id="mailchimp_store_id" name="mailchimp_store_id" value="' . h($app['store']) . '" maxlength="100" spellcheck="false">
                    </div>
                    <div class="col-md-6">
                        <label class="pg-field-label" for="mailchimp_sync_days">' . h(lang('Historical Sync')) . '</label>
                        <input type="text" class="pg-input text-end" id="mailchimp_sync_days" name="mailchimp_sync_days" value="' . h($app['days'] ? (string) $app['days'] : '') . '" maxlength="6" inputmode="numeric" aria-describedby="mailchimp_sync_days_help">
                        <div class="pg-field-help" id="mailchimp_sync_days_help">' . h(lang('Set how far in the past to sync orders. Leave blank to sync all historical orders.')) . '</div>
                    </div>
                    <div class="col-md-6">
                        <label class="pg-field-label" for="mailchimp_sync_limit">' . h(lang('Limit Sync')) . '</label>
                        <input type="text" class="pg-input text-end" id="mailchimp_sync_limit" name="mailchimp_sync_limit" value="' . h($app['limit'] ? (string) $app['limit'] : '') . '" maxlength="10" inputmode="numeric" aria-describedby="mailchimp_sync_limit_help">
                        <div class="pg-field-help" id="mailchimp_sync_limit_help">' . h(lang('max number of orders to sync each time cron job runs')) . '</div>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3">
                    <input type="checkbox" class="pg-switch" role="switch" name="mailchimp_automation" value="1" id="mailchimp_automation"' . ($app['automation'] ? ' checked' : '') . '>
                    <div>
                        <label class="fw-semibold" for="mailchimp_automation">' . h(lang('Automation')) . '</label>
                        <div class="pg-field-help mt-0">' . h(lang('only enable after all historical orders have been synced, to start sending MailChimp automation campaigns')) . '</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
                </div>';
}
