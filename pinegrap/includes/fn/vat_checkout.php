<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The EU VAT check at checkout: the billing information page and the express
 * order check the VAT id typed in the tax number box against VIES when the
 * billing country is a member state other than the site's. A confirmed id
 * sets the order's tax_exempt flag (reverse charge) and writes the id, the
 * time of the check and the VIES consultation number on the order; a refused
 * id stops the form; VIES not answering leaves the order taxed with a note.
 * No totals code changes: the reverse charge rides the existing flag.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Does the typed tax number go to VIES? Only when one is typed and both the
 * billing country and the site's are member states and differ. Pure.
 *
 * @param string $tax_number      as typed
 * @param string $billing_country ISO code
 * @param string $site_country    ISO code of the seller
 * @return bool
 */
function pg_vat_checkout_applies($tax_number, $billing_country, $site_country)
{
    if (trim((string) $tax_number) === '') {
        return false;
    }

    $billing = strtoupper(trim((string) $billing_country));
    $site = strtoupper(trim((string) $site_country));

    return pg_vat_eu_member($billing) && pg_vat_eu_member($site) && ($billing !== $site);
}

/**
 * The order's three VAT columns when no confirmed id stands behind the order.
 * Pure.
 *
 * @return array{vat_id:string,vat_checked_at:int,vat_request_id:string}
 */
function pg_vat_order_fields_empty()
{
    return array('vat_id' => '', 'vat_checked_at' => 0, 'vat_request_id' => '');
}

/**
 * What a check means for the order and the customer. tax_exempt is 1 when
 * the reverse charge applies and null when the posted tax-exempt box decides
 * as before; fields are the order's vat_id, vat_checked_at and vat_request_id,
 * cut to their column widths; error stops the form on the tax number box;
 * notice is shown on the next screen. Pure.
 *
 * @param array $check    an answer of pg_vat_check(), array() for none
 * @param array $decision pg_vat_exempt_decision()'s answer
 * @return array{tax_exempt:int|null,fields:array,error:string,notice:string}
 */
function pg_vat_checkout_outcome(array $check, array $decision)
{
    $outcome = array(
        'tax_exempt' => null,
        'fields'     => pg_vat_order_fields_empty(),
        'error'      => '',
        'notice'     => '',
    );

    $reason = (string) ($decision['reason'] ?? '');

    if (!empty($decision['exempt'])) {
        $outcome['tax_exempt'] = 1;
        $outcome['fields'] = array(
            'vat_id'         => substr((string) ($check['id'] ?? ''), 0, 20),
            'vat_checked_at' => (int) ($check['checked_at'] ?? 0),
            'vat_request_id' => substr((string) ($check['request_id'] ?? ''), 0, 32),
        );
        $outcome['notice'] = lang('VAT id verified: the order is invoiced without VAT under the reverse charge.');

        return $outcome;
    }

    if ($reason === 'unavailable') {
        $outcome['notice'] = lang('The EU VAT service (VIES) could not confirm the VAT id right now, so VAT is charged on this order.');
    } elseif ($reason === 'other_country') {
        $outcome['error'] = lang('The VAT id belongs to a member state other than the billing country.');
    } elseif ($reason === 'invalid') {
        $outcome['error'] = (($check['status'] ?? '') === 'malformed')
            ? lang('This is not a valid VAT id for the billing country. Correct it, or clear the box to order with VAT.')
            : lang('The EU VAT service (VIES) does not know this VAT id. Correct it, or clear the box to order with VAT.');
    }

    return $outcome;
}

/**
 * Every notice pg_vat_checkout_outcome() can give, as stored on a form.
 *
 * @return array
 */
function pg_vat_checkout_notices()
{
    $notices = array();

    foreach (array(
        array('exempt' => true, 'reason' => 'exempt'),
        array('exempt' => false, 'reason' => 'unavailable'),
    ) as $decision) {
        $notices[] = h(pg_vat_checkout_outcome(array(), $decision)['notice']);
    }

    return $notices;
}

/**
 * The tax number box's width: a Turkish store keeps the 11 digits of the
 * identity number, elsewhere an EU VAT id with its prefix fits
 * (contacts.tax_number is VARCHAR(20)). Pure.
 *
 * @param string $store_country ISO code
 * @return int
 */
function pg_vat_tax_number_width($store_country)
{
    return (strtoupper((string) $store_country) === 'TR') ? 11 : 20;
}

/**
 * Does orders carry the VAT columns yet? They arrive with a 2026.11.0
 * migration step, and files land before the upgrade runs.
 *
 * @return bool
 */
function pg_vat_order_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('orders', 'vat_id') && pg_schema_has('orders', 'vat_request_id');
    }

    return $ready;
}

/**
 * Runs the check for a checkout form and writes the order's VAT columns.
 * Called after the form's own checks, before its orders UPDATE; answers the
 * tax_exempt value that UPDATE writes. A refusal marks the tax number box;
 * a notice goes to $notice_form (the screen the customer sees next).
 *
 * @param liveform      $form        the billing or express order form
 * @param int           $tax_exempt  the posted tax-exempt box (0 or 1)
 * @param string|int    $order_id
 * @param liveform|null $notice_form where the notice is shown; null: $form
 * @return int
 */
function pg_vat_checkout_apply($form, $tax_exempt, $order_id, $notice_form = null)
{
    $tax_exempt = $tax_exempt ? 1 : 0;

    if (!defined('ECOMMERCE_TAX') || !ECOMMERCE_TAX || !pg_vat_order_ready() || !$form->field_in_session('tax_number')) {
        return $tax_exempt;
    }

    $tax_number = (string) $form->get_field_value('tax_number');
    $billing_country = strtoupper(trim((string) $form->get_field_value('billing_country')));
    $site_country = function_exists('pg_erp_store_country') ? (string) pg_erp_store_country() : '';

    $check = array();

    if (pg_vat_checkout_applies($tax_number, $billing_country, $site_country) && !$form->check_field_error('tax_number')) {
        $check = pg_vat_check($tax_number, array('country' => $billing_country));
    }

    $outcome = pg_vat_checkout_outcome($check, pg_vat_exempt_decision($check, $billing_country, $site_country));

    if ($outcome['error'] !== '') {
        $form->mark_error('tax_number', $outcome['error']);
    }

    $target = ($notice_form !== null) ? $notice_form : $form;

    // The next screen keeps its notices until the order is placed: a notice
    // of an earlier try (another id, another country) is dropped first.
    $kept = array_values(array_diff($target->get_notices(), pg_vat_checkout_notices()));

    if (count($kept) !== count($target->get_notices())) {
        $target->clear_notices();

        foreach ($kept as $notice) {
            $target->add_notice($notice);
        }
    }

    if ($outcome['notice'] !== '') {
        $target->add_notice(h($outcome['notice']));
    }

    pg_vat_order_write($order_id, $outcome['fields']);

    return ($outcome['tax_exempt'] !== null) ? (int) $outcome['tax_exempt'] : $tax_exempt;
}

/**
 * Writes the order's VAT columns.
 *
 * @param string|int $order_id
 * @param array      $fields pg_vat_checkout_outcome()'s fields
 * @return bool
 */
function pg_vat_order_write($order_id, array $fields)
{
    if (((int) $order_id <= 0) || !pg_vat_order_ready()) {
        return false;
    }

    return (bool) db_q(
        "UPDATE orders SET vat_id = ?, vat_checked_at = ?, vat_request_id = ? WHERE id = ?",
        array((string) $fields['vat_id'], (int) $fields['vat_checked_at'], (string) $fields['vat_request_id'], (int) $order_id)
    );
}
