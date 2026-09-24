<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - tags: people, departments and the site's own records written
 * into a message or attached to a task.
 *
 * A tag travels inside the message text as a token - <@user:12>,
 * <@dept:3>, <#order:1045>, <#erp_account:7> - so it survives a rename, and
 * it is drawn for
 * whoever reads it at the moment they read it. A person who may open orders
 * sees "Order #1045 · Shipped · 1,250.00"; somebody who may not sees
 * "Order #1045" and nothing that would tell them more. A tag must never be a
 * way round a right the reader does not hold.
 *
 * Each tag is also a row in ws_refs, which is what lets the order screen, the
 * contact card and the product editor ask where they were talked about.
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
 * The reader: their workspace rights plus the rights of the other modules a
 * tag can point into. Built from a validate_user() array (booleans), a
 * pg_load_user_row() array or an API owner ('yes' / '1' strings) alike.
 *
 * @param array $user
 * @return array
 */
function ws_viewer($user)
{
    $viewer = ws_rights($user);
    $staff = ($viewer['role'] < 3);

    $yes = function ($value) {
        return ($value === true) || ($value === 1) || ($value === '1') || ($value === 'yes');
    };

    $viewer['ecommerce'] = defined('ECOMMERCE') && (ECOMMERCE === true)
        && ($staff || $yes($user['manage_ecommerce'] ?? false));

    $viewer['contacts'] = $staff || $yes($user['manage_contacts'] ?? false);

    $viewer['erp'] = defined('ERP_ENABLED') && ERP_ENABLED
        && ($staff || $yes($user['manage_erp'] ?? false));

    $viewer['erp_cash'] = $viewer['erp'] && ($staff || $yes($user['manage_erp_cash'] ?? false));

    $viewer['forms'] = $staff || $yes($user['manage_forms'] ?? false);

    $viewer['calendars'] = $staff || $yes($user['manage_calendars'] ?? false);

    // The user list is the managers' screen (edit_user.php asks for 'manager').
    $viewer['users'] = $staff;

    return $viewer;
}

/**
 * Every kind of token a text can hold, whatever the reader may see: what the
 * patterns that find tokens are built from. Record types are the ones that
 * live outside the workspace and have a screen of their own.
 *
 * @return string[]
 */
function ws_record_type_keys()
{
    return array(
        'order', 'product', 'product_group', 'offer',
        'contact', 'user_account',
        'erp_account', 'invoice', 'waybill', 'receipt', 'edoc',
        'form', 'calendar_event',
        'file', 'page',
    );
}

/**
 * @return string[] people, records and the workspace's own things; app is an
 *                  application that can be asked (Claude, <@app:N>)
 */
function ws_tag_type_keys()
{
    return array_merge(array('user', 'dept', 'app'), ws_record_type_keys(), array('task', 'plan', 'channel'));
}

/**
 * The regular expression alternation of every token type, for the patterns
 * that find tokens in a text.
 *
 * @return string
 */
function ws_token_types_pattern()
{
    return implode('|', ws_tag_type_keys());
}

/**
 * The kinds of record a tag may point at, for this reader.
 *
 * @param array $viewer
 * @return array type => label, icon, prefixes typed after # to pick the type,
 *               record (a record with a screen of its own, outside the workspace)
 */
function ws_ref_types($viewer)
{
    $types = array();

    $add = function ($key, $label, $icon, $prefixes, $record = true) use (&$types) {
        $types[$key] = array('label' => $label, 'icon' => $icon, 'prefixes' => $prefixes, 'record' => $record);
    };

    if ($viewer['ecommerce']) {
        $add('order', lang('Order'), 'bi-bag-check', array('sip', 'siparis', 'sipariş', 'order', 'o'));
        $add('product', lang('Product'), 'bi-box-seam', array('urun', 'ürün', 'product', 'p'));
        $add('product_group', lang('Product Group'), 'bi-collection', array('grup', 'urungrubu', 'ürüngrubu', 'kategori', 'group'));
        $add('offer', lang('Offer'), 'bi-tag', array('teklif', 'kampanya', 'kupon', 'offer'));
    }

    // A contact (the address book), a user (a login) and a current account
    // (the ERP's customer or supplier) are three different things; each has
    // a tag of its own.
    if ($viewer['contacts']) {
        $add('contact', lang('Contact'), 'bi-person-vcard', array('kisi', 'kişi', 'contact', 'k'));
    }

    if ($viewer['users']) {
        $add('user_account', lang('User'), 'bi-person-badge', array('kullanici', 'kullanıcı', 'kul', 'user', 'u'));
    }

    if ($viewer['erp']) {
        $add('erp_account', lang('Current account'), 'bi-building', array('cari', 'hesap', 'account', 'a'));
        $add('invoice', lang('Invoice'), 'bi-receipt', array('fat', 'fatura', 'invoice', 'f'));
        $add('waybill', lang('Delivery Note'), 'bi-truck', array('irs', 'irsaliye', 'waybill', 'i'));
    }

    if ($viewer['erp_cash']) {
        $add('receipt', lang('Cash receipt'), 'bi-cash-coin', array('makbuz', 'tahsilat', 'odeme', 'ödeme', 'receipt', 'm'));
    }

    if ($viewer['erp']) {
        $add('edoc', lang('Incoming e-invoice'), 'bi-envelope-paper', array('gelen', 'ebelge', 'e-belge', 'edoc', 'e'));
    }

    if ($viewer['forms']) {
        $add('form', lang('Form'), 'bi-ui-checks', array('form', 'basvuru', 'başvuru', 'talep'));
    }

    if ($viewer['calendars']) {
        $add('calendar_event', lang('Calendar Event'), 'bi-calendar-event', array('etkinlik', 'takvim', 'event', 't'));
    }

    $add('file', lang('File'), 'bi-file-earmark', array('dosya', 'file', 'd'));
    $add('page', lang('Page'), 'bi-window', array('sayfa', 'page', 's'));
    $add('task', lang('Task'), 'bi-check2-square', array('gorev', 'görev', 'task', 'g'), false);
    $add('plan', lang('Plan item'), 'bi-calendar-week', array('plan', 'izin', 'toplanti', 'toplantı', 'ziyaret'), false);
    $add('channel', lang('Channel'), 'bi-hash', array('kanal', 'channel', 'c'), false);

    return $types;
}

/**
 * Every tag in a text, in order of appearance.
 *
 * @param string $body
 * @return array[] sigil, type, id
 */
function ws_tokens($body)
{
    $out = array();

    if (preg_match_all('/<([@#])(' . ws_token_types_pattern() . '):([0-9]{1,10})>/', (string) $body, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $out[] = array('sigil' => $match[1], 'type' => $match[2], 'id' => (int) $match[3]);
        }
    }

    return $out;
}

/**
 * Keeps only tokens whose sigil and type belong together, so a text cannot
 * mention an order with @ or a person with #.
 *
 * @param string $body
 * @return string
 */
function ws_tokens_normalise($body)
{
    return preg_replace_callback('/<([@#])(' . ws_token_types_pattern() . '):([0-9]{1,10})>/', function ($match) {
        $people = in_array($match[2], array('user', 'dept', 'app'), true);

        if (($match[1] === '@') !== $people) {
            return '';
        }

        return $match[0];
    }, (string) $body);
}

/**
 * Writes the tags of one message or task into ws_refs, replacing what was
 * there for it.
 *
 * @param string $source_type 'message' | 'task'
 * @param int    $source_id
 * @param int    $channel_id
 * @param array  $tokens      ws_tokens() output, or [type, id] pairs
 */
function ws_refs_store($source_type, $source_id, $channel_id, $tokens)
{
    db("DELETE FROM ws_refs WHERE source_type = '" . e($source_type) . "' AND source_id = '" . (int) $source_id . "'");

    $seen = array();
    $values = array();
    $now = time();

    foreach ((array) $tokens as $token) {
        $key = $token['type'] . ':' . (int) $token['id'];

        // Asking an application is not talking about a record.
        if (isset($seen[$key]) || ((int) $token['id'] <= 0) || ($token['type'] === 'app')) {
            continue;
        }

        $seen[$key] = true;

        $values[] = "('" . e($source_type) . "', '" . (int) $source_id . "', '" . (int) $channel_id . "', '"
            . e($token['type']) . "', '" . (int) $token['id'] . "', '" . $now . "')";
    }

    if (!empty($values)) {
        db("INSERT INTO ws_refs (source_type, source_id, channel_id, ref_type, ref_id, created_at)
            VALUES " . implode(', ', $values));
    }
}

/**
 * A money amount in minor units, as the panel prints prices.
 *
 * @param int $minor
 * @return string
 */
function ws_money_out($minor)
{
    // The currency symbol is kept as an HTML entity (&#8378;); the text is
    // escaped where it is printed, so it travels decoded.
    if (function_exists('prepare_price_for_output') && defined('VISITOR_CURRENCY_EXCHANGE_RATE')) {
        return html_entity_decode(prepare_price_for_output((int) $minor, false, 0, 'plain_text'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return function_exists('pg_format_number') ? pg_format_number(((int) $minor) / 100, 2) : number_format(((int) $minor) / 100, 2);
}

/**
 * What each tag in a set looks like to this reader.
 *
 * One query per type, however many messages the tags came from.
 *
 * @param array $viewer
 * @param array $tokens ws_tokens() rows, from any number of texts
 * @return array "type:id" => label, url, meta, status, known (the reader may see it), icon
 */
function ws_refs_resolve($viewer, $tokens)
{
    $ids = array();

    foreach ((array) $tokens as $token) {
        $ids[$token['type']][(int) $token['id']] = (int) $token['id'];
    }

    $out = array();

    $put = function ($type, $id, $label, $url = '', $meta = '', $status = '', $known = true, $icon = '') use (&$out) {
        $out[$type . ':' . (int) $id] = array(
            'type'   => $type,
            'id'     => (int) $id,
            'label'  => (string) $label,
            'url'    => (string) $url,
            'meta'   => (string) $meta,
            'status' => (string) $status,
            'known'  => (bool) $known,
            'icon'   => (string) $icon,
        );
    };

    $in = function ($type) use ($ids) {
        return implode(',', array_map('intval', $ids[$type]));
    };

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    // May this reader edit the folder a file, a page or a form lives in? Asked
    // of the reader, not of whoever is signed in: an API owner reads too.
    $folder_open = function ($folder_id) use ($viewer) {
        if ($viewer['role'] < 3) {
            return true;
        }

        if (function_exists('pg_folder_edit_access')) {
            return (bool) pg_folder_edit_access((int) $folder_id, (int) $viewer['id'], (int) $viewer['role']);
        }

        return function_exists('check_edit_access') && check_edit_access($folder_id);
    };

    $date_out = function ($value) {
        $time = is_numeric($value) ? (int) $value : strtotime((string) $value);

        return ($time > 0) ? date('d.m.Y', $time) : '';
    };

    if (!empty($ids['user'])) {
        foreach (ws_people($ids['user']) as $id => $person) {
            $put('user', $id, '@' . $person['name'], '', $person['title'], '', true, 'bi-person');
        }
    }

    if (!empty($ids['dept'])) {
        foreach ((array) db_items("SELECT id, name FROM ws_departments WHERE id IN (" . $in('dept') . ")") as $row) {
            $put('dept', $row['id'], '@' . $row['name'], '', lang('Department'), '', true, 'bi-people');
        }
    }

    // Only Claude is offered as an application to ask, so an application
    // tag reads as Claude, also after the connection moved to another
    // application or was switched off.
    if (!empty($ids['app'])) {
        foreach ($ids['app'] as $id) {
            $put('app', $id, '@Claude', '', lang('AI assistant'), '', true, 'bi-stars');
        }
    }

    if (!empty($ids['order'])) {
        $rows = (defined('ECOMMERCE') && ECOMMERCE === true)
            ? (array) db_items("SELECT id, order_number, status, total, billing_first_name, billing_last_name, billing_company
                FROM orders WHERE id IN (" . $in('order') . ")")
            : array();

        foreach ($rows as $row) {
            $label = lang('Order') . ' #' . $row['order_number'];

            if ($viewer['ecommerce']) {
                $customer = trim($row['billing_company'] !== '' ? $row['billing_company'] : ($row['billing_first_name'] . ' ' . $row['billing_last_name']));
                $meta = trim($customer . ' · ' . ws_money_out($row['total']), ' ·');

                $order_statuses = array(
                    'incomplete' => lang('Incomplete'),
                    'complete'   => lang('Complete'),
                    'exported'   => lang('Exported'),
                    'cancelled'  => lang('Cancelled'),
                );

                $put('order', $row['id'], $label, $base . 'view_order.php?id=' . (int) $row['id'], $meta, $order_statuses[$row['status']] ?? (string) $row['status'], true, 'bi-bag-check');
            } else {
                $put('order', $row['id'], $label, '', '', '', false, 'bi-bag-check');
            }
        }
    }

    if (!empty($ids['product'])) {
        $rows = (defined('ECOMMERCE') && ECOMMERCE === true)
            ? (array) db_items("SELECT id, name, short_description, price, enabled FROM products WHERE id IN (" . $in('product') . ")")
            : array();

        foreach ($rows as $row) {
            $title = ((string) $row['short_description'] !== '') ? $row['short_description'] : $row['name'];

            if ($viewer['ecommerce']) {
                $put('product', $row['id'], $title, $base . 'edit_product.php?id=' . (int) $row['id'],
                    $row['name'] . ' · ' . ws_money_out($row['price']),
                    ((int) $row['enabled'] === 1) ? '' : lang('Disabled'), true, 'bi-box-seam');
            } else {
                $put('product', $row['id'], lang('Product'), '', '', '', false, 'bi-box-seam');
            }
        }
    }

    if (!empty($ids['contact'])) {
        foreach ((array) db_items("SELECT id, first_name, last_name, company, email_address FROM contacts WHERE id IN (" . $in('contact') . ")") as $row) {
            if ($viewer['contacts']) {
                $name = trim($row['first_name'] . ' ' . $row['last_name']);
                $label = ($name !== '') ? $name : (($row['company'] !== '') ? $row['company'] : $row['email_address']);
                $meta = ($name !== '' && $row['company'] !== '') ? $row['company'] : $row['email_address'];

                $put('contact', $row['id'], $label, $base . 'edit_contact.php?id=' . (int) $row['id'], $meta, '', true, 'bi-person-vcard');
            } else {
                $put('contact', $row['id'], lang('Contact'), '', '', '', false, 'bi-person-vcard');
            }
        }
    }

    if (!empty($ids['invoice'])) {
        $rows = (defined('ERP_ENABLED') && ERP_ENABLED)
            ? (array) db_items("SELECT i.id, i.full_number, i.status, i.grand_total, i.doc_type, a.title AS account_title
                FROM erp_invoices i LEFT JOIN erp_accounts a ON a.id = i.account_id
                WHERE i.id IN (" . $in('invoice') . ")")
            : array();

        foreach ($rows as $row) {
            if ($viewer['erp']) {
                $number = ((string) $row['full_number'] !== '') ? $row['full_number'] : lang('Draft');
                $invoice_statuses = array(
                    'draft'          => lang('Draft'),
                    'issued'         => lang('Issued'),
                    'partially_paid' => lang('Partially paid'),
                    'paid'           => lang('Paid'),
                    'cancelled'      => lang('Cancelled'),
                );

                $put('invoice', $row['id'], lang('Invoice') . ' ' . $number, $base . 'edit_erp_invoice.php?id=' . (int) $row['id'],
                    trim((string) $row['account_title'] . ' · ' . ws_money_out($row['grand_total']), ' ·'),
                    $invoice_statuses[$row['status']] ?? (string) $row['status'], true, 'bi-receipt');
            } else {
                $put('invoice', $row['id'], lang('Invoice'), '', '', '', false, 'bi-receipt');
            }
        }
    }

    if (!empty($ids['file'])) {
        foreach ((array) db_items("SELECT id, name, folder FROM files WHERE id IN (" . $in('file') . ")") as $row) {
            $open = $folder_open($row['folder']);

            if (!$open && function_exists('get_access_control_type') && ((int) $row['folder'] > 0)) {
                $open = (get_access_control_type($row['folder']) === 'public');
            }

            if ($open) {
                $put('file', $row['id'], $row['name'], PATH . encode_url_path($row['name']), '', '', true, 'bi-file-earmark');
            } else {
                $put('file', $row['id'], lang('File'), '', '', '', false, 'bi-file-earmark');
            }
        }
    }

    if (!empty($ids['page'])) {
        foreach ((array) db_items("SELECT page_id, page_name, page_folder FROM page WHERE page_id IN (" . $in('page') . ")") as $row) {
            if ($folder_open($row['page_folder'])) {
                $put('page', $row['page_id'], $row['page_name'], OUTPUT_PATH . encode_url_path($row['page_name']), '', '', true, 'bi-window');
            } else {
                $put('page', $row['page_id'], lang('Page'), '', '', '', false, 'bi-window');
            }
        }
    }

    if (!empty($ids['product_group'])) {
        $rows = (defined('ECOMMERCE') && ECOMMERCE === true)
            ? (array) db_items("SELECT id, name, short_description, enabled FROM product_groups WHERE id IN (" . $in('product_group') . ")")
            : array();

        foreach ($rows as $row) {
            if ($viewer['ecommerce']) {
                $put('product_group', $row['id'], $row['name'], $base . 'edit_product_group.php?id=' . (int) $row['id'],
                    (string) $row['short_description'], ((int) $row['enabled'] === 1) ? '' : lang('Disabled'), true, 'bi-collection');
            } else {
                $put('product_group', $row['id'], lang('Product Group'), '', '', '', false, 'bi-collection');
            }
        }
    }

    if (!empty($ids['offer'])) {
        $rows = (defined('ECOMMERCE') && ECOMMERCE === true)
            ? (array) db_items("SELECT id, code, description, status FROM offers WHERE id IN (" . $in('offer') . ")")
            : array();

        foreach ($rows as $row) {
            if ($viewer['ecommerce']) {
                $code = trim((string) $row['code']);
                $label = ($code !== '') ? $code : trim((string) $row['description']);
                $meta = ($code !== '') ? trim((string) $row['description']) : '';

                $put('offer', $row['id'], $label, $base . 'edit_offer.php?id=' . (int) $row['id'], $meta,
                    ($row['status'] === 'enabled') ? '' : lang('Disabled'), true, 'bi-tag');
            } else {
                $put('offer', $row['id'], lang('Offer'), '', '', '', false, 'bi-tag');
            }
        }
    }

    if (!empty($ids['user_account'])) {
        $roles = array(0 => lang('Administrator'), 1 => lang('Designer'), 2 => lang('Manager'), 3 => lang('User'));

        foreach ((array) db_items("SELECT user_id, user_username, user_email, user_role FROM user WHERE user_id IN (" . $in('user_account') . ")") as $row) {
            if ($viewer['users']) {
                $put('user_account', $row['user_id'], $row['user_username'], $base . 'edit_user.php?id=' . (int) $row['user_id'],
                    (string) $row['user_email'], $roles[(int) $row['user_role']] ?? '', true, 'bi-person-badge');
            } else {
                $put('user_account', $row['user_id'], lang('User'), '', '', '', false, 'bi-person-badge');
            }
        }
    }

    if (!empty($ids['erp_account'])) {
        $rows = (defined('ERP_ENABLED') && ERP_ENABLED)
            ? (array) db_items("SELECT id, kind, title, tax_number, balance, status FROM erp_accounts WHERE id IN (" . $in('erp_account') . ")")
            : array();

        $kinds = array('customer' => lang('Customer'), 'supplier' => lang('Supplier'), 'both' => lang('Customer and supplier'));

        foreach ($rows as $row) {
            if ($viewer['erp']) {
                $meta = trim(($kinds[$row['kind']] ?? '') . ' · ' . ws_money_out($row['balance']), ' ·');

                $put('erp_account', $row['id'], $row['title'], $base . 'edit_erp_account.php?id=' . (int) $row['id'], $meta,
                    ($row['status'] === 'passive') ? lang('Passive') : '', true, 'bi-building');
            } else {
                $put('erp_account', $row['id'], lang('Current account'), '', '', '', false, 'bi-building');
            }
        }
    }

    if (!empty($ids['waybill'])) {
        $rows = (defined('ERP_ENABLED') && ERP_ENABLED)
            ? (array) db_items("SELECT w.id, w.full_number, w.status, w.issue_date, a.title AS account_title
                FROM erp_waybills w LEFT JOIN erp_accounts a ON a.id = w.account_id
                WHERE w.id IN (" . $in('waybill') . ")")
            : array();

        $statuses = array('draft' => lang('Draft'), 'issued' => lang('Issued'), 'cancelled' => lang('Cancelled'));

        foreach ($rows as $row) {
            if ($viewer['erp']) {
                $number = ((string) $row['full_number'] !== '') ? $row['full_number'] : lang('Draft');

                $put('waybill', $row['id'], lang('Delivery Note') . ' ' . $number, $base . 'edit_erp_waybill.php?id=' . (int) $row['id'],
                    trim((string) $row['account_title'] . ' · ' . $date_out($row['issue_date']), ' ·'),
                    $statuses[$row['status']] ?? (string) $row['status'], true, 'bi-truck');
            } else {
                $put('waybill', $row['id'], lang('Delivery Note'), '', '', '', false, 'bi-truck');
            }
        }
    }

    if (!empty($ids['receipt'])) {
        $rows = (defined('ERP_ENABLED') && ERP_ENABLED)
            ? (array) db_items("SELECT c.id, c.doc_type, c.doc_date, c.amount, a.title AS account_title
                FROM erp_cash_transactions c LEFT JOIN erp_accounts a ON a.id = c.account_id
                WHERE c.id IN (" . $in('receipt') . ") AND c.doc_type IN ('collection', 'payment')")
            : array();

        foreach ($rows as $row) {
            $title = ($row['doc_type'] === 'collection') ? lang('Receipt') : lang('Payment');

            if ($viewer['erp_cash']) {
                $put('receipt', $row['id'], $title . ' #' . (int) $row['id'], $base . 'erp_receipt.php?id=' . (int) $row['id'],
                    trim((string) $row['account_title'] . ' · ' . ws_money_out($row['amount']) . ' · ' . $date_out($row['doc_date']), ' ·'),
                    '', true, 'bi-cash-coin');
            } else {
                $put('receipt', $row['id'], lang('Cash receipt'), '', '', '', false, 'bi-cash-coin');
            }
        }
    }

    if (!empty($ids['edoc'])) {
        $rows = (defined('ERP_ENABLED') && ERP_ENABLED)
            ? (array) db_items("SELECT id, gib_number, supplier_title, total, issue_date, status FROM erp_edoc_inbox WHERE id IN (" . $in('edoc') . ")")
            : array();

        $statuses = array('new' => lang('New'), 'imported' => lang('Imported'), 'ignored' => lang('Ignored'));

        foreach ($rows as $row) {
            if ($viewer['erp']) {
                $put('edoc', $row['id'], lang('Incoming e-invoice') . ' ' . $row['gib_number'], $base . 'erp_inbox_document.php?id=' . (int) $row['id'],
                    trim((string) $row['supplier_title'] . ' · ' . ws_money_out($row['total']), ' ·'),
                    $statuses[$row['status']] ?? (string) $row['status'], true, 'bi-envelope-paper');
            } else {
                $put('edoc', $row['id'], lang('Incoming e-invoice'), '', '', '', false, 'bi-envelope-paper');
            }
        }
    }

    if (!empty($ids['form'])) {
        $rows = (array) db_items("SELECT f.id, f.reference_code, f.submitted_timestamp, f.complete, p.page_folder, c.form_name
            FROM forms f
            LEFT JOIN page p ON p.page_id = f.page_id
            LEFT JOIN custom_form_pages c ON c.page_id = f.page_id
            WHERE f.id IN (" . $in('form') . ")");

        foreach ($rows as $row) {
            if ($viewer['forms'] && $folder_open($row['page_folder'])) {
                $name = ((string) $row['form_name'] !== '') ? $row['form_name'] : lang('Form');

                $put('form', $row['id'], trim($name . ' ' . $row['reference_code']), $base . 'edit_submitted_form.php?id=' . (int) $row['id'],
                    $date_out($row['submitted_timestamp']), ((int) $row['complete'] === 1) ? '' : lang('Incomplete'), true, 'bi-ui-checks');
            } else {
                $put('form', $row['id'], lang('Form'), '', '', '', false, 'bi-ui-checks');
            }
        }
    }

    if (!empty($ids['calendar_event'])) {
        $rows = (array) db_items("SELECT id, name, start_time, all_day, location, published FROM calendar_events WHERE id IN (" . $in('calendar_event') . ")");

        // Below the managers, a calendar event is for whoever may edit one of
        // the calendars it is in.
        $allowed = array();

        if ($viewer['calendars'] && ($viewer['role'] >= 3) && !empty($rows)) {
            foreach ((array) db_values("SELECT DISTINCT x.calendar_event_id
                FROM calendar_events_calendars_xref x
                JOIN users_calendars_xref u ON u.calendar_id = x.calendar_id AND u.user_id = '" . (int) $viewer['id'] . "'
                WHERE x.calendar_event_id IN (" . $in('calendar_event') . ")") as $event_id) {
                $allowed[(int) $event_id] = true;
            }
        }

        foreach ($rows as $row) {
            $open = $viewer['calendars'] && (($viewer['role'] < 3) || isset($allowed[(int) $row['id']]));

            if ($open) {
                $time = strtotime((string) $row['start_time']);
                $when = ($time > 0) ? date(((int) $row['all_day'] === 1) ? 'd.m.Y' : 'd.m.Y H:i', $time) : '';

                $put('calendar_event', $row['id'], $row['name'], $base . 'edit_calendar_event.php?id=' . (int) $row['id'],
                    trim($when . ' · ' . (string) $row['location'], ' ·'), ((int) $row['published'] === 1) ? '' : lang('Unpublished'), true, 'bi-calendar-event');
            } else {
                $put('calendar_event', $row['id'], lang('Calendar Event'), '', '', '', false, 'bi-calendar-event');
            }
        }
    }

    if (!empty($ids['plan'])) {
        $rows = (array) db_items("SELECT * FROM ws_events WHERE id IN (" . $in('plan') . ")");
        $people = array();

        foreach ((array) db_items("SELECT event_id, user_id FROM ws_event_people WHERE event_id IN (" . $in('plan') . ")") as $row) {
            $people[(int) $row['event_id']][] = (int) $row['user_id'];
        }

        $kinds = ws_event_kinds();
        $scope = $viewer['member'] ? ws_board_scope($viewer) : array();
        $departments = $viewer['member'] ? ws_user_department_ids($viewer['id']) : array();

        foreach ($rows as $row) {
            $row['people'] = $people[(int) $row['id']] ?? array();

            // Seen by whoever it is about, and by whoever sees the board of
            // one of the people in it.
            $open = $viewer['member'] && (($scope === true) || ws_event_applies($row, $viewer['id'], $departments)
                || !empty(array_intersect($row['people'], (array) $scope)));

            if ($open) {
                $from = date('d.m.Y', (int) $row['starts_at']);
                $to = date('d.m.Y', (int) $row['ends_at']);

                $put('plan', $row['id'], $row['title'], $base . 'workspace_board.php?from=' . date('Y-m-d', (int) $row['starts_at']),
                    trim(($kinds[$row['kind']] ?? '') . ' · ' . $from . (($to !== $from) ? ' – ' . $to : ''), ' ·'), '', true, 'bi-calendar-week');
            } else {
                $put('plan', $row['id'], lang('Plan item'), '', '', '', false, 'bi-calendar-week');
            }
        }
    }

    if (!empty($ids['task'])) {
        $rows = (array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . $in('task') . ")");
        $assignees = ws_task_assignees_map(array_keys($ids['task']));

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            if (ws_can_see_task($viewer, $row, $assignees[$id] ?? array())) {
                $put('task', $id, ws_task_number($id) . ' · ' . $row['title'], $base . 'workspace_tasks.php?task=' . $id,
                    ws_task_due_label($row), ws_task_status_label($row['status']), true, 'bi-check2-square');
            } else {
                $put('task', $id, ws_task_number($id), '', '', '', false, 'bi-check2-square');
            }
        }
    }

    if (!empty($ids['channel'])) {
        foreach ((array) db_items("SELECT * FROM ws_channels WHERE id IN (" . $in('channel') . ")") as $row) {
            if (ws_can_read_channel($viewer, $row)) {
                $put('channel', $row['id'], '#' . $row['name'], $base . 'workspace.php?channel=' . (int) $row['id'], $row['topic'], '', true,
                    ($row['kind'] === 'private') ? 'bi-lock' : 'bi-hash');
            } else {
                $put('channel', $row['id'], lang('Private channel'), '', '', '', false, 'bi-lock');
            }
        }
    }

    // Tags whose record is gone are still drawn, and say so.
    foreach ((array) $tokens as $token) {
        $key = $token['type'] . ':' . (int) $token['id'];

        if (!isset($out[$key])) {
            $put($token['type'], $token['id'], lang('Deleted record'), '', '', '', false, 'bi-x-circle');
        }
    }

    return $out;
}

/**
 * Records of one type that match what is being typed after # or @, for the
 * picker. Only what the reader may see is offered.
 *
 * @param array  $viewer
 * @param string $type
 * @param string $query
 * @param int    $limit
 * @return array[] token, type, id, label, meta, icon
 */
function ws_ref_search($viewer, $type, $query, $limit = 8)
{
    $query = trim((string) $query);
    $like = e(escape_like($query));
    $limit = max(1, min(20, (int) $limit));
    $number = preg_replace('/[^0-9]/', '', $query);
    $out = array();
    $found = array();

    $types = ws_ref_types($viewer);

    if (($type === 'user') || ($type === 'dept')) {
        if ($type === 'user') {
            foreach (ws_team_members() as $person) {
                if (($query === '') || (mb_stripos($person['name'], $query) !== false) || (mb_stripos($person['username'], $query) !== false)) {
                    $out[] = array('token' => '<@user:' . $person['id'] . '>', 'type' => 'user', 'id' => $person['id'],
                        'label' => $person['name'], 'meta' => $person['title'], 'icon' => 'bi-person', 'avatar' => $person['avatar']);
                }

                if (count($out) >= $limit) {
                    break;
                }
            }
        } else {
            foreach (ws_departments() as $department) {
                if (($query === '') || (mb_stripos($department['name'], $query) !== false)) {
                    $out[] = array('token' => '<@dept:' . $department['id'] . '>', 'type' => 'dept', 'id' => $department['id'],
                        'label' => $department['name'], 'meta' => lang('Department'), 'icon' => 'bi-people');
                }
            }
        }

        return array_slice($out, 0, $limit);
    }

    // Every kind at once: a few of each, in the order the kinds are listed,
    // each line saying what it is. Only once something is typed - with
    // nothing to match, every table would answer with its newest rows.
    if ($type === 'all') {
        if (mb_strlen($query) < 2) {
            return array();
        }

        // Two of each, so the kinds further down the list are not crowded
        // out by the first ones that happen to match.
        $limit = max($limit, 12);

        foreach ($types as $key => $info) {
            foreach (ws_ref_search($viewer, $key, $query, 2) as $item) {
                $item['meta'] = trim($info['label'] . ' · ' . $item['meta'], ' ·');
                $out[] = $item;
            }

            if (count($out) >= $limit) {
                break;
            }
        }

        return array_slice($out, 0, $limit);
    }

    if (!isset($types[$type])) {
        return array();
    }

    switch ($type) {

        case 'order':
            $where = ($number !== '')
                ? "(order_number LIKE '" . e(escape_like($number)) . "%' OR id = '" . (int) $number . "')"
                : "(billing_first_name LIKE '%" . $like . "%' OR billing_last_name LIKE '%" . $like . "%' OR billing_company LIKE '%" . $like . "%')";

            $found = db_values("SELECT id FROM orders WHERE status <> 'incomplete' AND " . $where . " ORDER BY id DESC LIMIT " . $limit);
            break;

        case 'product':
            $found = db_values("SELECT id FROM products
                WHERE (name LIKE '%" . $like . "%' OR short_description LIKE '%" . $like . "%'" . (($number !== '') ? " OR id = '" . (int) $number . "'" : '') . ")
                ORDER BY enabled DESC, short_description LIMIT " . $limit);
            break;

        case 'contact':
            $found = db_values("SELECT id FROM contacts
                WHERE (first_name LIKE '%" . $like . "%' OR last_name LIKE '%" . $like . "%'
                    OR CONCAT(first_name, ' ', last_name) LIKE '%" . $like . "%'
                    OR company LIKE '%" . $like . "%' OR email_address LIKE '%" . $like . "%')
                ORDER BY id DESC LIMIT " . $limit);
            break;

        case 'invoice':
            $found = db_values("SELECT i.id FROM erp_invoices i LEFT JOIN erp_accounts a ON a.id = i.account_id
                WHERE (i.full_number LIKE '%" . $like . "%' OR a.title LIKE '%" . $like . "%')
                ORDER BY i.id DESC LIMIT " . $limit);
            break;

        case 'file':
            // Files with no folder are the ones other modules keep for
            // themselves (ERP documents, private channel attachments).
            $found = db_values("SELECT id FROM files
                WHERE folder > 0 AND name LIKE '%" . $like . "%'
                ORDER BY timestamp DESC LIMIT " . ($limit * 3));
            break;

        case 'page':
            $found = db_values("SELECT page_id FROM page WHERE page_name LIKE '%" . $like . "%' ORDER BY page_name LIMIT " . ($limit * 3));
            break;

        case 'task':
            $where = ($number !== '')
                ? "id = '" . (int) $number . "'"
                : "title LIKE '%" . $like . "%'";

            $found = db_values("SELECT id FROM ws_tasks WHERE " . $where . " ORDER BY (status IN ('done', 'cancelled')), id DESC LIMIT " . ($limit * 3));
            break;

        case 'channel':
            $found = db_values("SELECT id FROM ws_channels WHERE archived_at = 0 AND name LIKE '%" . $like . "%' ORDER BY name LIMIT " . ($limit * 3));
            break;

        case 'product_group':
            $found = db_values("SELECT id FROM product_groups
                WHERE recycled = 0 AND (name LIKE '%" . $like . "%' OR short_description LIKE '%" . $like . "%'" . (($number !== '') ? " OR id = '" . (int) $number . "'" : '') . ")
                ORDER BY enabled DESC, name LIMIT " . $limit);
            break;

        case 'offer':
            $found = db_values("SELECT id FROM offers
                WHERE (code LIKE '%" . $like . "%' OR description LIKE '%" . $like . "%')
                ORDER BY (status = 'enabled') DESC, id DESC LIMIT " . $limit);
            break;

        case 'user_account':
            $found = db_values("SELECT user_id FROM user
                WHERE (user_username LIKE '%" . $like . "%' OR user_email LIKE '%" . $like . "%'" . (($number !== '') ? " OR user_id = '" . (int) $number . "'" : '') . ")
                ORDER BY user_username LIMIT " . $limit);
            break;

        case 'erp_account':
            $found = db_values("SELECT id FROM erp_accounts
                WHERE (title LIKE '%" . $like . "%' OR tax_number LIKE '" . $like . "%' OR email LIKE '%" . $like . "%')
                ORDER BY (status = 'active') DESC, title LIMIT " . $limit);
            break;

        case 'waybill':
            $found = db_values("SELECT w.id FROM erp_waybills w LEFT JOIN erp_accounts a ON a.id = w.account_id
                WHERE (w.full_number LIKE '%" . $like . "%' OR a.title LIKE '%" . $like . "%')
                ORDER BY w.id DESC LIMIT " . $limit);
            break;

        case 'receipt':
            $where = ($number !== '')
                ? "c.id = '" . (int) $number . "'"
                : "(a.title LIKE '%" . $like . "%' OR c.description LIKE '%" . $like . "%')";

            $found = db_values("SELECT c.id FROM erp_cash_transactions c LEFT JOIN erp_accounts a ON a.id = c.account_id
                WHERE c.doc_type IN ('collection', 'payment') AND " . $where . "
                ORDER BY c.id DESC LIMIT " . $limit);
            break;

        case 'edoc':
            $found = db_values("SELECT id FROM erp_edoc_inbox
                WHERE (gib_number LIKE '%" . $like . "%' OR supplier_title LIKE '%" . $like . "%')
                ORDER BY id DESC LIMIT " . $limit);
            break;

        case 'form':
            // Newest first; what the reader may not open is dropped below,
            // so a few more are read than are shown.
            $found = db_values("SELECT f.id FROM forms f LEFT JOIN custom_form_pages c ON c.page_id = f.page_id
                WHERE (f.reference_code LIKE '%" . $like . "%' OR c.form_name LIKE '%" . $like . "%')
                ORDER BY f.id DESC LIMIT " . ($limit * 3));
            break;

        case 'calendar_event':
            $found = db_values("SELECT id FROM calendar_events
                WHERE (name LIKE '%" . $like . "%' OR location LIKE '%" . $like . "%')
                ORDER BY start_time DESC LIMIT " . ($limit * 3));
            break;

        case 'plan':
            $found = db_values("SELECT id FROM ws_events
                WHERE title LIKE '%" . $like . "%'
                ORDER BY starts_at DESC LIMIT " . ($limit * 3));
            break;
    }

    $tokens = array();

    foreach ((array) $found as $id) {
        $tokens[] = array('sigil' => '#', 'type' => $type, 'id' => (int) $id);
    }

    // In the order the search found them (newest first), not the order the
    // lookup happened to read them in.
    $resolved = ws_refs_resolve($viewer, $tokens);

    foreach ($tokens as $token) {
        $ref = $resolved[$token['type'] . ':' . $token['id']] ?? null;

        if (!$ref || !$ref['known']) {
            continue;
        }

        $out[] = array(
            'token' => '<#' . $ref['type'] . ':' . $ref['id'] . '>',
            'type'  => $ref['type'],
            'id'    => $ref['id'],
            'label' => $ref['label'],
            'meta'  => trim($ref['meta'] . (($ref['status'] !== '') ? ' · ' . $ref['status'] : ''), ' ·'),
            'icon'  => $ref['icon'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Where a record was talked about and which tasks point at it, for the
 * "Workspace" drawer on the record's own screen. Only what the reader may
 * see is counted: a mention in a channel they cannot read is not even
 * admitted to exist.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @param int    $limit
 * @return array messages, tasks, channels (customer channels, for a contact)
 */
function ws_record_refs($viewer, $type, $id, $limit = 20)
{
    $id = (int) $id;
    $out = array('messages' => array(), 'tasks' => array(), 'channels' => array(), 'count' => 0);

    if (!$viewer['member'] || ($id <= 0)) {
        return $out;
    }

    $rows = (array) db_items("SELECT r.source_type, r.source_id, r.channel_id
        FROM ws_refs r
        WHERE r.ref_type = '" . e($type) . "' AND r.ref_id = '" . $id . "'
        ORDER BY r.id DESC
        LIMIT 200");

    $message_ids = array();
    $task_ids = array();

    foreach ($rows as $row) {
        if ($row['source_type'] === 'message') {
            $message_ids[(int) $row['source_id']] = (int) $row['source_id'];
        } else {
            $task_ids[(int) $row['source_id']] = (int) $row['source_id'];
        }
    }

    if (!empty($message_ids)) {
        $messages = (array) db_items("SELECT m.id, m.channel_id, m.sender_id, m.body, m.kind, m.created_at, m.task_id
            FROM ws_messages m
            WHERE m.id IN (" . implode(',', $message_ids) . ") AND m.deleted_at = 0
            ORDER BY m.id DESC");

        $channels = array();

        foreach ($messages as $message) {
            $channel_id = (int) $message['channel_id'];

            if (!isset($channels[$channel_id])) {
                $channels[$channel_id] = ws_channel($channel_id);
            }

            if (!ws_can_read_channel($viewer, $channels[$channel_id])) {
                continue;
            }

            if (((int) $message['task_id'] > 0) && ($message['kind'] === 'task')) {
                $task_ids[(int) $message['task_id']] = (int) $message['task_id'];
            }

            if (count($out['messages']) < $limit) {
                $out['messages'][] = array(
                    'id'         => (int) $message['id'],
                    'channel_id' => $channel_id,
                    'channel'    => $channels[$channel_id]['name'],
                    'private'    => ($channels[$channel_id]['kind'] === 'private'),
                    'sender'     => ws_person_name($message['sender_id']),
                    'kind'       => $message['kind'],
                    'excerpt'    => ws_plain_excerpt($viewer, $message['body'], 140),
                    'time'       => ws_time_label($message['created_at']),
                    'url'        => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . $channel_id . '&message=' . (int) $message['id'],
                );
            }

            $out['count']++;
        }
    }

    if (!empty($task_ids)) {
        $tasks = (array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', $task_ids) . ") ORDER BY (status IN ('done', 'cancelled')), due_date IS NULL, due_date, id DESC");
        $assignees = ws_task_assignees_map(array_keys($task_ids));

        foreach ($tasks as $task) {
            $task_id = (int) $task['id'];

            if (!ws_can_see_task($viewer, $task, $assignees[$task_id] ?? array())) {
                continue;
            }

            $brief = ws_task_brief($task, $assignees[$task_id] ?? array());
            $brief['can_edit'] = ws_can_edit_task($viewer, $task, $assignees[$task_id] ?? array());
            $out['tasks'][] = $brief;
            $out['count']++;
        }
    }

    if ($type === 'contact') {
        foreach ((array) db_items("SELECT * FROM ws_channels WHERE contact_id = '" . $id . "' AND archived_at = 0 ORDER BY last_message_at DESC") as $channel) {
            if (ws_can_read_channel($viewer, $channel)) {
                $out['channels'][] = array(
                    'id'      => (int) $channel['id'],
                    'name'    => $channel['name'],
                    'private' => ($channel['kind'] === 'private'),
                    'url'     => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'],
                );
            }
        }
    }

    return $out;
}

/**
 * How many times a record appears where the reader can see it, for the badge
 * on the record screen's button. The same rule as ws_record_refs().
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @return int
 */
function ws_record_ref_count($viewer, $type, $id)
{
    $refs = ws_record_refs($viewer, $type, $id, 1);

    return (int) $refs['count'] + count($refs['channels']);
}
