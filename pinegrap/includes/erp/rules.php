<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - accounting rules: the choices the tax practice of a store decides and
 * the software should not decide for it, kept in config and chosen on the ERP
 * settings screen.
 *
 * - erp_vat_net_withholding: whether the VAT report and the accountant's pack
 *   take the VAT withheld on sales off the calculated VAT (the buyer declares
 *   that part) or show the calculated VAT in full with the withheld part
 *   beside it.
 * - erp_vat_exemption_code: the exemption reason code a zero-rated invoice
 *   line carries when its product names none; an e-document with a 0% line
 *   and no reason is refused by the tax authority's schema.
 *
 * The third rule, whether an expense category takes its VAT back, sits on the
 * category (erp_expense_categories.tax_deductible) and is edited with the
 * categories.
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
 * Whether the rule columns are there (2026.4.4, 4.103).
 *
 * @return bool
 */
function erp_rules_ready()
{
    return function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_vat_net_withholding');
}

/**
 * The rules as they are set, read once per request.
 *
 * @param bool $reload  Read again (after a save)
 * @return array ['vat_net_withholding' => bool, 'vat_exemption_code' => string]
 */
function erp_rules($reload = false)
{
    static $rules = null;

    if (($rules !== null) && !$reload) {
        return $rules;
    }

    $rules = array('vat_net_withholding' => false, 'vat_exemption_code' => '');

    if (!erp_rules_ready()) {
        return $rules;
    }

    $row = db_item("SELECT erp_vat_net_withholding, erp_vat_exemption_code FROM config LIMIT 1");

    if (is_array($row)) {
        $rules['vat_net_withholding'] = ((int) $row['erp_vat_net_withholding'] === 1);
        $rules['vat_exemption_code'] = trim((string) $row['erp_vat_exemption_code']);
    }

    return $rules;
}

/**
 * Whether the VAT withheld on sales comes off the calculated VAT.
 *
 * @return bool
 */
function erp_vat_net_withholding()
{
    $rules = erp_rules();

    return $rules['vat_net_withholding'];
}

/**
 * The exemption code a zero-rated line takes when its product names none.
 *
 * @return string  '' when none is set
 */
function erp_vat_exemption_default()
{
    $rules = erp_rules();

    return $rules['vat_exemption_code'];
}

/**
 * Whether a typed exemption code has the shape of the tax authority's list:
 * three digits, or nothing.
 *
 * @param string $code
 * @return bool
 */
function erp_vat_exemption_code_valid($code)
{
    $code = trim((string) $code);

    return ($code === '') || (bool) preg_match('/^\d{3}$/', $code);
}

/**
 * The exemption code an invoice line is written with: none for a line that
 * carries VAT; for a zero-rated one the code it already has (a return keeps
 * its invoice's), else its product's (products.vat_exemption_code), else the
 * store's default.
 *
 * @param array $line  tax_rate, product_id, vat_exemption_code (optional)
 * @return string
 */
function erp_vat_line_exemption_code($line)
{
    if (abs((float) ($line['tax_rate'] ?? 0)) > 0.0001) {
        return '';
    }

    $given = trim((string) ($line['vat_exemption_code'] ?? ''));

    if ($given !== '') {
        return mb_substr($given, 0, 10);
    }

    $product_id = (int) ($line['product_id'] ?? 0);

    if (($product_id > 0) && waf_table_has_column('products', 'vat_exemption_code')) {
        $code = trim((string) db_value("SELECT vat_exemption_code FROM products WHERE id = '" . $product_id . "'"));

        if ($code !== '') {
            return mb_substr($code, 0, 10);
        }
    }

    return mb_substr(erp_vat_exemption_default(), 0, 10);
}

/**
 * The difference of a VAT period: the calculated VAT, less the VAT withheld
 * on sales when the store takes it off, less the deductible VAT.
 *
 * @param int $calculated      Sales less sales returns
 * @param int $deductible      Purchases less returns to suppliers, plus expenses
 * @param int $withheld_sales  Withheld on sales less sales returns
 * @return int
 */
function erp_vat_difference($calculated, $deductible, $withheld_sales)
{
    return (int) $calculated - (erp_vat_net_withholding() ? (int) $withheld_sales : 0) - (int) $deductible;
}

/**
 * The caption of that difference, which says whether the withheld VAT came
 * off.
 *
 * @param bool|null $net  erp_vat_net_withholding() when null
 * @return string
 */
function erp_vat_difference_label($net = null)
{
    if ($net === null) {
        $net = erp_vat_net_withholding();
    }

    return $net
        ? lang('Difference (calculated less withheld on sales, less deductible)')
        : lang('Difference (calculated less deductible)');
}

/**
 * Save the rules from the settings screen.
 *
 * @param array $input  vat_net_withholding ('1' or ''), vat_exemption_code
 * @return array ['success' => bool, 'error' => string, 'field' => string]
 */
function erp_rules_save($input)
{
    if (!erp_rules_ready()) {
        return array('success' => false, 'error' => lang('The accounting rules come with the software update; run the update to set them.'), 'field' => '_error');
    }

    $code = trim((string) ($input['vat_exemption_code'] ?? erp_vat_exemption_default()));

    if (!erp_vat_exemption_code_valid($code)) {
        return array('success' => false, 'error' => lang('The exemption code is three digits, as the tax authority lists it (for example 351).'), 'field' => 'vat_exemption_code');
    }

    $before = erp_rules();
    $net = array_key_exists('vat_net_withholding', $input) ? !empty($input['vat_net_withholding']) : $before['vat_net_withholding'];

    if (db("UPDATE config SET
            erp_vat_net_withholding = '" . ($net ? 1 : 0) . "',
            erp_vat_exemption_code = '" . escape($code) . "'") === false) {
        return array('success' => false, 'error' => lang('The settings could not be saved.'), 'field' => '_error');
    }

    $after = erp_rules(true);

    if (($after !== $before) && function_exists('log_activity')) {
        log_activity(lang(array(
            'string' => 'ERP accounting rules changed: VAT withheld on sales {var:1}; exemption code for zero-rated lines {var:2}.',
            'vars' => array(
                $after['vat_net_withholding'] ? lang('taken off the calculated VAT') : lang('shown beside the calculated VAT'),
                ($after['vat_exemption_code'] !== '') ? $after['vat_exemption_code'] : '-',
            ),
        )));
    }

    return array('success' => true, 'error' => '', 'field' => '');
}
