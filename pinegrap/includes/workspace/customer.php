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
 * @param array      $viewer
 * @param string     $type
 * @param int        $id
 * @param array|null $links ws_customer_link_tokens() of the customer when the
 *                          caller has read them already; null reads them
 * @return array|null customer (type, id, label, url, icon, kind, html), links (the same)
 */
function ws_customer_present($viewer, $type, $id, $links = null)
{
    if (!in_array($type, ws_customer_types(), true) || ((int) $id <= 0)) {
        return null;
    }

    $own = array('sigil' => '#', 'type' => $type, 'id' => (int) $id);

    if (!is_array($links)) {
        $links = ws_customer_link_tokens($type, $id);
    }

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
 * @param array      $viewer
 * @param array      $channel
 * @param array|null $links see ws_customer_present()
 * @return array|null see ws_customer_present()
 */
function ws_channel_customer_present($viewer, $channel, $links = null)
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

    return ws_customer_present($viewer, $type, $id, $links);
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
 * May the reader start a sales quote from a channel: the ERP right
 * (manage_erp; staff hold it), and not the accountant's read-only right,
 * which saves no ERP document (validate_erp_access() refuses the quote
 * editor's form). The same people the quote chip's "Copy as a new sales
 * quote" is offered to.
 *
 * @param array $viewer       ws_viewer()
 * @param bool  $erp_readonly whether the reader holds the read-only right (USER_ERP_READONLY)
 * @return bool
 */
function ws_sales_quote_may_start($viewer, $erp_readonly)
{
    return !empty($viewer['erp']) && !$erp_readonly;
}

/**
 * The current account a new sales quote on a channel is written to: the
 * channel's customer when it is an account a quote can go to, else the
 * first such account tied to the customer through its contact
 * (ws_customer_link_tokens() lists active accounts first). An account takes
 * a quote when it is active (the quote editor lists no other) and not a
 * supplier alone (its own screen offers no quote either).
 *
 * @param string  $type     the channel's customer kind (ws_channel_customer_of())
 * @param int     $id       its record
 * @param array[] $links    ws_customer_link_tokens() of that customer
 * @param array[] $accounts erp_accounts rows (id, kind, status) of the accounts named, by id
 * @return int 0 when there is none
 */
function ws_sales_quote_account_pick($type, $id, $links, $accounts)
{
    $candidates = array();

    if (($type === 'erp_account') && ((int) $id > 0)) {
        $candidates[] = (int) $id;
    }

    foreach ((array) $links as $link) {
        if ((($link['type'] ?? '') === 'erp_account') && ((int) ($link['id'] ?? 0) > 0)) {
            $candidates[] = (int) $link['id'];
        }
    }

    foreach ($candidates as $account_id) {
        $account = $accounts[$account_id] ?? null;

        if (is_array($account) && ((string) ($account['status'] ?? '') === 'active') && ((string) ($account['kind'] ?? '') !== 'supplier')) {
            return $account_id;
        }
    }

    return 0;
}

/**
 * The current account "New sales quote" opens the quote editor on for a
 * channel (add_erp_quote.php?account_id=); 0 when the reader may not write
 * quotes, when the quotes table is not there yet, or when the channel's
 * customer is no such account and is tied to none. The right is asked
 * before the lookup reads anything, so a reader without it never learns
 * whether the customer has an account. An account is only looked for, never
 * opened: erp_account_for_contact() would create one. The ties are the ones
 * ws_channel_detail() read for the customer card, so they are not read
 * twice.
 *
 * @param array   $viewer
 * @param array   $channel      a ws_channels row
 * @param bool    $erp_readonly see ws_sales_quote_may_start()
 * @param array[] $links        ws_customer_link_tokens() of the channel's customer
 * @return int
 */
function ws_channel_sales_quote_account($viewer, $channel, $erp_readonly, $links)
{
    if (!ws_sales_quote_may_start($viewer, $erp_readonly)) {
        return 0;
    }

    list($type, $id) = ws_channel_customer_of($channel);

    if (($type === '') || !ws_erp_quotes_readable()) {
        return 0;
    }

    $links = (array) $links;
    $ids = ($type === 'erp_account') ? array((int) $id) : array();

    foreach ($links as $link) {
        if ($link['type'] === 'erp_account') {
            $ids[] = (int) $link['id'];
        }
    }

    if (empty($ids)) {
        return 0;
    }

    $accounts = (array) db_items("SELECT id, kind, status FROM erp_accounts WHERE id IN ('" . implode("','", array_unique($ids)) . "')", 'id');

    return ws_sales_quote_account_pick($type, $id, $links, $accounts);
}

/**
 * The icon of a channel in the lists: the one it was given
 * (channel_icons.php), else the one its kind gives.
 *
 * @param array $channel a row or a brief
 * @return string
 */
function ws_channel_icon($channel)
{
    $chosen = ws_channel_icon_chosen($channel);

    return ($chosen !== '') ? $chosen : ws_channel_kind_icon($channel);
}

/**
 * The icon a channel's kind gives: a lock, a guest's door, a customer's
 * kind, a hash.
 *
 * @param array $channel a row or a brief
 * @return string
 */
function ws_channel_kind_icon($channel)
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
        'cust_orders'     => lang('Orders'),
        'cust_balance'    => lang('Balance'),
        'cust_quotes'     => lang('Open sales quotes'),
        'cust_cart_quotes' => lang('Open cart quotes'),
        'cust_forms'      => lang('Form submissions'),
        'cust_empty'      => lang('Nothing to show for this customer yet.'),
        'cust_history'       => lang('History'),
        'cust_older'         => lang('Show older'),
        'cust_history_empty' => lang('Nothing in the history yet.'),
        // "Remind me about this customer" (includes/workspace/followups.php).
        'cust_remind'         => lang('Remind me'),
        'cust_remind_help'    => lang('Remind me about this customer'),
        'cust_remind_what'    => lang('What to follow up on (optional)'),
        'cust_remind_default' => ws_js_template('Follow up: {var:1}', 1),
        'cust_remind_day'     => lang('Day'),
        'cust_remind_time'    => lang('Time (optional)'),
        'cust_remind_save'    => lang('Set reminder'),
        'cust_remind_note'    => lang(array('string' => 'A task for you on this channel, tagged with the customer. You are reminded on the day, in your inbox and by e-mail, at the time you pick or at {var:1}.', 'vars' => WS_TASK_REMIND_DAY_TIME)),
        'cust_remind_done'    => ws_js_template('Reminder set: {var:1}, {var:2}', 2),
    );
}

/* ---------------------------------------------------------------------------
   The customer summary: who the customer is and where they stand
   --------------------------------------------------------------------------- */

/**
 * The rows each section of the customer summary shows: the latest few.
 */
define('WS_CUSTOMER_SUMMARY_ROWS', 5);

/**
 * The words for the kinds of record a customer can be.
 *
 * @return array type => label
 */
function ws_customer_kind_labels()
{
    return array('contact' => lang('Contact'), 'user_account' => lang('User'), 'erp_account' => lang('Current account'));
}

/**
 * Who a channel's customer is: the record chosen (ws_channel_customer_of()),
 * named for the reader, and the records tied to it through its contact
 * (ws_customer_link_tokens()) that the reader may see. A record the reader
 * may not see is named by its kind and number only; so is a contact outside
 * the contact groups given to a reader with the user role, as the address
 * book opens it (ws_customer_contacts_open()). The ids the summary reads by -
 * the contact, every tied user and current account, seen or not - come with
 * it, for the readers below; they are not shown. Nothing is written: the
 * contact a channel stands for is brought in step where the channel's card is
 * drawn (ws_channel_customer_present()).
 *
 * @param array $viewer
 * @param array $channel a ws_channels row
 * @return array|null see ws_customer_identity_build(); null for a channel
 *                    about nobody, or a customer gone since
 */
function ws_customer_identity($viewer, $channel)
{
    list($type, $id) = ws_channel_customer_of($channel);

    if ($type === '') {
        return null;
    }

    $links = ws_customer_link_tokens($type, $id);
    $tokens = array_merge(array(array('sigil' => '#', 'type' => $type, 'id' => $id)), $links);
    $refs = ws_refs_resolve($viewer, $tokens);

    $contact_ids = array();

    foreach ($tokens as $token) {
        if ($token['type'] === 'contact') {
            $contact_ids[] = $token['id'];
        }
    }

    $refs = ws_customer_refs_narrow($refs, ws_customer_contacts_open($viewer, $contact_ids));

    return ws_customer_identity_build($type, $id, ws_customer_contact_of($type, $id), $links, $refs);
}

/**
 * The contacts of a set the reader may open by the address book's row rule,
 * for the summary: the Workspace's one rule, ws_viewer_contacts_open().
 *
 * @param array $viewer
 * @param int[] $contact_ids
 * @return int[]
 */
function ws_customer_contacts_open($viewer, $contact_ids)
{
    return ws_viewer_contacts_open($viewer, ws_customer_ids($contact_ids));
}

/**
 * The resolved tags of a customer with every contact the reader may not open
 * (ws_customer_contacts_open()) drawn as ws_refs_resolve() draws a contact for
 * a reader without the contacts right: no name, no detail, no address.
 *
 * @param array $refs             ws_refs_resolve() of the customer and its ties
 * @param int[] $open_contact_ids the contacts the reader may open
 * @return array the same entries
 */
function ws_customer_refs_narrow($refs, $open_contact_ids)
{
    $open = ws_customer_ids($open_contact_ids);

    foreach ((array) $refs as $key => $ref) {
        if ((($ref['type'] ?? '') !== 'contact') || empty($ref['known']) || in_array((int) $ref['id'], $open, true)) {
            continue;
        }

        $refs[$key] = array_merge($ref, array('label' => lang('Contact'), 'url' => '', 'meta' => '', 'status' => '', 'known' => false));
    }

    return $refs;
}

/**
 * The customer and its ties as the reader sees them, from the tags resolved
 * for that reader.
 *
 * @param string  $type       the customer's kind
 * @param int     $id         its record
 * @param int     $contact_id the contact it stands for (ws_customer_contact_of()), 0 for none
 * @param array[] $links      ws_customer_link_tokens() of the customer
 * @param array   $refs       ws_refs_resolve() of the customer and its ties
 * @return array|null customer (ws_customer_identity_item()), links (the ties
 *                    the reader may see, the same), contact_id, user_ids,
 *                    erp_account_ids; null when the customer is not there
 *                    (ws_refs_resolve() marks a record gone since: gone)
 */
function ws_customer_identity_build($type, $id, $contact_id, $links, $refs)
{
    $type = (string) $type;
    $id = (int) $id;
    $customer = $refs[$type . ':' . $id] ?? null;

    if (!in_array($type, ws_customer_types(), true) || ($id <= 0) || !is_array($customer) || !empty($customer['gone'])) {
        return null;
    }

    $user_ids = ($type === 'user_account') ? array($id) : array();
    $account_ids = ($type === 'erp_account') ? array($id) : array();
    $seen = array();

    foreach ((array) $links as $link) {
        $link_type = (string) ($link['type'] ?? '');
        $link_id = (int) ($link['id'] ?? 0);

        if ($link_id <= 0) {
            continue;
        }

        if ($link_type === 'user_account') {
            $user_ids[] = $link_id;
        } elseif ($link_type === 'erp_account') {
            $account_ids[] = $link_id;
        }

        $ref = $refs[$link_type . ':' . $link_id] ?? null;

        if (is_array($ref) && !empty($ref['known'])) {
            $seen[] = ws_customer_identity_item($ref);
        }
    }

    return array(
        'customer'        => ws_customer_identity_item($customer),
        'links'           => $seen,
        'contact_id'      => max(0, (int) $contact_id),
        'user_ids'        => array_values(array_unique($user_ids)),
        'erp_account_ids' => array_values(array_unique($account_ids)),
    );
}

/**
 * One record of a customer as the reader sees it. A record they may not see,
 * or one with nothing to call it by, goes by its kind and number; one they
 * may not see has no detail and no address either.
 *
 * @param array $ref a ws_refs_resolve() entry
 * @return array type, id, kind, label, detail, known, url
 */
function ws_customer_identity_item($ref)
{
    $kinds = ws_customer_kind_labels();
    $type = (string) $ref['type'];
    $id = (int) $ref['id'];
    $known = !empty($ref['known']);
    $kind = $kinds[$type] ?? '';
    $label = trim((string) $ref['label']);

    return array(
        'type'   => $type,
        'id'     => $id,
        'kind'   => $kind,
        'label'  => ($known && ($label !== '')) ? $label : ($kind . ' #' . $id),
        'detail' => $known ? (string) $ref['meta'] : '',
        'known'  => $known,
        'url'    => $known ? (string) $ref['url'] : '',
    );
}

/**
 * Which sections of the summary the reader may see: each the right its own
 * screens ask for. Orders and cart quotes the ecommerce right; the balance
 * and the sales quotes the ERP right (manage_erp, as the quote screens), which
 * the accountant's read-only right (manage_erp_readonly) holds too: the
 * summary only reads and offers nothing to do. Submitted forms the forms
 * right, each in a folder the reader may edit (ws_viewer_folder_open()), as
 * the form screens and the form tag open them.
 *
 * @param array $viewer ws_viewer(), or the API's copy narrowed to the key's
 *                      scopes (ws_api_customer_viewer())
 * @return array section => bool
 */
function ws_customer_summary_rights($viewer)
{
    $ecommerce = !empty($viewer['ecommerce']);
    $erp = !empty($viewer['erp']);

    return array(
        'orders'       => $ecommerce,
        'erp_accounts' => $erp,
        'sales_quotes' => $erp,
        'cart_quotes'  => $ecommerce,
        'forms'        => !empty($viewer['forms']),
    );
}

/**
 * Where a channel's customer stands, for the reader: their latest orders, the
 * balance of their current accounts, their open sales quotes and the forms
 * they submitted last. A section the reader holds no right to is not read at
 * all; one with nothing in it is left out. Read-only: nothing is written and
 * no action is offered.
 *
 * @param array      $viewer
 * @param array      $channel  a ws_channels row
 * @param array|null $identity ws_customer_identity() of the channel when the
 *                             caller has it already; null reads it
 * @return array see ws_customer_summary_build(); empty for a channel about nobody
 */
function ws_customer_summary($viewer, $channel, $identity = null)
{
    if ($identity === null) {
        $identity = ws_customer_identity($viewer, $channel);
    }

    if (!is_array($identity)) {
        return array();
    }

    $rights = ws_customer_summary_rights($viewer);
    $data = array();

    if ($rights['orders']) {
        $data['orders'] = ws_customer_order_rows($identity, WS_CUSTOMER_SUMMARY_ROWS);
    }

    if ($rights['erp_accounts']) {
        $data['erp_accounts'] = ws_customer_erp_account_rows($identity);
    }

    if ($rights['sales_quotes']) {
        $data['sales_quotes'] = ws_customer_sales_quote_rows($identity, WS_CUSTOMER_SUMMARY_ROWS);
    }

    if ($rights['cart_quotes']) {
        $data['cart_quotes'] = ws_customer_cart_quote_rows($identity, WS_CUSTOMER_SUMMARY_ROWS);
    }

    if ($rights['forms']) {
        $data['forms'] = ws_customer_form_rows($viewer, $identity, WS_CUSTOMER_SUMMARY_ROWS);
    }

    return ws_customer_summary_build($rights, $data, 'ws_customer_money_text', WS_CUSTOMER_SUMMARY_ROWS);
}

/**
 * The summary's sections laid out from the rows their readers handed over,
 * for the rights given: a section without its right is not laid out whatever
 * rows came, nor one with nothing to show. Amounts stay integer minor units
 * and are written out beside them by $money: orders and balances in the
 * site's own currency (''), sales quotes in their own.
 *
 * @param array    $rights ws_customer_summary_rights()
 * @param array    $data   orders and sales_quotes (count, rows), erp_accounts
 *                         (rows), forms (rows, each with open: whether the
 *                         reader may open it)
 * @param callable $money  function ($minor, $currency): string
 * @param int      $limit  the rows a section shows
 * @return array orders (count, latest), erp_accounts, sales_quotes (count,
 *               latest), forms; a section left out is not there
 */
function ws_customer_summary_build($rights, $data, $money, $limit = WS_CUSTOMER_SUMMARY_ROWS)
{
    $limit = max(1, (int) $limit);
    $out = array();

    if (!empty($rights['orders']) && isset($data['orders'])) {
        $rows = array_slice(array_values((array) ($data['orders']['rows'] ?? array())), 0, $limit);
        $count = max((int) ($data['orders']['count'] ?? 0), count($rows));
        $statuses = array(
            'incomplete' => lang('Incomplete'),
            'complete'   => lang('Complete'),
            'exported'   => lang('Exported'),
            'cancelled'  => lang('Cancelled'),
        );
        $latest = array();

        foreach ($rows as $row) {
            $total = ws_customer_minor($row['total'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            $latest[] = array(
                'id'           => (int) $row['id'],
                'number'       => (string) ($row['order_number'] ?? ''),
                'status'       => $status,
                'status_label' => $statuses[$status] ?? $status,
                'total'        => $total,
                'total_text'   => (string) call_user_func($money, $total, ''),
                'placed_at'    => (int) ($row['order_date'] ?? 0),
            );
        }

        if ($count > 0) {
            $out['orders'] = array('count' => $count, 'latest' => $latest);
        }
    }

    // erp_accounts.balance is the ledger's cache in the site's own currency
    // (erp_account_refresh_balance()): positive when the account owes.
    if (!empty($rights['erp_accounts']) && !empty($data['erp_accounts'])) {
        $accounts = array();

        foreach ((array) $data['erp_accounts'] as $row) {
            $balance = ws_customer_minor($row['balance'] ?? 0);

            $accounts[] = array(
                'id'           => (int) $row['id'],
                'title'        => (string) ($row['title'] ?? ''),
                'status'       => (string) ($row['status'] ?? ''),
                'balance'      => $balance,
                'balance_text' => (string) call_user_func($money, $balance, ''),
            );
        }

        $out['erp_accounts'] = $accounts;
    }

    if (!empty($rights['sales_quotes']) && isset($data['sales_quotes'])) {
        $rows = array_slice(array_values((array) ($data['sales_quotes']['rows'] ?? array())), 0, $limit);
        $count = max((int) ($data['sales_quotes']['count'] ?? 0), count($rows));
        $latest = array();

        foreach ($rows as $row) {
            $total = ws_customer_minor($row['grand_total'] ?? 0);
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            $valid_until = (string) ($row['valid_until'] ?? '');

            $latest[] = array(
                'id'            => (int) $row['id'],
                'number'        => (string) ($row['full_number'] ?? ''),
                'account_id'    => (int) ($row['account_id'] ?? 0),
                'account_title' => (string) ($row['account_title'] ?? ''),
                'issue_date'    => (string) ($row['issue_date'] ?? ''),
                'valid_until'   => ($valid_until > '0000-00-00') ? $valid_until : '',
                'currency'      => $currency,
                'total'         => $total,
                'total_text'    => (string) call_user_func($money, $total, $currency),
            );
        }

        if ($count > 0) {
            $out['sales_quotes'] = array('count' => $count, 'latest' => $latest);
        }
    }

    // Cart quotes are a section of their own, never among the sales quotes:
    // they carry no price, only how many products they hold.
    if (!empty($rights['cart_quotes']) && isset($data['cart_quotes'])) {
        $rows = array_slice(array_values((array) ($data['cart_quotes']['rows'] ?? array())), 0, $limit);
        $count = max((int) ($data['cart_quotes']['count'] ?? 0), count($rows));
        $latest = array();

        foreach ($rows as $row) {
            $latest[] = array(
                'id'          => (int) $row['id'],
                'valid_until' => (int) ($row['valid_until'] ?? 0),
                'lines'       => (int) ($row['line_count'] ?? 0),
            );
        }

        if ($count > 0) {
            $out['cart_quotes'] = array('count' => $count, 'latest' => $latest);
        }
    }

    if (!empty($rights['forms']) && !empty($data['forms'])) {
        $forms = array();

        foreach ((array) $data['forms'] as $row) {
            if (empty($row['open'])) {
                continue;
            }

            if (count($forms) >= $limit) {
                break;
            }

            $name = trim((string) ($row['form_name'] ?? ''));

            $forms[] = array(
                'id'             => (int) $row['id'],
                'name'           => ($name !== '') ? $name : lang('Form'),
                'reference_code' => (string) ($row['reference_code'] ?? ''),
                'complete'       => ((int) ($row['complete'] ?? 0) === 1),
                'submitted_at'   => (int) ($row['submitted_timestamp'] ?? 0),
            );
        }

        if (!empty($forms)) {
            $out['forms'] = $forms;
        }
    }

    return $out;
}

/**
 * The customer card on a channel's Summary tab: who the customer is and where
 * they stand, read for the reader exactly as the API's summary is (the same
 * names, sections and rights), laid out for the panel.
 *
 * @param array $viewer
 * @param array $channel a ws_channels row the reader may read
 * @return array|null see ws_customer_card_build(); null for a channel about
 *                    nobody, or a customer gone since
 */
function ws_customer_card($viewer, $channel)
{
    $identity = ws_customer_identity($viewer, $channel);

    if (!is_array($identity)) {
        return null;
    }

    return ws_customer_card_build($identity, ws_customer_summary($viewer, $channel, $identity), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/');
}

/**
 * The card laid out from the identity and the summary: each row gets the
 * screen its tag links to (a section is only there when the reader holds its
 * right, a form only when its folder is open to them) and its days written
 * out; amounts and their text pass through. The ids the summary reads by
 * (contact_id, user_ids, erp_account_ids) are left behind: they are never
 * shown.
 *
 * @param array  $identity ws_customer_identity()
 * @param array  $summary  ws_customer_summary()
 * @param string $base     the panel's address, ending in /
 * @return array customer, links (ws_customer_identity_item() each), sections:
 *               the summary's sections with url, date_text, status_label or
 *               valid_until_text added per row
 */
function ws_customer_card_build($identity, $summary, $base)
{
    $sections = array();

    if (isset($summary['orders'])) {
        $latest = array();

        foreach ((array) $summary['orders']['latest'] as $row) {
            $latest[] = array_merge($row, array(
                'url'       => $base . 'view_order.php?id=' . (int) $row['id'],
                'date_text' => ws_customer_date_text($row['placed_at'] ?? 0),
            ));
        }

        $sections['orders'] = array('count' => (int) $summary['orders']['count'], 'latest' => $latest);
    }

    if (isset($summary['erp_accounts'])) {
        foreach ((array) $summary['erp_accounts'] as $row) {
            $sections['erp_accounts'][] = array_merge($row, array(
                'url'          => $base . 'edit_erp_account.php?id=' . (int) $row['id'],
                'status_label' => (($row['status'] ?? '') === 'passive') ? lang('Passive') : '',
            ));
        }
    }

    if (isset($summary['sales_quotes'])) {
        $latest = array();

        foreach ((array) $summary['sales_quotes']['latest'] as $row) {
            $until = ws_customer_date_text($row['valid_until'] ?? '');

            $latest[] = array_merge($row, array(
                'number'           => ((string) ($row['number'] ?? '') !== '') ? (string) $row['number'] : '#' . (int) $row['id'],
                'url'              => $base . 'edit_erp_quote.php?id=' . (int) $row['id'],
                'valid_until_text' => ($until !== '') ? lang(array('string' => 'Valid until {var:1}', 'vars' => array($until))) : '',
            ));
        }

        $sections['sales_quotes'] = array('count' => (int) $summary['sales_quotes']['count'], 'latest' => $latest);
    }

    if (isset($summary['cart_quotes'])) {
        $latest = array();

        foreach ((array) $summary['cart_quotes']['latest'] as $row) {
            $until = ws_customer_date_text($row['valid_until'] ?? 0);

            $latest[] = array_merge($row, array(
                'number'           => '#' . (int) $row['id'],
                'url'              => $base . 'view_cart_quotes.php?contact_id=' . (int) ($identity['contact_id'] ?? 0),
                'lines_text'       => lang(array('string' => '{var:1} item(s)', 'vars' => array((int) ($row['lines'] ?? 0)))),
                'valid_until_text' => ($until !== '') ? lang(array('string' => 'Valid until {var:1}', 'vars' => array($until))) : '',
            ));
        }

        $sections['cart_quotes'] = array('count' => (int) $summary['cart_quotes']['count'], 'latest' => $latest);
    }

    if (isset($summary['forms'])) {
        foreach ((array) $summary['forms'] as $row) {
            $sections['forms'][] = array_merge($row, array(
                'url'          => $base . 'edit_submitted_form.php?id=' . (int) $row['id'],
                'date_text'    => ws_customer_date_text($row['submitted_at'] ?? 0),
                'status_label' => empty($row['complete']) ? lang('Incomplete') : '',
            ));
        }
    }

    return array(
        'customer' => $identity['customer'],
        'links'    => $identity['links'],
        'sections' => $sections,
    );
}

/**
 * A day as the card writes it, d.m.Y as the tags write one: from a moment (in
 * the site's zone) or from a Y-m-d day as a date column keeps it (taken as it
 * is, never moved by a zone).
 *
 * @param int|string $value a Unix time or Y-m-d
 * @return string '' for no day
 */
function ws_customer_date_text($value)
{
    if (is_int($value) || ctype_digit((string) $value)) {
        return ((int) $value > 0) ? date('d.m.Y', (int) $value) : '';
    }

    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', (string) $value, $parts) && ((string) $value > '0000-00-00')) {
        return $parts[3] . '.' . $parts[2] . '.' . $parts[1];
    }

    return '';
}

/**
 * An amount as whole minor units: the database hands integer columns over as
 * strings; anything else is rounded, never cut.
 *
 * @param mixed $value
 * @return int
 */
function ws_customer_minor($value)
{
    return is_numeric($value) ? (int) round((float) $value) : 0;
}

/**
 * An amount in minor units written out for the summary, as plain text the
 * caller escapes. The site's own currency is written as the order screens
 * write a total (prepare_amount(): no conversion to the visitor's currency);
 * any other currency - a sales quote's - as the quote screens write it
 * (erp_money_out_currency()).
 *
 * @param mixed  $minor
 * @param string $currency ISO code; '' for the site's own currency
 * @return string
 */
function ws_customer_money_text($minor, $currency = '')
{
    $minor = ws_customer_minor($minor);
    $currency = strtoupper(trim((string) $currency));
    $base = defined('BASE_CURRENCY_CODE') ? strtoupper(trim((string) BASE_CURRENCY_CODE)) : '';

    if (($currency !== '') && ($currency !== $base) && function_exists('erp_money_out_currency')) {
        return erp_money_out_currency($minor, $currency);
    }

    // The base symbol is kept as an HTML entity (&#8378;); the text travels
    // decoded and is escaped where it is printed.
    return html_entity_decode(prepare_amount($minor / 100), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Positive ids, each once.
 *
 * @param mixed $ids
 * @return int[]
 */
function ws_customer_ids($ids)
{
    $out = array();

    foreach ((array) $ids as $id) {
        if ((int) $id > 0) {
            $out[(int) $id] = (int) $id;
        }
    }

    return array_values($out);
}

/**
 * The WHERE clause that finds a customer's orders: by its contact, the users
 * tied to it and, once orders.erp_account_id is there, its current accounts.
 * A cart not ordered yet (incomplete) is no order. A counter sale writes the
 * cashier who rang it up into user_id (local_sale_complete()), so the users
 * find no counter sale; its contact_id and erp_account_id name the buyer.
 *
 * @param array $identity      ws_customer_identity()
 * @param bool  $with_accounts whether orders has erp_account_id
 * @param bool  $with_type     whether orders has type (online / local)
 * @return string '' when there is nothing to find them by
 */
function ws_customer_order_where($identity, $with_accounts, $with_type)
{
    $where = array();
    $contact_id = (int) ($identity['contact_id'] ?? 0);
    $user_ids = ws_customer_ids($identity['user_ids'] ?? array());
    $account_ids = $with_accounts ? ws_customer_ids($identity['erp_account_ids'] ?? array()) : array();

    if ($contact_id > 0) {
        $where[] = "contact_id = '" . $contact_id . "'";
    }

    if (!empty($user_ids)) {
        $by_user = "user_id IN ('" . implode("','", $user_ids) . "')";
        $where[] = $with_type ? "(" . $by_user . " AND type <> 'local')" : $by_user;
    }

    if (!empty($account_ids)) {
        $where[] = "erp_account_id IN ('" . implode("','", $account_ids) . "')";
    }

    if (empty($where)) {
        return '';
    }

    return "status <> 'incomplete' AND (" . implode(' OR ', $where) . ")";
}

/**
 * A customer's orders: how many, and the latest
 * (ws_customer_order_where() says which).
 *
 * @param array $identity ws_customer_identity()
 * @param int   $limit
 * @return array count, rows (id, order_number, status, total, order_date)
 */
function ws_customer_order_rows($identity, $limit)
{
    $none = array('count' => 0, 'rows' => array());

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return $none;
    }

    $schema = function_exists('pg_schema_has');
    $where = ws_customer_order_where($identity, $schema && pg_schema_has('orders', 'erp_account_id'), $schema && pg_schema_has('orders', 'type'));

    if ($where === '') {
        return $none;
    }

    $count = (int) db_value("SELECT COUNT(*) FROM orders WHERE " . $where);

    if ($count === 0) {
        return $none;
    }

    return array(
        'count' => $count,
        'rows'  => (array) db_items("SELECT id, order_number, status, total, order_date FROM orders
            WHERE " . $where . "
            ORDER BY order_date DESC, id DESC
            LIMIT " . max(1, (int) $limit)),
    );
}

/**
 * The current accounts of a customer with their balance, the active ones
 * first.
 *
 * @param array $identity ws_customer_identity()
 * @return array[] id, title, status, balance
 */
function ws_customer_erp_account_rows($identity)
{
    $ids = ws_customer_ids($identity['erp_account_ids'] ?? array());

    if (empty($ids) || !defined('ERP_ENABLED') || !ERP_ENABLED) {
        return array();
    }

    return (array) db_items("SELECT id, title, status, balance FROM erp_accounts
        WHERE id IN ('" . implode("','", $ids) . "')
        ORDER BY status = 'active' DESC, id");
}

/**
 * The open sales quotes of a customer's current accounts, from the ERP's own
 * reader (erp_quote_rows(): open and not past the date they run out on), the
 * newest first, and how many there are.
 *
 * @param array $identity ws_customer_identity()
 * @param int   $limit
 * @return array count, rows (erp_quote_rows() rows)
 */
function ws_customer_sales_quote_rows($identity, $limit)
{
    $ids = ws_customer_ids($identity['erp_account_ids'] ?? array());
    $limit = max(1, (int) $limit);

    if (empty($ids) || !ws_erp_quotes_readable()) {
        return array('count' => 0, 'rows' => array());
    }

    $count = 0;
    $rows = array();

    foreach ($ids as $account_id) {
        $count += erp_quote_open_count($account_id);
        $rows = array_merge($rows, erp_quote_rows(array('state' => 'open', 'account_id' => $account_id, 'limit' => $limit)));
    }

    usort($rows, function ($a, $b) {
        return (int) $b['id'] - (int) $a['id'];
    });

    return array('count' => $count, 'rows' => array_slice($rows, 0, $limit));
}

/**
 * The open cart quotes made for a customer's contact (includes/fn/cart_quotes.php),
 * newest first, each with how many lines it holds: apart from the sales
 * quotes, under the ecommerce right ('cart_quotes' in
 * ws_customer_summary_rights()). A quote past its last day is not open.
 *
 * @param array $identity ws_customer_identity()
 * @param int   $limit
 * @return array count, rows (id, valid_until, line_count)
 */
function ws_customer_cart_quote_rows($identity, $limit)
{
    $contact_id = (int) ($identity['contact_id'] ?? 0);

    if (($contact_id <= 0) || !pg_cart_quotes_ready()) {
        return array('count' => 0, 'rows' => array());
    }

    $where = "q.contact_id = '" . $contact_id . "' AND q.status = 'open' AND (q.valid_until = 0 OR q.valid_until >= '" . time() . "')";

    return array(
        'count' => (int) db_value("SELECT COUNT(*) FROM cart_quotes AS q WHERE " . $where),
        'rows'  => (array) db_items("SELECT q.id, q.valid_until, (SELECT COUNT(*) FROM cart_quote_items AS i WHERE i.cart_quote_id = q.id) AS line_count
            FROM cart_quotes AS q WHERE " . $where . " ORDER BY q.id DESC LIMIT " . max(1, (int) $limit)),
    );
}

/**
 * The forms a customer's contact submitted last, each with whether the reader
 * may open it: in a folder they may edit, as the form screens and the form tag
 * ask. More are read than are shown, since the ones the reader may not open
 * are dropped.
 *
 * @param array $viewer
 * @param array $identity ws_customer_identity()
 * @param int   $limit
 * @return array[] id, reference_code, submitted_timestamp, complete, page_folder, form_name, open
 */
function ws_customer_form_rows($viewer, $identity, $limit)
{
    $contact_id = (int) ($identity['contact_id'] ?? 0);

    if ($contact_id <= 0) {
        return array();
    }

    $rows = (array) db_items("SELECT f.id, f.reference_code, f.submitted_timestamp, f.complete, p.page_folder, c.form_name
        FROM forms f
        LEFT JOIN page p ON p.page_id = f.page_id
        LEFT JOIN custom_form_pages c ON c.page_id = f.page_id
        WHERE f.contact_id = '" . $contact_id . "'
        ORDER BY f.id DESC
        LIMIT " . (max(1, (int) $limit) * 3));

    foreach ($rows as $index => $row) {
        $rows[$index]['open'] = ws_viewer_folder_open($viewer, (int) $row['page_folder']);
    }

    return $rows;
}

/**
 * The submission a form.submitted event names, and the contact the form
 * tied it to (0 for none). Null for any other event, or one without a
 * submission. Pure.
 *
 * @param string $event
 * @param array  $payload id (the forms row), contact_id
 * @return array|null form_id, contact_id
 */
function ws_lead_submission_of($event, $payload)
{
    $payload = (array) $payload;
    $form_id = (int) ($payload['id'] ?? 0);

    if (((string) $event !== 'form.submitted') || ($form_id <= 0)) {
        return null;
    }

    return array('form_id' => $form_id, 'contact_id' => max(0, (int) ($payload['contact_id'] ?? 0)));
}

/**
 * The e-mail address a submission gives: the answer of the field tied to
 * the contact's address first, else the first e-mail field holding a valid
 * address. '' when there is none. Pure.
 *
 * @param array[] $rows form_data answers with their field: type, contact_field, data
 * @return string lower case
 */
function ws_lead_email_pick($rows)
{
    $found = '';

    foreach ((array) $rows as $row) {
        $address = strtolower(trim((string) ($row['data'] ?? '')));

        if (($address === '') || (filter_var($address, FILTER_VALIDATE_EMAIL) === false)) {
            continue;
        }

        if ((string) ($row['contact_field'] ?? '') === 'email_address') {
            return $address;
        }

        if (($found === '') && ((string) ($row['type'] ?? '') === 'email address')) {
            $found = $address;
        }
    }

    return $found;
}

/**
 * The contact a submission comes from: the one the form tied it to, else
 * the newest contact with the address the submission gives. 0 when there
 * is none. A second contact with the same address is left to the contacts
 * screen's duplicates and merge.
 *
 * @param array $submission ws_lead_submission_of()
 * @return int
 */
function ws_lead_contact_find($submission)
{
    $contact_id = (int) ($submission['contact_id'] ?? 0);

    if (($contact_id > 0) && ws_customer_exists('contact', $contact_id)) {
        return $contact_id;
    }

    $address = ws_lead_email_pick(db_items("SELECT d.data, ff.type, ff.contact_field
        FROM form_data d
        INNER JOIN form_fields ff ON ff.id = d.form_field_id
        WHERE d.form_id = '" . (int) ($submission['form_id'] ?? 0) . "' AND (ff.type = 'email address' OR ff.contact_field = 'email_address')
        ORDER BY d.id"));

    if ($address === '') {
        return 0;
    }

    return (int) db_value("SELECT id FROM contacts WHERE email_address = '" . e($address) . "' ORDER BY id DESC LIMIT 1");
}

/**
 * The name of a channel opened for a customer: their name, else their
 * company, else their address, else the contact's number; at most 80
 * characters, as a channel's name. Pure.
 *
 * @param array $contact contacts row: first_name, last_name, company, email_address
 * @param int   $contact_id
 * @return string
 */
function ws_lead_channel_name($contact, $contact_id)
{
    $contact = (array) $contact;
    $clean = function ($value) {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    };
    $name = $clean($clean($contact['first_name'] ?? '') . ' ' . $clean($contact['last_name'] ?? ''));

    foreach (array($name, $clean($contact['company'] ?? ''), $clean($contact['email_address'] ?? '')) as $candidate) {
        if ($candidate !== '') {
            return mb_substr(ltrim($candidate, '# '), 0, 80);
        }
    }

    return lang(array('string' => 'Contact #{var:1}', 'vars' => (int) $contact_id));
}

/**
 * The names tried in turn for a new channel, another open channel holding
 * the first: the name, then the name with (2) to (9), each within 80
 * characters. Pure.
 *
 * @param string $name
 * @return string[]
 */
function ws_lead_channel_names($name)
{
    $names = array($name);

    for ($number = 2; $number <= 9; $number++) {
        $suffix = ' (' . $number . ')';
        $names[] = mb_substr($name, 0, 80 - mb_strlen($suffix)) . $suffix;
    }

    return $names;
}

/**
 * The channel a lead is brought into when the customer has one: the first
 * public or private channel of the list ws_customer_channels() gives, newest
 * first; a discussion or a guest channel is no place for it. 0 for none.
 * Pure.
 *
 * @param array[] $channels ws_channels rows: id, kind
 * @return int
 */
function ws_lead_channel_pick($channels)
{
    foreach ((array) $channels as $channel) {
        if (in_array((string) ($channel['kind'] ?? ''), array('public', 'private'), true)) {
            return (int) $channel['id'];
        }
    }

    return 0;
}
