<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - e-mailing an issued invoice to the customer.
 *
 * The invoice goes as a PDF attached to a short covering e-mail. Where the
 * store works through an e-document provider and GIB has accepted the
 * invoice, the attachment is the provider's official copy (kept once, the way
 * the invoice screen keeps it); otherwise it is the store's own PDF, the copy
 * kept when the invoice was issued.
 *
 * Every e-mail is a row in erp_document_mails (4.97), written 'queued' before
 * the send and settled after it: the invoice screen lists them, a failed one
 * can be sent again, and one that never finished shows as queued rather than
 * vanishing.
 *
 * An invoice can also go on its own (config.erp_invoice_mail_auto). The
 * trigger is the invoice's own announcement (erp_event_invoice()): when it is
 * issued, or, where e-documents are in use, when GIB accepts it, so the
 * customer gets the official copy and not a rendering of it. The e-mail is
 * queued inside the writer's transaction and sent once the request is over,
 * after what was committed can be read back; a writer that rolled back leaves
 * a row pointing at nothing, which the send finds and drops.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_MAIL_MAX_RECIPIENTS')) {
    define('ERP_MAIL_MAX_RECIPIENTS', 5);
}

/**
 * Whether the 4.97 table is there.
 *
 * @return bool
 */
function erp_mail_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_document_mails', 'doc_id');
    }

    return $ready;
}

/**
 * The two settings on the config row (4.97), read once per request.
 *
 * @param string $name  'auto' | 'message'
 * @return mixed
 */
function erp_mail_setting($name)
{
    static $settings = null;

    if ($settings === null) {
        $settings = array('auto' => 0, 'message' => '');

        if (erp_mail_ready() && waf_table_has_column('config', 'erp_invoice_mail_auto')) {
            $row = db_item("SELECT erp_invoice_mail_auto, erp_invoice_mail_message FROM config LIMIT 1");

            if (is_array($row)) {
                $settings['auto'] = (int) $row['erp_invoice_mail_auto'];
                $settings['message'] = (string) $row['erp_invoice_mail_message'];
            }
        }
    }

    return $settings[$name] ?? null;
}

/**
 * Whether issued invoices go to the customer on their own.
 *
 * @return bool
 */
function erp_mail_auto_on()
{
    return ((int) erp_mail_setting('auto') === 1);
}

/**
 * The invoice as the e-mail needs it, with its account's addresses.
 *
 * @param int $invoice_id
 * @return array|null
 */
function erp_mail_invoice($invoice_id)
{
    $has_columns = function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'invoice_email');

    $row = db_item("SELECT i.id, i.full_number, i.direction, i.doc_type, i.status, i.issue_date, i.due_date, i.currency,
            i.grand_total, i.paid_total, i.account_id, i.account_title, i.edoc_provider, i.edoc_status, i.gib_number,
            a.title AS live_account_title, a.email AS account_email"
            . ($has_columns ? ", a.invoice_email, a.invoice_mail" : ", '' AS invoice_email, 1 AS invoice_mail") . "
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        WHERE i.id = '" . (int) $invoice_id . "'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Why an invoice cannot be e-mailed, or ''.
 *
 * A purchase invoice is the supplier's document and a draft is not one yet;
 * a cancelled invoice is not sent again as if it stood.
 *
 * @param array $invoice  From erp_mail_invoice()
 * @return string
 */
function erp_mail_invoice_refusal($invoice)
{
    if (!erp_mail_ready()) {
        return lang('Sending invoices by e-mail comes with the software update; run the update to use it.');
    }
    if ((string) $invoice['direction'] !== 'sales') {
        return lang('A purchase invoice is the supplier\'s document; it is not e-mailed from here.');
    }
    if ((string) $invoice['status'] === 'draft') {
        return lang('A draft is not a document yet; issue it first.');
    }
    if ((string) $invoice['status'] === 'cancelled') {
        return lang('A cancelled document is not sent.');
    }

    return '';
}

/**
 * Where the account's invoices go: its invoice address when it has one, its
 * main address otherwise.
 *
 * @param array $invoice  From erp_mail_invoice()
 * @return string
 */
function erp_mail_invoice_recipient($invoice)
{
    $invoice_email = trim((string) ($invoice['invoice_email'] ?? ''));

    return ($invoice_email !== '') ? $invoice_email : trim((string) ($invoice['account_email'] ?? ''));
}

/**
 * The addresses in what was typed: commas, semicolons or spaces between them.
 *
 * @param string $text
 * @return array ['addresses' => string[], 'error' => string]
 */
function erp_mail_addresses($text)
{
    $addresses = array();

    foreach (preg_split('/[\s,;]+/', trim((string) $text)) as $address) {
        if ($address === '') {
            continue;
        }
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return array('addresses' => array(), 'error' => lang(array('string' => '{var:1} is not an e-mail address.', 'vars' => $address)));
        }
        $addresses[strtolower($address)] = $address;
    }

    if (empty($addresses)) {
        return array('addresses' => array(), 'error' => lang('Enter the e-mail address to send it to.'));
    }

    if (count($addresses) > ERP_MAIL_MAX_RECIPIENTS) {
        return array('addresses' => array(), 'error' => lang(array('string' => 'At most {var:1} addresses at a time.', 'vars' => ERP_MAIL_MAX_RECIPIENTS)));
    }

    return array('addresses' => array_values($addresses), 'error' => '');
}

/**
 * What the document is called in the e-mail.
 *
 * @param array $invoice
 * @return string
 */
function erp_mail_invoice_kind($invoice)
{
    return ((string) $invoice['doc_type'] === 'return') ? lang('Return invoice') : lang('Invoice');
}

/**
 * The built-in covering text, or the store's own.
 *
 * @return string
 */
function erp_mail_default_message()
{
    $stored = trim((string) erp_mail_setting('message'));

    return ($stored !== '') ? $stored : lang('Thank you for your purchase. Your invoice is attached to this e-mail.');
}

/**
 * The subject and the covering text a new e-mail starts with.
 *
 * @param array $invoice
 * @return array ['to', 'subject', 'message']
 */
function erp_mail_invoice_defaults($invoice)
{
    return array(
        'to' => erp_mail_invoice_recipient($invoice),
        'subject' => ORGANIZATION_NAME . ' - ' . erp_mail_invoice_kind($invoice) . ' ' . (string) $invoice['full_number'],
        'message' => erp_mail_default_message(),
    );
}

/**
 * Whether the official copy is what goes: GIB has accepted the document.
 *
 * @param array $invoice
 * @return bool
 */
function erp_mail_invoice_official($invoice)
{
    return ((string) $invoice['edoc_provider'] !== '') && ((string) $invoice['edoc_status'] === 'accepted');
}

/**
 * The file the e-mail carries: the official copy of an accepted e-document,
 * the store's kept PDF otherwise.
 *
 * @param array $invoice  From erp_mail_invoice()
 * @return array ['name', 'content', 'type', 'official' => bool, 'error' => string]
 */
function erp_mail_invoice_attachment($invoice)
{
    $invoice_id = (int) $invoice['id'];
    $fail = function ($message) {
        return array('name' => '', 'content' => '', 'type' => '', 'official' => false, 'error' => $message);
    };

    if (erp_mail_invoice_official($invoice)) {
        $kept = function_exists('erp_archive_file') ? erp_archive_file('edoc', $invoice_id) : null;
        $bytes = ($kept !== null) ? (string) @file_get_contents($kept['path']) : '';

        // Not kept yet: asked of the provider once, and kept, the way the
        // invoice screen's official-copy link does it.
        $provider_error = '';

        if (($bytes === '') && function_exists('erp_edoc_invoice_document')) {
            $result = erp_edoc_invoice_document($invoice_id, 'pdf');
            $provider_error = trim((string) ($result['error'] ?? ''));

            if (!empty($result['success']) && (strncmp((string) $result['content'], '%PDF', 4) === 0)) {
                $bytes = (string) $result['content'];

                if (function_exists('erp_archive_edoc')) {
                    erp_archive_edoc($invoice_id, $bytes, (string) $result['mime']);
                }
            }
        }

        if ($bytes !== '') {
            $label = ((string) $invoice['gib_number'] !== '') ? (string) $invoice['gib_number'] : (string) $invoice['full_number'];

            return array(
                'name' => preg_replace('/[^A-Za-z0-9._-]+/', '_', $label) . '.pdf',
                'content' => $bytes,
                'type' => 'application/pdf',
                'official' => true,
                'error' => '',
            );
        }

        return $fail(($provider_error !== '')
            ? lang(array('string' => 'The official copy could not be fetched from the e-document provider: {var:1}', 'vars' => $provider_error))
            : lang('The official copy could not be fetched from the e-document provider. Try again later.'));
    }

    $kept = function_exists('erp_archive_invoice') ? erp_archive_invoice($invoice_id) : null;
    $bytes = ($kept !== null) ? (string) @file_get_contents($kept['path']) : '';

    if ($bytes === '') {
        $html = erp_invoice_html($invoice_id);
        $pdf = ($html !== false) ? erp_invoice_pdf($html) : false;
        $bytes = ($pdf !== false) ? (string) $pdf : '';
    }

    if ($bytes === '') {
        return $fail(lang('The PDF could not be made.'));
    }

    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $invoice['full_number']);

    return array(
        'name' => ((trim($name, '_') !== '') ? $name : ('invoice_' . $invoice_id)) . '.pdf',
        'content' => $bytes,
        'type' => 'application/pdf',
        'official' => false,
        'error' => '',
    );
}

/**
 * The e-mail itself: the covering text, and the document's figures so the
 * customer does not have to open the file to know what it is for.
 *
 * @param array  $invoice
 * @param string $message
 * @return string
 */
function erp_mail_invoice_html($invoice, $message)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';
    $cell = 'font-size: 14px; padding: 4px 16px 4px 0; vertical-align: top;';
    $sender = function_exists('erp_reconciliation_sender') ? erp_reconciliation_sender() : '';
    $currency = strtoupper(trim((string) $invoice['currency']));
    $title = (trim((string) $invoice['account_title']) !== '') ? (string) $invoice['account_title'] : (string) ($invoice['live_account_title'] ?? '');
    $open = max(0, (int) $invoice['grand_total'] - (int) $invoice['paid_total']);
    $date = function ($value) {
        return ((string) $value > '0000-00-00') ? prepare_form_data_for_output((string) $value, 'date', false) : '';
    };

    $rows = array(
        array(erp_mail_invoice_kind($invoice), (string) $invoice['full_number']),
        array(lang('Date'), $date($invoice['issue_date'])),
    );

    if ((string) $invoice['gib_number'] !== '' && erp_mail_invoice_official($invoice)) {
        $rows[] = array(lang('GİB Number'), (string) $invoice['gib_number']);
    }

    $rows[] = array(lang('Total'), erp_money_out_currency((int) $invoice['grand_total'], $currency));

    if (((string) $invoice['doc_type'] !== 'return') && ($open > 0)) {
        $rows[] = array(lang('Amount due'), erp_money_out_currency($open, $currency));

        if ($date($invoice['due_date']) !== '') {
            $rows[] = array(lang('Due Date'), $date($invoice['due_date']));
        }
    }

    $output_rows = '';
    foreach ($rows as $row) {
        if ((string) $row[1] === '') {
            continue;
        }
        $output_rows .= '<tr><td style="' . $cell . ' color: #666666;">' . h($row[0]) . '</td><td style="' . $cell . ' font-weight: bold;">' . h($row[1]) . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(erp_mail_invoice_kind($invoice)) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 640px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0;">' . h(ORGANIZATION_NAME) . ' &middot; ' . h(erp_mail_invoice_kind($invoice)) . ' ' . h((string) $invoice['full_number']) . '</h2>
        ' . (($title !== '') ? '<p style="' . $paragraph . '">' . h(lang(array('string' => 'Dear {var:1},', 'vars' => $title))) . '</p>' : '') . '
        <p style="' . $paragraph . '">' . nl2br(h(trim((string) $message))) . '</p>
        <table style="border-collapse: collapse; margin: 8px 0 16px 0;">' . $output_rows . '</table>
        <p style="' . $paragraph . '">' . h(lang('If you have a question about this invoice, simply reply to this e-mail.')) . '</p>
        <p style="' . $paragraph . '">' . h(ORGANIZATION_NAME) . (($sender !== '') ? '<br>' . h($sender) : '') . '</p>
    </div>
</body>
</html>';
}

/**
 * Write the row an e-mail is recorded in, before it is sent.
 *
 * @return int  The row, or 0
 */
function erp_mail_record($doc_type, $doc_id, $mode, $to, $subject, $message, $user_id)
{
    if (!erp_mail_ready()) {
        return 0;
    }

    $saved = db("INSERT INTO erp_document_mails (doc_type, doc_id, mode, status, to_address, subject, message, created_by, created_at)
        VALUES (
            '" . escape((string) $doc_type) . "',
            '" . (int) $doc_id . "',
            '" . (($mode === 'auto') ? 'auto' : 'manual') . "',
            'queued',
            '" . escape(mb_substr((string) $to, 0, 500)) . "',
            '" . escape(mb_substr((string) $subject, 0, 255)) . "',
            '" . escape((string) $message) . "',
            '" . (int) $user_id . "',
            '" . time() . "')");

    return ($saved !== false) ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * Settle a recorded e-mail.
 *
 * @param int    $mail_id
 * @param bool   $sent
 * @param string $attachments  The file names that went
 * @param string $error
 */
function erp_mail_settle($mail_id, $sent, $attachments, $error)
{
    if ((int) $mail_id <= 0) {
        return;
    }

    db("UPDATE erp_document_mails SET
            status = '" . ($sent ? 'sent' : 'failed') . "',
            attachments = '" . escape(mb_substr((string) $attachments, 0, 500)) . "',
            error = '" . escape(mb_substr((string) $error, 0, 255)) . "',
            sent_at = '" . ($sent ? time() : 0) . "'
        WHERE id = '" . (int) $mail_id . "'");
}

/**
 * Send an invoice to one or more addresses.
 *
 * @param int    $invoice_id
 * @param string $to        Addresses, comma separated
 * @param string $subject
 * @param string $message   The covering text
 * @param int    $user_id
 * @param string $mode      'manual' | 'auto'
 * @param int    $mail_id   A row already recorded (the automatic path), or 0
 * @return array ['success' => bool, 'error' => string, 'official' => bool]
 */
function erp_mail_invoice_send($invoice_id, $to, $subject, $message, $user_id = 0, $mode = 'manual', $mail_id = 0)
{
    $fail = function ($message) use (&$mail_id) {
        erp_mail_settle($mail_id, false, '', $message);
        return array('success' => false, 'error' => $message, 'official' => false);
    };

    $invoice = erp_mail_invoice($invoice_id);

    if ($invoice === null) {
        return $fail(lang('The invoice could not be found.'));
    }

    $refusal = erp_mail_invoice_refusal($invoice);

    if ($refusal !== '') {
        return $fail($refusal);
    }

    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        return $fail($addresses['error']);
    }

    $subject = trim((string) $subject);
    $defaults = erp_mail_invoice_defaults($invoice);
    $subject = ($subject !== '') ? mb_substr($subject, 0, 255) : $defaults['subject'];
    $message = (trim((string) $message) !== '') ? mb_substr(trim((string) $message), 0, 5000) : $defaults['message'];

    $sender = function_exists('erp_reconciliation_sender') ? erp_reconciliation_sender() : '';

    if ($sender === '') {
        return $fail(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    if ((int) $mail_id <= 0) {
        $mail_id = erp_mail_record('invoice', (int) $invoice['id'], $mode, implode(', ', $addresses['addresses']), $subject, $message, $user_id);
    }

    $attachment = erp_mail_invoice_attachment($invoice);

    if ($attachment['error'] !== '') {
        return $fail($attachment['error']);
    }

    $sent = email(array(
        'to' => $addresses['addresses'],
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => $sender,
        'reply_to' => $sender,
        'subject' => $subject,
        'format' => 'html',
        'body' => erp_mail_invoice_html($invoice, $message),
        'type' => 'system',
        'attachments' => array(
            array('name' => $attachment['name'], 'content' => $attachment['content'], 'type' => $attachment['type']),
        ),
    ));

    if (!$sent) {
        erp_mail_settle($mail_id, false, $attachment['name'], lang('The e-mail could not be sent. The details are in the activity log.'));
        return array('success' => false, 'error' => lang('The e-mail could not be sent. The details are in the activity log.'), 'official' => $attachment['official']);
    }

    erp_mail_settle($mail_id, true, $attachment['name'], '');

    log_activity(lang(array(
        'string' => 'erp invoice {var:1} was e-mailed to {var:2}',
        'vars' => array((string) $invoice['full_number'], implode(', ', $addresses['addresses'])),
    )), ($mode === 'auto') ? 'SYSTEM' : (string) ($_SESSION['sessionusername'] ?? ''));

    return array('success' => true, 'error' => '', 'official' => $attachment['official']);
}

/**
 * The e-mails a document went out in, newest first.
 *
 * @param string $doc_type
 * @param int    $doc_id
 * @return array
 */
function erp_mail_history($doc_type, $doc_id)
{
    if (!erp_mail_ready()) {
        return array();
    }

    return (array) db_items("SELECT m.*, u.user_username AS created_by_name
        FROM erp_document_mails m
        LEFT JOIN user u ON u.user_id = m.created_by
        WHERE m.doc_type = '" . escape((string) $doc_type) . "' AND m.doc_id = '" . (int) $doc_id . "'
        ORDER BY m.id DESC");
}

/**
 * The statuses, as the screens name them.
 *
 * @return array  status => [label, colour]
 */
function erp_mail_statuses()
{
    return array(
        'queued' => array(lang('Waiting to be sent'), 'warning'),
        'sent' => array(lang('Sent'), 'success'),
        'failed' => array(lang('Not sent'), 'danger'),
    );
}

/**
 * Who an invoice's automatic e-mail goes to at this moment, or '' when this
 * is not the moment for it (the setting aside).
 *
 * The moment is the issue, or, where e-documents are in use, GIB's
 * acceptance. An invoice goes on its own once: a second acceptance notice, a
 * retry or an e-mail already sent by hand does not send it again.
 *
 * @param int    $invoice_id
 * @param string $event  'erp.invoice.created' | 'erp.invoice.edoc_changed'
 * @return string
 */
function erp_mail_auto_target($invoice_id, $event)
{
    $invoice = erp_mail_invoice($invoice_id);

    if (($invoice === null) || (erp_mail_invoice_refusal($invoice) !== '')) {
        return '';
    }

    $edocs = function_exists('erp_edoc_in_use') && erp_edoc_in_use();

    if ($edocs) {
        if (($event !== 'erp.invoice.edoc_changed') || !erp_mail_invoice_official($invoice)) {
            return '';
        }
    } elseif ($event !== 'erp.invoice.created') {
        return '';
    }

    if ((int) ($invoice['invoice_mail'] ?? 1) !== 1) {
        return '';
    }

    $to = erp_mail_invoice_recipient($invoice);

    if (($to === '') || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    if ((int) db_value("SELECT COUNT(*) FROM erp_document_mails
        WHERE doc_type = 'invoice' AND doc_id = '" . (int) $invoice['id'] . "' AND (mode = 'auto' OR status = 'sent')") > 0) {
        return '';
    }

    return $to;
}

/**
 * Called where an invoice announces itself (erp_event_invoice()): queue its
 * automatic e-mail when the store sends them and this is the moment.
 *
 * @param int    $invoice_id
 * @param string $event
 * @return void
 */
function erp_mail_auto_consider($invoice_id, $event)
{
    if (!erp_mail_auto_on()) {
        return;
    }

    $to = erp_mail_auto_target($invoice_id, $event);

    if ($to === '') {
        return;
    }

    $defaults = erp_mail_invoice_defaults(erp_mail_invoice($invoice_id));
    $mail_id = erp_mail_record('invoice', (int) $invoice_id, 'auto', $to, $defaults['subject'], $defaults['message'], 0);

    if ($mail_id > 0) {
        erp_mail_defer($mail_id);
    }
}

/**
 * Send a queued automatic e-mail once this request is over, when what the
 * writer committed can be read back.
 *
 * @param int $mail_id
 * @return void
 */
function erp_mail_defer($mail_id)
{
    static $queue = null;

    if ($queue === null) {
        $queue = array();
        register_shutdown_function(function () use (&$queue) {
            if (empty($queue)) {
                return;
            }

            // The page is answered first where the server allows it, so
            // the person who issued the invoice does not wait on the mail.
            if (function_exists('fastcgi_finish_request')) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                fastcgi_finish_request();
            }

            foreach ($queue as $id) {
                erp_mail_send_queued($id);
            }
        });
    }

    $queue[] = (int) $mail_id;
}

/**
 * Send one queued e-mail. A row whose invoice is gone (a writer that rolled
 * back) or no longer mailable is dropped as not sent, with the reason.
 *
 * @param int $mail_id
 * @return bool
 */
function erp_mail_send_queued($mail_id)
{
    $row = db_item("SELECT * FROM erp_document_mails WHERE id = '" . (int) $mail_id . "' AND status = 'queued' LIMIT 1");

    if (!is_array($row) || ((string) $row['doc_type'] !== 'invoice')) {
        return false;
    }

    $result = erp_mail_invoice_send((int) $row['doc_id'], (string) $row['to_address'], (string) $row['subject'], (string) $row['message'],
        (int) $row['created_by'], (string) $row['mode'], (int) $row['id']);

    return !empty($result['success']);
}
