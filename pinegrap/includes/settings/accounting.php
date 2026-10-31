<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Accounting cards: the ERP and the e-document provider.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and accounting.save.php writes what they edit. The variables they
 * read are prepared by includes/settings/prep.php.
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

// ── ERP ──
$pg_settings_cards[] = '
    <div id="pgset-erp" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('ERP') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_enabled_checked . ' class="" type="checkbox" id="erp_enabled" name="erp_enabled" /></div>
                            <label class="" for="erp_enabled">' . lang('Enable the ERP module') . '</label>
                        </div>
                        <div class="form-text">' . lang('Adds accounts, cash and bank, invoices and delivery notes to the panel. Switching it off hides the screens; nothing that was entered is deleted.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_series">' . lang('Invoice series') . '</label>
                        <input type="text" class="form-control" id="erp_default_series" name="erp_default_series" value="' . h($erp_default_series) . '" maxlength="10" autocomplete="off" />
                        <div class="form-text">' . lang('The letters every invoice number starts with, for example PGF (three letters in Turkey). Delivery notes add S to them, returns I, purchase invoices A.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_number_style">' . lang('Document numbers') . '</label>
                        <select class="form-control" id="erp_number_style" name="erp_number_style">' . $erp_number_options . '</select>
                        <div class="form-text">' . lang('Applies to numbers taken from now on; documents already issued keep theirs. Changing it never repeats a number.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_tax_name">' . lang('Tax name on documents') . '</label>
                        <input type="text" class="form-control" id="erp_tax_name" name="erp_tax_name" value="' . h($erp_tax_name) . '" maxlength="30" placeholder="' . h(lang('VAT')) . '" autocomplete="off" />
                        <div class="form-text">' . lang('Leave empty for VAT. Write the name your invoices use instead, for example Sales tax or GST; it is printed as written.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_tax2_name">' . lang('Second tax on invoice lines') . '</label>
                        <input type="text" class="form-control" id="erp_tax2_name" name="erp_tax2_name" value="' . h($erp_tax2_name) . '" maxlength="30" autocomplete="off" />
                        <div class="form-text">' . lang('For places that charge two taxes on the same amount, such as GST and PST in Canada: name the second one and every invoice line gets a rate for it. Leave empty for one tax per line.') . '</div>
                    </div>
                    <div class="pg-f-md' . ((pg_erp_store_country() === 'TR') ? '' : ' d-none') . '">
                        <label class="form-label" for="erp_web_address">' . lang('Address the sale was made at') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" class="form-control" id="erp_web_address" name="erp_web_address" value="' . h($erp_web_address) . '" maxlength="255" autocomplete="off" />
                        <div class="form-text">' . lang('Printed on invoices for internet sales, which have to name it. For example www.example.com.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_due_days">' . lang('Default payment term (days)') . '</label>
                        <input type="number" class="form-control" id="erp_default_due_days" name="erp_default_due_days" value="' . (int) $erp_default_due_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" />
                        <div class="form-text">' . lang('A new invoice falls due this many days after its date, unless the account carries its own term. 0 means due on the invoice date.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_walkin_account_id">' . lang('Walk-in sales account') . '</label>
                        <select class="form-control" id="erp_walkin_account_id" name="erp_walkin_account_id">' . $erp_walkin_options . '</select>
                        <div class="form-text">' . lang('A local sale made without picking a customer is billed to this account when it is invoiced. Open an account named for the purpose, such as "Retail customer".') . '</div>
                    </div>' . ($erp_auto_invoice_ready ? '
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_auto_invoice_on">' . lang('The invoice of a web order') . '</label>
                        <select class="form-control" id="erp_auto_invoice_on" name="erp_auto_invoice_on">
                            <option value="off"' . (($erp_auto_invoice_on === 'off') ? ' selected' : '') . '>' . lang('Is issued from the order screen') . '</option>
                            <option value="order_paid"' . (($erp_auto_invoice_on === 'order_paid') ? ' selected' : '') . '>' . lang('Is issued on its own once the order is paid') . '</option>
                            <option value="order_shipped"' . (($erp_auto_invoice_on === 'order_shipped') ? ' selected' : '') . '>' . lang('Is issued on its own once the order is shipped') . '</option>
                        </select>
                        <div class="form-text">' . ($erp_auto_invoice_marketplace_ready
                            ? lang('On its own, the order gets the invoice its screen\'s Issue the Invoice step writes, with the same checks: an order the step cannot invoice yet waits there with the reason, and the panel bell names it. Paid is a card, PayPal or iyzico payment at checkout, a bank transfer recorded with Payment Received, or an order put on the customer\'s account. Counter sales are still invoiced at the till and marketplace orders from their screen unless the switch below is on; the e-document is still made official from the order screen.')
                            : lang('On its own, the order gets the invoice its screen\'s Issue the Invoice step writes, with the same checks: an order the step cannot invoice yet waits there with the reason, and the panel bell names it. Paid is a card, PayPal or iyzico payment at checkout, a bank transfer recorded with Payment Received, or an order put on the customer\'s account. Counter sales are still invoiced at the till and marketplace orders from their screen; the e-document is still made official from the order screen.')) . '</div>
                    </div>' : '') . ($erp_auto_invoice_filters_ready ? '
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_auto_invoice_min_total">' . h(lang(array('string' => 'Smallest order invoiced on its own ({var:1})', 'vars' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) . '</label>
                        <input type="number" class="form-control" id="erp_auto_invoice_min_total" name="erp_auto_invoice_min_total" value="' . (int) $erp_auto_invoice_min_total . '" min="0" max="10000000" step="1" inputmode="numeric" autocomplete="off" />
                        <div class="form-text">' . lang('A smaller order waits for Issue the Invoice on its screen. The amount is the whole sale the invoice states, a gift card included. 0 invoices every order.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_auto_invoice_tax_number_only_checked . ' class="" type="checkbox" id="erp_auto_invoice_tax_number_only" name="erp_auto_invoice_tax_number_only" /></div>
                            <label class="" for="erp_auto_invoice_tax_number_only">' . lang('Only for buyers with a tax number') . '</label>
                        </div>
                        <div class="form-text">' . ((pg_erp_store_country() === 'TR')
                            ? lang('Read from the account the invoice goes to, or from the contact while the order has no account. A VKN or a TCKN counts; the final consumer number 11111111111 does not. An order without one waits for Issue the Invoice on its screen.')
                            : lang('Read from the account the invoice goes to, or from the contact while the order has no account. An order without one waits for Issue the Invoice on its screen.')) . '</div>
                    </div>' : '') . ($erp_auto_invoice_marketplace_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_auto_invoice_marketplace_checked . ' class="" type="checkbox" id="erp_auto_invoice_marketplace" name="erp_auto_invoice_marketplace" /></div>
                            <label class="" for="erp_auto_invoice_marketplace">' . lang('Also invoice marketplace orders (Trendyol, n11)') . '</label>
                        </div>
                        <div class="form-text">' . lang('A marketplace order is paid when it is imported and shipped when its tracking number arrives; the same moment and filters apply. Leave it off if you invoice them elsewhere. The invoice link is still typed on the Imported orders card.') . '</div>
                    </div>' : '') . '
                    <div class="pg-f-md">
                        <label class="form-label" for="local_sale_prices">' . lang('Prices on the till') . '</label>
                        <select class="form-control" id="local_sale_prices" name="local_sale_prices">
                            <option value="gross"' . (($local_sale_prices === 'gross') ? ' selected' : '') . '>' . lang('With tax included (VAT countries)') . '</option>
                            <option value="net"' . (($local_sale_prices === 'net') ? ' selected' : '') . '>' . lang('Without tax, added at the total (sales-tax countries)') . '</option>
                        </select>
                        <div class="form-text">' . lang('Only what the local sale screen shows changes; product prices are kept without tax and the amount charged is the same.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_stock_documents_checked . ' class="" type="checkbox" id="erp_stock_documents" name="erp_stock_documents" /></div>
                            <label class="" for="erp_stock_documents">' . lang('Invoices change stock') . '</label>
                        </div>
                        <div class="form-text">' . lang('On, a purchase invoice adds its products to stock and a sales invoice typed in the ERP takes them out; a return or a cancellation moves them back. An invoice raised from an order leaves stock alone, because the order already took the goods out. Only products that track stock and whole quantities are counted. Off, only the costs are kept.') . '</div>
                    </div>' . ($erp_invoice_mail_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_invoice_mail_auto_checked . ' class="" type="checkbox" id="erp_invoice_mail_auto" name="erp_invoice_mail_auto" /></div>
                            <label class="" for="erp_invoice_mail_auto">' . lang('E-mail each issued invoice to the customer') . '</label>
                        </div>
                        <div class="form-text">' . lang('The invoice goes with its PDF to the account\'s invoice e-mail address (or its main address) when it is issued. Where e-documents are in use it goes once GİB has accepted it, with the official copy. Off for a single account on its card; any invoice can also be sent from its own screen.') . '</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="erp_invoice_mail_message">' . lang('Invoice e-mail message') . '</label>
                        <textarea class="form-control" id="erp_invoice_mail_message" name="erp_invoice_mail_message" rows="2" maxlength="2000" placeholder="' . h(lang('Thank you for your purchase. Your invoice is attached to this e-mail.')) . '">' . h($erp_invoice_mail_message) . '</textarea>
                        <div class="form-text">' . lang('The text above the invoice\'s figures in the e-mail. Empty uses the text shown.') . '</div>
                    </div>' : '') . ($erp_credit_ready ? '
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_credit_limit_mode">' . lang('A sale past a customer\'s credit limit') . '</label>
                        <select class="form-control" id="erp_credit_limit_mode" name="erp_credit_limit_mode">
                            <option value="warn"' . (($erp_credit_limit_mode === 'warn') ? ' selected' : '') . '>' . lang('Is issued, with a warning') . '</option>
                            <option value="block"' . (($erp_credit_limit_mode === 'block') ? ' selected' : '') . '>' . lang('Is refused until a payment comes in') . '</option>
                        </select>
                        <div class="form-text">' . lang('The limit is set on each account card. Weighed on invoices typed in the ERP; an order\'s invoice is never held back.') . '</div>
                    </div>' : '') . ($erp_storefront_prices_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_storefront_account_prices_checked . ' class="" type="checkbox" id="erp_storefront_account_prices" name="erp_storefront_account_prices" /></div>
                            <label class="" for="erp_storefront_account_prices">' . lang('Account prices in the store') . '</label>
                        </div>
                        <div class="form-text">' . lang('A customer whose contact is linked to an account sees and pays that account\'s prices in the store while signed in: its own price for a product, or its discount on the list price. A campaign that goes lower still wins; the two never add up. Off, everyone sees the list price.') . '</div>
                    </div>' : '') . ($erp_open_account_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_open_account_payment_checked . ' class="" type="checkbox" id="erp_open_account_payment" name="erp_open_account_payment" /></div>
                            <label class="" for="erp_open_account_payment">' . lang('Put it on my account at checkout') . '</label>
                        </div>
                        <div class="form-text">' . lang('A signed-in customer whose account has a credit limit can put an order on the account instead of paying: it is billed to the account with the account\'s payment terms. Refused when the order would take the account past its limit, counting the orders on account not invoiced yet. The order counts as paid for an invoice issued on its own and is never cancelled for an unpaid transfer.') . '</div>
                    </div>' : '') . '
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_shipping_tax">' . lang('Tax on shipping and surcharges') . '</label>
                        <select class="form-control" id="erp_shipping_tax" name="erp_shipping_tax">
                            <option value=""' . (($erp_shipping_tax === '') ? ' selected' : '') . '>' . lang('By the store\'s country') . '</option>
                            <option value="included"' . (($erp_shipping_tax === 'included') ? ' selected' : '') . '>' . lang('Included in the amount charged, at the rate of the goods') . '</option>
                            <option value="none"' . (($erp_shipping_tax === 'none') ? ' selected' : '') . '>' . lang('Not taxed') . '</option>
                        </select>
                        <div class="form-text">' . lang('How an order\'s shipping, surcharge and installment charge are written on its invoice. The customer paid them with no tax added, so a taxed charge carries its tax inside the amount (50.00 at 20% is 41.67 + 8.33) and the invoice total stays the same; goods at several rates share the charge between them. By the store\'s country: untaxed in the United States, included everywhere else.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_fx_enabled_checked . ' class="collapse-switcher" type="checkbox" id="erp_fx_enabled" name="erp_fx_enabled" data-bs-target="#erp_fx_row" /></div>
                            <label class="" for="erp_fx_enabled">' . lang('Enable foreign-currency invoices and accounts') . '</label>
                        </div>
                        <div class="form-text">' . lang('Off, everything in the ERP is in the store\'s base currency. On, an invoice, an account or a till may be kept in one of the currencies below; amounts stay in that currency and the day\'s exchange rate converts them for the ledger.') . '</div>
                        <div class="collapse popover fade bs-popover-bottom p-0 " id="erp_fx_row">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row gy-5">
                                    <div class="col-12" role="group" aria-labelledby="erp_fx_currencies_title">
                                        <div class="form-label" id="erp_fx_currencies_title">' . lang('Currencies allowed on documents') . '</div>
                                        ' . $output_erp_fx_currencies . '
                                        <div class="form-text">' . lang('The base currency is always allowed. Daily rates for these come from the Exchange rates job; run Update Exchange Rates on the currencies screen to fetch today\'s.') . '</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-field">
                                            <div class="switch"><input value="1"' . $erp_fx_auto_diff_checked . ' class="" type="checkbox" id="erp_fx_auto_diff" name="erp_fx_auto_diff" /></div>
                                            <label class="" for="erp_fx_auto_diff">' . lang('Post the exchange difference automatically when a foreign-currency invoice is paid off') . '</label>
                                        </div>
                                        <div class="form-text">' . lang('The invoice went on the account at the rate of its issue date and the receipts came off at the rate of their own day. When the invoice is paid, one movement in the base currency settles the gap so the account closes to zero.') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12" role="group" aria-labelledby="erp_overdue_notify_title">
                        <div class="form-label" id="erp_overdue_notify_title">' . lang('Overdue receivable reminders') . '</div>
                        <div class="form-text mb-3">' . lang('Sales invoices that pass this many days overdue are announced in the panel bell, by e-mail and on a subscribed device, and once more if still open a month later. An account can carry a threshold of its own. 0 turns the reminders off.') . '</div>
                        <div class="row gy-5">
                            <div class="col-12 md:col-4">
                                <label class="form-label" for="erp_overdue_notify_days">' . lang('Reminder threshold (days)') . '</label>
                                <input type="number" class="form-control" id="erp_overdue_notify_days" name="erp_overdue_notify_days" value="' . (int) $erp_overdue_notify_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" />
                            </div>
                            <div class="col-6 md:col-4">
                                <label class="form-label" for="erp_overdue_notify_frequency">' . lang('Frequency') . '</label>
                                <select class="form-control" id="erp_overdue_notify_frequency" name="erp_overdue_notify_frequency">
                                    <option value="daily"' . (($erp_overdue_notify_frequency === 'daily') ? ' selected="selected"' : '') . '>' . lang('Daily') . '</option>
                                    <option value="weekly"' . (($erp_overdue_notify_frequency === 'weekly') ? ' selected="selected"' : '') . '>' . lang('Weekly') . '</option>
                                </select>
                            </div>
                            <div class="col-6 md:col-4">
                                <label class="form-label" for="erp_overdue_notify_hour">' . lang('Hour') . '</label>
                                <select class="form-control" id="erp_overdue_notify_hour" name="erp_overdue_notify_hour">' . $output_erp_overdue_hours . '</select>
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_overdue_notify_panel_checked . ' class="" type="checkbox" id="erp_overdue_notify_panel" name="erp_overdue_notify_panel" /></div>
                                    <label class="" for="erp_overdue_notify_panel">' . lang('Panel notification') . '</label>
                                </div>
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_overdue_notify_email_checked . ' class="" type="checkbox" id="erp_overdue_notify_email" name="erp_overdue_notify_email" /></div>
                                    <label class="" for="erp_overdue_notify_email">' . lang('E-mail') . '</label>
                                </div>
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_overdue_notify_push_checked . ' class="" type="checkbox" id="erp_overdue_notify_push" name="erp_overdue_notify_push" /></div>
                                    <label class="" for="erp_overdue_notify_push">' . lang('Device notification') . '</label>
                                </div>' . $output_erp_overdue_push_hint . '
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_overdue_notify_customer_checked . ' class="" type="checkbox" id="erp_overdue_notify_customer" name="erp_overdue_notify_customer" /></div>
                                    <label class="" for="erp_overdue_notify_customer">' . lang('Reminder e-mail to the customer') . '</label>
                                </div>
                                <div class="form-text">' . lang('When a customer\'s invoices pass the threshold, a short reminder listing them goes to the e-mail address on their account, from the store address, with a second one a month later. Off for a single account on its card.') . '</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="erp_overdue_notify_recipients">' . lang('Recipients') . '</label>
                                <input type="text" inputmode="email" spellcheck="false" autocapitalize="off" class="form-control" id="erp_overdue_notify_recipients" name="erp_overdue_notify_recipients" value="' . h($erp_overdue_notify_recipients) . '" maxlength="500" autocomplete="off" placeholder="' . h($erp_overdue_notify_default_recipient) . '" />
                                <div class="form-text">' . lang('Comma-separated. Empty uses the store e-mail address.') . '</div>
                            </div>
                        </div>
                    </div>' . ($erp_alerts_ready ? '
                    <div class="col-12">
                        <div class="form-label">' . lang('Notices as they happen') . '</div>
                        <div class="form-text mb-3">' . lang('On the panel bell and on the devices that subscribed to notifications. The person who recorded it is not told.') . '</div>
                        <div class="row gy-3 align-items-end">
                            <div class="col-12 md:col-7">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_notify_collections_checked . ' class="" type="checkbox" id="erp_notify_collections" name="erp_notify_collections" /></div>
                                    <label class="" for="erp_notify_collections">' . lang('A collection is recorded') . '</label>
                                </div>
                            </div>
                            <div class="col-12 md:col-5">
                                <label class="form-label" for="erp_notify_collection_min">' . h(lang(array('string' => 'From this amount up ({var:1})', 'vars' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) . '</label>
                                <input type="number" class="form-control" id="erp_notify_collection_min" name="erp_notify_collection_min" value="' . (int) $erp_notify_collection_min . '" min="0" max="100000000" step="1" inputmode="numeric" autocomplete="off" />
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_notify_low_stock_checked . ' class="" type="checkbox" id="erp_notify_low_stock" name="erp_notify_low_stock" /></div>
                                    <label class="" for="erp_notify_low_stock">' . lang('A product drops to its minimum stock') . '</label>
                                </div>
                                <div class="form-text">' . lang('Checked every hour; each product is announced once each time it drops.') . '</div>' . $output_erp_overdue_push_hint . '
                            </div>' . ($erp_quote_alert_ready ? '
                            <div class="col-12 md:col-7">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_notify_quote_expiry_checked . ' class="" type="checkbox" id="erp_notify_quote_expiry" name="erp_notify_quote_expiry" /></div>
                                    <label class="" for="erp_notify_quote_expiry">' . lang('Announce quotes about to expire') . '</label>
                                </div>
                                <div class="form-text">' . lang('Checked every hour; each open quote is announced once, and again after it is opened again or its date changes.') . '</div>
                            </div>
                            <div class="col-12 md:col-5">
                                <label class="form-label" for="erp_notify_quote_expiry_days">' . lang('Days before it expires') . '</label>
                                <input type="number" class="form-control pg-f-xs" id="erp_notify_quote_expiry_days" name="erp_notify_quote_expiry_days" value="' . (int) $erp_notify_quote_expiry_days . '" min="1" max="30" step="1" inputmode="numeric" autocomplete="off" />
                            </div>' . ($erp_quote_followup_ready ? '
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . $erp_quote_expiry_followup_checked . ' class="" type="checkbox" id="erp_quote_expiry_followup" name="erp_quote_expiry_followup" /></div>
                                    <label class="" for="erp_quote_expiry_followup">' . lang('Open a Workspace follow-up for each quote announced') . '</label>
                                </div>
                                <div class="form-text">' . lang('A task for the person who made the quote, due on its last day. Needs the notice above and the Workspace.') . '</div>
                            </div>' : '') : '') . '
                        </div>
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>';

// ── E-Invoice ──
$pg_settings_cards[] = '
    <div id="pgset-invoice" class="pg-set-card' . (pg_edoc_settings_shown() ? '' : ' d-none') . '">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('E-Invoice') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . (($output_erp_edoc_options !== '') ? '
                    <div class="col-12" role="group" aria-labelledby="erp_edoc_provider_title">
                        <div class="form-label" id="erp_edoc_provider_title">' . lang('e-Document Provider') . ' <span class="fg-secondary small">(' . lang('ERP') . ')</span></div>
                        <div class="form-text mb-3">' . lang('Where the ERP\'s invoices and delivery notes go to become e-Invoice, e-Archive or e-Delivery note documents. One provider at a time; the choice can be changed later and every document remembers which provider carried it.') . '</div>
                        ' . $output_erp_edoc_options . '
                        <button type="button" class="btn btn-sm btn-outline theme-secondary pg-edoc-test" data-pg-action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/get_erp_edoc_test.php"><i class="bi bi-plug me-3"></i>' . lang('Test the connection') . '</button>
                        <div class="form-text">' . lang('Tries the provider picked above with what is typed here (or, for a box left empty, what is stored). Save the settings to keep the credentials.') . '</div>
                        <div class="pg-edoc-test-result mt-3" role="status" aria-live="polite"></div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $erp_edoc_autosend_checked . ' class="" type="checkbox" id="erp_edoc_autosend" name="erp_edoc_autosend" /></div>
                            <label class="" for="erp_edoc_autosend">' . lang('Hand the document to GİB in the same step') . '</label>
                        </div>
                        <div class="form-text">' . lang('A document created through a provider\'s API is only a draft there; it becomes a legal document when it is handed to the tax authority. Left on, one click does both. Turned off, the invoice screen gets a second button for the hand-over, so the draft can be looked over at the provider first.') . '</div>
                    </div>
                    <div class="col-12"><hr class="my-1"></div>' : '') . '
                    <div class="col-12">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $enable_parasut_checked . ' class="collapse-switcher" type="checkbox" id="enable_parasut" name="enable_parasut" data-bs-target="#ecommerce_parasut_configration_row"/></div>
                                                                <label class="" for="enable_parasut">' . lang('Enable Parasut Configuration') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_parasut_configration_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-5">
                                                                    <div class="col-12 "><h6 class="fg-secondary">' . lang('API Connection') . '</h6></div>
                                                                    <div class="col-12">
                                                                        <div class="alert theme-' . h($parasut_connection_tone) . ' py-3 small mb-0 pg-parasut-status d-flex align-items-center gap-3"><i class="bi ' . (($parasut_connection_tone === 'success') ? 'bi-check-circle' : 'bi-plug') . '" aria-hidden="true"></i><span>' . h($parasut_connection_text) . '</span></div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <details class="pg-parasut-steps"' . ($parasut_connection_connected ? '' : ' open') . '>
                                                                            <summary class="form-label">' . h($parasut_connection_connected ? lang('Authorize again or connect another account') : lang('Connect in three steps')) . '</summary>
                                                                            <div class="row gy-5 mt-0">
                                                                                <div class="col-12"><div class="form-text">' . lang('1. Enter the client id and secret of the API application; Paraşüt support issues them for your account.') . '</div></div>
                                                                                <div class="pg-f-md">
                                                                                    <label class="form-label" for="parasut_client_id">' . lang('Client ID') . '</label>
                                                                                    <input type="text" class="form-control" id="parasut_client_id" name="parasut_client_id" value="' . h($parasut_client_id) . '" autocomplete="off" />
                                                                                </div>
                                                                                <div class="pg-f-md">
                                                                                    <label class="form-label" for="parasut_client_secret">' . lang('Client Secret') . '</label>
                                                                                    <input type="password" class="form-control" id="parasut_client_secret" name="parasut_client_secret" value="" autocomplete="new-password" placeholder="' . h($parasut_secret_placeholder) . '" />
                                                                                    <div class="form-text">' . h($parasut_secret_help) . '</div>
                                                                                </div>
                                                                                <div class="col-12">
                                                                                    <div class="form-text mb-3">' . lang('2. Open the authorization page, sign in to Paraşüt and allow Pinegrap; Paraşüt then shows a code.') . '</div>
                                                                                    <a class="btn btn-sm btn-outline theme-primary pg-parasut-authorize' . (($parasut_authorize_url === '') ? ' disabled' : '') . '" href="' . h($parasut_authorize_url) . '" target="_blank" rel="noopener" data-pg-base="' . h(PARASUT_API_BASE . '/oauth/authorize') . '"><i class="bi bi-box-arrow-up-right me-3"></i>' . lang('Open the Paraşüt authorization page') . '</a>
                                                                                </div>
                                                                                <div class="col-12">
                                                                                    <label class="form-label" for="parasut_auth_code">' . lang('3. Paste the code and press Connect') . '</label>
                                                                                    <div class="input-group pg-f-lg">
                                                                                        <input type="text" class="form-control" id="parasut_auth_code" name="parasut_auth_code" value="" autocomplete="off" spellcheck="false" placeholder="' . h(lang('Authorization code')) . '" />
                                                                                        <button type="button" class="btn btn-solid theme-primary pg-edoc-test" data-pg-fields="parasut_" data-pg-action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/parasut_connect.php"><i class="bi bi-plug me-3"></i>' . lang('Connect') . '</button>
                                                                                    </div>
                                                                                    <div class="form-text">' . lang('A code works once and for a few minutes. Saving the page with a code in the box connects too. No Paraşüt password is stored this way.') . '</div>
                                                                                    <div class="pg-edoc-test-result mt-3" role="status" aria-live="polite"></div>
                                                                                </div>
                                                                            </div>
                                                                        </details>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <details>
                                                                            <summary class="form-text">' . lang('Advanced: company number, user name and password') . '</summary>
                                                                            <div class="row gy-5 mt-0">
                                                                                <div class="pg-f-md">
                                                                                    <label class="form-label" for="parasut_company_id">' . lang('Company ID') . ' <span class="fg-secondary small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Firma No'))) . ')</span></label>
                                                                                    <input type="text" class="form-control" id="parasut_company_id" name="parasut_company_id" value="' . h($parasut_company_id) . '" autocomplete="off" />
                                                                                    <div class="form-text">' . lang('Filled in by Connect. Write it only when the Paraşüt user works in more than one company; an empty box keeps the saved number.') . '</div>
                                                                                </div>
                                                                                <div class="col-12"><div class="form-text">' . lang('The older way: the Paraşüt account\'s e-mail and password are stored encrypted and used whenever the token has to be renewed. Not needed once Pinegrap is authorized with a code.') . '</div></div>
                                                                                <div class="pg-f-md">
                                                                                    <label class="form-label" for="parasut_username">' . lang('Username') . ' (' . lang('Email') . ')</label>
                                                                                    <input type="text" class="form-control" id="parasut_username" name="parasut_username" value="' . h($parasut_username) . '" autocomplete="off" />
                                                                                </div>
                                                                                <div class="pg-f-md">
                                                                                    <label class="form-label" for="parasut_password">' . lang('Password') . '</label>
                                                                                    <input type="password" class="form-control" id="parasut_password" name="parasut_password" value="" autocomplete="new-password" placeholder="' . h($parasut_password_placeholder) . '" />
                                                                                    <div class="form-text">' . h($parasut_password_help) . '</div>
                                                                                </div>
                                                                            </div>
                                                                        </details>
                                                                    </div>
                                                                    <div class="col-12 "><h6 class="fg-secondary">' . lang('Invoice Settings') . '</h6></div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_tc_in_field">' . lang('Select field to used as Republic of Turkey identification number') . '</label>
                                                                        <select class="form-control" id="parasut_tc_in_field" name="parasut_tc_in_field">' . $parasut_tc_in_field_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_default_product_id">' . lang('Default Product/Service ID') . ' <span class="fg-secondary small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Ürün/Hizmet'))) . ')</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_product_id" name="parasut_default_product_id" value="' . h($parasut_default_product_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Used for the invoice lines that have no Paraşüt product card of their own: shipping, charges, free-text lines and rounding. Each Pinegrap product gets its own card. Without this ID an invoice with such a line is refused. Create a generic product/service in Paraşüt and enter its ID here.') . '</div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_default_warehouse_id">' . lang('Default Warehouse ID') . ' <span class="fg-secondary small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Depo'))) . ')</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_warehouse_id" name="parasut_default_warehouse_id" value="' . h($parasut_default_warehouse_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Fetched automatically from Parasut on first use. Override here if you have multiple warehouses.') . '</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Accounting Rules ──
$pg_settings_cards[] = '
    <div id="pgset-erp-rules" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Accounting rules') . '
            </div>
            <div class="card-body">
                        <p class="fg-secondary">' . lang('Choices your accountant settles for the store; until then the ERP works as it always has.') . '</p>
                        ' . erp_settings_rules_fields() . '
            </div>
        </div>
    </div>';

// ── Late Payment Fee ──
// Drawn once the upgrade added its columns; the save writes it only then.
if (erp_late_fee_ready()) {
    $pg_settings_cards[] = '
    <div id="pgset-late-fee" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Late payment fee') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_late_fee_fields() . '
            </div>
        </div>
    </div>';
}

// ── e-Fatura Threshold ──
// A Turkish rule: outside Turkey the card is drawn hidden, like the E-Invoice
// card, so a save finds the stored amounts and keeps them.
if (erp_efatura_threshold_ready()) {
    $pg_settings_cards[] = '
    <div id="pgset-efatura-threshold" class="pg-set-card' . (erp_settings_efatura_threshold_shown() ? '' : ' d-none') . '">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('e-Fatura threshold') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_efatura_threshold_fields() . '
            </div>
        </div>
    </div>';
}

// ── Document Templates ──
// The editor, with its preview and reset, stays on the ERP Settings screen.
$pg_settings_cards[] = '
    <div id="pgset-erp-templates" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Document Templates') . '
            </div>
            <div class="card-body">
                        ' . erp_settings_templates_status(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_settings.php') . '
            </div>
        </div>
    </div>';
