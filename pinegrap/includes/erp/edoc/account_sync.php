<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the e-document provider's customer and supplier cards, beside the
 * ERP accounts.
 *
 * A provider keeps a card for every counterparty it has put a document on
 * (Logo İşbaşı: Müşteri & Tedarikçi) and the store keeps its own accounts.
 * The two drift apart: a card opened by an invoice, an address corrected on
 * one side only. This file reads the provider's list on request, keeps each
 * card once in erp_edoc_accounts (4.69) and says where it stands against the
 * ERP: linked and the same, linked and different, only at the provider, or
 * only in the ERP.
 *
 * Nothing is written on either side without the operator. A difference is
 * shown field by field with a direction for each; a card is taken into the
 * ERP, or an account sent to the provider, on a button. The one thing a read
 * does on its own is link a card to the single account that carries the
 * same tax number - a link, not a change to either record - and a link
 * somebody has undone is not made again. Money never travels: the
 * provider's balance belongs to its ledger, the ERP's to the ERP.
 *
 * The provider is asked through the one door (erp_edoc_call) for three
 * operations, so any driver that implements them gets these screens:
 *
 *   erp_edoc_<code>_accounts($page)
 *       ['success', 'error', 'items' => cards, 'total' => int, 'pages' => int]
 *   erp_edoc_<code>_account($external_id)
 *       ['success', 'error', 'card' => card]
 *   erp_edoc_<code>_account_save($card, $external_id)     '' creates
 *       ['success', 'error', 'external_id' => string]
 *       $card carries only the fields to write; a change leaves every other
 *       field of the provider's card as it was.
 *
 * A card: external_id, code, title, is_person, tax_number, tax_office,
 * email, phone, address, district, city, country_code, postcode, kind
 * ('customer' | 'supplier' | 'both'), is_active, updated_at (unix; 0 when
 * the provider does not say).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// Pages read in one go. A provider pages at up to a hundred cards; each page
// is one read of the provider's monthly quota.
if (!defined('ERP_EDOC_ACCOUNTS_MAX_PAGES')) {
    define('ERP_EDOC_ACCOUNTS_MAX_PAGES', 50);
}

// Cards or accounts one bulk action handles. Sending an account to the
// provider is a write and a read-back; a few dozen fit in one request.
if (!defined('ERP_EDOC_ACCOUNTS_BULK')) {
    define('ERP_EDOC_ACCOUNTS_BULK', 25);
}

/**
 * Whether the 4.69 table is there.
 *
 * @return bool
 */
function erp_edoc_accounts_installed()
{
    static $installed = null;

    if ($installed === null) {
        $installed = function_exists('waf_table_has_column') && waf_table_has_column('erp_edoc_accounts', 'external_id');
    }

    return $installed;
}

/**
 * The provider whose cards are compared: the active one, when it declares
 * the capability and its driver implements all three operations.
 *
 * @return string  A driver code, or ''
 */
function erp_edoc_accounts_provider()
{
    if (!erp_edoc_installed()) {
        return '';
    }

    $code = erp_edoc_active();

    if (($code === '') || !erp_edoc_has_capability('accounts', $code)
        || !erp_edoc_supports('accounts', $code) || !erp_edoc_supports('account', $code)
        || !erp_edoc_supports('account_save', $code)) {
        return '';
    }

    return $code;
}

/**
 * The fields that are compared, in the order the screens print them.
 *
 * @return array  field => label
 */
function erp_edoc_account_fields()
{
    return array(
        'title' => lang('Name'),
        'is_person' => lang('Taxpayer'),
        'tax_number' => erp_tax_id_label(),
        'tax_office' => lang('Tax Office'),
        'email' => lang('E-mail Address'),
        'phone' => lang('Phone Number'),
        'address' => lang('Address'),
        'district' => lang('District'),
        'city' => lang('City'),
        'postcode' => lang('Postal Code'),
        'country_code' => lang('Country'),
        'kind' => lang('Type'),
        'is_active' => lang('Status'),
    );
}

/**
 * The walk-in account the till bills to, or 0. Its sales go out with the
 * final-consumer number, so it never has a card of its own at the provider.
 *
 * @return int
 */
function erp_edoc_accounts_walkin_id()
{
    return (defined('ERP_WALKIN_ACCOUNT_ID') && ((int) ERP_WALKIN_ACCOUNT_ID > 0)) ? (int) ERP_WALKIN_ACCOUNT_ID : 0;
}

/**
 * The country a record is judged by: its own, or the store's when it has
 * none. The ERP is used far beyond Turkey; a rule of one country's tax
 * authority (a VKN's check digits, a five-digit postcode) applies only to
 * records of that country.
 *
 * @param string $country_code
 * @return string  Two letters, or '' when neither the record nor the store names one
 */
function erp_edoc_account_country($country_code)
{
    return erp_account_country($country_code);
}

/**
 * A tax number as it is matched: letters and digits only, upper case.
 * "12-3456789", "DE 123 456 789" and "1234567890" keep what identifies them
 * and lose how they were typed.
 *
 * @param string $number
 * @return string
 */
function erp_edoc_account_tax_key($number)
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $number));
}

/**
 * How long a tax number the account card holds (erp_accounts.tax_number),
 * read from the table so that a wider column is honoured without a change
 * here.
 *
 * @return int
 */
function erp_edoc_account_tax_number_width()
{
    return erp_tax_number_width();
}

/**
 * Whether a tax number can match two records.
 *
 * In Turkey: ten or eleven digits whose check digits hold, and not the
 * final-consumer number every counter sale shares. Elsewhere the formats
 * are too many to check (EIN, VAT id, ABN, ...), so what is asked is that
 * it looks like an identifier at all: four to thirty-two letters or digits,
 * not one character repeated (000000, 11111).
 *
 * @param string $number
 * @param string $country_code  The record's country; the store's when empty
 * @return bool
 */
function erp_edoc_account_tax_number_usable($number, $country_code = '')
{
    $key = erp_edoc_account_tax_key($number);

    if (erp_edoc_account_country($country_code) === 'TR') {
        if (!preg_match('/^\d{10,11}$/', $key) || ($key === erp_edoc_final_consumer_tckn())) {
            return false;
        }

        return erp_edoc_tax_number_valid($key);
    }

    return (strlen($key) >= 4) && (strlen($key) <= 32) && !preg_match('/^(.)\1*$/', $key);
}

/**
 * A tax number the way the account card keeps it: digits in Turkey, where
 * the number is digits by law; elsewhere as it was written, unless that
 * does not fit the card, and then without its separators.
 *
 * @param string $number
 * @param string $country_code
 * @return string
 */
function erp_edoc_account_tax_number_for_erp($number, $country_code = '')
{
    if (erp_edoc_account_country($country_code) === 'TR') {
        return preg_replace('/\D/', '', (string) $number);
    }

    $number = trim((string) $number);

    return (mb_strlen($number) <= erp_edoc_account_tax_number_width()) ? $number : erp_edoc_account_tax_key($number);
}

/**
 * The ERP accounts by matched tax number, kept for the request. A caller
 * that has just opened an account (or is about to decide whether to) asks
 * for a fresh read.
 *
 * @param bool $fresh
 * @return array  key => list of ['id', 'title', 'country_code', 'status']
 */
function erp_edoc_accounts_by_tax_key($fresh = false)
{
    static $map = null;

    if (($map === null) || $fresh) {
        $map = array();

        foreach ((array) db_items("SELECT id, title, tax_number, country_code, status FROM erp_accounts WHERE tax_number <> '' ORDER BY id ASC") as $account) {
            $key = erp_edoc_account_tax_key($account['tax_number']);

            if ($key !== '') {
                $map[$key][] = $account;
            }
        }
    }

    return $map;
}

/**
 * Text as it is compared: spaces collapsed, upper case the Turkish way
 * (i to İ, ı to I first, which mb_strtoupper() alone would fold together).
 *
 * @param string $text
 * @return string
 */
function erp_edoc_account_fold($text)
{
    $text = preg_replace('/\s+/u', ' ', trim((string) $text));

    return mb_strtoupper(strtr($text, array('i' => 'İ', 'ı' => 'I')), 'UTF-8');
}

/**
 * A field's value as it is compared. Case, spacing and the ways a phone
 * number or a tax office are usually written do not make two records
 * different; the final-consumer number counts as no number, because it
 * never belongs on an account card.
 *
 * @param string $field
 * @param mixed  $value
 * @return string
 */
function erp_edoc_account_norm($field, $value)
{
    switch ((string) $field) {
        case 'is_person':
        case 'is_active':
            return !empty($value) ? '1' : '0';

        case 'tax_number':
            $number = erp_edoc_account_tax_key($value);

            return ($number === erp_edoc_final_consumer_tckn()) ? '' : $number;

        case 'email':
            return mb_strtolower(trim((string) $value), 'UTF-8');

        case 'phone':
            // +90 5xx, 0 5xx and 5xx are one line (and +1 555 and 555 in
            // North America): the last ten digits decide.
            $digits = preg_replace('/\D/', '', (string) $value);

            return (strlen($digits) > 10) ? substr($digits, -10) : $digits;

        case 'postcode':
            return strtoupper(preg_replace('/\s+/', '', (string) $value));

        case 'country_code':
        case 'kind':
            return strtoupper(trim((string) $value));

        case 'tax_office':
            // "KADIKÖY VERGİ DAİRESİ", "Kadıköy V.D." and "Kadıköy" are one office.
            return trim(preg_replace('/\s*(VERGİ DAİRESİ( MÜDÜRLÜĞÜ)?|V\.\s?D\.?|VD)$/u', '', erp_edoc_account_fold($value)));
    }

    return erp_edoc_account_fold($value);
}

/**
 * Whether a field holds nothing. The switches (taxpayer type, status, kind)
 * always hold something.
 *
 * @param string $field
 * @param mixed  $value
 * @return bool
 */
function erp_edoc_account_blank($field, $value)
{
    if (in_array((string) $field, array('is_person', 'is_active', 'kind'), true)) {
        return false;
    }

    return erp_edoc_account_norm($field, $value) === '';
}

/**
 * An ERP account in the card shape.
 *
 * @param array $account  An erp_accounts row
 * @return array
 */
function erp_edoc_account_erp_card($account)
{
    return array(
        'title' => trim((string) ($account['title'] ?? '')),
        'is_person' => ((int) ($account['is_person'] ?? 0) === 1),
        'tax_number' => trim((string) ($account['tax_number'] ?? '')),
        'tax_office' => trim((string) ($account['tax_office'] ?? '')),
        'email' => trim((string) ($account['email'] ?? '')),
        'phone' => trim((string) ($account['phone'] ?? '')),
        'address' => trim((string) ($account['address'] ?? '')),
        'district' => trim((string) ($account['district'] ?? '')),
        'city' => trim((string) ($account['city'] ?? '')),
        'postcode' => trim((string) ($account['postcode'] ?? '')),
        'country_code' => strtoupper(trim((string) ($account['country_code'] ?? ''))),
        'kind' => (string) ($account['kind'] ?? 'customer'),
        'is_active' => ((string) ($account['status'] ?? 'active') !== 'passive'),
    );
}

/**
 * A kept card (an erp_edoc_accounts row) in the card shape.
 *
 * @param array $row
 * @return array
 */
function erp_edoc_account_stored_card($row)
{
    return array(
        'external_id' => (string) ($row['external_id'] ?? ''),
        'code' => (string) ($row['code'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'is_person' => ((int) ($row['is_person'] ?? 0) === 1),
        'tax_number' => (string) ($row['tax_number'] ?? ''),
        'tax_office' => (string) ($row['tax_office'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'district' => (string) ($row['district'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'postcode' => (string) ($row['postcode'] ?? ''),
        'country_code' => (string) ($row['country_code'] ?? ''),
        'kind' => (string) ($row['kind'] ?? 'customer'),
        'is_active' => ((int) ($row['is_active'] ?? 1) === 1),
    );
}

/**
 * The fields an account and a card disagree on.
 *
 * A kind of 'both' on either side covers the other: a card that is also a
 * supplier at the provider is not worth a write.
 *
 * @param array $erp   erp_edoc_account_erp_card()
 * @param array $card  A card
 * @return array  field => ['erp' => value, 'provider' => value]
 */
function erp_edoc_account_diff($erp, $card)
{
    $diff = array();

    foreach (array_keys(erp_edoc_account_fields()) as $field) {
        $mine = $erp[$field] ?? '';
        $theirs = $card[$field] ?? '';

        if ($field === 'kind') {
            if (($mine === $theirs) || ($mine === 'both') || ($theirs === 'both')) {
                continue;
            }
        } elseif (erp_edoc_account_norm($field, $mine) === erp_edoc_account_norm($field, $theirs)) {
            continue;
        }

        $diff[$field] = array('erp' => $mine, 'provider' => $theirs);
    }

    return $diff;
}

/**
 * Why a value cannot be written to the ERP account card, or ''. The card's
 * own form holds the same checks (includes/erp/account_form.php).
 *
 * @param string $field
 * @param mixed  $value
 * @param array  $values  The card as it would be after the write (country, taxpayer type)
 * @return string
 */
function erp_edoc_account_erp_refusal($field, $value, $values = array())
{
    switch ((string) $field) {
        case 'title':
            if (trim((string) $value) === '') {
                return lang('Enter a name.');
            }

            return (mb_strlen(trim((string) $value)) > 255) ? lang(array('string' => 'Longer than the account card holds ({var:1} characters).', 'vars' => 255)) : '';

        case 'address':
            return (mb_strlen(trim((string) $value)) > 255) ? lang(array('string' => 'Longer than the account card holds ({var:1} characters).', 'vars' => 255)) : '';

        case 'tax_number':
            $country = erp_edoc_account_country((string) ($values['country_code'] ?? ''));
            $number = erp_edoc_account_tax_number_for_erp($value, $country);

            if ($number === '') {
                return '';
            }

            if ($country === 'TR') {
                // Letters in a Turkish number mean it was mistyped; stripping
                // them would pass the rest off as a real VKN.
                if (preg_match('/[^0-9]/', erp_edoc_account_tax_key($value))) {
                    return lang('A VKN has 10 digits and a TCKN 11.');
                }

                if ($number === erp_edoc_final_consumer_tckn()) {
                    return lang('The final-consumer number is not written to an account card.');
                }

                if ((strlen($number) !== 10) && (strlen($number) !== 11)) {
                    return lang('A VKN has 10 digits and a TCKN 11.');
                }

                if (!erp_edoc_tax_number_valid($number)) {
                    return lang('The check digits of this number do not match; it is probably mistyped. GİB refuses a document that carries it.');
                }
            }

            return (mb_strlen($number) > erp_edoc_account_tax_number_width())
                ? lang(array('string' => 'Longer than the account card holds ({var:1} characters).', 'vars' => erp_edoc_account_tax_number_width()))
                : '';

        case 'postcode':
            // erp_edoc_postcode_valid() knows Turkey's shape and takes any
            // other country's code as written.
            $country = erp_edoc_account_country((string) ($values['country_code'] ?? ''));

            return ((trim((string) $value) !== '') && ($country !== '') && !erp_edoc_postcode_valid(trim((string) $value), $country))
                ? lang('A Turkish postcode has five digits.')
                : '';

        case 'country_code':
            $code = strtoupper(trim((string) $value));

            return (($code !== '') && !preg_match('/^[A-Z]{2}$/', $code)) ? lang('The country is not known.') : '';
    }

    return '';
}

/**
 * Why a value cannot be sent to the provider, or ''. A Turkish number is
 * held to its check digits; a provider's own rules for its country are the
 * driver's to report.
 *
 * @param string $field
 * @param mixed  $value
 * @param array  $values  The record the value belongs to (its country)
 * @return string
 */
function erp_edoc_account_provider_refusal($field, $value, $values = array())
{
    if (((string) $field === 'title') && (trim((string) $value) === '')) {
        return lang('Enter a name.');
    }

    if (((string) $field === 'tax_number') && (erp_edoc_account_country((string) ($values['country_code'] ?? '')) === 'TR')) {
        $number = preg_replace('/\D/', '', (string) $value);

        if (($number !== '') && !erp_edoc_tax_number_valid($number, true)) {
            return lang('The check digits of this number do not match; it is probably mistyped. GİB refuses a document that carries it.');
        }
    }

    return '';
}

/**
 * The direction a difference is offered in: the empty side is filled from
 * the one that holds something; when both hold something, the ERP's value
 * goes to the provider. A value the ERP card would refuse is not offered.
 *
 * @param string $field
 * @param mixed  $erp_value
 * @param mixed  $provider_value
 * @param array  $erp_card  The account in the card shape, for the checks
 * @return string  'erp' (the ERP's value goes over) | 'provider' (the provider's comes in) | 'keep'
 */
function erp_edoc_account_default_direction($field, $erp_value, $provider_value, $erp_card = array())
{
    if (erp_edoc_account_blank($field, $erp_value) && !erp_edoc_account_blank($field, $provider_value)) {
        return (erp_edoc_account_erp_refusal($field, $provider_value, $erp_card) === '') ? 'provider' : 'keep';
    }

    return (erp_edoc_account_provider_refusal($field, $erp_value, $erp_card) === '') ? 'erp' : 'keep';
}

/**
 * A field's value as the screens print it.
 *
 * @param string $field
 * @param mixed  $value
 * @return string  Plain text; '' for nothing
 */
function erp_edoc_account_value_text($field, $value)
{
    switch ((string) $field) {
        case 'is_person':
            return !empty($value) ? lang('Person') : lang('Company');

        case 'is_active':
            return !empty($value) ? lang('Active') : lang('Passive');

        case 'kind':
            $kinds = array('customer' => lang('Customer'), 'supplier' => lang('Supplier'), 'both' => lang('Customer and supplier'));

            return $kinds[(string) $value] ?? (string) $value;

        case 'country_code':
            $code = strtoupper(trim((string) $value));

            if ($code === '') {
                return '';
            }

            $name = db_value("SELECT name FROM countries WHERE code = '" . escape($code) . "' LIMIT 1");

            return ((string) $name !== '') ? (string) $name : $code;
    }

    return trim((string) $value);
}

/* ---------------------------------------------------------------------------
   Reading the provider's list
   --------------------------------------------------------------------------- */

/**
 * Reads every page of the provider's cards and keeps them; then links each
 * new card whose tax number belongs to exactly one account.
 *
 * @return array ['success' => bool, 'error' => string, 'found' => int,
 *                'added' => int, 'linked' => int, 'truncated' => bool]
 */
function erp_edoc_accounts_fetch()
{
    $out = array('success' => false, 'error' => '', 'found' => 0, 'added' => 0, 'linked' => 0, 'truncated' => false);

    if (!erp_edoc_accounts_installed()) {
        return array_merge($out, array('error' => lang('Run the software upgrade first: the account sync table is not there yet.')));
    }

    $provider = erp_edoc_accounts_provider();

    if ($provider === '') {
        return array_merge($out, array('error' => lang('The e-document provider in use does not hand over its customer and supplier cards.')));
    }

    $started = time();
    $page = 1;
    $pages = 1;

    do {
        $answer = erp_edoc_call('accounts', array($page), $provider);

        if (empty($answer['success'])) {
            return array_merge($out, array('error' => (string) $answer['error']));
        }

        foreach ((array) ($answer['items'] ?? array()) as $card) {
            $kept = erp_edoc_accounts_keep($provider, (array) $card, $started);

            if ($kept === 'added') {
                $out['added']++;
            }

            if ($kept !== '') {
                $out['found']++;
            }
        }

        $pages = max(1, (int) ($answer['pages'] ?? 1));
        $page++;
    } while (($page <= $pages) && ($page <= ERP_EDOC_ACCOUNTS_MAX_PAGES));

    $out['truncated'] = ($pages > ERP_EDOC_ACCOUNTS_MAX_PAGES);
    $out['linked'] = erp_edoc_accounts_autolink($provider);

    // A card missing from a complete read is gone at the provider; one that
    // was cut short proves nothing about the cards it did not reach.
    erp_edoc_settings_save($provider, array(
        'accounts_read_at' => $started,
        'accounts_read_complete' => $out['truncated'] ? 0 : 1,
    ));

    $out['success'] = true;

    return $out;
}

/**
 * Keeps one card. The provider's fields are written; the store's (status,
 * account_id, no_auto_link) are left alone.
 *
 * @param string $provider
 * @param array  $card
 * @param int    $seen_at  The read that saw it; 0 for a card read on its own
 * @return string  'added', 'kept', or '' when the card carried no id
 */
function erp_edoc_accounts_keep($provider, $card, $seen_at = 0)
{
    $external_id = mb_substr(trim((string) ($card['external_id'] ?? '')), 0, 64);

    if ($external_id === '') {
        return '';
    }

    $kind = (string) ($card['kind'] ?? 'customer');
    $country = strtoupper(trim((string) ($card['country_code'] ?? '')));

    $columns = array(
        'code' => mb_substr(trim((string) ($card['code'] ?? '')), 0, 100),
        'title' => mb_substr(trim((string) ($card['title'] ?? '')), 0, 255),
        'is_person' => !empty($card['is_person']) ? 1 : 0,
        'tax_number' => substr(erp_edoc_account_tax_key($card['tax_number'] ?? ''), 0, 32),
        'tax_office' => mb_substr(trim((string) ($card['tax_office'] ?? '')), 0, 100),
        'email' => mb_substr(trim((string) ($card['email'] ?? '')), 0, 255),
        'phone' => mb_substr(trim((string) ($card['phone'] ?? '')), 0, 50),
        'address' => mb_substr(trim((string) ($card['address'] ?? '')), 0, 500),
        'district' => mb_substr(trim((string) ($card['district'] ?? '')), 0, 100),
        'city' => mb_substr(trim((string) ($card['city'] ?? '')), 0, 100),
        'country_code' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
        'postcode' => mb_substr(trim((string) ($card['postcode'] ?? '')), 0, 20),
        'kind' => in_array($kind, array('customer', 'supplier', 'both'), true) ? $kind : 'customer',
        'is_active' => (!array_key_exists('is_active', $card) || !empty($card['is_active'])) ? 1 : 0,
    );

    // A card read on its own may not say when it changed (İşbaşı's detail
    // carries no date); the date the list gave is kept then.
    if ((int) ($card['updated_at'] ?? 0) > 0) {
        $columns['provider_updated_at'] = (int) $card['updated_at'];
    }

    if ((int) $seen_at > 0) {
        $columns['seen_at'] = (int) $seen_at;
    }

    $pairs = array();

    foreach ($columns as $column => $value) {
        $pairs[] = $column . " = '" . escape((string) $value) . "'";
    }

    $now = time();

    // Looked up first rather than INSERT ... ON DUPLICATE KEY UPDATE, which
    // spends an id on every card read again.
    $id = (int) db_value("SELECT id FROM erp_edoc_accounts
        WHERE provider = '" . escape((string) $provider) . "' AND external_id = '" . escape($external_id) . "' LIMIT 1");

    if ($id > 0) {
        $ok = erp_query("UPDATE erp_edoc_accounts SET " . implode(', ', $pairs) . ", updated_at = '" . $now . "' WHERE id = '" . $id . "'");

        return ($ok === false) ? '' : 'kept';
    }

    $ok = erp_query("INSERT INTO erp_edoc_accounts SET
            provider = '" . escape((string) $provider) . "',
            external_id = '" . escape($external_id) . "',
            status = 'new',
            created_at = '" . $now . "',
            updated_at = '" . $now . "',
            " . implode(",\n            ", $pairs));

    return ($ok === false) ? '' : 'added';
}

/**
 * Links new cards to accounts by tax number, where the answer is not in
 * doubt: the number is valid for its country, exactly one account carries
 * it, no other card of the provider carries it, and the account has no card
 * yet. A card somebody unlinked (no_auto_link) is left for them.
 *
 * @param string $provider
 * @return int  Cards linked
 */
function erp_edoc_accounts_autolink($provider)
{
    $provider_sql = escape((string) $provider);
    $walkin = erp_edoc_accounts_walkin_id();
    $linked = 0;

    $shared = array();
    foreach ((array) db_items("SELECT tax_number FROM erp_edoc_accounts
        WHERE provider = '" . $provider_sql . "' AND status <> 'ignored' AND tax_number <> ''
        GROUP BY tax_number HAVING COUNT(*) > 1") as $item) {
        $shared[(string) $item['tax_number']] = true;
    }

    $rows = (array) db_items("SELECT id, tax_number, country_code FROM erp_edoc_accounts
        WHERE provider = '" . $provider_sql . "' AND status = 'new' AND no_auto_link = 0 AND tax_number <> ''");
    $by_key = erp_edoc_accounts_by_tax_key();

    foreach ($rows as $row) {
        $number = (string) $row['tax_number'];

        if (isset($shared[$number]) || !erp_edoc_account_tax_number_usable($number, (string) $row['country_code'])) {
            continue;
        }

        $accounts = $by_key[$number] ?? array();

        if (count($accounts) !== 1) {
            continue;
        }

        $account_id = (int) $accounts[0]['id'];

        if (($account_id === $walkin) || (erp_edoc_account_card_of($provider, $account_id) !== null)) {
            continue;
        }

        $ok = erp_query("UPDATE erp_edoc_accounts SET
                status = 'linked',
                account_id = '" . $account_id . "',
                link_source = 'match',
                updated_at = '" . time() . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'new'");

        if (($ok !== false) && (mysqli_affected_rows(db::$con) > 0)) {
            $linked++;
        }
    }

    return $linked;
}

/* ---------------------------------------------------------------------------
   Rows
   --------------------------------------------------------------------------- */

/**
 * One kept card.
 *
 * @param int $id
 * @return array|null
 */
function erp_edoc_account_row($id)
{
    if (!erp_edoc_accounts_installed()) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_edoc_accounts WHERE id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The card an account is linked to at a provider, or null.
 *
 * @param string $provider
 * @param int    $account_id
 * @return array|null
 */
function erp_edoc_account_card_of($provider, $account_id)
{
    if (!erp_edoc_accounts_installed() || ((int) $account_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_edoc_accounts
        WHERE provider = '" . escape((string) $provider) . "' AND account_id = '" . (int) $account_id . "' AND status = 'linked'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Whether a card has gone from the provider: the last complete read did not
 * see it, and it was there before that read.
 *
 * @param array $row
 * @param array $settings  erp_edoc_settings() of its provider
 * @return bool
 */
function erp_edoc_account_gone($row, $settings)
{
    $read_at = (int) ($settings['accounts_read_at'] ?? 0);

    if (($read_at <= 0) || empty($settings['accounts_read_complete'])) {
        return false;
    }

    return max((int) $row['seen_at'], (int) $row['created_at']) < $read_at;
}

/**
 * The ERP accounts that carry a card's tax number, for linking by hand.
 *
 * @param array $row
 * @return array  erp_accounts rows, each with 'card_id' (the card it is linked to, 0 when none)
 */
function erp_edoc_account_candidates($row)
{
    $number = erp_edoc_account_tax_key($row['tax_number']);

    if (($number === '') || ($number === erp_edoc_final_consumer_tckn())) {
        return array();
    }

    $ids = array();

    foreach ((array) (erp_edoc_accounts_by_tax_key()[$number] ?? array()) as $account) {
        $ids[] = (int) $account['id'];
    }

    if (empty($ids)) {
        return array();
    }

    return (array) db_items("SELECT a.*, COALESCE(x.id, 0) AS card_id
        FROM erp_accounts a
        LEFT JOIN erp_edoc_accounts x ON x.account_id = a.id AND x.status = 'linked' AND x.provider = '" . escape((string) $row['provider']) . "'
        WHERE a.id IN (" . implode(', ', array_slice($ids, 0, 20)) . ")
        ORDER BY (a.status = 'active') DESC, a.id ASC");
}

/**
 * ERP accounts found by name, number or e-mail, for linking by hand.
 *
 * @param string $provider
 * @param string $query
 * @return array  erp_accounts rows, each with 'card_id'
 */
function erp_edoc_account_search($provider, $query)
{
    $query = trim((string) $query);

    if (mb_strlen($query) < 2) {
        return array();
    }

    $like = escape(str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $query));

    return (array) db_items("SELECT a.*, COALESCE(x.id, 0) AS card_id
        FROM erp_accounts a
        LEFT JOIN erp_edoc_accounts x ON x.account_id = a.id AND x.status = 'linked' AND x.provider = '" . escape((string) $provider) . "'
        WHERE a.title LIKE '%" . $like . "%' OR a.tax_number LIKE '%" . $like . "%' OR a.email LIKE '%" . $like . "%'
        ORDER BY (a.status = 'active') DESC, a.title ASC, a.id ASC
        LIMIT 20");
}

/**
 * Cards of a provider found by name, number or code, for linking an
 * account by hand.
 *
 * @param string $provider
 * @param string $query
 * @return array  erp_edoc_accounts rows
 */
function erp_edoc_account_card_search($provider, $query)
{
    $query = trim((string) $query);

    if (mb_strlen($query) < 2) {
        return array();
    }

    $like = escape(str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $query));

    return (array) db_items("SELECT * FROM erp_edoc_accounts
        WHERE provider = '" . escape((string) $provider) . "'
            AND (title LIKE '%" . $like . "%' OR tax_number LIKE '%" . $like . "%' OR code LIKE '%" . $like . "%')
        ORDER BY (status = 'new') DESC, title ASC, id ASC
        LIMIT 20");
}

/* ---------------------------------------------------------------------------
   The overview: where every card and account stands
   --------------------------------------------------------------------------- */

/**
 * The tabs of the sync screen.
 *
 * @param string $label  The provider's name
 * @return array  tab => ['label', 'tone']
 */
function erp_edoc_accounts_tabs($label)
{
    return array(
        'differ' => array('label' => lang('Different'), 'tone' => 'warning'),
        'provider_only' => array('label' => lang(array('string' => 'Only at {var:1}', 'vars' => $label)), 'tone' => 'primary'),
        'erp_only' => array('label' => lang('Only in the ERP'), 'tone' => 'primary'),
        'same' => array('label' => lang('The same'), 'tone' => 'success'),
        'ignored' => array('label' => lang('Put aside'), 'tone' => 'secondary'),
    );
}

/**
 * Every card of a provider and every account that could have one, sorted
 * into the tabs. A linked card carries its account (a_* columns) and its
 * differences ('diff'); a card that is not linked carries what speaks for
 * a link ('hint', 'candidates').
 *
 * @param string $provider
 * @return array  tab => rows; the erp_only and ignored_accounts tabs hold erp_accounts rows
 */
function erp_edoc_accounts_overview($provider)
{
    $groups = array('differ' => array(), 'provider_only' => array(), 'erp_only' => array(), 'same' => array(), 'ignored' => array(), 'ignored_accounts' => array());

    if (!erp_edoc_accounts_installed() || ((string) $provider === '')) {
        return $groups;
    }

    $provider_sql = escape((string) $provider);
    $settings = erp_edoc_settings($provider);

    $rows = (array) db_items("SELECT x.*, a.id AS a_id, a.title AS a_title, a.is_person AS a_is_person,
            a.tax_number AS a_tax_number, a.tax_office AS a_tax_office, a.email AS a_email, a.phone AS a_phone,
            a.address AS a_address, a.district AS a_district, a.city AS a_city, a.country_code AS a_country_code,
            a.postcode AS a_postcode, a.kind AS a_kind, a.status AS a_status
        FROM erp_edoc_accounts x
        LEFT JOIN erp_accounts a ON a.id = x.account_id AND x.status = 'linked'
        WHERE x.provider = '" . $provider_sql . "'
        ORDER BY x.title ASC, x.id ASC");

    // How many cards share a number, and which accounts are already linked
    // to a card; the accounts by number come from one query for the request.
    $shared = array();
    $linked_accounts = array();

    foreach ($rows as $row) {
        $number = (string) $row['tax_number'];

        if (($row['status'] !== 'ignored') && ($number !== '')) {
            $shared[$number] = ($shared[$number] ?? 0) + 1;
        }

        if (($row['status'] === 'linked') && ((int) $row['account_id'] > 0)) {
            $linked_accounts[(int) $row['account_id']] = (int) $row['id'];
        }
    }

    $by_key = erp_edoc_accounts_by_tax_key();

    foreach ($rows as $row) {
        $row['gone'] = erp_edoc_account_gone($row, $settings);

        if ((string) $row['status'] === 'ignored') {
            $groups['ignored'][] = $row;
            continue;
        }

        if (((string) $row['status'] === 'linked') && ((int) $row['a_id'] > 0)) {
            $erp = erp_edoc_account_erp_card(array(
                'title' => $row['a_title'], 'is_person' => $row['a_is_person'], 'tax_number' => $row['a_tax_number'],
                'tax_office' => $row['a_tax_office'], 'email' => $row['a_email'], 'phone' => $row['a_phone'],
                'address' => $row['a_address'], 'district' => $row['a_district'], 'city' => $row['a_city'],
                'country_code' => $row['a_country_code'], 'postcode' => $row['a_postcode'], 'kind' => $row['a_kind'],
                'status' => $row['a_status'],
            ));
            $row['diff'] = erp_edoc_account_diff($erp, erp_edoc_account_stored_card($row));
            $groups[empty($row['diff']) ? 'same' : 'differ'][] = $row;
            continue;
        }

        $number = (string) $row['tax_number'];
        $row['candidates'] = array();

        if (($number !== '') && erp_edoc_account_tax_number_usable($number, (string) $row['country_code'])) {
            foreach ((array) ($by_key[$number] ?? array()) as $account) {
                $row['candidates'][] = $account + array('card_id' => $linked_accounts[(int) $account['id']] ?? 0);
            }
        }

        if (($number === '') || ($number === erp_edoc_final_consumer_tckn())) {
            $row['hint'] = lang('No identity number');
        } elseif (!erp_edoc_account_tax_number_usable($number, (string) $row['country_code'])) {
            $row['hint'] = lang('Not a valid tax number');
        } elseif (($shared[$number] ?? 0) > 1) {
            $row['hint'] = lang(array('string' => '{var:1} cards carry this number', 'vars' => (int) $shared[$number]));
        } elseif (!empty($row['no_auto_link'])) {
            $row['hint'] = lang('Unlinked by hand');
        } else {
            $row['hint'] = '';
        }

        $groups['provider_only'][] = $row;
    }

    list($groups['erp_only'], $groups['ignored_accounts']) = erp_edoc_accounts_erp_only($provider);

    return $groups;
}

/**
 * The accounts that could go to the provider: active, with a tax number
 * that is valid for the account's country (erp_edoc_account_tax_number_usable()),
 * not the walk-in account, with no card linked and no card of the provider
 * carrying the same number (that one is linked, not sent again). Accounts
 * put aside come back separately.
 *
 * @param string $provider
 * @return array  [candidates, put aside] - erp_accounts rows
 */
function erp_edoc_accounts_erp_only($provider)
{
    $provider_sql = escape((string) $provider);
    $ignored = array_flip(erp_edoc_accounts_ignored_ids($provider));
    $walkin = erp_edoc_accounts_walkin_id();

    $rows = (array) db_items("SELECT a.* FROM erp_accounts a
        WHERE a.status = 'active'
            AND a.tax_number <> ''
            AND a.id <> '" . $walkin . "'
            AND NOT EXISTS (SELECT 1 FROM erp_edoc_accounts x
                WHERE x.provider = '" . $provider_sql . "' AND x.account_id = a.id AND x.status = 'linked')
        ORDER BY a.title ASC, a.id ASC
        LIMIT 5000");

    // The numbers the provider's cards carry, kept as matched (letters and
    // digits); the account's is brought to the same shape in PHP.
    $known = array();

    foreach ((array) db_items("SELECT DISTINCT tax_number FROM erp_edoc_accounts
        WHERE provider = '" . $provider_sql . "' AND tax_number <> ''") as $card) {
        $known[(string) $card['tax_number']] = true;
    }

    $candidates = array();
    $aside = array();

    foreach ($rows as $row) {
        if (!erp_edoc_account_tax_number_usable((string) $row['tax_number'], (string) $row['country_code'])
            || isset($known[erp_edoc_account_tax_key($row['tax_number'])])) {
            continue;
        }

        if (isset($ignored[(int) $row['id']])) {
            $aside[] = $row;
        } else {
            $candidates[] = $row;
        }
    }

    return array($candidates, $aside);
}

/**
 * The accounts put aside from "only in the ERP", kept in the provider's
 * settings blob: there is no card row to carry the mark.
 *
 * @param string $provider
 * @return int[]
 */
function erp_edoc_accounts_ignored_ids($provider)
{
    $settings = erp_edoc_settings($provider);

    return array_values(array_filter(array_map('intval', (array) ($settings['accounts_ignored'] ?? array()))));
}

/**
 * Puts accounts aside from "only in the ERP", or brings them back.
 *
 * @param string $provider
 * @param int[]  $account_ids
 * @param bool   $aside
 * @return bool
 */
function erp_edoc_accounts_erp_set_aside($provider, $account_ids, $aside)
{
    $ids = array_flip(erp_edoc_accounts_ignored_ids($provider));

    foreach ((array) $account_ids as $account_id) {
        if ((int) $account_id <= 0) {
            continue;
        }

        if ($aside) {
            $ids[(int) $account_id] = true;
        } else {
            unset($ids[(int) $account_id]);
        }
    }

    // The list lives in a TEXT column beside the session; the newest five
    // thousand are more than any store sets aside.
    return erp_edoc_settings_save($provider, array('accounts_ignored' => array_slice(array_map('intval', array_keys($ids)), -5000)));
}

/* ---------------------------------------------------------------------------
   Links
   --------------------------------------------------------------------------- */

/**
 * Links a card to an account. One account has at most one card at a
 * provider, and a card belongs to one account.
 *
 * @param int    $id          The card (erp_edoc_accounts.id)
 * @param int    $account_id
 * @param int    $user_id
 * @param string $source      'manual' | 'match' | 'invoice' | 'created' | 'imported'
 * @return array ['success' => bool, 'error' => string]
 */
function erp_edoc_account_link($id, $account_id, $user_id = 0, $source = 'manual')
{
    $row = erp_edoc_account_row($id);
    $account = erp_account((int) $account_id);

    if ($row === null) {
        return array('success' => false, 'error' => lang('The card could not be found.'));
    }

    if ($account === null) {
        return array('success' => false, 'error' => lang('The account could not be found.'));
    }

    if (((string) $row['status'] === 'linked') && ((int) $row['account_id'] === (int) $account['id'])) {
        return array('success' => true, 'error' => '');
    }

    if ((string) $row['status'] === 'linked') {
        return array('success' => false, 'error' => lang('This card is linked to another account. Undo that link first.'));
    }

    $other = erp_edoc_account_card_of((string) $row['provider'], (int) $account['id']);

    if ($other !== null) {
        return array('success' => false, 'error' => lang(array(
            'string' => 'This account is already linked to another card of {var:1} ({var:2}).',
            'vars' => array(erp_edoc_info((string) $row['provider'])['label'], (string) $other['title']),
        )));
    }

    $ok = erp_query("UPDATE erp_edoc_accounts SET
            status = 'linked',
            account_id = '" . (int) $account['id'] . "',
            link_source = '" . escape(mb_substr((string) $source, 0, 10)) . "',
            no_auto_link = 0,
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status <> 'linked'");

    if ($ok === false) {
        return array('success' => false, 'error' => erp_db_error());
    }

    return array('success' => true, 'error' => '');
}

/**
 * Undoes a link. Nothing on either record changes; the card waits again
 * and is not linked by its number on the next read.
 *
 * @param int $id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_edoc_account_unlink($id, $user_id = 0)
{
    $row = erp_edoc_account_row($id);

    if (($row === null) || ((string) $row['status'] !== 'linked')) {
        return array('success' => false, 'error' => lang('The card is not linked.'));
    }

    $ok = erp_query("UPDATE erp_edoc_accounts SET
            status = 'new',
            account_id = 0,
            link_source = '',
            no_auto_link = 1,
            synced_at = 0,
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    return ($ok === false)
        ? array('success' => false, 'error' => erp_db_error())
        : array('success' => true, 'error' => '');
}

/**
 * Sets a card aside, or brings it back. A linked card is unlinked first.
 *
 * @param int  $id
 * @param bool $aside
 * @param int  $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_edoc_account_set_aside($id, $aside, $user_id = 0)
{
    $row = erp_edoc_account_row($id);

    if ($row === null) {
        return array('success' => false, 'error' => lang('The card could not be found.'));
    }

    if ((string) $row['status'] === 'linked') {
        return array('success' => false, 'error' => lang('A linked card is not put aside; undo the link first.'));
    }

    $ok = erp_query("UPDATE erp_edoc_accounts SET
            status = '" . ($aside ? 'ignored' : 'new') . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    return ($ok === false)
        ? array('success' => false, 'error' => erp_db_error())
        : array('success' => true, 'error' => '');
}

/**
 * Reads one card again from the provider and keeps what it says.
 *
 * @param int $id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_edoc_account_refresh($id)
{
    $row = erp_edoc_account_row($id);

    if ($row === null) {
        return array('success' => false, 'error' => lang('The card could not be found.'));
    }

    $provider = (string) $row['provider'];

    if (!erp_edoc_supports('account', $provider)) {
        return array('success' => false, 'error' => lang('The e-document provider in use does not hand over its customer and supplier cards.'));
    }

    $read = erp_edoc_call('account', array((string) $row['external_id']), $provider);

    if (empty($read['success'])) {
        return array('success' => false, 'error' => (string) $read['error']);
    }

    $card = (array) ($read['card'] ?? array());
    $card['external_id'] = (string) $row['external_id'];
    erp_edoc_accounts_keep($provider, $card, time());

    return array('success' => true, 'error' => '');
}

/* ---------------------------------------------------------------------------
   Writing: into the ERP, to the provider, field by field
   --------------------------------------------------------------------------- */

/**
 * Everything erp_account_save() writes, from the account as it stands, with
 * the given card fields over it. erp_account_save() writes every column it
 * knows on an update, so a partial array would blank the rest.
 *
 * @param array $account    An erp_accounts row
 * @param array $overrides  Card fields
 * @return array
 */
function erp_edoc_account_save_data($account, $overrides)
{
    $data = array(
        'id' => (int) $account['id'],
        'kind' => (string) $account['kind'],
        'title' => (string) $account['title'],
        'is_person' => ((int) $account['is_person'] === 1),
        'tax_number' => (string) $account['tax_number'],
        'tax_office' => (string) $account['tax_office'],
        'email' => (string) $account['email'],
        'phone' => (string) $account['phone'],
        'address' => (string) $account['address'],
        'district' => (string) $account['district'],
        'city' => (string) $account['city'],
        'country_code' => (string) $account['country_code'],
        'postcode' => (string) $account['postcode'],
        'currency' => (string) $account['currency'],
        'status' => (string) $account['status'],
        'notes' => (string) $account['notes'],
        'payment_days' => (int) ($account['payment_days'] ?? 0),
    );

    if (array_key_exists('overdue_notify_days', $account)) {
        $data['overdue_notify_days'] = (int) $account['overdue_notify_days'];
    }

    if (array_key_exists('overdue_notify_customer', $account)) {
        $data['overdue_notify_customer'] = ((int) $account['overdue_notify_customer'] === 1);
    }

    foreach ($overrides as $field => $value) {
        switch ($field) {
            case 'is_active':
                $data['status'] = !empty($value) ? 'active' : 'passive';
                break;
            case 'is_person':
                $data['is_person'] = !empty($value);
                break;
            case 'tax_number':
                $data['tax_number'] = erp_edoc_account_tax_number_for_erp($value, (string) ($overrides['country_code'] ?? $data['country_code']));
                break;
            case 'country_code':
                $data['country_code'] = strtoupper(trim((string) $value));
                break;
            case 'kind':
                $data['kind'] = in_array((string) $value, array('customer', 'supplier', 'both'), true) ? (string) $value : $data['kind'];
                break;
            case 'tax_office':
            case 'district':
            case 'city':
                $data[$field] = mb_substr(trim((string) $value), 0, 100);
                break;
            case 'phone':
                $data[$field] = mb_substr(trim((string) $value), 0, 50);
                break;
            case 'postcode':
                $data[$field] = mb_substr(trim((string) $value), 0, 20);
                break;
            default:
                if (array_key_exists($field, $data)) {
                    $data[$field] = mb_substr(trim((string) $value), 0, 255);
                }
        }
    }

    return $data;
}

/**
 * Takes a card into the ERP as a new account, linked to it.
 *
 * An account already carrying the card's number (one, and without a card
 * of its own) is linked instead of opening a second one. A value the
 * account card would refuse - a number whose check digits fail, a postcode
 * of the wrong shape - is left out and named in 'notes'.
 *
 * @param int $id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string, 'account_id' => int, 'linked' => bool, 'notes' => string[]]
 */
function erp_edoc_account_import($id, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'account_id' => 0, 'linked' => false, 'notes' => array());
    };

    $row = erp_edoc_account_row($id);

    if ($row === null) {
        return $fail(lang('The card could not be found.'));
    }

    if ((string) $row['status'] !== 'new') {
        return $fail(((string) $row['status'] === 'linked')
            ? lang('This card is already linked to an account.')
            : lang('Bring the card back before taking it in.'));
    }

    $card = erp_edoc_account_stored_card($row);
    $number = erp_edoc_account_tax_key($card['tax_number']);

    if (erp_edoc_account_tax_number_usable($number, (string) $card['country_code'])) {
        $existing = (array) (erp_edoc_accounts_by_tax_key(true)[$number] ?? array());

        if (count($existing) > 1) {
            return $fail(lang('Several accounts carry this number; link the card to the right one by hand.'));
        }

        if (count($existing) === 1) {
            $linked = erp_edoc_account_link($id, (int) $existing[0]['id'], $user_id, 'match');

            return $linked['success']
                ? array('success' => true, 'error' => '', 'account_id' => (int) $existing[0]['id'], 'linked' => true, 'notes' => array())
                : $fail($linked['error']);
        }
    }

    $notes = array();
    $values = $card;

    if (mb_strlen(trim((string) $values['address'])) > 255) {
        $values['address'] = mb_substr(trim((string) $values['address']), 0, 255);
        $notes[] = erp_edoc_account_fields()['address'] . ': ' . lang(array('string' => 'shortened to {var:1} characters.', 'vars' => 255));
    }

    foreach (array('tax_number', 'postcode', 'country_code') as $field) {
        $refusal = erp_edoc_account_erp_refusal($field, $values[$field], $values);

        if ($refusal !== '') {
            // The final-consumer number is simply no number on a card.
            if (!(($field === 'tax_number') && ($number === erp_edoc_final_consumer_tckn()))) {
                $notes[] = erp_edoc_account_fields()[$field] . ': ' . $refusal;
            }

            $values[$field] = '';
        }
    }

    if (trim((string) $values['title']) === '') {
        return $fail(lang('The card has no name.'));
    }

    $label = erp_edoc_info((string) $row['provider'])['label'];

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $locked = db_item("SELECT status FROM erp_edoc_accounts WHERE id = '" . (int) $row['id'] . "' LIMIT 1 FOR UPDATE");

    if (!is_array($locked) || ((string) $locked['status'] !== 'new')) {
        erp_tx_rollback();
        return $fail(lang('This card has already been dealt with.'));
    }

    $saved = erp_account_save(array(
        'kind' => $values['kind'],
        'title' => mb_substr(trim((string) $values['title']), 0, 255),
        'is_person' => !empty($values['is_person']),
        'tax_number' => $values['tax_number'],
        'tax_office' => mb_substr((string) $values['tax_office'], 0, 100),
        'email' => mb_substr((string) $values['email'], 0, 255),
        'phone' => mb_substr((string) $values['phone'], 0, 50),
        'address' => (string) $values['address'],
        'district' => mb_substr((string) $values['district'], 0, 100),
        'city' => mb_substr((string) $values['city'], 0, 100),
        'country_code' => ((string) $values['country_code'] !== '') ? $values['country_code'] : erp_default_country_code(),
        'postcode' => (string) $values['postcode'],
        'currency' => erp_base_currency(),
        'status' => !empty($values['is_active']) ? 'active' : 'passive',
        'notes' => lang(array('string' => 'Taken in from {var:1} (card {var:2}).', 'vars' => array($label, ((string) $row['code'] !== '') ? (string) $row['code'] : (string) $row['external_id']))),
        'created_by' => (int) $user_id,
    ));

    if (!$saved['success']) {
        erp_tx_rollback();
        return $fail($saved['error']);
    }

    $account_id = (int) $saved['id'];

    $ok = erp_query("UPDATE erp_edoc_accounts SET
            status = 'linked',
            account_id = '" . $account_id . "',
            link_source = 'imported',
            no_auto_link = 0,
            synced_at = '" . time() . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    if (($ok === false) || !erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp account ({var:1}) was taken in from {var:2}', 'vars' => array((string) $values['title'], $label))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return array('success' => true, 'error' => '', 'account_id' => $account_id, 'linked' => false, 'notes' => $notes);
}

/**
 * Sends an account to the provider as a new card, linked to it. The card
 * gets the code PG-<account id>, so it can be told apart at the provider.
 *
 * Only an account the provider does not know yet: one with a card linked,
 * or whose number is on a card already, is linked rather than sent again.
 *
 * @param int $account_id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string, 'card_id' => int, 'message' => string]
 */
function erp_edoc_account_push($account_id, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'card_id' => 0, 'message' => '');
    };

    $provider = erp_edoc_accounts_provider();

    if ($provider === '') {
        return $fail(lang('The e-document provider in use does not hand over its customer and supplier cards.'));
    }

    $label = erp_edoc_info($provider)['label'];
    $account = erp_account((int) $account_id);

    if ($account === null) {
        return $fail(lang('The account could not be found.'));
    }

    if ((int) $account['id'] === erp_edoc_accounts_walkin_id()) {
        return $fail(lang('The walk-in account is not sent: its sales go out with the final-consumer number.'));
    }

    if (erp_edoc_account_card_of($provider, (int) $account['id']) !== null) {
        return $fail(lang(array('string' => 'This account is already linked to a card of {var:1}.', 'vars' => $label)));
    }

    $number = erp_edoc_account_tax_key($account['tax_number']);

    if (!erp_edoc_account_tax_number_usable($number, (string) $account['country_code'])) {
        return $fail(lang('Only an account with a valid tax number is sent.'));
    }

    $known = db_item("SELECT id, title FROM erp_edoc_accounts
        WHERE provider = '" . escape($provider) . "' AND tax_number = '" . escape($number) . "' LIMIT 1");

    if (is_array($known)) {
        return $fail(lang(array(
            'string' => '{var:1} already has a card with this number ({var:2}); link it instead of opening another.',
            'vars' => array($label, (string) $known['title']),
        )));
    }

    $card = erp_edoc_account_erp_card($account);

    if (trim((string) $card['title']) === '') {
        return $fail(lang('Enter a name.'));
    }

    $card['code'] = 'PG-' . (int) $account['id'];

    if ((int) ($account['einvoice_checked_at'] ?? 0) > 0) {
        $card['einvoice_user'] = ((int) $account['einvoice_user'] === 1);
        $card['einvoice_alias'] = (string) $account['einvoice_alias'];
    }

    $saved = erp_edoc_call('account_save', array($card, ''), $provider);

    if (empty($saved['success'])) {
        return $fail((string) $saved['error']);
    }

    $external_id = trim((string) ($saved['external_id'] ?? ''));

    // Read back, so the row holds what the provider made of it.
    $read = erp_edoc_call('account', array($external_id), $provider);
    $stored = !empty($read['success']) ? (array) $read['card'] : ($card + array('kind' => $account['kind']));
    $stored['external_id'] = $external_id;
    erp_edoc_accounts_keep($provider, $stored, 0);

    $row = db_item("SELECT id, status, account_id FROM erp_edoc_accounts
        WHERE provider = '" . escape($provider) . "' AND external_id = '" . escape($external_id) . "' LIMIT 1");

    if (!is_array($row)) {
        return $fail(lang(array('string' => '{var:1} took the card (id {var:2}) but it could not be kept here. Read the list again.', 'vars' => array($label, $external_id))));
    }

    if (((string) $row['status'] === 'linked') && ((int) $row['account_id'] !== (int) $account['id'])) {
        return $fail(lang(array('string' => '{var:1} answered with card {var:2}, which is linked to another account here.', 'vars' => array($label, $external_id))));
    }

    erp_query("UPDATE erp_edoc_accounts SET
            status = 'linked',
            account_id = '" . (int) $account['id'] . "',
            link_source = 'created',
            no_auto_link = 0,
            synced_at = '" . time() . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    erp_edoc_accounts_erp_set_aside($provider, array((int) $account['id']), false);

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp account ({var:1}) was sent to {var:2}', 'vars' => array((string) $account['title'], $label))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return array(
        'success' => true,
        'error' => '',
        'card_id' => (int) $row['id'],
        'message' => lang(array('string' => '{var:1} opened the card (id {var:2}).', 'vars' => array($label, $external_id))),
    );
}

/**
 * Aligns a linked pair field by field, each in the direction chosen.
 *
 * The provider is written first: it is the side that can refuse, and a
 * refusal there leaves the ERP as it was. The card is then read back and
 * a value the provider did not keep is named in 'not_taken' - a provider
 * that quietly drops a field must not look aligned.
 *
 * @param int   $id
 * @param array $choices  field => 'erp' (the ERP's value goes to the provider) |
 *                        'provider' (the provider's value comes into the ERP) | 'keep'
 * @param int   $user_id
 * @return array ['success' => bool, 'error' => string, 'message' => string, 'not_taken' => string[]]
 */
function erp_edoc_account_align($id, $choices, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'message' => '', 'not_taken' => array());
    };

    $row = erp_edoc_account_row($id);

    if (($row === null) || ((string) $row['status'] !== 'linked')) {
        return $fail(lang('The card is not linked.'));
    }

    $provider = (string) $row['provider'];
    $label = erp_edoc_info($provider)['label'];
    $account = erp_account((int) $row['account_id']);

    if ($account === null) {
        return $fail(lang('The account could not be found.'));
    }

    $labels = erp_edoc_account_fields();
    $erp = erp_edoc_account_erp_card($account);
    $card = erp_edoc_account_stored_card($row);
    $diff = erp_edoc_account_diff($erp, $card);
    $to_provider = array();
    $to_erp = array();

    foreach ((array) $choices as $field => $direction) {
        if (!isset($diff[$field])) {
            continue;
        }

        if ($direction === 'erp') {
            $to_provider[$field] = $erp[$field];
        } elseif ($direction === 'provider') {
            $to_erp[$field] = $card[$field];
        }
    }

    if (empty($to_provider) && empty($to_erp)) {
        return $fail(lang('Choose at least one field to align.'));
    }

    $after = array_merge($erp, $to_erp);
    $errors = array();

    foreach ($to_erp as $field => $value) {
        $refusal = erp_edoc_account_erp_refusal($field, $value, $after);

        if ($refusal !== '') {
            $errors[] = $labels[$field] . ': ' . $refusal;
        }
    }

    foreach ($to_provider as $field => $value) {
        $refusal = erp_edoc_account_provider_refusal($field, $value, $erp);

        if ($refusal !== '') {
            $errors[] = $labels[$field] . ': ' . $refusal;
        }
    }

    if (!empty($errors)) {
        return $fail(implode(' ', $errors));
    }

    if (!empty($to_provider) && !erp_edoc_supports('account_save', $provider)) {
        return $fail(lang('The e-document provider in use does not hand over its customer and supplier cards.'));
    }

    $not_taken = array();

    if (!empty($to_provider)) {
        // A person's name is split into its parts at the provider, so a
        // change of taxpayer type travels with the name it applies to.
        if (isset($to_provider['is_person']) && !isset($to_provider['title'])) {
            $to_provider['title'] = isset($to_erp['title']) ? $to_erp['title'] : $card['title'];
        }

        $saved = erp_edoc_call('account_save', array($to_provider, (string) $row['external_id']), $provider);

        if (empty($saved['success'])) {
            return $fail(lang(array('string' => '{var:1} did not take the change: {var:2}', 'vars' => array($label, (string) $saved['error']))));
        }

        $read = erp_edoc_call('account', array((string) $row['external_id']), $provider);

        if (!empty($read['success'])) {
            $back = (array) $read['card'];
            $back['external_id'] = (string) $row['external_id'];
            erp_edoc_accounts_keep($provider, $back, 0);

            foreach ($to_provider as $field => $value) {
                if (isset($labels[$field]) && (erp_edoc_account_norm($field, $back[$field] ?? '') !== erp_edoc_account_norm($field, $value))) {
                    $not_taken[] = $labels[$field];
                }
            }
        }
    }

    if (!empty($to_erp)) {
        if (!erp_tx_begin()) {
            return $fail(lang('Could not start a database transaction.'));
        }

        $saved = erp_account_save(erp_edoc_account_save_data($account, $to_erp));

        // The e-Invoice answer belonged to the old number.
        $ok = $saved['success'];

        if ($ok && isset($to_erp['tax_number'])) {
            $ok = (erp_query("UPDATE erp_accounts SET einvoice_user = 0, einvoice_alias = '', einvoice_aliases = NULL, einvoice_checked_at = 0
                WHERE id = '" . (int) $account['id'] . "'") !== false);
        }

        if (!$ok || !erp_tx_commit()) {
            $error = $saved['success'] ? erp_db_error() : (string) $saved['error'];
            erp_tx_rollback();

            return $fail(empty($to_provider)
                ? $error
                : lang(array('string' => '{var:1} took its part, but the ERP card could not be saved: {var:2}', 'vars' => array($label, $error))));
        }
    }

    erp_query("UPDATE erp_edoc_accounts SET
            synced_at = '" . time() . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp account ({var:1}) was aligned with {var:2}: {var:3}', 'vars' => array(
                (string) $account['title'],
                $label,
                implode(', ', array_merge(
                    array_map(function ($field) use ($labels) { return $labels[$field] . ' →'; }, array_keys($to_provider)),
                    array_map(function ($field) use ($labels) { return '← ' . $labels[$field]; }, array_keys($to_erp))
                )),
            ))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    $message = array();

    if (!empty($to_provider)) {
        $message[] = lang(array('string' => '{var:1} field(s) written to {var:2}.', 'vars' => array(count($to_provider), $label)));
    }

    if (!empty($to_erp)) {
        $message[] = lang(array('string' => '{var:1} field(s) written to the ERP account.', 'vars' => count($to_erp)));
    }

    return array('success' => true, 'error' => '', 'message' => implode(' ', $message), 'not_taken' => $not_taken);
}

/* ---------------------------------------------------------------------------
   Invoices: the card an invoice is put on
   --------------------------------------------------------------------------- */

/**
 * The provider's code of the card an invoice's account is linked to, for
 * the provider to put the invoice on that card instead of looking one up.
 *
 * Only when the link is beyond doubt: the card carries the very number the
 * invoice is going out with, and that number is a real one. The walk-in
 * account and a buyer without a number (the final-consumer eleven ones)
 * never get a code - one card for every counter sale would print one
 * buyer's name on everybody's document.
 *
 * @param string $provider
 * @param int    $account_id
 * @param string $tax_number    The number the invoice carries
 * @param string $country_code  The buyer's country on the invoice
 * @return string  '' when the provider should find the card itself
 */
function erp_edoc_account_link_code($provider, $account_id, $tax_number, $country_code = '')
{
    if (!erp_edoc_accounts_installed() || ((int) $account_id <= 0) || ((int) $account_id === erp_edoc_accounts_walkin_id())) {
        return '';
    }

    $number = erp_edoc_account_tax_key($tax_number);

    if (!erp_edoc_account_tax_number_usable($number, $country_code)) {
        return '';
    }

    $row = erp_edoc_account_card_of($provider, (int) $account_id);

    if (($row === null) || ((string) $row['tax_number'] !== $number)) {
        return '';
    }

    return trim((string) $row['code']);
}

/**
 * Links an invoice's account to the card the provider put the invoice on,
 * when the account has no card yet. What the provider says about the card
 * comes with the invoice's status (poll()'s 'party'); the rest of the card
 * arrives with the next read of the list.
 *
 * A card that was set aside, linked elsewhere or unlinked by hand is left
 * alone, and so are the walk-in account and a buyer without a real number.
 *
 * @param string $provider
 * @param array  $invoice  An erp_invoices row
 * @param array  $party    ['external_id', 'code', 'title']
 * @return bool  Whether a link was made
 */
function erp_edoc_account_link_from_invoice($provider, $invoice, $party)
{
    $external_id = mb_substr(trim((string) ($party['external_id'] ?? '')), 0, 64);
    $account_id = (int) ($invoice['account_id'] ?? 0);

    if (!erp_edoc_accounts_installed() || ($external_id === '') || ($account_id <= 0)
        || ($account_id === erp_edoc_accounts_walkin_id()) || !erp_edoc_has_capability('accounts', $provider)) {
        return false;
    }

    $buyer = erp_edoc_invoice_party($invoice);
    $number = erp_edoc_account_tax_key($buyer['tax_number'] ?? '');

    if (!erp_edoc_account_tax_number_usable($number, (string) ($buyer['country_code'] ?? ''))
        || (erp_edoc_account_card_of($provider, $account_id) !== null)) {
        return false;
    }

    $row = db_item("SELECT id, status, tax_number, no_auto_link FROM erp_edoc_accounts
        WHERE provider = '" . escape((string) $provider) . "' AND external_id = '" . escape($external_id) . "' LIMIT 1");

    if (is_array($row)) {
        if (((string) $row['status'] !== 'new') || !empty($row['no_auto_link'])
            || (((string) $row['tax_number'] !== '') && ((string) $row['tax_number'] !== $number))) {
            return false;
        }

        return erp_edoc_account_link((int) $row['id'], $account_id, 0, 'invoice')['success'];
    }

    $now = time();

    $ok = erp_query("INSERT INTO erp_edoc_accounts SET
            provider = '" . escape((string) $provider) . "',
            external_id = '" . escape($external_id) . "',
            code = '" . escape(mb_substr(trim((string) ($party['code'] ?? '')), 0, 100)) . "',
            title = '" . escape(mb_substr(trim((string) (($party['title'] ?? '') !== '' ? $party['title'] : ($buyer['title'] ?? ''))), 0, 255)) . "',
            is_person = '" . (!empty($buyer['is_person']) ? 1 : 0) . "',
            tax_number = '" . escape($number) . "',
            status = 'linked',
            account_id = '" . $account_id . "',
            link_source = 'invoice',
            created_at = '" . $now . "',
            updated_at = '" . $now . "'");

    if ($ok === false) {
        return false;
    }

    // The rest of the card, now rather than at the next read of the list:
    // a card kept with its name alone would look different on every field.
    erp_edoc_account_refresh((int) mysqli_insert_id(db::$con));

    return true;
}
