<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One settings category: the flow every one of them shares.
 *
 * Included at script scope by the thin wrapper that names the category, so the
 * markup and the save code moved out of settings.php run in exactly the
 * variable scope they always did -- which is why none of it had to be rewritten
 * when the screen was split on 2026-09-12.
 *
 * The wrapper has already run init.php and named the category:
 *
 *     include('init.php');
 *     define('PG_SETTINGS_KEY', 'security');
 *     include('includes/settings/screen.php');
 *
 * A category module fills these, all optional but $pg_settings_cards:
 *
 *     $pg_settings_cards[]        one entry per card, in reading order
 *     $pg_settings_modals[]       markup printed outside the form
 *     $pg_settings_scripts[]      scripts printed after the form
 *     $pg_settings_head           markup for <head>
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_KEY')) {
    exit;
}

// The gate the whole screen has always used: role <= 2 (Administrator,
// Designer, Manager). It stays on this side of the include, so every category
// is protected whether or not the hub chose to draw its tile.
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');

// Opens the category modules and the registry. Nothing under
// includes/settings/ runs without it.
define('PG_SETTINGS_ENTRY', true);

include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

$pg_settings_key      = (string) PG_SETTINGS_KEY;
$pg_settings_category = pg_settings_category($pg_settings_key);

if ($pg_settings_category === null) {
    output_error(lang('Page not found.'));
}

$liveform = new liveform('settings');

$pg_settings_cards       = array();
$pg_settings_modals      = array();
$pg_settings_scripts     = array();
$pg_settings_head        = '';

// Where this screen posts to and returns to. Built from the key rather than
// from the request, so nothing a visitor sends can steer it.
$pg_settings_self = pg_settings_url($pg_settings_key);


if (!$_POST) {

    // Every value the cards read, prepared exactly as before the split.
    include(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');

    // The cards themselves.
    include(PG_FUNCTIONS_DIR . '/includes/settings/' . $pg_settings_key . '.php');

    // ── the section chips ────────────────────────────────────────────────
    //
    // A category holds between one and six cards, which is few enough that a
    // rail down the side would be a column spent on a list you can already
    // see. The chips say the same thing in the toolbar and double as the
    // #hash a deep link arrives on.
    $pg_settings_chips = '';

    if (count($pg_settings_category['sections']) > 1) {

        foreach ($pg_settings_category['sections'] as $pg_settings_section_id => $pg_settings_section_label) {

            $pg_settings_chips .=
                '<a class="pg-chip" href="#' . h($pg_settings_section_id) . '" data-pgset="' . h($pg_settings_section_id) . '">'
                . h($pg_settings_section_label) . '</a>';
        }
    }

    // ── the category switcher ────────────────────────────────────────────
    //
    // Same list the hub and the Settings menu draw from. It is the primary
    // control of the bar because moving between categories is what an
    // operator does most on these screens.
    $pg_settings_switcher = '';

    foreach (pg_settings_categories() as $pg_settings_other_key => $pg_settings_other) {

        $pg_settings_switcher .=
            '<li><a class="dropdown-item' . (($pg_settings_other_key === $pg_settings_key) ? ' active' : '') . '"'
            . ' href="' . h(pg_settings_url($pg_settings_other_key)) . '">'
            . '<i class="bi ' . h($pg_settings_other['icon']) . ' me-2" aria-hidden="true"></i>'
            . h($pg_settings_other['label']) . '</a></li>';
    }

    // Previous and next, so a category can be read through without going back
    // to the hub between each one.
    $pg_settings_keys  = array_keys(pg_settings_categories());
    $pg_settings_index = array_search($pg_settings_key, $pg_settings_keys, true);

    $pg_settings_prev = ($pg_settings_index > 0) ? $pg_settings_keys[$pg_settings_index - 1] : '';
    $pg_settings_next = ($pg_settings_index < (count($pg_settings_keys) - 1)) ? $pg_settings_keys[$pg_settings_index + 1] : '';

    $pg_settings_step_links = '';

    foreach (array(array($pg_settings_prev, 'bi-chevron-left', lang('Previous')), array($pg_settings_next, 'bi-chevron-right', lang('Next'))) as $pg_settings_step) {

        if ($pg_settings_step[0] === '') {
            $pg_settings_step_links .= '<button type="button" class="btn btn-sm btn-ghost" disabled="disabled"><i class="bi ' . $pg_settings_step[1] . '" aria-hidden="true"></i></button>';
            continue;
        }

        $pg_settings_step_category = pg_settings_category($pg_settings_step[0]);

        $pg_settings_step_links .=
            '<a class="btn btn-sm btn-ghost no-popover" data-bs-toggle="tooltip"'
            . ' title="' . h($pg_settings_step_category['label']) . '"'
            . ' href="' . h(pg_settings_url($pg_settings_step[0])) . '">'
            . '<i class="bi ' . $pg_settings_step[1] . '" aria-hidden="true"></i></a>';
    }

    // ── the cards ────────────────────────────────────────────────────────
    //
    // A card is a whole grid column, id and width and all, exactly as it was
    // when the sixteen of them shared one screen. Nothing wraps them here: a
    // column inside a column is a card with two sets of gutters and a width
    // that does not answer to its own class.
    $pg_settings_body = implode('', $pg_settings_cards);

    // ── autofill ─────────────────────────────────────────────────────────
    //
    // The two decoy fields below absorb the browser's username and password
    // guess, which is what the single screen relied on. They are not enough on
    // their own: a password manager will also put the saved username into a
    // field it reads as an e-mail address, and on these screens that lands in
    // the support address or the store address, gets saved with everything
    // else, and nobody notices until mail stops arriving.
    //
    // So every control the screen draws is marked as well. Done here rather
    // than in the 258 controls themselves: one pass, and a control added later
    // is covered without anyone having to remember. A control that already
    // says what it wants keeps what it says.
    $pg_settings_body = preg_replace_callback(
        '/<(input|select|textarea)\b([^>]*)>/i',
        function ($match) {

            if (stripos($match[2], 'autocomplete=') !== false) {
                return $match[0];
            }

            // "off" alone is ignored by Chrome on fields it is confident about;
            // a token it does not know is respected, and every browser treats
            // an unknown token as off.
            return '<' . $match[1] . ' autocomplete="pg-no-autofill"' . $match[2] . '>';
        },
        $pg_settings_body);

    $output =
    pg_page_shell(array(
        'title'         => lang('Site Settings') . ' - ' . $pg_settings_category['label'],
        'extra classes' => 'setting',
        'icon'          => 'setting',
        'heading'       => $pg_settings_category['label'],
        'heading_description' => $pg_settings_category['description'],
        'head'          => $pg_settings_head,
    )) . '
<main id="content" class="container-fluid">
    ' . get_codemirror_includes() . '
    <div class="row gy-3">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . implode('', $pg_settings_modals) . '

            <div class="pg-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary rounded-pill px-3 dropdown-toggle no-popover" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi ' . h($pg_settings_category['icon']) . ' me-2" aria-hidden="true"></i>' . h($pg_settings_category['label']) . '
                    </button>
                    <ul class="dropdown-menu">' . $pg_settings_switcher . '</ul>
                </div>
                <span class="text-body-secondary small d-none d-md-inline">' . h(lang(array(
                    'string' => '{var:1} section{suffix:1}, saved together',
                    'vars'   => count($pg_settings_category['sections']),
                    'suffix' => ((count($pg_settings_category['sections']) == 1) ? '' : 's'),
                ))) . '</span>
                <div class="pg-toolbar-grow pg-chips">' . $pg_settings_chips . '</div>
                <div class="input-group input-group-sm rounded-pill pg-toolbar-search">
                    <span class="input-group-text bg-transparent border-end-0 rounded-start-pill"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="pg_settings_filter" class="form-control border-start-0 rounded-end-pill" placeholder="' . h(lang('Search in this page')) . '" autocomplete="off" />
                </div>
                <div class="btn-group btn-group-sm">' . $pg_settings_step_links . '</div>
            </div>

            <form name="form" action="' . h($pg_settings_self) . '" method="post" autocomplete="off">
                <!--
                    The following two fields are used to workaround a Safari bug where it incorrectly
                    autofills the member id label field and payment service password field.
                    https://discussions.apple.com/thread/5476502
                    https://discussions.apple.com/thread/6027332
                -->
                <!--
                    A decoy text field, and deliberately NO decoy password: a hidden
                    password input is what tells a password manager the form is a
                    sign-in form, and it answers by writing the saved username into
                    whichever field it takes for the username box.
                -->
                <input id="fake_user_name" name="fake_user[name]" style="position:absolute; top:-100px;" type="text" value="No Autofill for Site Settings">
                ' . get_token_field() . '

                <div class="pg-set-masonry pg-settings-body" id="pg_settings_sections">
                    ' . $pg_settings_body . '
                </div>

                <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />

                <nav class="buttons navigation text-center position-sticky " style="bottom:.5rem;" aria-label="data edit buttons ">
                    <div class="container">
                        <div class=" btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_save" value="Save" class="btn btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
    <script>
        $(function () {
            initSettingsAnchors();
            initSettingsFilter();
        });
    </script>
    ' . implode('', $pg_settings_scripts) . '
</main>' .
    output_footer();

    print $output;

    $liveform->remove_form('settings');

} else {

    validate_token_field();

    // Helper function to get POST value or NULL if not set
    function post_value($key) {
        return array_key_exists($key, $_POST) ? $_POST[$key] : NULL;
    }

    // Only this category's writes run. Nothing else on the site's settings can
    // be touched by a form that did not carry it -- which is what the single
    // form used to do, and what silently reset three affiliate fields and the
    // barcode label size on every save.
    include(PG_FUNCTIONS_DIR . '/includes/settings/' . $pg_settings_key . '.save.php');

    log_activity(lang('settings were modified'), $_SESSION['sessionusername']);

    $liveform->add_notice(lang('The Site Settings have been saved.'));

    // The General screen can change the scheme in the same request, so the
    // redirect has to use the one just chosen rather than the one this request
    // arrived on.
    $pg_settings_scheme = isset($url_scheme) ? $url_scheme : URL_SCHEME;

    header('Location: ' . $pg_settings_scheme . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $pg_settings_self);
}
