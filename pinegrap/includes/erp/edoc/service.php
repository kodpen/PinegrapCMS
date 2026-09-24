<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the e-document service: what the screens call when an invoice is to
 * go to the tax authority through the active provider, and what they read
 * back. The driver layer (registry.php) speaks to the provider; this file
 * owns the invoice row - edoc_provider, edoc_external_id, edoc_status,
 * gib_number, gib_uuid, edoc_error, edoc_sent_at - and the rules of when a
 * document may go.
 *
 * A document, once carried by a provider, stays with that provider: every
 * later question (status, PDF, XML) goes to erp_invoices.edoc_provider, not
 * to whichever provider is active today.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The words the screens print for erp_invoices.edoc_status.
 *
 * @return array  status => [label, bootstrap tone]
 */
function erp_edoc_status_labels()
{
    return array(
        'none' => array(lang('Not sent'), 'secondary'),
        'queued' => array(lang('Queued'), 'info'),
        'sending' => array(lang('Sending'), 'info'),
        'created' => array(lang('Draft at the provider'), 'warning'),
        'sent' => array(lang('Sent, awaiting GİB'), 'primary'),
        'accepted' => array(lang('Accepted by GİB'), 'success'),
        'rejected' => array(lang('Rejected'), 'danger'),
        'error' => array(lang('Error'), 'danger'),
        'cancelled' => array(lang('Cancelled at the provider'), 'secondary'),
    );
}

/**
 * The e-document views the invoice register filters on and the dashboard
 * counts, one place for both so a count on the dashboard is the length of
 * the list it opens.
 *
 * Only documents a provider would carry are in any of them: issued sales
 * documents that are not cancelled. 'unsent' is therefore not "every
 * invoice without a status" - a purchase bill never had one to begin with.
 *
 * @return array  key => ['label', 'statuses' => [...], 'tone', 'icon']
 */
function erp_edoc_filters()
{
    return array(
        'failed' => array('label' => lang('Failed or rejected'), 'statuses' => array('error', 'rejected'), 'tone' => 'danger', 'icon' => 'bi-x-octagon'),
        'created' => array('label' => lang('Draft at the provider'), 'statuses' => array('created'), 'tone' => 'warning', 'icon' => 'bi-hourglass'),
        'pending' => array('label' => lang('Awaiting GİB'), 'statuses' => array('queued', 'sending', 'sent'), 'tone' => 'primary', 'icon' => 'bi-send'),
        'unsent' => array('label' => lang('Not sent'), 'statuses' => array('none'), 'tone' => 'secondary', 'icon' => 'bi-dash-circle'),
        'accepted' => array('label' => lang('Accepted by GİB'), 'statuses' => array('accepted'), 'tone' => 'success', 'icon' => 'bi-check2-circle'),
    );
}

/**
 * The WHERE condition of one e-document view, or '' for a key it does not know.
 *
 * @param string $key    A key of erp_edoc_filters()
 * @param string $alias  The erp_invoices alias in the query
 * @return string  Without a leading AND
 */
function erp_edoc_filter_sql($key, $alias = 'i')
{
    $filters = erp_edoc_filters();

    if (!erp_edoc_installed() || !isset($filters[$key])) {
        return '';
    }

    $statuses = array();
    foreach ($filters[$key]['statuses'] as $status) {
        $statuses[] = "'" . escape($status) . "'";
    }

    return $alias . ".direction = 'sales' AND " . $alias . ".status NOT IN ('draft', 'cancelled') AND " . $alias . ".edoc_status IN (" . implode(', ', $statuses) . ")";
}

/**
 * How many documents stand in each e-document view, for the dashboard.
 *
 * @return array  key => int, every key of erp_edoc_filters() present
 */
function erp_edoc_filter_counts()
{
    $counts = array_fill_keys(array_keys(erp_edoc_filters()), 0);

    if (!erp_edoc_installed()) {
        return $counts;
    }

    $rows = (array) db_items("SELECT edoc_status, COUNT(*) AS documents FROM erp_invoices
        WHERE direction = 'sales' AND status NOT IN ('draft', 'cancelled')
        GROUP BY edoc_status");

    foreach (erp_edoc_filters() as $key => $filter) {
        foreach ($rows as $row) {
            if (in_array((string) $row['edoc_status'], $filter['statuses'], true)) {
                $counts[$key] += (int) $row['documents'];
            }
        }
    }

    return $counts;
}

/**
 * What the active provider would refuse this invoice's buyer for.
 *
 * Empty when there is no provider, when the driver has no opinion, or when
 * the document is not one a provider would carry anyway - a purchase
 * invoice is the supplier's to issue, and a store with no e-document
 * provider is not held to anybody's field list.
 *
 * @param array $invoice  An erp_invoices row; a draft works, and then the
 *                        live card is what gets checked
 * @return array ['provider' => string, 'label' => string,
 *                'fields' => field name => label]
 */
function erp_edoc_invoice_party_missing($invoice)
{
    $none = array('provider' => '', 'label' => '', 'fields' => array());

    if (!erp_edoc_installed()) {
        return $none;
    }

    $provider = erp_edoc_active();

    if (($provider === '') || !erp_edoc_supports('party_missing', $provider)) {
        return $none;
    }

    if (((string) ($invoice['direction'] ?? 'sales') !== 'sales')
        || ((string) ($invoice['doc_type'] ?? 'invoice') !== 'invoice')) {
        return $none;
    }

    $fields = erp_edoc_call('party_missing', array(erp_edoc_invoice_party($invoice), $invoice), $provider);

    // The one door wraps a driver's answer in its own envelope; the field
    // names are what is left once the two keys it adds are taken off.
    unset($fields['success'], $fields['error']);
    $fields = array_filter(array_map('strval', (array) $fields), 'strlen');

    if (empty($fields)) {
        return $none;
    }

    return array(
        'provider' => $provider,
        'label' => (string) erp_edoc_info($provider)['label'],
        'fields' => $fields,
    );
}

/**
 * What the active provider is missing on the document itself - the carrier
 * of an internet sale, today. Kept apart from the buyer's fields because
 * they are repaired in a different place: these belong to this one
 * document, not to the account card.
 *
 * @param array $invoice
 * @return array ['provider' => string, 'label' => string,
 *                'fields' => field name => label]
 */
function erp_edoc_invoice_document_missing($invoice)
{
    $none = array('provider' => '', 'label' => '', 'fields' => array());

    if (!erp_edoc_installed()) {
        return $none;
    }

    $provider = erp_edoc_active();

    if (($provider === '') || !erp_edoc_supports('document_missing', $provider)) {
        return $none;
    }

    if (((string) ($invoice['direction'] ?? 'sales') !== 'sales')
        || ((string) ($invoice['doc_type'] ?? 'invoice') !== 'invoice')) {
        return $none;
    }

    $fields = erp_edoc_call('document_missing', array($invoice), $provider);
    unset($fields['success'], $fields['error']);
    $fields = array_filter(array_map('strval', (array) $fields), 'strlen');

    if (empty($fields)) {
        return $none;
    }

    return array(
        'provider' => $provider,
        'label' => (string) erp_edoc_info($provider)['label'],
        'fields' => $fields,
    );
}

/**
 * Sends a delivery note as an e-Delivery Note through the active provider,
 * where the driver can. The provider's id is written on the note; a note
 * that has one is never sent again.
 *
 * @param int $waybill_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_waybill_send($waybill_id, $user_id = 0)
{
    $waybill_id = (int) $waybill_id;
    $waybill = db_item("SELECT * FROM erp_waybills WHERE id = '" . $waybill_id . "' LIMIT 1");

    if (!is_array($waybill) || ((string) $waybill['status'] === 'cancelled')) {
        return array('success' => false, 'message' => '', 'error' => lang('The delivery note could not be found.'));
    }

    $provider = erp_edoc_installed() ? erp_edoc_active() : '';

    if (($provider === '') || !erp_edoc_supports('send_waybill', $provider)) {
        return array('success' => false, 'message' => '', 'error' => lang('The e-document provider does not send e-Delivery Notes.'));
    }

    if ((string) ($waybill['edoc_external_id'] ?? '') !== '') {
        return array('success' => false, 'message' => '', 'error' => lang('The delivery note has already been sent.'));
    }

    $lines = (array) db_items("SELECT * FROM erp_waybill_items WHERE waybill_id = '" . $waybill_id . "' ORDER BY line_no ASC, id ASC");
    $result = erp_edoc_call('send_waybill', array($waybill, $lines, array('user_id' => (int) $user_id)), $provider);

    if (empty($result['success'])) {
        return array('success' => false, 'message' => '', 'error' => (string) ($result['error'] ?? lang('The provider refused the delivery note.')));
    }

    erp_query("UPDATE erp_waybills SET edoc_provider = '" . escape($provider) . "',
            edoc_external_id = '" . escape((string) ($result['external_id'] ?? '')) . "',
            updated_at = '" . time() . "'
        WHERE id = '" . $waybill_id . "'");

    return array('success' => true, 'error' => '', 'message' => lang(array('string' => 'Delivery note {var:1} sent as an e-Delivery Note.', 'vars' => (string) $waybill['full_number'])));
}

/**
 * Which e-document the provider will make of an invoice.
 *
 * GİB decides it by the buyer: a registered e-Fatura taxpayer gets an
 * e-Fatura, everybody else an e-Arşiv invoice. Once the provider has said
 * which it made (edoc_kind), that is the answer; before that the account
 * card's taxpayer query is: no number, a TCKN, or a number GİB said is not
 * registered is e-Arşiv, a registered one e-Fatura, a VKN nobody asked
 * about is not known yet.
 *
 * @param array      $invoice  An erp_invoices row
 * @param array|null $account  Its erp_accounts row, when the caller has it
 * @return string  'einvoice' | 'earchive' | '' (not known before it is sent)
 */
function erp_edoc_invoice_expected_kind($invoice, $account = null)
{
    $kind = (string) ($invoice['edoc_kind'] ?? '');

    if (($kind === 'einvoice') || ($kind === 'earchive')) {
        return $kind;
    }

    if (($account === null) && ((int) ($invoice['account_id'] ?? 0) > 0) && function_exists('erp_account')) {
        $account = erp_account((int) $invoice['account_id']);
    }

    if (is_array($account) && ((int) ($account['einvoice_user'] ?? 0) === 1)) {
        return 'einvoice';
    }

    $tax_number = preg_replace('/\D/', '', (string) ((($invoice['account_tax_number'] ?? '') !== '') ? $invoice['account_tax_number'] : ($account['tax_number'] ?? '')));
    $asked = is_array($account) && ((int) ($account['einvoice_checked_at'] ?? 0) > 0);

    return (($tax_number === '') || (strlen($tax_number) === 11) || $asked) ? 'earchive' : '';
}

/**
 * The latest date among the documents this provider already has. GİB takes
 * e-documents of one type only in date order, so an invoice dated before
 * this is refused. Given a kind, only documents of that kind count - an
 * e-Fatura dated today does not hold back an e-Arşiv invoice - and a sent
 * document whose kind is not known yet counts, so the warning errs on the
 * side of being shown.
 *
 * @param string $provider
 * @param int    $exclude_id
 * @param string $kind  'einvoice' | 'earchive' | '' for both
 * @return string  Y-m-d, or ''
 */
function erp_edoc_latest_sent_date($provider, $exclude_id = 0, $kind = '')
{
    if (((string) $provider === '') || !erp_edoc_installed()) {
        return '';
    }

    $where = "direction = 'sales' AND edoc_provider = '" . escape((string) $provider) . "'
          AND edoc_external_id <> '' AND edoc_status IN ('created', 'sent', 'accepted')
          AND id <> '" . (int) $exclude_id . "'";

    if ($kind === '') {
        $latest = (string) db_value("SELECT MAX(issue_date) FROM erp_invoices WHERE " . $where);

        return (($latest !== '') && ($latest !== '0000-00-00')) ? $latest : '';
    }

    // Newest first; the first of the same kind (or of a kind not known yet)
    // is the answer, so this rarely reads more than a row or two.
    foreach ((array) db_items("SELECT id, issue_date, edoc_kind, account_id, account_tax_number FROM erp_invoices
        WHERE " . $where . " ORDER BY issue_date DESC, id DESC LIMIT 50") as $row) {
        $row_kind = erp_edoc_invoice_expected_kind($row);

        if (($row_kind === $kind) || ($row_kind === '')) {
            return ((string) $row['issue_date'] !== '0000-00-00') ? (string) $row['issue_date'] : '';
        }
    }

    return '';
}

/**
 * Whether the provider's last answer refused the invoice for GİB's date
 * order, and the latest date it named. The words are the provider's, so the
 * driver reads them (erp_edoc_<code>_date_refusal()); a driver that does not
 * gets the general reading, which knows the refusal but not the date.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['refused' => bool, 'latest' => 'Y-m-d' or '', 'latest_time' => 'H:i' or '']
 */
function erp_edoc_invoice_date_refusal($invoice)
{
    $none = array('refused' => false, 'latest' => '', 'latest_time' => '');
    $message = trim((string) ($invoice['edoc_error'] ?? ''));

    if ($message === '') {
        return $none;
    }

    $provider = ((string) ($invoice['edoc_provider'] ?? '') !== '') ? (string) $invoice['edoc_provider'] : erp_edoc_active();

    if (($provider !== '') && erp_edoc_supports('date_refusal', $provider)) {
        $answer = erp_edoc_call('date_refusal', array($message), $provider);

        return array(
            'refused' => !empty($answer['refused']),
            'latest' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($answer['latest'] ?? '')) ? (string) $answer['latest'] : '',
            'latest_time' => preg_match('/^\d{2}:\d{2}$/', (string) ($answer['latest_time'] ?? '')) ? (string) $answer['latest_time'] : '',
        );
    }

    return array('refused' => (bool) preg_match('/after the date|tarihten sonra/u', $message), 'latest' => '', 'latest_time' => '');
}

/**
 * Whether an unsent invoice is dated before what the provider already has.
 *
 * The provider's own refusal comes first: it names the latest document of
 * the same type, which the store may not know of at all (one issued on the
 * provider's screen, say). Without one, the store's own record of what it
 * sent, for the kind of document this one will become.
 *
 * @param array $invoice  An erp_invoices row
 * @return string  The provider's latest date when this one is older, else ''
 */
function erp_edoc_invoice_date_behind($invoice)
{
    // Only what the store sends is held to the provider's date order; a
    // purchase invoice carries the supplier's date.
    if (((string) ($invoice['direction'] ?? 'sales') !== 'sales')
        || !erp_edoc_invoice_party_editable($invoice) || in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return '';
    }

    $refusal = erp_edoc_invoice_date_refusal($invoice);

    if ($refusal['refused'] && ($refusal['latest'] !== '')) {
        return ((string) $invoice['issue_date'] < $refusal['latest']) ? $refusal['latest'] : '';
    }

    $latest = erp_edoc_latest_sent_date(erp_edoc_active(), (int) $invoice['id'], erp_edoc_invoice_expected_kind($invoice));

    return (($latest !== '') && ((string) $invoice['issue_date'] < $latest)) ? $latest : '';
}

/**
 * May an issued invoice that has not gone to the provider yet be moved to
 * today? It is not an official document until the provider has it, and the
 * date is the one thing GİB's ordering rule can refuse it for.
 *
 * Not offered where moving the date would change more than the date: a
 * number that carries another year, an invoice in a foreign currency (its
 * rate belongs to its date), or one a return has been written against.
 *
 * @param array $invoice
 * @return array ['ok' => bool, 'reason' => string]
 */
function erp_edoc_invoice_redate_allowed($invoice)
{
    $today = date('Y-m-d');

    if ((string) ($invoice['direction'] ?? 'sales') !== 'sales') {
        return array('ok' => false, 'reason' => lang('A purchase invoice keeps the date the supplier gave it.'));
    }

    if (!erp_edoc_invoice_party_editable($invoice) || in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return array('ok' => false, 'reason' => lang('Only an issued invoice that has not gone to the provider can be moved to another date.'));
    }

    if ((string) $invoice['issue_date'] >= $today) {
        return array('ok' => false, 'reason' => '');
    }

    // Moving it out of a closed period would change what was filed.
    if (function_exists('erp_lock_refusal') && (($refusal = erp_lock_refusal((string) $invoice['issue_date'])) !== '')) {
        return array('ok' => false, 'reason' => $refusal);
    }

    // A continuous number is filed under year 0 (erp_next_number()): it
    // belongs to no year, so it moves with the date.
    if (((int) $invoice['issue_year'] !== 0) && ((int) $invoice['issue_year'] !== (int) date('Y'))) {
        return array('ok' => false, 'reason' => lang('The invoice number belongs to another year, so the invoice cannot be moved into this one. Cancel it and issue it again.'));
    }

    if (strtoupper(trim((string) $invoice['currency'])) !== erp_base_currency()) {
        return array('ok' => false, 'reason' => lang('A foreign-currency invoice keeps the rate of its date; cancel it and issue it again instead.'));
    }

    if ((int) db_value("SELECT COUNT(*) FROM erp_invoices WHERE parent_invoice_id = '" . (int) $invoice['id'] . "' AND status <> 'cancelled'") > 0) {
        return array('ok' => false, 'reason' => lang('A return has been written against this invoice, so its date is not moved.'));
    }

    return array('ok' => true, 'reason' => '');
}

/**
 * Moves an unsent invoice to today: the document, its due date by the same
 * number of days, and its movement on the account, in one transaction. The
 * kept PDF carried the old date, so it is replaced.
 *
 * @param int $invoice_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_redate($invoice_id, $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    $gate = erp_edoc_invoice_redate_allowed($invoice);

    if (!$gate['ok']) {
        return array('success' => false, 'message' => '', 'error' => ($gate['reason'] !== '') ? $gate['reason'] : lang('The invoice is already dated today.'));
    }

    $today = date('Y-m-d');
    $old = (string) $invoice['issue_date'];
    $due = (string) $invoice['due_date'];
    $term = (($due !== '') && ($due !== '0000-00-00')) ? (int) round((strtotime($due) - strtotime($old)) / 86400) : 0;
    $new_due = date('Y-m-d', strtotime($today . ' +' . max(0, $term) . ' days'));

    if (!erp_tx_begin()) {
        return array('success' => false, 'message' => '', 'error' => lang('Could not start a database transaction.'));
    }

    $ok = erp_query("UPDATE erp_invoices SET issue_date = '" . $today . "', due_date = '" . $new_due . "',
            exchange_rate_date = '" . $today . "', updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "' AND issue_date = '" . escape($old) . "'");

    $ok = $ok && erp_query("UPDATE erp_account_transactions SET doc_date = '" . $today . "'
        WHERE doc_type = 'invoice' AND doc_id = '" . $invoice_id . "' AND kind = 'invoice'");

    if (!$ok) {
        erp_tx_rollback();
        return array('success' => false, 'message' => '', 'error' => lang('The date could not be changed.'));
    }

    erp_tx_commit();

    // The PDF kept at issue shows the old date; the next one is the document.
    if (function_exists('erp_archive_file') && function_exists('erp_archive_defer')) {
        $kept = erp_archive_file('invoice', $invoice_id);
        if ($kept !== null) {
            db("DELETE FROM files WHERE id = '" . (int) $kept['id'] . "' AND erp_doc_type = 'invoice'");
            @unlink($kept['path']);
        }
        erp_archive_defer('invoice', $invoice_id);
    }

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp invoice ({var:1}) was moved from {var:2} to {var:3} before it went to the provider', 'vars' => array((string) $invoice['full_number'], $old, $today))), $_SESSION['sessionusername'] ?? '');
    }

    return array('success' => true, 'error' => '', 'message' => lang(array(
        'string' => 'The invoice is now dated {var:1} (due {var:2}). It can be sent.',
        'vars' => array(prepare_form_data_for_output($today, 'date', false), prepare_form_data_for_output($new_due, 'date', false)),
    )));
}

/**
 * The short name of each field the repair form can ask for, and which part
 * of the document it belongs to. The provider's refusal says why a field is
 * wanted; this is what the box is called, so a label stays a label.
 *
 * @return array  field => ['group' => 'buyer'|'carrier', 'label' => string]
 */
function erp_edoc_fix_fields()
{
    return array(
        'title' => array('group' => 'buyer', 'label' => lang('Name or title')),
        'tax_number' => array('group' => 'buyer', 'label' => lang('VKN / TCKN')),
        'address' => array('group' => 'buyer', 'label' => lang('Address')),
        'postcode' => array('group' => 'buyer', 'label' => lang('Zip Code')),
        'city' => array('group' => 'buyer', 'label' => lang('City')),
        'district' => array('group' => 'buyer', 'label' => lang('District')),
        'carrier_title' => array('group' => 'carrier', 'label' => lang('Carrier')),
        'carrier_vkn' => array('group' => 'carrier', 'label' => lang('Carrier VKN / TCKN')),
    );
}

/**
 * The short names of a list of missing fields, for a one-line summary.
 *
 * @param array $fields  field => reason, as the *_missing() functions return
 * @return string
 */
function erp_edoc_fix_summary($fields)
{
    $names = erp_edoc_fix_fields();
    $out = array();

    foreach (array_keys((array) $fields) as $field) {
        $out[] = isset($names[$field]) ? $names[$field]['label'] : (string) $fields[$field];
    }

    return implode(', ', array_unique($out));
}

/**
 * Carriers the store already knows, with a number that would pass: the
 * carrier set on a shipping method first, then the ones written on earlier
 * invoices and delivery notes. Offered on the repair form so the operator
 * picks "Aras Kargo - 1234567890" once instead of looking the VKN up again.
 *
 * @param int $limit
 * @return array  [['title' => string, 'vkn' => string, 'source' => string], ...]
 */
function erp_edoc_known_carriers($limit = 20)
{
    $candidates = array();

    if (function_exists('waf_table_has_column') && waf_table_has_column('shipping_methods', 'carrier_vkn')) {
        foreach ((array) db_items("SELECT carrier_title AS title, carrier_vkn AS vkn FROM shipping_methods WHERE carrier_vkn <> '' AND carrier_title <> ''") as $row) {
            $candidates[] = $row + array('source' => 'shipping');
        }
    }

    foreach ((array) db_items("SELECT carrier_title AS title, carrier_vkn AS vkn, MAX(id) AS latest FROM erp_invoices
        WHERE carrier_vkn <> '' AND carrier_title <> '' GROUP BY carrier_title, carrier_vkn ORDER BY latest DESC LIMIT 50") as $row) {
        $candidates[] = array('title' => $row['title'], 'vkn' => $row['vkn'], 'source' => 'document');
    }

    foreach ((array) db_items("SELECT carrier_title AS title, carrier_vkn AS vkn, MAX(id) AS latest FROM erp_waybills
        WHERE carrier_vkn <> '' AND carrier_title <> '' GROUP BY carrier_title, carrier_vkn ORDER BY latest DESC LIMIT 50") as $row) {
        $candidates[] = array('title' => $row['title'], 'vkn' => $row['vkn'], 'source' => 'document');
    }

    $carriers = array();

    foreach ($candidates as $candidate) {
        $title = trim((string) $candidate['title']);
        $vkn = preg_replace('/\D/', '', (string) $candidate['vkn']);
        $key = mb_strtolower($title) . '|' . $vkn;

        // Only what the provider would take: a valid number, and a person's
        // name in two parts when the number is a TCKN.
        if (isset($carriers[$key]) || !erp_edoc_tax_number_valid($vkn)
            || ((strlen($vkn) === 11) && (erp_edoc_person_name($title) === null))) {
            continue;
        }

        $carriers[$key] = array('title' => $title, 'vkn' => $vkn, 'source' => (string) $candidate['source']);

        if (count($carriers) >= (int) $limit) {
            break;
        }
    }

    return array_values($carriers);
}

/**
 * What the active provider would refuse this account card for, asked of
 * the card itself rather than of a document.
 *
 * The same rule the invoice is held to, so the list screen and the send
 * button can never disagree about what "ready" means.
 *
 * @param array $account  An erp_accounts row
 * @return array ['provider' => string, 'label' => string,
 *                'fields' => field name => label]
 */
function erp_edoc_account_missing($account)
{
    $none = array('provider' => '', 'label' => '', 'fields' => array());

    if (!erp_edoc_installed()) {
        return $none;
    }

    $provider = erp_edoc_active();

    if (($provider === '') || !erp_edoc_supports('party_missing', $provider)) {
        return $none;
    }

    // A supplier's card is not held to this: the invoice that carries their
    // details is theirs to issue, not the store's.
    if (!in_array((string) ($account['kind'] ?? 'customer'), array('customer', 'both'), true)) {
        return $none;
    }

    $party = array(
        'title' => (string) ($account['title'] ?? ''),
        'is_person' => ((int) ($account['is_person'] ?? 1) === 1),
        'tax_number' => (string) ($account['tax_number'] ?? ''),
        'address' => (string) ($account['address'] ?? ''),
        'district' => (string) ($account['district'] ?? ''),
        'city' => (string) ($account['city'] ?? ''),
        'postcode' => (string) ($account['postcode'] ?? ''),
    );

    $fields = erp_edoc_call('party_missing', array($party, array('account_id' => (int) ($account['id'] ?? 0))), $provider);
    unset($fields['success'], $fields['error']);
    $fields = array_filter(array_map('strval', (array) $fields), 'strlen');

    if (empty($fields)) {
        return $none;
    }

    return array(
        'provider' => $provider,
        'label' => (string) erp_edoc_info($provider)['label'],
        'fields' => $fields,
    );
}

/**
 * Complete the buyer on an invoice whose document has not gone anywhere.
 *
 * Writes the same values twice on purpose: to the account card, so the next
 * invoice is right, and to this invoice's own copy, so this one is. They go
 * together in one transaction - a card and a copy that disagree are worse
 * than either being wrong.
 *
 * Only while nothing has left the building: a document the provider has
 * taken is evidence, and evidence is not edited. Before that the copy is
 * the store's own record of a buyer it already knows, and completing it is
 * bookkeeping, not rewriting. Every correction is logged with what changed.
 *
 * @param int   $invoice_id
 * @param array $values   field name => value, from the card's own form
 * @param int   $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_party_fix($invoice_id, $values, $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    if (!erp_edoc_invoice_party_editable($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('This document has already gone to the provider; its copy of the buyer is not edited any more.'));
    }

    $account_id = (int) $invoice['account_id'];

    if ($account_id < 1) {
        return array('success' => false, 'message' => '', 'error' => lang('Choose an account.'));
    }

    // What may be completed here, with the column it lands in and how long
    // it is allowed to be. Nothing outside this list is touched.
    $allowed = array(
        'title' => array('title', 'account_title', 255),
        'tax_number' => array('tax_number', 'account_tax_number', 32),
        'address' => array('address', 'account_address', 255),
        'postcode' => array('postcode', 'account_postcode', 20),
        'city' => array('city', 'account_city', 100),
        'district' => array('district', 'account_district', 100),
    );

    // The document's own fields: the carrier of an internet sale belongs to
    // this shipment, not to the buyer, so it is written here and nowhere
    // else. A different carrier next month does not make this one wrong.
    $document_only = array(
        'carrier_title' => array('carrier_title', 255),
        'carrier_vkn' => array('carrier_vkn', 16),
    );

    $has_copy = (trim((string) ($invoice['account_title'] ?? '')) !== '');
    $snapshot_ready = waf_table_has_column('erp_invoices', 'account_district');
    $account_set = array();
    $invoice_set = array();
    $changed = array();

    foreach ($allowed as $field => $spec) {
        if (!array_key_exists($field, (array) $values)) {
            continue;
        }

        $value = trim((string) $values[$field]);

        if ($field === 'tax_number') {
            $value = preg_replace('/\D/', '', $value);

            if (($value !== '') && (strlen($value) !== 10) && (strlen($value) !== 11)) {
                return array('success' => false, 'message' => '', 'error' => lang('A VKN is ten digits and a TCKN is eleven.'));
            }
        }

        if ($value === '') {
            continue;
        }

        $value = mb_substr($value, 0, (int) $spec[2]);
        $account_set[] = '`' . $spec[0] . "` = '" . escape($value) . "'";
        $changed[] = $field;

        // The copy is only written where there is one: a draft has none yet,
        // and it will take a fresh one when it is issued.
        if ($has_copy && (($spec[1] !== 'account_district' && $spec[1] !== 'account_postcode') || $snapshot_ready)) {
            $invoice_set[] = '`' . $spec[1] . "` = '" . escape($value) . "'";
        }
    }

    foreach ($document_only as $field => $spec) {
        if (!array_key_exists($field, (array) $values)) {
            continue;
        }

        $value = trim((string) $values[$field]);

        if ($field === 'carrier_vkn') {
            $value = preg_replace('/\D/', '', $value);

            if (($value !== '') && (strlen($value) !== 10) && (strlen($value) !== 11)) {
                return array('success' => false, 'message' => '', 'error' => lang('A VKN is ten digits and a TCKN is eleven.'));
            }
        }

        if ($value === '') {
            continue;
        }

        $invoice_set[] = '`' . $spec[0] . "` = '" . escape(mb_substr($value, 0, (int) $spec[1])) . "'";
        $changed[] = $field;
    }

    // The carrier is checked as the pair it will be sent as: a TCKN makes it
    // a person, and a person needs a first name and a surname. Said here in
    // the operator's words rather than found out from the provider.
    if (array_key_exists('carrier_title', (array) $values) || array_key_exists('carrier_vkn', (array) $values)) {
        $carrier_title = trim((string) (($values['carrier_title'] ?? '') !== '' ? $values['carrier_title'] : ($invoice['carrier_title'] ?? '')));
        $carrier_vkn = preg_replace('/\D/', '', (string) (($values['carrier_vkn'] ?? '') !== '' ? $values['carrier_vkn'] : ($invoice['carrier_vkn'] ?? '')));

        if (($carrier_vkn !== '') && function_exists('erp_edoc_tax_number_valid') && !erp_edoc_tax_number_valid($carrier_vkn)) {
            return array('success' => false, 'message' => '', 'error' => lang('The carrier\'s VKN / TCKN does not pass the check digits; it is probably mistyped.'));
        }

        if ((strlen($carrier_vkn) === 11) && function_exists('erp_edoc_person_name') && (erp_edoc_person_name($carrier_title) === null)) {
            return array('success' => false, 'message' => '', 'error' => lang('An 11-digit number is a TCKN, so the carrier is a person: write their first name and surname. For a cargo company, enter its 10-digit VKN instead.'));
        }
    }

    if (empty($account_set) && empty($invoice_set)) {
        return array('success' => false, 'message' => '', 'error' => lang('Nothing was filled in.'));
    }

    if (!erp_tx_begin()) {
        return array('success' => false, 'message' => '', 'error' => lang('Could not start a database transaction.'));
    }

    $ok = true;

    if (!empty($account_set)) {
        $ok = erp_query("UPDATE erp_accounts SET " . implode(', ', $account_set) . ", updated_at = '" . time() . "'
            WHERE id = '" . $account_id . "'");
    }

    if (($ok !== false) && !empty($invoice_set)) {
        $ok = erp_query("UPDATE erp_invoices SET " . implode(', ', $invoice_set) . ", updated_at = '" . time() . "'
            WHERE id = '" . $invoice_id . "'");
    }

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();

        return array('success' => false, 'message' => '', 'error' => ($error !== '') ? $error : lang('The account could not be saved.'));
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();

        return array('success' => false, 'message' => '', 'error' => $error);
    }

    if (function_exists('log_activity')) {
        log_activity(lang(array(
            'string' => 'erp invoice ({var:1}): the buyer was completed on the account card and on the document copy ({var:2})',
            'vars' => array((string) $invoice['full_number'], implode(', ', $changed)),
        )), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return array(
        'success' => true,
        // The fields by the names the form showed them under, not by column.
        'message' => lang(array('string' => 'Saved on the account card and on this document: {var:1}.', 'vars' => erp_edoc_fix_summary(array_fill_keys($changed, '')))),
        'error' => '',
    );
}

/**
 * May this invoice's copy of the buyer still be corrected? Only while no
 * provider has taken the document.
 *
 * @param array $invoice
 * @return bool
 */
function erp_edoc_invoice_party_editable($invoice)
{
    return in_array((string) ($invoice['edoc_status'] ?? 'none'), array('none', 'error'), true)
        && (trim((string) ($invoice['edoc_external_id'] ?? '')) === '');
}

/**
 * May this invoice be sent now, and if not, why not - in the operator's words.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['ok' => bool, 'reason' => string, 'provider' => string]
 */
function erp_edoc_invoice_can_send($invoice)
{
    $provider = erp_edoc_active();

    if ($provider === '') {
        return array('ok' => false, 'provider' => '', 'reason' => lang('No e-document provider is selected on the E-Invoice card of the commerce settings.'));
    }

    if (!erp_edoc_supports('send_invoice', $provider)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang(array('string' => 'The {var:1} driver does not send invoices yet.', 'vars' => erp_edoc_info($provider)['label'])));
    }

    if ((string) $invoice['direction'] !== 'sales') {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('Only sales documents are sent; a purchase invoice is the supplier\'s to issue.'));
    }

    // A return goes only where the driver says it can: asked here, before
    // the provider is, so the screen explains instead of relaying a refusal.
    if (((string) $invoice['doc_type'] === 'return') && !erp_edoc_has_capability('return', $provider)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang(array('string' => '{var:1} does not take return invoices through its API. The return is kept here; its e-document, if one is needed, is made on the provider\'s own screen.', 'vars' => erp_edoc_info($provider)['label'])));
    }

    if (in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('A draft or a cancelled document is not sent.'));
    }

    // A second tax of the store's naming (erp_tax2_name()) has no place on a
    // GİB document, and leaving it off would send a total that is not the
    // invoice's.
    if ((int) ($invoice['tax2_total'] ?? 0) !== 0) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('The invoice carries a second tax, which an e-document cannot show; it stays a PDF document.'));
    }

    // A document that exists at the provider is never created again: the
    // repeat would leave a second draft behind that nobody is watching.
    // Handing that one over is a separate button.
    if ((string) $invoice['edoc_status'] === 'created') {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('The document is already at the provider as a draft; hand that one to GİB instead of creating a second.'));
    }

    if (in_array((string) $invoice['edoc_status'], array('queued', 'sending', 'sent', 'accepted'), true)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('The document has already gone; ask after its status instead of sending it twice.'));
    }

    return array('ok' => true, 'provider' => $provider, 'reason' => '');
}

/**
 * Whether an invoice that a provider has seen can be taken back there, and
 * how - the driver's answer (erp_edoc_<code>_cancellable(), see the
 * registry), or a general one for a driver that does not say.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['ok' => bool, 'manual' => bool, 'reason' => string, 'url' => string, 'provider' => string]
 */
function erp_edoc_invoice_cancellable($invoice)
{
    $provider = trim((string) ($invoice['edoc_provider'] ?? ''));
    $provider = ($provider !== '') ? $provider : erp_edoc_active();
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $status = (string) ($invoice['edoc_status'] ?? 'none');

    // Nothing was ever created there.
    if (($external_id === '') && in_array($status, array('none', 'error', 'rejected'), true)) {
        return array('ok' => true, 'manual' => false, 'reason' => '', 'url' => '', 'provider' => $provider);
    }

    if (($provider !== '') && erp_edoc_supports('cancellable', $provider)) {
        return erp_edoc_call('cancellable', array($invoice), $provider) + array('ok' => false, 'manual' => false, 'reason' => '', 'url' => '', 'provider' => $provider);
    }

    $label = ($provider !== '') ? (string) erp_edoc_info($provider)['label'] : '';

    if ($status === 'created') {
        return array('ok' => true, 'manual' => true, 'url' => '', 'provider' => $provider,
            'reason' => lang(array('string' => 'A draft of it is waiting at {var:1}: delete it there, then confirm here.', 'vars' => $label)));
    }

    return array('ok' => false, 'manual' => false, 'url' => '', 'provider' => $provider,
        'reason' => lang(array('string' => '{var:1} has the document and its driver cannot cancel it, so it is taken back with a return invoice.', 'vars' => $label)));
}

/**
 * Takes an invoice back at its provider before it is cancelled here: the
 * driver deletes a draft, cancels through the API, or checks that the
 * operator has done it on the provider's screen. Nothing changes on the row
 * unless the provider agrees.
 *
 * @param int    $invoice_id
 * @param string $reason
 * @param int    $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_provider_cancel($invoice_id, $reason = '', $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    $external_id = trim((string) $invoice['edoc_external_id']);

    if (($external_id === '') && in_array((string) $invoice['edoc_status'], array('none', 'error', 'rejected'), true)) {
        return array('success' => true, 'message' => '', 'error' => '');
    }

    $check = erp_edoc_invoice_cancellable($invoice);
    $provider = (string) $check['provider'];

    if (empty($check['ok'])) {
        return array('success' => false, 'message' => '', 'error' => (string) $check['reason']);
    }

    if (($provider === '') || !erp_edoc_supports('cancel_invoice', $provider)) {
        // A driver that cannot ask: the operator's confirmation is all there is.
        return array('success' => true, 'message' => '', 'error' => '');
    }

    $result = erp_edoc_call('cancel_invoice', array($invoice, (string) $reason), $provider);

    if (empty($result['success'])) {
        return array('success' => false, 'message' => '', 'error' => (string) $result['error']);
    }

    // A deleted draft leaves nothing at the provider to point at; a
    // cancelled document keeps its numbers, now marked as cancelled there.
    $deleted = ((string) ($result['outcome'] ?? '') === 'deleted');
    erp_query("UPDATE erp_invoices SET
            edoc_status = '" . ($deleted ? 'none' : 'cancelled') . "',
            edoc_error = '" . escape(mb_substr((string) ($result['message'] ?? ''), 0, 2000)) . "',
            " . ($deleted ? "edoc_external_id = ''," : '') . "
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'");

    log_activity(lang(array('string' => 'erp invoice ({var:1}) was taken back at {var:2}', 'vars' => array((string) $invoice['full_number'], (string) erp_edoc_info($provider)['label']))));

    if (function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    return array('success' => true, 'message' => (string) ($result['message'] ?? ''), 'error' => '');
}

/**
 * Sends an invoice through the active provider and writes the outcome on
 * the row. The operator sees the provider's own message on failure.
 *
 * @param int $invoice_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_send($invoice_id, $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    $gate = erp_edoc_invoice_can_send($invoice);

    if (!$gate['ok']) {
        return array('success' => false, 'message' => '', 'error' => $gate['reason']);
    }

    $provider = $gate['provider'];
    $lines = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

    // Marked before the call: a second click while the first is in flight
    // finds the row "sending" and stops at the gate.
    erp_query("UPDATE erp_invoices SET edoc_status = 'sending', edoc_error = '', updated_at = '" . time() . "' WHERE id = '" . $invoice_id . "'");

    // The card the account is linked to at the provider, when the link is
    // beyond doubt: the provider then puts the invoice on it instead of
    // looking one up by name and number.
    $party = erp_edoc_invoice_party($invoice);
    $party['provider_code'] = function_exists('erp_edoc_account_link_code')
        ? erp_edoc_account_link_code($provider, (int) $invoice['account_id'], (string) ($party['tax_number'] ?? ''), (string) ($party['country_code'] ?? ''))
        : '';

    $result = erp_edoc_call('send_invoice', array($invoice, $lines, array('party' => $party)), $provider);

    if (empty($result['success'])) {
        erp_query("UPDATE erp_invoices
            SET edoc_status = 'error', edoc_error = '" . escape(mb_substr((string) $result['error'], 0, 2000)) . "', updated_at = '" . time() . "'
            WHERE id = '" . $invoice_id . "'");

        return array('success' => false, 'message' => '', 'error' => (string) $result['error']);
    }

    // What the driver calls the new state. İşbaşı answers 'created': the
    // record exists there, as a draft, until it is handed to GİB.
    $known = array_keys(erp_edoc_status_labels());
    $created_status = (string) ($result['status'] ?? 'sent');
    $created_status = in_array($created_status, $known, true) ? $created_status : 'sent';

    // A provider that already knows the document's GİB number and ETTN says
    // so in its answer (İşbaşı does), and then nothing has to be asked for.
    $set = array(
        "edoc_provider = '" . escape($provider) . "'",
        "edoc_external_id = '" . escape(mb_substr((string) ($result['external_id'] ?? ''), 0, 64)) . "'",
        "edoc_status = '" . escape($created_status) . "'",
        "edoc_error = ''",
        "edoc_sent_at = '" . time() . "'",
        "updated_at = '" . time() . "'",
    );

    if (trim((string) ($result['gib_uuid'] ?? '')) !== '') {
        $set[] = "gib_uuid = '" . escape(mb_substr(trim((string) $result['gib_uuid']), 0, 36)) . "'";
    }

    if (trim((string) ($result['gib_number'] ?? '')) !== '') {
        $set[] = "gib_number = '" . escape(mb_substr(trim((string) $result['gib_number']), 0, 20)) . "'";
    }

    erp_query("UPDATE erp_invoices SET " . implode(', ', $set) . " WHERE id = '" . $invoice_id . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp invoice ({var:1}) was sent to {var:2} as an e-document', 'vars' => array((string) $invoice['full_number'], erp_edoc_info($provider)['label']))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    if (function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    $environment = erp_edoc_environment($provider);
    $message = lang(array('string' => '{var:1} took the invoice (id {var:2}).', 'vars' => array(erp_edoc_info($provider)['label'], (string) ($result['external_id'] ?? ''))));

    // The second step. A draft the provider is keeping is not a document
    // yet, so unless the store asked to look its drafts over first, the one
    // button finishes the job. A failure here leaves the row at 'created'
    // with the provider's own words on it; the screen then offers the
    // hand-over by itself and no second draft is ever created.
    if ($created_status === 'created') {
        if (!erp_edoc_supports('submit_invoice', $provider)) {
            $message .= ' ' . lang('It stays a draft there until it is handed to GİB.');
        } elseif (!erp_edoc_autosend()) {
            $message .= ' ' . lang('It is a draft there; hand it to GİB when you have looked it over.');
        } else {
            $submitted = erp_edoc_invoice_submit($invoice_id, $user_id);

            if (empty($submitted['success'])) {
                return array(
                    'success' => false,
                    'message' => '',
                    'error' => lang(array(
                        'string' => '{var:1} saved the invoice as a draft (id {var:2}) but would not hand it to GİB: {var:3}',
                        'vars' => array(erp_edoc_info($provider)['label'], (string) ($result['external_id'] ?? ''), (string) $submitted['error']),
                    )),
                );
            }

            $message .= ' ' . $submitted['message'];
        }
    } else {
        $message .= ' ' . lang('Ask after its status in a minute for the GİB number.');
    }

    return array(
        'success' => true,
        'message' => $message . (!empty($environment['is_test']) ? ' — ' . $environment['label'] : ''),
        'error' => '',
    );
}

/**
 * Whether the hand-over to the tax authority follows the creation on its
 * own. The store decides on the E-Invoice card of the commerce settings; a
 * database that has not run the 4.63 step yet answers yes, because a button
 * that says "send" is expected to finish the job.
 *
 * @return bool
 */
function erp_edoc_autosend()
{
    return !defined('ERP_EDOC_AUTOSEND') || ((int) ERP_EDOC_AUTOSEND === 1);
}

/**
 * Hands a document the provider is holding as a draft to the tax authority.
 * This is the step that makes it a legal document; until it runs, the
 * record exists at the provider and nowhere else.
 *
 * It never creates anything: a document that is not a draft any more is
 * refused here rather than sent twice.
 *
 * @param int $invoice_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_submit($invoice_id, $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    $provider = (string) $invoice['edoc_provider'];

    if (($provider === '') || ((string) $invoice['edoc_external_id'] === '')) {
        return array('success' => false, 'message' => '', 'error' => lang('The document has not been sent to a provider.'));
    }

    if (!erp_edoc_supports('submit_invoice', $provider)) {
        return array('success' => false, 'message' => '', 'error' => lang(array('string' => 'The {var:1} driver cannot hand a document to GİB yet.', 'vars' => erp_edoc_info($provider)['label'])));
    }

    if (in_array((string) $invoice['edoc_status'], array('sent', 'accepted'), true)) {
        return array('success' => false, 'message' => '', 'error' => lang('The document has already gone; ask after its status instead of handing it over twice.'));
    }

    $result = erp_edoc_call('submit_invoice', array($invoice, (string) $invoice['edoc_external_id']), $provider);

    if (empty($result['success'])) {
        erp_query("UPDATE erp_invoices
            SET edoc_error = '" . escape(mb_substr((string) $result['error'], 0, 2000)) . "', updated_at = '" . time() . "'
            WHERE id = '" . $invoice_id . "'");

        return array('success' => false, 'message' => '', 'error' => (string) $result['error']);
    }

    erp_query("UPDATE erp_invoices
        SET edoc_status = 'sent',
            edoc_error = '" . escape(mb_substr((string) ($result['message'] ?? ''), 0, 2000)) . "',
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp invoice ({var:1}) was handed to GİB through {var:2}', 'vars' => array((string) $invoice['full_number'], erp_edoc_info($provider)['label']))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    if (function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    return array(
        'success' => true,
        'message' => (trim((string) ($result['message'] ?? '')) !== '')
            ? (string) $result['message']
            : lang(array('string' => '{var:1} handed the document to GİB.', 'vars' => erp_edoc_info($provider)['label'])),
        'error' => '',
    );
}

/**
 * Asks the carrying provider where a sent invoice stands and writes what
 * it hears: GİB number, ETTN, status.
 *
 * @param int $invoice_id
 * @return array ['success' => bool, 'message' => string, 'error' => string, 'status' => string]
 */
function erp_edoc_invoice_poll($invoice_id)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'), 'status' => '');
    }

    $provider = (string) $invoice['edoc_provider'];

    if (($provider === '') || ((string) $invoice['edoc_external_id'] === '')) {
        return array('success' => false, 'message' => '', 'error' => lang('The document has not been sent to a provider.'), 'status' => (string) $invoice['edoc_status']);
    }

    if (!erp_edoc_supports('poll', $provider)) {
        return array('success' => false, 'message' => '', 'error' => lang(array('string' => 'The {var:1} driver cannot ask after a document yet.', 'vars' => erp_edoc_info($provider)['label'])), 'status' => (string) $invoice['edoc_status']);
    }

    $result = erp_edoc_call('poll', array($invoice, (string) $invoice['edoc_external_id']), $provider);

    if (empty($result['success'])) {
        return array('success' => false, 'message' => '', 'error' => (string) $result['error'], 'status' => (string) $invoice['edoc_status']);
    }

    $status = (string) ($result['status'] ?? 'sent');
    $known = array_keys(erp_edoc_status_labels());
    $status = in_array($status, $known, true) ? $status : 'sent';
    $before = (string) $invoice['edoc_status'];

    $set = array(
        "edoc_status = '" . escape($status) . "'",
        "edoc_error = '" . escape(mb_substr((string) ($result['message'] ?? ''), 0, 2000)) . "'",
        "updated_at = '" . time() . "'",
    );

    if (trim((string) ($result['gib_number'] ?? '')) !== '') {
        $set[] = "gib_number = '" . escape(mb_substr(trim((string) $result['gib_number']), 0, 20)) . "'";
    }

    if (trim((string) ($result['gib_uuid'] ?? '')) !== '') {
        $set[] = "gib_uuid = '" . escape(mb_substr(trim((string) $result['gib_uuid']), 0, 36)) . "'";
    }

    // Which document GİB made of it, when the driver can tell: the provider
    // decides from the buyer's registration, so the store only learns it
    // after the fact.
    if (in_array((string) ($result['kind'] ?? ''), array('einvoice', 'earchive'), true)) {
        $set[] = "edoc_kind = '" . escape((string) $result['kind']) . "'";
    }

    erp_query("UPDATE erp_invoices SET " . implode(', ', $set) . " WHERE id = '" . $invoice_id . "'");

    // The card the provider put the invoice on, when the account has none yet.
    if (!empty($result['party']) && is_array($result['party']) && function_exists('erp_edoc_account_link_from_invoice')) {
        erp_edoc_account_link_from_invoice($provider, $invoice, $result['party']);
    }

    if (($status !== $before) && function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    $labels = erp_edoc_status_labels();
    $message = $labels[$status][0];

    if (trim((string) ($result['gib_number'] ?? '')) !== '') {
        $message .= ' · ' . trim((string) $result['gib_number']);
    }

    if (trim((string) ($result['message'] ?? '')) !== '') {
        $message .= ' — ' . trim((string) $result['message']);
    }

    return array('success' => true, 'message' => $message, 'error' => '', 'status' => $status);
}

/**
 * Asks the active provider whether a tax number is registered at GİB for
 * e-Invoice, and keeps the answer on the account card.
 *
 * erp_accounts has carried einvoice_user / einvoice_alias / einvoice_aliases
 * / einvoice_checked_at since 4.43 and nothing has ever written to them; this
 * is what fills them. The number asked about is the one on screen, which may
 * not be the one on file yet - then the answer is reported and not stored,
 * because a stored answer has to belong to a stored number.
 *
 * @param int         $account_id
 * @param string|null $tax_number  The number to ask about; the stored one when null
 * @return array ['success' => bool, 'message' => string, 'error' => string, 'stored' => bool]
 */
function erp_edoc_account_check_taxpayer($account_id, $tax_number = null)
{
    $account_id = (int) $account_id;
    $account = db_item("SELECT * FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");
    $out = array('success' => false, 'message' => '', 'error' => '', 'stored' => false);

    if (!is_array($account)) {
        $out['error'] = lang('The account could not be found.');
        return $out;
    }

    $stored_number = preg_replace('/\D/', '', (string) $account['tax_number']);
    $number = ($tax_number === null) ? $stored_number : preg_replace('/\D/', '', (string) $tax_number);

    if ((strlen($number) !== 10) && (strlen($number) !== 11)) {
        $out['error'] = lang('A VKN has 10 digits and a TCKN 11.');
        return $out;
    }

    $provider = erp_edoc_active();

    if ($provider === '') {
        $out['error'] = lang('No e-document provider is selected on the E-Invoice card of the commerce settings.');
        return $out;
    }

    if (!erp_edoc_supports('check_taxpayer', $provider)) {
        $out['error'] = lang(array('string' => 'The {var:1} driver cannot ask GİB about a tax number yet.', 'vars' => erp_edoc_info($provider)['label']));
        return $out;
    }

    $result = erp_edoc_call('check_taxpayer', array($number), $provider);

    if (empty($result['success'])) {
        $out['error'] = (string) $result['error'];
        return $out;
    }

    $is_user = !empty($result['is_einvoice_user']);
    $aliases = array_values(array_filter(array_map('strval', (array) ($result['aliases'] ?? ''))));

    // Only an answer about the number the card actually holds may be kept.
    if ($number === $stored_number) {
        erp_query("UPDATE erp_accounts
            SET einvoice_user = '" . ($is_user ? 1 : 0) . "',
                einvoice_alias = '" . escape(mb_substr((string) ($aliases[0] ?? ''), 0, 100)) . "',
                einvoice_aliases = '" . escape(json_encode($aliases, JSON_UNESCAPED_UNICODE)) . "',
                einvoice_checked_at = '" . time() . "',
                updated_at = '" . time() . "'
            WHERE id = '" . $account_id . "'");

        $out['stored'] = true;
    }

    $out['success'] = true;
    $out['message'] = ($is_user
        ? lang(array('string' => '{var:1} is registered for e-Invoice at GİB{var:2}.', 'vars' => array($number,
            empty($aliases) ? '' : ' (' . implode(', ', array_slice($aliases, 0, 3)) . ')')))
        : lang(array('string' => '{var:1} is not registered for e-Invoice at GİB; a document for this buyer goes as an e-Archive.', 'vars' => $number)))
        . (!empty($result['is_edispatch_user']) ? ' ' . lang('Registered for e-Delivery notes as well.') : '')
        . ($out['stored'] ? '' : ' ' . lang('Not kept on the card: the number on the card is a different one. Save the account first.'));

    return $out;
}

/**
 * The provider's copy of a sent document.
 *
 * @param int    $invoice_id
 * @param string $format  'pdf' | 'xml'
 * @return array ['success' => bool, 'content' => string, 'filename' => string, 'mime' => string, 'error' => string]
 */
function erp_edoc_invoice_document($invoice_id, $format = 'pdf')
{
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");
    $none = array('success' => false, 'content' => '', 'filename' => '', 'mime' => '', 'error' => '');

    if (!is_array($invoice)) {
        return array_merge($none, array('error' => lang('The invoice could not be found.')));
    }

    $provider = (string) $invoice['edoc_provider'];

    if ($provider === '') {
        return array_merge($none, array('error' => lang('The document has not been sent to a provider.')));
    }

    if (!erp_edoc_supports('fetch_document', $provider)) {
        return array_merge($none, array('error' => lang(array('string' => 'The {var:1} driver does not hand documents back yet.', 'vars' => erp_edoc_info($provider)['label']))));
    }

    return array_merge($none, erp_edoc_call('fetch_document', array($invoice, $format), $provider));
}
