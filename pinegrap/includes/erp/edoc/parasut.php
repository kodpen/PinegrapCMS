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
 * pages through the list and through the archived cards it leaves out,
 * account() reads one card, account_save() opens a card or changes one.
 * Balances never travel.
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

// How many pages of the contact's sales invoices of a day the search for an
// unanswered try reads (newest first) before it gives up without writing,
// and how many pages of the cards under a buyer's number the search for the
// buyer's card reads (newest first) before it opens a card.
if (!defined('ERP_EDOC_PARASUT_FIND_PAGES')) {
    define('ERP_EDOC_PARASUT_FIND_PAGES', 20);
}

// What erp_invoices.parasut_invoice_id starts with while a sales invoice
// request is out, and after one whose answer never came: Paraşüt may have
// written the record, so the next send looks for it before writing one.
// The mark is followed by the issue date and the contact id the request
// carried ('?2026-10-19:7001', erp_edoc_parasut_unsure_mark()), because
// the invoice's date and buyer can change before the next send. A real id
// is digits.
if (!defined('ERP_EDOC_PARASUT_UNSURE')) {
    define('ERP_EDOC_PARASUT_UNSURE', '?');
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
 * No fields: the credentials live in the config row, read by
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
 * Paraşüt's code for an invoice's currency: TRL for the lira (its own
 * convention, the ISO code is TRY), the ISO code for the rest it knows (USD,
 * EUR, GBP; parasut_currency_code()). Any other code is passed on as it is
 * and Paraşüt answers for it.
 *
 * @param string $currency
 * @return string
 */
function erp_edoc_parasut_currency($currency)
{
    $code = parasut_currency_code($currency);

    return ($code !== '') ? $code : strtoupper(trim((string) $currency));
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
        return array('success' => false, 'message' => lang('The Paraşüt company id is empty. Connect on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration), or enter it there.'), 'details' => array());
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
 * carrier (VUK 509). Only an e-Arşiv document carries them (the e_archives
 * request's internet_sale and shipment blocks); the e_invoices request of a
 * registered buyer has neither, so an invoice expected to go as e-Fatura is
 * not held back for them.
 *
 * @param array       $invoice  An erp_invoices row
 * @param string|null $kind     'einvoice' | 'earchive' when the document's kind
 *                              is decided; null asks erp_edoc_invoice_expected_kind()
 *                              (the invoice's kind, else the account's e-Invoice answer)
 * @return array  field name => label, empty when nothing is missing
 */
function erp_edoc_parasut_document_missing($invoice, $kind = null)
{
    $missing = array();

    if ((int) ($invoice['is_internet_sale'] ?? 0) !== 1) {
        return $missing;
    }

    if (($kind === null) && function_exists('erp_edoc_invoice_expected_kind')) {
        $kind = erp_edoc_invoice_expected_kind($invoice);
    }

    if ($kind === 'einvoice') {
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
 * Why an invoice cannot go to Paraşüt for a line below zero, or '' when every
 * line is zero or more. Paraşüt takes no negative unit price, VAT or discount
 * on a line (live: a -0.52 line was answered 422, "the unit price must be
 * equal to or greater than 0"); an order line of a product priced below zero
 * is one, and so is the share of an order discount such a line carries. The
 * first such line is named.
 *
 * @param array $lines  erp_invoice_items rows or erp_order_lines() lines
 *                      (description, unit_price, line_total, discount_amount)
 * @return string
 */
function erp_edoc_parasut_negative_line_refusal($lines)
{
    foreach ((array) $lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        if (((int) ($line['unit_price'] ?? 0) < 0) || ((int) ($line['line_total'] ?? 0) < 0)
            || ((int) ($line['discount_amount'] ?? 0) < 0)) {
            return lang(array(
                'string' => 'Line "{var:1}" has an amount below zero, and Paraşüt takes no such line: a line\'s price, discount and VAT must be zero or more. Nothing was sent to Paraşüt.',
                'vars' => (string) ($line['description'] ?? ''),
            ));
        }
    }

    return '';
}

/**
 * The attributes a Paraşüt contact (its customer card) is written with.
 *
 * Paraşüt keeps one identity field, tax_number, for the VKN of a company
 * and the TCKN of a person (the "TC Kimlik No" box of a person's card); the
 * contact_type says which. The live API takes a person's TCKN in
 * tax_number and finds the card by it again. The order screen's own
 * Paraşüt buttons open their card the same way
 * (parasut_order_contact_attributes() in includes/fn/parasut.php).
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
 * A buyer without a number carries the final consumer's, which many cards
 * share; such a card is taken only when it holds the buyer's own name,
 * address and e-mail (erp_edoc_parasut_final_consumer_card_matches()), so
 * one consumer's document is not written on another consumer's card.
 *
 * The cards under the number are read page by page, newest first, until one
 * is taken or ERP_EDOC_PARASUT_FIND_PAGES pages were read: a store holds
 * many final-consumer cards, and the buyer's own may be past the first page.
 * After that many pages without a match a card is opened, as for a buyer
 * Paraşüt does not know.
 *
 * @param array $party       erp_edoc_invoice_party() output (+ provider_code)
 * @param string $tax_number Digits
 * @param array $context     For the log
 * @param callable $request  erp_edoc_parasut_request() or a stand-in with its
 *                           arguments and answer (the tests)
 * @return array ['id' => string, 'error' => string]
 */
function erp_edoc_parasut_contact_for($party, $tax_number, $context = array(), $request = 'erp_edoc_parasut_request')
{
    $linked = trim((string) ($party['provider_code'] ?? ''));

    if ($linked !== '') {
        $read = call_user_func($request, 'GET', '/contacts/' . rawurlencode($linked), null, $context);

        if ((string) $read['login_error'] !== '') {
            return array('id' => '', 'error' => $read['login_error']);
        }

        if (erp_edoc_parasut_ok($read) && erp_edoc_parasut_linked_contact_usable($read['body']['data'] ?? null, $linked, $tax_number)) {
            return array('id' => $linked, 'error' => '');
        }
    }

    $final_consumer = ($tax_number === erp_edoc_final_consumer_tckn());
    $page = 0;

    while ($page < (int) ERP_EDOC_PARASUT_FIND_PAGES) {
        $page++;

        // Paraşüt takes ten requests in any ten seconds, so a long list is
        // mostly a wait, which PHP's time limit counts on Windows.
        if ($page === 2) {
            erp_edoc_parasut_allow_time();
        }

        // Sorted by id: the list's own order is by balance, which an invoice
        // written meanwhile moves, so a card could slip between two pages.
        // Newest first, so the cards of recent buyers are read before the cap.
        $found = call_user_func($request, 'GET', '/contacts?filter[tax_number]=' . rawurlencode($tax_number)
            . '&sort=-id&page[number]=' . $page . '&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE, null, $context);

        if ((string) $found['login_error'] !== '') {
            return array('id' => '', 'error' => $found['login_error']);
        }

        // 404: no card under the number.
        if ((int) $found['http_code'] === 404) {
            break;
        }

        if (!erp_edoc_parasut_ok($found)) {
            return array('id' => '', 'error' => lang(array('string' => 'Paraşüt would not search its contacts: {var:1}', 'vars' => erp_edoc_parasut_error_text($found))));
        }

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

            if ($final_consumer && !erp_edoc_parasut_final_consumer_card_matches($attributes, $party, $tax_number)) {
                continue;
            }

            return array('id' => $id, 'error' => '');
        }

        if ($page >= max(1, (int) ($found['body']['meta']['total_pages'] ?? 1))) {
            break;
        }
    }

    $created = call_user_func($request, 'POST', '/contacts', array('data' => array(
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
 * Whether a card found under the final consumer's number is this buyer's.
 * The sales invoice sends no address, phone or e-mail of its own: the
 * e-Arşiv is printed with the card's details and mailed to the card's
 * e-mail. So the card is taken only when its name, address, district, city
 * and e-mail are the ones a new card for the buyer would be written with
 * (outer spaces and case ignored). A card holding an e-mail the buyer did
 * not give is not taken either, as the document would be mailed there.
 *
 * @param array  $attributes  attributes of a contact Paraşüt listed
 * @param array  $party       erp_edoc_invoice_party() output
 * @param string $tax_number  The final consumer's number, digits
 * @return bool
 */
function erp_edoc_parasut_final_consumer_card_matches($attributes, $party, $tax_number)
{
    $wanted = erp_edoc_parasut_contact_attributes($party, $tax_number);

    foreach (array('name', 'address', 'district', 'city', 'email') as $field) {
        $have = mb_strtolower(trim((string) ($attributes[$field] ?? '')), 'UTF-8');
        $want = mb_strtolower(trim((string) ($wanted[$field] ?? '')), 'UTF-8');

        if ($have !== $want) {
            return false;
        }
    }

    return true;
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
 * product/service, the settings card's "Default Product/Service ID". A
 * product whose card Paraşüt did not open is refused, not put on the
 * default (erp_edoc_parasut_line_product_pick()).
 *
 * @param array $line       An erp_invoice_items row
 * @param int   $taken_out  Units of the line's product the invoice's sale already took out
 *                          of Pinegrap's count (parasut_invoice_stock_taken()): a card
 *                          opened now starts from the stock before the invoice
 * @return array ['id' => string, 'error' => string]
 */
function erp_edoc_parasut_line_product($line, $taken_out = 0)
{
    $product_id = (int) ($line['product_id'] ?? 0);
    $sync = null;

    if (($product_id > 0) && function_exists('parasut_sync_product')) {
        $token = parasut_get_token();

        if (empty($token['success'])) {
            return array('id' => '', 'error' => (string) $token['error']);
        }

        $sync = parasut_sync_product($product_id, (string) $token['token'], $taken_out);
    }

    return erp_edoc_parasut_line_product_pick($line, $sync, defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '');
}

/**
 * Which Paraşüt product a line goes on, from the answer about its own card.
 *
 * The default product/service takes only a line without a product of its
 * own: none named (shipping, a charge, free text, a rounding line) or one
 * deleted from the catalogue since. A product whose card Paraşüt did not
 * open (a refused create, a timeout, a 5xx, a second 429) is refused in
 * Paraşüt's words before the invoice is written: on the default it would
 * carry the generic service's name into the invoice and its e-document,
 * and a stock-tracked product's goods would not leave its own card. A
 * products row that could not be read ('read_failed') is refused too, as
 * the database's error rather than Paraşüt's.
 *
 * @param array      $line     An erp_invoice_items row (product_id, description)
 * @param array|null $sync     parasut_sync_product()'s answer; null when no card was asked for
 * @param string     $default  The default product/service id (PARASUT_DEFAULT_PRODUCT_ID)
 * @return array ['id' => string, 'error' => string]
 */
function erp_edoc_parasut_line_product_pick($line, $sync, $default)
{
    $own = ((int) ($line['product_id'] ?? 0) > 0) && is_array($sync) && empty($sync['missing']);

    if ($own) {
        $id = trim((string) ($sync['parasut_product_id'] ?? ''));

        if (!empty($sync['success']) && ($id !== '')) {
            return array('id' => $id, 'error' => '');
        }

        $name = trim((string) ($line['description'] ?? ''));
        $vars = array(($name !== '') ? $name : '#' . (int) $line['product_id'], parasut_refusal_words((string) ($sync['error'] ?? '')));

        // The products row could not be read: no card was asked for, so
        // there is nothing of Paraşüt's to put right or delete.
        if (!empty($sync['read_failed'])) {
            return array('id' => '', 'error' => lang(array(
                'string' => 'Line "{var:1}" could not be written to Paraşüt because of a database error: {var:2}. The invoice was not written; send again once the database error is put right.',
                'vars' => $vars,
            )));
        }

        return array('id' => '', 'error' => lang(array(
            'string' => 'Paraşüt did not open a product card for line "{var:1}": {var:2}. The invoice was not written. Put right what Paraşüt names and send again; when no answer came, first delete the card from Paraşüt\'s product list if it is there, or the next send opens a second one.',
            'vars' => $vars,
        )));
    }

    $default = trim((string) $default);

    if ($default !== '') {
        return array('id' => $default, 'error' => '');
    }

    return array('id' => '', 'error' => erp_edoc_parasut_no_product_text($line));
}

/**
 * Why an invoice cannot go to Paraşüt for want of a product, or '' when
 * every line can be written against one. A line without a Pinegrap product
 * goes on the default product/service; with none set, the invoice used to
 * be refused only after the buyer's card had been opened at Paraşüt. A line
 * with a product finds or opens its own (erp_edoc_parasut_line_product()),
 * which only asking Paraşüt tells; one Paraşüt does not open is refused
 * then, never put on the default.
 *
 * @param array       $lines    erp_invoice_items rows (product_id, description)
 * @param string|null $default  The default product/service id; null reads the setting
 * @return string
 */
function erp_edoc_parasut_product_refusal($lines, $default = null)
{
    if ($default === null) {
        $default = defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '';
    }

    if (trim((string) $default) !== '') {
        return '';
    }

    foreach ((array) $lines as $line) {
        if (is_array($line) && ((int) ($line['product_id'] ?? 0) <= 0)) {
            return erp_edoc_parasut_no_product_text($line);
        }
    }

    // A rounding line (erp_edoc_parasut_vat_shift()) has no product either.
    if (erp_edoc_parasut_vat_shift($lines)['extra'] !== array()) {
        return erp_edoc_parasut_no_product_text(array('description' => lang('Rounding difference')));
    }

    return '';
}

/**
 * The refusal of a line that has no Paraşüt product to go on.
 *
 * @param array $line  An erp_invoice_items row (description)
 * @return string
 */
function erp_edoc_parasut_no_product_text($line)
{
    return lang(array(
        'string' => 'Line "{var:1}" has no Paraşüt product to go on. Enter the id of a generic service opened in Paraşüt as the default product/service on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration).',
        'vars' => (string) ($line['description'] ?? ''),
    ));
}

/**
 * The amount before VAT that comes to the total once Paraşüt adds the VAT
 * at the rate on the whole of it, half up to the kurus; null when no amount
 * does (erp_rate_amount(): at 20% one total in six, such as 57.93, is never
 * reached). The ERP's own documents take their VAT the same way.
 *
 * @param int   $total  Kurus, VAT included
 * @param float $rate   The VAT rate in percent
 * @return int|null
 */
function erp_edoc_parasut_rate_amount($total, $rate)
{
    return erp_rate_amount($total, $rate);
}

/**
 * What the lines need at Paraşüt so that its total is the ERP's.
 *
 * Paraşüt takes the VAT of each rate on the whole amount of its lines, half
 * up to the kurus. The ERP takes it line by line, as the checkout does, and
 * an order's VAT-inclusive prices are split line by line too
 * (erp_split_gross()). Where a rate's line VAT does not come to the VAT on
 * its whole amount (1.02 + 1.02 at 20%: 0.20 + 0.20 against 0.41 on 2.04),
 * the rate's amount at Paraşüt moves by the kurus that bring amount and VAT
 * to the ERP's total for that rate (2.03 + 0.41 = 2.44). The total stays the
 * ERP's; the rate's amount and VAT at Paraşüt are that many kurus off the
 * ERP's, the one up and the other down.
 *
 * Down, the kurus come off the rate's largest line as discount. Up, they
 * come back off a discount the rate's lines carry, else onto the price of
 * its largest single unit, else onto a rounding line of their own at the
 * rate (written after every line of the invoice).
 *
 * Some totals no amount reaches at a rate (erp_edoc_parasut_rate_amount());
 * such a rate is refused with the totals on either side. A rate with
 * withholding is left as it is, as is one whose VAT is further from its
 * amount's than line roundings take it (two kurus a line): the total check
 * then says what differs.
 *
 * @param array $lines  erp_invoice_items rows, in the invoice's order
 * @return array ['lines' => [index => ['discount' => kurus more, 'unit_price' => kurus more]],
 *                'extra' => [['kurus' => int, 'rate' => float]],
 *                'refusal' => [] or ['rate' => float, 'total' => int, 'below' => int, 'above' => int]]
 */
function erp_edoc_parasut_vat_shift($lines)
{
    $out = array('lines' => array(), 'extra' => array(), 'refusal' => array());
    $groups = array();

    foreach (array_values((array) $lines) as $index => $line) {
        $rate = is_array($line) ? round((float) ($line['tax_rate'] ?? 0), 3) : 0.0;

        if ($rate > 0) {
            $groups[number_format($rate, 3, '.', '')][$index] = $line;
        }
    }

    foreach ($groups as $key => $group) {
        $rate = (float) $key;
        $base = 0;
        $vat = 0;
        $withheld = false;

        foreach ($group as $line) {
            $base += (int) ($line['line_total'] ?? 0) - (int) ($line['discount_amount'] ?? 0);
            $vat += (int) ($line['tax_total'] ?? 0) - (int) ($line['tax2_amount'] ?? 0);
            $withheld = $withheld || ((float) ($line['withholding_rate'] ?? 0) > 0);
        }

        $gap = $vat - erp_apply_rate($base, $rate);

        if ($withheld || ($base <= 0) || ($gap === 0) || (abs($gap) > 2 * count($group))) {
            continue;
        }

        $total = $base + $vat;
        $amount = erp_edoc_parasut_rate_amount($total, $rate);

        if ($amount === null) {
            $below = $total - 1;
            $above = $total + 1;

            while (($below > 0) && (erp_edoc_parasut_rate_amount($below, $rate) === null)) {
                $below--;
            }

            while (($above < $total + 100) && (erp_edoc_parasut_rate_amount($above, $rate) === null)) {
                $above++;
            }

            $out['refusal'] = array('rate' => $rate, 'total' => $total, 'below' => $below, 'above' => $above);

            return $out;
        }

        // The rate's largest line, its largest single unit, and its largest
        // line whose discount can give the kurus back; the first of equals.
        $shift = $amount - $base;
        $bases = array();
        $largest = null;
        $single = null;
        $discounted = null;

        foreach ($group as $index => $line) {
            $discount = (int) ($line['discount_amount'] ?? 0);
            $bases[$index] = (int) ($line['line_total'] ?? 0) - $discount;

            if (($largest === null) || ($bases[$index] > $bases[$largest])) {
                $largest = $index;
            }

            if (((float) ($line['quantity'] ?? 0) == 1)
                && (($single === null) || ($bases[$index] > $bases[$single]))) {
                $single = $index;
            }

            if (($shift > 0) && ($discount >= $shift)
                && (($discounted === null) || ($bases[$index] > $bases[$discounted]))) {
                $discounted = $index;
            }
        }

        if ($shift < 0) {
            if ($bases[$largest] + $shift >= 0) {
                $out['lines'][$largest] = array('discount' => -$shift, 'unit_price' => 0);
            }
        } elseif ($discounted !== null) {
            $out['lines'][$discounted] = array('discount' => -$shift, 'unit_price' => 0);
        } elseif ($single !== null) {
            $out['lines'][$single] = array('discount' => 0, 'unit_price' => $shift);
        } else {
            $out['extra'][] = array('kurus' => $shift, 'rate' => $rate);
        }
    }

    return $out;
}

/**
 * Why an invoice cannot go to Paraşüt with its total, or '': a rate whose
 * lines come to a total no amount reaches with Paraşüt's VAT
 * (erp_edoc_parasut_vat_shift()).
 *
 * @param array  $lines     erp_invoice_items rows
 * @param string $currency  The invoice's currency
 * @return string
 */
function erp_edoc_parasut_total_refusal($lines, $currency)
{
    $refusal = erp_edoc_parasut_vat_shift($lines)['refusal'];

    if ($refusal === array()) {
        return '';
    }

    return lang(array(
        'string' => 'The lines at {var:1} VAT come to {var:2} with VAT, a total Paraşüt cannot reach: it takes the VAT on their whole amount, and no amount with that VAT comes to it (the nearest are {var:3} and {var:4}). Nothing was sent to Paraşüt.',
        'vars' => array(
            erp_percent_text($refusal['rate']),
            erp_money_out_currency($refusal['total'], $currency),
            erp_money_out_currency($refusal['below'], $currency),
            erp_money_out_currency($refusal['above'], $currency),
        ),
    ));
}

/**
 * The outgoing shipment document an order's sales invoice names: the one the
 * order screen's E-Shipment wrote at Paraşüt (orders.parasut_shipment_id),
 * typically for an order shipped before the store switched the ERP's
 * Paraşüt provider on. A real record id is digits, kept as a string (ids
 * outgrow a 32-bit integer); an empty column, anything else in it and a
 * typed invoice name none.
 *
 * @param array $invoice  An erp_invoices row; an order's invoice carries
 *                        order_shipment_id (orders.parasut_shipment_id)
 * @return string  The shipment document's id, '' for none
 */
function erp_edoc_parasut_order_shipment_id($invoice)
{
    if ((int) ($invoice['order_id'] ?? 0) <= 0) {
        return '';
    }

    $id = trim((string) ($invoice['order_shipment_id'] ?? ''));

    return preg_match('/^[1-9][0-9]*$/', $id) ? $id : '';
}

/**
 * The Paraşüt ids an invoice's send takes from what Pinegrap keeps rather
 * than asks Paraşüt for, as parasut_send_once_more() reads them: its lines'
 * product cards (parasut_kept_product_cards()) and the order's shipment
 * document (erp_edoc_parasut_order_shipment_id()). The buyer card is not
 * among them: erp_edoc_parasut_contact_for() reads it first.
 *
 * @param array      $invoice        An erp_invoices row (order_id, order_shipment_id)
 * @param array|null $product_cards  The cards the lines' products keep
 * @return array ['Product' => ids, 'ShipmentDocument' => ids]
 */
function erp_edoc_parasut_kept_ids($invoice, $product_cards)
{
    $cards = array();

    foreach ((array) $product_cards as $card) {
        $card = trim((string) $card);

        if ($card !== '') {
            $cards[] = $card;
        }
    }

    $shipment_id = erp_edoc_parasut_order_shipment_id($invoice);

    return array(
        'Product' => $cards,
        'ShipmentDocument' => ($shipment_id !== '') ? array($shipment_id) : array(),
    );
}

/**
 * What the send does about the sales invoice the order screen's legacy
 * Create Invoice wrote at Paraşüt for the same order (orders.parasut_invoice_id),
 * typically before the store switched the ERP's Paraşüt provider on. Sending
 * the ERP's invoice beside it would put the sale into Paraşüt twice: two
 * sales invoices, two e-documents at GİB, the receivable doubled.
 *
 * The unsure mark of a create that got no answer
 * (parasut_order_invoice_unsure()) is no record to read: Paraşüt may hold
 * the invoice, so the send refuses before any request, naming the way out
 * through the order screen's Create Invoice. A record id is read
 * and judged as the order screen's buttons judge it
 * (parasut_stored_record_state()): one Paraşüt holds refuses, naming it
 * and where its e-document is issued if it never went through (the legacy
 * create kept the id of such a record as well), or, when its total is not
 * the invoice's, to delete it there and not issue it; only a 404 naming it
 * as not found ("Record was not found", SalesInvoice-<id>) is gone, its id
 * for the caller to clear; any other answer (no connection, no token, the
 * 401 of a company number the token cannot reach, a 404 with no route)
 * refuses with Paraşüt's words.
 *
 * @param string   $value    orders.parasut_invoice_id
 * @param callable $get      function (string $path): the erp_edoc_parasut_request()
 *                           answer of GET $path under the company prefix
 * @param array    $invoice  The erp_invoices row being sent (grand_total); without it
 *                           the record's total is not compared
 * @return array ['state' => 'none' | 'unsure' | 'kept' | 'gone' | 'unknown',
 *                'id' => string (the record read), 'refusal' => string ('' unless the
 *                send stops), 'notice' => string ('' unless gone)]
 */
function erp_edoc_parasut_legacy_invoice_check($value, $get, $invoice = array())
{
    $value = trim((string) $value);
    $check = array('state' => 'none', 'id' => '', 'refusal' => '', 'notice' => '');

    if ($value === '') {
        return $check;
    }

    // Only Create Invoice's own search settles the mark (it finishes the
    // record it finds, or writes one when none is there), and the order
    // screen draws that button only while no provider is on.
    if (parasut_order_invoice_unsure($value)) {
        $check['state'] = 'unsure';
        $check['refusal'] = lang('The order screen\'s Create Invoice got no answer from Paraşüt for this order, so Paraşüt may already hold its sales invoice. This invoice was not sent there, so that the sale is not invoiced twice. While the provider is on, nothing settles that try: set the e-Document Provider to Off in the E-Invoice settings, press Create Invoice on the order screen (it looks for that sales invoice at Paraşüt first and writes one only if none is there), then set the provider back to Paraşüt and send this invoice again. It goes only if the order screen did not invoice the sale; otherwise keep it as a PDF document.');

        return $check;
    }

    $check['id'] = $value;
    $read = (array) call_user_func($get, '/sales_invoices/' . rawurlencode($value));

    // No connection or no token says nothing about the record, whatever
    // code came with it.
    $transport = (trim((string) ($read['error'] ?? '')) !== '') || (trim((string) ($read['login_error'] ?? '')) !== '');
    $check['state'] = $transport ? 'unknown' : parasut_stored_record_state($read, 'sales_invoices');

    // The order's invoice comes to the order's total, the figure Create
    // Invoice checked its record against; a record kept with another total
    // is one Paraşüt would not delete after that check, never to be issued.
    $attributes = (array) ($read['body']['data']['attributes'] ?? array());
    $other_total = isset($invoice['grand_total']) && (erp_edoc_parasut_totals($attributes) !== array())
        && !erp_edoc_parasut_total_check($attributes, $invoice)['ok'];

    if (($check['state'] === 'kept') && $other_total) {
        $check['refusal'] = lang(array(
            'string' => 'This order has a sales invoice at Paraşüt from the order screen (ID: {var:1}) whose total is not this invoice\'s: the order screen keeps such a record only when Paraşüt would not delete it after its total check, and its e-document must not be issued. This invoice was not sent there, so that the sale is not invoiced twice: delete that record on Paraşüt\'s screen, then send again.',
            'vars' => array($value),
        ));
    } elseif ($check['state'] === 'kept') {
        $check['refusal'] = lang(array(
            'string' => 'This order was already invoiced at Paraşüt from the order screen (sales invoice ID: {var:1}), so this invoice was not sent there: the same sale would be invoiced twice. Keep this invoice as a PDF document; if that record\'s e-document did not go through, issue it from that invoice in Paraşüt. Or delete that record on Paraşüt\'s screen first and send again.',
            'vars' => array($value),
        ));
    } elseif ($check['state'] === 'gone') {
        $check['notice'] = lang(array(
            'string' => 'The sales invoice the order screen created at Paraşüt for this order (ID: {var:1}) is no longer there: its id was removed from the order.',
            'vars' => array($value),
        ));
    } else {
        $check['state'] = 'unknown';
        $check['refusal'] = lang(array(
            'string' => 'This order has a sales invoice at Paraşüt from the order screen (ID: {var:1}), and Paraşüt could not be asked whether it is still there: {var:2}. This invoice was not sent; try again in a minute.',
            'vars' => array($value, parasut_refusal_words(erp_edoc_parasut_error_text($read))),
        ));
    }

    return $check;
}

/**
 * The sales_invoices body for an invoice, field for field from the
 * documentation. Unit prices are VAT-exclusive; the discount travels as the
 * line's rate when that rate is its only discount, as its amount otherwise.
 * Where Paraşüt's VAT on a rate's whole amount is not the ERP's line VAT, the
 * rate's amount moves by a kurus or so to keep the ERP's total
 * (erp_edoc_parasut_vat_shift()); a total it cannot keep is refused.
 *
 * An order that already has its outgoing shipment document at Paraşüt
 * (erp_edoc_parasut_order_shipment_id()) is invoiced without shipment,
 * naming that document; any other invoice carries no shipment field and
 * Paraşüt keeps its default, the shipment included.
 *
 * @param array  $invoice     An erp_invoices row; an order's invoice also carries
 *                            order_number, order_date (Y-m-d) and
 *                            order_shipment_id (orders.parasut_shipment_id); a caller
 *                            that knows what the sale took out of stock gives
 *                            stock_taken (parasut_invoice_stock_taken())
 * @param array  $lines
 * @param string $contact_id  The Paraşüt contact
 * @return array  The payload, or ['error' => string]
 */
function erp_edoc_parasut_invoice_payload($invoice, $lines, $contact_id)
{
    $details = array();
    $lines = array_values((array) $lines);
    $shift = erp_edoc_parasut_vat_shift($lines);

    if ($shift['refusal'] !== array()) {
        return array('error' => erp_edoc_parasut_total_refusal($lines, (string) ($invoice['currency'] ?? '')));
    }

    // What the invoice's sale already took out of Pinegrap's count, per
    // product: a card a line opens starts from the stock before the invoice.
    $taken = parasut_invoice_stock_taken($invoice, $lines);

    foreach ($lines as $index => $line) {
        $product = erp_edoc_parasut_line_product($line, (int) ($taken[(int) ($line['product_id'] ?? 0)] ?? 0));

        if ($product['error'] !== '') {
            return array('error' => $product['error']);
        }

        // A campaign discount and a typed one apply one after the other
        // (erp_manual_lines_build()), which no single rate says: such a line
        // goes with the amount the two came to (discount_amount holds the
        // sum). The typed rate alone, with no campaign, goes as the rate.
        $discount_rate = (float) ($line['discount_rate'] ?? 0);
        $as_rate = ($discount_rate > 0) && ((float) ($line['offer_discount_rate'] ?? 0) <= 0);
        $attributes = array(
            'quantity' => (float) $line['quantity'],
            'unit_price' => erp_edoc_parasut_money((int) $line['unit_price']),
            'vat_rate' => (float) $line['tax_rate'],
            'description' => mb_substr(trim((string) $line['description']), 0, 255),
            'discount_type' => $as_rate ? 'percentage' : 'amount',
            'discount_value' => $as_rate ? $discount_rate : erp_edoc_parasut_money((int) ($line['discount_amount'] ?? 0)),
        );

        // The kurus this line carries for its rate's VAT
        // (erp_edoc_parasut_vat_shift()). Its discount goes as the amount: a
        // rate on a changed price would not move the line by the kurus.
        if (isset($shift['lines'][$index])) {
            $moved = $shift['lines'][$index];
            $attributes['unit_price'] = erp_edoc_parasut_money((int) $line['unit_price'] + (int) $moved['unit_price']);
            $attributes['discount_type'] = 'amount';
            $attributes['discount_value'] = erp_edoc_parasut_money((int) ($line['discount_amount'] ?? 0) + (int) $moved['discount']);
        }

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

    // A rounding line of the rate's moved kurus, after every line of the
    // invoice, so Paraşüt's lines up to there are the ERP's in order
    // (erp_edoc_parasut_detail_ids()).
    foreach ($shift['extra'] as $extra) {
        $description = lang('Rounding difference');
        $product = erp_edoc_parasut_line_product(array('product_id' => 0, 'description' => $description));

        if ($product['error'] !== '') {
            return array('error' => $product['error']);
        }

        $details[] = array(
            'type' => 'sales_invoice_details',
            'attributes' => array(
                'quantity' => 1.0,
                'unit_price' => erp_edoc_parasut_money((int) $extra['kurus']),
                'vat_rate' => (float) $extra['rate'],
                'description' => $description,
                'discount_type' => 'amount',
                'discount_value' => 0.0,
            ),
            'relationships' => array(
                'product' => array('data' => array('type' => 'products', 'id' => $product['id'])),
            ),
        );
    }

    // The order as its buyer knows it: the number on the receipt and in the
    // account, not the row id behind it.
    $order_number = ((int) ($invoice['order_id'] ?? 0) > 0) ? trim((string) ($invoice['order_number'] ?? '')) : '';

    $currency = strtoupper(trim((string) $invoice['currency']));
    $attributes = array(
        'item_type' => 'invoice',
        'description' => erp_edoc_parasut_invoice_description($invoice),
        'issue_date' => (string) $invoice['issue_date'],
        'due_date' => (((string) $invoice['due_date'] !== '0000-00-00') && ((string) $invoice['due_date'] !== '')) ? (string) $invoice['due_date'] : (string) $invoice['issue_date'],
        'currency' => erp_edoc_parasut_currency($currency),
    );

    // The order number and date go together or not at all: Paraşüt refuses
    // to turn an invoice into an e-document when only one of them is set.
    $order_date = trim((string) ($invoice['order_date'] ?? ''));

    if (($order_number !== '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_date)) {
        $attributes['order_no'] = $order_number;
        $attributes['order_date'] = $order_date;
    }

    if (($currency !== '') && ($currency !== 'TRY') && ((float) ($invoice['exchange_rate'] ?? 0) > 0)) {
        $attributes['exchange_rate'] = (float) $invoice['exchange_rate'];
    }

    if (trim((string) ($invoice['notes'] ?? '')) !== '') {
        $attributes['invoice_note'] = mb_substr(trim((string) $invoice['notes']), 0, 1000);
    }

    $relationships = array(
        'contact' => array('data' => array('type' => 'contacts', 'id' => (string) $contact_id)),
        'details' => array('data' => $details),
    );

    // A sales invoice Paraşüt keeps with the shipment included, its default,
    // takes its goods out of stock itself; beside the order's shipment
    // document that counts them out twice. Without shipment and naming the
    // document it moves no stock, and both records name each other (live,
    // 201). The link is made here or never: a shipment document PUT with
    // relationships.invoices answers 200 and links nothing. A document that
    // no longer exists answers 404 and nothing is written.
    $shipment_id = erp_edoc_parasut_order_shipment_id($invoice);

    if ($shipment_id !== '') {
        $attributes['shipment_included'] = false;
        $relationships['shipment_documents'] = array('data' => array(array('type' => 'shipment_documents', 'id' => $shipment_id)));
    }

    return array('data' => array(
        'type' => 'sales_invoices',
        'attributes' => $attributes,
        'relationships' => $relationships,
    ));
}

/**
 * The description a sales invoice carries at Paraşüt: the ERP number, and
 * for an order's invoice the order's number (orders.order_number, the one on
 * the receipt). The search for the record of a send that got no answer
 * matches by it (erp_edoc_parasut_written_matches()).
 *
 * @param array $invoice  An erp_invoices row; an order's invoice also carries order_number
 * @return string
 */
function erp_edoc_parasut_invoice_description($invoice)
{
    $order_number = ((int) ($invoice['order_id'] ?? 0) > 0) ? trim((string) ($invoice['order_number'] ?? '')) : '';

    return mb_substr(trim((string) $invoice['full_number'] . (($order_number !== '') ? ' / ' . lang(array('string' => 'Order #{var:1}', 'vars' => $order_number)) : '')), 0, 255);
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
 * How many lines the sales invoice of these ERP lines has at Paraşüt: one
 * for each ERP line, then the rounding lines erp_edoc_parasut_invoice_payload()
 * writes after them (erp_edoc_parasut_vat_shift()). The ERP lines keep the
 * first line ids in their order.
 *
 * @param array $lines  erp_invoice_items rows, in the invoice's order
 * @return int
 */
function erp_edoc_parasut_detail_count($lines)
{
    $lines = array_values((array) $lines);

    return count($lines) + count(erp_edoc_parasut_vat_shift($lines)['extra']);
}

/**
 * Whether a refused or unanswered create leaves it open that Paraşüt wrote
 * the record: no answer at all (a timeout, a dropped connection), a 5xx
 * (a gateway that gave up while the server went on), or a success that
 * names no record. A 4xx is Paraşüt's refusal of the request, and a 429 is
 * refused unprocessed, so nothing was written.
 *
 * @param array $result  erp_edoc_parasut_request() answer
 * @return bool
 */
function erp_edoc_parasut_answer_unsure($result)
{
    $code = (int) ($result['http_code'] ?? 0);

    if (($code === 0) || ((string) ($result['error'] ?? '') !== '')) {
        return true;
    }

    if ($code >= 500) {
        return true;
    }

    return ($code >= 200) && ($code <= 299);
}

/**
 * The value parasut_invoice_id takes before a create goes: the unsure mark
 * with the issue date and the contact the request carries. A part that is
 * malformed, or a contact id that would not fit the column (VARCHAR(32)),
 * is left out rather than cut, so the search never asks for a wrong one.
 *
 * @param string $date        YYYY-MM-DD
 * @param string $contact_id  Paraşüt's contact id
 * @return string
 */
function erp_edoc_parasut_unsure_mark($date, $contact_id)
{
    $date = (string) $date;
    $contact_id = (string) $contact_id;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }

    $mark = ERP_EDOC_PARASUT_UNSURE . $date . ':';

    if (preg_match('/^\d+$/', $contact_id) && (strlen($mark . $contact_id) <= 32)) {
        $mark .= $contact_id;
    }

    return $mark;
}

/**
 * Reads parasut_invoice_id: null when it holds no unsure mark (empty, or a
 * real id), otherwise the date and contact the unanswered request carried.
 * A bare '?' (written before the mark carried them) gives both empty, and
 * the caller then searches with the invoice's current ones.
 *
 * @param string|null $value
 * @return array|null ['date' => string, 'contact_id' => string]
 */
function erp_edoc_parasut_unsure_read($value)
{
    $value = (string) $value;

    if (($value === '') || (strpos($value, ERP_EDOC_PARASUT_UNSURE) !== 0)) {
        return null;
    }

    $parts = explode(':', substr($value, strlen(ERP_EDOC_PARASUT_UNSURE)), 2);
    $date = (string) $parts[0];
    $contact_id = (string) ($parts[1] ?? '');

    return array(
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
        'contact_id' => preg_match('/^\d+$/', $contact_id) ? $contact_id : '',
    );
}

/**
 * Whether a search that found nothing has to stop short of "never written":
 * Paraşüt has pages of the contact's invoices of that day that were not
 * read, and the record may be on one of them.
 *
 * @param array $records     What the pages read matched
 * @param int   $read_pages  The last page read
 * @param int   $total_pages What Paraşüt reported (meta.total_pages)
 * @return bool
 */
function erp_edoc_parasut_search_incomplete($records, $read_pages, $total_pages)
{
    return empty($records) && ((int) $read_pages < (int) $total_pages);
}

/**
 * Why a row may not be handed to GİB through Paraşüt: only a draft waiting
 * there ('created') is converted. An 'error' row can still carry a record
 * whose total is not the ERP's (one Paraşüt would not delete, or one poll
 * found to differ); a rejected or sent one is already a document.
 *
 * @param array $invoice  An erp_invoices row
 * @return string  '' when it may be submitted
 */
function erp_edoc_parasut_submit_refusal($invoice)
{
    if ((string) ($invoice['edoc_status'] ?? '') === 'created') {
        return '';
    }

    return lang('Only a draft waiting at the provider is handed to GİB; ask after its status first.');
}

/**
 * The sales invoices in a list answer that are this invoice's record: the
 * same description (the ERP number, with the order's), the same issue date
 * and the same contact, not cancelled. A record that is already an
 * e-document, and the row's own earlier record ($skip_id: the issued one of
 * a rejected invoice sent again), are not an unanswered try's.
 *
 * @param array  $body        The decoded answer of GET sales_invoices
 * @param array  $payload     The request erp_edoc_parasut_invoice_payload() built
 * @param string $contact_id  '' takes any contact (a mark that carries none)
 * @param string $skip_id     A record id that is never a match
 * @return array  id => attributes
 */
function erp_edoc_parasut_written_matches($body, $payload, $contact_id, $skip_id = '')
{
    $wanted = (array) ($payload['data']['attributes'] ?? array());
    $description = trim((string) ($wanted['description'] ?? ''));
    $date = (string) ($wanted['issue_date'] ?? '');
    $skip_id = trim((string) $skip_id);
    $matches = array();

    if ($description === '') {
        return $matches;
    }

    foreach ((array) ($body['data'] ?? array()) as $item) {
        if (!is_array($item) || (trim((string) ($item['id'] ?? '')) === '')) {
            continue;
        }

        if ((($skip_id !== '') && (trim((string) $item['id']) === $skip_id))
            || (trim((string) ($item['relationships']['active_e_document']['data']['id'] ?? '')) !== '')) {
            continue;
        }

        $attributes = (array) ($item['attributes'] ?? array());
        $contact = trim((string) ($item['relationships']['contact']['data']['id'] ?? ''));

        if ((trim((string) ($attributes['description'] ?? '')) !== $description)
            || (substr((string) ($attributes['issue_date'] ?? ''), 0, 10) !== $date)
            || ((string) ($attributes['item_type'] ?? '') === 'cancelled')
            || (($contact !== '') && ((string) $contact_id !== '') && ($contact !== (string) $contact_id))) {
            continue;
        }

        $matches[trim((string) $item['id'])] = $attributes;
    }

    return $matches;
}

/**
 * The records in a list answer that erp_edoc_parasut_written_matches() would
 * take for this invoice's but for the e-document Paraşüt holds for them (one
 * converted on Paraşüt's screen after a send that got no answer): id => the
 * e-document's id. Such a record is not deleted through the API.
 *
 * @param array  $body        The decoded answer of GET sales_invoices (include=active_e_document)
 * @param array  $payload     Description and issue date, as for erp_edoc_parasut_written_matches()
 * @param string $contact_id  '' takes any contact
 * @return array  id => e-document id
 */
function erp_edoc_parasut_written_documents($body, $payload, $contact_id)
{
    $documents = array();

    foreach ((array) ($body['data'] ?? array()) as $item) {
        $edoc_id = is_array($item) ? trim((string) ($item['relationships']['active_e_document']['data']['id'] ?? '')) : '';

        if ($edoc_id === '') {
            continue;
        }

        unset($item['relationships']['active_e_document']);

        foreach (array_keys(erp_edoc_parasut_written_matches(array('data' => array($item)), $payload, $contact_id)) as $id) {
            $documents[$id] = $edoc_id;
        }
    }

    return $documents;
}

/**
 * Looks at Paraşüt for the record an earlier try of this invoice may have
 * written: the contact's sales invoices of the issue date, newest first
 * (the unanswered try is among the latest), matched by
 * erp_edoc_parasut_written_matches(). The final consumer's card is shared,
 * so a day can hold many; when pages are left unread and nothing matched,
 * the search refuses rather than call the invoice never written.
 *
 * @param array  $payload     The request the unanswered try carried (its issue date)
 * @param string $contact_id  The contact that request carried
 * @param array  $context     For the log
 * @param string $skip_id     The row's own earlier record, never a match
 * @return array ['records' => id => attributes, 'error' => string]
 */
function erp_edoc_parasut_find_written($payload, $contact_id, $context, $skip_id = '')
{
    $records = array();
    $date = (string) ($payload['data']['attributes']['issue_date'] ?? '');
    $page = 0;
    $total_pages = 1;

    while ($page < (int) ERP_EDOC_PARASUT_FIND_PAGES) {
        $page++;
        $list = erp_edoc_parasut_request('GET', '/sales_invoices?filter[contact_id]=' . rawurlencode((string) $contact_id)
            . '&filter[issue_date]=' . rawurlencode($date) . '&sort=-id&include=active_e_document'
            . '&page[number]=' . $page . '&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE, null, $context);

        if (((string) $list['login_error'] !== '') || !erp_edoc_parasut_ok($list)) {
            return array('records' => array(), 'error' => lang(array(
                'string' => 'The last try got no answer from Paraşüt, and Paraşüt could not be asked whether it kept the invoice: {var:1}. Nothing was sent; try again in a minute.',
                'vars' => erp_edoc_parasut_error_text($list),
            )));
        }

        $records += erp_edoc_parasut_written_matches($list['body'], $payload, $contact_id, $skip_id);
        $total_pages = max(1, (int) ($list['body']['meta']['total_pages'] ?? 1));

        if ($page >= $total_pages) {
            break;
        }
    }

    if (erp_edoc_parasut_search_incomplete($records, $page, $total_pages)) {
        return array('records' => array(), 'error' => lang('The last try got no answer from Paraşüt, and the buyer has more invoices on that day than can be looked through. Nothing was sent; look for the record on Paraşüt\'s screen.'));
    }

    return array('records' => $records, 'error' => '');
}

/**
 * Writes the invoice at Paraşüt as a sales invoice and checks the total it
 * computed. Answers 'created': the record is not an e-document until
 * submit_invoice() converts it (the service's autosend does both).
 *
 * A try whose answer never came is followed up on the next send: the record
 * is looked for and taken over instead of written twice
 * (ERP_EDOC_PARASUT_UNSURE). A record whose total is not the ERP's and that
 * Paraşüt would not delete is named in the refusal ('external_id'), so the
 * row keeps it.
 *
 * A product card or the order's shipment document whose id Pinegrap kept
 * and that Paraşüt answers it no longer holds is forgotten and the invoice
 * sent once more (parasut_send_once_more()); 'message' says so.
 *
 * An order the order screen's Create Invoice already invoiced at Paraşüt
 * is refused first, before the invoice's own checks and any write there
 * (erp_edoc_parasut_legacy_invoice_check()); the id of such a record
 * deleted at Paraşüt since is cleared once the invoice's own refusals have
 * passed, and from then on the refusal or 'message' says so.
 *
 * @param array $options  'party' (erp_edoc_invoice_party() output) when the caller has it
 * @return array ['success' => bool, 'external_id' => string, 'gib_uuid' => string,
 *                'gib_number' => string, 'status' => 'created', 'error' => string,
 *                'message' => string (on success: what was forgotten on the way, else '')]
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

    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);

    // The order's number and day (orders.order_date is a UNIX timestamp; the
    // document wants the day), the shipment document the order screen's
    // E-Shipment wrote for it (erp_edoc_parasut_order_shipment_id()) and the
    // sales invoice its Create Invoice wrote (orders.parasut_invoice_id).
    if (((int) ($invoice['order_id'] ?? 0) > 0)
        && (!isset($invoice['order_date']) || !isset($invoice['order_number']) || !isset($invoice['order_shipment_id']) || !isset($invoice['order_parasut_invoice_id']))) {
        $order = db_item("SELECT order_number, order_date, parasut_shipment_id, parasut_invoice_id FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1");

        if (!isset($invoice['order_parasut_invoice_id'])) {
            $invoice['order_parasut_invoice_id'] = is_array($order) ? trim((string) $order['parasut_invoice_id']) : '';
        }

        if (!isset($invoice['order_number'])) {
            $invoice['order_number'] = is_array($order) ? trim((string) $order['order_number']) : '';
        }

        if (!isset($invoice['order_date'])) {
            $stamp = is_array($order) ? (int) $order['order_date'] : 0;
            $invoice['order_date'] = ($stamp > 0) ? date('Y-m-d', $stamp) : '';
        }

        if (!isset($invoice['order_shipment_id'])) {
            $invoice['order_shipment_id'] = is_array($order) ? trim((string) $order['parasut_shipment_id']) : '';
        }
    }

    // An order the order screen's Create Invoice already invoiced at Paraşüt
    // is not invoiced there a second time. Asked before the invoice's own
    // checks, so the operator is not first sent to fix a line or the account
    // card of an invoice that cannot go anyway, and before the buyer's card
    // is looked for or opened, so a refused send writes nothing at Paraşüt.
    // A record deleted there since is no invoice of the sale: its id is
    // cleared once the invoice's own refusals have passed (a refused send
    // keeps it, and the next send reads it again).
    $legacy_notice = '';
    $legacy_gone = '';

    if ((int) ($invoice['order_id'] ?? 0) > 0) {
        $legacy = erp_edoc_parasut_legacy_invoice_check((string) ($invoice['order_parasut_invoice_id'] ?? ''), function ($path) use ($context) {
            return erp_edoc_parasut_request('GET', $path, null, $context);
        }, $invoice);

        if ($legacy['refusal'] !== '') {
            return $fail($legacy['refusal']);
        }

        if ($legacy['state'] === 'gone') {
            $legacy_gone = (string) $legacy['id'];
        }
    }

    // Refused before anything is written at Paraşüt, so that the buyer's
    // card is not opened there for an invoice that cannot follow: a rate it
    // does not take, a line below zero, a total its VAT cannot come to, a
    // line with no product to go on.
    $rate_refusal = erp_edoc_parasut_vat_rate_refusal($lines);

    if ($rate_refusal !== '') {
        return $fail($rate_refusal);
    }

    $negative_refusal = erp_edoc_parasut_negative_line_refusal($lines);

    if ($negative_refusal !== '') {
        return $fail($negative_refusal);
    }

    $total_refusal = erp_edoc_parasut_total_refusal($lines, (string) ($invoice['currency'] ?? ''));

    if ($total_refusal !== '') {
        return $fail($total_refusal);
    }

    $product_refusal = erp_edoc_parasut_product_refusal($lines);

    if ($product_refusal !== '') {
        return $fail($product_refusal);
    }

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

    // What the send takes from what Pinegrap keeps rather than asks Paraşüt
    // for, noted before the body is built (a card the build opens is not a
    // kept one): its lines' product cards and the order's shipment document.
    $kept = erp_edoc_parasut_kept_ids($invoice, parasut_kept_product_cards(array_column($lines, 'product_id')));
    $payload = erp_edoc_parasut_invoice_payload($invoice, $lines, $contact['id']);

    if (isset($payload['error'])) {
        return $fail($payload['error']);
    }

    // Every local refusal has passed and the invoice is about to be written:
    // the order's deleted legacy record is cleared, only while the order
    // still holds it, and every outcome from here says so.
    if ($legacy_gone !== '') {
        _parasut_order_forget_record((int) $invoice['order_id'], 'parasut_invoice_id', $legacy_gone);
        $legacy_notice = $legacy['notice'];
        $fail = function ($error) use ($fail, $legacy_notice) {
            return $fail($legacy_notice . ' ' . $error);
        };
    }

    $mark = function ($value) use ($invoice) {
        erp_query("UPDATE erp_invoices SET parasut_invoice_id = '" . escape(mb_substr((string) $value, 0, 32)) . "' WHERE id = '" . (int) $invoice['id'] . "'");
    };
    $external_id = '';
    $attributes = array();

    $date_now = (string) ($payload['data']['attributes']['issue_date'] ?? '');
    $contact_now = (string) $contact['id'];
    $unsure = erp_edoc_parasut_unsure_read($invoice['parasut_invoice_id'] ?? '');

    // An earlier try had no answer (a timeout, a 5xx) and may have written
    // the record all the same: it is looked for before a second is written,
    // with the date and contact that try carried (a redate or a party fix
    // may have changed the invoice's since).
    if ($unsure !== null) {
        $date_then = ($unsure['date'] !== '') ? $unsure['date'] : $date_now;
        $contact_then = ($unsure['contact_id'] !== '') ? $unsure['contact_id'] : $contact_now;
        $search = $payload;
        $search['data']['attributes']['issue_date'] = $date_then;

        $found = erp_edoc_parasut_find_written($search, $contact_then, $context, trim((string) ($invoice['edoc_external_id'] ?? '')));

        if ($found['error'] !== '') {
            return $fail($found['error']);
        }

        if (count($found['records']) > 1) {
            return $fail(lang(array(
                'string' => 'Paraşüt holds {var:1} records of this invoice from earlier tries (ids {var:2}). Delete all but one of them on Paraşüt\'s screen, then send again; the one left is taken over.',
                'vars' => array(count($found['records']), implode(', ', array_keys($found['records']))),
            )));
        }

        foreach ($found['records'] as $found_id => $found_attributes) {
            $external_id = (string) $found_id;
            $attributes = $found_attributes;
        }

        // The record carries what the invoice was, not what it is: it is
        // deleted (it is no e-document yet) and the invoice written afresh.
        // One that will not go keeps the mark, so the next send asks again.
        if (($external_id !== '') && (($date_then !== $date_now) || ($contact_then !== $contact_now))) {
            $deleted = erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode($external_id), null, $context);
            $gone = ((string) $deleted['login_error'] === '') && (erp_edoc_parasut_ok($deleted) || ((int) $deleted['http_code'] === 404));

            if (!$gone) {
                return $fail(lang(array(
                    'string' => 'Paraşüt holds the record of an earlier try (id {var:1}) with the date or buyer the invoice had then, and it could not be deleted there ({var:2}). Delete it on Paraşüt\'s screen, then send again.',
                    'vars' => array($external_id, erp_edoc_parasut_error_text($deleted)),
                )));
            }

            $external_id = '';
            $attributes = array();
        }
    }

    // What the operator is told beside the outcome: a kept id Paraşüt no
    // longer held, forgotten, and whether the invoice was sent once more
    // without it.
    $told = '';

    if ($external_id === '') {
        // Marked before the request goes, so that a request whose answer
        // never comes (or a process that dies waiting) leaves the next try
        // the question to ask instead of a second record, with the date and
        // contact to ask it with.
        $result = array();
        $create = function () use ($mark, $date_now, $contact_now, $context, $kept, &$payload, &$result) {
            $mark(erp_edoc_parasut_unsure_mark($date_now, $contact_now));

            $result = erp_edoc_parasut_request('POST', '/sales_invoices', $payload, $context);

            return array('answer' => $result, 'kept' => $kept);
        };

        // A product card or shipment document Pinegrap kept that Paraşüt
        // answers it no longer holds (404, nothing written: deleted there,
        // or the shop moved to another Paraşüt company) is forgotten and the
        // invoice sent once more: the product on a new card, the order's
        // invoice as an ordinary one with the shipment included. That 404 is
        // a definite answer, not an unsure one. The default product/service
        // is a setting: it refuses in words that name it.
        $sent = parasut_send_once_more($create, defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '', function ($type, $id) use ($lines, $contact, &$invoice, &$payload) {
            _parasut_forget_kept_id($type, $id, (int) ($invoice['order_id'] ?? 0));

            if ($type === 'ShipmentDocument') {
                $invoice['order_shipment_id'] = '';
            }

            $payload = erp_edoc_parasut_invoice_payload($invoice, $lines, $contact['id']);

            return isset($payload['error']) ? (string) $payload['error'] : '';
        });
        $told = implode(' ', $sent['notices']);
        $before = ($told !== '') ? $told . ' ' : '';

        if ($sent['refusal'] !== '') {
            $mark('');
            return $fail($before . $sent['refusal']);
        }

        if ((string) $result['login_error'] !== '') {
            $mark('');
            return $fail($before . $result['login_error']);
        }

        $external_id = trim((string) ($result['body']['data']['id'] ?? ''));

        if (!erp_edoc_parasut_ok($result) || ($external_id === '')) {
            if (!erp_edoc_parasut_answer_unsure($result)) {
                $mark('');
                return $fail($before . lang(array('string' => 'Paraşüt would not take the invoice: {var:1}', 'vars' => erp_edoc_parasut_error_text($result))));
            }

            return $fail($before . lang(array(
                'string' => 'Paraşüt did not say whether it took the invoice ({var:1}). The next send looks for it at Paraşüt first, so it is not written twice.',
                'vars' => erp_edoc_parasut_error_text($result),
            )));
        }

        $attributes = (array) ($result['body']['data']['attributes'] ?? array());
    }

    // What Paraşüt made of the lines against what the ERP charged. The
    // record that differs is deleted again: a legal document is not the
    // place to find a rounding gap, and a record left behind would be
    // invoiced twice on the next try.
    $check = erp_edoc_parasut_total_check($attributes, $invoice);

    if (!$check['ok']) {
        $deleted = erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode($external_id), null, $context);
        $gone = ((string) $deleted['login_error'] === '') && (erp_edoc_parasut_ok($deleted) || ((int) $deleted['http_code'] === 404));
        $currency = (string) $invoice['currency'];

        if ($gone) {
            $mark('');
            $message = lang(array(
                'string' => 'Paraşüt computed {var:1} for the invoice where the ERP has {var:2}; the record was deleted there and nothing was issued.',
                'vars' => array(erp_money_out_currency($check['total'], $currency), erp_money_out_currency((int) $invoice['grand_total'], $currency)),
            ));
        } else {
            // The record stays at Paraşüt: its id goes back with the refusal,
            // so the row keeps it (nothing is created over it, and cancelling
            // the invoice deletes it there).
            $mark($external_id);
            $message = lang(array(
                'string' => 'Paraşüt computed {var:1} for the invoice where the ERP has {var:2}, and the record could not be deleted there ({var:3}): it is still at Paraşüt as id {var:4}, not issued. Cancel the invoice here to delete it there, or delete it on Paraşüt\'s screen.',
                'vars' => array(erp_money_out_currency($check['total'], $currency), erp_money_out_currency((int) $invoice['grand_total'], $currency), erp_edoc_parasut_error_text($deleted), $external_id),
            ));
        }

        // How Paraşüt got there, so the gap can be traced to the VAT or to
        // the amounts before it once the record is gone.
        $totals = erp_edoc_parasut_totals($attributes);

        if ($totals !== array()) {
            $message .= ' ' . lang(array(
                'string' => 'Paraşüt has it at {var:1} before VAT, {var:2} VAT, {var:3} withheld, {var:4} in all.',
                'vars' => array(
                    erp_money_out_currency($totals['before_taxes'], $currency),
                    erp_money_out_currency($totals['vat'], $currency),
                    erp_money_out_currency($totals['vat_withholding'], $currency),
                    erp_money_out_currency($totals['net'], $currency),
                ),
            ));
        }

        return array_merge($fail((($told !== '') ? $told . ' ' : '') . $message), array('external_id' => $gone ? '' : $external_id));
    }

    erp_query("UPDATE erp_invoices
        SET parasut_invoice_id = '" . escape(mb_substr($external_id, 0, 32)) . "',
            parasut_edoc_id = '', parasut_job_id = '', parasut_job_expires_at = 0
        WHERE id = '" . (int) $invoice['id'] . "'");

    if ($legacy_notice !== '') {
        $told = trim($legacy_notice . ' ' . $told);
    }

    return array(
        'success' => true,
        'external_id' => $external_id,
        'gib_uuid' => '',
        'gib_number' => '',
        'status' => 'created',
        'error' => '',
        'message' => $told,
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

    // Every screen offers the hand-over only for a draft; a stale tab or a
    // replayed request must not convert an 'error' row's record whose total
    // is not the ERP's into a legal document.
    $submit_refusal = erp_edoc_parasut_submit_refusal($invoice);

    if ($submit_refusal !== '') {
        return $fail($submit_refusal);
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

    // GİB's answer decides the kind only now. The invoice may have been sent
    // as an expected e-Fatura, without the web address and the carrier an
    // internet sale's e-Arşiv document has to carry; that is refused here
    // rather than converted without them.
    if ($kind === 'earchive') {
        $document_missing = erp_edoc_parasut_document_missing($invoice, 'earchive');

        if (!empty($document_missing)) {
            return $fail(lang(array(
                'string' => 'GİB does not list the buyer for e-Invoice, so the invoice goes as e-Archive, and an internet sale needs these on it: {var:1}. The draft at Paraşüt cannot take them any more: cancel the invoice and issue it again once they are on it.',
                'vars' => implode(', ', $document_missing),
            )));
        }
    }

    // A withheld line's code is sent against Paraşüt's own line id. The
    // record holds the ERP lines in order, then any rounding line.
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

            if (count($detail_ids) !== erp_edoc_parasut_detail_count($lines)) {
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
 * What a status question says about an invoice that Paraşüt holds with no
 * e-document. A draft that was never handed to GİB is sent there for the
 * first time; one that was handed over (a conversion job kept, the kind
 * decided, the row marked sent) and still has none is sent there again.
 * Why the conversion failed, when its job said so, comes first.
 *
 * @param array  $invoice  The erp_invoices row as the question found it
 * @param string $why      The failed conversion job's words, or ''
 * @return string
 */
function erp_edoc_parasut_unconverted_message($invoice, $why = '')
{
    $handed_over = (trim((string) ($invoice['parasut_job_id'] ?? '')) !== '')
        || ((string) ($invoice['edoc_status'] ?? '') === 'sent')
        || in_array((string) ($invoice['edoc_kind'] ?? ''), array('einvoice', 'earchive'), true);
    $why = trim((string) $why);

    if ($why !== '') {
        $why .= preg_match('/[.!?]$/u', $why) ? ' ' : '. ';
    }

    if ($handed_over) {
        return $why . lang('The invoice is at Paraşüt but has not become an e-document; hand it to GİB again.');
    }

    return $why . lang(array('string' => 'The document is waiting as a draft at {var:1}; hand it to GİB to make it official.', 'vars' => array('Paraşüt')));
}

/**
 * What a conversion job's answer says: still running, finished, or failed,
 * and Paraşüt's own words for a failure (errors[], strings or JSON:API
 * error objects).
 *
 * @param array $attributes  data.attributes of GET trackable_jobs/{id}
 * @return array ['state' => 'running'|'done'|'error', 'message' => string]
 */
function erp_edoc_parasut_job_reading($attributes)
{
    $attributes = (array) $attributes;
    $state = mb_strtolower(trim((string) ($attributes['status'] ?? '')), 'UTF-8');
    $said = array();

    foreach ((array) ($attributes['errors'] ?? array()) as $error) {
        if (is_array($error)) {
            $title = trim((string) (is_scalar($error['title'] ?? null) ? $error['title'] : ''));
            $detail = trim((string) (is_scalar($error['detail'] ?? null) ? $error['detail'] : ''));
            $line = (($title !== '') || ($detail !== ''))
                ? ((($title !== '') && ($detail !== '') && ($title !== $detail)) ? $title . ': ' . $detail : (($detail !== '') ? $detail : $title))
                : trim(implode(' ', array_map('strval', array_filter($error, 'is_scalar'))));
        } else {
            $line = is_scalar($error) ? trim((string) $error) : '';
        }

        if (($line !== '') && !in_array($line, $said, true)) {
            $said[] = $line;
        }
    }

    if (preg_match('/error|fail|abort/u', $state)) {
        return array(
            'state' => 'error',
            'message' => ($said !== array()) ? implode(' | ', $said) : lang('Paraşüt could not convert the invoice into an e-document.'),
        );
    }

    if (preg_match('/done|complet|success|finish/u', $state)) {
        return array('state' => 'done', 'message' => '');
    }

    return array('state' => 'running', 'message' => '');
}

/**
 * Whether Paraşüt's answer to GET sales_invoices/{id} says that this very
 * record is not there: 404 with the error Paraşüt gives for a record deleted
 * on its screen (title "Record was not found", detail "SalesInvoice-<id>").
 *
 * Any other answer says nothing about the record: a 404 that names no record
 * or another one ("No route matches."), a company the token's user cannot
 * reach (401 "No access to company"), a 5xx, a transport error, no token.
 *
 * @param array  $result       erp_edoc_parasut_request() answer
 * @param string $external_id  The sales invoice id the row names
 * @return bool
 */
function erp_edoc_parasut_invoice_gone($result, $external_id)
{
    $result = (array) $result;
    $external_id = trim((string) $external_id);

    if (($external_id === '') || ((int) ($result['http_code'] ?? 0) !== 404)
        || ((string) ($result['error'] ?? '') !== '') || ((string) ($result['login_error'] ?? '') !== '')) {
        return false;
    }

    $errors = $result['body']['errors'] ?? null;

    if (!is_array($errors)) {
        return false;
    }

    foreach ($errors as $error) {
        if (is_array($error) && (trim((string) (is_scalar($error['title'] ?? null) ? $error['title'] : '')) === 'Record was not found')
            && (trim((string) (is_scalar($error['detail'] ?? null) ? $error['detail'] : '')) === 'SalesInvoice-' . $external_id)) {
            return true;
        }
    }

    return false;
}

/**
 * What a status question answers for an invoice whose sales invoice the
 * connected Paraşüt company does not hold (erp_edoc_parasut_invoice_gone()).
 * Either it was deleted on Paraşüt's screen, as the send's own refusal and
 * the draft's hand-over leave the operator free to do, or the settings card
 * now connects another company, taken to answer the same 404 (the path is
 * per company); the message names both, and a draft left in the earlier
 * company stays there. The row is freed so the invoice can be sent again:
 * the service writes it as not at the provider, and the driver forgets the
 * record, its e-document and its job ('forget', the parasut_* columns as
 * cancel_invoice() clears them).
 *
 * The unsure mark of a send that got no answer is kept: that send may have
 * written a record of its own, so the row is 'error' without an id, the
 * state the next send and a cancel follow the mark from.
 *
 * Only a row the ERP does not hold as a GİB document is freed: a draft
 * ('created'), a record a later step failed on ('error'), and a handed-over
 * row whose conversion job this question read as failed. A sent, accepted
 * or cancelled document is never let go on a 404, and a rejected one may be
 * sent again as it is.
 *
 * The row is judged as the ERP knows it, not as Paraşüt does. After a
 * company switch a 'created' or 'error' row may still stand for an
 * e-document in the earlier company: one issued on Paraşüt's own screen, or
 * one a hand-over made whose answer was lost (a failed hand-over leaves the
 * row 'created'). Sending it again would issue the sale at GİB a second
 * time. So the row is freed only when the connected company holds another
 * record the row names ($witnesses, erp_edoc_parasut_gone_witnesses()):
 * the first that answers as itself frees it. When every one answers 404,
 * another company is taken to be connected; when the row names none, the
 * two cannot be told apart. Either way the row stays as it was ('forget'
 * empty, no 'gone') with a warning naming the way out: connect the earlier
 * company and ask again, or cancel the invoice and raise it again. Any
 * other answer (no token, a timeout, a 5xx, another record) writes
 * nothing, and the question is asked again later.
 *
 * A conversion job this question read as failed is a record of the
 * connected company itself (the job path is per company), so such a row
 * is freed without another read.
 *
 * A card the ERP linked again, or a product synced again, after the switch
 * lives in the new company and frees the row all the same.
 *
 * @param array    $invoice     An erp_invoices row
 * @param bool     $job_failed  This question read the conversion job, and it had failed
 * @param string[] $witnesses   Paths of the other records the row names
 * @param callable $request     erp_edoc_parasut_request() or a stand-in with its
 *                              arguments and answer (the tests)
 * @return array|null  ['answer' => the poll answer ('gone' => true when freed),
 *                      'forget' => parasut_* column => value, empty when held];
 *                      null when the row is not one a 404 may free
 */
function erp_edoc_parasut_gone_answer($invoice, $job_failed = false, $witnesses = array(), $request = 'erp_edoc_parasut_request')
{
    $status = (string) ($invoice['edoc_status'] ?? '');

    if (!in_array($status, array('created', 'error'), true) && !(($status === 'sent') && $job_failed)) {
        return null;
    }

    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $held = function ($answer) use ($status) {
        return array(
            'answer' => $answer + array('success' => true, 'status' => $status, 'gib_uuid' => '', 'gib_number' => '', 'kind' => '', 'message' => '', 'error' => '', 'party' => array()),
            'forget' => array(),
        );
    };

    if (!$job_failed) {
        $context = array('doc_type' => 'invoice', 'doc_id' => (int) ($invoice['id'] ?? 0));
        $witnessed = false;

        foreach ((array) $witnesses as $path) {
            $read = call_user_func($request, 'GET', (string) $path, null, $context);

            if ((string) ($read['login_error'] ?? '') !== '') {
                return $held(array('success' => false, 'error' => (string) $read['login_error']));
            }

            if (((string) ($read['error'] ?? '') === '') && ((int) ($read['http_code'] ?? 0) === 404)) {
                continue;
            }

            $id = substr((string) $path, strrpos((string) $path, '/') + 1);

            if (!erp_edoc_parasut_ok($read) || (trim((string) ($read['body']['data']['id'] ?? '')) !== $id)) {
                return $held(array('success' => false, 'error' => lang(array(
                    'string' => 'Paraşüt does not have this invoice\'s record (id {var:1}), and it would not show another record the invoice names, to tell a record deleted there from another company connected now: {var:2}',
                    'vars' => array($external_id, erp_edoc_parasut_error_text($read)),
                ))));
            }

            $witnessed = true;
            break;
        }

        if (!$witnessed) {
            return $held(array('message' => ($witnesses !== array())
                ? lang(array('string' => 'The connected Paraşüt company has neither this invoice\'s record (id {var:1}) nor the other records the invoice names, so another company is probably connected now; the invoice is kept as it was. Connect the earlier company on the settings card and ask after the status again. If that company has no e-document for the invoice either, cancel the invoice here and raise it again.', 'vars' => array($external_id)))
                : lang(array('string' => 'The connected Paraşüt company does not have this invoice\'s record (id {var:1}), and the invoice names no other Paraşüt record to tell a record deleted there from another company connected now; the invoice is kept as it was. If another company is connected, connect the earlier one on the settings card and ask after the status again. If the record was deleted at Paraşüt, cancel the invoice here and raise it again.', 'vars' => array($external_id)))));
        }
    }

    $marked = (erp_edoc_parasut_unsure_read($invoice['parasut_invoice_id'] ?? '') !== null);
    $forget = array('parasut_edoc_id' => '', 'parasut_job_id' => '', 'parasut_job_expires_at' => 0);

    if (!$marked) {
        $forget = array('parasut_invoice_id' => '') + $forget;
    }

    return array(
        'answer' => array(
            'success' => true,
            'status' => $marked ? 'error' : 'none',
            'gone' => true,
            'gib_uuid' => '',
            'gib_number' => '',
            'kind' => '',
            'message' => $marked
                ? lang(array('string' => 'The connected Paraşüt company does not have this invoice\'s record (id {var:1}): it was deleted there, or another company is connected now. The last send got no answer, so the next send first looks at Paraşüt for a record that send may have written; the invoice can be sent again.', 'vars' => array($external_id)))
                : lang(array('string' => 'The connected Paraşüt company does not have this invoice\'s record (id {var:1}): it was deleted there, or another company is connected now. The invoice can be sent again.', 'vars' => array($external_id))),
            'error' => '',
            'party' => array(),
        ),
        'forget' => $forget,
    );
}

/**
 * The other Paraşüt records an invoice names, as paths to read, for the
 * company guard of erp_edoc_parasut_gone_answer(): the contact of an
 * unanswered send's unsure mark, the buyer account's linked card, then the
 * lines' product cards. Real ids only, each once, three at most.
 *
 * @param array    $invoice        An erp_invoices row (parasut_invoice_id)
 * @param string   $linked_card    The contact id the account is linked to, '' for none
 * @param string[] $product_cards  The cards the lines' products keep
 * @return string[]
 */
function erp_edoc_parasut_gone_witnesses($invoice, $linked_card, $product_cards)
{
    $unsure = erp_edoc_parasut_unsure_read($invoice['parasut_invoice_id'] ?? '');
    $contacts = array(($unsure !== null) ? (string) $unsure['contact_id'] : '', (string) $linked_card);
    $paths = array();

    foreach ($contacts as $id) {
        $id = trim($id);

        if (preg_match('/^[1-9][0-9]*$/', $id)) {
            $paths['/contacts/' . $id] = true;
        }
    }

    foreach ((array) $product_cards as $id) {
        $id = trim((string) $id);

        if (preg_match('/^[1-9][0-9]*$/', $id)) {
            $paths['/products/' . $id] = true;
        }
    }

    return array_slice(array_keys($paths), 0, 3);
}

/**
 * erp_edoc_parasut_gone_witnesses() of an invoice as the database holds it:
 * the account's card linked at Paraşüt and its lines' product cards.
 *
 * @param array $invoice  An erp_invoices row
 * @return string[]
 */
function erp_edoc_parasut_gone_witnesses_of($invoice)
{
    $card = function_exists('erp_edoc_account_card_of') ? erp_edoc_account_card_of('parasut', (int) ($invoice['account_id'] ?? 0)) : null;
    $product_ids = array();

    foreach ((array) db_items("SELECT product_id FROM erp_invoice_items WHERE invoice_id = '" . (int) $invoice['id'] . "'") as $line) {
        $product_ids[] = (int) $line['product_id'];
    }

    return erp_edoc_parasut_gone_witnesses(
        $invoice,
        is_array($card) ? trim((string) $card['code']) : '',
        function_exists('parasut_kept_product_cards') ? parasut_kept_product_cards($product_ids) : array()
    );
}

/**
 * Asks after a converted invoice: the trackable job while it still answers,
 * then the sales invoice and the e-document it became. A record the
 * connected company does not hold frees a row the ERP does not hold as a
 * GİB document (erp_edoc_parasut_gone_answer(); the answer then carries
 * 'gone' => true).
 *
 * @param array  $invoice    An erp_invoices row
 * @param string $job_id     The sales invoice id send_invoice() returned
 * @param bool   $free_gone  Whether a deleted record frees the row. False for
 *                           a caller that does not write the answer on the
 *                           row (fetch_document()): it gets the plain 404
 *                           answer, and the status question frees the row.
 * @return array ['success' => bool, 'status' => string, 'gib_uuid' => string,
 *                'gib_number' => string, 'kind' => 'einvoice'|'earchive'|'',
 *                'message' => string, 'error' => string, 'party' => array,
 *                'gone' => bool (only when the record was not found)]
 */
function erp_edoc_parasut_poll($invoice, $job_id, $free_gone = true)
{
    $external_id = trim((string) $job_id);
    $out = array('success' => false, 'status' => 'sent', 'gib_uuid' => '', 'gib_number' => '', 'kind' => '', 'message' => '', 'error' => '', 'party' => array());
    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);

    if ($external_id === '') {
        $out['error'] = lang('The invoice has no Paraşüt id to ask after.');
        return $out;
    }

    // 1. The conversion job, while its window is open. A job that failed
    // is the one place Paraşüt says why in words. A failed job leaves the
    // sales invoice at Paraşüt as it was, so what the row becomes is read
    // from the invoice below (a record with no e-document is 'created' and
    // is handed over again, never created a second time); the job's words
    // go with it.
    $tracked = trim((string) ($invoice['parasut_job_id'] ?? ''));
    $job_words = '';
    $job_failed = false;

    if (($tracked !== '') && ((int) ($invoice['parasut_job_expires_at'] ?? 0) > time())) {
        $job = erp_edoc_parasut_request('GET', '/trackable_jobs/' . rawurlencode($tracked), null, $context);

        if ((string) $job['login_error'] !== '') {
            $out['error'] = $job['login_error'];
            return $out;
        }

        if (erp_edoc_parasut_ok($job)) {
            $reading = erp_edoc_parasut_job_reading($job['body']['data']['attributes'] ?? array());

            if ($reading['state'] === 'running') {
                $out['success'] = true;
                $out['status'] = 'sent';
                $out['message'] = lang('Paraşüt is still converting the invoice; ask again in a minute.');
                return $out;
            }

            erp_query("UPDATE erp_invoices SET parasut_job_id = '', parasut_job_expires_at = 0 WHERE id = '" . (int) $invoice['id'] . "'");
            $job_words = (string) $reading['message'];
            $job_failed = ($reading['state'] === 'error');
        }
    }

    // 2. The sales invoice and the e-document it became.
    $detail = erp_edoc_parasut_request('GET', '/sales_invoices/' . rawurlencode($external_id) . '?include=active_e_document,contact', null, $context);

    if ((string) $detail['login_error'] !== '') {
        $out['error'] = $detail['login_error'];
        return $out;
    }

    // Not held by the connected company (deleted on Paraşüt's screen, or
    // another company connected): a row the ERP does not hold as a GİB
    // document is freed, so the invoice is sent again instead of being
    // refused at every press.
    if ($free_gone && erp_edoc_parasut_invoice_gone($detail, $external_id)) {
        $gone = erp_edoc_parasut_gone_answer($invoice, $job_failed, erp_edoc_parasut_gone_witnesses_of($invoice));

        if ($gone !== null) {
            if ($gone['forget'] !== array()) {
                $columns = array();

                foreach ($gone['forget'] as $column => $value) {
                    $columns[] = $column . " = '" . escape((string) $value) . "'";
                }

                erp_query("UPDATE erp_invoices SET " . implode(', ', $columns) . " WHERE id = '" . (int) $invoice['id'] . "'");
            }

            return $gone['answer'];
        }
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
        $totals = erp_edoc_parasut_totals($attributes);
        $check = erp_edoc_parasut_total_check($attributes, $invoice);

        // A record whose total is not the ERP's (one Paraşüt would not
        // delete after the send found the gap) is never offered to GİB.
        if (($totals !== array()) && !$check['ok']) {
            $currency = (string) ($invoice['currency'] ?? 'TRY');
            $out['success'] = true;
            $out['status'] = 'error';
            $out['message'] = lang(array(
                'string' => 'Paraşüt holds the record with a total of {var:1} where the ERP has {var:2}, so it is not handed to GİB. Cancel the invoice here to delete it there.',
                'vars' => array(erp_money_out_currency($check['total'], $currency), erp_money_out_currency((int) ($invoice['grand_total'] ?? 0), $currency)),
            ));
            return $out;
        }

        // No e-document: a draft not handed to GİB yet, or one whose
        // conversion job failed or ran out before it was read. Shown as the
        // provider's draft, so the hand-over button comes back.
        $out['success'] = true;
        $out['status'] = 'created';
        $out['message'] = erp_edoc_parasut_unconverted_message($invoice, $job_words);

        // What Paraşüt computed for the draft, so it can be looked over
        // before the hand-over without opening Paraşüt.
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
        // Not read yet: asking after the status writes it down. A record
        // deleted at Paraşüt is only reported here, since the service's write
        // of the status does not run on this path; the status question frees
        // the row.
        $polled = erp_edoc_parasut_poll($invoice, (string) ($invoice['edoc_external_id'] ?? ''), false);

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
 * Takes back at Paraşüt what a send that got no answer may have written,
 * for an invoice whose row names no record but still carries the unsure
 * mark. The record is looked for the way the next send looks for it: the
 * sales invoices of the date and contact the mark holds, newest first,
 * matched by erp_edoc_parasut_written_matches(); a mark without a contact
 * (one from before it carried it) is looked for among the day's invoices of
 * every contact. One plain record is deleted; none leaves nothing to take
 * back. Several, one Paraşüt holds as an e-document, or a search that
 * failed or left pages unread are refused, so that the invoice is not
 * cancelled here while its record stays there with nothing pointing to it.
 * Only GET and that one DELETE are sent.
 *
 * @param array    $invoice  An erp_invoices row (full_number, issue_date; an
 *                           order's invoice also order_number)
 * @param array    $unsure   erp_edoc_parasut_unsure_read() of its parasut_invoice_id
 * @param callable $request  function (string $method, string $path): an
 *                           erp_edoc_parasut_request() answer
 * @return array ['success' => bool, 'error' => string, 'message' => string, 'outcome' => 'deleted'|'']
 */
function erp_edoc_parasut_cancel_unsure($invoice, $unsure, $request)
{
    $fail = function ($error) {
        return array('success' => false, 'error' => $error, 'message' => '', 'outcome' => '');
    };
    $date = ((string) ($unsure['date'] ?? '') !== '') ? (string) $unsure['date'] : (string) ($invoice['issue_date'] ?? '');
    $contact_id = (string) ($unsure['contact_id'] ?? '');
    $payload = array('data' => array('attributes' => array(
        'description' => erp_edoc_parasut_invoice_description($invoice),
        'issue_date' => $date,
    )));
    $records = array();
    $documents = array();
    $page = 0;
    $total_pages = 1;

    while ($page < (int) ERP_EDOC_PARASUT_FIND_PAGES) {
        $page++;
        $list = $request('GET', '/sales_invoices?' . (($contact_id !== '') ? 'filter[contact_id]=' . rawurlencode($contact_id) . '&' : '')
            . 'filter[issue_date]=' . rawurlencode($date) . '&sort=-id&include=active_e_document'
            . '&page[number]=' . $page . '&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE);

        if (((string) ($list['login_error'] ?? '') !== '') || !erp_edoc_parasut_ok($list) || !is_array($list['body'] ?? null)) {
            return $fail(lang(array(
                'string' => 'The last send got no answer from Paraşüt, and Paraşüt could not be asked whether it kept the invoice: {var:1}. Nothing was cancelled; try again in a minute.',
                'vars' => erp_edoc_parasut_error_text($list),
            )));
        }

        $records += erp_edoc_parasut_written_matches($list['body'], $payload, $contact_id);
        $documents += erp_edoc_parasut_written_documents($list['body'], $payload, $contact_id);
        $total_pages = max(1, (int) ($list['body']['meta']['total_pages'] ?? 1));

        if ($page >= $total_pages) {
            break;
        }
    }

    if (!empty($documents)) {
        return $fail(lang(array(
            'string' => 'Paraşüt holds this invoice as an e-document (record {var:1}), written by the send that got no answer and converted there. Nothing was cancelled; an e-Archive invoice is cancelled on Paraşüt\'s screen first, an e-Invoice is answered with a return invoice.',
            'vars' => implode(', ', array_keys($documents)),
        )));
    }

    if (erp_edoc_parasut_search_incomplete($records, $page, $total_pages)) {
        return $fail(lang('The last send got no answer from Paraşüt, and the buyer has more invoices on that day than can be looked through, so whether Paraşüt kept it is not known. Nothing was cancelled; look for the record on Paraşüt\'s screen, and take the invoice back here with a return invoice.'));
    }

    if (count($records) > 1) {
        return $fail(lang(array(
            'string' => 'Paraşüt holds {var:1} records of this invoice from earlier tries (ids {var:2}). Nothing was cancelled; delete them on Paraşüt\'s screen, then cancel again.',
            'vars' => array(count($records), implode(', ', array_keys($records))),
        )));
    }

    if (empty($records)) {
        return array('success' => true, 'error' => '', 'message' => '', 'outcome' => 'deleted');
    }

    $record_id = (string) key($records);
    $deleted = $request('DELETE', '/sales_invoices/' . rawurlencode($record_id));
    $answered = ((string) ($deleted['login_error'] ?? '') === '');

    if ($answered && ((int) ($deleted['http_code'] ?? 0) === 404)) {
        return array('success' => true, 'error' => '', 'message' => lang('Paraşüt no longer has the record.'), 'outcome' => 'deleted');
    }

    if (!$answered || !erp_edoc_parasut_ok($deleted)) {
        return $fail(lang(array(
            'string' => 'Paraşüt holds the record of the send that got no answer (id {var:1}) and would not delete it ({var:2}). Nothing was cancelled; delete it on Paraşüt\'s screen, then cancel again.',
            'vars' => array($record_id, erp_edoc_parasut_error_text($deleted)),
        )));
    }

    return array('success' => true, 'error' => '', 'message' => lang('The record has been deleted at Paraşüt.'), 'outcome' => 'deleted');
}

/**
 * Takes a document back at Paraşüt. What Paraşüt holds is read first, so
 * the decision rests on its answer and not on a row that may be out of
 * date: a record that is no e-document is deleted, an e-Archive invoice is
 * cancelled, an e-Invoice is refused. A row that names no record but
 * carries the unsure mark is looked for first (erp_edoc_parasut_cancel_unsure()).
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
        $unsure = erp_edoc_parasut_unsure_read($invoice['parasut_invoice_id'] ?? '');

        if ($unsure === null) {
            return array('success' => true, 'error' => '', 'message' => '', 'outcome' => 'deleted');
        }

        // A send that got no answer may have written the record all the
        // same; it carries the order's number in its description.
        if (((int) ($invoice['order_id'] ?? 0) > 0) && !isset($invoice['order_number'])) {
            $order = db_item("SELECT order_number FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1");
            $invoice['order_number'] = is_array($order) ? trim((string) $order['order_number']) : '';
        }

        $taken = erp_edoc_parasut_cancel_unsure($invoice, $unsure, function ($method, $path) use ($context) {
            return erp_edoc_parasut_request($method, $path, null, $context);
        });

        if (!empty($taken['success'])) {
            $forget();
        }

        return $taken;
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
 * The lists a read of the cards goes through, each with the filter it is
 * asked with. Paraşüt's contacts list leaves archived cards out, and a
 * complete read that never saw them would show every archived card as gone
 * from Paraşüt; filter[archived]=true lists the archived cards alone. The
 * documentation names that filter for other lists only (sales offers,
 * waybills, warehouses); the contacts list takes it (live, 2026-10-20).
 *
 * @return array  List => query string prefix ('' for none)
 */
function erp_edoc_parasut_contact_lists()
{
    return array(
        'listed' => '',
        'archived' => 'filter[archived]=true&',
    );
}

/**
 * One page of a read out of the same page of each list: the cards of every
 * list, how many cards they hold together, and as many pages as the longest
 * has. The archived list adds archived cards only, so no card comes twice
 * even if Paraşüt answered that list unfiltered.
 *
 * @param array $reads  List => erp_edoc_parasut_contacts_read() of its page
 * @return array ['items' => cards, 'total' => int, 'pages' => int]
 */
function erp_edoc_parasut_contacts_merge($reads)
{
    $items = array();
    $total = 0;
    $pages = 1;

    foreach ((array) $reads as $list => $read) {
        foreach ((array) ($read['items'] ?? array()) as $card) {
            if (((string) $list === 'archived') && !empty($card['is_active'])) {
                continue;
            }

            $items[] = $card;
        }

        $total += max(0, (int) ($read['total'] ?? 0));
        $pages = max($pages, (int) ($read['pages'] ?? 1));
    }

    return array('items' => $items, 'total' => $total, 'pages' => $pages);
}

/**
 * One page of the company's customer and supplier cards (GET contacts):
 * page $page of every list of erp_edoc_parasut_contact_lists(), so that a
 * read through all the pages sees the archived cards too, as passive cards.
 * Sorted by id so that the pages do not shift under a read: the list's own
 * order is by balance, which moves while it is read.
 *
 * Page 1 asks every list; a later page of the same read asks only the lists
 * that reach it, so a company with few archived cards spends one request on
 * them in a read, not one a page.
 *
 * @param int $page  From 1
 * @return array ['success' => bool, 'error' => string, 'items' => cards, 'total' => int, 'pages' => int]
 */
function erp_edoc_parasut_accounts($page = 1)
{
    // Each list's card count and pages, as this read last found them.
    static $found = array();

    $none = array('success' => false, 'error' => '', 'items' => array(), 'total' => 0, 'pages' => 0);
    $page = max(1, (int) $page);
    $size = (int) ERP_EDOC_PARASUT_PAGE_SIZE;

    if ($page === 1) {
        $found = array();
    }

    erp_edoc_parasut_allow_time();

    $reads = array();

    foreach (erp_edoc_parasut_contact_lists() as $list => $filter) {
        if (isset($found[$list]) && ($page > $found[$list]['pages'])) {
            $reads[$list] = array('items' => array()) + $found[$list];

            continue;
        }

        $result = erp_edoc_parasut_request('GET', '/contacts?' . $filter . 'sort=id&page[number]=' . $page . '&page[size]=' . $size, null, array('doc_type' => 'account'));

        if ((string) $result['login_error'] !== '') {
            return array_merge($none, array('error' => $result['login_error']));
        }

        if (!erp_edoc_parasut_ok($result) || !is_array($result['body'] ?? null)) {
            return array_merge($none, array('error' => lang(array('string' => 'Paraşüt would not list its customers and suppliers: {var:1}', 'vars' => erp_edoc_parasut_error_text($result)))));
        }

        $reads[$list] = erp_edoc_parasut_contacts_read($result['body'], $size);
        $found[$list] = array('total' => $reads[$list]['total'], 'pages' => $reads[$list]['pages']);
    }

    return array('success' => true, 'error' => '') + erp_edoc_parasut_contacts_merge($reads);
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
