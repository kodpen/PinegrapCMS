<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Personal data: everything the site keeps about one person, in one pack.
 *
 * A person is looked up by an e-mail address, a user or a contact, and taken
 * together with the records the site itself ties to them: the contact a user
 * names (user.user_contact), the users that name a contact, and the
 * addresses of all of them. Nothing further is followed, so a shared company
 * address does not pull in a second person through a record of theirs.
 *
 * The pack is read section by section (pg_privacy_export_sections()), each a
 * list of rows as they are stored, with secrets masked by the audit trail's
 * rule (pg_audit_secret_key()), stored IP numbers written as addresses and
 * bearer codes (gift card codes, push endpoints) cut to what identifies them.
 * ERP documents and Workspace channels are listed by reference only.
 * privacy_export.php hands it out as JSON, the full record, or as a PDF
 * summary to send to the person; every download is an audit_log line.
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
 * The sections of a pack, in the order they are written, with their names.
 *
 * @return array  key => label
 */
function pg_privacy_export_sections()
{
    return array(
        'user'               => lang('User account'),
        'sessions'           => lang('Sign-in sessions'),
        'two_step'           => lang('Two-step verification'),
        'identities'         => lang('Connected sign-in accounts'),
        'passkeys'           => lang('Passkeys'),
        'push_subscriptions' => lang('Device notifications'),
        'contact'            => lang('Contact'),
        'contact_groups'     => lang('Contact groups'),
        'address_book'       => lang('Address book'),
        'orders'             => lang('Orders'),
        'order_items'        => lang('Order items'),
        'ship_tos'           => lang('Shipping addresses'),
        'order_documents'    => lang('Accepted documents'),
        'returns'            => lang('Returns'),
        'refunds'            => lang('Refunds'),
        'gift_cards'         => lang('Gift cards'),
        'form_submissions'   => lang('Form submissions'),
        'signatures'         => lang('Signatures'),
        'comments'           => lang('Comments and reviews'),
        'watchers'           => lang('Watched items'),
        'wishlist'           => lang('Favourites'),
        'campaigns'          => lang('E-mail campaigns'),
        'consents'           => lang('Consents'),
        'mail'               => lang('E-mail messages'),
        'sms'                => lang('Text messages'),
        'chats'              => lang('Site chat'),
        'erp_accounts'       => lang('Current accounts'),
        'erp_documents'      => lang('Accounting documents'),
        'workspace'          => lang('Workspace'),
    );
}

/**
 * The most rows one section holds. A section that has more is cut there and
 * marked truncated, so one very large customer cannot exhaust the request.
 *
 * @return int
 */
function pg_privacy_export_row_limit()
{
    return 5000;
}

/**
 * The person as asked for: the address in its one form and the ids, each
 * list without duplicates. Anything that is not an address or an id is
 * dropped.
 *
 * @param array $input  any of email, user_id, contact_id
 * @return array  emails, user_ids, contact_ids
 */
function pg_privacy_export_seed(array $input)
{
    $email = pg_privacy_normalize_email(isset($input['email']) ? $input['email'] : '');

    return array(
        'emails'      => ($email !== '') ? array($email) : array(),
        'user_ids'    => _pg_privacy_export_ids(array(isset($input['user_id']) ? $input['user_id'] : 0)),
        'contact_ids' => _pg_privacy_export_ids(array(isset($input['contact_id']) ? $input['contact_id'] : 0)),
    );
}

/**
 * A person with the users and contacts found for them added: their ids, the
 * contact each user names and the address each record holds. Addresses keep
 * the order they were met in (the one asked for first); ids are sorted.
 *
 * @param array   $person    emails, user_ids, contact_ids
 * @param array[] $users     user rows
 * @param array[] $contacts  contacts rows
 * @return array  emails, user_ids, contact_ids
 */
function pg_privacy_export_merge(array $person, array $users, array $contacts)
{
    $emails = isset($person['emails']) ? (array) $person['emails'] : array();
    $user_ids = isset($person['user_ids']) ? (array) $person['user_ids'] : array();
    $contact_ids = isset($person['contact_ids']) ? (array) $person['contact_ids'] : array();

    foreach ($users as $row) {
        if (!is_array($row)) {
            continue;
        }

        $user_ids[] = isset($row['user_id']) ? $row['user_id'] : 0;
        $contact_ids[] = isset($row['user_contact']) ? $row['user_contact'] : 0;
        $emails[] = isset($row['user_email']) ? $row['user_email'] : '';
    }

    foreach ($contacts as $row) {
        if (!is_array($row)) {
            continue;
        }

        $contact_ids[] = isset($row['id']) ? $row['id'] : 0;
        $emails[] = isset($row['email_address']) ? $row['email_address'] : '';
    }

    $unique = array();

    foreach ($emails as $email) {
        $email = pg_privacy_normalize_email($email);

        if (($email !== '') && !in_array($email, $unique, true)) {
            $unique[] = $email;
        }
    }

    return array(
        'emails'      => $unique,
        'user_ids'    => _pg_privacy_export_ids($user_ids),
        'contact_ids' => _pg_privacy_export_ids($contact_ids),
    );
}

/**
 * Whether a person has anything to look them up by.
 *
 * @param array $person
 * @return bool
 */
function pg_privacy_export_is_empty(array $person)
{
    return empty($person['emails']) && empty($person['user_ids']) && empty($person['contact_ids']);
}

/**
 * Whether an operator may export a person who has these user accounts. The
 * rule of the sessions screen and of signing in as a user: an administrator
 * may export anyone, everyone else only themselves and users of a strictly
 * lower role (Designer 1 is above Manager 2).
 *
 * @param int     $operator_role
 * @param int     $operator_id
 * @param array[] $users  user rows (user_id, user_role)
 * @return bool
 */
function pg_privacy_export_allowed($operator_role, $operator_id, array $users)
{
    $operator_role = (int) $operator_role;

    if ($operator_role === 0) {
        return true;
    }

    foreach ($users as $row) {
        if (!is_array($row) || ((int) ($row['user_id'] ?? 0) === (int) $operator_id)) {
            continue;
        }

        if ((int) ($row['user_role'] ?? 3) <= $operator_role) {
            return false;
        }
    }

    return true;
}

/**
 * A row as it goes into the pack: secrets masked by name ('***', an empty
 * value left empty), the columns named in $mask masked the same way, the
 * columns in $drop left out, and an IP number (ip_address kept as
 * INET_ATON) written as an address. Nested lists are cleaned the same way.
 *
 * @param array    $row
 * @param string[] $mask
 * @param string[] $drop
 * @return array
 */
function pg_privacy_export_clean(array $row, array $mask = array(), array $drop = array())
{
    $clean = array();

    foreach ($row as $key => $value) {
        $name = (string) $key;

        if (in_array($name, $drop, true)) {
            continue;
        }

        if (is_array($value)) {
            $clean[$key] = pg_privacy_export_clean($value, $mask, $drop);
            continue;
        }

        if (!is_int($key) && (pg_audit_secret_key($name) || in_array($name, $mask, true))) {
            $clean[$key] = (($value === null) || ($value === '')) ? $value : '***';
            continue;
        }

        if (($name === 'ip_address') && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
            $number = (int) $value;
            $clean[$key] = (($number > 0) && ($number <= 4294967295)) ? long2ip($number) : '';
            continue;
        }

        $clean[$key] = $value;
    }

    return $clean;
}

/**
 * A bearer code cut to its last four characters, enough to tell it apart.
 *
 * @param mixed $code
 * @return string
 */
function pg_privacy_export_code_hint($code)
{
    $code = trim((string) $code);

    if ($code === '') {
        return '';
    }

    return '…' . ((strlen($code) > 4) ? substr($code, -4) : '');
}

/**
 * The pack: what it is, when and where it was made, who it is about, and
 * every section with its rows (all of them listed, empty ones too, so the
 * reader sees what was looked at).
 *
 * @param array $person     emails, user_ids, contact_ids
 * @param array $collected  section key => rows, truncated
 * @param array $meta       time, site, prepared_by
 * @return array
 */
function pg_privacy_export_document(array $person, array $collected, array $meta)
{
    $sections = array();

    foreach (pg_privacy_export_sections() as $key => $label) {
        $part = (isset($collected[$key]) && is_array($collected[$key])) ? $collected[$key] : array();
        $rows = array();

        foreach ((isset($part['rows']) && is_array($part['rows'])) ? $part['rows'] : array() as $row) {
            if (is_array($row)) {
                $rows[] = pg_privacy_export_clean($row);
            }
        }

        $sections[$key] = array(
            'label'     => (string) $label,
            'count'     => count($rows),
            'truncated' => !empty($part['truncated']),
            'rows'      => $rows,
        );
    }

    $time = isset($meta['time']) ? (int) $meta['time'] : time();

    return array(
        'format'       => 'pinegrap-personal-data',
        'version'      => 1,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z', $time),
        'site'         => isset($meta['site']) ? (string) $meta['site'] : '',
        'prepared_by'  => isset($meta['prepared_by']) ? (string) $meta['prepared_by'] : '',
        'person'       => array(
            'emails'      => array_values(isset($person['emails']) ? (array) $person['emails'] : array()),
            'user_ids'    => _pg_privacy_export_ids(isset($person['user_ids']) ? (array) $person['user_ids'] : array()),
            'contact_ids' => _pg_privacy_export_ids(isset($person['contact_ids']) ? (array) $person['contact_ids'] : array()),
        ),
        'sections'     => $sections,
    );
}

/**
 * How many rows each section of a pack holds.
 *
 * @param array $document
 * @return array  key => count
 */
function pg_privacy_export_counts(array $document)
{
    $counts = array();

    foreach ((isset($document['sections']) && is_array($document['sections'])) ? $document['sections'] : array() as $key => $section) {
        $counts[$key] = isset($section['count']) ? (int) $section['count'] : 0;
    }

    return $counts;
}

/**
 * The pack as the JSON file: readable, UTF-8 as it is, an invalid byte
 * replaced rather than the whole file lost.
 *
 * @param array $document
 * @return string
 */
function pg_privacy_export_json(array $document)
{
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE; // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined() above
    }

    $json = json_encode($document, $flags);

    return is_string($json) ? $json . "\n" : "{}\n";
}

/**
 * The download's file name: ASCII only, after the person's first address
 * (or the first id when there is none) and the day it was made.
 *
 * @param array    $person
 * @param string   $extension  json | pdf
 * @param int|null $time
 * @return string
 */
function pg_privacy_export_filename(array $person, $extension, $time = null)
{
    $who = '';

    if (!empty($person['emails'])) {
        $who = (string) reset($person['emails']);
    } elseif (!empty($person['user_ids'])) {
        $who = 'user-' . (int) reset($person['user_ids']);
    } elseif (!empty($person['contact_ids'])) {
        $who = 'contact-' . (int) reset($person['contact_ids']);
    }

    $who = strtolower(pg_transliterate_to_ascii($who));
    $who = trim((string) preg_replace('/[^a-z0-9]+/', '-', $who), '-');
    $who = substr($who, 0, 60);
    $extension = preg_replace('/[^a-z0-9]/', '', strtolower((string) $extension));

    return 'personal-data' . (($who !== '') ? '-' . rtrim($who, '-') : '') . '-' . date('Y-m-d', ($time === null) ? time() : (int) $time) . '.' . $extension;
}

/**
 * The audit line of one download: the address it was made for as the label,
 * and in the after side the format, the person token (so the line can be
 * found by it once the address is gone), the ids and the sections that held
 * rows. The key is "person", not "person_token": a name ending in "token" is
 * masked by the trail.
 *
 * @param array  $document
 * @param string $format  json | pdf
 * @return array  label, after
 */
function pg_privacy_export_audit_entry(array $document, $format)
{
    $person = isset($document['person']) ? $document['person'] : array();
    $emails = isset($person['emails']) ? (array) $person['emails'] : array();
    $user_ids = isset($person['user_ids']) ? (array) $person['user_ids'] : array();
    $contact_ids = isset($person['contact_ids']) ? (array) $person['contact_ids'] : array();

    $label = $emails ? (string) reset($emails) : ($user_ids ? '#' . (int) reset($user_ids) : ($contact_ids ? '#' . (int) reset($contact_ids) : ''));

    $sections = array();

    foreach (pg_privacy_export_counts($document) as $key => $count) {
        if ($count > 0) {
            $sections[$key] = $count;
        }
    }

    return array(
        'label' => $label,
        'after' => array(
            'format'      => (string) $format,
            'person'      => $emails ? pg_privacy_person_token(reset($emails)) : '',
            'user_ids'    => implode(',', array_map('intval', $user_ids)),
            'contact_ids' => implode(',', array_map('intval', $contact_ids)),
            'sections'    => $sections,
        ),
    );
}

/**
 * Write the audit line of one download.
 *
 * @param array  $document
 * @param string $format
 * @return bool  whether a line was written
 */
function pg_privacy_export_log(array $document, $format)
{
    $entry = pg_privacy_export_audit_entry($document, $format);

    return pg_audit('export', 'person', 0, array(), $entry['after'], $entry['label']);
}

/**
 * The summary as HTML for the PDF: who the pack is about, how many records
 * each section holds, and the parts a person reads first (contact details,
 * accounts, addresses, orders, current consents, form submissions,
 * accounting documents). The full record is the JSON file.
 *
 * @param array $document  see pg_privacy_export_document()
 * @return string
 */
function pg_privacy_export_summary_html(array $document)
{
    $sections = isset($document['sections']) ? $document['sections'] : array();
    $rows_of = function ($key) use ($sections) {
        return (isset($sections[$key]['rows']) && is_array($sections[$key]['rows'])) ? $sections[$key]['rows'] : array();
    };

    $person = isset($document['person']) ? $document['person'] : array();

    // Names the person is known by, from the records themselves.
    $names = array();

    foreach ($rows_of('contact') as $row) {
        $names[] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    foreach ($rows_of('orders') as $row) {
        $names[] = trim(($row['billing_first_name'] ?? '') . ' ' . ($row['billing_last_name'] ?? ''));
    }

    $names = array_values(array_unique(array_filter($names, 'strlen')));

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . h(lang('Personal data summary')) . '</title><style>'
        . '@page{margin:18mm 16mm}'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#222}'
        . 'h1{font-size:15pt;margin:0 0 4pt}'
        . 'h2{font-size:11pt;margin:14pt 0 4pt;border-bottom:1px solid #999;padding-bottom:2pt}'
        . 'p{margin:0 0 4pt}'
        . '.muted{color:#666}'
        . 'table{width:100%;border-collapse:collapse;margin:0 0 6pt}'
        . 'th,td{border:1px solid #ccc;padding:3pt 4pt;text-align:left;vertical-align:top}'
        . 'th{background:#f0f0f0}'
        . '.num{text-align:right;white-space:nowrap}'
        . '</style></head><body>';

    $html .= '<h1>' . h(lang('Personal data summary')) . '</h1>';
    $html .= '<p class="muted">' . h(lang(array(
        'string' => 'Prepared on {var:1} by {var:2} for {var:3}.',
        'vars'   => array(
            _pg_privacy_export_time(strtotime((string) ($document['generated_at'] ?? '')) ?: 0),
            (string) ($document['prepared_by'] ?? ''),
            (string) ($document['site'] ?? ''),
        ),
    ))) . '</p>';

    $html .= '<h2>' . h(lang('Who this is about')) . '</h2><table>';
    $html .= _pg_privacy_export_summary_line(lang('Name'), implode(', ', $names));
    $html .= _pg_privacy_export_summary_line(lang('E-mail Address'), implode(', ', isset($person['emails']) ? (array) $person['emails'] : array()));

    $accounts = array();

    foreach ($rows_of('user') as $row) {
        $accounts[] = ($row['user_username'] ?? '') . ' (' . pg_user_role_name($row['user_role'] ?? 3) . ')';
    }

    $html .= _pg_privacy_export_summary_line(lang('User account'), implode(', ', $accounts));
    $html .= '</table>';

    // What the site keeps, section by section.
    $html .= '<h2>' . h(lang('What the site keeps')) . '</h2><table><tr><th>' . h(lang('Section')) . '</th><th class="num">' . h(lang('Records')) . '</th></tr>';

    foreach ($sections as $section) {
        $count = (int) ($section['count'] ?? 0);
        $html .= '<tr><td>' . h((string) ($section['label'] ?? '')) . '</td><td class="num">' . $count . (!empty($section['truncated']) ? ' +' : '') . '</td></tr>';
    }

    $html .= '</table>';

    if (array_filter($sections, function ($section) {
        return !empty($section['truncated']);
    })) {
        $html .= '<p class="muted">' . h(lang(array(
            'string' => 'A count marked + is cut at {var:1} records; the JSON file is cut at the same place.',
            'vars'   => array(pg_privacy_export_row_limit()),
        ))) . '</p>';
    }

    // Contact details.
    foreach ($rows_of('contact') as $row) {
        $html .= '<h2>' . h(lang('Contact')) . ' #' . (int) ($row['id'] ?? 0) . '</h2><table>';
        $html .= _pg_privacy_export_summary_line(lang('Name'), trim(($row['salutation'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        $html .= _pg_privacy_export_summary_line(lang('Company'), (string) ($row['company'] ?? ''));
        $html .= _pg_privacy_export_summary_line(lang('E-mail Address'), (string) ($row['email_address'] ?? ''));
        $html .= _pg_privacy_export_summary_line(lang('Phone'), implode(', ', array_filter(array((string) ($row['mobile_phone'] ?? ''), (string) ($row['home_phone'] ?? ''), (string) ($row['business_phone'] ?? '')), 'strlen')));
        $html .= _pg_privacy_export_summary_line(lang('Home address'), _pg_privacy_export_address($row, 'home_'));
        $html .= _pg_privacy_export_summary_line(lang('Business address'), _pg_privacy_export_address($row, 'business_'));
        $html .= '</table>';
    }

    // Address book.
    $book = $rows_of('address_book');

    if ($book) {
        $html .= '<h2>' . h(lang('Address book')) . '</h2><table><tr><th>' . h(lang('Name')) . '</th><th>' . h(lang('Address')) . '</th><th>' . h(lang('Phone')) . '</th></tr>';

        foreach ($book as $row) {
            $html .= '<tr><td>' . h(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) . '</td><td>' . h(_pg_privacy_export_address($row, '')) . '</td><td>' . h((string) ($row['phone_number'] ?? '')) . '</td></tr>';
        }

        $html .= '</table>';
    }

    // Orders.
    $orders = $rows_of('orders');

    if ($orders) {
        $statuses = array(
            'incomplete' => lang('Incomplete'),
            'complete'   => lang('Complete'),
            'exported'   => lang('Exported'),
            'cancelled'  => lang('Cancelled'),
            'canceled'   => lang('Cancelled'),
        );

        $html .= '<h2>' . h(lang('Orders')) . '</h2><table><tr><th>' . h(lang('Order Number')) . '</th><th>' . h(lang('Date')) . '</th><th>' . h(lang('Status')) . '</th><th class="num">' . h(lang('Total')) . '</th></tr>';

        foreach ($orders as $row) {
            $status = (string) ($row['status'] ?? '');
            $number = (string) ($row['order_number'] ?? '');

            $html .= '<tr><td>' . h(($number !== '' && $number !== '0') ? $number : '#' . (int) ($row['id'] ?? 0)) . '</td>'
                . '<td>' . h(_pg_privacy_export_time((int) ($row['order_date'] ?? 0))) . '</td>'
                . '<td>' . h(isset($statuses[$status]) ? $statuses[$status] : $status) . '</td>'
                . '<td class="num">' . h(_pg_privacy_export_money($row['total'] ?? 0, (string) ($row['currency_code'] ?? ''))) . '</td></tr>';
        }

        $html .= '</table>';
    }

    // The current answer of each consent.
    $consents = pg_consent_log_current($rows_of('consents'));

    if ($consents) {
        $html .= '<h2>' . h(lang('Consents')) . '</h2><table><tr><th>' . h(lang('Consent')) . '</th><th>' . h(lang('Answer')) . '</th><th>' . h(lang('Date')) . '</th><th>' . h(lang('Source')) . '</th></tr>';

        foreach ($consents as $key => $row) {
            $html .= '<tr><td>' . h((string) $key) . '</td>'
                . '<td>' . h(_pg_privacy_export_consent_answer((string) ($row['status'] ?? ''))) . '</td>'
                . '<td>' . h(_pg_privacy_export_time((int) ($row['created_at'] ?? 0))) . '</td>'
                . '<td>' . h((string) ($row['source'] ?? '')) . '</td></tr>';
        }

        $html .= '</table>';
    }

    // Form submissions.
    $forms = $rows_of('form_submissions');

    if ($forms) {
        $html .= '<h2>' . h(lang('Form submissions')) . '</h2><table><tr><th>' . h(lang('Form')) . '</th><th>' . h(lang('Date')) . '</th><th>' . h(lang('Reference')) . '</th></tr>';

        foreach ($forms as $row) {
            $html .= '<tr><td>' . h((string) ($row['form_name'] ?? '')) . '</td><td>' . h(_pg_privacy_export_time((int) ($row['submitted_timestamp'] ?? 0))) . '</td><td>' . h((string) ($row['reference_code'] ?? '')) . '</td></tr>';
        }

        $html .= '</table>';
    }

    // Accounting documents, by reference.
    $documents = $rows_of('erp_documents');

    if ($documents) {
        $kinds = array('invoice' => lang('Invoice'), 'quote' => lang('Sales quote'), 'waybill' => lang('Delivery Note'));

        $html .= '<h2>' . h(lang('Accounting documents')) . '</h2><table><tr><th>' . h(lang('Type')) . '</th><th>' . h(lang('Document number')) . '</th><th>' . h(lang('Date')) . '</th><th class="num">' . h(lang('Total')) . '</th></tr>';

        foreach ($documents as $row) {
            $kind = (string) ($row['document'] ?? '');

            $html .= '<tr><td>' . h(isset($kinds[$kind]) ? $kinds[$kind] : $kind) . '</td>'
                . '<td>' . h((string) ($row['full_number'] ?? '')) . '</td>'
                . '<td>' . h((string) ($row['issue_date'] ?? '')) . '</td>'
                . '<td class="num">' . (isset($row['grand_total']) ? h(_pg_privacy_export_money($row['grand_total'], (string) ($row['currency'] ?? ''))) : '') . '</td></tr>';
        }

        $html .= '</table>';
    }

    $html .= '<p class="muted" style="margin-top:12pt">' . h(lang('This summary lists what the site keeps about the person. The complete record is the JSON file prepared with it.')) . '</p>';

    return $html . '</body></html>';
}

/**
 * The summary as PDF bytes, rendered as the ERP renders its documents:
 * remote fetching and embedded PHP off, rooted at the software directory,
 * font caches under data/cache (see erp_invoice_pdf()).
 *
 * @param string $html
 * @return string|false  false when the library is not installed
 */
function pg_privacy_export_pdf($html)
{
    $autoload = PG_FUNCTIONS_DIR . '/includes/dompdf/autoload.inc.php';

    if (!is_file($autoload)) {
        return false;
    }

    // The vendored autoloader checks the same include gate as the API entry.
    if (!defined('PG_API_ENTRY')) {
        define('PG_API_ENTRY', true);
    }

    require_once($autoload);

    if (!class_exists('\Dompdf\Dompdf')) {
        return false;
    }

    $cache_directory = PG_FUNCTIONS_DIR . '/data/cache/dompdf';

    if (!is_dir($cache_directory)) {
        @mkdir($cache_directory, 0755, true);
    }

    $options = new \Dompdf\Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    $options->setChroot(PG_FUNCTIONS_DIR);
    $options->setFontCache($cache_directory);
    $options->setTempDir($cache_directory);
    $options->setLogOutputFile('');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml((string) $html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Find a person: the users and contacts named or found by address, and the
 * records tied to them (a user's contact, the users of those contacts).
 *
 * @param array $input  any of email, user_id, contact_id
 * @return array  person (emails, user_ids, contact_ids), users and contacts
 *                (rows keyed by id)
 */
function pg_privacy_export_find(array $input)
{
    $person = pg_privacy_export_seed($input);
    $found = array('person' => $person, 'users' => array(), 'contacts' => array());

    if (pg_privacy_export_is_empty($person)) {
        return $found;
    }

    $users = _pg_privacy_export_users(_pg_privacy_export_any(array(
        _pg_privacy_export_in('user_id', $person['user_ids']),
        _pg_privacy_export_in('user_email', $person['emails']),
        _pg_privacy_export_in('user_contact', $person['contact_ids']),
    )));

    $person = pg_privacy_export_merge($person, $users, array());

    $contacts = array();
    $where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('id', $person['contact_ids']),
        _pg_privacy_export_in('email_address', $person['emails']),
    ));

    if ($where !== '') {
        foreach (db_items("SELECT * FROM contacts WHERE " . $where . " ORDER BY id LIMIT 500") as $row) {
            $contacts[(int) $row['id']] = $row;
        }
    }

    $person = pg_privacy_export_merge($person, array(), $contacts);

    // The users of contacts found by address.
    $users += _pg_privacy_export_users(_pg_privacy_export_in('user_contact', $person['contact_ids']));
    ksort($users);

    return array(
        'person'   => pg_privacy_export_merge($person, $users, array()),
        'users'    => $users,
        'contacts' => $contacts,
    );
}

/**
 * Read every section of a person's pack.
 *
 * @param array $found  see pg_privacy_export_find()
 * @return array  section key => rows, truncated
 */
function pg_privacy_export_collect(array $found)
{
    $person = $found['person'];
    $users = array_values($found['users']);
    $contacts = array_values($found['contacts']);
    $user_ids = $person['user_ids'];
    $contact_ids = $person['contact_ids'];
    $emails = $person['emails'];
    $limit = pg_privacy_export_row_limit();

    $sections = array(
        'user'    => array('rows' => $users, 'truncated' => false),
        'contact' => array('rows' => $contacts, 'truncated' => false),
    );

    // Sign-in.
    if (pg_schema_has('auth_tokens', 'user_id')) {
        $sections['sessions'] = _pg_privacy_export_select("SELECT * FROM auth_tokens WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " ORDER BY created_at DESC", $user_ids);
    }

    if (pg_schema_has('user_mfa', 'user_id')) {
        $sections['two_step'] = _pg_privacy_export_select("SELECT * FROM user_mfa WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " ORDER BY user_id", $user_ids);

        if ($sections['two_step']['rows'] && pg_schema_has('user_mfa_recovery', 'used_at')) {
            $codes = array();

            foreach (db_items("SELECT user_id, SUM(used_at = 0) AS unused, SUM(used_at > 0) AS used FROM user_mfa_recovery WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " GROUP BY user_id") as $row) {
                $codes[(int) $row['user_id']] = $row;
            }

            foreach ($sections['two_step']['rows'] as $index => $row) {
                $code = isset($codes[(int) $row['user_id']]) ? $codes[(int) $row['user_id']] : array('unused' => 0, 'used' => 0);
                $sections['two_step']['rows'][$index]['recovery_codes_unused'] = (int) $code['unused'];
                $sections['two_step']['rows'][$index]['recovery_codes_used'] = (int) $code['used'];
            }
        }
    }

    if (pg_schema_has('user_identities', 'user_id')) {
        $sections['identities'] = _pg_privacy_export_select("SELECT * FROM user_identities WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " ORDER BY id", $user_ids);
    }

    // What the person named each passkey and when it was used; the credential
    // itself (its id, public key, user handle, counter) is the site's key
    // material, not the person's data, and is not read.
    if (pg_schema_has('user_passkeys', 'user_id')) {
        $sections['passkeys'] = _pg_privacy_export_select("SELECT id, user_id, name, aaguid, transports, backup_eligible, backup_state, created_at, last_used_at FROM user_passkeys WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " ORDER BY id", $user_ids);
    }

    if (pg_schema_has('push_subscriptions', 'user_id')) {
        $sections['push_subscriptions'] = _pg_privacy_export_select("SELECT * FROM push_subscriptions WHERE " . _pg_privacy_export_in('user_id', $user_ids) . " ORDER BY id", $user_ids, array('endpoint', 'p256dh', 'auth'), array('endpoint_hash'));
    }

    // Contact groups, with the opt-in of each.
    $sections['contact_groups'] = _pg_privacy_export_select(
        "SELECT x.contact_id, x.contact_group_id, g.name AS contact_group_name, o.opt_in
        FROM contacts_contact_groups_xref AS x
        LEFT JOIN contact_groups AS g ON g.id = x.contact_group_id
        LEFT JOIN opt_in AS o ON (o.contact_id = x.contact_id) AND (o.contact_group_id = x.contact_group_id)
        WHERE " . _pg_privacy_export_in('x.contact_id', $contact_ids) . "
        ORDER BY x.contact_id, x.contact_group_id",
        $contact_ids
    );

    $sections['address_book'] = _pg_privacy_export_select("SELECT * FROM address_book WHERE " . _pg_privacy_export_in('user', $user_ids) . " ORDER BY id", $user_ids);

    // Orders, carts and the lines saved for later included, and what hangs off them.
    $order_where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('user_id', $user_ids),
        _pg_privacy_export_in('contact_id', $contact_ids),
        _pg_privacy_export_in('billing_email_address', $emails),
    ));

    $sections['orders'] = _pg_privacy_export_select("SELECT * FROM orders WHERE " . $order_where . " ORDER BY id", ($order_where !== '') ? array(1) : array());
    $order_ids = _pg_privacy_export_column($sections['orders']['rows'], 'id');

    if ($order_ids) {
        // A product form's answers belong to the order line they were given for.
        $answers = array();

        foreach (db_items("SELECT order_id, order_item_id, ship_to_id, quantity_number, form_field_id, name, data, type, file_id FROM form_data WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY order_id, id LIMIT " . ($limit * 20)) as $row) {
            $answers[(int) $row['order_id']][] = $row;
        }

        foreach ($sections['orders']['rows'] as $index => $row) {
            if (isset($answers[(int) $row['id']])) {
                $sections['orders']['rows'][$index]['form_answers'] = $answers[(int) $row['id']];
            }
        }
    }

    $sections['order_items'] = _pg_privacy_export_select("SELECT * FROM order_items WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY order_id, id", $order_ids);
    $sections['ship_tos'] = _pg_privacy_export_select("SELECT * FROM ship_tos WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY order_id, id", $order_ids);

    if (pg_schema_has('order_documents', 'order_id')) {
        $sections['order_documents'] = _pg_privacy_export_select("SELECT * FROM order_documents WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY order_id, id", $order_ids);
    }

    if (pg_schema_has('order_returns', 'order_id')) {
        $sections['returns'] = _pg_privacy_export_select("SELECT * FROM order_returns WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY id", $order_ids);
        $return_ids = _pg_privacy_export_column($sections['returns']['rows'], 'id');

        if ($return_ids && pg_schema_has('order_return_items', 'return_id')) {
            $lines = array();

            foreach (db_items("SELECT * FROM order_return_items WHERE " . _pg_privacy_export_in('return_id', $return_ids) . " ORDER BY id") as $row) {
                $lines[(int) $row['return_id']][] = $row;
            }

            foreach ($sections['returns']['rows'] as $index => $row) {
                $sections['returns']['rows'][$index]['items'] = isset($lines[(int) $row['id']]) ? $lines[(int) $row['id']] : array();
            }
        }
    }

    if (pg_schema_has('order_refunds', 'order_id')) {
        $sections['refunds'] = _pg_privacy_export_select("SELECT * FROM order_refunds WHERE " . _pg_privacy_export_in('order_id', $order_ids) . " ORDER BY id", $order_ids);
    }

    // Gift cards bought in the person's orders or sent to them; the code is a
    // bearer secret, so only its end is written.
    $gift_where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('order_id', $order_ids),
        _pg_privacy_export_in('recipient_email_address', $emails),
    ));

    $sections['gift_cards'] = _pg_privacy_export_select("SELECT * FROM gift_cards WHERE " . $gift_where . " ORDER BY id", ($gift_where !== '') ? array(1) : array());

    foreach ($sections['gift_cards']['rows'] as $index => $row) {
        $sections['gift_cards']['rows'][$index]['code'] = pg_privacy_export_code_hint($row['code'] ?? '');
    }

    // Form submissions: the person's own, and those that name their address
    // in a field (a contact form sent before they had an account).
    $form_ids = array();

    if ($emails) {
        $form_ids = _pg_privacy_export_ids(db_values("SELECT DISTINCT form_id FROM form_data WHERE (form_id > 0) AND " . _pg_privacy_export_in('data', $emails) . " LIMIT " . ($limit + 1)));
    }

    $form_where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('f.user_id', $user_ids),
        _pg_privacy_export_in('f.contact_id', $contact_ids),
        _pg_privacy_export_in('f.id', $form_ids),
    ));

    $sections['form_submissions'] = _pg_privacy_export_select(
        "SELECT f.*, p.form_name
        FROM forms AS f
        LEFT JOIN custom_form_pages AS p ON p.page_id = f.page_id
        WHERE " . $form_where . "
        ORDER BY f.id",
        ($form_where !== '') ? array(1) : array()
    );

    $submission_ids = _pg_privacy_export_column($sections['form_submissions']['rows'], 'id');

    if ($submission_ids) {
        $fields = array();

        foreach (db_items("SELECT form_id, form_field_id, name, data, type, file_id FROM form_data WHERE " . _pg_privacy_export_in('form_id', $submission_ids) . " ORDER BY form_id, id LIMIT " . ($limit * 20)) as $row) {
            $fields[(int) $row['form_id']][] = $row;
        }

        foreach ($sections['form_submissions']['rows'] as $index => $row) {
            $sections['form_submissions']['rows'][$index]['fields'] = isset($fields[(int) $row['id']]) ? $fields[(int) $row['id']] : array();
        }
    }

    // Signatures given in a form, without the pen strokes and the timestamp
    // token, which are large and are kept to prove the image, not to read.
    if (pg_schema_has('form_signatures', 'user_id')) {
        $signature_where = _pg_privacy_export_any(array(
            _pg_privacy_export_in('user_id', $user_ids),
            _pg_privacy_export_in('form_id', $submission_ids),
        ));

        $sections['signatures'] = _pg_privacy_export_select("SELECT * FROM form_signatures WHERE " . $signature_where . " ORDER BY id", ($signature_where !== '') ? array(1) : array(), array(), array('strokes', 'stamp_token'));
    }

    $sections['comments'] = _pg_privacy_export_select("SELECT * FROM comments WHERE " . _pg_privacy_export_in('created_user_id', $user_ids) . " ORDER BY id", $user_ids);

    $watch_where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('user_id', $user_ids),
        _pg_privacy_export_in('email_address', $emails),
    ));

    $sections['watchers'] = _pg_privacy_export_select("SELECT * FROM watchers WHERE " . $watch_where . " ORDER BY id", ($watch_where !== '') ? array(1) : array());

    // Favourites, with the product's SKU and name. A visitor's list before
    // signing in is kept under a cookie key that names nobody; it joins the
    // account at sign-in (pg_wishlist_merge()) and is found from then on.
    if (pg_wishlist_ready()) {
        $sections['wishlist'] = _pg_privacy_export_select(
            "SELECT wishlist_items.id, wishlist_items.user_id, wishlist_items.product_id, products.name AS product_sku, products.short_description AS product_name, wishlist_items.added_at
            FROM wishlist_items
            LEFT JOIN products ON products.id = wishlist_items.product_id
            WHERE " . _pg_privacy_export_in('wishlist_items.user_id', $user_ids) . "
            ORDER BY wishlist_items.id",
            $user_ids
        );
    }

    $campaign_where = _pg_privacy_export_any(array(
        _pg_privacy_export_in('r.contact_id', $contact_ids),
        _pg_privacy_export_in('r.email_address', $emails),
    ));

    $sections['campaigns'] = _pg_privacy_export_select(
        "SELECT r.*, c.subject AS campaign_subject, c.start_time AS campaign_start_time
        FROM email_recipients AS r
        LEFT JOIN email_campaigns AS c ON c.id = r.email_campaign_id
        WHERE " . $campaign_where . "
        ORDER BY r.id",
        ($campaign_where !== '') ? array(1) : array(),
        array(),
        array('claim_token')
    );

    // Consents: by token, user or contact; the token itself is a pseudonym
    // of the address and says nothing to the person.
    if (pg_consent_log_ready()) {
        $tokens = array_values(array_filter(array_map('pg_privacy_person_token', $emails), 'strlen'));

        $consent_where = _pg_privacy_export_any(array(
            _pg_privacy_export_in('person_token', $tokens),
            _pg_privacy_export_in('user_id', $user_ids),
            _pg_privacy_export_in('contact_id', $contact_ids),
        ));

        $sections['consents'] = _pg_privacy_export_select("SELECT * FROM consent_log WHERE " . $consent_where . " ORDER BY created_at DESC, id DESC", ($consent_where !== '') ? array(1) : array(), array(), array('person_token'));
    }

    // Messages sent to the person, without what the message itself carried.
    if (pg_schema_has('mail_outbox', 'recipient')) {
        $sections['mail'] = _pg_privacy_export_select("SELECT id, created_at, send_after, status, attempts, sent_at, mail_type, recipient, subject FROM mail_outbox WHERE " . _pg_privacy_export_in('recipient', $emails) . " ORDER BY id", $emails);
    }

    if (pg_schema_has('sms_outbox', 'recipient')) {
        $phones = pg_privacy_export_phones(array_merge($contacts, $sections['address_book']['rows'], $sections['orders']['rows'], $sections['ship_tos']['rows']));

        $sections['sms'] = _pg_privacy_export_select("SELECT id, created_at, send_after, status, attempts, sent_at, kind, recipient, body, reference FROM sms_outbox WHERE " . _pg_privacy_export_in('recipient', $phones) . " ORDER BY id", $phones);
    }

    // Conversations a visitor had through the site's chat, with their messages.
    if (pg_schema_has('chat_conversations', 'party_email')) {
        $chat_where = _pg_privacy_export_any(array(
            _pg_privacy_export_in('party_email', $emails),
            ($user_ids ? "((channel = 'site') AND " . _pg_privacy_export_in('initiator_user_id', $user_ids) . ")" : ''),
        ));

        $sections['chats'] = _pg_privacy_export_select("SELECT * FROM chat_conversations WHERE (channel = 'site') AND " . $chat_where . " ORDER BY id", ($chat_where !== '') ? array(1) : array());
        $conversation_ids = _pg_privacy_export_column($sections['chats']['rows'], 'id');

        if ($conversation_ids && pg_schema_has('chat_messages', 'conversation_id')) {
            $messages = array();

            foreach (db_items("SELECT id, conversation_id, sender_kind, sender_user_id, body, attachment_file_id, attachment_kind, attachment_name, created_at FROM chat_messages WHERE " . _pg_privacy_export_in('conversation_id', $conversation_ids) . " ORDER BY conversation_id, id LIMIT " . ($limit * 20)) as $row) {
                $messages[(int) $row['conversation_id']][] = $row;
            }

            foreach ($sections['chats']['rows'] as $index => $row) {
                $sections['chats']['rows'][$index]['messages'] = isset($messages[(int) $row['id']]) ? $messages[(int) $row['id']] : array();
            }
        }
    }

    // ERP: the person's current accounts, and their documents by reference.
    if (pg_schema_has('erp_accounts', 'contact_id')) {
        $account_ids = array_merge(
            _pg_privacy_export_column($contacts, 'erp_account_id'),
            _pg_privacy_export_column($sections['orders']['rows'], 'erp_account_id')
        );

        $account_where = _pg_privacy_export_any(array(
            _pg_privacy_export_in('id', _pg_privacy_export_ids($account_ids)),
            _pg_privacy_export_in('contact_id', $contact_ids),
            _pg_privacy_export_in('email', $emails),
        ));

        $sections['erp_accounts'] = _pg_privacy_export_select("SELECT * FROM erp_accounts WHERE " . $account_where . " ORDER BY id", ($account_where !== '') ? array(1) : array());
        $account_ids = _pg_privacy_export_column($sections['erp_accounts']['rows'], 'id');

        $documents = array('rows' => array(), 'truncated' => false);

        foreach (
            array(
                'invoice' => array('erp_invoices', 'id, direction, doc_type, invoice_type, full_number, issue_date, due_date, currency, grand_total, paid_total, status, order_id, edoc_kind, edoc_status, gib_number'),
                'quote'   => array('erp_quotes', 'id, full_number, issue_date, valid_until, currency, grand_total, status'),
                'waybill' => array('erp_waybills', 'id, full_number, issue_date, ship_date, status, order_id'),
            ) as $kind => $source
        ) {
            if (!pg_schema_has($source[0], 'account_id')) {
                continue;
            }

            $part = _pg_privacy_export_select("SELECT '" . $kind . "' AS document, " . $source[1] . " FROM " . $source[0] . " WHERE " . _pg_privacy_export_in('account_id', $account_ids) . " ORDER BY issue_date, id", $account_ids);
            $documents['rows'] = array_merge($documents['rows'], $part['rows']);
            $documents['truncated'] = $documents['truncated'] || $part['truncated'];
        }

        $sections['erp_documents'] = $documents;
    }

    // Workspace: the channels kept about the person (by reference) and the
    // approvals they were asked for by e-mail.
    $workspace = array('rows' => array(), 'truncated' => false);

    if (pg_schema_has('ws_channels', 'contact_id')) {
        $part = _pg_privacy_export_select("SELECT 'channel' AS record, id, name, kind, created_at, last_message_at, archived_at FROM ws_channels WHERE " . _pg_privacy_export_in('contact_id', $contact_ids) . " ORDER BY id", $contact_ids);
        $workspace['rows'] = array_merge($workspace['rows'], $part['rows']);
        $workspace['truncated'] = $part['truncated'];
    }

    if (pg_schema_has('ws_approval_guests', 'email')) {
        $part = _pg_privacy_export_select("SELECT 'approval' AS record, id, approval_id, name, email, sent_at, expires_at, decision, note, decided_at, decided_ip, decided_agent FROM ws_approval_guests WHERE " . _pg_privacy_export_in('email', $emails) . " ORDER BY id", $emails);
        $workspace['rows'] = array_merge($workspace['rows'], $part['rows']);
        $workspace['truncated'] = $workspace['truncated'] || $part['truncated'];
    }

    $sections['workspace'] = $workspace;

    return $sections;
}

/**
 * The mobile numbers in a person's records, in the form the SMS queue keeps
 * them (pg_sms_number()).
 *
 * @param array[] $rows  contacts, address book, order and shipping rows
 * @return string[]
 */
function pg_privacy_export_phones(array $rows)
{
    $phones = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach (array('mobile_phone', 'home_phone', 'business_phone', 'phone_number', 'billing_phone_number') as $column) {
            $number = isset($row[$column]) ? pg_sms_number($row[$column]) : '';

            if (($number !== '') && !in_array($number, $phones, true)) {
                $phones[] = $number;
            }
        }
    }

    return $phones;
}

/**
 * Find a person and read their pack, unless the operator may not export
 * them.
 *
 * @param array $input     any of email, user_id, contact_id
 * @param array $operator  role, id
 * @param array $meta      site, prepared_by (time is now)
 * @return array  status (empty | refused | not_found | ok), found, document
 */
function pg_privacy_export_build(array $input, array $operator, array $meta)
{
    $found = pg_privacy_export_find($input);

    if (pg_privacy_export_is_empty($found['person'])) {
        return array('status' => 'empty', 'found' => $found, 'document' => null);
    }

    if (!pg_privacy_export_allowed($operator['role'] ?? 3, $operator['id'] ?? 0, $found['users'])) {
        return array('status' => 'refused', 'found' => $found, 'document' => null);
    }

    $meta['time'] = time();
    $document = pg_privacy_export_document($found['person'], pg_privacy_export_collect($found), $meta);

    return array(
        'status'   => array_sum(pg_privacy_export_counts($document)) ? 'ok' : 'not_found',
        'found'    => $found,
        'document' => $document,
    );
}

/**
 * Ids as a sorted list of positive integers without duplicates.
 *
 * @param array $ids
 * @return int[]
 */
function _pg_privacy_export_ids(array $ids)
{
    $clean = array();

    foreach ($ids as $id) {
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $id = (int) $id;

            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
    }

    sort($clean);

    return array_values($clean);
}

/**
 * The values of one column of some rows, as ids.
 *
 * @param array[] $rows
 * @param string  $column
 * @return int[]
 */
function _pg_privacy_export_column(array $rows, $column)
{
    $ids = array();

    foreach ($rows as $row) {
        if (is_array($row) && isset($row[$column])) {
            $ids[] = $row[$column];
        }
    }

    return _pg_privacy_export_ids($ids);
}

/**
 * "column IN (…)" for some ids or texts; '' when there is none. Ids are
 * written as integers, texts escaped and quoted.
 *
 * @param string $column  a trusted column name, optionally with its alias
 * @param array  $values
 * @return string
 */
function _pg_privacy_export_in($column, array $values)
{
    $list = array();

    foreach ($values as $value) {
        $list[] = is_int($value) ? (string) $value : "'" . e((string) $value) . "'";
    }

    if (!$list) {
        return '';
    }

    $column = implode('.', array_map(function ($part) {
        return '`' . str_replace('`', '', $part) . '`';
    }, explode('.', (string) $column)));

    return $column . ' IN (' . implode(', ', array_unique($list)) . ')';
}

/**
 * Conditions joined with OR, the empty ones left out; '' when none remains.
 *
 * @param string[] $conditions
 * @return string
 */
function _pg_privacy_export_any(array $conditions)
{
    $conditions = array_values(array_filter($conditions, 'strlen'));

    return $conditions ? '(' . implode(' OR ', $conditions) . ')' : '';
}

/**
 * The users matching a condition, keyed by id.
 *
 * @param string $where
 * @return array  id => row
 */
function _pg_privacy_export_users($where)
{
    $users = array();

    if ($where === '') {
        return $users;
    }

    foreach (db_items("SELECT * FROM user WHERE " . $where . " ORDER BY user_id LIMIT 100") as $row) {
        $users[(int) $row['user_id']] = $row;
    }

    return $users;
}

/**
 * The rows of one section, at most pg_privacy_export_row_limit() of them.
 * Nothing is read when the person has nothing the query looks for ($needs
 * empty): a condition left out would otherwise match every row.
 *
 * @param string   $sql    a SELECT without LIMIT
 * @param array    $needs  the list the condition is built from
 * @param string[] $mask   columns to mask besides the secrets
 * @param string[] $drop   columns to leave out
 * @return array  rows, truncated
 */
function _pg_privacy_export_select($sql, array $needs, array $mask = array(), array $drop = array())
{
    if (!$needs) {
        return array('rows' => array(), 'truncated' => false);
    }

    $limit = pg_privacy_export_row_limit();
    $rows = array();

    foreach (db_items($sql . " LIMIT " . ($limit + 1)) as $row) {
        $rows[] = pg_privacy_export_clean($row, $mask, $drop);
    }

    $truncated = count($rows) > $limit;

    return array('rows' => $truncated ? array_slice($rows, 0, $limit) : $rows, 'truncated' => $truncated);
}

/**
 * A time as the summary writes it.
 *
 * @param int $time
 * @return string
 */
function _pg_privacy_export_time($time)
{
    return ((int) $time > 0) ? date('Y-m-d H:i', (int) $time) : '';
}

/**
 * The answer of a consent row in words. A pending row (a double opt-in mail
 * not answered yet) is neither given nor refused.
 *
 * @param string $status
 * @return string
 */
function _pg_privacy_export_consent_answer($status)
{
    if ($status === 'granted') {
        return lang('Given');
    }

    return ($status === 'pending') ? lang('Waiting for confirmation') : lang('Refused');
}

/**
 * An amount kept in minor units, with its currency code.
 *
 * @param mixed  $amount
 * @param string $currency
 * @return string
 */
function _pg_privacy_export_money($amount, $currency)
{
    return trim(pg_format_number(round((float) $amount) / 100, 2) . ' ' . $currency);
}

/**
 * One address of a row on one line, from its columns with a prefix
 * (home_, business_, or none for the address book).
 *
 * @param array  $row
 * @param string $prefix
 * @return string
 */
function _pg_privacy_export_address(array $row, $prefix)
{
    $parts = array();

    foreach (array('address_1', 'address_2', 'zip_code', 'city', 'state', 'country') as $column) {
        $value = isset($row[$prefix . $column]) ? trim((string) $row[$prefix . $column]) : '';

        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

/**
 * A two-column row of the summary.
 *
 * @param string $label
 * @param string $value
 * @return string
 */
function _pg_privacy_export_summary_line($label, $value)
{
    return '<tr><th style="width:30%">' . h((string) $label) . '</th><td>' . h((string) $value) . '</td></tr>';
}
