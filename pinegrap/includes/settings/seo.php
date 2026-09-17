<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the SEO cards: how the site reads from outside, and what the traffic to it looks like.
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

// ── Search Engine Optimization ──
$pg_settings_cards[] = '
                        <div id="pgset-seo" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Search Engine Optimization') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="pg-f-lg">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" maxlength="255" value="' . h($title) . '"/>
                                            <div id="seo_c_title"></div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" >' . h($meta_description) . '</textarea>
                                            <div id="seo_c_meta_description"></div>
                                        </div>
                                        <div id="pgsub-sitemap" class="col-12  mb-0"><h6 class="text-muted text-uppercase">' . lang('Site Map and Crawling') . '</h6></div>
                                        <div class="pg-f-lg">
                                            <label for="additional_sitemap_content" class="form-label">' . lang('Additional sitemap.xml Content') . '</label>
                                            <textarea name="additional_sitemap_content" id="additional_sitemap_content" class="form-control" >' . h($additional_sitemap_content) . '</textarea>
                                            ' . get_codemirror_javascript(array('id' => 'additional_sitemap_content', 'code_type' => 'xml.text')) . '
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="additional_robots_content" class="form-label">' . lang('Additional robots.txt Content') . '</label>
                                            <textarea name="additional_robots_content" id="additional_robots_content" class="form-control" >' . h($additional_robots_content) . '</textarea>
                                            ' . get_codemirror_javascript(array('id' => 'additional_robots_content', 'code_type' => 'plain')) . '
                                            <div class="">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="pgRobotsNoIndex()" title="' . lang('Adds the rule that closes the entire site to search engines.') . '"><i class="bi bi-slash-circle me-1"></i>' . lang('Block All Search Engines') . '</button>
                                                <div class="form-text text-warning d-none" id="robots_noindex_hint"></div>
                                            </div>
                                        </div>

                                        <div class="pg-f-lg">
                                            <label for="indexnow_key" class="form-label">' . lang('IndexNow Key') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="indexnow_key" id="indexnow_key" class="form-control" value="' . h($indexnow_key) . '"/>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="generateIndexNowKey()"><i class="bi bi-key-fill me-1"></i>' . lang('Generate') . '</button>
                                            </div>
                                            <div class="form-text">' . lang('If you provide an IndexNow Key, search engines that support IndexNow will be automatically notified when your sitemap.xml file is updated.') . ' 
                                                <a class="link-secondary" href="https://www.indexnow.org/" target="_blank">' . lang('Learn more about IndexNow') . '</a>
                                            </div>
                                        </div>
                                        ' . $output_app_icon_picker . '
                                        ' . $output_share_image_pickers . '
                                        ' . $output_structured_data_section . '
                                    </div>
                                </div>
                            </div>
                        </div>';

// The SEO card brings its own two scripts. They were page-level on the single
// settings screen; they belong to the card that uses them, and nothing else
// on the site calls either one.
$pg_settings_scripts[] = '
    <script>
        // SEO character counters — logic in assets/js/backend.src.js
        initSeoCounters([
            { sel: "#title",            counterId: "seo_c_title",            min: 50,  max: 60  },
            { sel: "#meta_description", counterId: "seo_c_meta_description", min: 150, max: 160 }
        ]);

        // Fills the additional robots.txt field with the rule that blocks every crawler.
        // The field is managed by CodeMirror, which hides the original textarea and keeps
        // its own document, so writing to textarea.value alone would not show up on screen
        // and would be overwritten on submit.
        function pgRobotsNoIndex() {
            var snippet = "User-agent: *\\nDisallow: /";
            var textarea = document.getElementById("additional_robots_content");
            var hint = document.getElementById("robots_noindex_hint");
            if (!textarea) {
                return;
            }

            // CodeMirror.fromTextArea() inserts its wrapper right after the textarea and
            // exposes the instance on that element. The walk is bounded and stays inside
            // this column, so it can never pick up the sitemap editor next door.
            var cm = null;
            var sibling = textarea.nextElementSibling;
            var hops = 0;
            while (sibling && hops < 3) {
                if (sibling.CodeMirror) {
                    cm = sibling.CodeMirror;
                    break;
                }
                sibling = sibling.nextElementSibling;
                hops++;
            }
            var current = cm ? cm.getValue() : textarea.value;

            var already = /^\\s*User-agent:\\s*\\*\\s*$[\\s\\S]*?^\\s*Disallow:\\s*\\/\\s*$/mi.test(current);
            if (already) {
                if (hint) {
                    hint.textContent = ' . json_encode(lang('This rule has already been added.')) . ';
                    hint.classList.remove("d-none");
                }
                return;
            }

            // Keep whatever the operator already wrote, append below it.
            var updated = (current.replace(/\\s+$/, "") === "") ? snippet : current.replace(/\\s+$/, "") + "\\n\\n" + snippet;
            if (cm) {
                cm.setValue(updated);
            } else {
                textarea.value = updated;
            }
            if (hint) {
                hint.textContent = ' . json_encode(lang('The entire site will be closed to search engines. It takes effect once you save.')) . ';
                hint.classList.remove("d-none");
            }
        }
    </script>';

// ── Visitor Tracking ──
$pg_settings_cards[] = '
                        <div id="pgset-analytics" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Visitor Tracking') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $visitor_tracking_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="visitor_tracking" name="visitor_tracking" data-bs-target="#visitor_tracking_row"/>
                                                <label class="form-check-label" for="visitor_tracking">' . lang('Enable Visitor Tracking') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="visitor_tracking_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="pg-f-xs">
                                                            <label for="statracking_code_durationrt" class="form-label">*' . lang('Tracking Code Duration') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" required name="tracking_code_duration" id="tracking_code_duration" class="form-control" value="' . h($tracking_code_duration) . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="1" style="text-align: right;" min="1" max="365" />
                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="pay_per_click_flag" class="form-label">' . lang('Pay Per Click Tracking Code Flag') . '</label>
                                                            <input type="text" name="pay_per_click_flag" id="pay_per_click_flag" class="form-control" value="' . h($pay_per_click_flag) . '" />
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $google_analytics_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="google_analytics" name="google_analytics" data-bs-target="#google_analytics_row"/>
                                                                <label class="form-check-label" for="google_analytics">' . lang('Enable Google Analytics') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 " id="google_analytics_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-3">
                                                                    <div class="col-12 ">
                                                                        <label for="stats_url" class="form-label">' . lang('Website Analytics URL') . '</label>
                                                                        <input type="text" name="stats_url" id="stats_url" class="form-control" value="' . h($stats_url) . '" />
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <label for="google_analytics_web_property_id" class="form-label">' . lang('Web Property ID') . '</label>
                                                                        <input type="text" name="google_analytics_web_property_id" id="google_analytics_web_property_id" class="form-control" value="' . $google_analytics_web_property_id. '" maxlength="50"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';
