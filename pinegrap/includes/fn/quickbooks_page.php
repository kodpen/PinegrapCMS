<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * QuickBooks' page of Connections (connections.php?service=quickbooks):
 * what its Status card adds (the company), its "Connection details" (the
 * application, its environment, the company, the three accounts the site's
 * sales will be booked to and the tax codes matched to the site's rates)
 * and what those forms save. The state is includes/fn/quickbooks.php's; the
 * page is gated by Site Settings › Accounting
 * (connections_accounting_may_manage()).
 *
 * The accounts and the tax codes are read from QuickBooks while the page is
 * drawn with a company connected, and again when they are saved, so only an
 * active account that fits its role, and an active sales tax code, is kept.
 * A matched tax code is a pairing of the accounting sync
 * (pg_accounting_sync_pair_save()), keyed by the company. A US company has
 * no codes to match: its sales carry TAX or NON with the site's total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quickbooks.php');

/**
 * Each account role's label and what the account holds.
 *
 * @return array role => label, help
 */
function pg_quickbooks_page_roles()
{
    return array(
        'income'   => array(
            'label' => lang('Income account'),
            'help'  => lang('Sales lines are booked to this income account.'),
        ),
        'clearing' => array(
            'label' => lang('Clearing account'),
            'help'  => lang('Payments taken online, by card or a payment service, are deposited in this account (Undeposited Funds as a rule) until the payout reaches the bank.'),
        ),
        'bank'     => array(
            'label' => lang('Bank account'),
            'help'  => lang('Payments by bank transfer, cash or on delivery are recorded in this account.'),
        ),
    );
}

/**
 * The environments' labels. Pure but for lang().
 *
 * @return array value => label
 */
function pg_quickbooks_page_environments()
{
    return array(
        'production' => lang('Production'),
        'sandbox'    => lang('Sandbox (development keys)'),
    );
}

/**
 * QuickBooks' page: the company under Status and, for who may open Site
 * Settings › Accounting, "Connection details".
 *
 * @param array  $page       connections.php's page parts
 * @param bool   $may_manage may change the accounting settings
 * @param string $app_html   the application's hint and form
 *                           (connections_app_form())
 * @return array $page
 */
function pg_quickbooks_page($page, $may_manage, $app_html)
{
    $state = quickbooks_state();
    $connection = quickbooks_connection($state);

    $page['status_extra'] = '
			<div class="mt-4">
				<div class="pg-field-label">' . h(lang('QuickBooks company')) . '</div>
				<div class="pg-text-2 text-break">' . h(($state['realm_id'] !== '') ? $state['company_name'] : lang('None connected')) . '</div>
			</div>';

    if (!$may_manage || !in_array('details', $page['cards'], true)) {
        return $page;
    }

    // Intuit's sign-in addresses, read at most once a day.
    $state = quickbooks_discovery_refresh();

    $page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('The Intuit application the site connects through, the QuickBooks company its sales go to and the accounts they are booked to.')) . '</p>
			<h3 class="pg-field-label mb-1">' . h(lang('Application')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('An app of the site\'s own, made on Intuit\'s developer portal.')) . '</p>
			' . $app_html . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('Environment')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('Which QuickBooks companies the application\'s keys reach.')) . '</p>
			' . pg_quickbooks_page_environment($state, $connection) . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('QuickBooks company')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('The company whose books the site\'s sales, customers and payments go to; it is chosen on QuickBooks\' own screen while connecting.')) . '</p>
			' . pg_quickbooks_page_company($state, $connection) . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('Ledger accounts')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('Only active accounts of the company\'s chart of accounts that fit each role are offered.')) . '</p>
			' . pg_quickbooks_page_accounts($state, $connection) . '
			<h3 class="pg-field-label mt-6 mb-1">' . h(lang('Tax codes')) . '</h3>
			<p class="pg-field-help mt-0 mb-3">' . h(lang('Each rate the site charges is matched once to one of the company\'s sales tax codes. A sale with a rate not matched waits until it is.')) . '</p>
			' . pg_quickbooks_page_tax_codes($state, $connection) . '
		</section>';

    return $page;
}

/**
 * The environment part: a select saved on its own, read-only while a
 * company is connected, as a token of one environment never reaches the
 * other.
 *
 * @param array      $state
 * @param array|null $connection
 * @return string HTML
 */
function pg_quickbooks_page_environment($state, $connection)
{
    $locked = is_array($connection);
    $options = '';

    foreach (pg_quickbooks_page_environments() as $value => $label) {
        $options .= '<option value="' . h($value) . '"' . (($value === $state['environment']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return '
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="quickbooks_environment">
				<input type="hidden" name="connections_return" value="quickbooks">
				<div>
					<label class="pg-field-label" for="quickbooks_environment">' . h(lang('Environment')) . '</label>
					<select class="pg-input" id="quickbooks_environment" name="quickbooks_environment"' . ($locked ? ' disabled aria-describedby="quickbooks_environment_locked"' : '') . '>' . $options . '</select>
					<div class="pg-field-help">' . h(lang('Development keys reach only sandbox companies, whose sales are not real. Production keys come after Intuit\'s App Assessment Questionnaire.')) . '</div>
					' . ($locked ? '<div class="pg-field-help" id="quickbooks_environment_locked">' . h(lang('Disconnect to change the environment.')) . '</div>' : '') . '
				</div>
				' . ($locked ? '' : '<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
				</div>') . '
			</form>';
}

/**
 * The company part.
 *
 * @param array      $state
 * @param array|null $connection
 * @return string HTML
 */
function pg_quickbooks_page_company($state, $connection)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

    if (xero_ready() && (xero_state()['tenant_id'] !== '')) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang('Xero keeps the site\'s books. Disconnect Xero first: one accounting service at a time.')) . '</div>';
    }

    if (!is_array($connection)) {
        return '<p class="pg-text-2 mb-0">' . h(lang('Connect QuickBooks under Status, then choose the accounts here.')) . '</p>';
    }

    if ($state['realm_id'] === '') {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang('No QuickBooks company was read. Connect QuickBooks again under Status and choose the company the site\'s sales go to.')) . '</div>';
    }

    return '<dl class="pg-kv mb-0"><dt>' . h(lang('QuickBooks company')) . '</dt><dd class="text-break">' . h($state['company_name']) . '</dd>'
        . '<dt>' . h(lang('Country')) . '</dt><dd>' . h(($state['country'] !== '') ? $state['country'] : lang('Not given')) . '</dd>'
        . '<dt>' . h(lang('Environment')) . '</dt><dd>' . h(pg_quickbooks_page_environments()[$state['environment']]) . '</dd></dl>';
}

/**
 * The accounts part: the chart of accounts is read from QuickBooks for it.
 *
 * @param array      $state
 * @param array|null $connection
 * @return string HTML
 */
function pg_quickbooks_page_accounts($state, $connection)
{
    if (!is_array($connection) || ($state['realm_id'] === '')) {
        return '<p class="pg-text-2 mb-0">' . h(lang('The accounts are chosen once a company is connected.')) . '</p>';
    }

    $read = quickbooks_accounts_read($connection['id'], $state);

    if (!$read['ok']) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang(array('string' => 'The QuickBooks accounts could not be read: {var:1}', 'vars' => $read['error']))) . '</div>';
    }

    $fields = '';

    foreach (pg_quickbooks_page_roles() as $role => $words) {

        $kept = $state['accounts'][$role];
        $offered = quickbooks_accounts_for_role($read['accounts'], $role);
        $options = '<option value="">' . h(lang('None chosen')) . '</option>';

        foreach ($offered as $account_id => $label) {
            $options .= '<option value="' . h($account_id) . '"' . (((string) $account_id === $kept['id']) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        // An account made inactive or changed in QuickBooks since it was chosen.
        $gone = (($kept['id'] !== '') && !isset($offered[$kept['id']]))
            ? '<div class="pg-field-help text-break">' . h(lang(array('string' => 'The account chosen before, {var:1}, is no longer offered: choose another.', 'vars' => quickbooks_account_label($kept)))) . '</div>'
            : '';

        $fields .= '
				<div>
					<label class="pg-field-label" for="quickbooks_account_' . $role . '">' . h($words['label']) . '</label>
					<select class="pg-input" id="quickbooks_account_' . $role . '" name="quickbooks_account_' . $role . '">' . $options . '</select>
					<div class="pg-field-help">' . h($words['help']) . '</div>
					' . $gone . '
				</div>';

    }

    return '
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="quickbooks_accounts">
				<input type="hidden" name="connections_return" value="quickbooks">
				' . $fields . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
				</div>
			</form>';
}

/**
 * The tax codes part: each rate the site charges
 * (pg_accounting_sync_site_tax_rates()) with the company's sales tax codes
 * to match it to, read for it. A US company has none to match.
 *
 * @param array      $state
 * @param array|null $connection
 * @return string HTML
 */
function pg_quickbooks_page_tax_codes($state, $connection)
{
    if (!is_array($connection) || ($state['realm_id'] === '')) {
        return '<p class="pg-text-2 mb-0">' . h(lang('The tax codes are matched once a company is connected.')) . '</p>';
    }

    if (quickbooks_tax_us($state)) {
        return '<p class="pg-text-2 mb-0">' . h(lang('A US company needs no match: each sales line is sent as taxable or not, with the tax the site charged as the sale\'s total.')) . '</p>';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

    $target = pg_accounting_sync_target();

    if (!is_array($target) || ($target['service'] !== 'quickbooks')) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang('The tax codes can be matched once the site\'s update has run.')) . '</div>';
    }

    $read = quickbooks_tax_codes_read($connection['id'], $state);

    if (!$read['ok']) {
        return '<div class="alert theme-warning small py-3 mb-0 text-break">' . h(lang(array('string' => 'The QuickBooks tax codes could not be read: {var:1}', 'vars' => $read['error']))) . '</div>';
    }

    $fields = '';

    foreach (pg_accounting_sync_site_tax_rates() as $local_id) {

        $pair = pg_accounting_sync_pair($target, 'tax', 'tax_rate', $local_id);
        $kept = is_array($pair) ? (string) $pair['remote_id'] : '';
        $options = '<option value="">' . h(lang('Not matched')) . '</option>';

        foreach ($read['codes'] as $code_id => $label) {
            $options .= '<option value="' . h($code_id) . '"' . (((string) $code_id === $kept) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        $gone = (($kept !== '') && !isset($read['codes'][$kept]))
            ? '<div class="pg-field-help text-break">' . h(lang(array('string' => 'The tax code matched before, {var:1}, is no longer offered: choose another.', 'vars' => $kept))) . '</div>'
            : '';

        $fields .= '
				<div>
					<label class="pg-field-label" for="quickbooks_tax_code_' . $local_id . '">' . h(lang(array('string' => 'Site rate {var:1}', 'vars' => pg_accounting_sync_tax_label($local_id)))) . '</label>
					<select class="pg-input" id="quickbooks_tax_code_' . $local_id . '" name="quickbooks_tax_code_' . $local_id . '">' . $options . '</select>
					' . $gone . '
				</div>';

    }

    return '
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="quickbooks_tax_codes">
				<input type="hidden" name="connections_return" value="quickbooks">
				' . $fields . '
				<div class="pg-field-help">' . h(lang('The rates of the tax zones, the products\' own rates, 0% for an untaxed sale and, with Rates by the buyer\'s country on, the EU standard rates.')) . '</div>
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" class="pg-button pg-button-sm pg-button-fill">' . h(lang('Save')) . '</button>
				</div>
			</form>';
}

/**
 * Saves the tax codes matched: the codes are read again and only an active
 * sales code is kept; a rate left "Not matched" forgets its pairing.
 *
 * @param array $state
 * @param array $connection
 * @param array $input
 * @return array messages, log
 */
function pg_quickbooks_page_save_tax_codes($state, $connection, $input)
{
    $answer = array('messages' => array(), 'log' => '');

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

    $target = pg_accounting_sync_target();

    if (quickbooks_tax_us($state) || !is_array($target) || ($target['service'] !== 'quickbooks')) {
        $answer['messages'][] = array('error', lang('The tax codes can be matched once the site\'s update has run.'));

        return $answer;
    }

    $read = quickbooks_tax_codes_read($connection['id'], $state);

    if (!$read['ok']) {
        $answer['messages'][] = array('error', lang(array('string' => 'The QuickBooks tax codes could not be read: {var:1}', 'vars' => $read['error'])));

        return $answer;
    }

    $chosen = array();

    foreach (pg_accounting_sync_site_tax_rates() as $local_id) {

        $code = (isset($input['quickbooks_tax_code_' . $local_id]) && is_string($input['quickbooks_tax_code_' . $local_id])) ? trim($input['quickbooks_tax_code_' . $local_id]) : '';

        if (($code !== '') && !isset($read['codes'][$code])) {
            $answer['messages'][] = array('error', lang(array('string' => '{var:1}: choose a tax code from the list.',
                'vars' => lang(array('string' => 'Site rate {var:1}', 'vars' => pg_accounting_sync_tax_label($local_id))))));

            return $answer;
        }

        $chosen[$local_id] = $code;
    }

    foreach ($chosen as $local_id => $code) {

        $saved = ($code === '')
            ? pg_accounting_sync_pair_drop($target, 'tax', 'tax_rate', $local_id)
            : pg_accounting_sync_pair_save($target, 'tax', 'tax_rate', $local_id, $code);

        if (!$saved) {
            $answer['messages'][] = array('error', lang('The QuickBooks settings could not be saved.'));

            return $answer;
        }
    }

    $answer['messages'][] = array('notice', lang('The QuickBooks tax codes were saved.'));
    $answer['log'] = lang(array('string' => 'changed the QuickBooks tax codes of {var:1}', 'vars' => $state['company_name']));

    return $answer;
}

/**
 * Saves what a form of "Connection details" posts.
 *
 * @param string $action quickbooks_environment, quickbooks_accounts or
 *                       quickbooks_tax_codes
 * @param array  $input  the posted values
 * @return array messages (list of [notice|error, text]; the texts carry
 *               QuickBooks' words, unescaped), log ('' when nothing was
 *               saved)
 */
function pg_quickbooks_page_save($action, $input)
{
    $answer = array('messages' => array(), 'log' => '');
    $state = quickbooks_state(true);
    $connection = quickbooks_connection($state);

    if ($action === 'quickbooks_environment') {

        $value = (isset($input['quickbooks_environment']) && is_string($input['quickbooks_environment'])) ? $input['quickbooks_environment'] : '';

        if (is_array($connection) || !isset(pg_quickbooks_page_environments()[$value])) {
            $answer['messages'][] = array('error', is_array($connection) ? lang('Disconnect to change the environment.') : lang('Choose an environment from the list.'));

            return $answer;
        }

        // The other environment has its own discovery document.
        if ($value !== $state['environment']) {
            $state['sign_in_read_at'] = 0;
        }

        $state['environment'] = $value;

        if (!quickbooks_state_save($state)) {
            $answer['messages'][] = array('error', lang('The QuickBooks settings could not be saved.'));

            return $answer;
        }

        $answer['messages'][] = array('notice', lang('The QuickBooks environment was saved.'));
        $answer['log'] = lang(array('string' => 'set the QuickBooks environment to {var:1}', 'vars' => $value));

        return $answer;
    }

    if (!in_array($action, array('quickbooks_accounts', 'quickbooks_tax_codes'), true)) {
        return $answer;
    }

    if (!is_array($connection) || ($state['realm_id'] === '')) {
        $answer['messages'][] = array('error', lang('Connect QuickBooks under Status first.'));

        return $answer;
    }

    if ($action === 'quickbooks_tax_codes') {
        return pg_quickbooks_page_save_tax_codes($state, $connection, $input);
    }

    $read = quickbooks_accounts_read($connection['id'], $state);

    if (!$read['ok']) {
        $answer['messages'][] = array('error', lang(array('string' => 'The QuickBooks accounts could not be read: {var:1}', 'vars' => $read['error'])));

        return $answer;
    }

    $chosen = array();

    foreach (pg_quickbooks_page_roles() as $role => $words) {

        $id = (isset($input['quickbooks_account_' . $role]) && is_string($input['quickbooks_account_' . $role])) ? trim($input['quickbooks_account_' . $role]) : '';

        if ($id === '') {
            $chosen[$role] = array();
            continue;
        }

        if (!isset($read['accounts'][$id]) || !quickbooks_account_fits($role, $read['accounts'][$id])) {
            $answer['messages'][] = array('error', lang(array('string' => '{var:1}: choose an account from the list.', 'vars' => $words['label'])));

            return $answer;
        }

        $chosen[$role] = $read['accounts'][$id];
    }

    $state['accounts'] = $chosen;

    if (!quickbooks_state_save($state)) {
        $answer['messages'][] = array('error', lang('The QuickBooks settings could not be saved.'));

        return $answer;
    }

    $answer['messages'][] = array('notice', lang('The QuickBooks accounts were saved.'));
    $answer['log'] = lang(array('string' => 'changed the QuickBooks accounts of {var:1}', 'vars' => $state['company_name']));

    return $answer;
}
