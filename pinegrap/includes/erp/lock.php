<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the period lock.
 *
 * When the accountant has filed a period (the VAT return, the books), what
 * was filed must not change under them. The lock is one date: nothing dated
 * on or before it can be issued, cancelled, returned, received or paid. The
 * document functions ask erp_lock_refusal() first, so the operator is told
 * why in plain words; erp_account_post() and erp_cash_post() ask again, so a
 * path that forgot to ask still cannot write into a closed period.
 *
 * A return or a cancellation made after the lock is dated when it is made,
 * in the open period, as the accountant would book it; what cannot be done is
 * to cancel a document that sits inside the closed period, because that would
 * change what was filed.
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
 * The lock date, or '' when nothing is locked. Read once per request;
 * erp_lock_set() hands it the date it has just saved.
 *
 * @param string|null $set  Internal: the date just saved
 * @return string  Y-m-d or ''
 */
function erp_lock_date($set = null)
{
    static $date = null;

    if ($set !== null) {
        $date = (string) $set;
    }

    if ($date === null) {
        $value = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_lock_date'))
            ? (string) db_value("SELECT erp_lock_date FROM config LIMIT 1")
            : '';
        $date = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && ($value > '0000-00-00')) ? $value : '';
    }

    return $date;
}

/**
 * Whether a date falls in the locked period.
 *
 * @param string $date  Y-m-d
 * @return bool
 */
function erp_locked($date)
{
    $lock = erp_lock_date();

    return ($lock !== '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && ((string) $date <= $lock);
}

/**
 * Why a date cannot be written to, or '' when it can.
 *
 * @param string $date
 * @return string
 */
function erp_lock_refusal($date)
{
    if (!erp_locked($date)) {
        return '';
    }

    return lang(array(
        'string' => 'The books are locked up to {var:1}, so nothing dated {var:2} can be issued, cancelled or paid. Date it after the lock, or ask whoever keeps the books to move the lock.',
        'vars' => array(
            prepare_form_data_for_output(erp_lock_date(), 'date', false),
            prepare_form_data_for_output((string) $date, 'date', false),
        ),
    ));
}

/**
 * Remember a refusal for erp_db_error(), which is what the callers of the
 * posting functions report when a posting fails.
 *
 * @param string $message
 * @return void
 */
function erp_lock_refused($message)
{
    $GLOBALS['_erp_refusal'] = (string) $message;
}

/**
 * Move the lock. An empty date lifts it.
 *
 * @param string $date     Y-m-d or ''
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_lock_set($date, $user_id = 0)
{
    if (!function_exists('waf_table_has_column') || !waf_table_has_column('config', 'erp_lock_date')) {
        return array('success' => false, 'error' => lang('The period lock comes with the software update; run the update to use it.'));
    }

    $date = trim((string) $date);

    if (($date !== '') && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))) {
        return array('success' => false, 'error' => lang('Enter the lock date as a date.'));
    }

    if (($date !== '') && ($date >= date('Y-m-d'))) {
        return array('success' => false, 'error' => lang('The lock closes a period that is over; choose a date before today.'));
    }

    $before = erp_lock_date();

    if (db("UPDATE config SET erp_lock_date = '" . escape(($date !== '') ? $date : '0000-00-00') . "'") === false) {
        return array('success' => false, 'error' => lang('The lock could not be saved.'));
    }

    erp_lock_date($date);

    if (function_exists('log_activity')) {
        log_activity(($date !== '')
            ? lang(array('string' => 'ERP books locked up to {var:1} (was {var:2}).', 'vars' => array($date, ($before !== '') ? $before : '-')))
            : lang(array('string' => 'ERP period lock lifted (was {var:1}).', 'vars' => ($before !== '') ? $before : '-')));
    }

    return array('success' => true, 'error' => '');
}
