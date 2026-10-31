<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - sales quote templates through the external API.
 *
 * Read and written the way the quote screen's template box does
 * (includes/erp/quote_templates.php): the same rule for who sees a template
 * and who changes it, the same overwrite of a template of the same name, and
 * the same events. A template keeps lines only; a client fills a quote by
 * reading one and sending its lines to POST /erp/quotes.
 *
 * Every check the domain function makes is made here first, before the
 * first write, which is where X-Dry-Run stops (includes/api/dry_run.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A 503 for a store that has not run the update that brings the template
 * table (11.0.63).
 */
function erp_api_quote_templates_ready()
{
    if (!erp_quote_templates_ready()) {
        api_fail(503, 'service_unavailable', lang('Quote templates come with the software update; run the update to use them.'));
    }
}

/**
 * Whether the application's owner holds the accountant's read-only right.
 * Such an owner is not given erp:write; asked again so the domain check
 * stands on its own.
 *
 * @return bool
 */
function erp_api_quote_templates_readonly()
{
    $owner = (array) (api_current_app()['owner'] ?? array());

    return ((int) ($owner['role'] ?? 9) >= 3) && !empty($owner['manage_erp_readonly']);
}

// What erp_api_quote_template_present() returns, declared for the OpenAPI document.
function erp_api_quote_template_schema()
{
    return array(
        'id' => 'integer',
        'name' => 'string',
        'scope' => 'string',
        'mine' => 'boolean',
        'lines' => array(array(
            'product_id' => 'integer',
            'description' => 'string',
            'quantity' => 'number',
            'unit' => 'string',
            'unit_price' => 'integer',
            'discount_rate' => 'number',
            'tax_rate' => 'number',
            'tax2_rate' => 'number',
            'withholding_code' => 'string',
            'withholding_rate' => 'number',
        )),
        'updated_at' => 'string',
    );
}

/**
 * One template on the wire. The owner is never named: mine says whether it
 * is the caller's own personal template.
 *
 * @param array $row      id, name, scope, user_id, form_data, updated_at
 * @param int   $user_id  The application's owner
 * @return array
 */
function erp_api_quote_template_present($row, $user_id)
{
    $lines = array();

    foreach (erp_quote_template_lines($row['form_data'] ?? '') as $line) {
        $lines[] = array(
            'product_id' => (int) $line['product_id'],
            'description' => (string) $line['description'],
            'quantity' => (float) $line['quantity'],
            'unit' => (string) $line['unit_code'],
            'unit_price' => (int) $line['unit_price'],
            'discount_rate' => (float) $line['discount_rate'],
            'tax_rate' => (float) $line['tax_rate'],
            'tax2_rate' => (float) $line['tax2_rate'],
            'withholding_code' => (string) $line['withholding_code'],
            'withholding_rate' => (float) $line['withholding_rate'],
        );
    }

    return array(
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'scope' => (string) $row['scope'],
        'mine' => ((string) $row['scope'] === 'personal') && ((int) $user_id > 0) && ((int) ($row['user_id'] ?? 0) === (int) $user_id),
        'lines' => $lines,
        'updated_at' => api_time($row['updated_at'] ?? 0),
    );
}

/**
 * The template as the caller sees it, or 404.
 *
 * @param int $template_id
 * @param int $user_id
 * @return array
 */
function erp_api_quote_template_row($template_id, $user_id)
{
    $template = erp_quote_template_find($template_id, $user_id);

    if ($template === null) {
        api_fail(404, 'not_found', lang('The template could not be found.'));
    }

    return $template;
}

function erp_api_quote_templates_list($params)
{
    erp_api_quote_templates_ready();

    $user_id = (int) erp_api_owner()['id'];
    $items = array();

    foreach (erp_quote_templates_for_user($user_id) as $row) {
        $items[] = erp_api_quote_template_present($row, $user_id);
    }

    api_ok_list($items, count($items));
}

function erp_api_quote_templates_get($params)
{
    erp_api_quote_templates_ready();

    $user_id = (int) erp_api_owner()['id'];

    api_ok(erp_api_quote_template_present(erp_api_quote_template_row((int) $params['id'], $user_id), $user_id));
}

function erp_api_quote_templates_save($params)
{
    erp_api_quote_templates_ready();

    $user_id = (int) erp_api_owner()['id'];
    $readonly = erp_api_quote_templates_readonly();
    $scope = (string) $params['scope'];

    $read = erp_quote_template_lines_from_api($params['lines'] ?? array());
    if ($read['error'] !== '') {
        api_fail_validation($read['error'], $read['field']);
    }

    $check = erp_quote_template_save_check((string) ($params['name'] ?? ''), $scope, $read['lines'], $user_id, $readonly);
    if ($check['error'] !== '') {
        api_fail_validation($check['error'], ($check['field'] !== '') ? $check['field'] : null);
    }

    api_dry_run_stop(($check['replaces'] > 0) ? 'updated' : 'created', 'erp_quote_template', array(
        'id' => (int) $check['replaces'],
        'name' => $check['name'],
        'scope' => $scope,
        'lines' => count($read['lines']),
    ));

    $saved = erp_quote_template_save($check['name'], $scope, $read['lines'], $user_id, $readonly);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], ((string) $saved['field'] !== '') ? (string) $saved['field'] : null);
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote template {var:1} was saved through the API.',
        'vars' => (string) $saved['name'],
    )));

    api_ok(erp_api_quote_template_present(erp_api_quote_template_row((int) $saved['template_id'], $user_id), $user_id), $saved['created'] ? 201 : 200);
}

function erp_api_quote_templates_delete($params)
{
    erp_api_quote_templates_ready();

    $user_id = (int) erp_api_owner()['id'];
    $readonly = erp_api_quote_templates_readonly();

    $check = erp_quote_template_delete_check(erp_quote_template_find((int) $params['id'], $user_id), $user_id, $readonly);

    if ($check['status'] === 404) {
        api_fail(404, 'not_found', $check['error']);
    }

    if ($check['error'] !== '') {
        api_fail_validation($check['error']);
    }

    api_dry_run_stop('deleted', 'erp_quote_template', array('id' => (int) $check['template']['id'], 'name' => (string) $check['template']['name']));

    $deleted = erp_quote_template_delete((int) $check['template']['id'], $user_id, $readonly);

    if (empty($deleted['success'])) {
        api_fail_validation((string) $deleted['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote template {var:1} was deleted through the API.',
        'vars' => (string) $deleted['name'],
    )));

    api_ok(array('id' => (int) $check['template']['id'], 'deleted' => true));
}
