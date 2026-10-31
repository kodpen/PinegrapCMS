<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the settings cards drawn in two places: the Accounting pane of Site
 * Settings (role < 3) and the ERP Settings screen (also a role-3 user with
 * the ERP settings right, who has no Site Settings). The accounting rules,
 * the late payment fee and the e-Fatura thresholds are drawn and saved here
 * once, so both places show the same fields and write them the same way.
 * The printed document templates keep their editor on the screen; the pane
 * shows their state and links there.
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
 * The printed documents and where each one's template lives. The invoice has
 * had a saved template since 4.43; the delivery note and the reconciliation
 * letter got theirs in 4.60, so they are listed only once that upgrade has
 * run - until then they print from the built-in files.
 *
 * @return array key => label, column, default, current, preview, log
 */
function erp_settings_documents()
{
    $documents = array(
        'invoice' => array(
            'label' => lang('Invoice Template'),
            'column' => 'erp_invoice_template',
            'default' => erp_invoice_default_template(),
            'current' => erp_invoice_template(),
            'preview' => 'get_erp_invoice_pdf.php',
            'log' => 'erp invoice template',
        ),
    );

    if (waf_table_has_column('config', 'erp_waybill_template')) {
        $documents['waybill'] = array(
            'label' => lang('Delivery Note Template'),
            'column' => 'erp_waybill_template',
            'default' => erp_waybill_default_template(),
            'current' => erp_waybill_template(),
            'preview' => 'get_erp_waybill_pdf.php',
            'log' => 'erp delivery note template',
        );
    }

    if (waf_table_has_column('config', 'erp_reconciliation_template')) {
        $documents['reconciliation'] = array(
            'label' => lang('Reconciliation Letter Template'),
            'column' => 'erp_reconciliation_template',
            'default' => erp_reconciliation_default_template(),
            'current' => erp_reconciliation_template(),
            'preview' => 'get_erp_reconciliation_pdf.php',
            'log' => 'erp reconciliation letter template',
        );
    }

    return $documents;
}

/**
 * The template Preview: the PDF the document is made from the posted
 * template, through the same dompdf settings as the one downloaded, e-mailed
 * and kept (no remote files, DejaVu Sans, the paper size), so a remote logo,
 * a web font or a layout dompdf does not draw shows as the customer will see
 * it. Only when no PDF can be made (no library, or the render throws) is the
 * HTML returned, opened by a note that the PDF may look different.
 *
 * @param string   $html
 * @param callable $render html => PDF bytes, or false without the library
 * @return array type (pdf | html), body
 */
function erp_template_preview_document($html, $render = 'erp_invoice_pdf')
{
    try {
        $pdf = call_user_func($render, $html);
    } catch (\Throwable $e) {
        $pdf = false;
    }

    if (is_string($pdf) && ($pdf !== '')) {
        return array('type' => 'pdf', 'body' => $pdf);
    }

    $note = '<div style="font-family:sans-serif;font-size:13px;background:#fff3cd;color:#664d03;padding:8px 12px;margin:0 0 12px;">'
        . h(lang('The PDF could not be made, so this preview shows the HTML it is made from; the PDF may look different.')) . '</div>';

    if (preg_match('/<body\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
        $position = $match[0][1] + strlen($match[0][0]);
        return array('type' => 'html', 'body' => substr($html, 0, $position) . $note . substr($html, $position));
    }

    return array('type' => 'html', 'body' => $note . $html);
}

/**
 * Send the template Preview and stop. It is never kept: the endpoints call
 * this before they read or write a kept copy, and the browser is told not to
 * store it.
 *
 * @param string $html
 */
function erp_template_preview_send($html)
{
    $preview = erp_template_preview_document($html);

    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');

    if ($preview['type'] === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="preview.pdf"');
        header('Content-Length: ' . strlen($preview['body']));
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }

    echo $preview['body'];
    exit();
}

/**
 * Whether the accounting rules can be edited: the withholding and the
 * exemption code concern Turkish VAT and e-documents, so they are offered
 * where those are in use, once the upgrade added their columns.
 *
 * @return bool
 */
function erp_settings_rules_editable()
{
    return erp_rules_ready() && erp_turkish_features();
}

/**
 * Whether the e-Fatura threshold card is drawn: a Turkish rule, offered
 * where the Turkish features are, once the upgrade added the columns
 * (11.0.61).
 *
 * @return bool
 */
function erp_settings_efatura_threshold_shown()
{
    return erp_turkish_features() && erp_efatura_threshold_ready();
}

/**
 * The accounting rules card's fields.
 *
 * @return string
 */
function erp_settings_rules_fields()
{
    $output = '';

    if (erp_settings_rules_editable()) {
        $rules = erp_rules();

        $output .= '
                        <fieldset class="mb-7">
                            <legend class="form-label fs-md">' . lang('VAT withheld on sales, in the VAT report and the accountant\'s pack') . '</legend>
                            <div class="form-field">
                                <input class="radio" type="radio" name="vat_net_withholding" id="vat_net_withholding_0" value="0"' . ($rules['vat_net_withholding'] ? '' : ' checked') . ' />
                                <label class="" for="vat_net_withholding_0">' . lang('Shown beside the calculated VAT, which stays in full') . '</label>
                            </div>
                            <div class="form-field">
                                <input class="radio" type="radio" name="vat_net_withholding" id="vat_net_withholding_1" value="1"' . ($rules['vat_net_withholding'] ? ' checked' : '') . ' />
                                <label class="" for="vat_net_withholding_1">' . lang('Taken off the calculated VAT, since the buyer declares that part') . '</label>
                            </div>
                            <div class="form-text">' . lang('The invoices do not change: they carry the whole VAT and the withheld part either way. Only the difference the report works out does.') . '</div>
                        </fieldset>
                        <div class="mb-7" style="max-width:36rem">
                            <label class="form-label" for="vat_exemption_code">' . lang('Exemption code for zero-rated lines') . '</label>
                            <input type="text" class="form-control font-monospace" style="max-width:8rem" id="vat_exemption_code" name="vat_exemption_code" value="' . h($rules['vat_exemption_code']) . '" maxlength="3" inputmode="numeric" pattern="[0-9]{3}" placeholder="351" autocomplete="off" />
                            <div class="form-text">' . lang('A 0% line on an e-document has to say why it carries no VAT. A line takes its product\'s code when the product has one, otherwise this one; empty leaves the line without a code. Which code fits your sales is for your accountant to say.') . '</div>
                        </div>';
    }

    $output .= '
                        <p class="mb-0">' . lang('Whether an expense category takes its VAT back is set with the categories.') . '
                            <a href="erp_expense_categories.php">' . lang('Expense categories') . '</a></p>';

    return $output;
}

/**
 * The late payment fee card's fields: a monthly rate on what is owed past
 * the due date, and the tax rate of the fee's invoice line
 * (includes/erp/late_fee.php). Empty before the upgrade added the columns.
 *
 * @return string
 */
function erp_settings_late_fee_fields()
{
    if (!erp_late_fee_ready()) {
        return '';
    }

    $late_fee = erp_late_fee_settings();
    $rate_mark = erp_decimal_comma() ? ',' : '.';
    $rate_out = function ($rate) use ($rate_mark) {
        return rtrim(rtrim(number_format((float) $rate, 3, $rate_mark, ''), '0'), $rate_mark);
    };

    return '
                        <p class="fg-secondary">' . lang('A monthly rate on what a customer owes past the due date, counted day by day until it is paid. The account screen and the reconciliation letter screen show what an account has run up and write it on a sales invoice draft for you to check and issue; nothing is charged by itself.') . '</p>
                        <div class="row g-5" style="max-width:36rem">
                            <div class="col-12 sm:col-6">
                                <label class="form-label" for="late_fee_rate">' . lang('Monthly rate (%)') . '</label>
                                <input type="text" class="form-control text-end" id="late_fee_rate" name="late_fee_rate" value="' . (($late_fee['rate'] > 0) ? h($rate_out($late_fee['rate'])) : '') . '" inputmode="decimal" placeholder="0" autocomplete="off" />
                                <div class="form-text">' . lang('Empty or 0 turns the fee off.') . '</div>
                            </div>
                            <div class="col-12 sm:col-6">
                                <label class="form-label" for="late_fee_tax_rate">' . h(lang(array('string' => '{var:1} rate of the fee (%)', 'vars' => erp_tax_label('tax')))) . '</label>
                                <input type="text" class="form-control text-end" id="late_fee_tax_rate" name="late_fee_tax_rate" value="' . (($late_fee['tax_rate'] !== null) ? h($rate_out($late_fee['tax_rate'])) : '') . '" inputmode="decimal" placeholder="—" autocomplete="off" />
                                <div class="form-text">' . lang('Empty: the rate the account is sold at when a product names none.') . '</div>
                            </div>
                        </div>';
}

/**
 * The e-Fatura threshold card's fields: the amounts System Status weighs the
 * year's sales against. Empty before the upgrade added the columns.
 *
 * @return string
 */
function erp_settings_efatura_threshold_fields()
{
    if (!erp_efatura_threshold_ready()) {
        return '';
    }

    $limits = erp_efatura_threshold_limits();
    $currency = html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return '
                        <p class="fg-secondary">' . lang('A seller outside e-Fatura must join it from the seventh month of the year after its gross sales reached one of these amounts. System Status weighs this year\'s completed orders against them and warns from four fifths on. The tax authority changes the amounts; enter the ones in force. 0 switches a warning off.') . '</p>
                        <div class="row g-5" style="max-width:36rem">
                            <div class="col-12 sm:col-6">
                                <label class="form-label" for="erp_efatura_web_threshold">' . h(lang(array('string' => 'Sales over the internet ({var:1})', 'vars' => $currency))) . '</label>
                                <input type="text" class="form-control text-end" id="erp_efatura_web_threshold" name="erp_efatura_web_threshold" value="' . (int) floor($limits['web'] / 100) . '" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />
                                <div class="form-text">' . lang('Web and marketplace orders; counter sales are left out.') . '</div>
                            </div>
                            <div class="col-12 sm:col-6">
                                <label class="form-label" for="erp_efatura_general_threshold">' . h(lang(array('string' => 'All sales ({var:1})', 'vars' => $currency))) . '</label>
                                <input type="text" class="form-control text-end" id="erp_efatura_general_threshold" name="erp_efatura_general_threshold" value="' . (int) floor($limits['general'] / 100) . '" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />
                                <div class="form-text">' . lang('Every completed order, counter sales included. Invoices written in the ERP without an order are not counted.') . '</div>
                            </div>
                        </div>';
}

/**
 * What a save of the cards would refuse, checked before anything is written,
 * so the Accounting pane can refuse its one Save as a whole. The checks are
 * those of erp_rules_save(), erp_late_fee_settings_save() and
 * erp_efatura_threshold_save(), which still make them on writing.
 *
 * @param array $post   the posted fields
 * @param array $cards  'rules', 'late_fee', 'efatura_threshold': the cards posted
 * @return array field => sentence
 */
function erp_settings_cards_refusals($post, $cards)
{
    $refusals = array();
    $text = function ($name) use ($post) {
        return (isset($post[$name]) && is_string($post[$name])) ? $post[$name] : '';
    };

    if (in_array('rules', $cards, true) && isset($post['vat_exemption_code'])
        && !erp_vat_exemption_code_valid(trim($text('vat_exemption_code')))) {
        $refusals['vat_exemption_code'] = lang('The exemption code is three digits, as the tax authority lists it (for example 351).');
    }

    if (in_array('late_fee', $cards, true)) {
        $rate = (trim($text('late_fee_rate')) === '') ? 0.0 : erp_fx_rate_in($text('late_fee_rate'));

        if (($rate < 0) || ($rate > ERP_LATE_FEE_MAX_RATE)) {
            $refusals['late_fee_rate'] = lang(array('string' => 'The monthly rate runs from 0 to {var:1}.', 'vars' => ERP_LATE_FEE_MAX_RATE));
        }

        $tax_rate = (trim($text('late_fee_tax_rate')) === '') ? null : erp_fx_rate_in($text('late_fee_tax_rate'));

        if (($tax_rate !== null) && (($tax_rate < 0) || ($tax_rate > 100))) {
            $refusals['late_fee_tax_rate'] = lang('The tax rate runs from 0 to 100.');
        }
    }

    if (in_array('efatura_threshold', $cards, true)) {
        foreach (array('erp_efatura_web_threshold', 'erp_efatura_general_threshold') as $field) {
            $value = trim($text($field));
            $value = ($value === '') ? '0' : $value;

            if (!preg_match('/^[0-9]{1,10}$/', $value) || ((int) $value > 1000000000)) {
                $refusals[$field] = lang('Enter a whole amount without separators, from 0 to 1000000000.');
            }
        }
    }

    return $refusals;
}

/**
 * Save the cards that were posted, each through its own save, and log a
 * change. Pass only the cards that were drawn: one whose upgrade has not run
 * is refused.
 *
 * @param array $post   the posted fields
 * @param array $cards  'rules', 'late_fee', 'efatura_threshold': the cards posted
 * @return array ['success' => bool, 'field' => string, 'error' => string] of the first refusal
 */
function erp_settings_cards_save($post, $cards)
{
    $text = function ($name) use ($post) {
        return (isset($post[$name]) && is_string($post[$name])) ? $post[$name] : '';
    };
    $user = (string) ($_SESSION['sessionusername'] ?? '');

    if (in_array('rules', $cards, true)) {
        $input = array();

        if (isset($post['vat_net_withholding'])) {
            $input['vat_net_withholding'] = ($text('vat_net_withholding') === '1');
        }

        if (isset($post['vat_exemption_code'])) {
            $input['vat_exemption_code'] = $text('vat_exemption_code');
        }

        // The rules log their own change.
        $saved = erp_rules_save($input);

        if (!$saved['success']) {
            return $saved;
        }
    }

    if (in_array('late_fee', $cards, true)) {
        $before = erp_late_fee_settings();
        $saved = erp_late_fee_settings_save($text('late_fee_rate'), $text('late_fee_tax_rate'));

        if (!$saved['success']) {
            return $saved;
        }

        if (erp_late_fee_settings() != $before) {
            log_activity(lang('erp late payment fee terms were changed'), $user);
        }
    }

    if (in_array('efatura_threshold', $cards, true)) {
        if (!erp_efatura_threshold_ready()) {
            return array('success' => false, 'field' => 'erp_efatura_web_threshold', 'error' => lang('The settings could not be saved.'));
        }

        $before = db_item("SELECT erp_efatura_web_threshold, erp_efatura_general_threshold FROM config LIMIT 1");
        $saved = erp_efatura_threshold_save($text('erp_efatura_web_threshold'), $text('erp_efatura_general_threshold'));

        if (!$saved['success']) {
            return $saved;
        }

        if (db_item("SELECT erp_efatura_web_threshold, erp_efatura_general_threshold FROM config LIMIT 1") != $before) {
            log_activity(lang('erp e-fatura thresholds were changed'), $user);
        }
    }

    return array('success' => true, 'field' => '', 'error' => '');
}

/**
 * The document templates' state, for the Accounting pane: each document,
 * whether it prints a saved template or the built-in one, and a link to the
 * editor on the ERP Settings screen, which keeps the preview and the reset.
 *
 * @param string $editor_url  the ERP Settings screen
 * @return string
 */
function erp_settings_templates_status($editor_url)
{
    $output = '';

    foreach (erp_settings_documents() as $key => $document) {
        $saved = (str_replace("\r\n", "\n", (string) $document['current']) !== str_replace("\r\n", "\n", (string) $document['default']));
        $url = $editor_url . (($key === 'invoice') ? '' : '?doc=' . $key);

        $output .= '
                        <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <span>' . h($document['label']) . '</span>
                            <span class="d-flex align-items-center gap-3">
                                <span class="badge ' . ($saved ? 'theme-primary' : 'theme-secondary') . '">' . ($saved ? lang('Saved template') : lang('Built-in template')) . '</span>
                                <a href="' . h($url) . '">' . lang('Edit') . '</a>
                            </span>
                        </li>';
    }

    return '
                        <p class="fg-secondary">' . lang('The HTML the PDF documents are rendered from. They are edited, previewed and reset on the ERP Settings screen.') . '</p>
                        <ul class="list-group mb-0">' . $output . '
                        </ul>';
}
