<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Xero's page of Connections (connections.php?service=xero): what its
 * Status card adds (the organisation), its "Connection details" (the
 * application, the organisation and the three accounts the site's sales
 * will be booked to) and what those forms save. The state is
 * includes/fn/xero.php's; the page is gated by Site Settings › Accounting
 * (connections_accounting_may_manage()).
 *
 * The chart of accounts is read from Xero while the page is drawn with an
 * organisation chosen, and again when the accounts are saved, so only an
 * active account that fits its role is kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

/**
 * Each account role's label and what the account holds.
 *
 * @return array role => label, help
 */
function pg_xero_page_roles()
{
    return array(
        'income'   => array(
            'label' => lang('Income account'),
            'help'  => lang('Sales lines are booked to this revenue account.'),
        ),
        'clearing' => array(
            'label' => lang('Clearing account'),
            'help'  => lang('Payments taken online, by card or a payment service, are recorded in this account until the payout reaches the bank.'),
        ),
        'bank'     => array(
            'label' => lang('Bank account'),
            'help'  => lang('Payments by bank transfer, cash or on delivery are recorded in this account.'),
        ),
    );
}

/**
 * Xero's page: the organisation under Status and, for who may open Site
 * Settings › Accounting, "Connection details".
 *
 * @param array  $page       connections.php's page parts
 * @param bool   $may_manage may change the accounting settings
 * @param string $app_html   the application's hint and form
 *                           (connections_app_form())
 * @return array $page
 */
function pg_xero_page($page, $may_manage, $app_html)
{
    $state = xero_state();
    $connection = xero_connection($state);

    $page['status_extra'] = '
			<div class="mt-4">
				<div class="pg-field-label">' . h(lang('Xero organisation')) . '</div>
				<div class="pg-text-2 text-break">' . h(($state['tenant_id'] !== '') ? $state['tenant_name'] : lang('None chosen')) . '</div>
			</div>';

    if (!$may_manage || !in_array('details', $page['cards'], true)) {
        return $page;
    }

    $page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('The Xero application the site connects through, the organisation its sales go to and the accounts they are booked to.')) . '</p>
			<h3 class="pg-field-label mb-1">' . h(lang('Application')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('A free Web app of the site\'s own, registered on Xero\'s developer portal.')) . '</p>
			' . $app_html . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('Xero organisation')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('The organisation whose books the site\'s sales, customers and payments go to.')) . '</p>
			' . pg_xero_page_organisation($state, $connection) . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('Ledger accounts')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('Only active accounts of the organisation\'s chart of accounts that fit each role are offered.')) . '</p>
			' . pg_xero_page_accounts($state, $connection) . '
		</section>';

    return $page;
}

/**
 * The organisation part of "Connection details".
 *
 * @param array      $state      xero_state()
 * @param array|null $connection xero_connection()
 * @return string HTML
 */
function pg_xero_page_organisation($state, $connection)
{
    if (!is_array($connection)) {
        return '<p class="pg-text-2 mb-0">' . h(lang('Connect Xero under Status, then choose the organisation and the accounts here.')) . '</p>';
    }

    if (!$state['tenants']) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang('No Xero organisation was read. Connect Xero again under Status and allow the organisation the site\'s sales go to.')) . '</div>';
    }

    if ((count($state['tenants']) === 1) && ($state['tenant_id'] !== '')) {
        return '<dl class="pg-kv mb-0"><dt>' . h(lang('Xero organisation')) . '</dt><dd class="text-break">' . h($state['tenant_name']) . '</dd></dl>';
    }

    $options = '';

    foreach ($state['tenants'] as $tenant_id => $tenant_name) {
        $options .= '<option value="' . h($tenant_id) . '"' . (($tenant_id === $state['tenant_id']) ? ' selected' : '') . '>' . h($tenant_name) . '</option>';
    }

    return '
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="xero_organisation">
				<input type="hidden" name="connections_return" value="xero">
				<div>
					<label class="pg-field-label" for="xero_tenant">' . h(lang('Xero organisation')) . '</label>
					<select class="pg-input" id="xero_tenant" name="xero_tenant">' . (($state['tenant_id'] === '') ? '<option value="">' . h(lang('Choose')) . '</option>' : '') . $options . '</select>
					<div class="pg-field-help">' . h(lang('Another organisation leaves no account chosen.')) . '</div>
				</div>
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
				</div>
			</form>';
}

/**
 * The accounts part of "Connection details": the chart of accounts is read
 * from Xero for it.
 *
 * @param array      $state      xero_state()
 * @param array|null $connection xero_connection()
 * @return string HTML
 */
function pg_xero_page_accounts($state, $connection)
{
    if (!is_array($connection) || ($state['tenant_id'] === '')) {
        return '<p class="pg-text-2 mb-0">' . h(lang('The accounts are chosen once the organisation is.')) . '</p>';
    }

    $read = xero_accounts_read($connection['id'], $state['tenant_id']);

    if (!$read['ok']) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang(array('string' => 'The Xero accounts could not be read: {var:1}', 'vars' => $read['error']))) . '</div>';
    }

    $fields = '';

    foreach (pg_xero_page_roles() as $role => $words) {

        $kept = $state['accounts'][$role];
        $offered = xero_accounts_for_role($read['accounts'], $role);
        $options = '<option value="">' . h(lang('None chosen')) . '</option>';

        foreach ($offered as $account_id => $label) {
            $options .= '<option value="' . h($account_id) . '"' . (($account_id === $kept['id']) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        // An account archived or changed in Xero since it was chosen.
        $gone = (($kept['id'] !== '') && !isset($offered[$kept['id']]))
            ? '<div class="pg-field-help text-break">' . h(lang(array('string' => 'The account chosen before, {var:1}, is no longer offered: choose another.', 'vars' => xero_account_label($kept)))) . '</div>'
            : '';

        $fields .= '
				<div>
					<label class="pg-field-label" for="xero_account_' . $role . '">' . h($words['label']) . '</label>
					<select class="pg-input" id="xero_account_' . $role . '" name="xero_account_' . $role . '">' . $options . '</select>
					<div class="pg-field-help">' . h($words['help']) . '</div>
					' . $gone . '
				</div>';

    }

    return '
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="xero_accounts">
				<input type="hidden" name="connections_return" value="xero">
				' . $fields . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
				</div>
			</form>';
}

/**
 * Saves what a form of "Connection details" posts.
 *
 * @param string $action xero_organisation or xero_accounts
 * @param array  $input  the posted values
 * @return array messages (list of [notice|warning|error, text]; the texts
 *               carry Xero's words, unescaped), log ('' when nothing was
 *               saved)
 */
function pg_xero_page_save($action, $input)
{
    $answer = array('messages' => array(), 'log' => '');
    $state = xero_state();

    if (!is_array(xero_connection($state))) {
        $answer['messages'][] = array('error', lang('Connect Xero under Status first.'));

        return $answer;
    }

    if ($action === 'xero_organisation') {

        $tenant = (isset($input['xero_tenant']) && is_string($input['xero_tenant'])) ? strtolower(trim($input['xero_tenant'])) : '';

        if (!isset($state['tenants'][$tenant])) {
            $answer['messages'][] = array('error', lang('Choose one of the organisations Xero allowed.'));

            return $answer;
        }

        $changed = ($tenant !== $state['tenant_id']);

        if ($changed) {
            $state['accounts'] = array();
        }

        $state['tenant_id'] = $tenant;
        $state['tenant_name'] = $state['tenants'][$tenant];

        if (!xero_state_save($state)) {
            $answer['messages'][] = array('error', lang('The Xero settings could not be saved.'));

            return $answer;
        }

        $answer['messages'][] = array('notice', $changed
            ? lang(array('string' => 'The site uses the Xero organisation {var:1}. Choose its accounts.', 'vars' => $state['tenant_name']))
            : lang(array('string' => 'The site uses the Xero organisation {var:1}.', 'vars' => $state['tenant_name'])));
        $answer['log'] = lang(array('string' => 'chose the Xero organisation {var:1}', 'vars' => $state['tenant_name']));

        return $answer;

    }

    if ($action !== 'xero_accounts') {
        return $answer;
    }

    if ($state['tenant_id'] === '') {
        $answer['messages'][] = array('error', lang('Choose the Xero organisation first.'));

        return $answer;
    }

    $read = xero_accounts_read(xero_connection($state)['id'], $state['tenant_id']);

    if (!$read['ok']) {
        $answer['messages'][] = array('error', lang(array('string' => 'The Xero accounts could not be read: {var:1}', 'vars' => $read['error'])));

        return $answer;
    }

    $chosen = array();

    foreach (pg_xero_page_roles() as $role => $words) {

        $id = (isset($input['xero_account_' . $role]) && is_string($input['xero_account_' . $role])) ? strtolower(trim($input['xero_account_' . $role])) : '';

        if ($id === '') {
            $chosen[$role] = array();
            continue;
        }

        if (!isset($read['accounts'][$id]) || !xero_account_fits($role, $read['accounts'][$id])) {
            $answer['messages'][] = array('error', lang(array('string' => '{var:1}: choose an account from the list.', 'vars' => $words['label'])));

            return $answer;
        }

        $chosen[$role] = $read['accounts'][$id];
    }

    $state['accounts'] = $chosen;

    if (!xero_state_save($state)) {
        $answer['messages'][] = array('error', lang('The Xero settings could not be saved.'));

        return $answer;
    }

    $answer['messages'][] = array('notice', lang('The Xero accounts were saved.'));
    $answer['log'] = lang(array('string' => 'changed the Xero accounts of {var:1}', 'vars' => $state['tenant_name']));

    return $answer;
}
