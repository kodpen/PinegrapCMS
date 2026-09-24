<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - settings
 *
 * Behind its own right: the shape of the printed invoice is not an everyday
 * choice. The seller's identity is kept with the rest of the site settings;
 * this screen points at it and owns the invoice template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'settings')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_settings');

$self_url = PATH . SOFTWARE_DIRECTORY . '/erp_settings.php';

// The three printed documents and where each one's template lives. The
// invoice has had a saved template since 4.43; the delivery note and the
// reconciliation letter got theirs in 4.60, so they are offered only once
// that upgrade has run - until then they print from the built-in files.
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

$doc = isset($_REQUEST['doc']) && isset($documents[$_REQUEST['doc']]) ? (string) $_REQUEST['doc'] : 'invoice';
$document = $documents[$doc];
$default_template = $document['default'];
$doc_url = $self_url . (($doc === 'invoice') ? '' : '?doc=' . $doc);

// The accounting rules have a form of their own on this screen.
if ($_POST && ((string) ($_POST['rules'] ?? '') === '1')) {

    validate_token_field();

    $rules_input = array();

    if (isset($_POST['vat_net_withholding'])) {
        $rules_input['vat_net_withholding'] = ((string) $_POST['vat_net_withholding'] === '1');
    }

    if (isset($_POST['vat_exemption_code'])) {
        $rules_input['vat_exemption_code'] = (string) $_POST['vat_exemption_code'];
    }

    $saved = erp_rules_save($rules_input);

    if (!$saved['success']) {
        $liveform->mark_error($saved['field'], h($saved['error']));
    } else {
        $liveform->add_notice(lang('The accounting rules were saved.'));
    }

    go($self_url . '#erp_rules');
}

if ($_POST) {

    validate_token_field();

    // Line endings are normalised so a template pasted from Windows compares
    // equal to the built-in file. A template that matches the built-in one, or
    // that is empty, is stored as NULL: "use the built-in" is the absence of a
    // saved template, so a later upgrade of the file reaches that installation.
    $template = isset($_POST['reset']) ? '' : str_replace("\r\n", "\n", (string) ($_POST['template'] ?? ''));

    if (trim($template) === '' || $template === str_replace("\r\n", "\n", $default_template)) {
        db("UPDATE config SET " . $document['column'] . " = NULL");
        $liveform->add_notice(isset($_POST['reset'])
            ? lang(array('string' => 'The built-in template is back in use for: {var:1}.', 'vars' => $document['label']))
            : lang(array('string' => 'The template has been saved: {var:1}.', 'vars' => $document['label'])));
        log_activity(lang($document['log'] . ' was reset'), $_SESSION['sessionusername']);
    } else {
        db("UPDATE config SET " . $document['column'] . " = '" . escape($template) . "'");
        $liveform->add_notice(lang(array('string' => 'The template has been saved: {var:1}.', 'vars' => $document['label'])));
        log_activity(lang($document['log'] . ' was changed'), $_SESSION['sessionusername']);
    }

    go($doc_url);
}

/**
 * The names a template may use, read off a real document's data: every
 * nested key as a dotted path, with the value that document carries as its
 * description. A list shows its block and the names inside it. A sample says
 * more than a hand-written note would, and it cannot go stale.
 *
 * @param array  $data
 * @param string $prefix
 * @return array  name => description
 */
function erp_settings_placeholders_from_data($data, $prefix = '')
{
    $out = array();

    foreach ((array) $data as $key => $value) {
        $name = ($prefix === '') ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            $is_list = (array_keys($value) === range(0, count($value) - 1));

            if ($is_list) {
                $out[$name] = lang(array('string' => 'A list; repeat a block with {{#{var:1}}} ... {{/{var:1}}}', 'vars' => $name));

                if (!empty($value) && is_array($value[0])) {
                    foreach (erp_settings_placeholders_from_data($value[0], '') as $inner => $description) {
                        $out[$name . ' › ' . $inner] = $description;
                    }
                }
            } else {
                $out = $out + erp_settings_placeholders_from_data($value, $name);
            }

            continue;
        }

        if (is_bool($value)) {
            $out[$name] = $value ? lang('True') : lang('Empty');
        } else {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
            $out[$name] = ($text === '') ? lang('Empty') : mb_substr($text, 0, 60);
        }
    }

    return $out;
}

$settings_url = PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-erp');
$contact_url = PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('contact');
$edoc_url = PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-invoice');

// The names a template may use, grouped the way the data is nested. Kept next
// to the editor because a placeholder nobody can find is a placeholder nobody
// uses.
$placeholders = array(
    lang('Seller') => array(
        'seller.title' => lang('Organization Name'),
        'seller.address_1' => lang('Address') . ' 1',
        'seller.address_2' => lang('Address') . ' 2',
        'seller.city' => lang('City'),
        'seller.state' => lang('State'),
        'seller.zip_code' => lang('Zip Code'),
        'seller.country' => lang('Country'),
        'seller.locality' => lang('Place line in the country\'s order: postcode, city, state, country'),
        'seller.vkn' => (erp_account_country('') === 'TR') ? lang('Seller VKN / TCKN') : lang('Seller tax number'),
        'seller.tax_office' => lang('Seller Tax Office'),
        'seller.web_address' => lang('Address the sale was made at'),
        'seller.email' => lang('Email'),
        'seller.logo_url' => lang('Logo address'),
        'seller.logo_data_uri' => lang('Logo embedded in the document, for the img src'),
    ),
    lang('Account') => array(
        'account.title' => lang('Title'),
        'account.is_person' => lang('True for an individual, empty for a company'),
        'account.tax_number' => erp_tax_id_label(),
        'account.tax_office' => lang('Tax Office'),
        'account.address' => lang('Address'),
        'account.district' => lang('District'),
        'account.city' => lang('City'),
        'account.state' => lang('State / Province'),
        'account.country' => lang('Country'),
        'account.postcode' => lang('Postcode'),
        'account.locality' => lang('Place line in the country\'s order: postcode, city, state, country'),
        'account.email' => lang('Email'),
        'account.phone' => lang('Phone'),
    ),
    lang('Invoice') => array(
        'invoice.full_number' => lang('Document Number'),
        'invoice.title' => lang('Document title: invoice, return invoice or proforma'),
        'invoice.issue_date' => lang('Date'),
        'invoice.due_date' => lang('Due Date'),
        'invoice.is_return' => lang('True for a return invoice'),
        'invoice.status_label' => lang('Status'),
        'invoice.order_number' => lang('Order Number'),
        'invoice.against_number' => lang('Against Invoice'),
        'invoice.currency' => lang('Currency'),
        'invoice.is_foreign' => lang('True when the document is not in the base currency'),
        'invoice.base_currency' => lang('Base currency code'),
        'invoice.exchange_rate' => lang('Exchange rate, empty for a base-currency document'),
        'invoice.exchange_rate_date' => lang('Exchange rate date'),
        'invoice.grand_total_base' => lang('Total in the base currency, empty for a base-currency document'),
        'invoice.is_internet_sale' => lang('True when the sale was made over the internet'),
        'invoice.payment_method_label' => lang('Payment Method'),
        'invoice.payment_date' => lang('Payment Date'),
        'invoice.shipment_date' => lang('Shipment Date'),
        'invoice.carrier_title' => lang('Carrier'),
        'invoice.carrier_vkn' => (erp_account_country('') === 'TR') ? lang('Carrier VKN') : lang('Carrier tax number'),
        'invoice.web_address' => lang('Address the sale was made at'),
        'invoice.notes' => lang('Notes'),
        'invoice.is_purchase' => lang('True for a purchase invoice'),
        'invoice.supplier_invoice_no' => lang('Supplier Invoice Number'),
        'invoice.supplier_invoice_date' => lang('Supplier Invoice Date'),
    ),
    lang('Lines') => array(
        'lines' => lang('The lines of the document; repeat a block with {{#lines}} ... {{/lines}}'),
        'no' => lang('Line number'),
        'description' => lang('Description'),
        'quantity' => lang('Quantity'),
        'unit' => lang('Unit code'),
        'unit_price' => lang('Unit price'),
        'discount' => lang('Discount'),
        'has_discount' => lang('True when the line carries a discount'),
        'base' => lang('Taxable Amount'),
        'tax_rate' => lang('Rate'),
        'tax' => erp_tax_label('tax'),
        'total' => lang('Total'),
    ),
    lang('Totals') => array(
        'totals.subtotal' => lang('Subtotal'),
        'totals.discount_total' => lang('Discount'),
        'totals.shipping_total' => lang('Shipping'),
        'totals.surcharge_total' => lang('Surcharge'),
        'totals.gift_card_total' => lang('Settled by gift card'),
        'totals.tax_total' => erp_tax_label('tax'),
        'totals.grand_total' => lang('Total'),
        'totals.grand_total_base' => lang('Total in the base currency, empty for a base-currency document'),
        'totals.has_discount' => lang('True when there is a discount'),
        'totals.has_shipping' => lang('True when shipping was charged'),
        'totals.has_surcharge' => lang('True when a surcharge was added'),
        'totals.has_gift_card' => lang('True when a gift card was used'),
        'generated_at' => lang('The moment the document was generated'),
        'language' => lang('The language code of the site, for the html lang attribute'),
        'paper' => lang('Paper size for the @page rule: letter in the US, Canada and much of Latin America, A4 elsewhere'),
    ),
);

// The captions the built-in template prints, in the site language. They are
// listed by what they print, since the caption is its own description.
$placeholders[lang('Labels')] = array();
foreach (erp_invoice_document_labels() as $name => $caption) {
    $placeholders[lang('Labels')]['label.' . $name] = $caption;
}

// The other two documents describe themselves from the latest one on file;
// with none yet, the built-in labels are all there is to list.
if ($doc === 'waybill') {
    $sample_id = (int) db_value("SELECT id FROM erp_waybills WHERE status <> 'draft' ORDER BY id DESC LIMIT 1");
    $sample = ($sample_id > 0) ? erp_waybill_document_data($sample_id) : false;
    $placeholders = array(
        lang('Delivery Note') => is_array($sample)
            ? erp_settings_placeholders_from_data($sample)
            : array('waybill.full_number' => lang('Issue a delivery note first; its fields will be listed here with their values.')),
    );
} elseif ($doc === 'reconciliation') {
    $sample_account = (int) db_value("SELECT account_id FROM erp_account_transactions ORDER BY id DESC LIMIT 1");
    $sample = ($sample_account > 0) ? erp_reconciliation_data($sample_account, erp_reconciliation_options(array())) : false;
    $placeholders = array(
        lang('Reconciliation Letter') => is_array($sample)
            ? erp_settings_placeholders_from_data($sample)
            : array('account.title' => lang('Record a movement on an account first; the letter fields will be listed here with their values.')),
    );
}

$output_placeholders = '';

foreach ($placeholders as $group => $names) {
    $output_placeholders .= '
                                <div class="col-12 col-md-6 col-xl-4 mb-3">
                                    <div class="fw-bold text-uppercase small text-body-secondary mb-2">' . h($group) . '</div>
                                    <dl class="row small mb-0">';

    foreach ($names as $name => $description) {
        $output_placeholders .= '
                                        <dt class="col-6 font-monospace fw-normal text-truncate">{{' . h($name) . '}}</dt>
                                        <dd class="col-6 text-body-secondary mb-1">' . h($description) . '</dd>';
    }

    $output_placeholders .= '
                                    </dl>
                                </div>';
}

// The accounting rules: the withholding and the exemption code concern
// Turkish VAT and e-documents, so they are offered where those are in use.
$rules = erp_rules();
$rules_turkish = erp_turkish_features();
$output_rules = '';

if (erp_rules_ready() && $rules_turkish) {
    $output_rules .= '
                        <fieldset class="mb-4">
                            <legend class="form-label fs-6">' . lang('VAT withheld on sales, in the VAT report and the accountant\'s pack') . '</legend>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_net_withholding" id="vat_net_withholding_0" value="0"' . ($rules['vat_net_withholding'] ? '' : ' checked') . ' />
                                <label class="form-check-label" for="vat_net_withholding_0">' . lang('Shown beside the calculated VAT, which stays in full') . '</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_net_withholding" id="vat_net_withholding_1" value="1"' . ($rules['vat_net_withholding'] ? ' checked' : '') . ' />
                                <label class="form-check-label" for="vat_net_withholding_1">' . lang('Taken off the calculated VAT, since the buyer declares that part') . '</label>
                            </div>
                            <div class="form-text">' . lang('The invoices do not change: they carry the whole VAT and the withheld part either way. Only the difference the report works out does.') . '</div>
                        </fieldset>
                        <div class="mb-4" style="max-width:36rem">
                            <label class="form-label" for="vat_exemption_code">' . lang('Exemption code for zero-rated lines') . '</label>
                            <input type="text" class="form-control font-monospace" style="max-width:8rem" id="vat_exemption_code" name="vat_exemption_code" value="' . h($rules['vat_exemption_code']) . '" maxlength="3" inputmode="numeric" pattern="[0-9]{3}" placeholder="351" autocomplete="off" />
                            <div class="form-text">' . lang('A 0% line on an e-document has to say why it carries no VAT. A line takes its product\'s code when the product has one, otherwise this one; empty leaves the line without a code. Which code fits your sales is for your accountant to say.') . '</div>
                        </div>';
}

$output_rules .= '
                        <p class="mb-0">' . lang('Whether an expense category takes its VAT back is set with the categories.') . '
                            <a href="erp_expense_categories.php">' . lang('Expense categories') . '</a></p>';

$output_document_pills = '';
foreach ($documents as $key => $item) {
    $output_document_pills .= '
                            <li class="nav-item"><a class="nav-link py-1 px-2' . (($key === $doc) ? ' active' : '') . '" href="' . h($self_url . (($key === 'invoice') ? '' : '?doc=' . $key)) . '">' . h($item['label']) . '</a></li>';
}

echo pg_page_shell(array(
    'title'               => lang('ERP Settings'),
    'extra classes'       => 'erp erp_settings',
    'icon'                => 'erp',
    'heading'             => lang('ERP Settings'),
    'heading_description' => lang('Where the seller is named, the accounting rules and how the printed documents look.'),
    'cancel'              => false,
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Seller Information') . '
                </div>
                <div class="card-body">
                    <p class="mb-2">' . lang('The seller title, address and logo printed on the invoice come from the organization settings.') . '
                        <a href="' . h($contact_url) . '">' . lang('Site Settings') . ' &rsaquo; ' . lang('Contact') . '</a></p>
                    <p class="mb-2">' . lang('The seller tax number, tax office and the address the sale was made at are on the ERP card of the commerce settings.') . '
                        <a href="' . h($settings_url) . '">' . lang('Site Settings') . ' &rsaquo; ' . lang('Commerce') . ' &rsaquo; ' . lang('ERP') . '</a></p>
                    <p class="mb-0">' . lang('The e-document provider (Paraşüt, Logo İşbaşı) and its credentials are on the E-Invoice card of the commerce settings.') . '
                        <a href="' . h($edoc_url) . '">' . lang('Site Settings') . ' &rsaquo; ' . lang('Commerce') . ' &rsaquo; ' . lang('E-Invoice') . '</a></p>
                </div>
            </div>

            <form action="erp_settings.php" method="post" id="erp_rules">
                ' . get_token_field() . '
                <input type="hidden" name="rules" value="1">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Accounting rules') . '</div>
                    <div class="card-body">
                        <p class="text-body-secondary">' . lang('Choices your accountant settles for the store; until then the ERP works as it always has.') . '</p>
                        ' . $output_rules . '
                        ' . ((erp_rules_ready() && $rules_turkish) ? '<div class="mt-3"><button type="submit" class="btn btn-sm btn-primary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-check2 me-2" aria-hidden="true"></i>' . lang('Save') . '</button></div>' : '') . '
                    </div>
                </div>
            </form>

            <form name="form" action="erp_settings.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="doc" value="' . h($doc) . '">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h($document['label']) . '</span>
                        <ul class="nav nav-pills nav-sm mb-0">' . $output_document_pills . '
                        </ul>
                    </div>
                    <div class="card-body">
                        <nav id="button_bar" class="navigation mb-3" aria-label="Button Bar">
                            <button type="submit" name="submit" value="Save" class="btn btn-sm btn-primary m-1" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-check2 me-2"></i><span class="btn-text">' . lang('Save') . '</span></button>
                            <button type="submit" class="btn btn-sm btn-outline-secondary m-1" formaction="' . h($document['preview']) . '" formmethod="post" formtarget="_blank"><i class="bi bi-eye me-2"></i>' . lang('Preview') . '</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary m-1" data-bs-toggle="collapse" data-bs-target="#placeholder_reference" aria-expanded="false" aria-controls="placeholder_reference"><i class="bi bi-braces me-2"></i>' . lang('Placeholder Reference') . '</button>
                            <button type="submit" name="reset" value="1" class="btn btn-sm btn-outline-warning m-1" onclick="return confirm(' . h(json_encode(lang('The saved template will be replaced by the built-in one. Continue?'))) . ');"><i class="bi bi-arrow-counterclockwise me-2"></i>' . lang('Reset to Default') . '</button>
                        </nav>

                        <div class="collapse mb-3" id="placeholder_reference">
                            <div class="border rounded p-3">
                                <p class="small text-body-secondary">' . lang('Write a name in double braces to print it: {{seller.title}}. Triple braces print it without escaping. A block between {{#name}} and {{/name}} is shown when the value is set, and repeated for every line when the value is a list; a block between {{^name}} and {{/name}} is shown when it is empty.') . '</p>
                                <div class="row">' . $output_placeholders . '
                                </div>
                            </div>
                        </div>

                        <textarea class="form-control font-monospace" name="template" id="template" rows="28" spellcheck="false" aria-label="' . h($document['label']) . '">' . h($document['current']) . '</textarea>
                        <div class="form-text">' . lang('The HTML the PDF document is rendered from. Saving it unchanged, or empty, keeps the built-in template.') . '</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
