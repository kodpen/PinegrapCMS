<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP e-document driver: Paraşüt.
 *
 * Paraşüt is reached through the client the site has carried since the
 * Paraşüt repair (includes/fn/parasut.php): OAuth2 (an authorization code or
 * the password grant), one token sealed among the service tokens,
 * credentials AES-encrypted in the config row and entered on the commerce
 * settings card. This driver declares no fields of its own and points the
 * settings screen at that card; what it adds is the ERP document path.
 *
 * How an invoice travels (API v4, apidocs.parasut.com):
 *
 *   send_invoice   the buyer's card is found or opened (contacts), the
 *                  invoice is written as a sales_invoices record with its
 *                  lines, and the total Paraşüt computed is compared with
 *                  the ERP's; a record whose total differs is deleted again.
 *                  Answers 'created': the record is a plain invoice at
 *                  Paraşüt until it is turned into an e-document.
 *   submit_invoice asks GİB's registry through e_invoice_inboxes whether the
 *                  buyer takes e-Fatura, then POSTs e_invoices (with the
 *                  buyer's alias and the scenario) or e_archives (with the
 *                  internet-sale block). Paraşüt answers with a
 *                  trackable_jobs id that is good for fifteen minutes.
 *   poll           the job while it lives, then the sales invoice with its
 *                  active_e_document: kind, ETTN, GİB number, status.
 *   fetch_document the e-document's PDF (e_archives|e_invoices/{id}/pdf
 *                  answers with a signed address, read once).
 *   cancel_invoice a record not yet converted is deleted; an e-Archive
 *                  invoice is cancelled through sales_invoices/{id}/cancel;
 *                  an e-Invoice that has gone is answered with a return.
 *
 * The company's customer and supplier cards (contacts) are read and written
 * for the ERP's account sync (includes/erp/edoc/account_sync.php): accounts()
 * pages through the list, account() reads one card, account_save() opens a
 * card or changes one. Balances never travel.
 *
 * The fields follow the published swagger (apidocs.parasut.com/swagger.json)
 * and are read by name with fallbacks; where the documentation is silent
 * the comment beside the reading says what the live API answered, or
 * UNVERIFIED until a live document settles it. The API takes 10 requests in
 * any 10 seconds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// A trackable job answers for fifteen minutes (API documentation); after
// that the document itself is asked.
if (!defined('ERP_EDOC_PARASUT_JOB_LIFETIME')) {
    define('ERP_EDOC_PARASUT_JOB_LIFETIME', 15 * 60);
}

// The contacts list gives at most 25 cards a page (documentation:
// page[size] maximum 25).
if (!defined('ERP_EDOC_PARASUT_PAGE_SIZE')) {
    define('ERP_EDOC_PARASUT_PAGE_SIZE', 25);
}

function erp_edoc_parasut_info()
{
    return array(
        'label' => 'Paraşüt',
        'description' => lang('Cloud pre-accounting by Mikrogrup. e-Invoice and e-Archive through the Paraşüt API; the store needs a Paraşüt subscription with API access.'),
        'docs_url' => 'https://apidocs.parasut.com/',
        'capabilities' => array('einvoice', 'earchive', 'accounts'),
        'settings_note' => lang('The Paraşüt client id, secret and company id are the Paraşüt boxes further down on this card; authorize Pinegrap there with Connect (or enter the Paraşüt user name and password).'),
    );
}

/**
 * No fields: the credentials live in the config row (Faz -1), read by
 * parasut_get_token().
 */
function erp_edoc_parasut_fields()
{
    return array();
}

/* ---------------------------------------------------------------------------
   Transport
   --------------------------------------------------------------------------- */

/**
 * One call to the company's API, logged. A 401 drops the cached token and
 * tries once more with a renewed one.
 *
 * @param string       $method   GET | POST | PUT | DELETE
 * @param string       $path     '/sales_invoices', '/trackable_jobs/1' ... under the
 *                               company prefix; a full https address is used as it is
 * @param array|null   $body     Sent as JSON
 * @param array        $context  'doc_type', 'doc_id' for the log
 * @return array  erp_edoc_http() result + 'login_error' (the token could not be had)
 */
function erp_edoc_parasut_request($method, $path, $body = null, $context = array())
{
    $none = array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => '', 'duration_ms' => 0, 'login_error' => '');

    if (!function_exists('parasut_get_token')) {
        return array_merge($none, array('login_error' => lang('The Paraşüt client is not loaded on this installation.')));
    }

    $url = (strpos((string) $path, 'https://') === 0) ? (string) $path : _parasut_prefix() . (string) $path;
    $doc_type = (string) ($context['doc_type'] ?? '');
    $doc_id = (int) ($context['doc_id'] ?? 0);
    $renewed = false;
    $waited = false;

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $token = parasut_get_token();

        if (empty($token['success'])) {
            return array_merge($none, array('login_error' => (string) $token['error']));
        }

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $token['token'],
        );

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if (function_exists('parasut_throttle')) {
            parasut_throttle();
        }

        $result = erp_edoc_http($method, $url, $headers, $body, 40);
        erp_edoc_log('parasut', $doc_type, $doc_id, $method, $url, $result, $body);
        $code = (int) $result['http_code'];

        if (($code === 401) && !$renewed && function_exists('parasut_token_forget')) {
            $renewed = true;
            parasut_token_forget();
            continue;
        }

        // Over the rate limit (another process was writing too): one wait of
        // a full window, then the same request once more. A POST refused with
        // 429 was not processed, so repeating it cannot double a record.
        if (($code === 429) && !$waited) {
            $waited = true;
            sleep((int) ceil(defined('PARASUT_RATE_WINDOW') ? PARASUT_RATE_WINDOW : 10));
            continue;
        }

        return $result + array('login_error' => '');
    }

    return $result + array('login_error' => '');
}

/**
 * Whether an answer is one Paraşüt meant as success: a 2xx, and a body
 * that is JSON or empty (a DELETE answers 204 with nothing).
 *
 * @param array $result
 * @return bool
 */
function erp_edoc_parasut_ok($result)
{
    $code = (int) ($result['http_code'] ?? 0);

    if (($code < 200) || ($code > 299) || ((string) ($result['error'] ?? '') !== '')) {
        return false;
    }

    return (trim((string) ($result['raw'] ?? '')) === '') || is_array($result['body'] ?? null);
}

/**
 * The server's own words for a refusal (JSON:API errors[].title / detail,
 * the OAuth error_description), or the transport error, or the code.
 *
 * @param array $result
 * @return string
 */
function erp_edoc_parasut_error_text($result)
{
    if ((string) ($result['login_error'] ?? '') !== '') {
        return (string) $result['login_error'];
    }

    if ((string) ($result['error'] ?? '') !== '') {
        return (string) $result['error'];
    }

    $body = is_array($result['body'] ?? null) ? $result['body'] : array();
    $said = array();

    foreach ((array) ($body['errors'] ?? array()) as $error) {
        if (is_array($error)) {
            // Paraşüt often repeats the detail as the title ("X: X").
            $title = trim((string) ($error['title'] ?? ''));
            $detail = trim((string) ($error['detail'] ?? ''));
            $line = (($title !== '') && ($detail !== '') && ($title !== $detail)) ? $title . ': ' . $detail : (($detail !== '') ? $detail : $title);
        } else {
            $line = trim((string) $error);
        }

        if (($line !== '') && !in_array($line, $said, true)) {
            $said[] = $line;
        }
    }

    if (!empty($said)) {
        return implode(' | ', $said);
    }

    if ((string) ($body['error_description'] ?? '') !== '') {
        return (string) $body['error_description'];
    }

    if ((string) ($body['error'] ?? '') !== '') {
        return (string) $body['error'];
    }

    return 'HTTP ' . (int) ($result['http_code'] ?? 0);
}

/**
 * Kurus to the decimal Paraşüt reads, and back. Paraşüt answers amounts as
 * strings ("1180.0"); both directions round to the kurus.
 */
function erp_edoc_parasut_money($kurus)
{
    return round(((int) $kurus) / 100, 2);
}

function erp_edoc_parasut_kurus($decimal)
{
    return (int) round(((float) str_replace(',', '.', (string) $decimal)) * 100);
}

/**
 * Paraşüt's code for a currency: TRL for the lira (its own convention, the
 * ISO code is TRY), the ISO code for the rest it knows (USD, EUR, GBP).
 *
 * @param string $currency
 * @return string
 */
function erp_edoc_parasut_currency($currency)
{
    $currency = strtoupper(trim((string) $currency));

    return (($currency === '') || ($currency === 'TRY') || ($currency === 'TL')) ? 'TRL' : $currency;
}

/* ---------------------------------------------------------------------------
   Connection
   --------------------------------------------------------------------------- */

/**
 * Can we get a token, and is a company named.
 *
 * @param array $credentials  Unused: Paraşüt reads its own
 * @return array ['success' => bool, 'message' => string, 'details' => array]
 */
function erp_edoc_parasut_ping($credentials)
{
    if (!function_exists('parasut_get_token')) {
        return array('success' => false, 'message' => lang('The Paraşüt client is not loaded on this installation.'), 'details' => array());
    }

    $company = defined('PARASUT_COMPANY_ID') ? trim((string) PARASUT_COMPANY_ID) : '';

    if ($company === '') {
        return array('success' => false, 'message' => lang('The Paraşüt company id is empty. Connect on the Paraşüt card of the commerce settings, or enter it there.'), 'details' => array());
    }

    $token = parasut_get_token();

    if (empty($token['success'])) {
        return array('success' => false, 'message' => (string) $token['error'], 'details' => array());
    }

    // The token is good; the company it opens is read back so a company id
    // typed for another account is caught here and not on the first invoice.
    $me = function_exists('parasut_me') ? parasut_me((string) $token['token']) : array('success' => false, 'companies' => array(), 'email' => '', 'error' => '');
    $companies = (array) ($me['companies'] ?? array());

    if (!empty($me['success']) && !empty($companies) && !isset($companies[$company])) {
        $named = array();

        foreach ($companies as $id => $name) {
            $named[] = ((string) $name !== '') ? $name . ' (' . $id . ')' : (string) $id;
        }

        return array(
            'success' => false,
            'message' => lang(array('string' => 'The Paraşüt user cannot see company {var:1}. Its companies: {var:2}', 'vars' => array($company, implode(', ', $named)))),
            'details' => array('company_id' => $company),
        );
    }

    $name = (string) ($companies[$company] ?? '');
    $company_text = ($name !== '') ? $name . ' (' . $company . ')' : $company;
    $email = (string) ($me['email'] ?? '');

    return array(
        'success' => true,
        'message' => ($email !== '')
            ? lang(array('string' => 'Paraşüt answered; company {var:1}, user {var:2}.', 'vars' => array($company_text, $email)))
            : lang(array('string' => 'Paraşüt answered; company {var:1}.', 'vars' => $company_text)),
        'details' => array('company_id' => $company, 'email' => $email),
    );
}

/**
 * Whether a tax number belongs to a registered e-invoice user, its aliases
 * (the mailbox addresses) and the name it is registered under at GİB.
 *
 * @param string $vkn
 * @return array ['success' => bool, 'is_einvoice_user' => bool, 'aliases' => array, 'title' => string, 'error' => string]
 */
function erp_edoc_parasut_check_taxpayer($vkn)
{
    $result = erp_edoc_parasut_request('GET', '/e_invoice_inboxes?filter[vkn]=' . rawurlencode((string) $vkn), null, array('doc_type' => '', 'doc_id' => 0));

    if (!erp_edoc_parasut_ok($result)) {
        return array('success' => false, 'is_einvoice_user' => false, 'aliases' => array(), 'title' => '', 'error' => erp_edoc_parasut_error_text($result));
    }

    $inboxes = erp_edoc_parasut_inboxes_read($result['body']);

    return array('success' => true, 'is_einvoice_user' => !empty($inboxes['aliases']), 'aliases' => $inboxes['aliases'], 'title' => $inboxes['title'], 'error' => '');
}

/**
 * Reads an e_invoice_inboxes answer: one entry per GİB mailbox of the tax
 * number, each carrying the mailbox (e_invoice_address) and the name the
 * taxpayer is registered under (name). The name is the same on every
 * mailbox of one number; the first one given is taken.
 *
 * @param mixed $body  The decoded answer
 * @return array ['aliases' => string[], 'title' => string]
 */
function erp_edoc_parasut_inboxes_read($body)
{
    $aliases = array();
    $title = '';
    $data = (is_array($body) && is_array($body['data'] ?? null)) ? $body['data'] : array();

    foreach ($data as $item) {
        $attributes = (is_array($item) && is_array($item['attributes'] ?? null)) ? $item['attributes'] : array();
        $alias = trim((string) ($attributes['e_invoice_address'] ?? ''));
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($attributes['name'] ?? '')));

        if (($alias !== '') && !in_array($alias, $aliases, true)) {
            $aliases[] = $alias;
        }

        if (($title === '') && ($name !== '')) {
            $title = $name;
        }
    }

    return array('aliases' => $aliases, 'title' => $title);
}

/* ---------------------------------------------------------------------------
   The buyer
   --------------------------------------------------------------------------- */

/**
 * What GİB would refuse this buyer for, by field label. Paraşüt itself takes
 * a contact with a name alone; the e-document behind it does not: an
 * identity number (the final consumer's for a counter sale), an address,
 * city and district, and for a company its tax office.
 *
 * The same function answers at issue time, before a number is spent, and
 * at send time.
 *
 * @param array $party    erp_edoc_invoice_party() output
 * @param array $invoice  The row, when there is one
 * @return array  field name => label, empty when the buyer is complete
 */
function erp_edoc_parasut_party_missing($party, $invoice = array())
{
    $tax_number = preg_replace('/\D/', '', (string) ($party['tax_number'] ?? ''));
    $is_person = !empty($party['is_person']) || (strlen($tax_number) === 11);
    $is_walk_in = defined('ERP_WALKIN_ACCOUNT_ID') && ((int) ERP_WALKIN_ACCOUNT_ID > 0)
        && ((int) ($invoice['account_id'] ?? 0) === (int) ERP_WALKIN_ACCOUNT_ID);

    if (($tax_number === '') && ($is_walk_in || $is_person)) {
        $tax_number = erp_edoc_final_consumer_tckn();
    }

    $missing = array();

    if (trim((string) ($party['title'] ?? '')) === '') {
        $missing['title'] = lang('Title');
    }

    if (($tax_number === '') || ((strlen($tax_number) !== 10) && (strlen($tax_number) !== 11))) {
        $missing['tax_number'] = lang('VKN / TCKN');
    } elseif (!erp_edoc_tax_number_valid($tax_number, true)) {
        $missing['tax_number'] = lang('VKN / TCKN (the check digits do not match; the number is mistyped)');
    }

    // A company's document names its tax office; a person's does not.
    if ((strlen($tax_number) === 10) && (trim((string) ($party['tax_office'] ?? '')) === '')) {
        $missing['tax_office'] = lang('Tax office');
    }

    foreach (array('address' => lang('Address'), 'city' => lang('City'), 'district' => lang('District')) as $field => $label) {
        if (trim((string) ($party[$field] ?? '')) === '') {
            $missing[$field] = $label;
        }
    }

    return $missing;
}

/**
 * What the document itself is missing: the internet sale's web address and
 * carrier (VUK 509), which Paraşüt's internet_sale block carries.
 *
 * @param array $invoice  An erp_invoices row
 * @return array  field name => label, empty when nothing is missing
 */
function erp_edoc_parasut_document_missing($invoice)
{
    $missing = array();

    if ((int) ($invoice['is_internet_sale'] ?? 0) !== 1) {
        return $missing;
    }

    if (trim((string) ($invoice['web_address'] ?? '')) === '') {
        $missing['web_address'] = lang('Web address');
    }

    if (trim((string) ($invoice['carrier_title'] ?? '')) === '') {
        $missing['carrier_title'] = lang('Carrier');
    }

    $carrier_vkn = preg_replace('/\D/', '', (string) ($invoice['carrier_vkn'] ?? ''));

    if (($carrier_vkn === '') || ((strlen($carrier_vkn) !== 10) && (strlen($carrier_vkn) !== 11))) {
        $missing['carrier_vkn'] = lang('Carrier VKN / TCKN');
    } elseif (!erp_edoc_tax_number_valid($carrier_vkn)) {
        $missing['carrier_vkn'] = lang('Carrier VKN / TCKN (not a valid number)');
    }

    return $missing;
}

/**
 * The VAT rates Paraşüt takes on a sales invoice line: Turkey's rates since
 * July 2023. The documentation types vat_rate as a plain number; the live
 * API answers 422 "KDV oranı (18.0) artık kullanılamaz." for an older one.
 *
 * @return float[]
 */
function erp_edoc_parasut_vat_rates()
{
    return array(0.0, 1.0, 10.0, 20.0);
}

/**
 * Why an invoice cannot go to Paraşüt for its VAT rates, or '' when every
 * line carries one Paraşüt takes. An issued invoice keeps its figures, so the
 * way out is to cancel it and issue it again at the current rate.
 *
 * @param array $lines  erp_invoice_items rows (tax_rate)
 * @return string
 */
function erp_edoc_parasut_vat_rate_refusal($lines)
{
    $taken = erp_edoc_parasut_vat_rates();
    $refused = array();

    foreach ((array) $lines as $line) {
        $rate = is_array($line) ? round((float) ($line['tax_rate'] ?? 0), 3) : 0.0;

        if (!in_array($rate, $taken, true) && !in_array($rate, $refused, true)) {
            $refused[] = $rate;
        }
    }

    if (empty($refused)) {
        return '';
    }

    return lang(array(
        'string' => 'Paraşüt takes VAT at {var:1} only; this invoice has {var:2}. It cannot be sent at that rate: cancel it and issue it again at a current rate.',
        'vars' => array(
            implode(', ', array_map('erp_percent_text', $taken)),
            implode(', ', array_map('erp_percent_text', $refused)),
        ),
    ));
}

/**
 * The attributes a Paraşüt contact (its customer card) is written with.
 *
 * Paraşüt keeps one identity field, tax_number, for the VKN of a company
 * and the TCKN of a person (the "TC Kimlik No" box of a person's card); the
 * contact_type says which. The live API takes a person's TCKN in
 * tax_number and finds the card by it again (the old order path sent a
 * 'tckn' attribute the documentation does not list).
 *
 * The postal code goes with the address when the buyer has one: the
 * account sync compares it, and a card opened without it would differ from
 * its account on that field alone.
 *
 * @param array  $party       erp_edoc_invoice_party() output
 * @param string $tax_number  Digits; the final consumer's for a counter sale
 * @return array
 */
function erp_edoc_parasut_contact_attributes($party, $tax_number)
{
    $is_person = !empty($party['is_person']) || (strlen($tax_number) === 11);
    $country = strtoupper(trim((string) ($party['country_code'] ?? 'TR')));

    $attributes = array(
        'account_type' => 'customer',
        'contact_type' => $is_person ? 'person' : 'company',
        'name' => mb_substr(trim((string) $party['title']), 0, 255),
        'tax_number' => $tax_number,
        'tax_office' => mb_substr(trim((string) ($party['tax_office'] ?? '')), 0, 255),
        'district' => mb_substr(trim((string) ($party['district'] ?? '')), 0, 255),
        'city' => mb_substr(trim((string) ($party['city'] ?? '')), 0, 255),
        'address' => mb_substr(trim((string) ($party['address'] ?? '')), 0, 1000),
        'is_abroad' => (($country !== '') && ($country !== 'TR')),
    );

    if (trim((string) ($party['email'] ?? '')) !== '') {
        $attributes['email'] = trim((string) $party['email']);
    }

    if (trim((string) ($party['phone'] ?? '')) !== '') {
        $attributes['phone'] = mb_substr(trim((string) $party['phone']), 0, 50);
    }

    if (trim((string) ($party['postcode'] ?? '')) !== '') {
        $attributes['postal_code'] = mb_substr(trim((string) $party['postcode']), 0, 255);
    }

    return $attributes;
}

/**
 * The Paraşüt contact an invoice goes on: the card the account is linked
 * to when the link is beyond doubt (options['party']['provider_code'], the
 * contact's id), else the first card with the buyer's tax number, else a
 * new one.
 *
 * The linked card is read once before it is used: since the cards were
 * last read it may have been archived, deleted or given another number at
 * Paraşüt, and then the card is looked up as if there were no link.
 *
 * A counter sale carries the final consumer's number, which many cards may
 * share; such a buyer is matched by name on top of the number, so one
 * consumer's document is not written on another consumer's card.
 *
 * @param array $party       erp_edoc_invoice_party() output (+ provider_code)
 * @param string $tax_number Digits
 * @param array $context     For the log
 * @return array ['id' => string, 'error' => string]
 */
function erp_edoc_parasut_contact_for($party, $tax_number, $context = array())
{
    $linked = trim((string) ($party['provider_code'] ?? ''));

    if ($linked !== '') {
        $read = erp_edoc_parasut_request('GET', '/contacts/' . rawurlencode($linked), null, $context);

        if ((string) $read['login_error'] !== '') {
            return array('id' => '', 'error' => $read['login_error']);
        }

        if (erp_edoc_parasut_ok($read) && erp_edoc_parasut_linked_contact_usable($read['body']['data'] ?? null, $linked, $tax_number)) {
            return array('id' => $linked, 'error' => '');
        }
    }

    $final_consumer = ($tax_number === erp_edoc_final_consumer_tckn());
    $wanted_name = mb_strtolower(trim((string) $party['title']), 'UTF-8');

    $found = erp_edoc_parasut_request('GET', '/contacts?filter[tax_number]=' . rawurlencode($tax_number) . '&page[size]=25', null, $context);

    if ((string) $found['login_error'] !== '') {
        return array('id' => '', 'error' => $found['login_error']);
    }

    if (erp_edoc_parasut_ok($found)) {
        foreach ((array) ($found['body']['data'] ?? array()) as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            $attributes = (array) ($item['attributes'] ?? array());

            if ($id === '') {
                continue;
            }

            // The filter is the server's; a card whose number only begins the
            // same way is not this buyer.
            if (preg_replace('/\D/', '', (string) ($attributes['tax_number'] ?? '')) !== $tax_number) {
                continue;
            }

            if (!empty($attributes['archived'])) {
                continue;
            }

            if ($final_consumer && (mb_strtolower(trim((string) ($attributes['name'] ?? '')), 'UTF-8') !== $wanted_name)) {
                continue;
            }

            return array('id' => $id, 'error' => '');
        }
    } elseif ((int) $found['http_code'] !== 404) {
        return array('id' => '', 'error' => lang(array('string' => 'Paraşüt would not search its contacts: {var:1}', 'vars' => erp_edoc_parasut_error_text($found))));
    }

    $created = erp_edoc_parasut_request('POST', '/contacts', array('data' => array(
        'type' => 'contacts',
        'attributes' => erp_edoc_parasut_contact_attributes($party, $tax_number),
    )), $context);

    if ((string) $created['login_error'] !== '') {
        return array('id' => '', 'error' => $created['login_error']);
    }

    $id = trim((string) ($created['body']['data']['id'] ?? ''));

    if (!erp_edoc_parasut_ok($created) || ($id === '')) {
        return array('id' => '', 'error' => lang(array('string' => 'Paraşüt would not open a contact for the buyer: {var:1}', 'vars' => erp_edoc_parasut_error_text($created))));
    }

    return array('id' => $id, 'error' => '');
}

/**
 * Whether the contact an account is linked to can carry the invoice: it is
 * the card asked for, it is not archived, and it still has the buyer's tax
 * number.
 *
 * @param mixed  $contact     data of a GET contacts/{id} answer
 * @param string $id          The linked contact's id
 * @param string $tax_number  The buyer's number, digits
 * @return bool
 */
function erp_edoc_parasut_linked_contact_usable($contact, $id, $tax_number)
{
    if (!is_array($contact) || (trim((string) ($contact['id'] ?? '')) !== trim((string) $id)) || ((string) $tax_number === '')) {
        return false;
    }

    $attributes = is_array($contact['attributes'] ?? null) ? $contact['attributes'] : array();

    return empty($attributes['archived'])
        && (preg_replace('/\D/', '', (string) ($attributes['tax_number'] ?? '')) === (string) $tax_number);
}

/* ---------------------------------------------------------------------------
   The sales invoice
   --------------------------------------------------------------------------- */

/**
 * The Paraşüt product a line is written against. A sales_invoices line
 * names a product; a Pinegrap product gets its own card once
 * (parasut_sync_product(), products.parasut_product_id), a line without a
 * product (shipping, a charge, free text) goes on the store's default
 * product/service, the settings card's "Default Product/Service ID".
 *
 * @param array $line  An erp_invoice_items row
 * @return array ['id' => string, 'error' => string]
 */
function erp_edoc_parasut_line_product($line)
{
    $product_id = (int) ($line['product_id'] ?? 0);

    if (($product_id > 0) && function_exists('parasut_sync_product')) {
        $token = parasut_get_token();

        if (empty($token['success'])) {
            return array('id' => '', 'error' => (string) $token['error']);
        }

        $sync = parasut_sync_product($product_id, (string) $token['token']);

        if (!empty($sync['success']) && ((string) ($sync['parasut_product_id'] ?? '') !== '')) {
            return array('id' => (string) $sync['parasut_product_id'], 'error' => '');
        }
    }

    $default = defined('PARASUT_DEFAULT_PRODUCT_ID') ? trim((string) PARASUT_DEFAULT_PRODUCT_ID) : '';

    if ($default !== '') {
        return array('id' => $default, 'error' => '');
    }

    return array('id' => '', 'error' => lang(array(
        'string' => 'Line "{var:1}" has no Paraşüt product to go on. Enter a default product/service id on the Paraşüt card of the commerce settings (a generic service opened in Paraşüt).',
        'vars' => (string) ($line['description'] ?? ''),
    )));
}

/**
 * The sales_invoices body for an invoice, field for field from the
 * documentation. Unit prices are VAT-exclusive; the discount travels as the
 * line's rate when it has one, as its amount otherwise.
 *
 * @param array  $invoice
 * @param array  $lines
 * @param string $contact_id  The Paraşüt contact
 * @return array  The payload, or ['error' => string]
 */
function erp_edoc_parasut_invoice_payload($invoice, $lines, $contact_id)
{
    $details = array();

    foreach ($lines as $line) {
        $product = erp_edoc_parasut_line_product($line);

        if ($product['error'] !== '') {
            return array('error' => $product['error']);
        }

        $discount_rate = (float) ($line['discount_rate'] ?? 0);
        $attributes = array(
            'quantity' => (float) $line['quantity'],
            'unit_price' => erp_edoc_parasut_money((int) $line['unit_price']),
            'vat_rate' => (float) $line['tax_rate'],
            'description' => mb_substr(trim((string) $line['description']), 0, 255),
            'discount_type' => ($discount_rate > 0) ? 'percentage' : 'amount',
            'discount_value' => ($discount_rate > 0) ? $discount_rate : erp_edoc_parasut_money((int) ($line['discount_amount'] ?? 0)),
        );

        // Withholding is a line attribute at Paraşüt (details[].vat_withholding_rate),
        // so lines with different rates share one document. The rate is the
        // withheld share of the VAT in percent, as Pinegrap keeps it (4/10 =
        // 40): on the live API 40 on 15.99 VAT came back as 6.40 withheld.
        if ((float) ($line['withholding_rate'] ?? 0) > 0) {
            $attributes['vat_withholding_rate'] = (float) $line['withholding_rate'];
        }

        $details[] = array(
            'type' => 'sales_invoice_details',
            'attributes' => $attributes,
            'relationships' => array(
                'product' => array('data' => array('type' => 'products', 'id' => $product['id'])),
            ),
        );
    }

    $currency = strtoupper(trim((string) $invoice['currency']));
    $attributes = array(
        'item_type' => 'invoice',
        'description' => mb_substr(trim((string) $invoice['full_number'] . (((int) ($invoice['order_id'] ?? 0) > 0) ? ' / ' . lang(array('string' => 'Order #{var:1}', 'vars' => (int) $invoice['order_id'])) : '')), 0, 255),
        'issue_date' => (string) $invoice['issue_date'],
        'due_date' => (((string) $invoice['due_date'] !== '0000-00-00') && ((string) $invoice['due_date'] !== '')) ? (string) $invoice['due_date'] : (string) $invoice['issue_date'],
        'currency' => erp_edoc_parasut_currency($currency),
    );

    // The order number and date go together or not at all: Paraşüt refuses
    // to turn an invoice into an e-document when only one of them is set.
    $order_date = trim((string) ($invoice['order_date'] ?? ''));

    if (((int) ($invoice['order_id'] ?? 0) > 0) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_date)) {
        $attributes['order_no'] = (string) (int) $invoice['order_id'];
        $attributes['order_date'] = $order_date;
    }

    if (($currency !== '') && ($currency !== 'TRY') && ((float) ($invoice['exchange_rate'] ?? 0) > 0)) {
        $attributes['exchange_rate'] = (float) $invoice['exchange_rate'];
    }

    if (trim((string) ($invoice['notes'] ?? '')) !== '') {
        $attributes['invoice_note'] = mb_substr(trim((string) $invoice['notes']), 0, 1000);
    }

    return array('data' => array(
        'type' => 'sales_invoices',
        'attributes' => $attributes,
        'relationships' => array(
            'contact' => array('data' => array('type' => 'contacts', 'id' => (string) $contact_id)),
            'details' => array('data' => $details),
        ),
    ));
}

/**
 * The total the ERP expects Paraşüt to compute: the amount payable, net of
 * discounts plus VAT less the VAT withheld - the invoice's grand_total.
 * Paraşüt's net_total is read after the withholding (live: 79.95 net, 15.99
 * VAT, 6.40 withheld, net_total 89.54), so a total that still holds the
 * withheld VAT means the withholding was not applied there.
 *
 * @param array $invoice
 * @return array  Kurus; one reading
 */
function erp_edoc_parasut_expected_totals($invoice)
{
    return array((int) $invoice['grand_total']);
}

/**
 * Whether the total Paraşüt computed for the sales invoice is the ERP's.
 * The documentation names net_total "Genel Toplam" (the grand total, VAT
 * included) and gross_total "Ara toplam" (the subtotal before VAT), so the
 * grand total is the one compared.
 *
 * @param array $attributes  data.attributes of the sales invoice Paraşüt answered
 * @param array $invoice     The erp_invoices row
 * @return array ['ok' => bool, 'total' => int kurus Paraşüt computed]
 */
function erp_edoc_parasut_total_check($attributes, $invoice)
{
    $attributes = (array) $attributes;

    if (!isset($attributes['net_total']) || ((string) $attributes['net_total'] === '')) {
        return array('ok' => false, 'total' => 0);
    }

    $total = erp_edoc_parasut_kurus((string) $attributes['net_total']);

    return array('ok' => in_array($total, erp_edoc_parasut_expected_totals($invoice), true), 'total' => $total);
}

/**
 * The totals Paraşüt holds for a sales invoice, in kurus: before taxes
 * (before_taxes_total), VAT (total_vat), VAT withheld (total_vat_withholding)
 * and the grand total (net_total). Empty when the answer carries no
 * net_total.
 *
 * @param array $attributes  data.attributes of the sales invoice
 * @return array ['before_taxes' => int, 'vat' => int, 'vat_withholding' => int, 'net' => int] or []
 */
function erp_edoc_parasut_totals($attributes)
{
    $attributes = (array) $attributes;

    if (!isset($attributes['net_total']) || ((string) $attributes['net_total'] === '')) {
        return array();
    }

    return array(
        'before_taxes' => erp_edoc_parasut_kurus((string) ($attributes['before_taxes_total'] ?? ($attributes['gross_total'] ?? '0'))),
        'vat' => erp_edoc_parasut_kurus((string) ($attributes['total_vat'] ?? '0')),
        'vat_withholding' => erp_edoc_parasut_kurus((string) ($attributes['total_vat_withholding'] ?? '0')),
        'net' => erp_edoc_parasut_kurus((string) $attributes['net_total']),
    );
}

/**
 * The Paraşüt ids of a sales invoice's lines, in the order the invoice
 * lists them (relationships.details.data), which is the order they were
 * written in.
 *
 * @param array $body  The decoded answer of GET sales_invoices/{id}?include=details
 * @return array  Ids as strings
 */
function erp_edoc_parasut_detail_ids($body)
{
    $ids = array();

    foreach ((array) ($body['data']['relationships']['details']['data'] ?? array()) as $reference) {
        if (is_array($reference) && (trim((string) ($reference['id'] ?? '')) !== '')) {
            $ids[] = trim((string) $reference['id']);
        }
    }

    return $ids;
}

/**
 * Writes the invoice at Paraşüt as a sales invoice and checks the total it
 * computed. Answers 'created': the record is not an e-document until
 * submit_invoice() converts it (the service's autosend does both).
 *
 * @param array $options  'party' (erp_edoc_invoice_party() output) when the caller has it
 * @return array ['success' => bool, 'external_id' => string, 'gib_uuid' => string,
 *                'gib_number' => string, 'status' => 'created', 'error' => string]
 */
function erp_edoc_parasut_send_invoice($invoice, $lines, $options = array())
{
    $fail = function ($error) {
        return array('success' => false, 'external_id' => '', 'gib_uuid' => '', 'gib_number' => '', 'status' => 'error', 'error' => $error);
    };

    if ((string) ($invoice['direction'] ?? 'sales') !== 'sales') {
        return $fail(lang('Only sales invoices go to Paraşüt.'));
    }

    if ((string) ($invoice['doc_type'] ?? 'invoice') !== 'invoice') {
        return $fail(lang('Only invoices go to Paraşüt; a return or a proforma is not sent through its API.'));
    }

    if (!in_array((string) ($invoice['invoice_type'] ?? 'SATIS'), array('SATIS', 'ISTISNA', 'TEVKIFAT'), true)) {
        return $fail(lang(array('string' => 'The {var:1} invoice type is not sent through the Paraşüt API.', 'vars' => (string) $invoice['invoice_type'])));
    }

    if (empty($lines)) {
        return $fail(lang('The invoice has no lines.'));
    }

    // Refused before anything reaches Paraşüt: a rate it does not take used
    // to open the buyer's card there first and only then fail on the invoice.
    $rate_refusal = erp_edoc_parasut_vat_rate_refusal($lines);

    if ($rate_refusal !== '') {
        return $fail($rate_refusal);
    }

    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);
    $party = (isset($options['party']) && is_array($options['party'])) ? $options['party'] : erp_edoc_invoice_party($invoice);
    $tax_number = preg_replace('/\D/', '', (string) ($party['tax_number'] ?? ''));
    $is_person = !empty($party['is_person']) || (strlen($tax_number) === 11);
    $is_walk_in = defined('ERP_WALKIN_ACCOUNT_ID') && ((int) ERP_WALKIN_ACCOUNT_ID > 0)
        && ((int) ($invoice['account_id'] ?? 0) === (int) ERP_WALKIN_ACCOUNT_ID);

    if (($tax_number === '') && ($is_walk_in || $is_person)) {
        $tax_number = erp_edoc_final_consumer_tckn();
    }

    $missing = erp_edoc_parasut_party_missing($party, $invoice);

    if (!empty($missing)) {
        return $fail(lang(array(
            'string' => 'GİB will not take the invoice until these are on the account card: {var:1}. A document already issued keeps the copy it was written with, so the invoice has to be raised again after the card is filled in.',
            'vars' => implode(', ', $missing),
        )));
    }

    $document_missing = erp_edoc_parasut_document_missing($invoice);

    if (!empty($document_missing)) {
        return $fail(lang(array(
            'string' => 'An internet-sale e-Archive invoice needs these on the invoice before it is sent: {var:1}.',
            'vars' => implode(', ', $document_missing),
        )));
    }

    $contact = erp_edoc_parasut_contact_for($party, $tax_number, $context);

    if ($contact['error'] !== '') {
        return $fail($contact['error']);
    }

    // orders.order_date is a UNIX timestamp; the document wants the day.
    if (((int) ($invoice['order_id'] ?? 0) > 0) && !isset($invoice['order_date'])) {
        $stamp = (int) db_value("SELECT order_date FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1");
        $invoice['order_date'] = ($stamp > 0) ? date('Y-m-d', $stamp) : '';
    }

    $payload = erp_edoc_parasut_invoice_payload($invoice, $lines, $contact['id']);

    if (isset($payload['error'])) {
        return $fail($payload['error']);
    }

    $result = erp_edoc_parasut_request('POST', '/sales_invoices', $payload, $context);

    if ((string) $result['login_error'] !== '') {
        return $fail($result['login_error']);
    }

    $external_id = trim((string) ($result['body']['data']['id'] ?? ''));

    if (!erp_edoc_parasut_ok($result) || ($external_id === '')) {
        return $fail(lang(array('string' => 'Paraşüt would not take the invoice: {var:1}', 'vars' => erp_edoc_parasut_error_text($result))));
    }

    // What Paraşüt made of the lines against what the ERP charged. The
    // record that differs is deleted again: a legal document is not the
    // place to find a rounding gap, and a record left behind would be
    // invoiced twice on the next try.
    $check = erp_edoc_parasut_total_check($result['body']['data']['attributes'] ?? array(), $invoice);

    if (!$check['ok']) {
        erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode($external_id), null, $context);

        return $fail(lang(array(
            'string' => 'Paraşüt computed {var:1} for the invoice where the ERP has {var:2}; the record was deleted there and nothing was issued.',
            'vars' => array(erp_money_out_currency($check['total'], (string) $invoice['currency']), erp_money_out_currency((int) $invoice['grand_total'], (string) $invoice['currency'])),
        )));
    }

    erp_query("UPDATE erp_invoices
        SET parasut_invoice_id = '" . escape(mb_substr($external_id, 0, 32)) . "',
            parasut_edoc_id = '', parasut_job_id = '', parasut_job_expires_at = 0
        WHERE id = '" . (int) $invoice['id'] . "'");

    return array(
        'success' => true,
        'external_id' => $external_id,
        'gib_uuid' => '',
        'gib_number' => '',
        'status' => 'created',
        'error' => '',
    );
}

/**
 * Which e-document the buyer gets, asked of GİB's registry at the moment
 * of conversion (a stale "not registered" is the answer that produces an
 * invalid document). A buyer with no number of their own, or the final
 * consumer's, cannot be registered and takes an e-Archive invoice without
 * a lookup.
 *
 * @param array  $invoice
 * @param string $tax_number  Digits
 * @return array ['kind' => 'einvoice'|'earchive', 'alias' => string, 'error' => string]
 */
function erp_edoc_parasut_decide_kind($invoice, $tax_number)
{
    if (($tax_number === '') || ($tax_number === erp_edoc_final_consumer_tckn())) {
        return array('kind' => 'earchive', 'alias' => '', 'error' => '');
    }

    $lookup = erp_edoc_parasut_check_taxpayer($tax_number);

    if (empty($lookup['success'])) {
        return array('kind' => '', 'alias' => '', 'error' => lang(array(
            'string' => 'Could not check whether the buyer is registered for e-invoice: {var:1}',
            'vars' => (string) $lookup['error'],
        )));
    }

    $aliases = (array) $lookup['aliases'];

    if (empty($aliases)) {
        return array('kind' => 'earchive', 'alias' => '', 'error' => '');
    }

    // The alias the account card holds wins when GİB still lists it; a
    // buyer with several mailboxes gets the first one otherwise.
    $alias = (string) $aliases[0];
    $account = ((int) ($invoice['account_id'] ?? 0) > 0) ? erp_account((int) $invoice['account_id']) : null;
    $kept = is_array($account) ? trim((string) ($account['einvoice_alias'] ?? '')) : '';

    if (($kept !== '') && in_array($kept, $aliases, true)) {
        $alias = $kept;
    }

    return array('kind' => 'einvoice', 'alias' => $alias, 'error' => '');
}

/**
 * The attributes of the e-document request: what both kinds share (the
 * exemption behind a zero-rated line, each withheld line's code) and what
 * each adds - the scenario and the buyer's alias for e-Fatura, the
 * internet-sale block for e-Arşiv. The invoice note is already on the sales
 * invoice (invoice_note); the e-document request has no note of its own.
 *
 * @param array  $invoice
 * @param array  $lines
 * @param string $kind        'einvoice' | 'earchive'
 * @param string $alias       The buyer's GİB mailbox (e-Fatura)
 * @param array  $detail_ids  The Paraşüt line ids in the order of $lines
 *                            (erp_edoc_parasut_detail_ids()); needed for withholding
 * @return array  The attributes, or ['error' => string]
 */
function erp_edoc_parasut_edoc_attributes($invoice, $lines, $kind, $alias, $detail_ids = array())
{
    $attributes = array();
    $exemptions = array();
    $withholding = array();
    $detail_ids = array_values((array) $detail_ids);

    foreach (array_values($lines) as $index => $line) {
        if (((float) ($line['tax_rate'] ?? 0) == 0) && (trim((string) ($line['vat_exemption_code'] ?? '')) !== '')) {
            $exemptions[] = trim((string) $line['vat_exemption_code']);
        }

        $code = trim((string) ($line['withholding_code'] ?? ''));

        if (((float) ($line['withholding_rate'] ?? 0) > 0) && ($code !== '')) {
            // The code goes with the Paraşüt line it belongs to
            // (vat_withholding_params[].detail_id), so the line ids of the
            // sales invoice must be known.
            $detail_id = trim((string) ($detail_ids[$index] ?? ''));

            if ($detail_id === '') {
                return array('error' => lang('The lines of the invoice at Paraşüt could not be matched to the withholding codes; nothing was converted.'));
            }

            $withholding[] = array('detail_id' => (int) $detail_id, 'vat_withholding_code' => $code);
        }
    }

    $exemptions = array_values(array_unique($exemptions));

    if (count($exemptions) > 1) {
        return array('error' => lang('Paraşüt names one VAT exemption reason for the whole document; this invoice has zero-rated lines with different exemption codes.'));
    }

    if (!empty($exemptions)) {
        $attributes['vat_exemption_reason_code'] = $exemptions[0];

        // "Other" exemptions (250, 350) need the reason in words; the code
        // alone is enough for the rest, Paraşüt knows their wording.
        if (in_array($exemptions[0], array('250', '350'), true)) {
            $reason = trim((string) ($invoice['notes'] ?? ''));

            if ($reason === '') {
                return array('error' => lang(array(
                    'string' => 'Exemption code {var:1} needs its reason in words: write it in the invoice note (it goes to GİB as the exemption reason), or choose the specific exemption code.',
                    'vars' => $exemptions[0],
                )));
            }

            $attributes['vat_exemption_reason'] = mb_substr($reason, 0, 255);
        }
    }

    if (!empty($withholding)) {
        $attributes['vat_withholding_params'] = $withholding;
    }

    if ($kind === 'einvoice') {
        $attributes['scenario'] = ((string) ($invoice['edoc_scenario'] ?? '') === 'commercial') ? 'commercial' : 'basic';
        $attributes['to'] = $alias;

        return $attributes;
    }

    if ((int) ($invoice['is_internet_sale'] ?? 0) === 1) {
        $payment_date = (((string) $invoice['payment_date'] !== '0000-00-00') && ((string) $invoice['payment_date'] !== '')) ? (string) $invoice['payment_date'] : (string) $invoice['issue_date'];
        $shipment_date = (((string) $invoice['shipment_date'] !== '0000-00-00') && ((string) $invoice['shipment_date'] !== '')) ? (string) $invoice['shipment_date'] : (string) $invoice['issue_date'];
        $carrier_vkn = preg_replace('/\D/', '', (string) $invoice['carrier_vkn']);
        $method = (string) ($invoice['payment_method'] ?? '');

        // GİB's list ends with DIGER; Paraşüt's documentation lists the first
        // four only. UNVERIFIED on the live API: whether DIGER is taken.
        if (!in_array($method, array('KREDIKARTI/BANKAKARTI', 'EFT/HAVALE', 'KAPIDAODEME', 'ODEMEARACISI', 'DIGER'), true)) {
            $method = 'DIGER';
        }

        $attributes['internet_sale'] = array(
            'url' => (string) $invoice['web_address'],
            'payment_type' => $method,
            'payment_platform' => function_exists('erp_payment_method_labels') ? (string) ((erp_payment_method_labels()[$method]) ?? $method) : $method,
            'payment_date' => $payment_date,
        );

        $shipment = array(
            'title' => (string) $invoice['carrier_title'],
            'date' => $shipment_date,
        );

        if (strlen($carrier_vkn) === 11) {
            $shipment['tckn'] = $carrier_vkn;
            $shipment['name'] = (string) $invoice['carrier_title'];
        } else {
            $shipment['vkn'] = $carrier_vkn;
        }

        $attributes['shipment'] = $shipment;
    }

    return $attributes;
}

/**
 * Turns the sales invoice into the e-document: e_invoices for a registered
 * buyer, e_archives for everyone else. Paraşüt queues the conversion and
 * answers with a trackable job; its id is kept on the row for poll().
 *
 * @param array  $invoice      An erp_invoices row
 * @param string $external_id  Paraşüt's sales invoice id; the row's own when omitted
 * @return array ['success' => bool, 'status' => 'sent', 'message' => string, 'error' => string]
 */
function erp_edoc_parasut_submit_invoice($invoice, $external_id = '')
{
    $fail = function ($error) {
        return array('success' => false, 'status' => '', 'message' => '', 'error' => $error);
    };

    $external_id = trim((string) (($external_id !== '') ? $external_id : ($invoice['edoc_external_id'] ?? '')));

    if ($external_id === '') {
        return $fail(lang('The invoice has no Paraşüt id.'));
    }

    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);
    $lines = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . (int) $invoice['id'] . "' ORDER BY line_no ASC, id ASC");
    $party = erp_edoc_invoice_party($invoice);
    $tax_number = preg_replace('/\D/', '', (string) ($party['tax_number'] ?? ''));

    $decision = erp_edoc_parasut_decide_kind($invoice, $tax_number);

    if ($decision['error'] !== '') {
        return $fail($decision['error']);
    }

    $kind = $decision['kind'];
    $detail_ids = array();

    // A withheld line's code is sent against Paraşüt's own line id.
    foreach ($lines as $line) {
        if (((float) ($line['withholding_rate'] ?? 0) > 0) && (trim((string) ($line['withholding_code'] ?? '')) !== '')) {
            $read = erp_edoc_parasut_request('GET', '/sales_invoices/' . rawurlencode($external_id) . '?include=details', null, $context);

            if ((string) $read['login_error'] !== '') {
                return $fail($read['login_error']);
            }

            if (!erp_edoc_parasut_ok($read)) {
                return $fail(lang(array('string' => 'Paraşüt would not show the lines of the invoice: {var:1}', 'vars' => erp_edoc_parasut_error_text($read))));
            }

            $detail_ids = erp_edoc_parasut_detail_ids($read['body']);

            if (count($detail_ids) !== count($lines)) {
                return $fail(lang('The invoice at Paraşüt does not have the same lines as the ERP invoice; nothing was converted.'));
            }

            break;
        }
    }

    $attributes = erp_edoc_parasut_edoc_attributes($invoice, $lines, $kind, $decision['alias'], $detail_ids);

    if (isset($attributes['error'])) {
        return $fail($attributes['error']);
    }

    // The two requests name the invoice under different relationship keys
    // (documentation: e_invoices.invoice, e_archives.sales_invoice).
    if ($kind === 'einvoice') {
        $body = array('data' => array(
            'type' => 'e_invoices',
            'attributes' => $attributes,
            'relationships' => array('invoice' => array('data' => array('type' => 'sales_invoices', 'id' => $external_id))),
        ));
        $result = erp_edoc_parasut_request('POST', '/e_invoices', $body, $context);
    } else {
        $body = array('data' => array(
            'type' => 'e_archives',
            'attributes' => $attributes,
            'relationships' => array('sales_invoice' => array('data' => array('type' => 'sales_invoices', 'id' => $external_id))),
        ));
        $result = erp_edoc_parasut_request('POST', '/e_archives', $body, $context);
    }

    if ((string) $result['login_error'] !== '') {
        return $fail($result['login_error']);
    }

    if (!erp_edoc_parasut_ok($result)) {
        return $fail(lang(array('string' => 'Paraşüt would not convert the invoice into an e-document: {var:1}', 'vars' => erp_edoc_parasut_error_text($result))));
    }

    $job_id = trim((string) ($result['body']['data']['id'] ?? ''));

    erp_query("UPDATE erp_invoices
        SET edoc_kind = '" . escape($kind) . "',
            parasut_job_id = '" . escape(mb_substr($job_id, 0, 64)) . "',
            parasut_job_expires_at = '" . (($job_id !== '') ? (time() + ERP_EDOC_PARASUT_JOB_LIFETIME) : 0) . "'
        WHERE id = '" . (int) $invoice['id'] . "'");

    return array(
        'success' => true,
        'status' => 'sent',
        'message' => ($kind === 'einvoice')
            ? lang(array('string' => 'Paraşüt is turning the invoice into an e-Invoice for {var:1}.', 'vars' => $decision['alias']))
            : lang('Paraşüt is turning the invoice into an e-Archive invoice.'),
        'error' => '',
    );
}

/* ---------------------------------------------------------------------------
   Where a sent document stands
   --------------------------------------------------------------------------- */

/**
 * The ERP's word for a status word read by its stems, for what
 * erp_edoc_parasut_edoc_state() does not know by name: anything unknown,
 * with an ETTN beside it, counts as sent and awaiting GİB.
 *
 * @param string $status  The provider's status word
 * @return string  'accepted' | 'rejected' | 'cancelled' | 'error' | 'sent'
 */
function erp_edoc_parasut_document_state($status)
{
    // The Turkish capitals first: mb_strtolower() turns İ into i with a
    // combining dot, which no stem below would match.
    $status = mb_strtolower(str_replace(array('İ', 'I'), array('i', 'ı'), trim((string) $status)), 'UTF-8');

    if ($status === '') {
        return 'sent';
    }

    if (preg_match('/iptal|cancel/u', $status)) {
        return 'cancelled';
    }

    if (preg_match('/redded|reject|refus/u', $status)) {
        return 'rejected';
    }

    if (preg_match('/hata|error|fail|başarısız|basarisiz/u', $status)) {
        return 'error';
    }

    // "sent" and "delivered" are not acceptance: a commercial e-Invoice
    // waits for the buyer's answer after it has been delivered.
    if (preg_match('/kabul|accept|success|başarı|basari|complet|done/u', $status)) {
        return 'accepted';
    }

    return 'sent';
}

/**
 * The ERP's word for an e-document Paraşüt holds, from its own attributes.
 *
 * e_archives.status is one of bounced | sent | printed | legalized
 * (documentation): every one is a signed document GİB has, the words say
 * how it reached the buyer (e-mail, paper; bounced = the e-mail came back),
 * so a signed e-Archive invoice counts as accepted and the word is the
 * message. e_invoices.status is waiting | failed | successful, and the
 * buyer's answer to a commercial one is response_type (accepted | rejected
 * | refunded).
 *
 * @param string $type        'e_archives' | 'e_invoices'
 * @param array  $attributes  The e-document's attributes
 * @return array ['status' => accepted|rejected|error|sent, 'message' => string]
 */
function erp_edoc_parasut_edoc_state($type, $attributes)
{
    $attributes = (array) $attributes;
    $status = strtolower(trim((string) ($attributes['status'] ?? '')));

    if ($type === 'e_archives') {
        $signed = !empty($attributes['is_signed']) || in_array($status, array('sent', 'printed', 'legalized', 'bounced'), true);
        $words = array(
            'sent' => lang('e-Archive invoice issued and sent to the buyer.'),
            'printed' => lang('e-Archive invoice issued and printed.'),
            'legalized' => lang('e-Archive invoice issued.'),
            'bounced' => lang('e-Archive invoice issued; the e-mail to the buyer came back.'),
        );

        return array(
            'status' => $signed ? 'accepted' : erp_edoc_parasut_document_state($status),
            'message' => $words[$status] ?? $status,
        );
    }

    $response = strtolower(trim((string) ($attributes['response_type'] ?? '')));

    if ($response === 'rejected') {
        return array('status' => 'rejected', 'message' => trim(lang('The buyer rejected the e-Invoice.') . ' ' . trim((string) ($attributes['response_note'] ?? ''))));
    }

    if ($status === 'failed') {
        return array('status' => 'error', 'message' => lang('GİB did not take the e-Invoice.'));
    }

    if ($status === 'successful') {
        return array(
            'status' => 'accepted',
            'message' => ($response === 'refunded') ? lang('The buyer answered the e-Invoice with a return.') : lang('GİB delivered the e-Invoice.'),
        );
    }

    if ($status === 'waiting') {
        return array('status' => 'sent', 'message' => lang('The e-Invoice is on its way to GİB.'));
    }

    return array('status' => erp_edoc_parasut_document_state($status), 'message' => $status);
}

/**
 * Whether GİB still lets an e-Archive invoice be cancelled
 * (e_archives.cancellable_until). No date given: the server decides.
 *
 * @param array $attributes  The e-Archive invoice's attributes
 * @param int   $now
 * @return bool
 */
function erp_edoc_parasut_cancel_window_open($attributes, $now)
{
    $attributes = (array) $attributes;
    $until = trim((string) ($attributes['cancellable_until'] ?? ''));

    if ($until === '') {
        return true;
    }

    $stamp = strtotime($until);

    return ($stamp === false) || ($stamp >= (int) $now);
}

/**
 * Asks after a converted invoice: the trackable job while it still answers,
 * then the sales invoice and the e-document it became.
 *
 * @param array  $invoice  An erp_invoices row
 * @param string $job_id   The sales invoice id send_invoice() returned
 * @return array ['success' => bool, 'status' => string, 'gib_uuid' => string,
 *                'gib_number' => string, 'kind' => 'einvoice'|'earchive'|'',
 *                'message' => string, 'error' => string, 'party' => array]
 */
function erp_edoc_parasut_poll($invoice, $job_id)
{
    $external_id = trim((string) $job_id);
    $out = array('success' => false, 'status' => 'sent', 'gib_uuid' => '', 'gib_number' => '', 'kind' => '', 'message' => '', 'error' => '', 'party' => array());
    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);

    if ($external_id === '') {
        $out['error'] = lang('The invoice has no Paraşüt id to ask after.');
        return $out;
    }

    // 1. The conversion job, while its window is open. A job that failed
    // is the one place Paraşüt says why in words.
    $tracked = trim((string) ($invoice['parasut_job_id'] ?? ''));

    if (($tracked !== '') && ((int) ($invoice['parasut_job_expires_at'] ?? 0) > time())) {
        $job = erp_edoc_parasut_request('GET', '/trackable_jobs/' . rawurlencode($tracked), null, $context);

        if ((string) $job['login_error'] !== '') {
            $out['error'] = $job['login_error'];
            return $out;
        }

        if (erp_edoc_parasut_ok($job)) {
            $attributes = (array) ($job['body']['data']['attributes'] ?? array());
            $state = mb_strtolower(trim((string) ($attributes['status'] ?? '')), 'UTF-8');
            $said = array();

            foreach ((array) ($attributes['errors'] ?? array()) as $error) {
                $said[] = is_array($error) ? implode(' ', array_map('strval', $error)) : (string) $error;
            }

            if (preg_match('/error|fail|abort/u', $state)) {
                erp_query("UPDATE erp_invoices SET parasut_job_id = '', parasut_job_expires_at = 0 WHERE id = '" . (int) $invoice['id'] . "'");
                $out['success'] = true;
                $out['status'] = 'error';
                $out['message'] = ($said !== array()) ? implode(' | ', array_filter(array_map('trim', $said), 'strlen')) : lang('Paraşüt could not convert the invoice into an e-document.');
                return $out;
            }

            if (!preg_match('/done|complet|success|finish/u', $state)) {
                $out['success'] = true;
                $out['status'] = 'sent';
                $out['message'] = lang('Paraşüt is still converting the invoice; ask again in a minute.');
                return $out;
            }

            erp_query("UPDATE erp_invoices SET parasut_job_id = '', parasut_job_expires_at = 0 WHERE id = '" . (int) $invoice['id'] . "'");
        }
    }

    // 2. The sales invoice and the e-document it became.
    $detail = erp_edoc_parasut_request('GET', '/sales_invoices/' . rawurlencode($external_id) . '?include=active_e_document,contact', null, $context);

    if ((string) $detail['login_error'] !== '') {
        $out['error'] = $detail['login_error'];
        return $out;
    }

    if ((int) $detail['http_code'] === 404) {
        $out['status'] = 'error';
        $out['error'] = lang('Paraşüt no longer has an invoice with this id.');
        return $out;
    }

    if (!erp_edoc_parasut_ok($detail)) {
        $out['error'] = erp_edoc_parasut_error_text($detail);
        return $out;
    }

    $data = is_array($detail['body']['data'] ?? null) ? $detail['body']['data'] : array();
    $attributes = (array) ($data['attributes'] ?? array());
    $included = array();

    foreach ((array) ($detail['body']['included'] ?? array()) as $item) {
        if (is_array($item) && isset($item['type'], $item['id'])) {
            $included[(string) $item['type'] . ':' . (string) $item['id']] = $item;
        }
    }

    // The customer card the invoice is on.
    $contact_reference = (array) ($data['relationships']['contact']['data'] ?? array());

    if (trim((string) ($contact_reference['id'] ?? '')) !== '') {
        $contact = (array) ($included['contacts:' . (string) $contact_reference['id']] ?? array());
        $out['party'] = array(
            'external_id' => trim((string) $contact_reference['id']),
            'code' => trim((string) $contact_reference['id']),
            'title' => trim((string) ($contact['attributes']['name'] ?? '')),
        );
    }

    // The document's number is the series and the number Paraşüt gave it
    // on conversion (invoice_series + invoice_id).
    $series = trim((string) ($attributes['invoice_series'] ?? ''));
    $number = trim((string) ($attributes['invoice_id'] ?? ''));
    $out['gib_number'] = (($series !== '') && ($number !== '')) ? $series . $number : '';

    if ((string) ($attributes['item_type'] ?? '') === 'cancelled') {
        $out['success'] = true;
        $out['status'] = 'cancelled';
        $out['message'] = lang('Paraşüt has the invoice as cancelled.');
        return $out;
    }

    $document_reference = (array) ($data['relationships']['active_e_document']['data'] ?? array());
    $document_type = (string) ($document_reference['type'] ?? '');
    $document_id = trim((string) ($document_reference['id'] ?? ''));

    if (($document_id === '') || !in_array($document_type, array('e_archives', 'e_invoices'), true)) {
        // Converted and still no e-document: the job ran out before it was
        // read, or failed without a word. Shown as the provider's draft, so
        // the hand-over button comes back.
        $out['success'] = true;
        $out['status'] = 'created';
        $out['message'] = lang('The invoice is at Paraşüt but has not become an e-document; hand it to GİB again.');

        // What Paraşüt computed for the draft, so it can be looked over
        // before the hand-over without opening Paraşüt.
        $totals = erp_edoc_parasut_totals($attributes);

        if (($totals !== array()) && function_exists('erp_money_out_currency')) {
            $currency = (string) ($invoice['currency'] ?? 'TRY');
            $out['message'] .= ' ' . lang(array(
                'string' => 'Paraşüt has it at {var:1} before VAT, {var:2} VAT, {var:3} withheld, {var:4} in all.',
                'vars' => array(
                    erp_money_out_currency($totals['before_taxes'], $currency),
                    erp_money_out_currency($totals['vat'], $currency),
                    erp_money_out_currency($totals['vat_withholding'], $currency),
                    erp_money_out_currency($totals['net'], $currency),
                ),
            ));
        }

        return $out;
    }

    $out['kind'] = ($document_type === 'e_invoices') ? 'einvoice' : 'earchive';

    $document = (array) ($included[$document_type . ':' . $document_id] ?? array());

    if (empty($document)) {
        $read = erp_edoc_parasut_request('GET', '/' . $document_type . '/' . rawurlencode($document_id), null, $context);

        if (erp_edoc_parasut_ok($read)) {
            $document = (array) ($read['body']['data'] ?? array());
        }
    }

    $document_attributes = (array) ($document['attributes'] ?? array());
    $out['gib_uuid'] = trim((string) ($document_attributes['uuid'] ?? ($document_attributes['env_uuid'] ?? '')));

    // The e-document's own number is the one GİB knows (e_archives carry
    // invoice_number); the sales invoice's series and number stand in when
    // the e-document does not say.
    if (trim((string) ($document_attributes['invoice_number'] ?? '')) !== '') {
        $out['gib_number'] = trim((string) $document_attributes['invoice_number']);
    }

    $state = erp_edoc_parasut_edoc_state($document_type, $document_attributes);
    $out['status'] = $state['status'];
    $out['message'] = $state['message'];

    erp_query("UPDATE erp_invoices SET parasut_edoc_id = '" . escape(mb_substr($document_id, 0, 32)) . "' WHERE id = '" . (int) $invoice['id'] . "'");

    $out['success'] = true;

    return $out;
}

/**
 * The e-document's PDF. Paraşüt answers the pdf address with a signed
 * link that lives a short while; it is read at once and handed back.
 *
 * @param array  $invoice  An erp_invoices row
 * @param string $format   'pdf' (the API hands no UBL back)
 * @return array ['success' => bool, 'content' => string (binary), 'filename' => string,
 *                'mime' => string, 'error' => string]
 */
function erp_edoc_parasut_fetch_document($invoice, $format = 'pdf')
{
    $none = array('success' => false, 'content' => '', 'filename' => '', 'mime' => '', 'error' => '');
    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);

    if (strtolower((string) $format) !== 'pdf') {
        return array_merge($none, array('error' => lang('Paraşüt hands back the PDF of an e-document, not its UBL.')));
    }

    $document_id = trim((string) ($invoice['parasut_edoc_id'] ?? ''));
    $kind = (string) ($invoice['edoc_kind'] ?? '');

    if (($document_id === '') || !in_array($kind, array('einvoice', 'earchive'), true)) {
        // Not read yet: asking after the status writes it down.
        $polled = erp_edoc_parasut_poll($invoice, (string) ($invoice['edoc_external_id'] ?? ''));

        if (empty($polled['success'])) {
            return array_merge($none, array('error' => (string) $polled['error']));
        }

        $row = db_item("SELECT parasut_edoc_id, edoc_kind FROM erp_invoices WHERE id = '" . (int) $invoice['id'] . "' LIMIT 1");
        $document_id = trim((string) ($row['parasut_edoc_id'] ?? ''));
        $kind = (string) ($row['edoc_kind'] ?? '');

        if (($document_id === '') || !in_array($kind, array('einvoice', 'earchive'), true)) {
            return array_merge($none, array('error' => lang('The invoice has not become an e-document at Paraşüt yet.')));
        }
    }

    $resource = ($kind === 'einvoice') ? 'e_invoices' : 'e_archives';
    $answer = erp_edoc_parasut_request('GET', '/' . $resource . '/' . rawurlencode($document_id) . '/pdf', null, $context);

    if ((string) $answer['login_error'] !== '') {
        return array_merge($none, array('error' => $answer['login_error']));
    }

    if (!erp_edoc_parasut_ok($answer)) {
        return array_merge($none, array('error' => erp_edoc_parasut_error_text($answer)));
    }

    // 204 with no body: the PDF is still being drawn (documentation: ask
    // again until the answer is not 204).
    if ((int) $answer['http_code'] === 204) {
        return array_merge($none, array('error' => lang('Paraşüt has not prepared the PDF yet; try again in a minute.')));
    }

    $url = trim((string) ($answer['body']['data']['attributes']['url'] ?? ''));

    if ((strpos($url, 'https://') !== 0) && (strpos($url, 'http://') !== 0)) {
        return array_merge($none, array('error' => lang('Paraşüt returned no address for the PDF.')));
    }

    // The signed address needs no token and must not be sent one.
    $file = erp_edoc_http('GET', $url, array('Accept: application/pdf'), null, 60);

    if (((string) $file['error'] !== '') || ((int) $file['http_code'] !== 200) || (strncmp((string) $file['raw'], '%PDF', 4) !== 0)) {
        return array_merge($none, array('error' => lang('The PDF could not be read from the address Paraşüt gave.')));
    }

    return array(
        'success' => true,
        'content' => (string) $file['raw'],
        'filename' => (string) $invoice['full_number'] . '.pdf',
        'mime' => 'application/pdf',
        'error' => '',
    );
}

/* ---------------------------------------------------------------------------
   Taking a document back
   --------------------------------------------------------------------------- */

/**
 * Can a document at Paraşüt be taken back, from the row alone. A record not
 * yet converted is deleted through the API; an e-Archive invoice is
 * cancelled through the API (Paraşüt reports the cancellation to GİB); an
 * e-Invoice that has gone is answered with a return invoice.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['ok' => bool, 'manual' => bool, 'reason' => string, 'url' => string]
 */
function erp_edoc_parasut_cancellable($invoice)
{
    $status = (string) ($invoice['edoc_status'] ?? 'none');
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $url = ($external_id !== '') ? erp_edoc_parasut_panel_url($external_id) : '';

    if (($external_id === '') || ($status === 'created')) {
        return array('ok' => true, 'manual' => false, 'url' => '',
            'reason' => ($external_id === '') ? '' : lang('The record is deleted at Paraşüt in the same click.'));
    }

    if (in_array($status, array('error', 'rejected'), true)) {
        return array('ok' => true, 'manual' => false, 'url' => $url,
            'reason' => lang('Paraşüt is asked what it holds first: a record that is not an e-document is deleted there, an e-Archive invoice is cancelled there.'));
    }

    if ((string) ($invoice['edoc_kind'] ?? '') === 'earchive') {
        return array('ok' => true, 'manual' => false, 'url' => $url,
            'reason' => lang('The e-Archive invoice is cancelled at Paraşüt through its API; Paraşüt reports the cancellation to GİB.'));
    }

    if ((string) ($invoice['edoc_kind'] ?? '') === 'einvoice') {
        return array('ok' => false, 'manual' => false, 'url' => '',
            'reason' => lang('An e-Invoice that has gone to GİB cannot be cancelled: the buyer answers it with their own return invoice (or rejects it), and that return is recorded here.'));
    }

    return array('ok' => true, 'manual' => false, 'url' => $url,
        'reason' => lang('Whether Paraşüt lets it be cancelled depends on what it became; Paraşüt is asked before anything changes.'));
}

/**
 * The invoice on Paraşüt's own screen, for the operator.
 *
 * @param string $external_id
 * @return string
 */
function erp_edoc_parasut_panel_url($external_id)
{
    $company = defined('PARASUT_COMPANY_ID') ? trim((string) PARASUT_COMPANY_ID) : '';

    if (($company === '') || (trim((string) $external_id) === '')) {
        return '';
    }

    return 'https://uygulama.parasut.com/' . rawurlencode($company) . '/satislar/' . rawurlencode((string) $external_id);
}

/**
 * Takes a document back at Paraşüt. What Paraşüt holds is read first, so
 * the decision rests on its answer and not on a row that may be out of
 * date: a record that is no e-document is deleted, an e-Archive invoice is
 * cancelled, an e-Invoice is refused.
 *
 * @param array  $invoice
 * @param string $reason
 * @return array ['success' => bool, 'error' => string, 'message' => string, 'outcome' => 'deleted'|'cancelled'|'']
 */
function erp_edoc_parasut_cancel_invoice($invoice, $reason = '')
{
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);
    $fail = function ($error) {
        return array('success' => false, 'error' => $error, 'message' => '', 'outcome' => '');
    };
    $forget = function () use ($invoice) {
        erp_query("UPDATE erp_invoices SET parasut_invoice_id = '', parasut_edoc_id = '', parasut_job_id = '', parasut_job_expires_at = 0 WHERE id = '" . (int) $invoice['id'] . "'");
    };

    if ($external_id === '') {
        return array('success' => true, 'error' => '', 'message' => '', 'outcome' => 'deleted');
    }

    $detail = erp_edoc_parasut_request('GET', '/sales_invoices/' . rawurlencode($external_id) . '?include=active_e_document', null, $context);

    if ((string) $detail['login_error'] !== '') {
        return $fail($detail['login_error']);
    }

    if ((int) $detail['http_code'] === 404) {
        $forget();
        return array('success' => true, 'error' => '', 'message' => lang('Paraşüt no longer has the record.'), 'outcome' => 'deleted');
    }

    if (!erp_edoc_parasut_ok($detail)) {
        return $fail(erp_edoc_parasut_error_text($detail));
    }

    $data = is_array($detail['body']['data'] ?? null) ? $detail['body']['data'] : array();
    $attributes = (array) ($data['attributes'] ?? array());
    $document_reference = (array) ($data['relationships']['active_e_document']['data'] ?? array());
    $document_type = (string) ($document_reference['type'] ?? '');

    if ((string) ($attributes['item_type'] ?? '') === 'cancelled') {
        return array('success' => true, 'error' => '', 'message' => lang('Paraşüt has it as cancelled.'), 'outcome' => 'cancelled');
    }

    // No e-document behind it: a plain record, deleted.
    if (trim((string) ($document_reference['id'] ?? '')) === '') {
        $deleted = erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode($external_id), null, $context);

        if ((string) $deleted['login_error'] !== '') {
            return $fail($deleted['login_error']);
        }

        if (!erp_edoc_parasut_ok($deleted)) {
            return $fail(lang(array('string' => 'Paraşüt would not delete the record: {var:1}', 'vars' => erp_edoc_parasut_error_text($deleted))));
        }

        $forget();

        return array('success' => true, 'error' => '', 'message' => lang('The record has been deleted at Paraşüt.'), 'outcome' => 'deleted');
    }

    if ($document_type === 'e_invoices') {
        return $fail(lang('The invoice has gone to GİB as an e-Invoice and cannot be cancelled; the buyer answers it with a return invoice.'));
    }

    // An e-Archive invoice: cancelled through the API, which Paraşüt then
    // reports to GİB - while GİB's window is open (cancellable_until).
    $archive_id = trim((string) ($document_reference['id'] ?? ''));
    $archive = array();

    foreach ((array) ($detail['body']['included'] ?? array()) as $item) {
        if (is_array($item) && ((string) ($item['type'] ?? '') === 'e_archives') && ((string) ($item['id'] ?? '') === $archive_id)) {
            $archive = (array) ($item['attributes'] ?? array());
        }
    }

    if (!erp_edoc_parasut_cancel_window_open($archive, time())) {
        return $fail(lang(array(
            'string' => 'The e-Archive invoice could be cancelled until {var:1}; after that it is answered with a return invoice.',
            'vars' => (string) $archive['cancellable_until'],
        )));
    }

    $cancelled = erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode($external_id) . '/cancel', null, $context);

    if ((string) $cancelled['login_error'] !== '') {
        return $fail($cancelled['login_error']);
    }

    if (!erp_edoc_parasut_ok($cancelled)) {
        return $fail(lang(array('string' => 'Paraşüt would not cancel the e-Archive invoice: {var:1}', 'vars' => erp_edoc_parasut_error_text($cancelled))));
    }

    return array('success' => true, 'error' => '', 'message' => lang('The e-Archive invoice has been cancelled at Paraşüt.'), 'outcome' => 'cancelled');
}

/* ---------------------------------------------------------------------------
   Customer and supplier cards (account sync)
   --------------------------------------------------------------------------- */

/**
 * The contact attributes Paraşüt lets be written (ContactAttributes in the
 * documentation, those not marked read-only). The balances and the dates
 * are Paraşüt's own and are never sent.
 *
 * @return string[]
 */
function erp_edoc_parasut_contact_fields()
{
    return array(
        'name', 'short_name', 'contact_type', 'account_type', 'email', 'tax_office', 'tax_number',
        'district', 'city', 'postal_code', 'country', 'address', 'phone', 'fax', 'iban',
        'is_abroad', 'archived', 'untrackable', 'invoicing_preferences',
    );
}

/**
 * Gives the request at least a minute more before PHP's time limit stops
 * it. The account screens chain many requests in one page load (up to fifty
 * pages of the list, twenty-five cards sent in one go) and Paraşüt takes
 * ten in any ten seconds, so most of such a page's time is spent waiting on
 * the rate limit; on Windows the time limit counts that wait too. The limit
 * only ever moves later, and no limit (the command line) stays none.
 *
 * @return void
 */
function erp_edoc_parasut_allow_time()
{
    $limit = (int) ini_get('max_execution_time');

    if (($limit > 0) && function_exists('set_time_limit')) {
        @set_time_limit(max(60, $limit));
    }
}

/**
 * The ISO code of the country a contact names. Paraşüt keeps the country as
 * free text beside is_abroad, and a domestic card usually names none.
 * Turkey's spellings are known here; another name is looked up in the
 * store's country list.
 *
 * @param string $name
 * @param bool   $is_abroad
 * @return string  Two letters, or '' for a foreign country that is not known
 */
function erp_edoc_parasut_country_code($name, $is_abroad)
{
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    $folded = strtr(mb_strtolower(strtr($name, array('İ' => 'i', 'I' => 'ı')), 'UTF-8'), array('ı' => 'i', 'ü' => 'u'));

    if (in_array($folded, array('turkiye', 'turkey', 'turkiye cumhuriyeti', 'tr'), true)) {
        return 'TR';
    }

    if ($name === '') {
        return $is_abroad ? '' : 'TR';
    }

    if (preg_match('/^[A-Za-z]{2}$/', $name)) {
        return strtoupper($name);
    }

    $found = strtoupper(trim((string) db_value("SELECT code FROM countries WHERE LOWER(name) = LOWER('" . escape($name) . "') LIMIT 1")));

    if (preg_match('/^[A-Z]{2}$/', $found)) {
        return $found;
    }

    return $is_abroad ? '' : 'TR';
}

/**
 * The country name a contact is written with: Türkiye for Turkey, the
 * store's name for any other country.
 *
 * @param string $code  Two letters; '' counts as Turkey, Paraşüt's home
 * @return string
 */
function erp_edoc_parasut_country_name($code)
{
    $code = strtoupper(trim((string) $code));

    if (($code === '') || ($code === 'TR')) {
        return 'Türkiye';
    }

    if (function_exists('erp_country_name')) {
        return erp_country_name($code);
    }

    $name = (string) db_value("SELECT name FROM countries WHERE code = '" . escape($code) . "' LIMIT 1");

    return ($name !== '') ? $name : $code;
}

/**
 * A contact as the card shape includes/erp/edoc/account_sync.php works
 * with. Paraşüt keeps one name for a person and a company alike, one
 * identity field (tax_number: a company's VKN, a person's TCKN) and two
 * kinds, customer and supplier. It has no card code of its own: the
 * contact's id stands in, as it does in the invoice status (poll()'s
 * party), so that a linked account's invoices go on that contact.
 *
 * @param array $contact  One data item of a contacts answer (id, attributes)
 * @return array
 */
function erp_edoc_parasut_card($contact)
{
    $contact = (array) $contact;
    $attributes = is_array($contact['attributes'] ?? null) ? $contact['attributes'] : array();
    $id = trim((string) ($contact['id'] ?? ''));
    $text = function ($key) use ($attributes) {
        return is_scalar($attributes[$key] ?? null) ? trim((string) $attributes[$key]) : '';
    };
    $updated = ($text('updated_at') !== '') ? strtotime($text('updated_at')) : false;

    return array(
        'external_id' => $id,
        'code' => $id,
        'title' => (string) preg_replace('/\s+/u', ' ', $text('name')),
        'is_person' => ($text('contact_type') === 'person'),
        // Letters are kept so that a mistyped number stays mistyped: stripped
        // to its digits, "1234567890abc" would pass for a real VKN.
        'tax_number' => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $text('tax_number'))),
        'tax_office' => $text('tax_office'),
        'email' => $text('email'),
        'phone' => $text('phone'),
        'address' => $text('address'),
        'district' => $text('district'),
        'city' => $text('city'),
        'country_code' => erp_edoc_parasut_country_code($text('country'), !empty($attributes['is_abroad'])),
        'postcode' => $text('postal_code'),
        'kind' => ($text('account_type') === 'supplier') ? 'supplier' : 'customer',
        'is_active' => empty($attributes['archived']),
        'updated_at' => ($updated !== false) ? (int) $updated : 0,
    );
}

/**
 * Reads a page of the contacts list: the cards, and how many there are in
 * all (meta.total_count, meta.total_pages).
 *
 * @param mixed $body  The decoded answer
 * @param int   $size  The page size asked for
 * @return array ['items' => cards, 'total' => int, 'pages' => int]
 */
function erp_edoc_parasut_contacts_read($body, $size)
{
    $body = is_array($body) ? $body : array();
    $items = array();

    foreach ((array) ($body['data'] ?? array()) as $contact) {
        if (is_array($contact) && (trim((string) ($contact['id'] ?? '')) !== '')) {
            $items[] = erp_edoc_parasut_card($contact);
        }
    }

    $meta = is_array($body['meta'] ?? null) ? $body['meta'] : array();
    $total = isset($meta['total_count']) ? (int) $meta['total_count'] : count($items);
    $pages = isset($meta['total_pages']) ? (int) $meta['total_pages'] : (int) ceil($total / max(1, (int) $size));

    return array('items' => $items, 'total' => max(0, $total), 'pages' => max(1, $pages));
}

/**
 * One page of the company's customer and supplier cards (GET contacts).
 * Sorted by id so that the pages do not shift under a read: the list's own
 * order is by balance, which moves while it is read.
 *
 * @param int $page  From 1
 * @return array ['success' => bool, 'error' => string, 'items' => cards, 'total' => int, 'pages' => int]
 */
function erp_edoc_parasut_accounts($page = 1)
{
    $none = array('success' => false, 'error' => '', 'items' => array(), 'total' => 0, 'pages' => 0);
    $page = max(1, (int) $page);
    $size = (int) ERP_EDOC_PARASUT_PAGE_SIZE;

    erp_edoc_parasut_allow_time();

    $result = erp_edoc_parasut_request('GET', '/contacts?sort=id&page[number]=' . $page . '&page[size]=' . $size, null, array('doc_type' => 'account'));

    if ((string) $result['login_error'] !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_parasut_ok($result) || !is_array($result['body'] ?? null)) {
        return array_merge($none, array('error' => lang(array('string' => 'Paraşüt would not list its customers and suppliers: {var:1}', 'vars' => erp_edoc_parasut_error_text($result)))));
    }

    return array('success' => true, 'error' => '') + erp_edoc_parasut_contacts_read($result['body'], $size);
}

/**
 * One card, read whole (GET contacts/{id}). 'contact' is Paraşüt's own
 * object, the base account_save() writes back.
 *
 * @param string $external_id
 * @return array ['success' => bool, 'error' => string, 'card' => array, 'contact' => array]
 */
function erp_edoc_parasut_account($external_id)
{
    $none = array('success' => false, 'error' => '', 'card' => array(), 'contact' => array());
    $external_id = trim((string) $external_id);

    if (!preg_match('/^[0-9A-Za-z\-]{1,64}$/', $external_id)) {
        return array_merge($none, array('error' => lang('The card has no Paraşüt id.')));
    }

    erp_edoc_parasut_allow_time();

    $result = erp_edoc_parasut_request('GET', '/contacts/' . rawurlencode($external_id), null, array('doc_type' => 'account'));

    if ((string) $result['login_error'] !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if ((int) $result['http_code'] === 404) {
        return array_merge($none, array('error' => lang('Paraşüt no longer has this card.')));
    }

    $contact = $result['body']['data'] ?? null;

    if (!erp_edoc_parasut_ok($result) || !is_array($contact) || (trim((string) ($contact['id'] ?? '')) === '')) {
        return array_merge($none, array('error' => lang(array('string' => 'Paraşüt would not hand over the card: {var:1}', 'vars' => erp_edoc_parasut_error_text($result)))));
    }

    return array('success' => true, 'error' => '', 'card' => erp_edoc_parasut_card($contact), 'contact' => $contact);
}

/**
 * The attributes a card is written to Paraşüt with.
 *
 * A change starts from the contact as Paraşüt keeps it, every attribute it
 * lets be written as it came, and changes only the fields named in $card:
 * nothing else of the card (its IBAN, short name, e-document accounts,
 * whether it is listed) passes through Pinegrap. A new card is written
 * with the fields that hold something.
 *
 * The short name follows the name when it mirrored it. The tax number is
 * digits on a Turkish card and as written on a foreign one. The country
 * travels as its name with is_abroad. Paraşüt knows a customer and a
 * supplier, so an account that is both is written as a customer.
 *
 * @param array      $card     Card fields to write (title, is_person, tax_number,
 *                             tax_office, email, phone, address, district, city,
 *                             country_code, postcode, kind, is_active); code and
 *                             the e-Invoice fields are not Paraşüt's and are left out
 * @param array|null $current  The contact's attributes as read; null to create
 * @return array  Attributes, or ['error' => string]
 */
function erp_edoc_parasut_card_attributes($card, $current = null)
{
    $card = (array) $card;
    $creating = ($current === null);
    $current = (array) $current;
    $attributes = array();

    foreach (erp_edoc_parasut_contact_fields() as $key) {
        // An attribute that came empty is not sent back: null is no value,
        // and an empty object decodes to an empty array, which would go out
        // as a list.
        if (!array_key_exists($key, $current) || ($current[$key] === null) || (is_array($current[$key]) && empty($current[$key]))) {
            continue;
        }

        $attributes[$key] = $current[$key];
    }

    if ($creating) {
        $attributes['account_type'] = 'customer';
        $attributes['contact_type'] = 'company';
    }

    $put = function ($key, $value) use (&$attributes, $creating) {
        if ($creating && ($value === '')) {
            return;
        }

        $attributes[$key] = $value;
    };

    if (array_key_exists('title', $card)) {
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) $card['title']));

        if ($title === '') {
            return array('error' => lang('Enter a name.'));
        }

        $short_name = is_scalar($current['short_name'] ?? null) ? trim((string) $current['short_name']) : '';

        if (($short_name !== '') && ($short_name === trim((string) ($current['name'] ?? '')))) {
            $attributes['short_name'] = mb_substr($title, 0, 255);
        }

        $attributes['name'] = mb_substr($title, 0, 255);
    }

    if (array_key_exists('is_person', $card)) {
        $attributes['contact_type'] = !empty($card['is_person']) ? 'person' : 'company';
    }

    // The country before the tax number, which is written by its rule.
    if (array_key_exists('country_code', $card)) {
        $country = strtoupper(trim((string) $card['country_code']));
        $country = preg_match('/^[A-Z]{2}$/', $country) ? $country : 'TR';
        $attributes['country'] = erp_edoc_parasut_country_name($country);
        $attributes['is_abroad'] = ($country !== 'TR');
    } else {
        $country = erp_edoc_parasut_country_code(is_scalar($current['country'] ?? null) ? (string) $current['country'] : '', !empty($current['is_abroad']));
    }

    if (array_key_exists('tax_number', $card)) {
        $number = ($country === 'TR') ? preg_replace('/\D/', '', (string) $card['tax_number']) : trim((string) $card['tax_number']);
        $put('tax_number', mb_substr((string) $number, 0, 255));
    }

    $plain = array(
        'tax_office' => 'tax_office',
        'email' => 'email',
        'phone' => 'phone',
        'address' => 'address',
        'district' => 'district',
        'city' => 'city',
        'postcode' => 'postal_code',
    );

    foreach ($plain as $field => $key) {
        if (array_key_exists($field, $card)) {
            $put($key, mb_substr(trim((string) $card[$field]), 0, ($key === 'address') ? 1000 : 255));
        }
    }

    if (array_key_exists('kind', $card)) {
        $attributes['account_type'] = ((string) $card['kind'] === 'supplier') ? 'supplier' : 'customer';
    }

    if (array_key_exists('is_active', $card)) {
        $attributes['archived'] = empty($card['is_active']);
    }

    return $attributes;
}

/**
 * Opens a card at Paraşüt ($external_id '': POST contacts) or changes one
 * (PUT contacts/{id}).
 *
 * A change is made on the card as Paraşüt keeps it: the card is read first
 * and written back whole, with only the fields in $card changed, so the
 * edit is the same whether Paraşüt replaces the attributes it is sent or
 * merges them.
 *
 * @param array  $card         Card fields to write (erp_edoc_parasut_card_attributes())
 * @param string $external_id  '' to create
 * @return array ['success' => bool, 'error' => string, 'external_id' => string]
 */
function erp_edoc_parasut_account_save($card, $external_id = '')
{
    $none = array('success' => false, 'error' => '', 'external_id' => '');
    $external_id = trim((string) $external_id);
    $current = null;

    if ($external_id !== '') {
        $read = erp_edoc_parasut_account($external_id);

        if (!$read['success']) {
            return array_merge($none, array('error' => $read['error']));
        }

        $current = is_array($read['contact']['attributes'] ?? null) ? $read['contact']['attributes'] : array();
    }

    $attributes = erp_edoc_parasut_card_attributes((array) $card, $current);

    if (isset($attributes['error'])) {
        return array_merge($none, array('error' => (string) $attributes['error']));
    }

    $data = array('type' => 'contacts', 'attributes' => $attributes);

    if ($external_id !== '') {
        $data['id'] = $external_id;
    }

    erp_edoc_parasut_allow_time();

    $result = ($external_id === '')
        ? erp_edoc_parasut_request('POST', '/contacts', array('data' => $data), array('doc_type' => 'account'))
        : erp_edoc_parasut_request('PUT', '/contacts/' . rawurlencode($external_id), array('data' => $data), array('doc_type' => 'account'));

    if ((string) $result['login_error'] !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    // A refused change is reported by the sync screen as Paraşüt's refusal of
    // it; a new card is not, so its refusal says what was refused.
    if (!erp_edoc_parasut_ok($result)) {
        return array_merge($none, array('error' => ($external_id === '')
            ? lang(array('string' => 'Paraşüt would not open the card: {var:1}', 'vars' => erp_edoc_parasut_error_text($result)))
            : erp_edoc_parasut_error_text($result)));
    }

    $id = trim((string) ($result['body']['data']['id'] ?? ''));

    if ($id === '') {
        $id = $external_id;
    }

    if ($id === '') {
        return array_merge($none, array('error' => lang('Paraşüt took the card but did not say its id. Read the list again to find it.')));
    }

    return array('success' => true, 'error' => '', 'external_id' => $id);
}
