<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one card of the e-document provider beside its ERP account.
 *
 * A linked pair is shown field by field: what the ERP holds, what the
 * provider holds, and for every difference which side is right. The
 * offered direction fills the empty side from the other and otherwise
 * keeps the ERP's value; nothing is written until the operator confirms,
 * and the provider's card is read back afterwards so a value it did not
 * keep is named. A card with no account is linked by hand or taken in as a
 * new account; an account with no card (?account_id=) is linked to one or
 * sent to the provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_account_sync_item');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync.php';
$provider = erp_edoc_accounts_provider();
$id = (int) ($_REQUEST['id'] ?? 0);
$account_id = (int) ($_REQUEST['account_id'] ?? 0);
$row = ($id > 0) ? erp_edoc_account_row($id) : null;
$account = null;

// An account that has a card is looked at through its card.
if (($row === null) && ($account_id > 0) && ($provider !== '')) {
    $linked = erp_edoc_account_card_of($provider, $account_id);

    if ($linked !== null) {
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync_item.php?id=' . (int) $linked['id']);
    }

    $account = erp_account($account_id);
}

if (($row === null) && ($account === null)) {
    output_error(lang('The card could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Account sync') . '</a>');
    exit();
}

if ($row !== null) {
    $provider_code = (string) $row['provider'];
    $account = ((string) $row['status'] === 'linked') ? erp_account((int) $row['account_id']) : null;
    $self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync_item.php?id=' . (int) $row['id'];
} else {
    $provider_code = $provider;
    $self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync_item.php?account_id=' . (int) $account['id'];
}

$provider_info = erp_edoc_info($provider_code);
$provider_label = is_array($provider_info) ? (string) $provider_info['label'] : $provider_code;
$mode = ($row === null) ? 'account' : (((string) $row['status'] === 'linked') ? (($account !== null) ? 'linked' : 'broken') : (string) $row['status']);

if ($_POST) {

    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? '');
    $user_id = (int) $user['id'];

    if (($action === 'align') && ($mode === 'linked')) {

        $choices = array();
        foreach ((array) ($_POST['choice'] ?? array()) as $field => $direction) {
            if (in_array((string) $direction, array('erp', 'provider', 'keep'), true)) {
                $choices[(string) $field] = (string) $direction;
            }
        }

        $result = erp_edoc_account_align((int) $row['id'], $choices, $user_id);

        if ($result['success']) {
            $liveform->add_notice(h($result['message']));

            if (!empty($result['not_taken'])) {
                $liveform->add_warning(h(lang(array(
                    'string' => '{var:1} answered that it saved the card, but these fields read back unchanged: {var:2}. Check them in {var:1}.',
                    'vars' => array($provider_label, implode(', ', $result['not_taken'])),
                ))));
            }
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif ($action === 'refresh') {

        $result = erp_edoc_account_refresh((int) $row['id']);

        if ($result['success']) {
            $liveform->add_notice(h(lang(array('string' => 'The card has been read again from {var:1}.', 'vars' => $provider_label))));
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif ($action === 'unlink') {

        $result = erp_edoc_account_unlink((int) $row['id'], $user_id);

        if ($result['success']) {
            $liveform->add_notice(lang('The link has been undone. Neither record was changed, and the card is not linked by its number again on its own.'));
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif (($action === 'link') && ($row !== null)) {

        $result = erp_edoc_account_link((int) $row['id'], (int) ($_POST['link_account_id'] ?? 0), $user_id, 'manual');

        if ($result['success']) {
            $liveform->add_notice(lang('The card is linked to the account. Check the differences below.'));
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif (($action === 'link_card') && ($row === null)) {

        $card_id = (int) ($_POST['link_card_id'] ?? 0);
        $result = erp_edoc_account_link($card_id, (int) $account['id'], $user_id, 'manual');

        if ($result['success']) {
            $liveform->add_notice(lang('The card is linked to the account. Check the differences below.'));
            $self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync_item.php?id=' . $card_id;
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif (($action === 'import') && ($row !== null)) {

        $result = erp_edoc_account_import((int) $row['id'], $user_id);

        if ($result['success']) {
            $liveform->add_notice($result['linked']
                ? lang('An account already carried this number; the card has been linked to it.')
                : lang('The card has been taken into the ERP as a new account.'));

            if (!empty($result['notes'])) {
                $liveform->add_warning(h(lang('Left out because the account card would refuse them:') . ' ' . implode(' · ', $result['notes'])));
            }
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif (($action === 'push') && ($row === null)) {

        $result = erp_edoc_account_push((int) $account['id'], $user_id);

        if ($result['success']) {
            $liveform->add_notice(h($result['message']));
            $self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync_item.php?id=' . (int) $result['card_id'];
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

    } elseif (($action === 'aside') || ($action === 'restore')) {

        $aside = ($action === 'aside');

        if ($row !== null) {
            $result = erp_edoc_account_set_aside((int) $row['id'], $aside, $user_id);
        } else {
            $result = erp_edoc_accounts_erp_set_aside($provider_code, array((int) $account['id']), $aside)
                ? array('success' => true, 'error' => '')
                : array('success' => false, 'error' => lang('The choice could not be saved.'));
        }

        if ($result['success']) {
            $liveform->add_notice($aside
                ? lang('Put aside. It is listed under "Put aside" and can be brought back.')
                : lang('Brought back.'));
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }
    }

    go($self_url);
}

$fields = erp_edoc_account_fields();
$wait = ' data-loading-content="' . lang(array('string' => 'Please Wait')) . '"';

// The confirmation sits on the button: the panel's click handler asks it.
$form = function ($action, $button, $extra = '', $confirm = '') use ($row, $account) {
    if (($confirm !== '') && (strpos($button, '<button ') === 0)) {
        $button = '<button data-confirm-content="' . h($confirm) . '" ' . substr($button, 8);
    }

    return '<form action="erp_account_sync_item.php" method="post" class="d-inline">
                ' . get_token_field() . '
                ' . (($row !== null) ? '<input type="hidden" name="id" value="' . (int) $row['id'] . '" />' : '<input type="hidden" name="account_id" value="' . (int) $account['id'] . '" />') . '
                <input type="hidden" name="erp_action" value="' . h($action) . '" />
                ' . $extra . $button . '
            </form>';
};

$text = function ($field, $value) {
    $out = erp_edoc_account_value_text($field, $value);

    return ($out !== '') ? nl2br(h($out)) : '<span class="text-body-secondary">—</span>';
};

$output_main = '';
$output_actions = '';

// The details of one side, for a card or an account that stands alone.
$details = function ($card) use ($fields, $text) {
    $rows = '';

    foreach ($fields as $field => $label) {
        $rows .= '<tr><th class="fw-normal text-body-secondary" style="width:30%">' . h($label) . '</th><td>' . $text($field, $card[$field] ?? '') . '</td></tr>';
    }

    return '<table class="table align-middle mb-0">' . $rows . '</table>';
};

// Accounts or cards to link to, as rows with a button each.
$pick_list = function ($items, $kind) use ($form, $wait, $provider_label) {
    if (empty($items)) {
        return '';
    }

    $rows = '';

    foreach ($items as $item) {
        if ($kind === 'account') {
            $taken = ((int) ($item['card_id'] ?? 0) > 0);
            $button = $taken
                ? '<span class="small text-body-secondary">' . h(lang(array('string' => 'already linked to a card of {var:1}', 'vars' => $provider_label))) . '</span>'
                : $form('link', '<button type="submit" class="btn btn-sm btn-outline-primary"' . $wait . '><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('Link to this account') . '</button>',
                    '<input type="hidden" name="link_account_id" value="' . (int) $item['id'] . '" />');
            $name = '<a href="edit_erp_account.php?id=' . (int) $item['id'] . '">' . h($item['title']) . '</a>'
                . (((string) $item['status'] === 'passive') ? ' <span class="badge rounded-pill text-bg-light">' . lang('Passive') . '</span>' : '');
        } else {
            $taken = ((string) $item['status'] === 'linked');
            $button = $taken
                ? '<span class="small text-body-secondary">' . lang('linked to another account') . '</span>'
                : $form('link_card', '<button type="submit" class="btn btn-sm btn-outline-primary"' . $wait . '><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('Link to this card') . '</button>',
                    '<input type="hidden" name="link_card_id" value="' . (int) $item['id'] . '" />');
            $name = h($item['title']) . (((string) $item['code'] !== '') ? ' <span class="small text-body-secondary">' . h(lang(array('string' => 'code {var:1}', 'vars' => (string) $item['code']))) . '</span>' : '');
        }

        $rows .= '<tr>
                        <td>' . $name . '</td>
                        <td class="text-nowrap">' . h($item['tax_number']) . '</td>
                        <td>' . h($item['city']) . '</td>
                        <td class="text-end">' . $button . '</td>
                    </tr>';
    }

    return '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>' . $rows . '</tbody></table></div>';
};

$search_box = function ($placeholder) use ($row, $account) {
    return '<form action="erp_account_sync_item.php" method="get" class="d-flex gap-2 my-3" role="search">
                ' . (($row !== null) ? '<input type="hidden" name="id" value="' . (int) $row['id'] . '" />' : '<input type="hidden" name="account_id" value="' . (int) $account['id'] . '" />') . '
                <input type="search" name="q" value="' . h((string) ($_GET['q'] ?? '')) . '" class="form-control form-control-sm" placeholder="' . h($placeholder) . '" maxlength="100" />
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-search me-1" aria-hidden="true"></i>' . lang('Search') . '</button>
            </form>';
};

if ($mode === 'linked') {

    $erp = erp_edoc_account_erp_card($account);
    $card = erp_edoc_account_stored_card($row);
    $diff = erp_edoc_account_diff($erp, $card);
    $table_rows = '';

    foreach ($fields as $field => $label) {
        $erp_value = $erp[$field] ?? '';
        $card_value = $card[$field] ?? '';

        if (!isset($diff[$field])) {
            $table_rows .= '<tr class="text-body-secondary">
                    <th class="fw-normal">' . h($label) . '</th>
                    <td>' . $text($field, $erp_value) . '</td>
                    <td>' . $text($field, $card_value) . '</td>
                    <td><i class="bi bi-check2 text-success me-1" aria-hidden="true"></i>' . lang('Same') . '</td>
                </tr>';
            continue;
        }

        $default = erp_edoc_account_default_direction($field, $erp_value, $card_value, $erp);
        $erp_refusal = erp_edoc_account_erp_refusal($field, $card_value, array_merge($erp, array($field => $card_value)));
        $provider_refusal = erp_edoc_account_provider_refusal($field, $erp_value, $erp);
        $name = 'choice[' . h($field) . ']';
        $options = array(
            'erp' => array(lang('Use the ERP value'), $provider_refusal),
            'provider' => array(lang(array('string' => 'Use the {var:1} value', 'vars' => $provider_label)), $erp_refusal),
            'keep' => array(lang('Leave as is'), ''),
        );
        $radios = '';

        foreach ($options as $value => $option) {
            $radio_id = 'choice_' . h($field) . '_' . $value;
            $disabled = ($option[1] !== '');
            $radios .= '<div class="form-check">
                        <input class="form-check-input" type="radio" name="' . $name . '" id="' . $radio_id . '" value="' . $value . '"'
                            . (($default === $value) ? ' checked' : '') . ($disabled ? ' disabled' : '') . ' />
                        <label class="form-check-label" for="' . $radio_id . '">' . h($option[0])
                            . ($disabled ? ' <span class="small text-danger d-block">' . h($option[1]) . '</span>' : '') . '</label>
                    </div>';
        }

        $table_rows .= '<tr class="table-warning">
                <th class="fw-semibold">' . h($label) . '</th>
                <td>' . $text($field, $erp_value) . '</td>
                <td>' . $text($field, $card_value) . '</td>
                <td class="small">' . $radios . '</td>
            </tr>';
    }

    $output_main = '
            <form action="erp_account_sync_item.php" method="post" id="erp_account_align">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . (int) $row['id'] . '" />
                <input type="hidden" name="erp_action" value="align" />
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Comparison') . '</div>
                    <div class="card-body">
                        <p class="text-body-secondary small">' . (empty($diff)
                            ? lang('The account and the card agree on every field that is compared.')
                            : h(lang(array('string' => 'Choose which side is right for each difference. The empty side is filled from the other; where both hold something, the ERP value is offered. {var:1} is written first and read back.', 'vars' => $provider_label)))) . '</p>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:18%">' . lang('Field') . '</th>
                                        <th>' . lang('ERP account') . '</th>
                                        <th>' . h($provider_label) . '</th>
                                        <th style="width:26%">' . lang('Which is right') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $table_rows . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>';

    if (!empty($diff)) {
        $output_actions .= '<button type="submit" form="erp_account_align" class="btn my-1 btn-primary" data-confirm-content="' . h(lang(array('string' => 'Write the chosen values to the ERP account and to {var:1}?', 'vars' => $provider_label))) . '"' . $wait . '><i class="bi bi-arrow-left-right me-2" aria-hidden="true"></i>' . lang('Align the chosen fields') . '</button>';
    }

    $output_actions .= $form('unlink', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-x-circle me-2" aria-hidden="true"></i>' . lang('Undo the link') . '</button>', '',
        lang('Undo the link between this card and the account? Neither record changes.'));

} elseif ($mode === 'account') {

    $number = erp_edoc_account_tax_key($account['tax_number']);
    $same_number = ($provider_code !== '' && $number !== '')
        ? (array) db_items("SELECT * FROM erp_edoc_accounts WHERE provider = '" . escape($provider_code) . "' AND tax_number = '" . escape($number) . "' ORDER BY id ASC LIMIT 20")
        : array();
    $found = (isset($_GET['q']) && ($provider_code !== '')) ? erp_edoc_account_card_search($provider_code, (string) $_GET['q']) : array();

    $output_main = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('ERP account') . '</div>
                <div class="card-body p-0">' . $details(erp_edoc_account_erp_card($account)) . '</div>
            </div>
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang(array('string' => 'Link to a card at {var:1}', 'vars' => $provider_label))) . '</div>
                <div class="card-body">
                    ' . (!empty($same_number)
                        ? '<p class="small text-body-secondary">' . lang('Cards with the same tax number:') . '</p>' . $pick_list($same_number, 'card')
                        : '<p class="small text-body-secondary mb-0">' . h(lang(array('string' => 'No card at {var:1} carries this number (as of the last read).', 'vars' => $provider_label))) . '</p>') . '
                    ' . $search_box(lang('Name, tax number or code')) . '
                    ' . (isset($_GET['q']) ? (!empty($found) ? $pick_list($found, 'card') : '<p class="small text-body-secondary mb-0">' . lang('Nothing found.') . '</p>') : '') . '
                </div>
            </div>';

    $eligible = erp_edoc_account_tax_number_usable($number, (string) $account['country_code']) && ((int) $account['id'] !== erp_edoc_accounts_walkin_id()) && empty($same_number);

    if (($provider !== '') && $eligible) {
        $output_actions .= $form('push', '<button type="submit" class="btn my-1 btn-primary"' . $wait . '><i class="bi bi-cloud-upload me-2" aria-hidden="true"></i>' . h(lang(array('string' => 'Send to {var:1} as a new card', 'vars' => $provider_label))) . '</button>', '',
            lang(array('string' => 'Open a card for this account at {var:1}?', 'vars' => $provider_label)));
    } elseif (!$eligible && empty($same_number)) {
        $output_main = '<div class="alert alert-info my-4">' . lang('Only an account with a valid tax number is sent; the walk-in account never is.') . '</div>' . $output_main;
    }

    $aside = in_array((int) $account['id'], ($provider_code !== '') ? erp_edoc_accounts_ignored_ids($provider_code) : array(), true);
    $output_actions .= $aside
        ? $form('restore', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>' . lang('Bring back') . '</button>')
        : $form('aside', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-archive me-2" aria-hidden="true"></i>' . lang('Set aside') . '</button>');

} else {

    // A card with no account: waiting, set aside, or linked to an account
    // that is no longer there.
    $card = erp_edoc_account_stored_card($row);
    $candidates = erp_edoc_account_candidates($row);
    $found = isset($_GET['q']) ? erp_edoc_account_search($provider_code, (string) $_GET['q']) : array();

    $output_main = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang(array('string' => 'Card at {var:1}', 'vars' => $provider_label))) . '</div>
                <div class="card-body p-0">' . $details($card) . '</div>
            </div>';

    if ($mode === 'broken') {
        $output_main = '<div class="alert alert-warning my-4">' . lang('The card is linked to an account that is no longer there. Undo the link and link it again.') . '</div>' . $output_main;
        $output_actions .= $form('unlink', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-x-circle me-2" aria-hidden="true"></i>' . lang('Undo the link') . '</button>');
    } elseif ($mode === 'new') {
        $output_main .= '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Link to an ERP account') . '</div>
                <div class="card-body">
                    ' . (!empty($candidates)
                        ? '<p class="small text-body-secondary">' . lang('Accounts with the same tax number:') . '</p>' . $pick_list($candidates, 'account')
                        : '<p class="small text-body-secondary mb-0">' . lang('No ERP account carries this number.') . '</p>') . '
                    ' . $search_box(lang('Name, tax number or e-mail address')) . '
                    ' . (isset($_GET['q']) ? (!empty($found) ? $pick_list($found, 'account') : '<p class="small text-body-secondary mb-0">' . lang('Nothing found.') . '</p>') : '') . '
                </div>
            </div>';

        $output_actions .= $form('import', '<button type="submit" class="btn my-1 btn-primary"' . $wait . '><i class="bi bi-box-arrow-in-down me-2" aria-hidden="true"></i>' . lang('Take into the ERP as a new account') . '</button>', '',
            lang('Open a new ERP account from this card? When an account already carries its number, the card is linked to it instead.'));
        $output_actions .= $form('aside', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-archive me-2" aria-hidden="true"></i>' . lang('Set aside') . '</button>');
    } else {
        $output_actions .= $form('restore', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>' . lang('Bring back') . '</button>');
    }
}

// The status column.
$sources = array(
    'match' => lang('Matched by tax number'),
    'manual' => lang('Linked by hand'),
    'invoice' => lang('Linked by an invoice'),
    'created' => lang('Sent from the ERP'),
    'imported' => lang('Taken into the ERP'),
);
$status_items = array();

if ($row !== null) {
    $settings = erp_edoc_settings($provider_code);
    $states = array(
        'linked' => '<span class="badge rounded-pill text-bg-success">' . lang('Linked') . '</span>',
        'broken' => '<span class="badge rounded-pill text-bg-danger">' . lang('Account missing') . '</span>',
        'new' => '<span class="badge rounded-pill text-bg-primary">' . lang('Not linked') . '</span>',
        'ignored' => '<span class="badge rounded-pill text-bg-secondary">' . lang('Put aside') . '</span>',
    );
    $status_items[lang('Status')] = ($states[$mode] ?? '')
        . (erp_edoc_account_gone($row, $settings) ? ' <span class="badge rounded-pill text-bg-danger">' . lang(array('string' => 'Not at {var:1} any more', 'vars' => h($provider_label))) . '</span>' : '');
    $status_items[lang('Card id')] = h($row['external_id']);
    $status_items[lang('Code')] = ((string) $row['code'] !== '') ? h($row['code']) : '—';

    if ($account !== null) {
        $status_items[lang('ERP account')] = '<a href="edit_erp_account.php?id=' . (int) $account['id'] . '">' . h($account['title']) . '</a>';
        $status_items[lang('How linked')] = h($sources[(string) $row['link_source']] ?? '—');
        $status_items[lang('Last aligned')] = ((int) $row['synced_at'] > 0) ? h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['synced_at']), 'date and time')) : '—';
    }

    $status_items[h(lang(array('string' => 'Changed at {var:1}', 'vars' => $provider_label)))] = ((int) $row['provider_updated_at'] > 0) ? h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['provider_updated_at']), 'date and time')) : '—';
    $status_items[lang('Last read')] = h(prepare_form_data_for_output(date('Y-m-d H:i:s', ((int) $row['seen_at'] > 0) ? (int) $row['seen_at'] : (int) $row['updated_at']), 'date and time'));
} else {
    $status_items[lang('Status')] = '<span class="badge rounded-pill text-bg-primary">' . h(lang(array('string' => 'No card at {var:1}', 'vars' => $provider_label))) . '</span>';
    $status_items[lang('ERP account')] = '<a href="edit_erp_account.php?id=' . (int) $account['id'] . '">' . h($account['title']) . '</a>';
}

$output_status = '';
foreach ($status_items as $label => $value) {
    $output_status .= '<div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . $label . '</div>
                                <div>' . $value . '</div>
                            </div>';
}

$heading = ($row !== null) ? (((string) $row['title'] !== '') ? (string) $row['title'] : ('#' . $row['external_id'])) : (string) $account['title'];

echo
pg_page_shell([
        'title' => lang('Account sync'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => h($heading),
        'heading_description' => h(lang(array('string' => 'The ERP account beside its card at {var:1}.', 'vars' => $provider_label))),
        'cancel' => array('enable' => 'true', 'url' => 'erp_account_sync.php'),
        'breadcrumb' => array(
            array('label' => lang('Account sync'), 'url' => $list_url),
            array('label' => $heading),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . ((($row !== null) && ($provider_code !== '') && erp_edoc_supports('account', $provider_code))
                ? '<nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . $form('refresh', '<button type="submit" class="btn btn-sm btn-ghost"' . $wait . '><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Read the card again from {var:1}', 'vars' => $provider_label))) . '</button>') . '
            </nav>'
                : '') . '
        </div>

        <div class="col-12 col-lg-8 col-xxl-9 order-1">
            ' . $output_main . '

            ' . (($output_actions !== '')
                ? '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                <div class="container">
                    <div class="d-flex flex-wrap justify-content-center gap-2">' . $output_actions . '</div>
                </div>
            </nav>'
                : '') . '
        </div>

        <div class="col-12 col-lg-4 col-xxl-3 order-2">
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h($provider_label) . '</div>
                    <div class="card-body">
                        <div class="row">' . $output_status . '</div>
                        <div class="small text-body-secondary border-top mt-2 pt-2">' . lang('Balances are never exchanged.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
