<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the customer a channel is about. It is one record, of the three
 * kinds a customer can be here: a contact (the address book), a user (a
 * login) or a current account (the ERP's customer). They are often the same
 * customer seen from three places, and the site ties them through the
 * contact: a user names its contact (user.user_contact), a current account
 * too (erp_accounts.contact_id). The channel shows the one chosen and the
 * records tied to it that way, each with the way to its own screen.
 *
 * ws_channels.contact_id stays the contact the customer stands for, whatever
 * kind was chosen: the address book card, the record screens and the API
 * find a customer's channels by it.
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
 * Has the database the customer of a channel as a kind and a record
 * (2026.4.5, 5.89)?
 *
 * @return bool
 */
function ws_customer_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_channels', 'customer_type');
    }

    return $ready;
}

/**
 * The kinds of record a customer can be, as the tags name them.
 *
 * @return string[]
 */
function ws_customer_types()
{
    return array('contact', 'user_account', 'erp_account');
}

/**
 * Is there such a record?
 *
 * @param string $type
 * @param int    $id
 * @return bool
 */
function ws_customer_exists($type, $id)
{
    $id = (int) $id;

    if ($id <= 0) {
        return false;
    }

    switch ($type) {
        case 'contact':
            return (int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $id . "'") > 0;

        case 'user_account':
            return (int) db_value("SELECT COUNT(*) FROM user WHERE user_id = '" . $id . "'") > 0;

        case 'erp_account':
            return defined('ERP_ENABLED') && ERP_ENABLED
                && ((int) db_value("SELECT COUNT(*) FROM erp_accounts WHERE id = '" . $id . "'") > 0);
    }

    return false;
}

/**
 * The contact a customer stands for: the contact itself, the one a user
 * names, the one a current account names. 0 when there is none.
 *
 * @param string $type
 * @param int    $id
 * @return int
 */
function ws_customer_contact_of($type, $id)
{
    $id = (int) $id;

    if ($id <= 0) {
        return 0;
    }

    switch ($type) {
        case 'contact':
            return $id;

        case 'user_account':
            return (int) db_value("SELECT user_contact FROM user WHERE user_id = '" . $id . "'");

        case 'erp_account':
            return (defined('ERP_ENABLED') && ERP_ENABLED) ? (int) db_value("SELECT contact_id FROM erp_accounts WHERE id = '" . $id . "'") : 0;
    }

    return 0;
}

/**
 * The records tied to a customer through its contact, as tags: its contact,
 * the users that name it, the current accounts that name it. Not the
 * customer itself.
 *
 * @param string $type
 * @param int    $id
 * @return array[] sigil, type, id
 */
function ws_customer_link_tokens($type, $id)
{
    $contact_id = ws_customer_contact_of($type, $id);
    $tokens = array();

    if ($contact_id <= 0) {
        return $tokens;
    }

    if (($type !== 'contact') && ws_customer_exists('contact', $contact_id)) {
        $tokens[] = array('sigil' => '#', 'type' => 'contact', 'id' => $contact_id);
    }

    foreach ((array) db_values("SELECT user_id FROM user WHERE user_contact = '" . $contact_id . "' ORDER BY user_id LIMIT 3") as $user_id) {
        if (($type !== 'user_account') || ((int) $user_id !== (int) $id)) {
            $tokens[] = array('sigil' => '#', 'type' => 'user_account', 'id' => (int) $user_id);
        }
    }

    if (defined('ERP_ENABLED') && ERP_ENABLED) {
        foreach ((array) db_values("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY status = 'active' DESC, id LIMIT 5") as $account_id) {
            if (($type !== 'erp_account') || ((int) $account_id !== (int) $id)) {
                $tokens[] = array('sigil' => '#', 'type' => 'erp_account', 'id' => (int) $account_id);
            }
        }
    }

    return $tokens;
}

/**
 * A customer and the records tied to it, as the screen shows them. A record
 * the reader may not see is left out of the ties; the customer itself stays,
 * named by its kind only.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @return array|null customer (type, id, label, url, icon, kind, html), links (the same)
 */
function ws_customer_present($viewer, $type, $id)
{
    if (!in_array($type, ws_customer_types(), true) || ((int) $id <= 0)) {
        return null;
    }

    $own = array('sigil' => '#', 'type' => $type, 'id' => (int) $id);
    $links = ws_customer_link_tokens($type, $id);
    $refs = ws_refs_resolve($viewer, array_merge(array($own), $links));
    $kinds = array('contact' => lang('Contact'), 'user_account' => lang('User'), 'erp_account' => lang('Current account'));

    $item = function ($ref) use ($kinds) {
        $kind = $kinds[$ref['type']] ?? '';

        // A record with nothing to call it by (a contact with no name, company
        // or address) goes by its kind and number.
        if (trim((string) $ref['label']) === '') {
            $ref['label'] = $kind . ' #' . (int) $ref['id'];
        }

        return array(
            'type'  => $ref['type'],
            'id'    => (int) $ref['id'],
            'label' => $ref['label'],
            'url'   => $ref['url'],
            'icon'  => $ref['icon'],
            'meta'  => $ref['meta'],
            'kind'  => $kind,
            'known' => (bool) $ref['known'],
            'html'  => ws_chip_html($ref),
        );
    };

    $customer = $refs[$type . ':' . (int) $id] ?? null;

    // Gone since it was chosen: the channel is about nobody now.
    if (!$customer) {
        return null;
    }

    $out = array('customer' => $item($customer), 'links' => array());

    foreach ($links as $token) {
        $ref = $refs[$token['type'] . ':' . $token['id']] ?? null;

        if ($ref && $ref['known']) {
            $out['links'][] = $item($ref);
        }
    }

    return $out;
}

/**
 * The customer of a channel, as the screen shows it. A tie that changed
 * since (the user now names another contact) is brought in step, so the
 * address book finds the channel under the right card.
 *
 * @param array $viewer
 * @param array $channel
 * @return array|null see ws_customer_present()
 */
function ws_channel_customer_present($viewer, $channel)
{
    list($type, $id) = ws_channel_customer_of($channel);

    if ($type === '') {
        return null;
    }

    if (ws_customer_ready() && ($type !== 'contact')) {
        $contact_id = ws_customer_contact_of($type, $id);

        if ($contact_id !== (int) $channel['contact_id']) {
            db("UPDATE ws_channels SET contact_id = '" . $contact_id . "' WHERE id = '" . (int) $channel['id'] . "'");
        }
    }

    return ws_customer_present($viewer, $type, $id);
}

/**
 * The kind and the record a channel is about; a channel written before the
 * kinds (only contact_id) is about its contact.
 *
 * @param array $channel
 * @return array type ('' for none), id
 */
function ws_channel_customer_of($channel)
{
    $type = (string) ($channel['customer_type'] ?? '');
    $id = (int) ($channel['customer_id'] ?? 0);

    if (($type !== '') && ($id > 0) && in_array($type, ws_customer_types(), true)) {
        return array($type, $id);
    }

    if ((int) ($channel['contact_id'] ?? 0) > 0) {
        return array('contact', (int) $channel['contact_id']);
    }

    return array('', 0);
}

/**
 * Reads the customer given for a channel: customer_type and customer_id, or
 * contact_id alone as before. A record the person may not tag cannot be
 * chosen; the one the channel already has may stay.
 *
 * @param array      $viewer
 * @param array      $data
 * @param array|null $channel the channel it is for, when it is changed
 * @return array|null null when nothing was given; else ok, error, field, type, id, contact_id
 */
function ws_customer_input($viewer, $data, $channel = null)
{
    if (array_key_exists('customer_type', $data) || array_key_exists('customer_id', $data)) {
        $type = trim((string) ($data['customer_type'] ?? ''));
        $id = max(0, (int) ($data['customer_id'] ?? 0));
    } elseif (array_key_exists('contact_id', $data)) {
        $type = 'contact';
        $id = max(0, (int) $data['contact_id']);
    } else {
        return null;
    }

    if (($type === '') || ($id === 0)) {
        return array('ok' => true, 'error' => '', 'field' => '', 'type' => '', 'id' => 0, 'contact_id' => 0);
    }

    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'field' => 'customer', 'type' => '', 'id' => 0, 'contact_id' => 0);
    };

    if (!in_array($type, ws_customer_types(), true)) {
        return $fail(lang('That record could not be found.'));
    }

    // Before the kinds, only a contact can be the customer.
    if (($type !== 'contact') && !ws_customer_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $unchanged = is_array($channel) && (ws_channel_customer_of($channel) === array($type, $id));

    if (!$unchanged && !isset(ws_ref_types($viewer)[$type])) {
        return $fail(lang('You cannot choose that record.'));
    }

    if (!ws_customer_exists($type, $id)) {
        return $fail(($type === 'contact') ? lang('That contact could not be found.') : lang('That record could not be found.'));
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'type' => $type, 'id' => $id, 'contact_id' => ws_customer_contact_of($type, $id));
}

/**
 * The SET part of an UPDATE that gives a channel this customer.
 *
 * @param array $input from ws_customer_input()
 * @return string[]
 */
function ws_customer_set($input)
{
    $set = array("contact_id = '" . (int) $input['contact_id'] . "'");

    if (ws_customer_ready()) {
        $set[] = "customer_type = '" . e($input['type']) . "'";
        $set[] = "customer_id = '" . (int) $input['id'] . "'";
    }

    return $set;
}

/**
 * The channels about a record, or about the customer it is tied to: for a
 * contact, every channel whose customer stands for it; for a user or a
 * current account, the ones about it and the ones about its contact.
 *
 * @param string $type
 * @param int    $id
 * @return array[] channel rows, not archived, newest first
 */
function ws_customer_channels($type, $id)
{
    $id = (int) $id;

    if (!in_array($type, ws_customer_types(), true) || ($id <= 0)) {
        return array();
    }

    $where = array();
    $contact_id = ws_customer_contact_of($type, $id);

    if ($contact_id > 0) {
        $where[] = "contact_id = '" . $contact_id . "'";
    }

    if (ws_customer_ready() && ($type !== 'contact')) {
        $where[] = "(customer_type = '" . e($type) . "' AND customer_id = '" . $id . "')";
    }

    if (empty($where)) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_channels WHERE archived_at = 0 AND (" . implode(' OR ', $where) . ") ORDER BY last_message_at DESC LIMIT 50");
}

/**
 * The icon of a channel in the lists: a lock, a customer's kind, a hash.
 *
 * @param array $channel a row or a brief
 * @return string
 */
function ws_channel_icon($channel)
{
    if ((string) $channel['kind'] === 'private') {
        return 'bi-lock';
    }

    if ((string) $channel['kind'] === 'guest') {
        return 'bi-door-open';
    }

    $type = (string) ($channel['customer_type'] ?? '');

    if ($type === 'user_account') {
        return 'bi-person-badge';
    }

    if ($type === 'erp_account') {
        return 'bi-building';
    }

    return (((int) ($channel['contact_id'] ?? 0) > 0) || ($type === 'contact')) ? 'bi-person-vcard' : 'bi-hash';
}

/**
 * The words the screen needs for the customer of a channel.
 *
 * @return array
 */
function ws_customer_js_strings()
{
    return array(
        'cust_search'     => lang('Search contacts, users and current accounts'),
        'cust_help'       => lang('A contact, a user or a current account. The records tied to it are shown with it: a user and a current account are tied to a customer through their contact.'),
        'cust_links'      => lang('Tied to it'),
        'cust_links_none' => lang('Nothing is tied to it yet.'),
    );
}
