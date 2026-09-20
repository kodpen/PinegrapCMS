<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the account form, shared by the create and the edit screen.
 *
 * Two screens, one set of fields. They are described once here because an
 * account that can be created carrying a field the edit screen does not show is
 * an account nobody can afterwards correct.
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
 * The fields an account is described by, as the cards they are shown in.
 *
 * @param liveform $liveform
 * @param bool     $with_opening  Show the opening balance card. Creating only:
 *                                once an account has movements behind it, the
 *                                opening figure is one of them and is corrected
 *                                there rather than retyped here.
 * @param bool     $currency_locked  The account already has movements, so its
 *                                currency is shown but cannot be changed: the
 *                                own-currency balance is summed over movements
 *                                in that currency.
 * @param array|null $contact  The linked contact as erp_contact_summary()
 *                                reads it, or null for none
 * @return string  HTML
 */
function erp_account_form_cards($liveform, $with_opening = false, $currency_locked = false, $contact = null)
{
    $kind_options = array();
    $kind_options[lang('Customer')] = 'customer';
    $kind_options[lang('Supplier')] = 'supplier';
    $kind_options[lang('Customer and supplier')] = 'both';

    $status_options = array();
    $status_options[lang('Active')] = 'active';
    $status_options[lang('Passive')] = 'passive';

    $person_options = array();
    $person_options[lang('Person')] = '1';
    $person_options[lang('Company')] = '0';

    $output = '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Account') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-6 my-2">
                    <label for="title" class="form-label">' . lang('Name') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'title', 'name' => 'title',
                        'class' => 'form-control', 'maxlength' => '255',
                        'autocomplete' => 'off', 'required' => 'required')) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="kind" class="form-label">' . lang('Type') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'kind', 'name' => 'kind',
                        'class' => 'form-select', 'options' => $kind_options)) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="status" class="form-label">' . lang('Status') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'status', 'name' => 'status',
                        'class' => 'form-select', 'options' => $status_options)) . '
                </div>
            </div>
            ' . erp_currency_form_row($liveform, $currency_locked, lang('The currency the account is kept in. Its balance is reported in the base currency as well.')) . '
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="payment_days" class="form-label">' . lang('Payment term (days)') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'payment_days', 'name' => 'payment_days',
                        'class' => 'form-control', 'maxlength' => '4', 'inputmode' => 'numeric',
                        'autocomplete' => 'off')) . '
                    <div class="form-text">' . h(lang(array('string' => 'Days from the invoice date to its due date. 0 uses the store default, currently {var:1} days.', 'vars' => (defined('ERP_DEFAULT_DUE_DAYS') ? (int) ERP_DEFAULT_DUE_DAYS : 0)))) . '</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="overdue_notify_days" class="form-label">' . lang('Reminder threshold (days)') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'overdue_notify_days', 'name' => 'overdue_notify_days',
                        'class' => 'form-control', 'maxlength' => '4', 'inputmode' => 'numeric',
                        'autocomplete' => 'off')) . '
                    <div class="form-text">' . h(lang(array('string' => 'Days overdue before this account\'s invoices are announced. 0 uses the store threshold, currently {var:1} days.', 'vars' => (defined('ERP_OVERDUE_NOTIFY_DAYS') ? (int) ERP_OVERDUE_NOTIFY_DAYS : 0)))) . '</div>
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <div class="form-label">' . lang('Reminder e-mail to the customer') . '</div>
                    <div class="form-check form-switch">
                        ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'overdue_notify_customer', 'name' => 'overdue_notify_customer', 'value' => '1', 'class' => 'form-check-input')) . '
                        <label class="form-check-label" for="overdue_notify_customer">' . lang('Write to this account when its invoices pass the threshold') . '</label>
                    </div>
                    <div class="form-text">' . h((defined('ERP_OVERDUE_NOTIFY_CUSTOMER') && ERP_OVERDUE_NOTIFY_CUSTOMER)
                        ? lang('Goes to the e-mail address above. Switch it off for a customer who asked not to be written to.')
                        : lang('Reminder e-mails to customers are switched off on the ERP settings card; this choice takes effect once they are on.')) . '</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Tax Details') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="is_person" class="form-label">' . lang('Taxpayer') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'is_person', 'name' => 'is_person',
                        'class' => 'form-select', 'options' => $person_options)) . '
                    <div class="form-text">' . lang('Decides whether the number is read as a TCKN or a VKN.') . '</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="tax_number" class="form-label">' . lang('VKN / TCKN') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'tax_number', 'name' => 'tax_number',
                        'class' => 'form-control', 'maxlength' => '11',
                        'inputmode' => 'numeric', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'tax_office', 'name' => 'tax_office',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Contact Information') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-6 my-2">
                    <label for="email" class="form-label">' . lang('E-mail Address') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'email', 'name' => 'email',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="phone" class="form-label">' . lang('Phone Number') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'phone', 'name' => 'phone',
                        'class' => 'form-control', 'maxlength' => '50', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 my-2">
                    <label for="address" class="form-label">' . lang('Address') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'address', 'name' => 'address',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-5 my-2">
                    <label for="district" class="form-label">' . lang('District') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'district', 'name' => 'district',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-4 my-2">
                    <label for="city" class="form-label">' . lang('City') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'city', 'name' => 'city',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-3 my-2">
                    <label for="postcode" class="form-label">' . lang('Postal Code') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'postcode', 'name' => 'postcode',
                        'class' => 'form-control', 'maxlength' => '20', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>';

    if ($with_opening) {
        $output .= '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Opening Balance') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-4 col-lg-3 my-2">
                    <label for="opening_amount" class="form-label">' . lang('Amount') . '</label>
                    <div class="input-group">
                        ' . $liveform->output_field(array(
                            'type' => 'text', 'id' => 'opening_amount', 'name' => 'opening_amount',
                            'class' => 'form-control text-end', 'maxlength' => '15',
                            'inputmode' => 'decimal', 'autocomplete' => 'off')) . '
                        <label class="input-group-text" for="opening_amount">' . (erp_fx_enabled() ? lang('in the account currency') : BASE_CURRENCY_SYMBOL) . '</label>
                    </div>
                    <div class="form-text">' . lang('Positive when they owe you, negative when you owe them.') . (erp_fx_enabled() ? ' ' . lang('A foreign-currency opening figure is converted at the recorded rate of its date.') : '') . '</div>
                </div>
                <div class="col-12 col-sm-4 col-lg-3 my-2">
                    <label for="opening_date" class="form-label">' . lang('Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'opening_date', 'name' => 'opening_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    ' . get_date_picker_format() . '
                    <script>$("#opening_date").datepicker(datetimepicker_options);</script>
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <div class="form-text pt-4">' . lang('Recorded as a movement rather than written straight onto the balance, so it can be traced later like any other entry.') . '</div>
                </div>
            </div>
        </div>
    </div>';
    }

    $output .= erp_account_form_contact_card($liveform, $contact);

    $output .= '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Notes') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 my-2">
                    ' . $liveform->output_field(array(
                        'type' => 'textarea', 'id' => 'notes', 'name' => 'notes',
                        'class' => 'form-control', 'rows' => '3')) . '
                </div>
            </div>
        </div>
    </div>';

    return $output;
}

/**
 * The contact behind the account, as one line of the summary column.
 *
 * @param array $contact  erp_contact_summary()
 * @return string  HTML
 */
function erp_account_form_contact_summary($contact)
{
    $lines = array();

    if ($contact['company'] !== '' && $contact['person'] !== '') {
        $lines[] = h($contact['company']);
    }
    if ($contact['email'] !== '') {
        $lines[] = '<a href="mailto:' . h($contact['email']) . '" class="link-body-emphasis">' . h($contact['email']) . '</a>';
    }
    if ($contact['phone'] !== '') {
        $lines[] = h($contact['phone']);
    }
    if (trim($contact['district'] . ' ' . $contact['city']) !== '') {
        $lines[] = h(trim($contact['district'] . ' ' . $contact['city']));
    }

    $links = array();
    $links[] = '<a href="edit_contact.php?id=' . (int) $contact['id'] . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-vcard me-1"></i>' . lang('Contact') . '</a>';

    if (defined('ECOMMERCE') && ECOMMERCE) {
        $links[] = '<a href="view_orders_for_contact.php?id=' . (int) $contact['id'] . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bag me-1"></i>' . h(lang(array('string' => '{var:1} order(s)', 'vars' => (int) $contact['orders']))) . '</a>';
    }

    if ($contact['user_id'] > 0) {
        $links[] = '<a href="edit_user.php?id=' . (int) $contact['user_id'] . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-badge me-1"></i>' . h(lang(array('string' => 'User: {var:1}', 'vars' => $contact['username']))) . '</a>';
    }

    return '
                    <div class="fw-bold">' . h($contact['name']) . '</div>
                    ' . (($lines !== array()) ? '<div class="small text-body-secondary">' . implode('<br>', $lines) . '</div>' : '') . '
                    <div class="d-flex flex-wrap gap-2 mt-2">' . implode('', $links) . '</div>';
}

/**
 * The card that ties the account to a contact.
 *
 * The hidden contact_id is what is saved; the search box fills it, the
 * unlink button empties it. The summary column shows the contact that is
 * linked now, and on a pick the script draws what it knows from the search
 * result - the user behind the contact and the order count appear after the
 * save, when the row is read again.
 *
 * @param liveform   $liveform
 * @param array|null $contact  erp_contact_summary() of the linked contact
 * @return string  HTML
 */
function erp_account_form_contact_card($liveform, $contact)
{
    $contact_id = (int) $liveform->get_field_value('contact_id');
    $is_linked = is_array($contact) && ((int) $contact['id'] === $contact_id) && ($contact_id > 0);

    $output_summary = $is_linked
        ? erp_account_form_contact_summary($contact)
        : '<div class="text-body-secondary">' . lang('No contact is linked to this account.') . '</div>';

    return '
    <div class="card my-4" data-erp-contact-card data-erp-contacts-url="get_erp_contacts.php" data-erp-own-account="' . (int) $liveform->get_field_value('id') . '">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Linked Contact') . '
        </div>
        <div class="card-body">
            ' . $liveform->output_field(array('type' => 'hidden', 'id' => 'contact_id', 'name' => 'contact_id')) . '
            <div class="row">
                <div class="col-12 col-lg-6 my-2 position-relative">
                    <label for="contact_search" class="form-label">' . lang('Find a contact') . '</label>
                    <input type="text" id="contact_search" class="form-control" maxlength="100" autocomplete="off" placeholder="' . h(lang('Name, company or e-mail address')) . '" data-erp-contact-search />
                    <div class="dropdown-menu shadow-sm w-100" data-erp-contact-results></div>
                    <div class="form-text">' . lang('Orders placed by the linked contact are billed to this account, and the contact screen shows the balance. One contact links to one account.') . '</div>
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <div class="form-label d-flex align-items-center">
                        <span>' . lang('Contact') . '</span>
                        <button type="button" class="btn btn-sm btn-link link-danger ms-auto no-submit' . ($is_linked ? '' : ' d-none') . '" data-erp-contact-unlink><i class="bi bi-x-circle me-1"></i>' . lang('Unlink') . '</button>
                    </div>
                    <div data-erp-contact-summary>' . $output_summary . '</div>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var card = document.querySelector("[data-erp-contact-card]");
            if (!card) { return; }
            var url = card.getAttribute("data-erp-contacts-url");
            var hidden = card.querySelector("#contact_id");
            var input = card.querySelector("[data-erp-contact-search]");
            var menu = card.querySelector("[data-erp-contact-results]");
            var summary = card.querySelector("[data-erp-contact-summary]");
            var unlink = card.querySelector("[data-erp-contact-unlink]");
            var texts = ' . json_encode(array(
                'none' => lang('No contact is linked to this account.'),
                'noResults' => lang('No contacts found.'),
                'pending' => lang('Linked when the account is saved.'),
                'unlinkPending' => lang('The link is removed when the account is saved.'),
                'taken' => lang('already linked to another account'),
            ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
            var timer = 0, request = 0;

            function escapeHtml(text) {
                return String(text).replace(/[&<>"]/g, function (c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;"}[c]; });
            }
            function close() { menu.classList.remove("show"); menu.innerHTML = ""; }
            function pick(contact) {
                hidden.value = String(contact.id);
                input.value = "";
                var lines = [];
                if (contact.company && contact.company !== contact.name) { lines.push(escapeHtml(contact.company)); }
                if (contact.email) { lines.push(escapeHtml(contact.email)); }
                if (contact.city) { lines.push(escapeHtml(contact.city)); }
                summary.innerHTML = "<div class=\"fw-bold\">" + escapeHtml(contact.name) + "</div>"
                    + (lines.length ? "<div class=\"small text-body-secondary\">" + lines.join("<br>") + "</div>" : "")
                    + "<div class=\"small text-warning-emphasis mt-2\"><i class=\"bi bi-info-circle me-1\"></i>" + escapeHtml(texts.pending) + "</div>";
                unlink.classList.remove("d-none");
                close();
            }
            function render(results) {
                menu.innerHTML = "";
                if (!results.length) {
                    var empty = document.createElement("span");
                    empty.className = "dropdown-item-text text-body-secondary small";
                    empty.textContent = texts.noResults;
                    menu.appendChild(empty);
                }
                results.forEach(function (contact) {
                    var item = document.createElement("button");
                    item.type = "button";
                    item.className = "dropdown-item text-truncate no-submit";
                    var taken = contact.account_id > 0 && String(contact.account_id) !== card.getAttribute("data-erp-own-account");
                    item.innerHTML = escapeHtml(contact.name)
                        + (contact.company && contact.company !== contact.name ? " <span class=\"text-body-secondary small\">" + escapeHtml(contact.company) + "</span>" : "")
                        + (contact.email ? " <span class=\"text-body-secondary small\">" + escapeHtml(contact.email) + "</span>" : "")
                        + (taken ? " <span class=\"badge text-bg-warning ms-1\">" + escapeHtml(texts.taken) + "</span>" : "");
                    if (taken) { item.disabled = true; }
                    item.addEventListener("click", function () { pick(contact); });
                    menu.appendChild(item);
                });
                menu.classList.add("show");
            }
            input.addEventListener("input", function () {
                var query = input.value.trim();
                if (query.length < 2) { close(); return; }
                var current = ++request;
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    fetch(url + "?q=" + encodeURIComponent(query), { credentials: "same-origin" })
                        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                        .then(function (data) {
                            if (current !== request || document.activeElement !== input) { return; }
                            render((data && data.results) ? data.results : []);
                        })
                        .catch(close);
                }, 250);
            });
            input.addEventListener("keydown", function (event) {
                if (event.key === "Enter") { event.preventDefault(); var first = menu.querySelector("button:not([disabled])"); if (first) { first.click(); } }
                if (event.key === "Escape") { close(); }
            });
            document.addEventListener("click", function (event) {
                if (!card.contains(event.target)) { close(); }
            });
            unlink.addEventListener("click", function () {
                hidden.value = "0";
                summary.innerHTML = "<div class=\"text-body-secondary\">" + escapeHtml(texts.none) + "</div>"
                    + "<div class=\"small text-warning-emphasis mt-2\"><i class=\"bi bi-info-circle me-1\"></i>" + escapeHtml(texts.unlinkPending) + "</div>";
                unlink.classList.add("d-none");
            });
        })();
        </script>
    </div>';
}

/**
 * The currency row shared by the account and the till forms.
 *
 * Nothing at all while foreign currency is off: the record is in the base
 * currency and the screen does not ask. Locked, the code is shown read-only
 * and posted through a hidden field, so a save keeps it.
 *
 * @param liveform $liveform
 * @param bool     $locked
 * @param string   $help
 * @return string  HTML
 */
function erp_currency_form_row($liveform, $locked, $help)
{
    if (!erp_fx_enabled()) {
        return '';
    }

    $current = strtoupper(trim((string) $liveform->get_field_value('currency')));

    if ($current === '') {
        $current = erp_base_currency();
    }

    if ($locked) {
        $field = '<input type="hidden" name="currency" value="' . h($current) . '" />
                    <input class="form-control" type="text" value="' . h($current) . '" readonly="readonly" />
                    <div class="form-text">' . lang('Cannot be changed once there are movements.') . '</div>';
    } else {
        $options = erp_fx_currency_options();
        // A code that is no longer allowed still has to be shown, or the save
        // would silently move the record to the base currency.
        if (!in_array($current, $options, true)) {
            $options[h($current)] = $current;
        }
        $field = $liveform->output_field(array(
            'type' => 'select', 'id' => 'currency', 'name' => 'currency',
            'class' => 'form-select', 'options' => $options)) . '
                    <div class="form-text">' . $help . '</div>';
    }

    return '
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="currency" class="form-label">' . lang('Currency') . '</label>
                    ' . $field . '
                </div>
            </div>';
}
