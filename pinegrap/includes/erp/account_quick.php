<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - opening an account without leaving the screen it is needed on.
 *
 * A half-written invoice, quote, delivery note or receipt is lost when the
 * operator has to go to the account form and come back, so those screens
 * open the account in a dialog instead (erp_account_quick.php answers it,
 * assets/js/erp_account_quick.js drives it) and pick it in their account
 * select. The counter sale opens its accounts through the same function, so
 * the two doors apply the same rules.
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
 * Open an account with the little a busy screen can ask for: a name, and a
 * phone, e-mail or tax number when they are at hand. The same format rules as
 * the account form, so the card is not born broken for e-documents; the rest
 * is completed on the card later.
 *
 * erp_account_save() announces the new account (erp_event_account()).
 *
 * @param array $user
 * @param array $data  kind (customer|supplier), title, is_person, tax_number, phone, email
 * @return array ['success' => bool, 'id' => int, 'title' => string, 'kind' => string,
 *                'error' => string, 'field_errors' => array field => message]
 */
function erp_account_quick_create($user, $data)
{
    $kind = in_array(($data['kind'] ?? ''), array('customer', 'supplier'), true) ? $data['kind'] : 'customer';
    $title = trim(mb_substr((string) ($data['title'] ?? ''), 0, 255));
    $is_person = ((string) ($data['is_person'] ?? '1') === '1');
    // The account opens in the store's country, and that country's rules
    // apply (erp_tax_number_check()): a Turkish number is digits with check
    // digits (GİB), any other is taken as typed.
    $is_tr = (erp_account_country('') === 'TR');
    $tax = erp_tax_number_check(mb_substr((string) ($data['tax_number'] ?? ''), 0, 64));
    $tax_number = $tax['value'];
    $email = trim(mb_substr((string) ($data['email'] ?? ''), 0, 255));
    $phone = trim(mb_substr((string) ($data['phone'] ?? ''), 0, 50));
    $errors = array();

    $result = array('success' => false, 'id' => 0, 'title' => $title, 'kind' => $kind, 'error' => '', 'field_errors' => array());

    if ($title === '') {
        $errors['title'] = lang(array('string' => '{var:1} is required', 'vars' => array(lang('Name'))));
    } elseif ($is_tr && $is_person && (erp_edoc_person_name($title) === null)) {
        $errors['title'] = lang('A person needs a first name and a surname. If this is a company, choose company as the taxpayer type.');
    }

    if ($tax['error'] !== '') {
        $errors['tax_number'] = $tax['error'];
    } elseif ($tax_number !== '') {
        $existing = db_item("SELECT id, title FROM erp_accounts WHERE tax_number = '" . escape($tax_number) . "' LIMIT 1");
        if ($existing) {
            $errors['tax_number'] = lang(array('string' => 'This number already belongs to the account “{var:1}”. Search for it instead.', 'vars' => array($existing['title'])));
        }
    }

    if (($email !== '') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = lang('Please enter a valid email address.');
    }

    if (!empty($errors)) {
        $result['error'] = reset($errors);
        $result['field_errors'] = $errors;

        return $result;
    }

    $saved = erp_account_save(array(
        'kind' => $kind,
        'title' => $title,
        'is_person' => $is_person,
        'tax_number' => $tax_number,
        'email' => $email,
        'phone' => $phone,
        'created_by' => (int) $user['id'],
    ));

    if (empty($saved['success'])) {
        $result['error'] = (string) $saved['error'];

        return $result;
    }

    log_activity(lang(array('string' => 'erp account ({var:1}) was opened from a document form', 'vars' => $title)), $_SESSION['sessionusername'] ?? '');

    $result['success'] = true;
    $result['id'] = (int) $saved['id'];

    return $result;
}

/**
 * Whether this operator may open an account from a document screen. The
 * accountant's read-only right sees the screens but changes nothing.
 *
 * @return bool
 */
function erp_account_quick_allowed()
{
    return !(defined('USER_ERP_READONLY') && USER_ERP_READONLY);
}

/**
 * The "New account" link under an account select. A plain click opens the
 * dialog and picks the new account in the select; a middle click or a
 * modified click still opens the full account form in a new tab.
 *
 * @param string $target  Selector of the account select, '#account_id'
 * @param string $kind    customer, supplier, or auto (read from the screen's #direction)
 * @return string  HTML, empty for a read-only operator
 */
function erp_account_quick_link($target, $kind = 'auto')
{
    if (!erp_account_quick_allowed()) {
        return '';
    }

    return '<div class="form-text"><a href="add_erp_account.php" class="link-body-emphasis no-submit" data-erp-account-quick data-target="' . h($target) . '" data-kind="' . h($kind) . '">' . lang('New account') . '</a></div>';
}

/**
 * The dialog behind erp_account_quick_link(), with its script. It carries a
 * form of its own, so it is printed after the screen's form has closed:
 * a form inside a form is dropped by the parser.
 *
 * @return string  HTML, empty for a read-only operator
 */
function erp_account_quick_modal()
{
    if (!erp_account_quick_allowed()) {
        return '';
    }

    $country = erp_account_country('');
    $is_tr = ($country === 'TR');

    return '
    <div class="modal fade" id="erp_account_quick_modal" tabindex="-1" aria-labelledby="erp_account_quick_title" aria-hidden="true"
         data-url="erp_account_quick.php"
         data-token="' . h((string) ($_SESSION['software']['token'] ?? '')) . '"
         data-text-failed="' . h(lang('The account could not be opened. Check the connection and try again.')) . '">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content disable_shortcut" id="erp_account_quick_form" data-pg-no-curtain novalidate>
                <input type="hidden" name="kind" value="customer" />
                <div class="modal-header">
                    <h5 class="modal-title" id="erp_account_quick_title">' . lang('New account') . '</h5>
                    <button type="button" class="btn-close no-submit" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary">' . lang('Only the name is needed. The rest can be completed on the account card later.') . '</p>
                    <div class="alert alert-danger d-none" role="alert" data-erp-quick-error="_form"></div>
                    <div class="btn-group btn-group-sm mb-3" role="group" aria-label="' . h(lang('Taxpayer type')) . '">
                        <input type="radio" class="btn-check" name="is_person" id="erp_quick_acc_person" value="1" checked>
                        <label class="btn btn-outline-secondary" for="erp_quick_acc_person"><i class="bi bi-person me-1" aria-hidden="true"></i>' . lang('Person') . '</label>
                        <input type="radio" class="btn-check" name="is_person" id="erp_quick_acc_company" value="0">
                        <label class="btn btn-outline-secondary" for="erp_quick_acc_company"><i class="bi bi-building me-1" aria-hidden="true"></i>' . lang('Company') . '</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="erp_quick_acc_title">' . lang('Name and surname, or company name') . '</label>
                        <input type="text" class="form-control" id="erp_quick_acc_title" name="title" maxlength="255" required autocomplete="off">
                        <div class="invalid-feedback" data-erp-quick-error="title"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="erp_quick_acc_phone">' . lang('Phone') . '</label>
                            <input type="tel" class="form-control" id="erp_quick_acc_phone" name="phone" maxlength="50" autocomplete="off">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="erp_quick_acc_email">' . lang('Email') . '</label>
                            <input type="email" class="form-control" id="erp_quick_acc_email" name="email" maxlength="255" autocomplete="off">
                            <div class="invalid-feedback" data-erp-quick-error="email"></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="erp_quick_acc_tax">' . h(erp_tax_id_label($country)) . ' <span class="text-body-secondary fw-normal">(' . lang('optional') . ')</span></label>
                        <input type="text" class="form-control" id="erp_quick_acc_tax" name="tax_number"' . ($is_tr ? ' inputmode="numeric" maxlength="11"' : ' maxlength="' . (int) erp_tax_number_width() . '"') . ' autocomplete="off">
                        <div class="invalid-feedback" data-erp-quick-error="tax_number"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 no-submit" data-erp-quick-go>' . lang('Open and pick') . '</button>
                </div>
            </form>
        </div>
    </div>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/erp_account_quick.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/erp_account_quick.js') . '"></script>';
}
