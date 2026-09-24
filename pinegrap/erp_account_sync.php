<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the e-document provider's customer and supplier cards beside the
 * ERP accounts: which are linked and the same, which differ, and which
 * exist on one side only.
 *
 * Reading the provider's list writes only to its register
 * (erp_edoc_accounts) and links the cards whose tax number belongs to one
 * account. Everything else is a button: a card is taken into the ERP, an
 * account sent to the provider, and a difference aligned field by field on
 * the pair's own screen (erp_account_sync_item.php).
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
$liveform = new liveform('erp_account_sync');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_sync.php';
$installed = erp_edoc_accounts_installed();
$provider = erp_edoc_accounts_provider();
$provider_label = ($provider !== '') ? (string) erp_edoc_info($provider)['label'] : '';
$tabs = erp_edoc_accounts_tabs($provider_label);

$tab = (string) ($_REQUEST['tab'] ?? '');

if ($_POST) {

    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? '');
    $back = $self_url . (isset($tabs[$tab]) ? '?tab=' . urlencode($tab) : '');

    if ($provider === '') {
        go($back);
    }

    if ($action === 'fetch') {

        $result = erp_edoc_accounts_fetch();

        if ($result['success']) {
            $liveform->add_notice(lang(array(
                'string' => '{var:1} card(s) read from {var:2}, {var:3} of them new; {var:4} linked by tax number.',
                'vars' => array($result['found'], $provider_label, $result['added'], $result['linked']),
            )));

            if ($result['truncated']) {
                $liveform->add_notice(lang(array('string' => 'There are more cards than one read takes ({var:1} pages); the rest were not read.', 'vars' => ERP_EDOC_ACCOUNTS_MAX_PAGES)));
            }
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

        go($back);
    }

    $collect = function ($name) {
        $ids = array();

        foreach ((array) ($_POST[$name] ?? array()) as $value) {
            if ((int) $value > 0) {
                $ids[(int) $value] = true;
            }
        }

        return array_keys($ids);
    };

    $card_ids = $collect('card_ids');
    $account_ids = $collect('account_ids');
    $total = count($card_ids) + count($account_ids);

    if ($total === 0) {
        $liveform->mark_error('_error', lang('Tick at least one row.'));
        go($back);
    }

    $left = max(0, $total - ERP_EDOC_ACCOUNTS_BULK);
    $card_ids = array_slice($card_ids, 0, ERP_EDOC_ACCOUNTS_BULK);
    $account_ids = array_slice($account_ids, 0, max(0, ERP_EDOC_ACCOUNTS_BULK - count($card_ids)));
    $done = 0;
    $failed = array();
    $notes = array();

    $name_of_card = function ($id) {
        $row = erp_edoc_account_row($id);

        return is_array($row) ? (string) $row['title'] : ('#' . (int) $id);
    };

    $name_of_account = function ($id) {
        $account = erp_account($id);

        return is_array($account) ? (string) $account['title'] : ('#' . (int) $id);
    };

    if ($action === 'bulk_import') {

        foreach ($card_ids as $card_id) {
            $result = erp_edoc_account_import($card_id, (int) $user['id']);

            if ($result['success']) {
                $done++;

                foreach ($result['notes'] as $note) {
                    $notes[] = $name_of_card($card_id) . ' — ' . $note;
                }
            } else {
                $failed[] = $name_of_card($card_id) . ': ' . $result['error'];
            }
        }

        $liveform->add_notice(lang(array('string' => '{var:1} card(s) taken into the ERP (a card whose number an account already carried was linked to it).', 'vars' => $done)));

    } elseif ($action === 'bulk_push') {

        foreach ($account_ids as $account_id) {
            $result = erp_edoc_account_push($account_id, (int) $user['id']);

            if ($result['success']) {
                $done++;
            } else {
                $failed[] = $name_of_account($account_id) . ': ' . $result['error'];
            }
        }

        $liveform->add_notice(lang(array('string' => '{var:1} account(s) sent to {var:2}.', 'vars' => array($done, $provider_label))));

    } elseif (($action === 'bulk_aside') || ($action === 'bulk_restore')) {

        $aside = ($action === 'bulk_aside');

        foreach ($card_ids as $card_id) {
            $result = erp_edoc_account_set_aside($card_id, $aside, (int) $user['id']);

            if ($result['success']) {
                $done++;
            } else {
                $failed[] = $name_of_card($card_id) . ': ' . $result['error'];
            }
        }

        if (!empty($account_ids) && erp_edoc_accounts_erp_set_aside($provider, $account_ids, $aside)) {
            $done += count($account_ids);
        }

        $liveform->add_notice($aside
            ? lang(array('string' => '{var:1} row(s) put aside. They stay listed under "Put aside" and can be brought back.', 'vars' => $done))
            : lang(array('string' => '{var:1} row(s) brought back.', 'vars' => $done)));
    }

    if ($left > 0) {
        $liveform->add_notice(lang(array('string' => 'Only the first {var:1} ticked rows are handled per click; {var:2} were left for the next one.', 'vars' => array(ERP_EDOC_ACCOUNTS_BULK, $left))));
    }

    if (!empty($notes)) {
        $liveform->add_notice(h(implode(' · ', array_slice($notes, 0, 5))));
    }

    if (!empty($failed)) {
        $liveform->mark_error('_error', h(implode(' · ', array_slice($failed, 0, 5)))
            . ((count($failed) > 5) ? ' ' . h(lang(array('string' => 'and {var:1} more', 'vars' => count($failed) - 5))) : ''));
    }

    log_activity(lang(array('string' => 'erp account sync handled in bulk ({var:1}: {var:2} done, {var:3} failed)', 'vars' => array($action, $done, count($failed)))), $_SESSION['sessionusername']);

    go($back);
}

$groups = erp_edoc_accounts_overview($provider);
$counts = array(
    'differ' => count($groups['differ']),
    'provider_only' => count($groups['provider_only']),
    'erp_only' => count($groups['erp_only']),
    'same' => count($groups['same']),
    'ignored' => count($groups['ignored']) + count($groups['ignored_accounts']),
);

if (!isset($tabs[$tab])) {
    $tab = ($counts['differ'] > 0) ? 'differ' : (($counts['provider_only'] > 0) ? 'provider_only' : 'erp_only');
}

$fields = erp_edoc_account_fields();
$sources = array(
    'match' => lang('Matched by tax number'),
    'manual' => lang('Linked by hand'),
    'invoice' => lang('Linked by an invoice'),
    'created' => lang('Sent from the ERP'),
    'imported' => lang('Taken into the ERP'),
);

$view = function ($url) {
    return '<button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'' . h($url) . '\'"><i class="bi bi-eye" aria-hidden="true"></i></button>';
};

$check = function ($name, $id, $label) {
    return '<td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="' . $name . '[]" value="' . (int) $id . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => $label))) . '" /></td>';
};

$card_cell = function ($row) {
    $sub = array_filter(array((string) $row['tax_number'], ((string) $row['code'] !== '') ? lang(array('string' => 'code {var:1}', 'vars' => (string) $row['code'])) : ''), 'strlen');

    return '<div class="text-truncate">' . h(((string) $row['title'] !== '') ? $row['title'] : ('#' . $row['external_id'])) . '</div>'
        . '<div class="small text-body-secondary">' . h(implode(' · ', $sub)) . '</div>';
};

$gone = function ($row) use ($provider_label) {
    return !empty($row['gone'])
        ? ' <span class="badge rounded-pill text-bg-danger" title="' . h(lang(array('string' => 'The last complete read of {var:1} did not find this card.', 'vars' => $provider_label))) . '">' . lang(array('string' => 'Not at {var:1} any more', 'vars' => h($provider_label))) . '</span>'
        : '';
};

$head = '';
$output_rows = '';
$bulk = '';
$form_open = '';
$form_close = '';

switch ($tab) {
    case 'differ':
    case 'same':
        $head = '<th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . h(lang(array('string' => 'Card at {var:1}', 'vars' => $provider_label))) . '</th>
                                <th>' . lang('ERP account') . '</th>
                                <th>' . (($tab === 'differ') ? lang('Differences') : lang('How linked')) . '</th>';

        foreach ($groups[$tab] as $row) {
            $output_info = ($tab === 'differ')
                ? h(implode(', ', array_map(function ($field) use ($fields) { return $fields[$field]; }, array_keys($row['diff']))))
                : h($sources[(string) $row['link_source']] ?? '')
                    . (((int) $row['synced_at'] > 0) ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'aligned {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['synced_at']), 'date and time')))) . '</div>' : '');

            $output_rows .= '
                        <tr>
                            <td class="align-middle text-start">' . $view('erp_account_sync_item.php?id=' . (int) $row['id']) . '</td>
                            <td style="max-width:320px" class="align-middle">' . $card_cell($row) . $gone($row) . '</td>
                            <td style="max-width:320px" class="align-middle"><a class="link-body-emphasis" href="edit_erp_account.php?id=' . (int) $row['a_id'] . '">' . h($row['a_title']) . '</a>'
                                . '<div class="small text-body-secondary">' . h($row['a_tax_number']) . '</div></td>
                            <td class="align-middle">' . $output_info . '</td>
                        </tr>';
        }
        break;

    case 'provider_only':
        $head = '<th class="noVis"><input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all"></th>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Name') . '</th>
                                <th>' . lang('City') . '</th>
                                <th>' . lang('Type') . '</th>
                                <th>' . lang('Note') . '</th>';

        foreach ($groups['provider_only'] as $row) {
            $note = array();

            if ((string) $row['hint'] !== '') {
                $note[] = h($row['hint']);
            }

            foreach ((array) $row['candidates'] as $candidate) {
                $note[] = lang(array('string' => 'In the ERP: {var:1}', 'vars' => '<a href="edit_erp_account.php?id=' . (int) $candidate['id'] . '">' . h($candidate['title']) . '</a>'))
                    . (((int) $candidate['card_id'] > 0) ? ' <span class="text-body-secondary">(' . lang('linked to another card') . ')</span>' : '');
            }

            $output_rows .= '
                        <tr>
                            ' . $check('card_ids', $row['id'], (string) $row['title']) . '
                            <td class="align-middle text-start">' . $view('erp_account_sync_item.php?id=' . (int) $row['id']) . '</td>
                            <td style="max-width:320px" class="align-middle">' . $card_cell($row) . $gone($row) . '</td>
                            <td class="align-middle">' . h($row['city']) . '</td>
                            <td class="align-middle">' . h(erp_edoc_account_value_text('kind', $row['kind'])) . (empty($row['is_active']) ? ' <span class="badge rounded-pill text-bg-light">' . lang('Passive') . '</span>' : '') . '</td>
                            <td class="align-middle small">' . implode('<br />', $note) . '</td>
                        </tr>';
        }

        $bulk = '
                <span class="enable-on-selected d-inline-flex flex-wrap gap-1">
                    <button type="submit" form="erp_account_sync_bulk" name="erp_action" value="bulk_import" class="btn btn-sm btn-outline-secondary disabled" data-confirm-content="' . h(lang('Take the ticked cards into the ERP as new accounts? A card whose number an account already carries is linked to that account instead.')) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>' . lang('Take into the ERP') . '</button>
                    <button type="submit" form="erp_account_sync_bulk" name="erp_action" value="bulk_aside" class="btn btn-sm btn-outline-secondary disabled" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . lang('Set aside') . '</button>
                </span>';
        break;

    case 'erp_only':
        $head = '<th class="noVis"><input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all"></th>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Name') . '</th>
                                <th>' . h(erp_tax_id_label()) . '</th>
                                <th>' . lang('City') . '</th>
                                <th>' . lang('Type') . '</th>';

        foreach ($groups['erp_only'] as $account) {
            $output_rows .= '
                        <tr>
                            ' . $check('account_ids', $account['id'], (string) $account['title']) . '
                            <td class="align-middle text-start">' . $view('erp_account_sync_item.php?account_id=' . (int) $account['id']) . '</td>
                            <td style="max-width:320px" class="align-middle text-truncate"><a class="link-body-emphasis" href="edit_erp_account.php?id=' . (int) $account['id'] . '">' . h($account['title']) . '</a></td>
                            <td class="align-middle text-nowrap">' . h($account['tax_number']) . '</td>
                            <td class="align-middle">' . h($account['city']) . '</td>
                            <td class="align-middle">' . h(erp_edoc_account_value_text('kind', $account['kind'])) . '</td>
                        </tr>';
        }

        $bulk = '
                <span class="enable-on-selected d-inline-flex flex-wrap gap-1">
                    <button type="submit" form="erp_account_sync_bulk" name="erp_action" value="bulk_push" class="btn btn-sm btn-outline-secondary disabled" data-confirm-content="' . h(lang(array('string' => 'Open a card at {var:1} for each ticked account?', 'vars' => $provider_label))) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-cloud-upload me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Send to {var:1}', 'vars' => $provider_label))) . '</button>
                    <button type="submit" form="erp_account_sync_bulk" name="erp_action" value="bulk_aside" class="btn btn-sm btn-outline-secondary disabled" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . lang('Set aside') . '</button>
                </span>';
        break;

    case 'ignored':
        $head = '<th class="noVis"><input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all"></th>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Name') . '</th>
                                <th>' . h(erp_tax_id_label()) . '</th>
                                <th>' . lang('Side') . '</th>';

        foreach ($groups['ignored'] as $row) {
            $output_rows .= '
                        <tr class="text-body-secondary">
                            ' . $check('card_ids', $row['id'], (string) $row['title']) . '
                            <td class="align-middle text-start">' . $view('erp_account_sync_item.php?id=' . (int) $row['id']) . '</td>
                            <td style="max-width:320px" class="align-middle">' . $card_cell($row) . '</td>
                            <td class="align-middle text-nowrap">' . h($row['tax_number']) . '</td>
                            <td class="align-middle">' . h(lang(array('string' => 'Card at {var:1}', 'vars' => $provider_label))) . '</td>
                        </tr>';
        }

        foreach ($groups['ignored_accounts'] as $account) {
            $output_rows .= '
                        <tr class="text-body-secondary">
                            ' . $check('account_ids', $account['id'], (string) $account['title']) . '
                            <td class="align-middle text-start">' . $view('erp_account_sync_item.php?account_id=' . (int) $account['id']) . '</td>
                            <td style="max-width:320px" class="align-middle text-truncate">' . h($account['title']) . '</td>
                            <td class="align-middle text-nowrap">' . h($account['tax_number']) . '</td>
                            <td class="align-middle">' . lang('ERP account') . '</td>
                        </tr>';
        }

        $bulk = '
                <span class="enable-on-selected d-inline-flex flex-wrap gap-1">
                    <button type="submit" form="erp_account_sync_bulk" name="erp_action" value="bulk_restore" class="btn btn-sm btn-outline-secondary disabled" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . lang('Bring back') . '</button>
                </span>';
        break;
}

if ($bulk !== '') {
    $form_open = '<form id="erp_account_sync_bulk" method="post" action="' . h($self_url) . '">' . get_token_field() . '<input type="hidden" name="tab" value="' . h($tab) . '" />';
    $form_close = '</form>';
}

$output_filter = '';
foreach ($tabs as $key => $item) {
    $output_filter .= '<a class="btn btn-sm btn-ghost' . (($tab === $key) ? ' active' : '') . '" href="erp_account_sync.php?tab=' . $key . '">'
        . h($item['label']) . ' <span class="badge rounded-pill text-bg-' . ((($counts[$key] > 0) && in_array($key, array('differ', 'provider_only', 'erp_only'), true)) ? $item['tone'] : 'light') . '">' . (int) $counts[$key] . '</span></a>';
}

$output_notice = '';

if (!$installed) {
    $output_notice = '<div class="alert alert-warning my-4">' . lang('Run the software upgrade first: the account sync table is not there yet.') . '</div>';
} elseif ($provider === '') {
    $active = erp_edoc_installed() ? erp_edoc_active() : '';
    $output_notice = '<div class="alert alert-info my-4">'
        . (($active === '')
            ? lang('The cards are compared with the e-document provider\'s, and no provider is selected.')
            : lang(array('string' => '{var:1} does not hand over its customer and supplier cards yet.', 'vars' => h(erp_edoc_info($active)['label']))))
        . ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-invoice')) . '">' . lang('E-Invoice settings') . '</a>'
        . '</div>';
}

$output_fetch = '';
$output_tab_note = '';

if ($provider !== '') {
    $settings = erp_edoc_settings($provider);
    $read_at = (int) ($settings['accounts_read_at'] ?? 0);

    $output_fetch = '
                <form action="erp_account_sync.php" method="post" class="d-inline-flex align-items-center gap-2">
                    ' . get_token_field() . '
                    <input type="hidden" name="erp_action" value="fetch" />
                    <input type="hidden" name="tab" value="' . h($tab) . '" />
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 text-nowrap" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Read from {var:1}', 'vars' => $provider_label))) . '</button>
                </form>
                ' . (($read_at > 0)
                    ? '<span class="small text-body-secondary">' . lang(array('string' => 'Last read {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', $read_at), 'date and time'))) . '</span>'
                    : '<span class="small text-body-secondary">' . lang('Not read yet.') . '</span>');

    $tab_notes = array(
        'differ' => lang('Linked pairs whose details differ. Open one to choose, field by field, which side is right; nothing is written until you confirm.'),
        'provider_only' => lang(array('string' => 'Cards at {var:1} that are not linked to an ERP account. Take them in as new accounts, or open one to link it to an account you already have.', 'vars' => h($provider_label))),
        'erp_only' => lang(array('string' => 'Active accounts with a valid tax number that {var:1} does not have. The walk-in account and accounts without a tax number are not listed.', 'vars' => h($provider_label))),
        'same' => lang('Linked pairs whose details agree.'),
        'ignored' => lang('Cards and accounts you put aside. They are not offered again until you bring them back.'),
    );
    $output_tab_note = '<p class="text-body-secondary small mt-3 mb-0">' . $tab_notes[$tab] . ' ' . lang('Balances are never exchanged.') . '</p>';
}

echo
pg_page_shell([
        'title' => lang('Account sync'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => lang('Account sync'),
        'heading_description' => ($provider_label !== '')
            ? h(lang(array('string' => 'The customer and supplier cards at {var:1} beside the ERP accounts.', 'vars' => $provider_label)))
            : lang('The e-document provider\'s customer and supplier cards beside the ERP accounts.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_notice . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . $output_fetch . '
                ' . $bulk . '
                <div class="pg-toolbar-grow"></div>
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="' . lang('Status') . '">' . $output_filter . '</div>
            </nav>
            ' . $output_tab_note . '
            ' . $form_open . '
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                ' . $head . '
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
            ' . $form_close . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
