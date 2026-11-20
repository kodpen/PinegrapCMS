<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The personal data inventory: a catalogue in code of each table that holds
 * personal data (its categories, whose data it is, why it is kept, the
 * suggested legal ground and how long it is kept), the live counts of
 * records and people each line covers, and the transfers: each outside
 * service of the registry that is switched on, with its country and the data
 * it receives.
 *
 * The catalogue sits beside pg_privacy_erase_tables(): every table the
 * erasure reaches has a line here, and every registry row names its country
 * and data (tests/privacy_inventory_test.php fails otherwise). Purposes and
 * labels are English source text, translated where a screen draws them.
 * Legal grounds and retentions are suggested defaults, not legal advice.
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
 * The categories of personal data, in the order a list reads them. Labels
 * are English source text. Pure.
 *
 * @return array key => label
 */
function pg_privacy_inventory_categories()
{
    return array(
        'identity'      => 'Identity',
        'contact'       => 'Contact',
        'address'       => 'Address',
        'order_payment' => 'Order and payment',
        'ip_cookie'     => 'IP address and cookies',
        'location'      => 'Location',
        'signature'     => 'Signature',
        'uploads'       => 'Uploaded files',
        'messages'      => 'Messages and notes',
        'security'      => 'Sign-in security',
    );
}

/**
 * Whose data a line holds. Pure.
 *
 * @return array key => label
 */
function pg_privacy_inventory_subjects()
{
    return array(
        'customer'  => 'Customers',
        'visitor'   => 'Visitors',
        'applicant' => 'Applicants',
        'staff'     => 'Staff',
    );
}

/**
 * The suggested legal grounds (KVKK article 5, GDPR article 6). Pure.
 *
 * @return array key => label
 */
function pg_privacy_inventory_grounds()
{
    return array(
        'consent'             => 'Explicit consent',
        'contract'            => 'Performance of a contract',
        'legal_obligation'    => 'Legal obligation',
        'legitimate_interest' => 'Legitimate interest',
        'establish_right'     => 'Establishing or defending a right',
    );
}

/**
 * How long a line's data is kept: until a person is erased, the log
 * retention of the Audit Trail card, or a retention rule of the Privacy
 * centre by its kind. Pure.
 *
 * @return array key => label
 */
function pg_privacy_inventory_retentions()
{
    return array(
        'until_erased'     => 'Until erased',
        'log_retention'    => 'The audit trail\'s retention',
        'passive_contact'  => 'Rule: passive contacts',
        'form_submissions' => 'Rule: form submissions',
        'incomplete_carts' => 'Rule: incomplete carts',
        'orders'           => 'Rule: orders',
    );
}

/**
 * The catalogue: table => line. Pure.
 *
 * categories: keys of pg_privacy_inventory_categories().
 * subjects:   keys of pg_privacy_inventory_subjects().
 * purpose:    why it is kept (English, a few words).
 * ground:     a key of pg_privacy_inventory_grounds().
 * retention:  a key of pg_privacy_inventory_retentions(), or a list of them.
 * person:     optional: the column naming the person, and its kind (int or
 *             text), for the count of people; a column the erasure finds or
 *             replaces in that table. Without it only records are counted.
 *             An int column also counts only the rows linked to somebody.
 *
 * @return array table => line
 */
function pg_privacy_inventory()
{
    $user = array('user_id', 'int');
    $contact = array('contact_id', 'int');
    $account = array('account_id', 'int');
    $sign_in = array(
        'categories' => array('security'),
        'subjects'   => array('customer', 'staff'),
        'ground'     => 'contract',
        'retention'  => 'until_erased',
        'person'     => $user,
    );

    return array(
        // Accounts and contacts.
        'user' => array('categories' => array('identity', 'contact', 'security'), 'subjects' => array('customer', 'staff'),
            'purpose' => 'Accounts that sign in to the site or the panel', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => $user),
        'contacts' => array('categories' => array('identity', 'contact', 'address'), 'subjects' => array('customer', 'visitor', 'applicant'),
            'purpose' => 'Customers, subscribers and people who wrote in', 'ground' => 'legitimate_interest', 'retention' => 'passive_contact', 'person' => array('id', 'int')),
        'contacts_contact_groups_xref' => array('categories' => array('contact'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Contact group memberships', 'ground' => 'legitimate_interest', 'retention' => 'passive_contact', 'person' => $contact),
        'opt_in' => array('categories' => array('contact'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Mailing list subscriptions', 'ground' => 'consent', 'retention' => 'passive_contact', 'person' => $contact),
        'address_book' => array('categories' => array('identity', 'contact', 'address'), 'subjects' => array('customer'),
            'purpose' => 'Saved addresses of an account', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => array('user', 'int')),
        'user_passkeys' => $sign_in + array('purpose' => 'Passkeys'),
        'user_identities' => array('categories' => array('identity', 'contact', 'security')) + $sign_in + array('purpose' => 'Sign-in with an outside account'),
        'user_mfa' => $sign_in + array('purpose' => 'Two-step verification'),
        'user_mfa_recovery' => $sign_in + array('purpose' => 'Two-step verification recovery codes'),
        'auth_tokens' => $sign_in + array('purpose' => 'Remembered sign-ins and API devices'),
        'push_subscriptions' => array('categories' => array('ip_cookie'), 'subjects' => array('staff'),
            'purpose' => 'Panel notifications in a browser', 'ground' => 'consent', 'retention' => 'until_erased', 'person' => $user),
        'site_push_subscription' => array('categories' => array('ip_cookie'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Site notifications in a browser', 'ground' => 'consent', 'retention' => 'until_erased', 'person' => $user),

        // Orders and money.
        'orders' => array('categories' => array('identity', 'contact', 'address', 'order_payment', 'ip_cookie'), 'subjects' => array('customer'),
            'purpose' => 'Orders, carts, their payment and delivery', 'ground' => 'contract', 'retention' => array('orders', 'incomplete_carts'), 'person' => array('billing_email_address', 'text')),
        'ship_tos' => array('categories' => array('identity', 'contact', 'address'), 'subjects' => array('customer'),
            'purpose' => 'Delivery addresses of orders', 'ground' => 'contract', 'retention' => 'orders'),
        'order_documents' => array('categories' => array('ip_cookie'), 'subjects' => array('customer'),
            'purpose' => 'Proof that an order\'s legal documents were accepted', 'ground' => 'legal_obligation', 'retention' => 'orders'),
        'order_returns' => array('categories' => array('identity', 'order_payment', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'Returns and their notes', 'ground' => 'contract', 'retention' => 'orders'),
        'shipment_labels' => array('categories' => array('identity', 'address'), 'subjects' => array('customer'),
            'purpose' => 'Shipping labels', 'ground' => 'contract', 'retention' => 'orders'),
        'gift_cards' => array('categories' => array('identity', 'contact', 'order_payment', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'Gift cards and store credit', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => array('recipient_email_address', 'text')),
        'cart_quotes' => array('categories' => array('identity'), 'subjects' => array('customer'),
            'purpose' => 'Quotes made from a cart', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => $contact),
        'wishlist_items' => array('categories' => array('identity'), 'subjects' => array('customer'),
            'purpose' => 'Favourites', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => $user),
        'stock_notify' => array('categories' => array('contact'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Back-in-stock notices', 'ground' => 'consent', 'retention' => 'until_erased', 'person' => array('email_address', 'text')),
        'bookings' => array('categories' => array('identity', 'contact', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'Bookings', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => array('email', 'text')),
        'course_progress' => array('categories' => array('identity'), 'subjects' => array('customer'),
            'purpose' => 'Members\' course progress', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => $user),

        // Accounting.
        'erp_accounts' => array('categories' => array('identity', 'contact', 'address'), 'subjects' => array('customer'),
            'purpose' => 'Customer accounts of the accounting', 'ground' => 'legal_obligation', 'retention' => 'until_erased', 'person' => array('id', 'int')),
        'erp_invoices' => array('categories' => array('identity', 'contact', 'address', 'order_payment'), 'subjects' => array('customer'),
            'purpose' => 'Invoices', 'ground' => 'legal_obligation', 'retention' => 'until_erased', 'person' => $account),
        'erp_quotes' => array('categories' => array('identity', 'contact'), 'subjects' => array('customer'),
            'purpose' => 'Quotes', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => $account),
        'erp_waybills' => array('categories' => array('identity', 'address'), 'subjects' => array('customer'),
            'purpose' => 'Waybills', 'ground' => 'legal_obligation', 'retention' => 'until_erased', 'person' => $account),

        // Forms and uploads.
        'forms' => array('categories' => array('identity', 'contact', 'ip_cookie'), 'subjects' => array('customer', 'visitor', 'applicant'),
            'purpose' => 'Form submissions', 'ground' => 'legitimate_interest', 'retention' => 'form_submissions'),
        'form_data' => array('categories' => array('identity', 'contact', 'address', 'messages'), 'subjects' => array('customer', 'visitor', 'applicant'),
            'purpose' => 'What people typed into forms', 'ground' => 'legitimate_interest', 'retention' => 'form_submissions'),
        'form_signatures' => array('categories' => array('signature'), 'subjects' => array('customer', 'applicant'),
            'purpose' => 'Signatures on forms', 'ground' => 'contract', 'retention' => 'form_submissions'),
        'files' => array('categories' => array('uploads'), 'subjects' => array('customer', 'visitor', 'applicant'),
            'purpose' => 'Files sent with forms', 'ground' => 'legitimate_interest', 'retention' => 'form_submissions',
            'where' => '`id` IN (SELECT `file_id` FROM `form_data` WHERE `file_id` > 0)'),
        'record_revisions' => array('categories' => array('identity', 'contact', 'messages'), 'subjects' => array('customer', 'applicant'),
            'purpose' => 'Earlier copies of edited records', 'ground' => 'legitimate_interest', 'retention' => 'until_erased'),
        'watchers' => array('categories' => array('contact'), 'subjects' => array('customer', 'staff'),
            'purpose' => 'People told of a record\'s changes', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('email_address', 'text')),

        // Comments.
        'comments' => array('categories' => array('identity', 'messages'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Comments on the site\'s pages', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('created_user_id', 'int')),
        'prototype_comments' => array('categories' => array('identity', 'messages'), 'subjects' => array('customer', 'staff'),
            'purpose' => 'Comments on design prototypes', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => $user),

        // Mail, messages and chats.
        'email_recipients' => array('categories' => array('contact'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Recipients of e-mail campaigns', 'ground' => 'consent', 'retention' => 'passive_contact', 'person' => array('email_address', 'text')),
        'mail_outbox' => array('categories' => array('contact', 'messages'), 'subjects' => array('customer', 'visitor', 'applicant', 'staff'),
            'purpose' => 'Mail waiting to be sent', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('recipient', 'text')),
        'mail_bounces' => array('categories' => array('contact'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Addresses whose mail bounced', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('address', 'text')),
        'sms_outbox' => array('categories' => array('contact', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'Text messages', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('recipient', 'text')),
        'whatsapp_outbox' => array('categories' => array('contact', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'WhatsApp messages sent', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('recipient', 'text')),
        'whatsapp_inbox' => array('categories' => array('contact', 'messages'), 'subjects' => array('customer'),
            'purpose' => 'WhatsApp messages received', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => array('from_number', 'text')),
        'chat_conversations' => array('categories' => array('identity', 'contact', 'ip_cookie'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Site chats', 'ground' => 'legitimate_interest', 'retention' => 'until_erased'),
        'chat_messages' => array('categories' => array('messages', 'uploads'), 'subjects' => array('customer', 'visitor', 'staff'),
            'purpose' => 'Messages of site chats', 'ground' => 'legitimate_interest', 'retention' => 'until_erased'),

        // Workspace.
        'ws_approval_guests' => array('categories' => array('identity', 'contact', 'ip_cookie'), 'subjects' => array('customer'),
            'purpose' => 'Guests who approve Workspace items', 'ground' => 'contract', 'retention' => 'until_erased', 'person' => array('email', 'text')),
        'ws_events' => array('categories' => array('identity'), 'subjects' => array('customer'),
            'purpose' => 'Customer visits on the Work Calendar', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => $contact),
        'ws_personal_plans' => array('categories' => array('identity'), 'subjects' => array('customer'),
            'purpose' => 'Personal plans made for a customer', 'ground' => 'legitimate_interest', 'retention' => 'until_erased', 'person' => $contact),
        'ws_wtable_rows' => array('categories' => array('identity', 'contact'), 'subjects' => array('customer'),
            'purpose' => 'Rows of workflow tables', 'ground' => 'legitimate_interest', 'retention' => 'until_erased'),
        'ws_wtable_row_bin' => array('categories' => array('identity', 'contact'), 'subjects' => array('customer'),
            'purpose' => 'Rows in a workflow table\'s bin', 'ground' => 'legitimate_interest', 'retention' => 'until_erased'),

        // Logs.
        'consent_log' => array('categories' => array('ip_cookie'), 'subjects' => array('customer', 'visitor'),
            'purpose' => 'Proof of cookie and marketing consents', 'ground' => 'legal_obligation', 'retention' => 'log_retention', 'person' => array('person_token', 'text')),
        'audit_log' => array('categories' => array('identity', 'ip_cookie', 'security'), 'subjects' => array('staff', 'customer'),
            'purpose' => 'The audit trail of changes', 'ground' => 'legal_obligation', 'retention' => 'log_retention'),
    );
}

/**
 * What is wrong with one line, as the names of the keys at fault; an empty
 * list for a whole line. Pure.
 *
 * @param mixed $line
 * @return array list of key names
 */
function pg_privacy_inventory_line_problems($line)
{
    if (!is_array($line)) {
        return array('line');
    }

    $problems = array();
    $keys_of = function ($key, $allowed) use ($line) {
        if (!isset($line[$key])) {
            return false;
        }

        $keys = (array) $line[$key];

        if (!$keys) {
            return false;
        }

        foreach ($keys as $value) {
            if (!is_string($value) || !isset($allowed[$value])) {
                return false;
            }
        }

        return true;
    };

    if (!$keys_of('categories', pg_privacy_inventory_categories())) {
        $problems[] = 'categories';
    }

    if (!$keys_of('subjects', pg_privacy_inventory_subjects())) {
        $problems[] = 'subjects';
    }

    if (!isset($line['purpose']) || !is_string($line['purpose']) || (trim($line['purpose']) === '')) {
        $problems[] = 'purpose';
    }

    if (!isset($line['ground']) || !is_string($line['ground']) || !isset(pg_privacy_inventory_grounds()[$line['ground']])) {
        $problems[] = 'ground';
    }

    if (!$keys_of('retention', pg_privacy_inventory_retentions())) {
        $problems[] = 'retention';
    }

    if (isset($line['person'])) {
        $person = $line['person'];

        if (!is_array($person) || (count($person) !== 2) || !preg_match('/^[a-z_]+$/', (string) $person[0]) || !in_array($person[1], array('int', 'text'), true)) {
            $problems[] = 'person';
        }
    }

    return $problems;
}

/**
 * The query counting one line's records and, with a person column, the
 * people they name. Pure: table and columns come from the catalogue, never
 * from input.
 *
 * @param string $table
 * @param array  $line
 * @return string
 */
function pg_privacy_inventory_count_sql($table, array $line)
{
    $where = array();

    if (!empty($line['where'])) {
        $where[] = $line['where'];
    }

    $select = 'COUNT(*) AS records';

    if (!empty($line['person'])) {
        $column = '`' . $line['person'][0] . '`';

        if ($line['person'][1] === 'int') {
            $select .= ', COUNT(DISTINCT NULLIF(' . $column . ', 0)) AS people';
            $where[] = $column . ' > 0';
        } else {
            $select .= ', COUNT(DISTINCT NULLIF(' . $column . ", '')) AS people";
        }
    }

    return 'SELECT ' . $select . ' FROM `' . $table . '`' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
}

/**
 * The live counts of every line: records, and people where the line names a
 * person column. A table or column this site does not have, or a query that
 * fails, counts as null (not known), never as nothing.
 *
 * @return array table => array('records' => int|null, 'people' => int|null)
 */
function pg_privacy_inventory_counts()
{
    $counts = array();

    foreach (pg_privacy_inventory() as $table => $line) {
        $counts[$table] = array('records' => null, 'people' => null);

        if (!pg_schema_has($table) || (!empty($line['person']) && !pg_schema_has($table, $line['person'][0]))) {
            continue;
        }

        $result = db_q(pg_privacy_inventory_count_sql($table, $line));
        $row = $result ? mysqli_fetch_assoc($result) : null;

        if (!$row) {
            continue;
        }

        $counts[$table]['records'] = (int) $row['records'];

        if (isset($row['people'])) {
            $counts[$table]['people'] = (int) $row['people'];
        }
    }

    return $counts;
}

/**
 * The transfers: the registry's rows that are switched on, with the country
 * each sits in, the data it receives and why. Pure.
 *
 * A row is kept when its state is connected, needs attention, or cannot be
 * told (none: such a service may be in use). A row receives personal data
 * unless its data is 'none'; it is a transfer abroad when it does and its
 * country is not the site's ('*', many countries, counts as abroad).
 *
 * @param array  $registry     id => row (pg_connections_registry())
 * @param array  $states       id => state (pg_connections_screen_state())
 * @param string $site_country the site's two-letter country code
 * @return array id => name, group, uses, country, data, personal, abroad, unknown
 */
function pg_privacy_transfers(array $registry, array $states, $site_country)
{
    $list = array();

    foreach ($registry as $id => $row) {
        $state = isset($states[$id]) ? $states[$id] : 'none';

        if (!in_array($state, array('connected', 'attention', 'none'), true)) {
            continue;
        }

        $data = isset($row['data']) ? (array) $row['data'] : array();
        $country = isset($row['country']) ? (string) $row['country'] : '';
        $personal = ($data !== array()) && ($data !== array('none'));

        $list[$id] = array(
            'name'     => $row['name'],
            'group'    => $row['group'],
            'uses'     => $row['uses'],
            'country'  => $country,
            'data'     => $personal ? $data : array(),
            'personal' => $personal,
            'abroad'   => $personal && ($country !== strtoupper((string) $site_country)),
            'unknown'  => ($state === 'none'),
        );
    }

    return $list;
}

/**
 * The transfers as this site stands, read from what the Connections screen
 * reads (no outside service is called).
 *
 * @param string $site_country the site's two-letter country code
 * @return array see pg_privacy_transfers()
 */
function pg_privacy_transfers_live($site_country)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

    $facts = pg_connections_screen_facts();
    $registry = pg_connections_registry();
    $states = array();

    foreach ($registry as $id => $row) {
        $states[$id] = pg_connections_screen_state($row, $facts);
    }

    return pg_privacy_transfers($registry, $states, $site_country);
}
