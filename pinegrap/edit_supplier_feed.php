<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Add and edit a supplier feed on the edit layout. Cards: Feed (name,
 * address, format, interval), Sign-in (one way at a time, the http box
 * folded under Advanced), and after the first read Prices (currency, VAT,
 * the ERP supplier account, the price rule and its rules by category or
 * brand) and Fields (the record path and a select of the feed's fields for
 * each product field, with a sample value). Held prices wait in their own
 * card above them for Accept or Reject. The status column holds the state,
 * the counts, Pull now, Test pull, the pull history and Delete. A test
 * pull's figures and rows open in a drawer; nothing is written by it.
 * Helpers: includes/fn/supplier_feed_screen.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The form was larger than this server accepts. Nothing was saved.'));
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_screen.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!pg_supplier_feed_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_supplier_feed');
$feed_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_supplier_feed.php';

// A row's Pull now, Test pull and Delete on the list post the feed's id as
// the button's value; the buttons here do the same.
$feed_id = (int) ($_POST['pull_now'] ?? $_POST['test_pull'] ?? $_POST['submit_delete'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
$feed = ($feed_id > 0) ? pg_supplier_feed_get($feed_id) : null;

if (($feed_id > 0) && ($feed === null)) {
    output_error(lang('The supplier feed was not found.'), 404);
}

$feed_secret = ($feed !== null) ? pg_supplier_feed_secret_decode($feed['secret']) : array();
$feed_mapping = pg_supplier_feed_mapping_decode(($feed !== null) ? $feed['mapping'] : null);
$feed_rules = pg_supplier_feed_rules_decode(($feed !== null) ? $feed['rules'] : null);
$feed_input = null;
$feed_fields_posted = null;
$feed_test = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (($feed !== null) && isset($_POST['submit_delete'])) {
        pg_supplier_feed_delete($feed_id);
        log_activity('supplier feed "' . $feed['name'] . '" was deleted');
        $list_form = new liveform('view_supplier_feeds');
        $list_form->add_notice(h(lang(array('string' => 'The supplier feed "{var:1}" was deleted. Its products stay and no longer follow it.', 'vars' => array($feed['name'])))));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_supplier_feeds.php');
    }

    if (($feed !== null) && isset($_POST['held_accept_all'])) {
        $accepted = pg_supplier_feed_held_accept_all($feed_id);
        log_activity('supplier feed "' . $feed['name'] . '": ' . $accepted . ' held buy prices were accepted');
        $liveform->add_notice(h(lang(array('string' => '{var:1} new buy prices were accepted. The selling prices follow at the next pull.', 'vars' => array($accepted)))));
        go($feed_self . '?id=' . $feed_id);
    }

    if (($feed !== null) && (isset($_POST['held_accept']) || isset($_POST['held_reject']))) {
        $accept = isset($_POST['held_accept']);

        if (pg_supplier_feed_held_decide($feed_id, (int) ($accept ? $_POST['held_accept'] : $_POST['held_reject']), $accept)) {
            log_activity('supplier feed "' . $feed['name'] . '": a held buy price was ' . ($accept ? 'accepted' : 'rejected'));
            $liveform->add_notice(h($accept ? lang('The new buy price was accepted. The selling price follows at the next pull.') : lang('The new buy price was rejected. The last one stays.')));
        }

        go($feed_self . '?id=' . $feed_id);
    }

    if (($feed !== null) && isset($_POST['pull_now'])) {
        if (!$feed_mapping['confirmed']) {
            $liveform->add_error(h(lang('Save the fields first, then pull.')));
            go($feed_self . '?id=' . $feed_id);
        }

        $pull = pg_supplier_feed_pull_now($feed);
        $run = $pull['run'];
        log_activity('supplier feed "' . $feed['name'] . '" was pulled by hand');

        if ($pull['result'] === 'locked') {
            $liveform->add_notice(h(lang('A pull of this feed is running already.')));
        } elseif ($pull['result'] === 'running') {
            $liveform->add_notice(h(lang('The pull started and goes on in the background.')));
        } elseif (($pull['result'] === 'done') && is_array($run)) {
            $liveform->add_notice(h(lang(array('string' => 'The pull is done: {var:1} changed, {var:2} closed, {var:3} reopened, {var:4} held.', 'vars' => array((int) $run['changed'], (int) $run['closed'], (int) $run['reopened'], (int) $run['held'])))));
        } elseif (($pull['result'] === 'braked') && is_array($run)) {
            $liveform->add_error(h(lang(array('string' => 'The pull stopped: {var:1}', 'vars' => array(pg_supplier_feed_detail_reason($run['detail']))))));
        } else {
            $liveform->add_error(h(lang('The feed is off. Save its fields to switch it on.')));
        }

        go($feed_self . '?id=' . $feed_id);
    }

    if (($feed !== null) && isset($_POST['test_pull'])) {
        // Drawn on this answer, not kept: a test pull writes nothing.
        $feed_test = $feed_mapping['confirmed']
            ? pg_supplier_feed_test_pull($feed)
            : array('ok' => false, 'error' => lang('Save the fields first, then try a test pull.'));

        if (!$feed_test['ok']) {
            $liveform->add_error(h($feed_test['error']));
            $feed_test = null;
        }
    } elseif (isset($_POST['read_feed']) || isset($_POST['submit_save'])) {
        $feed_input = pg_supplier_feed_input($_POST, $feed_secret);
        $errors = $feed_input['errors'];
        $has_keys = ($feed_mapping['keys'] !== array());
        $posted_path = isset($_POST['record_path']) ? (string) $_POST['record_path'] : null;
        $path_changed = $has_keys && ($posted_path !== null) && ($posted_path !== $feed_mapping['record_path']) && in_array($posted_path, $feed_mapping['paths'], true);
        $save_fields = isset($_POST['submit_save']) && $has_keys && !$path_changed;

        if ($save_fields) {
            $feed_fields_posted = pg_supplier_feed_mapping_input((array) ($_POST['field'] ?? array()), $feed_mapping['keys']);
            $errors = array_merge($errors, $feed_fields_posted['errors']);
        }

        // The price rule sits in the prices card, drawn once the feed was read.
        $rules_posted = $has_keys ? pg_supplier_feed_rules_input($_POST) : null;

        if ($rules_posted !== null) {
            $errors = array_merge($errors, $rules_posted['errors']);
            $feed_rules = pg_supplier_feed_rules_decode(json_encode($rules_posted['rules']));
        }

        if (!$errors) {
            $columns = $feed_input['feed'];
            $columns['secret'] = pg_supplier_feed_secret_encode($feed_input['secret']);

            // The prices card is drawn only once the feed was read.
            if (!$has_keys) {
                unset($columns['currency'], $columns['vat_included'], $columns['erp_account_id']);
            } else {
                $columns['rules'] = json_encode($rules_posted['rules']);
            }

            if ($save_fields) {
                $mapping = $feed_mapping;
                $mapping['fields'] = $feed_fields_posted['fields'];
                $mapping['confirmed'] = 1;
                $columns['mapping'] = json_encode($mapping);

                // Saving the fields switches an off feed on, and lets a feed
                // a brake paused go on (the sync, supplier_feed_sync.php).
                if ((int) $feed['status'] !== 1) {
                    $columns['status'] = 1;
                    $columns['pause_reason'] = '';
                }
            }

            $saved_id = pg_supplier_feed_save($feed_id, $columns, (int) $user['id']);

            if ($saved_id <= 0) {
                $liveform->add_error(h(lang('The supplier feed could not be saved.')));
                go($feed_self . (($feed_id > 0) ? '?id=' . $feed_id : ''));
            }

            log_activity('supplier feed "' . $columns['name'] . '" was ' . (($feed_id > 0) ? 'modified' : 'created'));

            if (isset($_POST['read_feed']) || $path_changed) {
                $saved = pg_supplier_feed_get($saved_id);
                $read = pg_supplier_feed_first_read($saved, $path_changed ? $posted_path : (($feed_mapping['record_path'] !== '') ? $feed_mapping['record_path'] : null));

                if ($read['ok']) {
                    // New fields wait for a person's Save before a pull uses them.
                    pg_supplier_feed_save($saved_id, array('mapping' => json_encode($read['mapping']), 'status' => ((int) $saved['status'] === 1) ? 0 : (int) $saved['status']), (int) $user['id']);
                    $liveform->add_notice(h(lang(array('string' => 'The feed was read: {var:1} records. Check the fields and save.', 'vars' => array($read['mapping']['records'])))));
                } else {
                    $liveform->add_error(h($read['error']));
                }
            } else {
                $liveform->add_notice(h(lang('The supplier feed was saved.')));
            }

            go($feed_self . '?id=' . $saved_id);
        }

        foreach ($errors as $error) {
            $liveform->add_error(h($error));
        }
    }
}

// What the form shows: the posted values after a refused save, else the
// stored feed. Secret boxes are always drawn empty.
$values = ($feed !== null) ? $feed : array('name' => '', 'format' => '', 'interval_hours' => 1, 'currency' => '', 'vat_included' => 0, 'erp_account_id' => 0, 'allow_http' => 0);
$values['auth'] = $feed_secret['auth'] ?? 'none';

if ($feed_input !== null) {
    $values = array_merge($values, $feed_input['feed']);
    $values['auth'] = $feed_input['secret']['auth'];
}

$fields_shown = ($feed_fields_posted !== null) ? $feed_fields_posted['fields'] : $feed_mapping['fields'];
$has_keys = ($feed_mapping['keys'] !== array());
$title = ($feed !== null) ? $feed['name'] : lang('Add supplier feed');

$option = function ($value, $label, $chosen) {
    return '<option value="' . h($value) . '"' . (((string) $value === (string) $chosen) ? ' selected' : '') . '>' . h($label) . '</option>';
};

// A saved secret box says so under it; its value is never drawn.
// The key box is drawn under two ways, so an id carries the way.
$text_field = function ($name, $label, $type = 'text', $help = '', $class = 'col-12', $way = '') use ($feed_secret) {
    $id = 'sf_' . (($way !== '') ? $way . '_' : '') . $name;
    $saved = isset($feed_secret[$name]) && ((string) $feed_secret[$name] !== '');
    $help = $saved ? lang('Saved. Leave empty to keep it.') : $help;

    return '<div class="' . $class . '"><label class="pg-field-label" for="' . $id . '">' . h($label) . '</label>'
        . '<input type="' . $type . '" class="pg-input" id="' . $id . '" name="' . $name . '" value="" autocomplete="off"' . (($help !== '') ? ' aria-describedby="' . $id . '_help"' : '') . ' />'
        . (($help !== '') ? '<div class="pg-field-help" id="' . $id . '_help">' . h($help) . '</div>' : '') . '</div>';
};

// Card 1: the feed.
$format_options = '';

foreach (array('' => lang('Automatic'), 'xml' => 'XML', 'csv' => 'CSV', 'json' => 'JSON') as $format => $label) {
    $format_options .= $option($format, $label, $values['format']);
}

$interval_options = '';

foreach (pg_supplier_feed_intervals() as $hours) {
    $interval_options .= $option($hours, ($hours === 1) ? lang('Every hour') : lang(array('string' => 'Every {var:1} hours', 'vars' => array($hours))), $values['interval_hours']);
}

$output_feed_card = '
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Feed')) . '</h2>
                            <div class="card-body pt-0">
                                <p class="pg-card-sub">' . h(lang('Where the supplier\'s list is and how often it is read.')) . '</p>
                                <div class="row gy-5">
                                    <div class="col-12">
                                        <label class="pg-field-label" for="sf_name">' . h(lang('Name')) . '</label>
                                        <input type="text" class="pg-input" id="sf_name" name="name" maxlength="150" required value="' . h($values['name']) . '" />
                                    </div>
                                    ' . $text_field('address', lang('Address'), 'url', lang('The feed\'s full address, starting with https://.')) . '
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_format">' . h(lang('Format')) . '</label>
                                        <select class="pg-input" id="sf_format" name="format">' . $format_options . '</select>
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_interval">' . h(lang('Pulled')) . '</label>
                                        <select class="pg-input" id="sf_interval" name="interval_hours">' . $interval_options . '</select>
                                    </div>
                                </div>
                            </div>
                        </div>';

// Card 2: sign-in, one way at a time. The fields of the other ways are
// disabled, so they are not sent.
$auth_buttons = '';

foreach (pg_supplier_feed_auth_ways() as $way => $label) {
    $auth_buttons .= '<button type="button" class="pg-seg-item" data-pg-sf-auth="' . $way . '" aria-pressed="' . (($values['auth'] === $way) ? 'true' : 'false') . '">' . h($label) . '</button>';
}

$auth_group = function ($way, $html) use ($values) {
    $shown = ($values['auth'] === $way);

    return '<div class="row gy-5' . ($shown ? '' : ' d-none') . '" data-pg-sf-way="' . $way . '">' . $html . '</div>';
};

$output_sign_in_card = '
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Sign-in')) . '</h2>
                            <div class="card-body pt-0">
                                <p class="pg-card-sub">' . h(lang('How the supplier lets you read the list. What you enter is kept encrypted and never shown again.')) . '</p>
                                <input type="hidden" name="auth" id="sf_auth" value="' . h($values['auth']) . '" />
                                <div class="mb-5"><span class="pg-seg" role="group" aria-label="' . h(lang('Sign-in')) . '">' . $auth_buttons . '</span></div>
                                ' . $auth_group('basic', $text_field('user', lang('User name'), 'text', '', 'col-12 md:col-6', 'basic') . $text_field('password', lang('Password'), 'password', '', 'col-12 md:col-6', 'basic')) . '
                                ' . $auth_group('header', $text_field('header_name', lang('Header name'), 'text', lang('For example X-Api-Key.'), 'col-12 md:col-6', 'header') . $text_field('key', lang('Key'), 'password', '', 'col-12 md:col-6', 'header')) . '
                                ' . $auth_group('query', $text_field('key_param', lang('Name in the address'), 'text', lang('For example apikey, as in ?apikey=…'), 'col-12 md:col-6', 'query') . $text_field('key', lang('Key'), 'password', '', 'col-12 md:col-6', 'query')) . '
                                <button type="button" class="pg-gather-toggle mt-5" aria-expanded="' . ($values['allow_http'] ? 'true' : 'false') . '" aria-controls="sf_advanced" data-pg-sf-fold>' . h(lang('Advanced')) . '<span class="bi bi-chevron-down" aria-hidden="true"></span></button>
                                <div id="sf_advanced" class="mt-3' . ($values['allow_http'] ? '' : ' d-none') . '">
                                    <div class="d-flex align-items-start gap-3">
                                        <input type="checkbox" class="pg-checkbox" id="sf_allow_http" name="allow_http" value="1"' . ($values['allow_http'] ? ' checked' : '') . ' aria-describedby="sf_allow_http_help" />
                                        <div><label class="pg-text-2" for="sf_allow_http">' . h(lang('Allow an address without https')) . '</label>
                                        <div class="pg-field-help" id="sf_allow_http_help">' . h(lang('Without https anyone on the way can read the list and its key; only a key in the address is sent.')) . '</div></div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// Card 3: prices and the price rule, once the feed was read.
$output_prices_card = '';
$home_currency = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';

if ($has_keys) {
    $currency_options = $option('', lang('The store\'s currency'), $values['currency']);

    foreach ((array) db_items("SELECT code, name FROM currencies ORDER BY code") as $currency) {
        $currency_options .= $option($currency['code'], $currency['code'] . ' · ' . $currency['name'], $values['currency']);
    }

    $erp_accounts = pg_supplier_feed_erp_accounts();
    $erp_field = '';

    if (defined('ERP_ENABLED') && ERP_ENABLED) {
        $erp_options = $option(0, lang('None'), $values['erp_account_id']);

        foreach ($erp_accounts as $account_id => $account_title) {
            $erp_options .= $option($account_id, $account_title, $values['erp_account_id']);
        }

        $erp_field = '
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_erp_account">' . h(lang('Supplier account')) . '</label>
                                        <select class="pg-input" id="sf_erp_account" name="erp_account_id" aria-describedby="sf_erp_account_help">' . $erp_options . '</select>
                                        <div class="pg-field-help" id="sf_erp_account_help">' . h(lang('The account the stock plan names when no purchase invoice does.')) . '</div>
                                    </div>';
    }

    $output_prices_card = '
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Prices')) . '</h2>
                            <div class="card-body pt-0">
                                <p class="pg-card-sub">' . h(lang('How the feed\'s buy prices are read and turned into your selling prices. With no margin, only stock follows the feed.')) . '</p>
                                <div class="row gy-5">
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_currency">' . h(lang('The feed\'s currency')) . '</label>
                                        <select class="pg-input" id="sf_currency" name="currency">' . $currency_options . '</select>
                                    </div>' . $erp_field . '
                                    <div class="col-12 d-flex align-items-start gap-3">
                                        <input type="checkbox" class="pg-switch" id="sf_vat_included" name="vat_included" value="1"' . ($values['vat_included'] ? ' checked' : '') . ' />
                                        <label class="pg-text-2" for="sf_vat_included">' . h(lang('The feed\'s prices include VAT')) . '</label>
                                    </div>
                                </div>' . pg_supplier_feed_rule_fields_html($feed_rules, ($values['currency'] !== '') ? $values['currency'] : $home_currency) . '
                            </div>
                        </div>';
}

// Card 4: the fields, a row a product field with the feed's fields to
// choose from and the first record's value.
$output_fields_card = '';

if ($has_keys) {
    $field_row = function ($field, $label) use ($feed_mapping, $fields_shown, $option) {
        $chosen = $fields_shown[$field];
        $options = $option('', lang('Not in the feed'), $chosen);

        foreach ($feed_mapping['keys'] as $key => $sample) {
            $options .= '<option value="' . h($key) . '" data-sample="' . h($sample) . '"' . (((string) $key === $chosen) ? ' selected' : '') . '>' . h($key) . '</option>';
        }

        $suggested = (!$feed_mapping['confirmed'] && ($chosen !== '')) ? ' <span class="pg-pill pg-tone-blue" data-pg-sf-suggested>' . h(lang('Suggested')) . '</span>' : '';
        $sample = ($chosen !== '') ? (string) ($feed_mapping['keys'][$chosen] ?? '') : '';

        return '
                                    <div class="row gy-2 align-items-center mb-3">
                                        <div class="col-12 md:col-3"><label class="pg-field-label mb-0" for="sf_field_' . $field . '">' . h($label) . '</label>' . $suggested . '</div>
                                        <div class="col-12 md:col-4"><select class="pg-input" id="sf_field_' . $field . '" name="field[' . $field . ']" data-pg-sf-field>' . $options . '</select></div>
                                        <div class="col-12 md:col-5 pg-text-meta text-truncate" data-pg-sf-sample>' . h($sample) . '</div>
                                    </div>';
    };

    $required_rows = '';
    $more_rows = '';

    foreach (pg_supplier_feed_field_labels() as $field => $label) {
        if (in_array($field, pg_supplier_feed_required_fields(), true)) {
            $required_rows .= $field_row($field, $label);
        } else {
            $more_rows .= $field_row($field, $label);
        }
    }

    $path_field = '';

    if (in_array($feed_mapping['format'], array('xml', 'json'), true) && ($feed_mapping['paths'] !== array())) {
        $path_options = '';

        foreach ($feed_mapping['paths'] as $path) {
            $path_options .= $option($path, ($path === '') ? lang('The whole list') : $path, $feed_mapping['record_path']);
        }

        $path_field = '
                                <div class="row mb-5">
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_record_path">' . h(lang('Records')) . '</label>
                                        <select class="pg-input" id="sf_record_path" name="record_path" aria-describedby="sf_record_path_help">' . $path_options . '</select>
                                    </div>
                                    <div class="col-12 pg-field-help" id="sf_record_path_help">' . h(lang('Where each product sits in the feed. Another choice reads the feed again when you save.')) . '</div>
                                </div>';
    }

    $output_fields_card = '
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Fields')) . '</h2>
                            <div class="card-body pt-0">
                                <p class="pg-card-sub">' . h(lang(array('string' => 'Which of the feed\'s fields is which. Read from {var:1} records on {var:2}.', 'vars' => array($feed_mapping['records'], get_absolute_time(array('timestamp' => $feed_mapping['read_at'], 'format' => 'plain_text')))))) . '</p>
                                ' . $path_field . $required_rows . '
                                <button type="button" class="pg-gather-toggle" aria-expanded="false" aria-controls="sf_more_fields" data-pg-sf-fold>' . h(lang('More fields')) . '<span class="bi bi-chevron-down" aria-hidden="true"></span></button>
                                <div id="sf_more_fields" class="mt-3 d-none">' . $more_rows . '
                                </div>
                                <div class="mt-5"><button type="submit" name="read_feed" value="1" formnovalidate class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Read the feed again')) . '</button></div>
                            </div>
                        </div>';
}

// A test pull's result opens in a drawer as the page loads.
$output_test = ($feed_test !== null)
    ? pg_supplier_feed_test_drawer_html($feed_test, pg_supplier_feed_pull_button($feed, $feed_mapping, 'pg-button pg-button-fill'), ($feed['currency'] !== '') ? $feed['currency'] : $home_currency, $home_currency)
    : '';

// Buy prices held for a person's yes, above the cards.
$output_held = ($feed !== null) ? pg_supplier_feed_held_html(pg_supplier_feed_held_items($feed_id), ($feed['currency'] !== '') ? $feed['currency'] : $home_currency) : '';
$output_runs = ($feed !== null) ? pg_supplier_feed_runs_drawer_html(pg_supplier_feed_runs($feed_id)) : '';

// The status column of a saved feed.
$output_status = '';

if ($feed !== null) {
    $run = pg_supplier_feed_last_run($feed_id);
    $state = pg_supplier_feed_state($feed, $run);
    $when = function ($timestamp) {
        return h(get_absolute_time(array('timestamp' => (int) $timestamp, 'format' => 'plain_text')));
    };

    if ((int) $feed['status'] === 1) {
        $next = ((int) $feed['next_run_at'] > time()) ? $when($feed['next_run_at']) : h(lang('At the next scheduled run'));
    } else {
        $next = h($feed_mapping['confirmed'] ? lang('Not planned') : lang('After the fields are saved'));
    }

    $output_status = '
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Status')) . '</h2>
                                <div class="card-body pt-0">
                                    <p><span class="pg-pill pg-tone-' . $state['tone'] . '">' . h($state['label']) . '</span>' . (($state['note'] !== '') ? ' <span class="pg-text-meta">' . h($state['note']) . '</span>' : '') . '</p>
                                    <dl class="pg-kv">
                                        <dt>' . h(lang('Host')) . '</dt><dd>' . h($feed['host']) . '</dd>
                                        <dt>' . h(lang('Format')) . '</dt><dd>' . h(($feed_mapping['format'] !== '') ? strtoupper($feed_mapping['format']) : lang('Not read yet')) . '</dd>
                                        <dt>' . h(lang('Last pull')) . '</dt><dd>' . (($run !== null) ? $when($run['started_at']) : h(lang('Never'))) . '</dd>
                                        <dt>' . h(lang('Next pull')) . '</dt><dd>' . $next . '</dd>
                                        <dt>' . h(lang('Products matched')) . '</dt><dd class="pg-tabular">' . h(pg_format_number(pg_supplier_feed_matched_count($feed_id), 0)) . '</dd>
                                    </dl>
                                    <div class="mt-5 d-flex flex-wrap gap-3">' . pg_supplier_feed_pull_button($feed, $feed_mapping, 'pg-button pg-button-quiet')
                                        . ($feed_mapping['confirmed'] ? '<button type="submit" name="test_pull" value="' . $feed_id . '" formnovalidate class="pg-button pg-button-quiet">' . h(lang('Test pull')) . '</button>' : '')
                                        . '<button type="button" class="pg-button pg-button-quiet" data-bs-toggle="drawer" data-bs-target="#sf_runs" aria-controls="sf_runs"><i class="bi bi-clock-history" aria-hidden="true"></i>' . h(lang('Pull history')) . '</button></div>
                                    <div class="mt-5 pt-3 border-top"><button type="submit" name="submit_delete" value="' . $feed_id . '" formnovalidate class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The feed and its pull history are deleted. Its products stay and no longer follow it.')) . '"><i class="bi bi-trash" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></div>
                                </div>
                            </div>
                        </div>';
}

// The save bar: a feed not read yet reads it; a read one saves its fields.
$output_buttons = $has_keys
    ? '<button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill"><i class="bi bi-check2" aria-hidden="true"></i>' . h(lang('Save')) . '</button>'
    : '<button type="submit" name="read_feed" value="1" class="pg-button pg-button-fill"><i class="bi bi-cloud-download" aria-hidden="true"></i>' . h(lang('Read the feed')) . '</button>'
        . (($feed !== null) ? '<button type="submit" name="submit_save" value="1" class="pg-button pg-button-quiet">' . h(lang('Save')) . '</button>' : '');
$output_buttons .= '<a class="pg-button pg-button-quiet" href="view_supplier_feeds.php">' . h(lang('Cancel')) . '</a>';

echo pg_page_shell(array(
    'title' => $title,
    'extra classes' => 'supplier-feeds pg-measure-edit',
    'icon' => 'store',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($title),
    'heading_description' => lang('A supplier\'s product list: where it is, how to sign in, and which of its fields is which.'),
    'breadcrumb' => array(
        array('label' => lang('Supplier feeds'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_supplier_feeds.php'),
        array('label' => $title),
    ),
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            <form name="form" action="edit_supplier_feed.php" method="post" id="supplier_feed_form">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $feed_id . '" />
                <button type="submit" name="' . ($has_keys ? 'submit_save' : 'read_feed') . '" value="1" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $output_held . $output_feed_card . $output_sign_in_card . $output_prices_card . $output_fields_card . '
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">' . $output_status . '
                    </div>' . $output_test . $output_runs . '
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="d-flex flex-wrap justify-content-center gap-3">' . $output_buttons . '</div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
    // Sign-in: one way at a time, the other ways\' fields hidden and not
    // sent. Folds: Advanced and More fields. Fields: the sample follows the
    // chosen field and a changed choice is no longer "Suggested". Price
    // rule: the rounding segments fill their hidden box; "Add a rule" copies
    // the row template, a row\'s cross removes it. A test pull\'s drawer
    // opens as the page loads.
    (function () {
        var form = document.getElementById(\'supplier_feed_form\');

        function showWay(way) {
            document.getElementById(\'sf_auth\').value = way;
            form.querySelectorAll(\'[data-pg-sf-auth]\').forEach(function (button) {
                button.setAttribute(\'aria-pressed\', (button.getAttribute(\'data-pg-sf-auth\') === way) ? \'true\' : \'false\');
            });
            form.querySelectorAll(\'[data-pg-sf-way]\').forEach(function (group) {
                var shown = (group.getAttribute(\'data-pg-sf-way\') === way);
                group.classList.toggle(\'d-none\', !shown);
                group.querySelectorAll(\'input\').forEach(function (input) {
                    input.disabled = !shown;
                });
            });
        }

        form.addEventListener(\'click\', function (event) {
            var way = event.target.closest(\'[data-pg-sf-auth]\');
            var fold = event.target.closest(\'[data-pg-sf-fold]\');

            var round = event.target.closest(\'[data-pg-sf-round]\');
            var add = event.target.closest(\'[data-pg-sf-rule-add]\');
            var remove = event.target.closest(\'[data-pg-sf-rule-remove]\');

            if (round) {
                document.getElementById(\'sf_rule_rounding\').value = round.getAttribute(\'data-pg-sf-round\');
                form.querySelectorAll(\'[data-pg-sf-round]\').forEach(function (button) {
                    button.setAttribute(\'aria-pressed\', (button === round) ? \'true\' : \'false\');
                });
            } else if (add) {
                var rows = form.querySelector(\'[data-pg-sf-rules]\');
                rows.appendChild(form.querySelector(\'[data-pg-sf-rule-template]\').content.cloneNode(true));
                rows.lastElementChild.querySelector(\'input\').focus();
            } else if (remove) {
                remove.closest(\'[data-pg-sf-rule-row]\').remove();
                form.querySelector(\'[data-pg-sf-rule-add]\').focus();
            } else if (way) {
                showWay(way.getAttribute(\'data-pg-sf-auth\'));
            } else if (fold) {
                var open = (fold.getAttribute(\'aria-expanded\') !== \'true\');
                fold.setAttribute(\'aria-expanded\', open ? \'true\' : \'false\');
                document.getElementById(fold.getAttribute(\'aria-controls\')).classList.toggle(\'d-none\', !open);
            }
        });

        form.addEventListener(\'change\', function (event) {
            if (!event.target.matches(\'[data-pg-sf-field]\')) {
                return;
            }

            var row = event.target.closest(\'.row\');
            var option = event.target.options[event.target.selectedIndex];
            var pill = row.querySelector(\'[data-pg-sf-suggested]\');
            row.querySelector(\'[data-pg-sf-sample]\').textContent = option ? (option.getAttribute(\'data-sample\') || \'\') : \'\';

            if (pill) {
                pill.remove();
            }
        });

        showWay(document.getElementById(\'sf_auth\').value);

        var test = document.querySelector(\'[data-pg-sf-open-at-load]\');

        if (test && window.pgUI) {
            window.pgUI.ready(function () {
                window.pgUI.drawer.show(test);
            });
        }
    }());
</script>
' . output_footer();

$liveform->remove_form();
