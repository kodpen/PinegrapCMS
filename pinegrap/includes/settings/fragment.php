<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One settings category, rendered as a fragment.
 *
 * The settings are a modal now: the panel opens it from the header on any
 * screen and each category's cards arrive over AJAX. This is what produces
 * them, and it is the only place that does -- the pane in the modal and the
 * card markup are the same bytes.
 *
 * The cards were moved out of the old single screen unchanged and read the
 * variables prep.php prepares, so both files are included into this function's
 * scope: the markup finds exactly what it always found. Neither declares a
 * function or touches a global, which is what makes that safe.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}


/**
 * Render one category.
 *
 * @param string $key one of pg_settings_categories()
 * @param array $user the row validate_user() returned
 * @param object $liveform the form the cards report errors through
 * @return array html, modals, scripts, meta, sections
 */
function pg_settings_fragment($key, $user, $liveform)
{
    $category = pg_settings_category($key);

    if ($category === null) {
        output_error(lang('Page not found.'));
    }

    $row = pg_settings_config_row();

    $pg_settings_cards   = array();
    $pg_settings_modals  = array();
    $pg_settings_scripts = array();
    $pg_settings_head    = '';

    include(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
    include(PG_FUNCTIONS_DIR . '/includes/settings/' . $key . '.php');

    return array(
        'html'     => pg_settings_stamp_autocomplete(
                          pg_settings_own_dialog_triggers(implode('', $pg_settings_cards))),
        // A card that opens a dialog carries the dialog with it. It cannot stay
        // inside the pane: a Bootstrap modal nested in another modal shares the
        // outer one's backdrop and ends up behind it, so the browser puts these
        // on the body instead.
        'modals'   => implode('', $pg_settings_modals),
        'scripts'  => implode('', $pg_settings_scripts),
        'head'     => $pg_settings_head,
        // From the accessor, not from $row: the cards read the config row by
        // that name and a module is free to reuse it for a row of its own.
        'meta'     => pg_settings_last_modified(pg_settings_config_row()),
        'sections' => $category['sections'],
    );
}


/**
 * Mark every control the screen draws as not-to-be-autofilled.
 *
 * The two decoy fields in the form absorb the browser's username and password
 * guess, which is what the single screen relied on. They are not enough on
 * their own: a password manager will also put the saved username into a field
 * it reads as an e-mail address, and on these screens that lands in the support
 * address or the store address, is saved with everything else, and nobody
 * notices until mail stops arriving.
 *
 * One pass rather than 258 attributes, so a control added later is covered
 * without anyone having to remember. A control that already says what it wants
 * keeps what it says.
 *
 * "off" alone is ignored by Chrome on fields it is confident about; a token it
 * does not know is respected, and every browser treats an unknown token as off.
 *
 * @param string $html
 * @return string
 */
function pg_settings_stamp_autocomplete($html)
{
    // The opt-outs the password managers publish. Each one is that vendor's
    // own attribute, and a manager that does not know an attribute ignores it,
    // so they are all stamped together. They are the polite half of the
    // defence only: a manager is free to ignore all of it, and Kaspersky does
    // -- what actually keeps an unasked-for value out of the database is the
    // dialog putting it back (pgSettingsModal, review()).
    $ignore = ' data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other"';

    return preg_replace_callback(
        '/<(input|select|textarea)\b([^>]*)>/i',
        function ($match) use ($ignore) {

            $attributes = $match[2];

            if (stripos($attributes, 'autocomplete=') === false) {
                $attributes = ' autocomplete="pg-no-autofill"' . $attributes;
            }

            return '<' . $match[1] . $ignore . $attributes . '>';
        },
        $html);
}


/**
 * Take Bootstrap's own modal trigger out of the cards' hands.
 *
 * A dialog a card opens has to appear ON TOP of the settings, and Bootstrap's
 * data-api will not do that: it closes whatever modal is already open before
 * showing the next one, which here drops the operator back on the screen
 * behind and takes the dialog down with the pane it came from.
 *
 * Its handler cannot be headed off either -- delegated Bootstrap listeners are
 * registered on the document in the CAPTURE phase, so they run before anything
 * bound to an element inside it. The only way past is for the attribute it
 * looks for not to be there; the pane's own handler answers to this one
 * instead (see the settings modal in assets/js/backend.src.js).
 *
 * Only the cards are rewritten. The dialogs stay as they are: the close
 * buttons inside them are Bootstrap's to handle and they find the dialog they
 * sit in, and the page flow in screen.php prints the cards untouched, where
 * there is no outer modal and the data-api is right.
 *
 * @param string $html
 * @return string
 */
function pg_settings_own_dialog_triggers($html)
{
    return str_ireplace('data-bs-toggle="modal"', 'data-pgdialog="modal"', $html);
}

/**
 * Apply one category's save.
 *
 * Only the columns edited by the cards on that screen are written, so a
 * category that was not submitted cannot have its settings overwritten -- which
 * is what the single screen did, and what silently reset three affiliate fields
 * and the barcode label design on every save.
 *
 * Runs at this function's scope for the same reason as the markup: the save
 * code is the old screen's, unchanged.
 *
 * @param string $key
 * @param array $user
 * @param object $liveform
 * @return string the url scheme the caller should return to, when this save could change it
 */
function pg_settings_apply($key, $user, $liveform)
{
    if (pg_settings_category($key) === null) {
        output_error(lang('Page not found.'));
    }

    include(PG_FUNCTIONS_DIR . '/includes/settings/' . $key . '.save.php');

    // A module that marked a field wrote nothing and returned early; the log
    // must not say otherwise.
    if (!$liveform->check_form_errors()) {
        log_activity(lang('settings were modified'), $_SESSION['sessionusername']);
    }

    // The General screen can turn Secure Mode on in the same request, so the
    // answer to "which scheme now" comes from the save, not from the request
    // this arrived on.
    return isset($url_scheme) ? $url_scheme : URL_SCHEME;
}


/**
 * post_value(), which every save module calls.
 *
 * It was declared inside the old screen's POST branch. Guarded here because
 * this file can be loaded by a page and by the modal's endpoint in one request.
 */
if (!function_exists('post_value')) {

    function post_value($key)
    {
        return array_key_exists($key, $_POST) ? $_POST[$key] : NULL;
    }
}
