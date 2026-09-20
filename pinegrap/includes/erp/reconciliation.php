<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the account reconciliation letter.
 *
 * A letter to a counterparty: "as of this date our records show this balance
 * on your account; here are the movements behind it; please tell us whether
 * you agree". It is read from the ledger and rendered like the invoice and the
 * delivery note - the same template engine, the same PDF library. Nothing is
 * written: a reconciliation letter is a statement of what the books say, and
 * the books are not changed by sending one.
 *
 * The figures are the ledger's, taken as of a date, so a letter for the end
 * of last month says what the account looked like then even when movements
 * have been posted since. The cached balance on the account row is never
 * used here for the same reason.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}


if (!defined('ERP_RECONCILIATION_MAX_ROWS')) {
    // More than this many movements in the period and the earliest ones are
    // folded into the opening balance; the letter says how many. A statement
    // that runs to forty pages is not read, and the closing figure is what the
    // counterparty is asked to agree to.
    define('ERP_RECONCILIATION_MAX_ROWS', 400);
}

if (!defined('ERP_RECONCILIATION_MAX_REPLY_DAYS')) {
    define('ERP_RECONCILIATION_MAX_REPLY_DAYS', 90);
}

/**
 * Normalise what the screen or the URL asked for.
 *
 * Dates arrive as Y-m-d. The balance date cannot be after today (a balance as
 * of tomorrow is a forecast, not a statement) and the period cannot start
 * after it ends. Anything unusable falls back to a default rather than to an
 * error: a letter can always be produced.
 *
 * @param array $input  as_of, from, reply_days (all optional)
 * @return array  ['as_of' => Y-m-d, 'from' => Y-m-d, 'reply_days' => int]
 */
function erp_reconciliation_options($input)
{
    $input = (array) $input;
    $today = date('Y-m-d');

    $valid = function ($value) {
        $value = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $parts = explode('-', $value);

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $value : '';
    };

    $as_of = $valid($input['as_of'] ?? '');
    if (($as_of === '') || ($as_of > $today)) {
        $as_of = $today;
    }

    $from = $valid($input['from'] ?? '');
    if (($from === '') || ($from > $as_of)) {
        $from = substr($as_of, 0, 4) . '-01-01';
    }

    $reply_days = isset($input['reply_days']) ? (int) $input['reply_days'] : 7;
    if (($reply_days < 0) || ($reply_days > ERP_RECONCILIATION_MAX_REPLY_DAYS)) {
        $reply_days = 7;
    }

    return array('as_of' => $as_of, 'from' => $from, 'reply_days' => $reply_days);
}

/**
 * The letter's reference: date and account, readable and reproducible, so
 * two letters for the same account and date carry the same reference and a
 * reply can quote it. It is not a document number and spends nothing from
 * any series.
 *
 * @param int    $account_id
 * @param string $as_of  Y-m-d
 * @return string
 */
function erp_reconciliation_reference($account_id, $as_of)
{
    return 'MUT-' . str_replace('-', '', (string) $as_of) . '-' . (int) $account_id;
}

/**
 * An account's position as of a date, straight from the ledger.
 *
 * Positive is a debit balance (the counterparty owes the store), negative a
 * credit balance (the store owes the counterparty) - the ledger's own sign.
 * The own-currency figure counts only the movements made in the account's
 * currency, the way erp_account_refresh_balance() does.
 *
 * @param int    $account_id
 * @param string $as_of  Y-m-d, inclusive
 * @return array  ['base' => int kurus, 'fc' => int, 'currency' => string]
 */
function erp_reconciliation_balance($account_id, $as_of)
{
    $account_id = (int) $account_id;
    $currency = strtoupper(trim((string) db_value("SELECT currency FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1")));

    $base = (int) db_value("SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_base ELSE -amount_base END), 0)
        FROM erp_account_transactions
        WHERE account_id = '" . $account_id . "' AND doc_date <= '" . escape($as_of) . "'");

    $fc = $base;
    if (($currency !== '') && ($currency !== erp_base_currency())) {
        $fc = (int) db_value("SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)
            FROM erp_account_transactions
            WHERE account_id = '" . $account_id . "' AND doc_date <= '" . escape($as_of) . "' AND currency = '" . escape($currency) . "'");
    }

    return array('base' => $base, 'fc' => $fc, 'currency' => $currency);
}

/**
 * The captions the letter prints, in the site language.
 *
 * @return array
 */
function erp_reconciliation_labels()
{
    return array(
        'title' => lang('ACCOUNT RECONCILIATION LETTER'),
        'reference' => lang('Reference'),
        'letter_date' => lang('Date'),
        'as_of' => lang('Balance as of'),
        'tax_id' => lang('VKN / TCKN'),
        'tax_office' => lang('Tax Office'),
        'to' => lang('Addressed to'),
        'dear' => lang('Dear Sirs,'),
        'intro' => lang('Please compare the balance below with your own records and let us know whether you agree.'),
        'movements' => lang('Movements'),
        'date' => lang('Date'),
        'description' => lang('Description'),
        'debit' => lang('Debit'),
        'credit' => lang('Credit'),
        'balance' => lang('Balance'),
        'opening_balance' => lang('Opening Balance'),
        'closing_balance' => lang('Closing Balance'),
        'no_movements' => lang('There were no movements in this period.'),
        'agree' => lang('WE AGREE'),
        'disagree' => lang('WE DO NOT AGREE'),
        'our_balance' => lang('Our records show a balance of'),
        'stamp' => lang('Stamp and signature'),
        'name_date' => lang('Name, date'),
        'regards' => lang('Yours faithfully,'),
        'questions' => lang('Questions about this letter'),
        'generated_at' => lang('Generated at'),
    );
}

/**
 * Everything the template sees for one account as of one date.
 *
 * seller / account are the same blocks the invoice and the delivery note
 * print. letter carries the reference, the dates, the balance in words and
 * figures, and the reply terms. movements is the period's statement with
 * the running balance, the earliest rows folded into the opening figure when
 * there are more than ERP_RECONCILIATION_MAX_ROWS.
 *
 * @param int   $account_id
 * @param array $options  From erp_reconciliation_options()
 * @return array|false  false when the account does not exist
 */
function erp_reconciliation_data($account_id, $options)
{
    $account_row = erp_account((int) $account_id);

    if (!is_array($account_row)) {
        return false;
    }

    $options = erp_reconciliation_options($options);
    $account_id = (int) $account_row['id'];

    $constant = function ($name) {
        return defined($name) ? trim((string) constant($name)) : '';
    };

    $logo = erp_document_logo();

    $seller = array(
        'title' => $constant('ORGANIZATION_NAME'),
        'address_1' => $constant('ORGANIZATION_ADDRESS_1'),
        'address_2' => $constant('ORGANIZATION_ADDRESS_2'),
        'city' => $constant('ORGANIZATION_CITY'),
        'state' => $constant('ORGANIZATION_STATE'),
        'zip_code' => $constant('ORGANIZATION_ZIP_CODE'),
        'country' => $constant('ORGANIZATION_COUNTRY'),
        'vkn' => $constant('ERP_SELLER_VKN'),
        'tax_office' => $constant('ERP_SELLER_TAX_OFFICE'),
        'web_address' => $constant('ERP_WEB_ADDRESS'),
        'email' => erp_reconciliation_sender(),
        'logo_url' => $logo['url'],
        'logo_data_uri' => $logo['data_uri'],
    );

    $account = array(
        'title' => (string) $account_row['title'],
        'tax_number' => (string) $account_row['tax_number'],
        'tax_office' => (string) $account_row['tax_office'],
        'address' => (string) $account_row['address'],
        'district' => (string) $account_row['district'],
        'city' => (string) $account_row['city'],
        'country' => (string) $account_row['country_code'],
        'postcode' => (string) $account_row['postcode'],
        'email' => (string) $account_row['email'],
    );

    // ----------------------------------------------------------- the balance
    $balance = erp_reconciliation_balance($account_id, $options['as_of']);
    $as_of_text = erp_document_date($options['as_of']);
    $amount_text = erp_money_out(abs($balance['base']), false);

    $show_fc = erp_fx_enabled() && ($balance['currency'] !== '') && ($balance['currency'] !== erp_base_currency());
    $amount_fc_text = $show_fc ? erp_money_out_currency(abs($balance['fc']), $balance['currency'], false) . ' ' . $balance['currency'] : '';

    if ($balance['base'] > 0) {
        $side = 'debit';
        $sentence = lang(array('string' => 'As of {var:1}, your account in our records shows a debit balance of {var:2} in our favour.', 'vars' => array($as_of_text, $amount_text)));
    } elseif ($balance['base'] < 0) {
        $side = 'credit';
        $sentence = lang(array('string' => 'As of {var:1}, your account in our records shows a credit balance of {var:2} in your favour.', 'vars' => array($as_of_text, $amount_text)));
    } else {
        $side = 'zero';
        $sentence = lang(array('string' => 'As of {var:1}, your account in our records shows a zero balance.', 'vars' => $as_of_text));
    }

    if ($amount_fc_text !== '') {
        $sentence .= ' ' . lang(array('string' => 'In the currency of your account that is {var:1}.', 'vars' => $amount_fc_text));
    }

    // -------------------------------------------------------- the reply terms
    $reply_by = '';
    $reply_sentence = '';

    if ($options['reply_days'] > 0) {
        $reply_by_date = date('Y-m-d', strtotime($options['as_of'] . ' +' . $options['reply_days'] . ' days'));

        // The clock runs from today when the letter is written after the
        // balance date; a deadline already in the past asks for nothing.
        $today = date('Y-m-d');
        if ($reply_by_date < $today) {
            $reply_by_date = date('Y-m-d', strtotime($today . ' +' . $options['reply_days'] . ' days'));
        }

        $reply_by = erp_document_date($reply_by_date);
        $reply_sentence = lang(array('string' => 'Please reply within {var:1} days, by {var:2}. If we receive no reply by that date, we will take the balance as agreed.', 'vars' => array($options['reply_days'], $reply_by)));
    }

    // ---------------------------------------------------------- the movements
    $statement = erp_account_statement($account_id, $options['from'], $options['as_of']);
    $rows = $statement['rows'];
    $opening = (int) $statement['opening'];
    $folded = 0;

    if (count($rows) > ERP_RECONCILIATION_MAX_ROWS) {
        $folded = count($rows) - ERP_RECONCILIATION_MAX_ROWS;
        $last_folded = $rows[$folded - 1];
        $opening = (int) $last_folded['running_balance'];
        $rows = array_slice($rows, $folded);
    }

    $movements = array();
    $base_currency = erp_base_currency();
    $fx_enabled = erp_fx_enabled();

    foreach ($rows as $row) {
        $is_debit = ((string) $row['direction'] === 'debit');
        $amount = erp_money_out((int) $row['amount_base'], false);

        // A movement made in another currency also says what it was in that
        // currency, the way the account screen does.
        $row_currency = strtoupper(trim((string) $row['currency']));
        if ($fx_enabled && ($row_currency !== '') && ($row_currency !== $base_currency)) {
            $amount .= ' (' . erp_money_out_currency((int) $row['amount'], $row_currency, false) . ' ' . $row_currency . ')';
        }

        $movements[] = array(
            'date' => erp_document_date($row['doc_date']),
            'description' => (string) $row['description'],
            'debit' => $is_debit ? $amount : '',
            'credit' => $is_debit ? '' : $amount,
            'balance' => erp_money_out((int) $row['running_balance']),
        );
    }

    $letter = array(
        'title' => lang('ACCOUNT RECONCILIATION LETTER'),
        'reference' => erp_reconciliation_reference($account_id, $options['as_of']),
        'letter_date' => erp_document_date(date('Y-m-d')),
        'as_of' => $as_of_text,
        'from' => erp_document_date($options['from']),
        'period' => lang(array('string' => 'Movements from {var:1} to {var:2}', 'vars' => array(erp_document_date($options['from']), $as_of_text))),
        'balance' => $amount_text,
        'balance_fc' => $amount_fc_text,
        'balance_signed' => erp_money_out($balance['base']),
        'side' => $side,
        'is_debit' => ($side === 'debit'),
        'is_credit' => ($side === 'credit'),
        'is_zero' => ($side === 'zero'),
        'sentence' => $sentence,
        'reply_days' => $options['reply_days'],
        'reply_by' => $reply_by,
        'reply_sentence' => $reply_sentence,
        'opening_balance' => erp_money_out($opening),
        'closing_balance' => erp_money_out((int) $statement['closing']),
        'folded' => $folded,
        'folded_sentence' => ($folded > 0) ? lang(array('string' => 'The opening balance includes {var:1} earlier movement(s) in this period that are not listed.', 'vars' => $folded)) : '',
        'has_movements' => (count($movements) > 0),
    );

    return array(
        'seller' => $seller,
        'account' => $account,
        'letter' => $letter,
        'movements' => $movements,
        'label' => erp_reconciliation_labels(),
        'language' => defined('SOFTWARE_LANGUAGE') ? (string) SOFTWARE_LANGUAGE : 'en',
        'generated_at' => (string) prepare_form_data_for_output(date('Y-m-d H:i:s'), 'date and time', false),
    );
}

/**
 * The address the letter is sent from and answered to: the store's, then the
 * site's, whichever is a usable address.
 *
 * @return string
 */
function erp_reconciliation_sender()
{
    foreach (array(
        defined('ECOMMERCE_EMAIL_ADDRESS') ? (string) ECOMMERCE_EMAIL_ADDRESS : '',
        defined('EMAIL_ADDRESS') ? (string) EMAIL_ADDRESS : '',
    ) as $address) {
        if (($address !== '') && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return $address;
        }
    }

    return '';
}

/**
 * The built-in letter template, as shipped.
 *
 * @return string
 */
function erp_reconciliation_default_template()
{
    $path = PG_FUNCTIONS_DIR . '/includes/erp/templates/reconciliation_default.html';
    $template = is_readable($path) ? file_get_contents($path) : '';

    return ($template === false) ? '' : (string) $template;
}

/**
 * The template the letters are printed from: the one saved on the ERP settings
 * screen when there is one (4.60), otherwise the built-in file.
 *
 * @return string
 */
function erp_reconciliation_template()
{
    if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_reconciliation_template')) {
        $saved = db_value("SELECT erp_reconciliation_template FROM config LIMIT 1");

        if (is_string($saved) && (trim($saved) !== '')) {
            return $saved;
        }
    }

    return erp_reconciliation_default_template();
}

/**
 * The letter as HTML.
 *
 * @param int   $account_id
 * @param array $options
 * @return string|false  false when the account does not exist
 */
function erp_reconciliation_html($account_id, $options, $template = null)
{
    $data = erp_reconciliation_data($account_id, $options);

    if ($data === false) {
        return false;
    }

    return erp_template_render(($template === null) ? erp_reconciliation_template() : (string) $template, $data);
}

/**
 * The letter as a PDF, through the invoice's renderer.
 *
 * @param int   $account_id
 * @param array $options
 * @return string|false  false when the account does not exist or the PDF library is missing
 */
function erp_reconciliation_pdf($account_id, $options)
{
    $html = erp_reconciliation_html($account_id, $options);

    if ($html === false) {
        return false;
    }

    return erp_invoice_pdf($html);
}

/**
 * The file name a letter is saved or attached under.
 *
 * @param int    $account_id
 * @param string $as_of  Y-m-d
 * @return string  Without extension
 */
function erp_reconciliation_file_name($account_id, $as_of)
{
    return preg_replace('/[^A-Za-z0-9._-]+/', '_', erp_reconciliation_reference($account_id, $as_of));
}

/**
 * The covering e-mail: a few lines that say what is attached and what is
 * asked, so the letter itself can be printed and stamped as it is.
 *
 * @param array $data  From erp_reconciliation_data()
 * @return string  HTML
 */
function erp_reconciliation_mail_html($data)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';
    $sender = erp_reconciliation_sender();

    $output_reply = '';
    if ((string) $data['letter']['reply_sentence'] !== '') {
        $output_reply = '
        <p style="' . $paragraph . '">' . h($data['letter']['reply_sentence']) . '</p>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(lang('Account reconciliation')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 760px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0;">' . h($data['seller']['title']) . ' &middot; ' . h(lang('Account reconciliation')) . '</h2>
        <p style="' . $paragraph . '">' . h(lang(array('string' => 'Dear {var:1},', 'vars' => $data['account']['title']))) . '</p>
        <p style="' . $paragraph . '">' . h(lang(array('string' => 'Attached is our account reconciliation letter, reference {var:1}.', 'vars' => $data['letter']['reference']))) . '</p>
        <p style="' . $paragraph . ' font-weight: bold;">' . h($data['letter']['sentence']) . '</p>
        <p style="' . $paragraph . '">' . h(lang('Please compare the balance below with your own records and let us know whether you agree.')) . '</p>'
        . $output_reply . '
        <p style="' . $paragraph . ' margin-top: 12px;">' . h(lang('If you have a question about this letter, simply reply to this e-mail.')) . '</p>
        <p style="' . $paragraph . '">' . h($data['seller']['title']) . (($sender !== '') ? '<br>' . h($sender) : '') . '</p>
    </div>
</body>
</html>';
}

/**
 * Send the letter to an address, as a PDF attached to a short covering
 * e-mail. Nothing is recorded on the account beyond the activity log: the
 * letter is a question to the counterparty, and the answer, when it comes,
 * is what matters.
 *
 * @param int    $account_id
 * @param array  $options
 * @param string $to  Recipient address
 * @return array ['success' => bool, 'error' => string]
 */
function erp_reconciliation_send($account_id, $options, $to)
{
    $to = trim((string) $to);

    if (($to === '') || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return array('success' => false, 'error' => lang('Please enter a valid e-mail address to send the letter to.'));
    }

    $sender = erp_reconciliation_sender();

    if ($sender === '') {
        return array('success' => false, 'error' => lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    $data = erp_reconciliation_data($account_id, $options);

    if ($data === false) {
        return array('success' => false, 'error' => lang('The account could not be found.'));
    }

    $pdf = erp_invoice_pdf(erp_template_render(erp_reconciliation_template(), $data));

    if ($pdf === false) {
        return array('success' => false, 'error' => lang('The PDF library is not installed.'));
    }

    $options = erp_reconciliation_options($options);

    $sent = email(array(
        'to'                 => $to,
        'to_name'            => (string) $data['account']['title'],
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => $sender,
        'reply_to'           => $sender,
        'subject'            => lang(array('string' => '{var:1} - account reconciliation as of {var:2}', 'vars' => array(ORGANIZATION_NAME, $data['letter']['as_of']))),
        'format'             => 'html',
        'body'               => erp_reconciliation_mail_html($data),
        'type'               => 'system',
        'attachments'        => array(
            array(
                'name' => erp_reconciliation_file_name($account_id, $options['as_of']) . '.pdf',
                'content' => $pdf,
                'type' => 'application/pdf',
            ),
        ),
    ));

    if (!$sent) {
        return array('success' => false, 'error' => lang('The e-mail could not be sent. The details are in the activity log.'));
    }

    return array('success' => true, 'error' => '');
}
