<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Accounting as folded rows (includes/settings/fold.php): the
 * rows' order, names and icons, and each part's one line of state with its
 * pill. The ERP's fields are cut by topic into Documents, Sales, Web orders,
 * Customer accounts, Foreign currency and Reminders and notices; the module's
 * other cards follow as rows of their own. The pg_accounting_fold_<part>()
 * lines are pure but for lang(); includes/settings/accounting.php reads the
 * facts they take.
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
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_accounting_fold_order()
{
    return array(
        'erp', 'erp-sales', 'erp-orders', 'erp-accounts', 'erp-currency', 'erp-notices',
        'invoice', 'erp-rules', 'late-fee', 'efatura-threshold', 'stock-plan', 'erp-templates',
    );
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_accounting_fold_order()
 * @return array name, icon, tone
 */
function pg_accounting_fold_face($key)
{
    switch ($key) {
        case 'erp':
            return array('name' => lang('Documents'), 'icon' => 'bi-file-earmark-text', 'tone' => 'pg-tone-blue');
        case 'erp-sales':
            return array('name' => lang('Sales'), 'icon' => 'bi-shop-window', 'tone' => 'pg-tone-shop');
        case 'erp-orders':
            return array('name' => lang('Web orders'), 'icon' => 'bi-bag-check', 'tone' => 'pg-tone-teal');
        case 'erp-accounts':
            return array('name' => lang('Customer accounts'), 'icon' => 'bi-person-vcard', 'tone' => 'pg-tone-violet');
        case 'erp-currency':
            return array('name' => lang('Foreign currency'), 'icon' => 'bi-currency-exchange', 'tone' => 'pg-tone-amber');
        case 'erp-notices':
            return array('name' => lang('Reminders and notices'), 'icon' => 'bi-bell', 'tone' => 'pg-tone-rose');
        case 'invoice':
            return array('name' => lang('E-Invoice'), 'icon' => 'bi-send-check', 'tone' => 'pg-tone-blue');
        case 'erp-rules':
            return array('name' => lang('Accounting rules'), 'icon' => 'bi-journal-check', 'tone' => 'pg-tone-violet');
        case 'late-fee':
            return array('name' => lang('Late payment fee'), 'icon' => 'bi-hourglass-split', 'tone' => 'pg-tone-rose');
        case 'efatura-threshold':
            return array('name' => lang('e-Fatura threshold'), 'icon' => 'bi-speedometer2', 'tone' => 'pg-tone-amber');
        case 'stock-plan':
            return array('name' => lang('Stock planning'), 'icon' => 'bi-box-seam', 'tone' => 'pg-tone-teal');
        default:
            return array('name' => lang('Document templates'), 'icon' => 'bi-filetype-html', 'tone' => 'pg-tone-gray');
    }
}

/**
 * The On or Off pill of a part one switch turns on.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_accounting_fold_switch_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * Documents' line: the invoice series, how numbers run and the default
 * payment term.
 *
 * @param string $series       the invoice series ('' reads as PGF, as the save does)
 * @param string $number_style gib, year or continuous
 * @param int    $due_days     the default payment term in days
 * @return array line, pill, attention
 */
function pg_accounting_fold_documents($series, $number_style, $due_days)
{
    $series = trim((string) $series);
    $series = ($series !== '') ? $series : 'PGF';
    $due_days = (int) $due_days;

    switch ((string) $number_style) {
        case 'year':
            $numbers = lang('numbers by year');
            break;
        case 'continuous':
            $numbers = lang('numbers never restart');
            break;
        default:
            $numbers = lang('numbers in the GİB shape');
    }

    $due = ($due_days > 0)
        ? lang(array('string' => 'due in {var:1} day{suffix:1}', 'vars' => $due_days, 'suffix' => array(($due_days === 1) ? '' : 's')))
        : lang('due on the invoice date');

    return array(
        'line'      => lang(array('string' => 'Series {var:1}', 'vars' => array($series))) . ' · ' . $numbers . ' · ' . $due,
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Sales' line: how the till shows prices, whether invoices move stock and
 * the walk-in sales account.
 *
 * @param string $till_prices     gross or net
 * @param bool   $stock_documents the Invoices change stock switch
 * @param string $walkin          the walk-in account's name, '' for none
 * @return array line, pill, attention
 */
function pg_accounting_fold_sales($till_prices, $stock_documents, $walkin)
{
    $walkin = trim((string) $walkin);

    return array(
        'line'      => ((($till_prices === 'net') ? lang('Till prices without tax') : lang('Till prices with tax'))
            . ' · ' . ($stock_documents ? lang('invoices change stock') : lang('invoices leave stock alone'))
            . ' · ' . (($walkin !== '') ? $walkin : lang('no walk-in account'))),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Web orders' line: when a web order's invoice is issued, then whether it
 * is e-mailed. Before the upgrade added either, how shipping is taxed.
 *
 * @param string|null $auto_invoice off, order_paid or order_shipped; null before its column
 * @param bool|null   $mail         the invoice e-mail switch; null before its column
 * @param string      $shipping_tax '', included or none
 * @return array line, pill, attention
 */
function pg_accounting_fold_orders($auto_invoice, $mail, $shipping_tax)
{
    $parts = array();

    if ($auto_invoice !== null) {
        if ($auto_invoice === 'order_paid') {
            $parts[] = lang('Invoiced once paid');
        } elseif ($auto_invoice === 'order_shipped') {
            $parts[] = lang('Invoiced once shipped');
        } else {
            $parts[] = lang('Invoiced from the order screen');
        }

        if ($mail) {
            $parts[] = lang('e-mailed to the customer');
        }
    } elseif ($mail) {
        $parts[] = lang('Invoices e-mailed to the customer');
    } elseif ($shipping_tax === 'included') {
        $parts[] = lang('Shipping taxed at the goods\' rate');
    } elseif ($shipping_tax === 'none') {
        $parts[] = lang('Shipping not taxed');
    } else {
        $parts[] = lang('Shipping taxed by the store\'s country');
    }

    return array('line' => implode(' · ', $parts), 'pill' => null, 'attention' => false);
}

/**
 * Customer accounts' line: what a sale past the credit limit does, then
 * account prices in the store and paying on account when they are on.
 *
 * @param string|null $limit_mode   warn or block; null before its column
 * @param bool|null   $store_prices the Account prices in the store switch; null before its column
 * @param bool|null   $on_account   the Put it on my account switch; null before its column
 * @return array line, pill, attention
 */
function pg_accounting_fold_accounts($limit_mode, $store_prices, $on_account)
{
    $parts = array();

    if ($limit_mode !== null) {
        $parts[] = ($limit_mode === 'block') ? lang('Over the limit: refused') : lang('Over the limit: warned');

        if ($store_prices) {
            $parts[] = lang('account prices in the store');
        }

        if ($on_account) {
            $parts[] = lang('on account at checkout');
        }
    } else {
        if ($store_prices) {
            $parts[] = lang('Account prices in the store');
        }

        if ($on_account) {
            $parts[] = empty($parts) ? lang('On account at checkout') : lang('on account at checkout');
        }

        if (empty($parts)) {
            $parts[] = lang('List prices for everyone');
        }
    }

    return array('line' => implode(' · ', $parts), 'pill' => null, 'attention' => false);
}

/**
 * Foreign currency's line: Off with the base currency, or the currencies
 * allowed on documents.
 *
 * @param bool     $on    the foreign-currency switch
 * @param string   $base  the base currency's code
 * @param string[] $codes the currencies ticked, the base left out
 * @return array line, pill, attention
 */
function pg_accounting_fold_currency($on, $base, $codes)
{
    $base = strtoupper(trim((string) $base));
    $allowed = array();

    foreach ((array) $codes as $code) {
        $code = strtoupper(trim((string) $code));

        if (($code !== '') && ($code !== $base) && !in_array($code, $allowed, true)) {
            $allowed[] = $code;
        }
    }

    if (!$on) {
        $line = lang(array('string' => 'Off: everything in {var:1}', 'vars' => array($base)));
    } elseif (empty($allowed)) {
        $line = lang(array('string' => 'No currency picked beside {var:1}', 'vars' => array($base)));
    } else {
        $line = implode(', ', $allowed);
    }

    return array('line' => $line, 'pill' => pg_accounting_fold_switch_pill((bool) $on), 'attention' => false);
}

/**
 * Reminders and notices' line: when an overdue receivable is announced, or
 * No reminder, then how many notices as they happen are on.
 *
 * @param int      $days      the reminder threshold in days (0: off)
 * @param string   $frequency daily or weekly
 * @param int      $hour      0 to 23
 * @param int|null $notices   how many notices are switched on; null before their columns
 * @return array line, pill, attention
 */
function pg_accounting_fold_notices($days, $frequency, $hour, $notices)
{
    $days = (int) $days;
    $at = sprintf('%02d:00', min(23, max(0, (int) $hour)));

    if ($days > 0) {
        $line = ($frequency === 'weekly')
            ? lang(array('string' => 'Overdue after {var:1} day{suffix:1}, weekly at {var:2}', 'vars' => array($days, $at), 'suffix' => array(($days === 1) ? '' : 's')))
            : lang(array('string' => 'Overdue after {var:1} day{suffix:1}, daily at {var:2}', 'vars' => array($days, $at), 'suffix' => array(($days === 1) ? '' : 's')));
    } else {
        $line = lang('No reminder');
    }

    if ($notices !== null) {
        $notices = (int) $notices;
        $line .= ' · ' . (($notices > 0)
            ? lang(array('string' => '{var:1} notice{suffix:1} on', 'vars' => $notices, 'suffix' => array(($notices === 1) ? '' : 's')))
            : lang('no notice on'));
    }

    return array('line' => $line, 'pill' => pg_accounting_fold_switch_pill($days > 0), 'attention' => false);
}

/**
 * E-Invoice's line: the provider in use and its connection. A provider
 * picked and not connected asks for attention.
 *
 * @param string $provider  the provider's name, '' for none
 * @param bool   $connected
 * @return array line, pill, attention
 */
function pg_accounting_fold_edoc($provider, $connected)
{
    $provider = trim((string) $provider);

    if ($provider === '') {
        return array('line' => lang('No e-document provider'), 'pill' => pg_accounting_fold_switch_pill(false), 'attention' => false);
    }

    if (!$connected) {
        return array(
            'line'      => lang(array('string' => '{var:1} is not connected', 'vars' => array($provider))),
            'pill'      => array(lang('Not connected'), 'pg-tone-amber'),
            'attention' => true,
        );
    }

    return array(
        'line'      => lang(array('string' => '{var:1} · connected', 'vars' => array($provider))),
        'pill'      => pg_accounting_fold_switch_pill(true),
        'attention' => false,
    );
}

/**
 * Accounting rules' line: how withheld VAT is reported and the exemption
 * code, where the rules are offered.
 *
 * @param bool   $editable  erp_settings_rules_editable()
 * @param bool   $net       withheld VAT taken off the calculated VAT
 * @param string $code      the exemption code, '' for none
 * @return array line, pill, attention
 */
function pg_accounting_fold_rules($editable, $net, $code)
{
    if (!$editable) {
        return array('line' => lang('VAT taken back is set with the expense categories'), 'pill' => null, 'attention' => false);
    }

    $code = trim((string) $code);

    return array(
        'line'      => ($net ? lang('Withheld VAT taken off') : lang('Withheld VAT shown beside'))
            . ' · ' . (($code !== '') ? lang(array('string' => 'exemption code {var:1}', 'vars' => array($code))) : lang('no exemption code')),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Late payment fee's line: the monthly rate, or No fee.
 *
 * @param float  $rate  the monthly rate
 * @param string $shown the rate as the card writes it
 * @return array line, pill, attention
 */
function pg_accounting_fold_late_fee($rate, $shown)
{
    $on = ((float) $rate > 0);

    return array(
        'line'      => $on ? lang(array('string' => '{var:1}% a month', 'vars' => array((string) $shown))) : lang('No fee'),
        'pill'      => pg_accounting_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * e-Fatura threshold's line: the two amounts System Status warns at; 0
 * switches a warning off.
 *
 * @param int    $web          the internet sales amount in minor units
 * @param int    $general      the all sales amount in minor units
 * @param string $web_shown    the internet sales amount as written
 * @param string $general_shown the all sales amount as written
 * @return array line, pill, attention
 */
function pg_accounting_fold_threshold($web, $general, $web_shown, $general_shown)
{
    $web = (int) $web;
    $general = (int) $general;

    if (($web <= 0) && ($general <= 0)) {
        return array('line' => lang('No warning'), 'pill' => pg_accounting_fold_switch_pill(false), 'attention' => false);
    }

    return array(
        'line'      => lang(array('string' => 'Internet sales {var:1} · all sales {var:2}', 'vars' => array(
            ($web > 0) ? (string) $web_shown : lang('off'),
            ($general > 0) ? (string) $general_shown : lang('off'),
        ))),
        'pill'      => pg_accounting_fold_switch_pill(true),
        'attention' => false,
    );
}

/**
 * Stock planning's line: the safety, cover and dead-stock days.
 *
 * @param int $safety
 * @param int $cover
 * @param int $dead
 * @return array line, pill, attention
 */
function pg_accounting_fold_stock_plan($safety, $cover, $dead)
{
    return array(
        'line'      => lang(array('string' => 'Safety {var:1} days · cover {var:2} days · dead stock after {var:3} days', 'vars' => array((int) $safety, (int) $cover, (int) $dead))),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Document templates' line: how many documents print a saved template.
 *
 * @param int $saved the documents with a saved template
 * @param int $total the documents listed
 * @return array line, pill, attention
 */
function pg_accounting_fold_templates($saved, $total)
{
    $saved = (int) $saved;

    return array(
        'line'      => ($saved > 0)
            ? lang(array('string' => '{var:1} of {var:2} documents print a saved template', 'vars' => array($saved, (int) $total)))
            : lang('Every document prints the built-in template'),
        'pill'      => null,
        'attention' => false,
    );
}

/* ---------------------------------------------------------------------------
   Inside the rows: the pane's switch and radio rows
   --------------------------------------------------------------------------- */

/**
 * A pg-switch with its label and at most one line of help, as every switch
 * inside an Accounting row is drawn. Pure.
 *
 * @param string $name  the field's name and id
 * @param bool   $on    it is on
 * @param string $label plain text
 * @param string $help  plain text, '' for none
 * @return string HTML, a full-width cell
 */
function pg_accounting_settings_switch($name, $on, $label, $help = '')
{
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-5">
                            <input type="checkbox" class="pg-switch" value="1"' . ($on ? ' checked="checked"' : '') . ' id="' . h($name) . '" name="' . h($name) . '"' . (($help !== '') ? ' aria-describedby="' . h($name) . '_help"' : '') . '/>
                            <label class="pg-text-2" for="' . h($name) . '">' . h($label) . '</label>
                        </div>' . (($help !== '') ? '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="' . h($name) . '_help">' . h($help) . '</p>' : '') . '
                    </div>';
}

/**
 * A choice of a few as radio rows under their legend, each with its one
 * line, posting the values the select did; the choice's own line follows.
 * A radio's id is the name and its value, "default" for the empty one. Pure.
 *
 * @param string $name    the radios' name
 * @param string $legend  plain text
 * @param array  $options each value, label and line (plain text, '' for none), on
 * @param string $help    plain text, '' for none
 * @return string HTML, a full-width cell
 */
function pg_accounting_settings_choice($name, $legend, $options, $help = '')
{
    $rows = '';

    foreach ($options as $option) {
        $id = $name . '_' . (($option['value'] !== '') ? $option['value'] : 'default');
        $rows .= '
                            <div class="form-field">
                                <input value="' . h($option['value']) . '" class="radio" type="radio" id="' . h($id) . '" name="' . h($name) . '"' . (($option['line'] !== '') ? ' aria-describedby="' . h($id) . '_help"' : '') . ($option['on'] ? ' checked="checked"' : '') . '/>
                                <label for="' . h($id) . '">' . h($option['label']) . '</label>
                            </div>' . (($option['line'] !== '') ? '
                            <div class="form-text mb-3" id="' . h($id) . '_help">' . h($option['line']) . '</div>' : '');
    }

    return '
                    <div class="col-12">
                        <fieldset' . (($help !== '') ? ' aria-describedby="' . h($name) . '_help"' : '') . '><legend class="form-label pg-field-label">' . h($legend) . '</legend>' . $rows . '
                        </fieldset>' . (($help !== '') ? '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="' . h($name) . '_help">' . h($help) . '</p>' : '') . '
                    </div>';
}
