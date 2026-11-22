<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Features pane: the Modules board of switches, then the
 * rest as folded rows (includes/settings/fold.php), each row holding its card.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/features_modules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/features_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// ── Modules ──
//
// One board of module switches (includes/fn/features_modules.php). Each
// switch keeps the field name and id of its old card, so features.save.php
// reads it unchanged; a card with nothing but its switch (Courses, Tickets)
// posts its marker from the row. Commerce and Accounting keep their switch
// at the head of their own category, where their save needs it: the board
// shows their state and opens them.
$features_modules = array(
    'commerce'     => array('on' => ($ecommerce_checked !== ''), 'href' => pg_settings_may_open('commerce', $user) ? pg_settings_link('commerce') : ''),
    'accounting'   => array('on' => ($erp_enabled_checked !== ''), 'href' => pg_settings_may_open('accounting', $user) ? pg_settings_link('accounting') : ''),
    'forms'        => array('on' => ($forms_checked !== '')),
    'calendars'    => array('on' => ($calendars_checked !== '')),
    'ads'          => array('on' => ($ads_checked !== '')),
    'auto_dialogs' => array('on' => ($auto_dialogs_checked !== '')),
    'sharing'      => array('on' => ($social_networking_checked !== ''), 'settings' => 'pg-fold-sharing'),
    'mobile'       => array('on' => ($mobile_checked !== '')),
);

if ($workspace_setting_available) {
    $features_modules['workspace'] = array('on' => ($workspace_enabled_checked !== ''));
}

if (function_exists('pg_course_ready') && pg_course_ready() && pg_schema_has('config_text')) {
    $features_modules['courses'] = array('on' => pg_course_switch_on(), 'extra' => '<input type="hidden" name="courses_card" value="1"/>');
}

if (function_exists('pg_event_tickets_ready') && pg_event_tickets_ready() && pg_schema_has('config_text')) {
    $features_modules['tickets'] = array('on' => pg_event_tickets_switch_on(), 'extra' => '<input type="hidden" name="event_tickets_card" value="1"/>');
}

if (function_exists('pg_site_push_ready') && pg_site_push_ready()) {
    $features_modules['site_push'] = array('on' => pg_site_push_switch_on(), 'settings' => 'pg-fold-site-push');
}

if (pg_schema_has('config_text')) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_tiles.php');
    $features_modules['maps'] = array('on' => pg_place_tiles_switch_on(), 'settings' => 'pg-fold-maps');
}

if (function_exists('pg_page_clicks_ready') && pg_page_clicks_ready()) {
    $features_modules['page_clicks'] = array('on' => pg_page_clicks_switch_on(), 'settings' => 'pg-fold-page-clicks');
}

// Drawn for designers and administrators only, as its card is: a Manager's
// own saves are the ones it holds.
$features_content_approval_shown = pg_content_proposals_ready() && pg_content_proposals_may_set($user);

if ($features_content_approval_shown) {
    $content_approval = pg_content_proposals_settings();
    $features_modules['content_approval'] = array('on' => $content_approval['on'], 'settings' => 'pg-fold-content-approval');
}

$pg_settings_cards[] = '
                        <div id="pgset-features" class="pg-set-card">
                            ' . pg_features_modules_board($features_modules) . '
                        </div>';

// ── The rows below the board ──
//
// Each part is one folded row (includes/fn/features_fold.php), its card
// drawn here with its own pgset- id, fields and names, so the anchors, the
// search index and features.save.php read it as before.
$features_parts = array();

// ── Sharing buttons ──
//
// The board switches them; their setup is here: Simple ticks the services,
// Advanced takes the site's own code. The Setup control shows the one its
// choice uses (collapse-switcher); both are posted.
$sharing_services = array(
    'facebook' => array('Facebook', $social_networking_facebook_checked),
    'twitter'  => array('X (Twitter)', $social_networking_twitter_checked),
    'linkedin' => array('LinkedIn', $social_networking_linkedin_checked),
    'whatsapp' => array('WhatsApp', $social_networking_whatsapp_checked),
    'telegram' => array('Telegram', $social_networking_telegram_checked),
    'pinterest' => array('Pinterest', $social_networking_pinterest_checked),
    'reddit'   => array('Reddit', $social_networking_reddit_checked),
    'email'    => array(lang('Email'), $social_networking_email_checked),
);
$sharing_chosen = array();

foreach ($sharing_services as $sharing_service) {
    if ($sharing_service[1] !== '') {
        $sharing_chosen[] = $sharing_service[0];
    }
}

$features_parts['sharing'] = array('html' => '
                        <div id="pgset-sharing" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Sharing buttons') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <fieldset><legend class="form-label pg-field-label">' . lang('Setup') . '</legend>
                                            <div class="pg-seg flex-wrap">
                                                <label class="pg-seg-item" for="social_networking_type_simple"><input value="simple" class="visually-hidden collapse-switcher" type="radio" id="social_networking_type_simple" name="social_networking_type"' . $social_networking_type_simple_checked . ' data-bs-target="#social_networking_services_row"/>' . lang('Simple') . '</label>
                                                <label class="pg-seg-item" for="social_networking_type_advanced"><input value="advanced" class="visually-hidden collapse-switcher" type="radio" id="social_networking_type_advanced" name="social_networking_type"' . $social_networking_type_advanced_checked . ' data-bs-target="#social_networking_code_row"/>' . lang('Advanced') . '</label>
                                            </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-12" id="social_networking_services_row"' . (($social_networking_type_advanced_checked !== '') ? ' style="display:none"' : '') . '>
                                            <div class="form-label">' . lang('Services') . '</div>
                                            <div class="row gy-5">
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_facebook" id="social_networking_facebook" value="1"' . $social_networking_facebook_checked . '/><label class="pg-text-2" for="social_networking_facebook">Facebook</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_twitter" id="social_networking_twitter" value="1"' . $social_networking_twitter_checked . '/><label class="pg-text-2" for="social_networking_twitter">X (Twitter)</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_linkedin" id="social_networking_linkedin" value="1"' . $social_networking_linkedin_checked . '/><label class="pg-text-2" for="social_networking_linkedin">LinkedIn</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_whatsapp" id="social_networking_whatsapp" value="1"' . $social_networking_whatsapp_checked . '/><label class="pg-text-2" for="social_networking_whatsapp">WhatsApp</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_telegram" id="social_networking_telegram" value="1"' . $social_networking_telegram_checked . '/><label class="pg-text-2" for="social_networking_telegram">Telegram</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_pinterest" id="social_networking_pinterest" value="1"' . $social_networking_pinterest_checked . '/><label class="pg-text-2" for="social_networking_pinterest">Pinterest</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_reddit" id="social_networking_reddit" value="1"' . $social_networking_reddit_checked . '/><label class="pg-text-2" for="social_networking_reddit">Reddit</label></div>
                                                <div class="sm:col-6 d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" name="social_networking_email" id="social_networking_email" value="1"' . $social_networking_email_checked . '/><label class="pg-text-2" for="social_networking_email">' . lang('Email') . '</label></div>
                                            </div>
                                        </div>
                                        <div class="col-12" id="social_networking_code_row"' . (($social_networking_type_advanced_checked === '') ? ' style="display:none"' : '') . '>
                                            <label for="social_networking_code" class="form-label">' . lang('Code') . '</label>
                                            <textarea class="form-control" id="social_networking_code" name="social_networking_code">' . h($social_networking_code) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'social_networking_code', 'code_type' => 'mixed')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_sharing($social_networking_checked !== '', $social_networking_type_advanced_checked !== '', $sharing_chosen);

// ── Badge ──
//
// The label a member gets by default in comments and form records; no other
// category holds members' badges.
$features_parts['badge'] = array('html' => '
                        <div id="pgset-badge" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Badge') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="pg-f-md">
                                            <label for="setting_badge_label" class="form-label">' . lang('Default Badge Label') . '</label>
                                            <input type="text" name="badge_label" id="setting_badge_label" maxlength="100" class="form-control" value="' . h($badge_label) . '" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_badge($badge_label);

    // ── Images ──────────────────────────────────────────
    //
    // Hidden entirely on an install that has not run the 2026.4.4 upgrade.
    // The defaults are already in force there, so the block would show numbers
    // that look editable and then save into columns that do not exist.
    $output_image_settings_card = '';

    if ($image_settings_ready) {

        $image_product_optimize_checked = ($image_settings['product_optimize'] == 1) ? ' checked="checked"' : '';
        $image_formats_offered = array();

        // Which library will actually do the work. Worth printing: the two
        // produce different file sizes from the same settings, and on a host
        // with neither there is nothing to configure — better to say so here
        // than to leave the operator adjusting numbers that nothing reads.
        // The PHP ceilings are printed next to the image settings because they
        // decide whether an upload happens at all — a photo bigger than
        // upload_max_filesize never reaches the code these numbers configure,
        // and the failure looks nothing like a limit being hit. Read-only:
        // they live in php.ini, not in this table.
        $upload_limits = pg_upload_limits();

        $output_upload_limits_note =
            ($upload_limits['file_max'] > 0)
                ? ' &middot; ' . h(lang(array(
                    'string' => 'At most {var:1} per file, and {var:2} in one upload.',
                    'vars'   => array(
                        convert_bytes_to_string($upload_limits['file_max'], 1),
                        convert_bytes_to_string($upload_limits['post_max'], 1)))))
                : '';

        $output_image_library_note =
            ($image_library !== '')
                ? '<div class="form-text">' . lang(array('string' => 'Image library in use: {var:1}', 'vars' => array($image_library))) . $output_upload_limits_note . '</div>'
                : '<div class="alert theme-warning py-3 mb-0 small">' . lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.') . '</div>';

        // How pictures reach visitors: the modern formats the responsive
        // markup offers and the quality each is written at
        // (includes/image_engine.php). Hidden until the 2026.11.0 columns
        // exist; the defaults (both formats, 80 and 50) apply meanwhile. A
        // format the server cannot write is said beside its switch: the
        // switch alone would promise pictures nobody gets.
        $output_image_delivery_fields = '';

        if ($image_delivery_ready) {

            $image_format_rows = array(
                'webp' => array('bit' => 1, 'name' => 'WebP', 'label' => lang('Offer pictures as WebP'), 'text' => lang('Pictures on pages are sent at the width the screen needs, and as WebP to browsers that read it: usually about a third lighter than JPEG. Each is made the first time it is asked for.')),
                'avif' => array('bit' => 2, 'name' => 'AVIF', 'label' => lang('Offer pictures as AVIF'), 'text' => lang('Lighter still than WebP. Browsers that cannot read AVIF get WebP or the original instead.')),
            );

            foreach ($image_format_rows as $image_format => $image_format_row) {

                $image_format_checked = (($image_settings['derivative_formats'] & $image_format_row['bit']) === $image_format_row['bit']) ? ' checked="checked"' : '';

                if (($image_format_checked !== '') && !empty($image_formats_written[$image_format])) {
                    $image_formats_offered[] = $image_format_row['name'];
                }

                $image_format_note = empty($image_formats_written[$image_format])
                    ? '<div class="form-text fg-emphasis-warning">' . lang(array('string' => 'This server cannot write {var:1}, so it is not offered while that stays so.', 'vars' => array($image_format_row['name']))) . '</div>'
                    : '';

                $output_image_delivery_fields .= '
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" class="pg-switch" value="1"' . $image_format_checked . ' id="image_derivative_' . $image_format . '" name="image_derivative_' . $image_format . '" aria-describedby="image_derivative_' . $image_format . '_help"/>
                                    <label class="pg-text-2" for="image_derivative_' . $image_format . '">' . $image_format_row['label'] . '</label>
                                </div>
                                <div class="form-text" id="image_derivative_' . $image_format . '_help">' . $image_format_row['text'] . '</div>' . $image_format_note . '
                            </div>';
            }

            // A JPEG or PNG answered with its WebP copy at its own address
            // (bit 4, includes/image_negotiation.php), and the confirmation
            // that the CDN in front keeps a copy per format (bit 8): until it
            // is given, a request that came through a CDN is answered private.
            // The daily self-test (includes/fn/image_vary_check.php) sets it,
            // and takes back one the edge is not seen honouring.
            $image_same_address = $image_settings['delivery']['same_address'];

            $image_same_address_note = empty($image_formats_written['webp'])
                ? '<div class="form-text fg-emphasis-warning">' . lang(array('string' => 'This server cannot write {var:1}, so it is not offered while that stays so.', 'vars' => array('WebP'))) . '</div>'
                : '';

            $output_image_delivery_fields .= '
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" class="pg-switch" value="1"' . ($image_same_address['on'] ? ' checked="checked"' : '') . ' id="image_same_address" name="image_same_address" aria-describedby="image_same_address_help"/>
                                    <label class="pg-text-2" for="image_same_address">' . lang('Send WebP at the picture\'s own address') . '</label>
                                </div>
                                <div class="form-text" id="image_same_address_help">' . lang('A JPEG or PNG keeps its address and its file; a browser that reads WebP gets a lighter WebP copy there, every other reader the original. Needs WebP offered above.') . '</div>' . $image_same_address_note . '
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" class="pg-switch" value="1"' . ($image_same_address['cdn'] ? ' checked="checked"' : '') . ' id="image_same_address_cdn" name="image_same_address_cdn" aria-describedby="image_same_address_cdn_help"/>
                                    <label class="pg-text-2" for="image_same_address_cdn">' . lang('The CDN keeps a copy per format') . '</label>
                                </div>
                                <div class="form-text" id="image_same_address_cdn_help">' . lang('Until this is on, requests through a CDN get WebP marked private, so the CDN keeps no copy of pictures. A daily check turns it on once it sees the CDN keep a copy per Accept value (on Cloudflare, a Cache Rule), and turns it off again, even when ticked here, once it finds the CDN handing one copy to everyone or keeping none.') . '</div>
                            </div>';

            // A unit is the field's suffix, after the number, in every panel
            // language (ui/screen-composition-nesting.md).

            $output_image_delivery_fields .= '
                            <div class="pg-f-xs">
                                <label for="image_quality_webp" class="form-label">' . lang('WebP Quality') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">%</span>
                                    <input type="text" name="image_quality_webp" id="image_quality_webp" maxlength="3" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['quality_webp']) . '" />
                                </label>
                                <div class="form-text">' . lang('A changed quality makes the pictures again as they are next asked for. Resized JPEG and PNG pictures use the quality when resizing above.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_quality_avif" class="form-label">' . lang('AVIF Quality') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">%</span>
                                    <input type="text" name="image_quality_avif" id="image_quality_avif" maxlength="3" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['quality_avif']) . '" />
                                </label>
                                <div class="form-text">' . lang('AVIF holds up at a lower number than WebP, so its quality is set apart.') . '</div>
                            </div>';
        }

        // The visual designer's pages keep the HTML drawn when they were
        // saved; this draws them all again (pg_designer_rebuild_page_code()).
        $output_page_rebuild_field = '';

        if ($page_rebuild_ready) {
            $output_page_rebuild_field = '
                            <div class="col-12 ">
                                <button type="submit" name="submit_rebuild_pages" value="1" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Save and rebuild page HTML') . '</button>
                                <div class="form-text">' . lang('Page Design Studio pages keep the HTML drawn when they were last saved. This draws every page again from what was saved, so pages published before responsive images, or before a format was switched on or off, get the current picture markup. Their content, modified date and author do not change.') . '</div>
                            </div>';
        }

        // The upload size cap (11.0.142), hidden until its column exists.
        $output_image_upload_cap_field = '';

        if (pg_schema_has('config', 'image_upload_max_dimension')) {

            $output_image_upload_cap_field = '
                            <div class="pg-f-xs">
                                <label for="image_upload_max_dimension" class="form-label">' . lang('Shrink Pictures On Upload Above') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">' . lang('pixels') . '</span>
                                    <input type="text" name="image_upload_max_dimension" id="image_upload_max_dimension" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['upload_max_dimension']) . '" />
                                </label>
                                <div class="form-text">' . lang('A picture uploaded to the library whose longest edge is above this is shrunk to it, in its own format. 0 leaves uploads as they are; product photos keep their own limit.') . '</div>
                            </div>';
        }

        // The image editor's largest upload, a config_text row. Empty or 0:
        // PHP's upload_max_filesize, named below.
        $image_editor_php_limit = pg_ini_bytes(@ini_get('upload_max_filesize'));
        $image_editor_php_limit_text = ($image_editor_php_limit > 0) ? round($image_editor_php_limit / 1048576, 1) . ' MB' : lang('no limit');

        $output_image_upload_cap_field .= '
                            <div class="pg-f-xs">
                                <label for="image_editor_upload_max_mb" class="form-label">' . lang('Largest Video Or Sound In The Artifact Canvas (MB)') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">MB</span>
                                    <input type="text" name="image_editor_upload_max_mb" id="image_editor_upload_max_mb" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h(pg_image_editor_upload_max_mb()) . '" />
                                </label>
                                <div class="form-text">' . h(lang(array('string' => 'A video or sound uploaded in the Artifact Canvas, and an animation it saves to Files, may be up to this size; it is sent in parts, so it may be larger than the upload limit of PHP. 0 keeps that limit ({var:1}). At most {var:2} MB.', 'vars' => array($image_editor_php_limit_text, pg_image_editor_upload_max_mb_most())))) . '</div>
                            </div>';

        $output_image_settings_card =
            '<div id="pgset-images" class="pg-set-card">
                <div class="card">
                    <div class="card-header bg-reset border-0 pg-title-section">
                        ' . lang('Images') . '
                    </div>
                    <div class="card-body">
                       <div class="row gy-5">
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" class="pg-switch" value="1"' . $image_product_optimize_checked . ' id="image_product_optimize" name="image_product_optimize" aria-describedby="image_product_optimize_help"/>
                                    <label class="pg-text-2" for="image_product_optimize">' . lang('Optimize Product Images On Upload') . '</label>
                                </div>
                                <div class="form-text" id="image_product_optimize_help">' . lang('Photos added from the product and variant set screens are compressed as they arrive, and scaled down when they are larger than the limit below. Images picked from the file library are left exactly as they are.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_product_max_dimension" class="form-label">' . lang('Product Image Size Limit') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">' . lang('pixels') . '</span>
                                    <input type="text" name="image_product_max_dimension" id="image_product_max_dimension" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['product_max_dimension']) . '" />
                                </label>
                                <div class="form-text">' . lang('Longest edge. A product photo above this is scaled down on upload, keeping its proportions.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_product_min_dimension" class="form-label">' . lang('Smallest Acceptable Product Image') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">' . lang('pixels') . '</span>
                                    <input type="text" name="image_product_min_dimension" id="image_product_min_dimension" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['product_min_dimension']) . '" />
                                </label>
                                <div class="form-text">' . lang('Shortest edge. Smaller images are still accepted; the product screen and the SEO details point them out. Google Merchant Center requires 500 pixels from 2027.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_resize_quality" class="form-label">' . lang('Quality When Resizing') . '</label>
                                <div class="form-range pg-range-bubble" data-pg-range="' . h(lang('{var:1}%')) . '">
                                    <input type="range" name="image_resize_quality" id="image_resize_quality" min="40" max="100" step="1" class="form-range-input" value="' . (int) $image_settings['resize_quality'] . '" />
                                </div>
                                <div class="form-text">' . lang('Applies only when an image is actually scaled down. Optimizing without resizing keeps its own quality setting.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_file_resize_trigger" class="form-label">' . lang('Offer To Resize Files Above') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">' . lang('pixels') . '</span>
                                    <input type="text" name="image_file_resize_trigger" id="image_file_resize_trigger" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['file_resize_trigger']) . '" />
                                </label>
                                <div class="form-text">' . lang('On the Files screen the resize button appears only for images whose longest edge is above this.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_file_max_dimension" class="form-label">' . lang('Resize Files Down To') . '</label>
                                <label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">' . lang('pixels') . '</span>
                                    <input type="text" name="image_file_max_dimension" id="image_file_max_dimension" maxlength="5" inputmode="numeric" class="form-ghost text-end" value="' . h($image_settings['file_max_dimension']) . '" />
                                </label>
                                <div class="form-text">' . lang('Used only when the resize button is pressed; the plain optimize button never resizes.') . '</div>
                            </div>' . $output_image_upload_cap_field . $output_image_delivery_fields . $output_page_rebuild_field . '
                            <div class="col-12 ">
                                ' . $output_image_library_note . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }

if ($output_image_settings_card !== '') {
    $features_parts['images'] = array('html' => $output_image_settings_card) + pg_features_fold_images($image_formats_offered, $image_library);
}

// ── Site search ──
//
// The search type, and the tuning (includes/fn/site_search_tuning.php):
// synonym groups and promoted results, each one config_text row as JSON,
// drawn once that table exists.
$search_tuning_fields = '';
$search_synonyms_count = null;
$search_promoted_count = null;

if (pg_schema_has('config_text')) {
    $search_synonyms = pg_search_synonyms();
    $search_promoted = pg_search_promoted();
    $search_synonyms_count = count($search_synonyms);
    $search_promoted_count = count($search_promoted);
    $search_synonyms_text = pg_search_synonyms_format($search_synonyms);
    $search_promoted_text = pg_search_promoted_format($search_promoted);
    // Opened from the site search report's "promote" button: a line for the
    // term, the address left for the operator to write.
    if (isset($_GET['search_promote']) && is_string($_GET['search_promote'])) {
        $search_promoted_text = pg_search_promoted_add_line($search_promoted_text, $_GET['search_promote']);
    }

    $search_tuning_fields = '
                                        <input type="hidden" name="search_tuning_card" value="1"/>
                                        <div class="col-12">
                                            <label class="form-label" for="search_synonyms">' . lang('Synonyms') . '</label>
                                            <textarea class="form-control" id="search_synonyms" name="search_synonyms" rows="5" spellcheck="false">' . h($search_synonyms_text) . '</textarea>
                                            <div class="form-text">' . lang('One group of words that mean the same per line, separated by commas, such as: shipping, delivery, cargo. A search for one of them also finds the pages of the others.') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="search_promoted">' . lang('Promoted Results') . '</label>
                                            <textarea class="form-control" id="search_promoted" name="search_promoted" rows="5" spellcheck="false">' . h($search_promoted_text) . '</textarea>
                                            <div class="form-text">' . lang('One per line: the search terms separated by commas, then => and the address on this site, optionally | and a title, such as: return, refund => return-policy | Returns. A search for one of the terms lists the address first, marked Recommended.') . '</div>
                                        </div>';
}

$features_parts['search'] = array('html' => '
                        <div id="pgset-search-tuning" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Site search') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <fieldset><legend class="form-label pg-field-label">' . lang('Site Search Type') . '</legend>
                                            <div class="pg-seg flex-wrap">
                                                <label class="pg-seg-item" for="search_type_simple"><input value="simple" class="visually-hidden" type="radio" id="search_type_simple" name="search_type"' . $search_type_simple_checked . ' aria-describedby="search_type_help"/>' . lang('Simple') . '</label>
                                                <label class="pg-seg-item" for="search_type_advanced"><input value="advanced" class="visually-hidden" type="radio" id="search_type_advanced" name="search_type"' . $search_type_advanced_checked . ' aria-describedby="search_type_help"/>' . lang('Advanced') . '</label>
                                            </div>
                                            </fieldset>
                                            <div class="form-text" id="search_type_help">' . lang('Advanced searches the search index, which the search index job rebuilds once a day: pages, linked files and the records of public form lists. Simple searches the text of pages as it is now and finds no form records.') . '</div>
                                        </div>' . $search_tuning_fields . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_search($search_type_advanced_checked !== '', $search_synonyms_count, $search_promoted_count);

// Clicks and scrolling (includes/fn/page_clicks.php): off on every site, a
// new install too, until it is switched on here or with the Visitors
// section's button. Read and saved only through the module's one reader and
// saver. The privacy sentence is for the site to copy into its own text.
if (function_exists('pg_page_clicks_ready') && pg_page_clicks_ready()) {
    $features_parts['page-clicks'] = array('html' => '
                        <div id="pgset-page-clicks" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . h(lang('Clicks and scrolling')) . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="page_clicks_card" value="1"/>
                                    <div class="row gy-12">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('Which links and buttons visitors click on each page and how far down they scroll, per page, day and device type, shown in Visitors. Signed-in members and visitors who refused statistics in the cookie notice are not counted.')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-label" id="page_clicks_privacy_label">' . h(lang('For your privacy text')) . '</div>
                                            <p class="form-text mt-0 mb-0 user-select-all" id="page_clicks_privacy" aria-labelledby="page_clicks_privacy_label">' . h(lang('We count which links and buttons are clicked on our pages and how far down they are scrolled, as totals per page, day and device type (desktop, phone or tablet). No cookie is set and nothing that identifies you is kept: no IP address, no identifier and no record per visitor. Nothing is counted if you refuse statistics in our cookie notice.')) . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_page_clicks(pg_page_clicks_switch_on());
}

// Maps (includes/fn/place_tiles.php): off on every site, a new install too.
// On, the "Map of places" block draws its places on a map beside its list;
// off, the list alone. The tile address and its credit line are
// OpenStreetMap's until another is typed; a provider's key is kept sealed,
// drawn empty and never shown back.
if (pg_schema_has('config_text')) {
    $place_tiles = pg_place_tiles_settings();

    $features_parts['maps'] = array('html' => '
                        <div id="pgset-place-map" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . h(lang('Maps')) . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="place_map_card" value="1"/>
                                    <div class="row gy-12">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('The Map of places block draws its places on a map beside its list; off, it shows the list alone. While the cookie notice is on, the map loads only after the visitor allows marketing, and the block shows a Show the map button until then.')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="place_map_tiles" class="form-label">' . h(lang('Tile Address')) . '</label>
                                            <input type="url" name="place_map_tiles" id="place_map_tiles" maxlength="' . PG_PLACE_TILES_URL_MAX . '" class="form-control" spellcheck="false" value="' . h(($place_tiles['url'] === PG_PLACE_TILES_DEFAULT_URL) ? '' : $place_tiles['url']) . '" placeholder="' . h(PG_PLACE_TILES_DEFAULT_URL) . '"/>
                                            <div class="form-text">' . h(lang('Picture tiles with {z}, {x} and {y} in the address. Empty uses OpenStreetMap\'s own tiles, which are free but may be limited for a busy site; a map provider gives an address of its own, with {key} where its key goes.')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="place_map_credit" class="form-label">' . h(lang('Credit Line')) . '</label>
                                            <input type="text" name="place_map_credit" id="place_map_credit" maxlength="' . PG_PLACE_TILES_CREDIT_MAX . '" class="form-control" value="' . h(($place_tiles['credit'] === PG_PLACE_TILES_DEFAULT_CREDIT) ? '' : $place_tiles['credit']) . '" placeholder="' . h(PG_PLACE_TILES_DEFAULT_CREDIT) . '"/>
                                            <div class="form-text">' . h(lang('Always printed in the corner of the map, as the tiles\' provider asks.')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="place_map_key" class="form-label">' . h(lang('Provider Key')) . '</label>
                                            <input type="password" name="place_map_key" id="place_map_key" maxlength="' . PG_PLACE_TILES_KEY_MAX . '" class="form-control" value="" autocomplete="new-password" placeholder="' . ($place_tiles['key_stored'] ? h(lang('Saved')) : '') . '"/>'
    . ($place_tiles['key_stored'] ? '
                                            <div class="form-field mb-0 mt-1">
                                                <input value="1" class="check" type="checkbox" id="place_map_key_clear" name="place_map_key_clear"/>
                                                <label class="fg-danger" for="place_map_key_clear">' . h(lang('Remove the saved value')) . '</label>
                                            </div>' : '') . '
                                            <div class="form-text">' . h(lang('Put into the address in place of {key}. Every visitor\'s browser receives it with the tiles, so use a key the provider limits to this site\'s address.')) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_maps(pg_place_tiles_switch_on(), $place_tiles['url'] !== PG_PLACE_TILES_DEFAULT_URL);
}

// Visitor notifications (includes/fn/site_push.php): off on every site, a new
// install too, until it is switched on here. Read and saved only through the
// module's one reader and saver. With it off the "Notify me" block draws
// nothing and site_push.php takes no subscription. App mode, its own switch
// that also starts off, works only beside it (pg_site_push_app_mode()).
if (function_exists('pg_site_push_ready') && pg_site_push_ready()) {
    $features_parts['site-push'] = array('html' => '
                        <div id="pgset-site-push" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . h(lang('Visitor notifications')) . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="site_push_card" value="1"/>
                                    <div class="row gy-12">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('A visitor presses the Notify Me block of the visual editor and gets your news as a notification in the browser, on a phone or a computer, without an account or an email address. The block shows only where the browser can subscribe. Messages are sent from Communication, Channels. Off: the block shows nothing and no new subscription is taken.')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . (pg_site_push_app_on() ? ' checked="checked"' : '') . ' id="site_push_app_on" name="site_push_app_on" aria-describedby="site_push_app_on_help"/>
                                                <label class="pg-text-2" for="site_push_app_on">' . h(lang('App mode')) . '</label>
                                            </div>
                                            <div class="form-text" id="site_push_app_on_help">' . h(lang('A visitor who adds the site to the home screen opens it as an app, without the browser\'s address bar. On an iPhone, notifications reach only a site added this way. A page that cannot be loaded without a connection shows an offline page. Works while visitor notifications are on and a site icon is set.')) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_site_push(pg_site_push_switch_on(), pg_site_push_app_on());
}

// Content changes that wait for approval (includes/fn/content_proposals.php):
// off on every site until it is switched on here, by a designer or an
// administrator only (a Manager's own saves are the ones held). The channel
// the change is also asked in is offered while the Workspace is on.
if ($features_content_approval_shown) {
    $content_approval_channel = '';

    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && pg_schema_has('ws_channels')) {
        $content_approval_options = '<option value="0">' . h(lang('None')) . '</option>';

        foreach ((array) db_items("SELECT id, name FROM ws_channels WHERE kind IN ('public', 'private') AND archived_at = 0 ORDER BY name") as $content_approval_row) {
            $content_approval_options .= '<option value="' . (int) $content_approval_row['id'] . '"'
                . (((int) $content_approval_row['id'] === $content_approval['channel_id']) ? ' selected="selected"' : '') . '>'
                . h($content_approval_row['name']) . '</option>';
        }

        $content_approval_channel = '
                                        <div class="col-12">
                                            <label class="form-label" for="content_approval_channel">' . lang('Also Ask in Channel') . '</label>
                                            <select class="form-control" id="content_approval_channel" name="content_approval_channel">' . $content_approval_options . '</select>
                                            <div class="form-text">' . lang('A waiting change is also asked there as an approval request, to the channel\'s designers.') . '</div>
                                        </div>';
    }

    $features_parts['content-approval'] = array('html' => '
                        <div id="pgset-content-approval" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Content approval') . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="content_approval_card" value="1"/>
                                    <div class="row gy-12">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . lang('A Manager or User who saves a change in the Page Design Studio to a page already on the site does not publish it: it waits until a Designer or Administrator applies it. Drafts, new pages, classic pages and the changes of Designers and Administrators are not held.') . '</div>
                                        </div>' . $content_approval_channel . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_content_approval($content_approval['on'], ($content_approval_channel !== '') && ($content_approval['channel_id'] > 0));
}

// How long the published revisions of visual-designer pages and the
// revisions of collection items are kept (includes/fn/page_revisions.php,
// includes/fn/record_revisions.php): the last N of each page or item
// whatever their age, and every one younger than D days. Shown once the 2026.11.0 columns
// exist; the defaults (30 and 90) apply meanwhile.
if (function_exists('pg_page_revisions_retention') && array_key_exists('page_revisions_keep', $row) && array_key_exists('page_revisions_days', $row)) {

    $page_revisions_retention = pg_page_revisions_retention((int) $row['page_revisions_keep'], (int) $row['page_revisions_days']);

    $features_parts['revisions'] = array('html' => '
                        <div id="pgset-revisions" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Page revisions') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . lang('Every publish of a Page Design Studio page is kept as a revision, which the History panel of the Studio compares and loads back; so is every save that changes an item of a collection. A revision is removed only when both limits below let it go; the general job does that once a day.') . '</div>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="page_revisions_keep" class="form-label">' . lang('Revisions Kept per Page or Item') . '</label>
                                            <input type="text" name="page_revisions_keep" id="page_revisions_keep" maxlength="3" inputmode="numeric" class="form-control text-end" value="' . h($page_revisions_retention['keep']) . '" />
                                            <div class="form-text">' . lang(array('string' => 'The newest ones, however old they are. 1 to {var:1}.', 'vars' => array(PG_PAGE_REVISIONS_KEEP_MAX))) . '</div>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="page_revisions_days" class="form-label">' . lang('Revisions Kept for') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('days') . '</span>
                                                <input type="text" name="page_revisions_days" id="page_revisions_days" maxlength="4" inputmode="numeric" class="form-ghost text-end" value="' . h($page_revisions_retention['days']) . '" />
                                            </label>
                                            <div class="form-text">' . lang(array('string' => 'Every revision younger than this, however many there are. 1 to {var:1}.', 'vars' => array(PG_PAGE_REVISIONS_DAYS_MAX))) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_revisions($page_revisions_retention['keep'], $page_revisions_retention['days']);
}

// ── Speed ──
//
// The page cache, Prefetch Menu Links and Performance Monitoring. The page
// lifetime is PAGE_CACHE_TTL in data/config.php rather than a config
// column, so it is known before any query (includes/fn/page_cache.php).
$speed_cache_fields = '';
$page_cache_ttl = null;

if (function_exists('pg_page_cache_ttl')) {
    $page_cache_ttl = pg_page_cache_ttl();

    $speed_cache_fields = '
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . (($page_cache_ttl > 0) ? ' checked="checked"' : '') . ' id="page_cache" name="page_cache" aria-describedby="page_cache_help"/>
                                                <label class="pg-text-2" for="page_cache">' . lang('Enable Page Cache') . '</label>
                                            </div>
                                            <div class="form-text" id="page_cache_help">' . lang('A page that looks the same for every visitor who is not signed in is kept on this server and sent from there, and goes out with standard cache headers, so a CDN or reverse proxy in front of the site (Cloudflare, Varnish, nginx) may keep it too. Pages with a form, a cart or member content, private pages, pages that run PHP, pages for a visitor with a cart, a sign-in or a cookie choice, and error pages are never kept. Off on a site whose mobile view is switched on. A visit answered from a kept page is counted by a small request the browser sends.') . '</div>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="page_cache_ttl" class="form-label">' . lang('Page Lifetime') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('seconds') . '</span>
                                                <input type="text" name="page_cache_ttl" id="page_cache_ttl" maxlength="4" inputmode="numeric" class="form-ghost text-end" value="' . h(($page_cache_ttl > 0) ? $page_cache_ttl : PG_PAGE_CACHE_TTL_DEFAULT) . '" />
                                            </label>
                                            <div class="form-text">' . lang(array('string' => 'A change to a page reaches visitors within this time. {var:1} to {var:2}.', 'vars' => array(PG_PAGE_CACHE_TTL_MIN, PG_PAGE_CACHE_TTL_MAX))) . '</div>
                                        </div>';
}

$speed_prefetch_on = !empty($row['speculation_prefetch']);

$features_parts['speed'] = array('html' => '
                        <div id="pgset-page-cache" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Speed') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">' . $speed_cache_fields
    . (array_key_exists('speculation_prefetch', $row) ? '
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . ($speed_prefetch_on ? ' checked="checked"' : '') . ' id="speculation_prefetch" name="speculation_prefetch" aria-describedby="speculation_prefetch_help"/>
                                                <label class="pg-text-2" for="speculation_prefetch">' . lang('Prefetch Menu Links') . '</label>
                                            </div>
                                            <div class="form-text" id="speculation_prefetch_help">' . lang('Pages tell the browser the addresses in their menus, so a page a visitor points at or presses is fetched a moment before it is opened and appears at once. Chrome and Edge do this; other browsers ignore it. Not for signed-in members and staff, and never for sign-in, cart, download or panel links. A page fetched this way and then opened is counted once.') . '</div>
                                        </div>' : '') . '
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . $perf_monitor_checked . ' id="perf_monitor" name="perf_monitor" aria-describedby="perf_monitor_help"/>
                                                <label class="pg-text-2" for="perf_monitor">' . lang('Enable Performance Monitoring') . '</label>
                                            </div>
                                            <div class="form-text" id="perf_monitor_help">' . lang('Records how long each request takes, so slow pages can be found. The measurement is written after the response has been sent, so visitors do not wait for it.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_speed($page_cache_ttl, $mobile_checked !== '', $speed_prefetch_on, $perf_monitor_checked !== '');

// ── Signature time stamp ──
//
// The address is a setting and not a line in data/config.php because it is an
// ordinary choice an operator makes, and it is here rather than under Security
// because it belongs with the other "how this software handles a kind of
// content" settings, next to image handling.
$signature_tsa_url = isset($row['signature_tsa_url']) ? (string) $row['signature_tsa_url'] : '';
$signature_tsa_auth = (isset($row['signature_tsa_auth']) && ($row['signature_tsa_auth'] === 'basic')) ? 'basic' : 'none';
$signature_tsa_username = isset($row['signature_tsa_username']) ? (string) $row['signature_tsa_username'] : '';
// Read only to tell whether one is saved: the password is never sent back to
// the browser (pg_signature_tsa_password_to_store()).
$signature_tsa_password = isset($row['signature_tsa_password']) ? (string) $row['signature_tsa_password'] : '';

$features_parts['signature'] = array('html' => '
                        <div id="pgset-signature" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Signature time stamp') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . lang('A signature field records when it was signed from this server\'s clock, which is this site\'s own word for it. A time stamp authority vouches for the same moment independently. Only a 32 byte digest is sent - the document itself never leaves this server.') . '</div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="signature_tsa_url" class="form-label">' . lang('Time Stamp Authority Address') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="signature_tsa_url" id="signature_tsa_url" maxlength="255" class="form-control" value="' . h($signature_tsa_url) . '" placeholder="https://tsa.example/tsr" />
                                            <div class="form-text">' . lang('Leave empty to take no time stamp; signatures are stored exactly as they are today. Free authorities produce a real, verifiable token but are not licensed providers, so their stamp is not a "qualified time stamp" in the sense Turkish law gives that term.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_auth" class="form-label">' . lang('Credentials') . '</label>
                                            <select name="signature_tsa_auth" id="signature_tsa_auth" class="form-control">
                                                <option value="none"' . (($signature_tsa_auth === 'none') ? ' selected="selected"' : '') . '>' . lang('None') . '</option>
                                                <option value="basic"' . (($signature_tsa_auth === 'basic') ? ' selected="selected"' : '') . '>' . lang('HTTP Basic') . '</option>
                                            </select>
                                            <div class="form-text">' . lang('Free authorities want none. A provider selling stamps against an account generally takes HTTP Basic; Kamu SM does neither and is not offered yet.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_username" class="form-label">' . lang('User Name') . '</label>
                                            <input type="text" spellcheck="false" autocapitalize="off" name="signature_tsa_username" id="signature_tsa_username" maxlength="100" class="form-control" value="' . h($signature_tsa_username) . '" />
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_password" class="form-label">' . lang('Password') . '</label>
                                            <input type="password" name="signature_tsa_password" id="signature_tsa_password" maxlength="100" class="form-control" value="" autocomplete="new-password" placeholder="' . (($signature_tsa_password !== '') ? h(lang('Saved')) : '') . '" />'
    . (($signature_tsa_password !== '') ? '
                                            <div class="form-field mb-0 mt-1">
                                                <input value="1" class="check" type="checkbox" id="signature_tsa_password_clear" name="signature_tsa_password_clear" />
                                                <label class="fg-danger" for="signature_tsa_password_clear">' . lang('Remove the saved value') . '</label>
                                            </div>' : '') . '
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="submit_test_tsa" value="1" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-clock-history"></i>' . lang('Test The Time Stamp') . '
                                            </button>
                                            <div class="form-text">' . lang('Saves this card and then asks the authority to stamp a throwaway digest, so the answer describes what was just entered. No counter is spent beyond that one stamp.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_features_fold_signature($signature_tsa_url);

// ── The rows ──
$features_rows = array();

foreach (pg_features_fold_order() as $features_key) {
    if (isset($features_parts[$features_key])) {
        $features_rows[] = array('key' => $features_key) + pg_features_fold_face($features_key) + $features_parts[$features_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($features_rows);
