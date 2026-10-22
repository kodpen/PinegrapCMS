<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Features cards: which parts of the software are switched on, and how images are handled.
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

// ── Feature Options ──
$pg_settings_cards[] = '
                        <div id="pgset-features" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Feature Options') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="pg-f-md">
                                            <label for="badge_label" class="form-label">' . lang('Default Badge Label') . '</label>
                                            <input type="text" name="badge_label" id="badge_label" maxlength="100" class="form-control" value="' . h($badge_label) . '" />
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Site Search Type') . '</label>
                                            <div class="form-check">
                                                <input value="simple" class="form-check-input" type="radio" id="search_type_simple" name="search_type" ' . $search_type_simple_checked . '>
                                                <label class="form-check-label" for="search_type_simple">'. lang('Simple') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="advanced" class="form-check-input" type="radio" id="search_type_advanced" name="search_type" ' . $search_type_advanced_checked . '>
                                                <label class="form-check-label" for="search_type_advanced">'. lang('Advanced') . '</label>
                                            </div>
                                            <div class="form-text">' . lang('Advanced searches the search index, which the search index job rebuilds once a day: pages, linked files and the records of public form lists. Simple searches the text of pages as it is now and finds no form records.') . '</div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $perf_monitor_checked . ' class="form-check-input" type="checkbox" id="perf_monitor" name="perf_monitor"/>
                                                <label class="form-check-label" for="perf_monitor">' . lang('Enable Performance Monitoring') . '</label>
                                                <div class="form-text">' . lang('Records how long each request takes, so slow pages can be found. The measurement is written after the response has been sent, so visitors do not wait for it.') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $mobile_checked . ' class="form-check-input" type="checkbox" id="mobile" name="mobile"/>
                                                <label class="form-check-label" for="mobile">' . lang('Enable Mobile') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $auto_dialogs_checked . ' class="form-check-input" type="checkbox" id="auto_dialogs" name="auto_dialogs"/>
                                                <label class="form-check-label" for="auto_dialogs">' . lang('Enable Auto Dialogs') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $social_networking_checked . '  id="social_networking" name="social_networking" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#social_networking_type_row"/>
                                                <label class="form-check-label d-inline" for="social_networking">' . lang('Enable Social Networking') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0  w-100" id="social_networking_type_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <label class="form-label">'. lang('Setup') . '</label>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-check-inline">
                                                                <input value="simple" class="form-check-input collapse-switcher" type="radio" id="social_networking_type_simple" name="social_networking_type" ' . $social_networking_type_simple_checked . ' data-bs-target="#social_networking_services_row">
                                                                <label class="form-check-label" for="social_networking_type_simple">'. lang('Simple') . '</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input value="advanced" class="form-check-input collapse-switcher" type="radio" id="social_networking_type_advanced" name="social_networking_type" ' . $social_networking_type_advanced_checked . ' data-bs-target="#social_networking_code_row">
                                                                <label class="form-check-label" for="social_networking_type_advanced">'. lang('Advanced') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="social_networking_services_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(15px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <label class="form-label">'. lang('Services') . '</label>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_facebook" id="social_networking_facebook" value="1"' . $social_networking_facebook_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_facebook">Facebook</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_twitter" id="social_networking_twitter" value="1"' . $social_networking_twitter_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_twitter">X (Twitter)</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_linkedin" id="social_networking_linkedin" value="1"' . $social_networking_linkedin_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_linkedin">LinkedIn</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_whatsapp" id="social_networking_whatsapp" value="1"' . $social_networking_whatsapp_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_whatsapp">WhatsApp</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_telegram" id="social_networking_telegram" value="1"' . $social_networking_telegram_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_telegram">Telegram</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_pinterest" id="social_networking_pinterest" value="1"' . $social_networking_pinterest_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_pinterest">Pinterest</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_reddit" id="social_networking_reddit" value="1"' . $social_networking_reddit_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_reddit">Reddit</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_email" id="social_networking_email" value="1"' . $social_networking_email_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_email">'. lang('Email') . '</label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="social_networking_code_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(90px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <label for="social_networking_code" class="form-label">'. lang('Code') . '</label>
                                                                            <textarea class="form-control" id="social_networking_code" name="social_networking_code">' . h($social_networking_code) . '</textarea>
                                                                            ' . pg_code_editor_attach(array('id' => 'social_networking_code', 'code_type' => 'mixed')) . '
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $forms_checked . ' class="form-check-input" type="checkbox" id="forms" name="forms"/>
                                                <label class="form-check-label" for="forms">' . lang('Enable Forms') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $calendars_checked . ' class="form-check-input" type="checkbox" id="calendars" name="calendars"/>
                                                <label class="form-check-label" for="calendars">' . lang('Enable Calendars') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $ads_checked . ' class="form-check-input" type="checkbox" id="ads" name="ads"/>
                                                <label class="form-check-label" for="ads">' . lang('Enable Ads') . '</label>
                                            </div>
                                        </div>
                                        ' . ($workspace_setting_available ? '
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $workspace_enabled_checked . ' class="form-check-input" type="checkbox" id="workspace_enabled" name="workspace_enabled"/>
                                                <label class="form-check-label" for="workspace_enabled">' . lang('Enable Workspace') . '</label>
                                                <div class="form-text">' . lang('Channels for the team, tasks with owners and dates, and a planning board. Basic users are given it from their user screen.') . '</div>
                                            </div>
                                        </div>' : '') . '
                                   </div>
                                </div>
                            </div>
                        </div>';

// Hidden entirely on an install that has not run the 2026.4.4 upgrade: the
// defaults are already in force there, so the block would show numbers that
// look editable and then save into columns that do not exist.


    // ── Image limits ────────────────────────────────────
    //
    // Hidden entirely on an install that has not run the 2026.4.4 upgrade.
    // The defaults are already in force there, so the block would show numbers
    // that look editable and then save into columns that do not exist.
    $output_image_settings_card = '';

    if ($image_settings_ready) {

        $image_product_optimize_checked = ($image_settings['product_optimize'] == 1) ? ' checked="checked"' : '';

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
                : '<div class="alert alert-warning py-2 mb-0">' . lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.') . '</div>';

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

                $image_format_note = empty($image_formats_written[$image_format])
                    ? '<div class="form-text text-warning-emphasis">' . lang(array('string' => 'This server cannot write {var:1}, so it is not offered while that stays so.', 'vars' => array($image_format_row['name']))) . '</div>'
                    : '';

                $output_image_delivery_fields .= '
                            <div class="col-12 ">
                                <div class="form-check form-switch">
                                    <input value="1"' . $image_format_checked . ' class="form-check-input" type="checkbox" id="image_derivative_' . $image_format . '" name="image_derivative_' . $image_format . '"/>
                                    <label class="form-check-label" for="image_derivative_' . $image_format . '">' . $image_format_row['label'] . '</label>
                                    <div class="form-text">' . $image_format_row['text'] . '</div>' . $image_format_note . '
                                </div>
                            </div>';
            }

            $output_image_delivery_fields .= '
                            <div class="pg-f-xs">
                                <label for="image_quality_webp" class="form-label">' . lang('WebP Quality') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_quality_webp" id="image_quality_webp" maxlength="3" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['quality_webp']) . '" />
                                    <label class="input-group-text" for="image_quality_webp">%</label>
                                </div>
                                <div class="form-text">' . lang('A changed quality makes the pictures again as they are next asked for. Resized JPEG and PNG pictures use the quality when resizing above.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_quality_avif" class="form-label">' . lang('AVIF Quality') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_quality_avif" id="image_quality_avif" maxlength="3" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['quality_avif']) . '" />
                                    <label class="input-group-text" for="image_quality_avif">%</label>
                                </div>
                                <div class="form-text">' . lang('AVIF holds up at a lower number than WebP, so its quality is set apart.') . '</div>
                            </div>';
        }

        // The visual designer's pages keep the HTML drawn when they were
        // saved; this draws them all again (pg_designer_rebuild_page_code()).
        $output_page_rebuild_field = '';

        if ($page_rebuild_ready) {
            $output_page_rebuild_field = '
                            <div class="col-12 ">
                                <button type="submit" name="submit_rebuild_pages" value="1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Save and rebuild page HTML') . '</button>
                                <div class="form-text">' . lang('Visual designer pages keep the HTML drawn when they were last saved. This draws every page again from what was saved, so pages published before responsive images, or before a format was switched on or off, get the current picture markup. Their content, modified date and author do not change.') . '</div>
                            </div>';
        }

        $output_image_settings_card =
            '<div id="pgset-images" class="pg-set-card">
                <div class="card">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Image Optimization') . '
                    </div>
                    <div class="card-body">
                       <div class="row gy-3">
                            <div class="col-12 ">
                                <div class="form-check form-switch">
                                    <input value="1"' . $image_product_optimize_checked . ' class="form-check-input" type="checkbox" id="image_product_optimize" name="image_product_optimize"/>
                                    <label class="form-check-label" for="image_product_optimize">' . lang('Optimize Product Images On Upload') . '</label>
                                    <div class="form-text">' . lang('Photos added from the product and variant set screens are compressed as they arrive, and scaled down when they are larger than the limit below. Images picked from the file library are left exactly as they are.') . '</div>
                                </div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_product_max_dimension" class="form-label">' . lang('Product Image Size Limit') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_product_max_dimension" id="image_product_max_dimension" maxlength="5" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['product_max_dimension']) . '" />
                                    <label class="input-group-text" for="image_product_max_dimension">' . lang('pixels') . '</label>
                                </div>
                                <div class="form-text">' . lang('Longest edge. A product photo above this is scaled down on upload, keeping its proportions.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_product_min_dimension" class="form-label">' . lang('Smallest Acceptable Product Image') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_product_min_dimension" id="image_product_min_dimension" maxlength="5" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['product_min_dimension']) . '" />
                                    <label class="input-group-text" for="image_product_min_dimension">' . lang('pixels') . '</label>
                                </div>
                                <div class="form-text">' . lang('Shortest edge. Smaller images are still accepted; the product screen and the SEO details point them out. Google Merchant Center requires 500 pixels from 2027.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_resize_quality" class="form-label">' . lang('Quality When Resizing') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_resize_quality" id="image_resize_quality" maxlength="3" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['resize_quality']) . '" />
                                    <label class="input-group-text" for="image_resize_quality">%</label>
                                </div>
                                <div class="form-text">' . lang('Applies only when an image is actually scaled down. Optimizing without resizing keeps its own quality setting.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_file_resize_trigger" class="form-label">' . lang('Offer To Resize Files Above') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_file_resize_trigger" id="image_file_resize_trigger" maxlength="5" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['file_resize_trigger']) . '" />
                                    <label class="input-group-text" for="image_file_resize_trigger">' . lang('pixels') . '</label>
                                </div>
                                <div class="form-text">' . lang('On the Files screen the resize button appears only for images whose longest edge is above this.') . '</div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="image_file_max_dimension" class="form-label">' . lang('Resize Files Down To') . '</label>
                                <div class="input-group">
                                    <input type="text" name="image_file_max_dimension" id="image_file_max_dimension" maxlength="5" inputmode="numeric" class="form-control text-end" value="' . h($image_settings['file_max_dimension']) . '" />
                                    <label class="input-group-text" for="image_file_max_dimension">' . lang('pixels') . '</label>
                                </div>
                                <div class="form-text">' . lang('Files are never resized on upload and never by the plain optimize button. This is only used when the resize button is pressed.') . '</div>
                            </div>' . $output_image_delivery_fields . $output_page_rebuild_field . '
                            <div class="col-12 ">
                                ' . $output_image_library_note . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }
if ($output_image_settings_card !== '') {
    $pg_settings_cards[] = $output_image_settings_card;
}

// ── Page Revisions ──
//
// How long the published revisions of visual-designer pages are kept
// (includes/fn/page_revisions.php): the last N of each page whatever their
// age, and every one younger than D days. Shown once the 2026.11.0 columns
// exist; the defaults (30 and 90) apply meanwhile.
if (function_exists('pg_page_revisions_retention') && array_key_exists('page_revisions_keep', $row) && array_key_exists('page_revisions_days', $row)) {

    $page_revisions_retention = pg_page_revisions_retention((int) $row['page_revisions_keep'], (int) $row['page_revisions_days']);

    $pg_settings_cards[] = '
                        <div id="pgset-revisions" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Page Revisions') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-3">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . lang('Every publish of a visual designer page is kept as a revision, which the History panel of the editor compares and loads back. A revision is removed only when both limits below let it go; the general job does that once a day.') . '</div>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="page_revisions_keep" class="form-label">' . lang('Revisions Kept per Page') . '</label>
                                            <input type="text" name="page_revisions_keep" id="page_revisions_keep" maxlength="3" inputmode="numeric" class="form-control text-end" value="' . h($page_revisions_retention['keep']) . '" />
                                            <div class="form-text">' . lang(array('string' => 'The newest ones, however old they are. 1 to {var:1}.', 'vars' => array(PG_PAGE_REVISIONS_KEEP_MAX))) . '</div>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="page_revisions_days" class="form-label">' . lang('Revisions Kept for') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="page_revisions_days" id="page_revisions_days" maxlength="4" inputmode="numeric" class="form-control text-end" value="' . h($page_revisions_retention['days']) . '" />
                                                <label class="input-group-text" for="page_revisions_days">' . lang('days') . '</label>
                                            </div>
                                            <div class="form-text">' . lang(array('string' => 'Every revision younger than this, however many there are. 1 to {var:1}.', 'vars' => array(PG_PAGE_REVISIONS_DAYS_MAX))) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

// ── Signature Time Stamp ──
//
// The address is a setting and not a line in data/config.php because it is an
// ordinary choice an operator makes, and it is here rather than under Security
// because it belongs with the other "how this software handles a kind of
// content" settings, next to image handling.
$signature_tsa_url = isset($row['signature_tsa_url']) ? (string) $row['signature_tsa_url'] : '';
$signature_tsa_auth = (isset($row['signature_tsa_auth']) && ($row['signature_tsa_auth'] === 'basic')) ? 'basic' : 'none';
$signature_tsa_username = isset($row['signature_tsa_username']) ? (string) $row['signature_tsa_username'] : '';
$signature_tsa_password = isset($row['signature_tsa_password']) ? (string) $row['signature_tsa_password'] : '';

$pg_settings_cards[] = '
                        <div id="pgset-signature" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Signature Time Stamp') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-3">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . lang('A signature field records when it was signed from this server\'s clock, which is this site\'s own word for it. A time stamp authority vouches for the same moment independently. Only a 32 byte digest is sent - the document itself never leaves this server.') . '</div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="signature_tsa_url" class="form-label">' . lang('Time Stamp Authority Address') . '</label>
                                            <input type="text" name="signature_tsa_url" id="signature_tsa_url" maxlength="255" class="form-control" value="' . h($signature_tsa_url) . '" placeholder="https://sunucu.example/tsr" />
                                            <div class="form-text">' . lang('Leave empty to take no time stamp; signatures are stored exactly as they are today. Free authorities produce a real, verifiable token but are not licensed providers, so their stamp is not a "qualified time stamp" in the sense Turkish law gives that term.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_auth" class="form-label">' . lang('Credentials') . '</label>
                                            <select name="signature_tsa_auth" id="signature_tsa_auth" class="form-select">
                                                <option value="none"' . (($signature_tsa_auth === 'none') ? ' selected="selected"' : '') . '>' . lang('None') . '</option>
                                                <option value="basic"' . (($signature_tsa_auth === 'basic') ? ' selected="selected"' : '') . '>' . lang('HTTP Basic') . '</option>
                                            </select>
                                            <div class="form-text">' . lang('Free authorities want none. A provider selling stamps against an account generally takes HTTP Basic; Kamu SM does neither and is not offered yet.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_username" class="form-label">' . lang('User Name') . '</label>
                                            <input type="text" name="signature_tsa_username" id="signature_tsa_username" maxlength="100" class="form-control" value="' . h($signature_tsa_username) . '" />
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="signature_tsa_password" class="form-label">' . lang('Password') . '</label>
                                            <input type="password" name="signature_tsa_password" id="signature_tsa_password" maxlength="100" class="form-control" value="' . h($signature_tsa_password) . '" />
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="submit_test_tsa" value="1" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-clock-history me-1"></i>' . lang('Test The Time Stamp') . '
                                            </button>
                                            <div class="form-text">' . lang('Saves this card and then asks the authority to stamp a throwaway digest, so the answer describes what was just entered. No counter is spent beyond that one stamp.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
