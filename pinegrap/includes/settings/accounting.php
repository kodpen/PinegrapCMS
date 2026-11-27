<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Accounting pane: the ERP and the e-document provider.
 *
 * Enable the ERP module stands at the pane's head; under it each part is one
 * folded row (includes/settings/fold.php) holding its card, the rows' order,
 * names and lines being includes/fn/accounting_settings_fold.php. The ERP's
 * fields are cut by topic into Documents, Sales, Web orders, Customer
 * accounts, Foreign currency and Reminders and notices; the module's other
 * cards follow. Fills $pg_settings_cards with the head and the one list of
 * rows. Which cards belong here is said once, in
 * includes/settings/registry.php; this file holds them, and
 * accounting.save.php writes what they edit, every field under its old name.
 * The variables they read are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// The accounting rules, the late payment fee, the e-Fatura thresholds and the
// document templates are drawn by the ERP module, which draws them on the ERP
// Settings screen too.
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_accounting_fold_order(). A card the site does not show (the
// Turkish e-document cards elsewhere) is drawn hidden after the rows, as
// before, so a save still finds its fields.
$accounting_parts = array();
$accounting_hidden = '';

// ── Enable the ERP module ──
// Above the rows, in the same form as the invoice series: the save checks one
// with the other and runs the module's setup after it.
$accounting_head = '
    <div class="d-flex flex-column gap-3 mb-5">
        <div class="d-flex align-items-center gap-5">
            <input type="checkbox" class="pg-switch" value="1"' . $erp_enabled_checked . ' id="erp_enabled" name="erp_enabled" aria-describedby="erp_enabled_help"/>
            <label class="pg-text-2" for="erp_enabled">' . lang('Enable the ERP module') . '</label>
        </div>
        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="erp_enabled_help">' . lang('Adds accounts, cash and bank, invoices and delivery notes to the panel. Switching it off hides the screens; nothing that was entered is deleted.') . '</p>
    </div>';

// ── Documents ──
// The series, numbers, tax names, the sale's address and the payment term.
// Keeps the ERP card's id, so every link to that card lands on this row.
$accounting_parts['erp'] = array('html' => '
    <div id="pgset-erp" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Documents') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_series">' . lang('Invoice series') . '</label>
                        <input type="text" class="form-control" id="erp_default_series" name="erp_default_series" value="' . h($erp_default_series) . '" maxlength="10" pattern="[A-Za-z0-9]{1,10}" autocapitalize="characters" spellcheck="false" autocomplete="off" aria-describedby="erp_default_series_help" />
                        <div class="form-text" id="erp_default_series_help">' . lang('The letters every invoice number starts with, for example PGF (three letters in Turkey). Delivery notes add S to them, returns I, purchase invoices A.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label class="form-label" for="erp_number_style">' . lang('Document numbers') . '</label>
                        <select class="form-control" id="erp_number_style" name="erp_number_style" aria-describedby="erp_number_style_help">' . $erp_number_options . '</select>
                        <div class="form-text" id="erp_number_style_help">' . lang('Applies to numbers taken from now on; documents already issued keep theirs. Changing it never repeats a number.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_tax_name">' . lang('Tax name on documents') . '</label>
                        <input type="text" class="form-control" id="erp_tax_name" name="erp_tax_name" value="' . h($erp_tax_name) . '" maxlength="30" placeholder="' . h(lang('VAT')) . '" autocomplete="off" aria-describedby="erp_tax_name_help" />
                        <div class="form-text" id="erp_tax_name_help">' . lang('Leave empty for VAT. Write the name your invoices use instead, for example Sales tax or GST; it is printed as written.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_tax2_name">' . lang('Second tax on invoice lines') . '</label>
                        <input type="text" class="form-control" id="erp_tax2_name" name="erp_tax2_name" value="' . h($erp_tax2_name) . '" maxlength="30" autocomplete="off" aria-describedby="erp_tax2_name_help" />
                        <div class="form-text" id="erp_tax2_name_help">' . lang('For places that charge two taxes on the same amount, such as GST and PST in Canada: name the second one and every invoice line gets a rate for it. Leave empty for one tax per line.') . '</div>
                    </div>
                    <div class="pg-f-md' . ((pg_erp_store_country() === 'TR') ? '' : ' d-none') . '">
                        <label class="form-label" for="erp_web_address">' . lang('Address the sale was made at') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" class="form-control" id="erp_web_address" name="erp_web_address" value="' . h($erp_web_address) . '" maxlength="255" autocomplete="off" aria-describedby="erp_web_address_help" />
                        <div class="form-text" id="erp_web_address_help">' . lang('Printed on invoices for internet sales, which have to name it. For example www.example.com.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_due_days">' . lang('Default payment term (days)') . '</label>
                        <input type="number" class="form-control" id="erp_default_due_days" name="erp_default_due_days" value="' . (int) $erp_default_due_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" aria-describedby="erp_default_due_days_help" />
                        <div class="form-text" id="erp_default_due_days_help">' . lang('A new invoice falls due this many days after its date, unless the account carries its own term. 0 means due on the invoice date.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_documents($erp_default_series, $erp_number_style, $erp_default_due_days);

// ── Sales ──
// The walk-in sales account, the till's prices and whether invoices move stock.
$accounting_walkin_name = '';

foreach ($erp_walkin_accounts as $accounting_walkin) {
    if ((int) $accounting_walkin['id'] === $erp_walkin_account_id) {
        $accounting_walkin_name = (string) $accounting_walkin['title'];
    }
}

$accounting_parts['erp-sales'] = array('html' => '
    <div id="pgset-erp-sales" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Sales') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_walkin_account_id">' . lang('Walk-in sales account') . '</label>
                        <select class="form-control" id="erp_walkin_account_id" name="erp_walkin_account_id" aria-describedby="erp_walkin_account_id_help">' . $erp_walkin_options . '</select>
                        <div class="form-text" id="erp_walkin_account_id_help">' . lang('A local sale made without picking a customer is billed to this account when it is invoiced. Open an account named for the purpose, such as "Retail customer".') . '</div>
                    </div>' . pg_accounting_settings_choice('local_sale_prices', lang('Prices on the till'), array(
                        array('value' => 'gross', 'label' => lang('With tax included (VAT countries)'), 'line' => lang('The local sale screen shows each price with its tax.'), 'on' => $local_sale_prices === 'gross'),
                        array('value' => 'net', 'label' => lang('Without tax, added at the total (sales-tax countries)'), 'line' => lang('The local sale screen shows prices without tax and adds the tax at the total.'), 'on' => $local_sale_prices === 'net'),
                    ), lang('Product prices are kept without tax and the amount charged is the same.'))
                    . pg_accounting_settings_switch('erp_stock_documents', $erp_stock_documents_checked !== '', lang('Invoices change stock'), lang('On, a purchase invoice adds its products to stock and a sales invoice typed in the ERP takes them out; an order\'s invoice leaves stock alone, as the order already took them. Off, only the costs are kept.')) . '
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_sales($local_sale_prices, $erp_stock_documents_checked !== '', $accounting_walkin_name);

// ── Web orders ──
// The invoice of a web order with its filters and the marketplaces, the
// invoice e-mail, and how an order's shipping is written on its invoice.
$accounting_parts['erp-orders'] = array('html' => '
    <div id="pgset-erp-orders" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Web orders') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . ($erp_auto_invoice_ready ? pg_accounting_settings_choice('erp_auto_invoice_on', lang('The invoice of a web order'), array(
                        array('value' => 'off', 'label' => lang('Is issued from the order screen'), 'line' => lang('Nothing is issued on its own; the order screen\'s Issue the Invoice step writes it.'), 'on' => $erp_auto_invoice_on === 'off'),
                        array('value' => 'order_paid', 'label' => lang('Is issued on its own once the order is paid'), 'line' => lang('Paid is a card, PayPal or iyzico payment at checkout, a bank transfer recorded with Payment Received, or an order put on the customer\'s account.'), 'on' => $erp_auto_invoice_on === 'order_paid'),
                        array('value' => 'order_shipped', 'label' => lang('Is issued on its own once the order is shipped'), 'line' => lang('With the checks of Issue the Invoice: an order it cannot invoice yet waits there with the reason, and the panel bell names it.'), 'on' => $erp_auto_invoice_on === 'order_shipped'),
                    ), $erp_auto_invoice_marketplace_ready
                        ? lang('Counter sales are still invoiced at the till and marketplace orders from their screen unless the switch below is on; the e-document is still made official from the order screen.')
                        : lang('Counter sales are still invoiced at the till and marketplace orders from their screen; the e-document is still made official from the order screen.')) : '') . ($erp_auto_invoice_filters_ready ? '
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_auto_invoice_min_total">' . h(lang(array('string' => 'Smallest order invoiced on its own ({var:1})', 'vars' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) . '</label>
                        <input type="number" class="form-control" id="erp_auto_invoice_min_total" name="erp_auto_invoice_min_total" value="' . (int) $erp_auto_invoice_min_total . '" min="0" max="10000000" step="1" inputmode="numeric" autocomplete="off" aria-describedby="erp_auto_invoice_min_total_help" />
                        <div class="form-text" id="erp_auto_invoice_min_total_help">' . lang('A smaller order waits for Issue the Invoice on its screen. The amount is the whole sale the invoice states, a gift card included. 0 invoices every order.') . '</div>
                    </div>' . pg_accounting_settings_switch('erp_auto_invoice_tax_number_only', $erp_auto_invoice_tax_number_only_checked !== '', lang('Only for buyers with a tax number'), (pg_erp_store_country() === 'TR')
                        ? lang('Read from the account the invoice goes to, or from the contact while the order has no account. A VKN or a TCKN counts; the final consumer number 11111111111 does not. An order without one waits for Issue the Invoice on its screen.')
                        : lang('Read from the account the invoice goes to, or from the contact while the order has no account. An order without one waits for Issue the Invoice on its screen.')) : '')
                    . ($erp_auto_invoice_marketplace_ready ? pg_accounting_settings_switch('erp_auto_invoice_marketplace', $erp_auto_invoice_marketplace_checked !== '', lang('Also invoice marketplace orders (Trendyol, n11)'), lang('A marketplace order is paid when it is imported and shipped when its tracking number arrives; the same moment and filters apply. Leave it off if you invoice them elsewhere. The invoice link is still typed on the Imported orders card.')) : '')
                    . ($erp_invoice_mail_ready ? pg_accounting_settings_switch('erp_invoice_mail_auto', $erp_invoice_mail_auto_checked !== '', lang('E-mail each issued invoice to the customer'), lang('Sent with its PDF to the account\'s invoice e-mail address when it is issued, or once GİB has accepted it where e-documents are in use. Off for a single account on its card.')) . '
                    <div class="col-12">
                        <label class="form-label" for="erp_invoice_mail_message">' . lang('Invoice e-mail message') . '</label>
                        <textarea class="form-control" id="erp_invoice_mail_message" name="erp_invoice_mail_message" rows="2" maxlength="2000" placeholder="' . h(lang('Thank you for your purchase. Your invoice is attached to this e-mail.')) . '" aria-describedby="erp_invoice_mail_message_help">' . h($erp_invoice_mail_message) . '</textarea>
                        <div class="form-text" id="erp_invoice_mail_message_help">' . lang('The text above the invoice\'s figures in the e-mail. Empty uses the text shown.') . '</div>
                    </div>' : '')
                    . pg_accounting_settings_choice('erp_shipping_tax', lang('Tax on shipping and surcharges'), array(
                        array('value' => '', 'label' => lang('By the store\'s country'), 'line' => lang('Untaxed in the United States, included everywhere else.'), 'on' => $erp_shipping_tax === ''),
                        array('value' => 'included', 'label' => lang('Included in the amount charged, at the rate of the goods'), 'line' => lang('The tax is inside the amount (50.00 at 20% is 41.67 + 8.33) and the invoice total stays the same; goods at several rates share the charge.'), 'on' => $erp_shipping_tax === 'included'),
                        array('value' => 'none', 'label' => lang('Not taxed'), 'line' => lang('The charge is written on the invoice without tax.'), 'on' => $erp_shipping_tax === 'none'),
                    ), lang('How an order\'s shipping, surcharge and installment charge are written on its invoice; the customer paid them with no tax added.')) . '
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_orders($erp_auto_invoice_ready ? $erp_auto_invoice_on : null, $erp_invoice_mail_ready ? ($erp_invoice_mail_auto_checked !== '') : null, $erp_shipping_tax);

// ── Customer accounts ──
// The credit limit, account prices and paying on account; drawn once the
// upgrade added one of them.
if ($erp_credit_ready || $erp_storefront_prices_ready || $erp_open_account_ready) {
    $accounting_parts['erp-accounts'] = array('html' => '
    <div id="pgset-erp-accounts" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Customer accounts') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . ($erp_credit_ready ? pg_accounting_settings_choice('erp_credit_limit_mode', lang('A sale past a customer\'s credit limit'), array(
                        array('value' => 'warn', 'label' => lang('Is issued, with a warning'), 'line' => '', 'on' => $erp_credit_limit_mode === 'warn'),
                        array('value' => 'block', 'label' => lang('Is refused until a payment comes in'), 'line' => '', 'on' => $erp_credit_limit_mode === 'block'),
                    ), lang('The limit is set on each account card. Weighed on invoices typed in the ERP; an order\'s invoice is never held back.')) : '')
                    . ($erp_storefront_prices_ready ? pg_accounting_settings_switch('erp_storefront_account_prices', $erp_storefront_account_prices_checked !== '', lang('Account prices in the store'), lang('A customer whose contact is linked to an account sees and pays that account\'s prices in the store while signed in: its own price for a product, or its discount on the list price. A campaign that goes lower still wins; the two never add up. Off, everyone sees the list price.')) : '')
                    . ($erp_open_account_ready ? pg_accounting_settings_switch('erp_open_account_payment', $erp_open_account_payment_checked !== '', lang('Put it on my account at checkout'), lang('A signed-in customer whose account has a credit limit can put an order on the account instead of paying: it is billed to the account with the account\'s payment terms. Refused when the order would take the account past its limit, counting the orders on account not invoiced yet. The order counts as paid for an invoice issued on its own and is never cancelled for an unpaid transfer.')) : '') . '
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_accounts(
        $erp_credit_ready ? $erp_credit_limit_mode : null,
        $erp_storefront_prices_ready ? ($erp_storefront_account_prices_checked !== '') : null,
        $erp_open_account_ready ? ($erp_open_account_payment_checked !== '') : null
    );
}

// ── Foreign currency ──
// The switch, then what it governs under it, shown whatever its state.
$accounting_parts['erp-currency'] = array('html' => '
    <div id="pgset-erp-currency" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Foreign currency') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_accounting_settings_switch('erp_fx_enabled', $erp_fx_enabled === 1, lang('Enable foreign-currency invoices and accounts'), lang('Off, everything in the ERP is in the store\'s base currency. On, an invoice, an account or a till may be kept in one of the currencies below; amounts stay in that currency and the day\'s exchange rate converts them for the ledger.')) . '
                    <div class="col-12" role="group" aria-labelledby="erp_fx_currencies_title">
                        <div class="form-label" id="erp_fx_currencies_title">' . lang('Currencies allowed on documents') . '</div>
                        ' . $output_erp_fx_currencies . '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . lang('The base currency is always allowed. Daily rates for these come from the Exchange rates job; run Update Exchange Rates on the currencies screen to fetch today\'s.') . '</p>
                    </div>' . pg_accounting_settings_switch('erp_fx_auto_diff', $erp_fx_auto_diff_checked !== '', lang('Post the exchange difference automatically when a foreign-currency invoice is paid off'), lang('The invoice went on the account at the rate of its issue date and the receipts came off at the rate of their own day. When the invoice is paid, one movement in the base currency settles the gap so the account closes to zero.')) . '
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_currency($erp_fx_enabled === 1, BASE_CURRENCY_CODE, $erp_fx_selected);

// ── Reminders and notices ──
// Overdue receivable reminders, then the notices as they happen, each under
// its group label: the row holds more than eight fields.
$accounting_notices = null;

if ($erp_alerts_ready) {
    $accounting_notices = (int) ($erp_notify_collections_checked !== '') + (int) ($erp_notify_low_stock_checked !== '')
        + (int) ($erp_quote_alert_ready && ($erp_notify_quote_expiry_checked !== ''));
}

$accounting_parts['erp-notices'] = array('html' => '
    <div id="pgset-erp-notices" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Reminders and notices') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Overdue receivable reminders') . '</h3></div>
                    <div class="pg-f-sm">
                        <label class="form-label" for="erp_overdue_notify_days">' . lang('Reminder threshold (days)') . '</label>
                        <input type="number" class="form-control" id="erp_overdue_notify_days" name="erp_overdue_notify_days" value="' . (int) $erp_overdue_notify_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" aria-describedby="erp_overdue_notify_days_help" />
                        <div class="form-text" id="erp_overdue_notify_days_help">' . lang('Sales invoices that pass this many days overdue are announced in the panel bell, by e-mail and on a subscribed device, and once more if still open a month later. An account can carry a threshold of its own. 0 turns the reminders off.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="row gy-5">
                            <div class="col-6">
                                <label class="form-label" for="erp_overdue_notify_frequency">' . lang('Frequency') . '</label>
                                <select class="form-control" id="erp_overdue_notify_frequency" name="erp_overdue_notify_frequency">
                                    <option value="daily"' . (($erp_overdue_notify_frequency === 'daily') ? ' selected="selected"' : '') . '>' . lang('Daily') . '</option>
                                    <option value="weekly"' . (($erp_overdue_notify_frequency === 'weekly') ? ' selected="selected"' : '') . '>' . lang('Weekly') . '</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="erp_overdue_notify_hour">' . lang('Hour') . '</label>
                                <select class="form-control" id="erp_overdue_notify_hour" name="erp_overdue_notify_hour">' . $output_erp_overdue_hours . '</select>
                            </div>
                        </div>
                    </div>' . pg_accounting_settings_ticks('erp_overdue_notify_channels', lang('Send through'),
                        pg_accounting_settings_tick('erp_overdue_notify_panel', $erp_overdue_notify_panel_checked !== '', lang('Panel notification'))
                        . pg_accounting_settings_tick('erp_overdue_notify_email', $erp_overdue_notify_email_checked !== '', lang('E-mail'))
                        . pg_accounting_settings_tick('erp_overdue_notify_push', $erp_overdue_notify_push_checked !== '', lang('Device notification'))
                        . $output_erp_overdue_push_hint)
                    . pg_accounting_settings_switch('erp_overdue_notify_customer', $erp_overdue_notify_customer_checked !== '', lang('Reminder e-mail to the customer'), lang('When a customer\'s invoices pass the threshold, a short reminder listing them goes to the e-mail address on their account, from the store address, with a second one a month later. Off for a single account on its card.')) . '
                    <div class="col-12">
                        <label class="form-label" for="erp_overdue_notify_recipients">' . lang('Recipients') . '</label>
                        <input type="text" inputmode="email" spellcheck="false" autocapitalize="off" class="form-control" id="erp_overdue_notify_recipients" name="erp_overdue_notify_recipients" value="' . h($erp_overdue_notify_recipients) . '" maxlength="500" autocomplete="off" placeholder="' . h($erp_overdue_notify_default_recipient) . '" aria-describedby="erp_overdue_notify_recipients_help" />
                        <div class="form-text" id="erp_overdue_notify_recipients_help">' . lang('Comma-separated. Empty uses the store e-mail address.') . '</div>
                    </div>' . ($erp_alerts_ready ? '
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Notices as they happen') . '</h3>
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . lang('On the panel bell and on the devices that subscribed to notifications. The person who recorded it is not told.') . '</p>
                    </div>' . pg_accounting_settings_switch('erp_notify_collections', $erp_notify_collections_checked !== '', lang('A collection is recorded')) . '
                    <div class="pg-f-sm">
                        <label class="form-label" for="erp_notify_collection_min">' . h(lang(array('string' => 'From this amount up ({var:1})', 'vars' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) . '</label>
                        <input type="number" class="form-control" id="erp_notify_collection_min" name="erp_notify_collection_min" value="' . (int) $erp_notify_collection_min . '" min="0" max="100000000" step="1" inputmode="numeric" autocomplete="off" />
                    </div>' . pg_accounting_settings_switch('erp_notify_low_stock', $erp_notify_low_stock_checked !== '', lang('A product drops to its minimum stock'), lang('Checked every hour; each product is announced once each time it drops.')) . (($output_erp_overdue_push_hint !== '') ? '
                    <div class="col-12 mt-0">' . $output_erp_overdue_push_hint . '</div>' : '')
                    . ($erp_quote_alert_ready ? pg_accounting_settings_switch('erp_notify_quote_expiry', $erp_notify_quote_expiry_checked !== '', lang('Announce quotes about to expire'), lang('Checked every hour; each open quote is announced once, and again after it is opened again or its date changes.')) . '
                    <div class="pg-f-sm">
                        <label class="form-label" for="erp_notify_quote_expiry_days">' . lang('Days before it expires') . '</label>
                        <input type="number" class="form-control" id="erp_notify_quote_expiry_days" name="erp_notify_quote_expiry_days" value="' . (int) $erp_notify_quote_expiry_days . '" min="1" max="30" step="1" inputmode="numeric" autocomplete="off" />
                    </div>' . ($erp_quote_followup_ready ? pg_accounting_settings_switch('erp_quote_expiry_followup', $erp_quote_expiry_followup_checked !== '', lang('Open a Workspace follow-up for each quote announced'), lang('A task for the person who made the quote, due on its last day. Needs the notice above and the Workspace.')) : '') : '') : '') . '
                </div>
            </div>
        </div>
    </div>') + pg_accounting_fold_notices($erp_overdue_notify_days, $erp_overdue_notify_frequency, $erp_overdue_notify_hour, $accounting_notices);

// ── E-Invoice ──
// The provider in use and its connection; a provider picked and not
// connected asks for attention, so the pane arrives with this row open.
$accounting_edoc_label = '';
$accounting_edoc_connected = false;

if ($erp_edoc_installed) {
    $accounting_edoc_code = erp_edoc_active();
    $accounting_edoc_info = ($accounting_edoc_code !== '') ? erp_edoc_info($accounting_edoc_code) : null;

    if (is_array($accounting_edoc_info)) {
        $accounting_edoc_label = (string) $accounting_edoc_info['label'];
        $accounting_edoc_connected = erp_edoc_connected($accounting_edoc_code);
    }
}

$accounting_invoice_card = '
    <div id="pgset-invoice" class="pg-set-card' . (pg_edoc_settings_shown() ? '' : ' d-none') . '">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('E-Invoice') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . (($output_erp_edoc_options !== '') ? '
                    <div class="col-12" role="group" aria-labelledby="erp_edoc_provider_title">
                        <div class="form-label" id="erp_edoc_provider_title">' . lang('e-Document Provider') . ' <span class="pg-field-label-note">(' . lang('ERP') . ')</span></div>
                        <div class="form-text mb-3">' . lang('Where the ERP\'s invoices and delivery notes go to become e-Invoice, e-Archive or e-Delivery note documents. One provider at a time; the choice can be changed later and every document remembers which provider carried it.') . '</div>
                        ' . $output_erp_edoc_options . '
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm pg-edoc-test" data-pg-action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/get_erp_edoc_test.php"><i class="bi bi-plug" aria-hidden="true"></i>' . lang('Test the connection') . '</button>
                        <div class="form-text">' . lang('Tries the provider picked above with the connection kept on its page of Connections.') . '</div>
                        <div class="pg-edoc-test-result mt-3" role="status" aria-live="polite"></div>
                    </div>' . pg_accounting_settings_switch('erp_edoc_autosend', $erp_edoc_autosend_checked !== '', lang('Hand the document to GİB in the same step'), lang('A document created through a provider\'s API is only a draft there; it becomes a legal document when it is handed to the tax authority. Left on, one click does both. Turned off, the invoice screen gets a second button for the hand-over, so the draft can be looked over at the provider first.')) : '') . pg_accounting_settings_switch('enable_parasut', $enable_parasut_checked !== '', lang('Enable Parasut Configuration')) . '
                    <div class="col-12">' . $parasut_card_connection . '
                    </div>
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Invoice Settings') . '</h3></div>
                    <div class="pg-f-md">
                        <label class="form-label" for="parasut_tc_in_field">' . lang('Select field to used as Republic of Turkey identification number') . '</label>
                        <select class="form-control" id="parasut_tc_in_field" name="parasut_tc_in_field">' . $parasut_tc_in_field_options . '</select>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="parasut_default_product_id">' . lang('Default Product/Service ID') . ' <span class="pg-field-label-note">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Ürün/Hizmet'))) . ')</span></label>
                        <input type="text" class="form-control" id="parasut_default_product_id" name="parasut_default_product_id" value="' . h($parasut_default_product_id) . '" autocomplete="off" aria-describedby="parasut_default_product_id_help" />
                        <div class="form-text" id="parasut_default_product_id_help">' . lang('Used for the invoice lines that have no Paraşüt product card of their own: shipping, charges, free-text lines and rounding. Each Pinegrap product gets its own card. Without this ID an invoice with such a line is refused. Create a generic product/service in Paraşüt and enter its ID here.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="parasut_default_warehouse_id">' . lang('Default Warehouse ID') . ' <span class="pg-field-label-note">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Depo'))) . ')</span></label>
                        <input type="text" class="form-control" id="parasut_default_warehouse_id" name="parasut_default_warehouse_id" value="' . h($parasut_default_warehouse_id) . '" autocomplete="off" aria-describedby="parasut_default_warehouse_id_help" />
                        <div class="form-text" id="parasut_default_warehouse_id_help">' . lang('Fetched automatically from Parasut on first use. Override here if you have multiple warehouses.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

if (pg_edoc_settings_shown()) {
    $accounting_parts['invoice'] = array('html' => $accounting_invoice_card) + pg_accounting_fold_edoc($accounting_edoc_label, $accounting_edoc_connected);
} else {
    $accounting_hidden .= $accounting_invoice_card;
}

// ── Accounting Rules ──
$accounting_rules = erp_rules();
$accounting_parts['erp-rules'] = array('html' => '
    <div id="pgset-erp-rules" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Accounting rules') . '
            </div>
            <div class="card-body">
                        <p class="fg-secondary">' . lang('Choices your accountant settles for the store; until then the ERP works as it always has.') . '</p>
                        ' . erp_settings_rules_fields() . '
            </div>
        </div>
    </div>') + pg_accounting_fold_rules(erp_settings_rules_editable(), $accounting_rules['vat_net_withholding'], $accounting_rules['vat_exemption_code']);

// ── Late Payment Fee ──
// Drawn once the upgrade added its columns; the save writes it only then.
if (erp_late_fee_ready()) {
    $accounting_late_fee = erp_late_fee_settings();
    $accounting_late_fee_mark = erp_decimal_comma() ? ',' : '.';
    $accounting_parts['late-fee'] = array('html' => '
    <div id="pgset-late-fee" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Late payment fee') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_late_fee_fields() . '
            </div>
        </div>
    </div>') + pg_accounting_fold_late_fee($accounting_late_fee['rate'], rtrim(rtrim(number_format((float) $accounting_late_fee['rate'], 3, $accounting_late_fee_mark, ''), '0'), $accounting_late_fee_mark));
}

// ── e-Fatura Threshold ──
// A Turkish rule: outside Turkey the card is drawn hidden, like the E-Invoice
// card, so a save finds the stored amounts and keeps them.
if (erp_efatura_threshold_ready()) {
    $accounting_threshold_card = '
    <div id="pgset-efatura-threshold" class="pg-set-card' . (erp_settings_efatura_threshold_shown() ? '' : ' d-none') . '">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('e-Fatura threshold') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_efatura_threshold_fields() . '
            </div>
        </div>
    </div>';

    if (erp_settings_efatura_threshold_shown()) {
        $accounting_limits = erp_efatura_threshold_limits();
        $accounting_parts['efatura-threshold'] = array('html' => $accounting_threshold_card) + pg_accounting_fold_threshold(
            $accounting_limits['web'],
            $accounting_limits['general'],
            number_format(floor($accounting_limits['web'] / 100), 0, '', erp_decimal_comma() ? '.' : ',') . ' ' . html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            number_format(floor($accounting_limits['general'] / 100), 0, '', erp_decimal_comma() ? '.' : ',') . ' ' . html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    } else {
        $accounting_hidden .= $accounting_threshold_card;
    }
}

// ── Stock Planning ──
// Drawn once the upgrade added the plan; the save writes it only then.
$erp_stock_plan_fields = erp_settings_stock_plan_fields();

if ($erp_stock_plan_fields !== '') {
    $accounting_stock_plan = erp_stock_plan_settings();
    $accounting_parts['stock-plan'] = array('html' => '
    <div id="pgset-stock-plan" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Stock planning') . '
            </div>
            <div class="card-body">
                        ' . $erp_stock_plan_fields . '
            </div>
        </div>
    </div>') + pg_accounting_fold_stock_plan($accounting_stock_plan['safety'], $accounting_stock_plan['cover'], $accounting_stock_plan['dead']);
}

// ── Document Templates ──
// The editor, with its preview and reset, stays on the ERP Settings screen.
$accounting_templates_saved = 0;
$accounting_templates = erp_settings_documents();

foreach ($accounting_templates as $accounting_template) {
    if (str_replace("\r\n", "\n", (string) $accounting_template['current']) !== str_replace("\r\n", "\n", (string) $accounting_template['default'])) {
        $accounting_templates_saved++;
    }
}

$accounting_parts['erp-templates'] = array('html' => '
    <div id="pgset-erp-templates" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Document templates') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_templates_status(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_settings.php') . '
            </div>
        </div>
    </div>') + pg_accounting_fold_templates($accounting_templates_saved, count($accounting_templates));

// ── The rows ──
$accounting_rows = array();

foreach (pg_accounting_fold_order() as $accounting_key) {
    if (isset($accounting_parts[$accounting_key])) {
        $accounting_rows[] = array('key' => $accounting_key) + pg_accounting_fold_face($accounting_key) + $accounting_parts[$accounting_key];
    }
}

$pg_settings_cards[] = $accounting_head . pg_settings_fold($accounting_rows) . $accounting_hidden;
