<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The İYS card of Site Settings › Communication: the account (API user, its
 * password sealed with the site key, İYS code, brand), the environment, dry
 * run or live, and the e-mail switch. The account is the connect_apps row
 * "iys" that includes/iys/client.php reads; the job's cursors in its options
 * (push_id, pull_after) are kept on every save.
 *
 * pg_iys_settings_decide() is a plain function of the post and the stored
 * account, so the tests check it without a database or İYS.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

/**
 * A posted text field, '' when absent or not text.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_iys_settings_post($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
}

/**
 * The stored account as the card compares it: pg_iys_app() with whether a
 * password is stored (even one this site can no longer read) and the brand
 * list the last check brought back. Every field empty when none is set up.
 *
 * @return array
 */
function pg_iys_settings_before()
{
    $app = pg_iys_app();
    $raw = function_exists('pg_connect_app') ? pg_connect_app('iys') : null;

    if (!is_array($app) || !is_array($raw)) {
        return pg_iys_settings_empty();
    }

    $app['password_stored'] = !empty($raw['secret_stored']);
    $app['brands'] = pg_iys_settings_brand_list(isset($raw['options']['brands']) ? $raw['options']['brands'] : array());

    return $app;
}

/**
 * The account of a site that has none.
 *
 * @return array
 */
function pg_iys_settings_empty()
{
    return array(
        'user'            => '',
        'password'        => '',
        'password_stored' => false,
        'iys_code'        => '',
        'brand_code'      => '',
        'env'             => 'live',
        'live'            => false,
        'email'           => false,
        'enabled'         => false,
        'push_id'         => 0,
        'pull_after'      => '',
        'brands'          => array(),
    );
}

/**
 * A stored brand list read back: code (digits) => name.
 *
 * @param mixed $brands
 * @return array
 */
function pg_iys_settings_brand_list($brands)
{
    $list = array();

    foreach ((array) $brands as $code => $name) {
        if (preg_match('/^[0-9]{1,16}$/', (string) $code) && is_scalar($name)) {
            $list[(string) $code] = (string) $name;
        }
    }

    return $list;
}

/**
 * Why no İYS account can be set up on this site now, '' when one can.
 *
 * @return string
 */
function pg_iys_settings_refusal()
{
    if (function_exists('pg_demo') && pg_demo()) {
        return lang('This is a demonstration site: no İYS account can be set up here.');
    }

    if (!pg_connect_schema_ready()) {
        return lang('The database has not been upgraded yet. Run the software update first.');
    }

    if (!pg_connect_can_seal()) {
        return lang('This site has no encryption key, so it cannot keep the password of an İYS account.');
    }

    return '';
}

/**
 * The fields of the İYS card. The password is never drawn back; the brand is
 * a list once İYS has named the brands of the İYS code, a code box before.
 *
 * @return string
 */
function pg_iys_settings_fields()
{
    $app = pg_iys_settings_before();

    $warning = pg_iys_settings_refusal();

    if (($warning === '') && $app['enabled'] && !pg_iys_app_complete($app)) {
        $warning = ($app['password_stored'] && ($app['password'] === ''))
            ? lang('İYS is switched on but cannot be reached: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang('İYS is switched on but the account is not complete, so nothing is sent to İYS.');
    }

    $selected = function ($on) {
        return $on ? ' selected="selected"' : '';
    };

    $html = '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="iys_enabled" id="iys_enabled" value="1"' . ($app['enabled'] ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#iys_row"/></div>
                                                <label class="" for="iys_enabled">' . lang('İYS consent sync') . '<br/><span class="form-text">' . lang('Every day, the e-mail marketing answers people give on this site are sent to İYS (İleti Yönetim Sistemi), and the refusals they give through İYS are brought back. A consent missing from İYS counts as not given.') . '</span></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="iys_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">';

    if ($warning !== '') {
        $html .= '
                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . h($warning) . '</div></div>';
    }

    $placeholder = ($app['password_stored'] && ($app['password'] !== ''))
        ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"'
        : '';

    if ($app['brands']) {
        $brand_options = '';

        // A stored code the list no longer names stays selectable, so a save
        // does not change it unasked.
        $brands = $app['brands'];

        if (($app['brand_code'] !== '') && !isset($brands[$app['brand_code']])) {
            $brands = array($app['brand_code'] => '') + $brands;
        }

        foreach ($brands as $code => $name) {
            $brand_options .= '<option value="' . h($code) . '"' . $selected((string) $code === $app['brand_code']) . '>' . h(($name !== '') ? $name . ' (' . $code . ')' : $code) . '</option>';
        }

        $brand_field = '<select name="iys_brand" id="iys_brand" class="form-control">' . $brand_options . '</select>';
    } else {
        $brand_field = '<input type="text" name="iys_brand" id="iys_brand" class="form-control" value="' . h($app['brand_code']) . '" maxlength="16" inputmode="numeric" autocomplete="off"/>
                                                            <div class="form-text">' . lang('Left empty, saving asks İYS for the brands of the İYS code and takes the only one; with several, pick one after saving.') . '</div>';
    }

    $html .= '
                                                        <div class="col-12 form-text">' . lang('Enter the API user of your İYS account (Settings › API Users on the İYS portal), its password, and the İYS code of the company. Saving checks them with İYS and lists the brands. To remove the account from this site, switch İYS off and empty the API user.') . '</div>
                                                        <div class="pg-f-md">
                                                            <label for="iys_user" class="form-label">' . lang('API User') . '</label>
                                                            <input type="text" name="iys_user" id="iys_user" class="form-control" value="' . h($app['user']) . '" maxlength="255" autocomplete="off"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="iys_password" class="form-label">' . lang('Password') . '</label>
                                                            <input type="password" name="iys_password" id="iys_password" class="form-control" value="" maxlength="255" autocomplete="new-password"' . $placeholder . '/>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="iys_code" class="form-label">' . lang('İYS Code') . '</label>
                                                            <input type="text" name="iys_code" id="iys_code" class="form-control" value="' . h($app['iys_code']) . '" maxlength="16" inputmode="numeric" autocomplete="off"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="iys_brand" class="form-label">' . lang('Brand') . '</label>
                                                            ' . $brand_field . '
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="iys_env" class="form-label">' . lang('Environment') . '</label>
                                                            <select name="iys_env" id="iys_env" class="form-control">
                                                                <option value="live"' . $selected($app['env'] === 'live') . '>' . lang('Live') . '</option>
                                                                <option value="test"' . $selected($app['env'] === 'test') . '>' . lang('Test (sandbox)') . '</option>
                                                            </select>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="iys_live" class="form-label">' . lang('Mode') . '</label>
                                                            <select name="iys_live" id="iys_live" class="form-control">
                                                                <option value="0"' . $selected(!$app['live']) . '>' . lang('Dry run: only log what would be sent') . '</option>
                                                                <option value="1"' . $selected($app['live']) . '>' . lang('Live: send to İYS') . '</option>
                                                            </select>
                                                            <div class="form-text">' . lang('A dry run calls nothing and keeps its place, so the answers go to İYS once the mode is live.') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-field">
                                                                <div class="switch"><input type="checkbox" name="iys_email" id="iys_email" value="1"' . ($app['email'] ? ' checked="checked"' : '') . ' class=""/></div>
                                                                <label class="" for="iys_email">' . lang('E-mail') . '<br/><span class="form-text">' . lang('Sync e-mail consents, and leave out of e-mail campaigns every address İYS holds a refusal for.') . '</span></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    return $html;
}

/**
 * What a post of the İYS card asks for, decided from the post and the stored
 * account alone. An account left as it was is not written again, so one this
 * site can no longer read does not stop the rest of the pane from saving.
 *
 * @param array  $post
 * @param array  $before  pg_iys_settings_before()
 * @param string $refusal pg_iys_settings_refusal()
 * @return array|null user, password (as typed), password_effective,
 *                    iys_code, brand_code, env, live, email, enabled, action
 *                    ('none', 'save', 'disable' or 'forget'), ask (whether
 *                    İYS is to be asked for the brands), marks
 *                    (field => sentence), notices; null when the post has no
 *                    İYS card
 */
function pg_iys_settings_decide($post, $before, $refusal)
{
    if (!isset($post['iys_user'])) {
        return null;
    }

    $decision = array(
        'user'               => pg_iys_settings_post($post, 'iys_user'),
        'password'           => pg_iys_settings_post($post, 'iys_password'),
        'password_effective' => '',
        'iys_code'           => pg_iys_settings_post($post, 'iys_code'),
        'brand_code'         => pg_iys_settings_post($post, 'iys_brand'),
        'env'                => (pg_iys_settings_post($post, 'iys_env') === 'test') ? 'test' : 'live',
        'live'               => (pg_iys_settings_post($post, 'iys_live') === '1'),
        'email'              => !empty($post['iys_email']),
        'enabled'            => !empty($post['iys_enabled']),
        'action'             => 'none',
        'ask'                => false,
        'marks'              => array(),
        'notices'            => array(),
    );

    $stored = ($before['user'] !== '') || $before['password_stored'];

    if (!$decision['enabled'] && ($decision['user'] === '')) {
        $decision['action'] = $stored ? 'forget' : 'none';
        return $decision;
    }

    $account_same = ($decision['user'] === $before['user'])
        && ($decision['password'] === '')
        && ($decision['iys_code'] === $before['iys_code'])
        && ($decision['env'] === $before['env']);

    $unchanged = $account_same
        && ($decision['brand_code'] === $before['brand_code'])
        && ($decision['live'] === $before['live'])
        && ($decision['email'] === $before['email']);

    if ($unchanged && ($decision['enabled'] === $before['enabled'])) {
        return $decision;
    }

    // Switching off writes the switch alone, which needs no sealing.
    if ($unchanged && !$decision['enabled']) {
        $decision['action'] = 'disable';
        return $decision;
    }

    if ($refusal !== '') {
        if ($decision['enabled']) {
            $decision['marks']['iys_enabled'] = $refusal;
        } else {
            $decision['action'] = $before['enabled'] ? 'disable' : 'none';
            $decision['notices'][] = lang(array('string' => 'The İYS account was not saved: {var:1}', 'vars' => array($refusal)));
        }

        return $decision;
    }

    $decision['action'] = 'save';

    if (!$decision['enabled']) {
        return $decision;
    }

    $decision['password_effective'] = ($decision['password'] !== '') ? $decision['password'] : $before['password'];

    if ($decision['user'] === '') {
        $decision['marks']['iys_user'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('API User'))));
    }

    if ($decision['password_effective'] === '') {
        $decision['marks']['iys_password'] = $before['password_stored']
            ? lang('İYS is switched on but cannot be reached: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Password'))));
    }

    if ($decision['iys_code'] === '') {
        $decision['marks']['iys_code'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('İYS Code'))));
    } elseif (!preg_match('/^[0-9]{1,16}$/', $decision['iys_code'])) {
        $decision['marks']['iys_code'] = lang('An İYS code is made of digits only.');
    }

    if (($decision['brand_code'] !== '') && !preg_match('/^[0-9]{1,16}$/', $decision['brand_code'])) {
        $decision['marks']['iys_brand'] = lang('A brand code is made of digits only.');
    }

    // İYS is asked when what reaches it changed, when it is switched on, or
    // while the brand is still to be found; a change of mode or of the
    // e-mail switch alone asks nothing.
    $decision['ask'] = !$decision['marks']
        && (!$account_same || !$before['enabled'] || ($decision['brand_code'] === '') || !$before['brands']);

    return $decision;
}

/**
 * The brand an answer settles on: the chosen one when İYS names it, the only
 * one when none was chosen, '' otherwise.
 *
 * @param string $chosen
 * @param array  $brands code => name
 * @return string
 */
function pg_iys_settings_brand_pick($chosen, $brands)
{
    if ($chosen !== '') {
        return isset($brands[$chosen]) ? $chosen : '';
    }

    return (count($brands) === 1) ? (string) key($brands) : '';
}

/**
 * Checks the İYS card before the Communication settings are written and asks
 * İYS for the brands of a changed account. A refusal is marked on its field,
 * so nothing of the category is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array|null what pg_iys_settings_save() writes, with brands (the
 *                    list İYS gave, or null when it was not asked); null when
 *                    the posted pane had no İYS card
 */
function pg_iys_settings_check($post, $liveform)
{
    $before = pg_iys_settings_before();
    $decision = pg_iys_settings_decide($post, $before, pg_iys_settings_refusal());

    if ($decision === null) {
        return null;
    }

    $decision['brands'] = null;

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    if (!$decision['ask'] || $liveform->check_form_errors()) {
        return $decision;
    }

    $check = pg_iys_brands(array(
        'user'     => $decision['user'],
        'password' => $decision['password_effective'],
        'iys_code' => $decision['iys_code'],
        'env'      => $decision['env']));

    if (!$check['ok']) {
        // İYS could not be asked or refused the account: it is kept as typed,
        // and the card's warning stays until a save gets an answer.
        $decision['notices'][] = lang(array(
            'string' => 'The İYS account was saved without being checked: {var:1}',
            'vars'   => array(h(($check['error'] !== '') ? $check['error'] : lang('no answer')))));

        return $decision;
    }

    $decision['brands'] = $check['brands'];

    if (!$check['brands']) {
        $liveform->mark_error('iys_code', lang('İYS lists no brand under this İYS code.'));
        return $decision;
    }

    $picked = pg_iys_settings_brand_pick($decision['brand_code'], $check['brands']);

    if ($picked !== '') {
        $decision['brand_code'] = $picked;
    } elseif ($decision['brand_code'] !== '') {
        $liveform->mark_error('iys_brand', lang(array(
            'string' => 'İYS does not list the brand {var:1} under this İYS code. Brands: {var:2}',
            'vars'   => array(h($decision['brand_code']), h(implode(', ', array_keys($check['brands'])))))));
    } else {
        // Several brands and none chosen: the list is stored so the card
        // offers it, and nothing is sent until one is picked.
        $decision['notices'][] = lang('İYS lists several brands under this İYS code. Pick the brand and save again.');
    }

    return $decision;
}

/**
 * The options a save writes: the stored ones (the job's cursors among them,
 * and anything a later version keeps there) with the card's own replaced.
 *
 * @param array      $stored   the row's options as they are now
 * @param array      $decision pg_iys_settings_check()
 * @return array
 */
function pg_iys_settings_options($stored, $decision)
{
    $options = array_merge(is_array($stored) ? $stored : array(), array(
        'iys_code'   => $decision['iys_code'],
        'brand_code' => $decision['brand_code'],
        'env'        => $decision['env'],
        'live'       => $decision['live'],
        'email'      => $decision['email'],
    ));

    if (isset($decision['brands']) && is_array($decision['brands'])) {
        $options['brands'] = $decision['brands'];
    }

    return $options;
}

/**
 * Writes what pg_iys_settings_check() decided, after the Communication
 * settings were written and only when nothing was refused, and puts the daily
 * job in the general job's list when the account is on.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_iys_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision)) {
        return;
    }

    if ($decision['action'] === 'forget') {
        db("DELETE FROM connect_apps WHERE provider = 'iys'");
    } elseif ($decision['action'] === 'disable') {
        db("UPDATE connect_apps
            SET
                enabled = 0,
                updated_user_id = '" . (int) $user_id . "',
                updated_timestamp = UNIX_TIMESTAMP()
            WHERE provider = 'iys'");
    } elseif ($decision['action'] === 'save') {
        // Read just before the write: the job may have moved its cursors
        // since the pane was drawn.
        $stored = json_decode((string) db_value("SELECT options FROM connect_apps WHERE provider = 'iys'"), true);

        pg_connect_app_save(
            'iys',
            $decision['user'],
            ($decision['password'] !== '') ? $decision['password'] : null,
            pg_iys_settings_options($stored, $decision),
            $decision['enabled'],
            $user_id);

        if ($decision['enabled']) {
            pg_iys_job_switch_on();
        }
    }

    foreach ($decision['notices'] as $notice) {
        $liveform->add_notice($notice);
    }
}

/**
 * Adds the daily İYS job to the jobs the general job runs, when the general
 * job is the one running them; a site that schedules each job on its own
 * keeps its own schedule.
 *
 * @return bool the job is (now) in the list
 */
function pg_iys_job_switch_on()
{
    if (!function_exists('pg_cron_dispatch_list') || !defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return false;
    }

    $list = pg_cron_dispatch_list();

    if (in_array('iys_job', $list, true)) {
        return true;
    }

    $list[] = 'iys_job';

    db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");

    return true;
}
