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
$default_template = erp_invoice_default_template();

if ($_POST) {

    validate_token_field();

    // Line endings are normalised so a template pasted from Windows compares
    // equal to the built-in file. A template that matches the built-in one, or
    // that is empty, is stored as NULL: "use the built-in" is the absence of a
    // saved template, so a later upgrade of the file reaches that installation.
    $template = isset($_POST['reset']) ? '' : str_replace("\r\n", "\n", (string) ($_POST['template'] ?? ''));

    if (trim($template) === '' || $template === str_replace("\r\n", "\n", $default_template)) {
        db("UPDATE config SET erp_invoice_template = NULL");
        $liveform->add_notice(isset($_POST['reset'])
            ? lang('The built-in invoice template is back in use.')
            : lang('The invoice template has been saved.'));
        log_activity(lang('erp invoice template was reset'), $_SESSION['sessionusername']);
    } else {
        db("UPDATE config SET erp_invoice_template = '" . escape($template) . "'");
        $liveform->add_notice(lang('The invoice template has been saved.'));
        log_activity(lang('erp invoice template was changed'), $_SESSION['sessionusername']);
    }

    go($self_url);
}

$settings_url = PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-erp');
$contact_url = PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('contact');

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
        'seller.vkn' => lang('Seller VKN / TCKN'),
        'seller.tax_office' => lang('Seller Tax Office'),
        'seller.web_address' => lang('Address the sale was made at'),
        'seller.email' => lang('Email'),
        'seller.logo_url' => lang('Logo address'),
        'seller.logo_data_uri' => lang('Logo embedded in the document, for the img src'),
    ),
    lang('Account') => array(
        'account.title' => lang('Title'),
        'account.is_person' => lang('True for an individual, empty for a company'),
        'account.tax_number' => lang('VKN / TCKN'),
        'account.tax_office' => lang('Tax Office'),
        'account.address' => lang('Address'),
        'account.district' => lang('District'),
        'account.city' => lang('City'),
        'account.country' => lang('Country'),
        'account.postcode' => lang('Postcode'),
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
        'invoice.carrier_vkn' => lang('Carrier VKN'),
        'invoice.web_address' => lang('Address the sale was made at'),
        'invoice.notes' => lang('Notes'),
    ),
    lang('Lines') => array(
        'lines' => lang('The lines of the document; repeat a block with {{#lines}} ... {{/lines}}'),
        'no' => lang('Line number'),
        'description' => lang('Description'),
        'quantity' => lang('Quantity'),
        'unit_price' => lang('Unit price'),
        'discount' => lang('Discount'),
        'has_discount' => lang('True when the line carries a discount'),
        'base' => lang('Taxable Amount'),
        'tax_rate' => lang('Rate'),
        'tax' => lang('VAT'),
        'total' => lang('Total'),
    ),
    lang('Totals') => array(
        'totals.subtotal' => lang('Subtotal'),
        'totals.discount_total' => lang('Discount'),
        'totals.shipping_total' => lang('Shipping'),
        'totals.surcharge_total' => lang('Surcharge'),
        'totals.gift_card_total' => lang('Settled by gift card'),
        'totals.tax_total' => lang('VAT'),
        'totals.grand_total' => lang('Total'),
        'totals.grand_total_base' => lang('Total in the base currency, empty for a base-currency document'),
        'totals.has_discount' => lang('True when there is a discount'),
        'totals.has_shipping' => lang('True when shipping was charged'),
        'totals.has_surcharge' => lang('True when a surcharge was added'),
        'totals.has_gift_card' => lang('True when a gift card was used'),
        'generated_at' => lang('The moment the document was generated'),
    ),
);

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

echo pg_page_shell(array(
    'title'               => lang('ERP Settings'),
    'extra_classes'       => 'erp erp_settings',
    'icon'                => 'store',
    'heading'             => lang('ERP Settings'),
    'heading_description' => lang('Where the seller is named and how the printed invoice looks.'),
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
                    <p class="mb-0">' . lang('The seller tax number, tax office and the address the sale was made at are on the ERP card of the commerce settings.') . '
                        <a href="' . h($settings_url) . '">' . lang('Site Settings') . ' &rsaquo; ' . lang('Commerce') . ' &rsaquo; ' . lang('ERP') . '</a></p>
                </div>
            </div>

            <form name="form" action="erp_settings.php" method="post">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Invoice Template') . '
                    </div>
                    <div class="card-body">
                        <nav id="button_bar" class="navigation mb-3" aria-label="Button Bar">
                            <button type="submit" name="submit" value="Save" class="btn btn-sm btn-primary m-1" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-check2 me-2"></i><span class="btn-text">' . lang('Save') . '</span></button>
                            <button type="submit" class="btn btn-sm btn-outline-secondary m-1" formaction="get_erp_invoice_pdf.php" formmethod="post" formtarget="_blank"><i class="bi bi-eye me-2"></i>' . lang('Preview') . '</button>
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

                        <textarea class="form-control font-monospace" name="template" id="template" rows="28" spellcheck="false" aria-label="' . lang('Invoice Template') . '">' . h(erp_invoice_template()) . '</textarea>
                        <div class="form-text">' . lang('The HTML the PDF invoice is rendered from. Saving it unchanged, or empty, keeps the built-in template.') . '</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
