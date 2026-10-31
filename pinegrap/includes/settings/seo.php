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
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Search Engine Optimization') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="pg-f-lg">
                                            <label for="setting_title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="setting_title" class="form-control" maxlength="255" value="' . h($title) . '"/>
                                            <div id="setting_seo_c_title"></div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="setting_meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="setting_meta_description" class="form-control" maxlength="255" >' . h($meta_description) . '</textarea>
                                            <div id="setting_seo_c_meta_description"></div>
                                        </div>
                                        <div id="pgsub-sitemap" class="col-12  mb-0"><h6 class="fg-secondary text-uppercase">' . lang('Site Map and Crawling') . '</h6></div>
                                        <div class="pg-f-lg">
                                            <label for="additional_sitemap_content" class="form-label">' . lang('Additional sitemap.xml Content') . '</label>
                                            <textarea name="additional_sitemap_content" id="additional_sitemap_content" class="form-control" >' . h($additional_sitemap_content) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'additional_sitemap_content', 'code_type' => 'xml.text')) . '
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="additional_robots_content" class="form-label">' . lang('Additional robots.txt Content') . '</label>
                                            <textarea name="additional_robots_content" id="additional_robots_content" class="form-control" >' . h($additional_robots_content) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'additional_robots_content', 'code_type' => 'plain')) . '
                                            <div class="">
                                                <button type="button" class="btn btn-sm btn-outline theme-secondary" onclick="pgRobotsNoIndex()" title="' . lang('Adds the rule that closes the entire site to search engines.') . '"><i class="bi bi-slash-circle me-1"></i>' . lang('Block All Search Engines') . '</button>
                                                <div class="form-text fg-warning d-none" id="robots_noindex_hint"></div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($max_image_preview_large ? ' checked="checked"' : '') . ' class="" type="checkbox" id="max_image_preview_large" name="max_image_preview_large"/></div>
                                                <label class="" for="max_image_preview_large">' . lang('Allow large image previews in search results') . '</label>
                                                <div class="form-text">' . lang('Adds max-image-preview:large to the robots meta tag of every page in the search engines, so a page can be shown with its large picture, as Google Discover does. Pages closed to search engines never get it.') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($main_host_redirect ? ' checked="checked"' : '') . ' class="" type="checkbox" id="main_host_redirect" name="main_host_redirect"/></div>
                                                <label class="" for="main_host_redirect">' . lang('Redirect other host names to the site address') . '</label>
                                                <div class="form-text">' . h(lang(array('string' => 'A page opened on another name of this site (with or without www) moves to the same address on {var:1} with a permanent redirect, so search engines see one copy of each page. Turn it on only once that name opens this site. The panel, the API and file downloads answer on every name.', 'vars' => array(HOSTNAME_SETTING)))) . '</div>
                                            </div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="indexnow_key" class="form-label">' . lang('IndexNow Key') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="indexnow_key" id="indexnow_key" class="form-control" value="' . h($indexnow_key) . '"/>
                                                <button type="button" class="btn btn-sm btn-outline theme-secondary" onclick="generateIndexNowKey()"><i class="bi bi-key-fill me-1"></i>' . lang('Generate') . '</button>
                                            </div>
                                            <div class="form-text">' . lang('If you provide an IndexNow Key, search engines that support IndexNow will be automatically notified when your sitemap.xml file is updated.') . ' 
                                                <a class="pg-link-secondary" href="https://www.indexnow.org/" target="_blank">' . lang('Learn more about IndexNow') . '</a>
                                            </div>
                                        </div>
                                        ' . $output_site_icon_picker . '
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
            { sel: "#setting_title",            counterId: "setting_seo_c_title",            min: 50,  max: 60  },
            { sel: "#setting_meta_description", counterId: "setting_seo_c_meta_description", min: 150, max: 160 }
        ]);

        // Fills the additional robots.txt field with the rule that blocks every crawler.
        // The field is the code editor (pg_code_editor_attach()), which hides the
        // textarea and keeps its own document, so the text is written through the
        // editor; it writes the textarea back itself.
        function pgRobotsNoIndex() {
            var snippet = "User-agent: *\\nDisallow: /";
            var textarea = document.getElementById("additional_robots_content");
            var hint = document.getElementById("robots_noindex_hint");
            if (!textarea) {
                return;
            }

            var cm = (window.pgCodeEditors && window.pgCodeEditors.additional_robots_content) || null;
            if (cm && cm.isDestroyed && cm.isDestroyed()) {
                cm = null;
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
                // In the undo history: Ctrl+Z in the field takes the rule back out.
                cm.setValue(updated, true);
            } else {
                textarea.value = updated;
            }
            if (hint) {
                hint.textContent = ' . json_encode(lang('The entire site will be closed to search engines. It takes effect once you save.')) . ';
                hint.classList.remove("d-none");
            }
        }
    </script>';

// ── AI Crawlers ──
// What robots.txt tells AI crawlers, and llms.txt (includes/fn/ai_crawlers.php).
// Whether assistants and AI search may come is the firewall's pair of
// switches: shown here and changed there, so robots.txt and the firewall never
// say different things. The fields are drawn before the 11.0.21 step as well;
// seo.save.php writes nothing until the columns are there.
$ai_crawlers_catalog = pg_ai_crawler_catalog();
$ai_crawlers_agents = implode(', ', $ai_crawlers_catalog['agent']);
$ai_crawlers_search = implode(', ', $ai_crawlers_catalog['search']);

$ai_crawlers_agents_text = $ai_crawlers['agents']
    ? lang(array('string' => 'Assistants ({var:1}): let in by the firewall.', 'vars' => array($ai_crawlers_agents)))
    : lang(array('string' => 'Assistants ({var:1}): turned away by the firewall.', 'vars' => array($ai_crawlers_agents)));

$ai_crawlers_search_text = $ai_crawlers['search']
    ? lang(array('string' => 'AI search ({var:1}): let in by the firewall.', 'vars' => array($ai_crawlers_search)))
    : lang(array('string' => 'AI search ({var:1}): turned away by the firewall.', 'vars' => array($ai_crawlers_search)));

$pg_settings_cards[] = '
                        <div id="pgset-ai" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('AI Crawlers') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-text">' . lang('What the site tells AI tools: in robots.txt, how they may use its pages and which of their crawlers should stay away; in llms.txt, where to start reading. Both are requests that well-behaved crawlers follow; the firewall is what turns a crawler away.') . '</div>' . (!$ai_crawlers['ready'] ? '
                                            <div class="form-text fg-warning">' . h(lang('These settings become available once the database upgrade has run.')) . '</div>' : '') . '
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($ai_crawlers['rules'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="robots_ai_rules" name="robots_ai_rules"/></div>
                                                <label class="" for="robots_ai_rules">' . lang('Write AI crawler rules into robots.txt') . '</label>
                                                <div class="form-text">' . lang('Adds a Content-Signal line that says how AI tools may use the pages, and a group that asks each AI crawler not allowed here to stay away. A crawler the Additional robots.txt Content field already has a group for is left to that field.') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($ai_crawlers['training'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="robots_ai_training" name="robots_ai_training"/></div>
                                                <label class="" for="robots_ai_training">' . lang('Allow AI training') . '</label>
                                                <div class="form-text">' . h(lang(array('string' => 'Lets {var:1} use the pages to train AI models. Turned off, robots.txt asks them to stay away and says ai-train=no. The firewall turns GPTBot, ClaudeBot and CCBot away either way, unless they are listed under Allowed Bots.', 'vars' => array(implode(', ', $ai_crawlers_catalog['train']))))) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . h($ai_crawlers_agents_text) . '</div>
                                            <div class="form-text">' . h($ai_crawlers_search_text) . '</div>
                                            <div class="form-text">' . lang('robots.txt says the same for them.') . ' <a class="pg-link-secondary" href="' . h(pg_settings_link('firewall', 'pgset-waf')) . '">' . lang('Change under Firewall') . '</a></div>
                                        </div>
                                        <div class="col-12 mb-0"><h6 class="fg-secondary text-uppercase">llms.txt</h6></div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($ai_crawlers['llms'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="llms_txt" name="llms_txt"/></div>
                                                <label class="" for="llms_txt">' . lang('Publish llms.txt') . '</label>
                                                <div class="form-text">' . lang('A short guide to the site for AI tools at /llms.txt: the organization name and the web browser description, then up to 50 of the pages the site map lists, the home page and the most visited first. Google does not read it; some AI tools do. Turned on, it is answered instead of a file of that name in the file manager.') . '</div>
                                            </div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="additional_llms_content" class="form-label">' . lang('Additional llms.txt Content') . '</label>
                                            <textarea name="additional_llms_content" id="additional_llms_content" class="form-control" >' . h($ai_crawlers['llms_text']) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'additional_llms_content', 'code_type' => 'plain')) . '
                                            <div class="form-text">' . lang('Markdown, written after the description. A part that starts with a ## heading gives AI tools a list of links of your own.') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <a class="btn btn-sm btn-outline theme-secondary" href="' . h(PATH . 'robots.txt') . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>robots.txt</a>' . ($ai_crawlers['llms'] ? '
                                            <a class="btn btn-sm btn-outline theme-secondary" href="' . h(PATH . 'llms.txt') . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>llms.txt</a>' : '') . '
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';

// ── Product Feeds ──
// The shop's products as files shopping platforms read
// (includes/fn/product_feeds.php): a switch, an address and the last build per
// format, then the seller fields every format shares. The fields are drawn
// even while e-commerce is off or before the 11.0.4 step, so a save never
// finds them missing; seo.save.php writes nothing until the column is there.
$product_feeds_groups = pg_product_feed_screen_groups();
$product_feeds_rows = '';

// The Google Merchant Center part of the card ($product_feeds_merchant).
require(PG_FUNCTIONS_DIR . '/includes/settings/seo_merchant.php');

foreach (pg_product_feed_format_texts() as $product_feeds_format => $product_feeds_text) {
    $product_feeds_entry = $product_feeds_settings['formats'][$product_feeds_format];
    $product_feeds_address = '';

    if ($product_feeds_entry['token_hash'] !== '') {
        $product_feeds_token = pg_product_feed_token($product_feeds_entry);

        $product_feeds_address = '
                                            <div class="input-group input-group-sm mt-3 pg-f-lg">
                                                ' . (($product_feeds_token !== '')
                                                    ? '<input type="text" class="form-control" readonly value="' . h(pg_product_feed_url($product_feeds_token)) . '" aria-label="' . h(lang('Feed address')) . '" onfocus="this.select()" />'
                                                    : '<span class="form-control fg-secondary">' . h(lang('This address cannot be shown again on this site. Make a new address to see it.')) . '</span>') . '
                                                <button type="submit" name="product_feed_new_address" value="' . h($product_feeds_format) . '" class="btn btn-sm btn-outline theme-warning" onclick="return confirm(' . h(json_encode(lang('Make a new address? The current address stops working at once, so the platforms that read it must be given the new one.'))) . ')"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('New address') . '</button>
                                            </div>';
    }

    $product_feeds_status = pg_product_feed_status_text($product_feeds_entry);

    if ($product_feeds_entry['error'] !== '') {
        $product_feeds_status = '<span class="fg-danger">' . h(lang(array('string' => 'The last build failed: {var:1} The file built before is still served.', 'vars' => array(pg_product_feed_error_text($product_feeds_entry['error']))))) . '</span>'
            . (($product_feeds_status !== '') ? ' ' . h($product_feeds_status) : '');
    } elseif ($product_feeds_entry['enabled'] && ((int) $product_feeds_entry['built_at'] === 0)) {
        $product_feeds_status = h(lang('Not built yet.'));
    } else {
        $product_feeds_status = h($product_feeds_status);
    }

    $product_feeds_rows .= '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($product_feeds_entry['enabled'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="product_feed_' . h($product_feeds_format) . '" name="product_feed_' . h($product_feeds_format) . '"/></div>
                                                <label class="" for="product_feed_' . h($product_feeds_format) . '">' . h($product_feeds_text['label']) . '</label>
                                                <div class="form-text">' . h($product_feeds_text['help']) . '</div>
                                            </div>' . $product_feeds_address . (($product_feeds_status !== '') ? '
                                            <div class="form-text">' . $product_feeds_status . '</div>' : '') . '
                                        </div>';
}

$product_feeds_group_options = '';

foreach ($product_feeds_groups as $product_feeds_group_id => $product_feeds_group_path) {
    $product_feeds_group_options .= '<option value="' . (int) $product_feeds_group_id . '"' . (in_array((int) $product_feeds_group_id, $product_feeds_settings['excluded_group_ids'], true) ? ' selected="selected"' : '') . '>' . h($product_feeds_group_path) . '</option>';
}

$product_feeds_notice = '';

if (!$product_feeds_ready) {
    $product_feeds_notice = lang('Product feeds become available once the database upgrade has run.');
} elseif (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    $product_feeds_notice = lang('E-commerce is turned off, so no feed is built.');
}

$pg_settings_cards[] = '
                        <div id="pgset-feeds" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Product Feeds') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-text">' . lang('Publishes the products of your catalog as files that shopping platforms and AI shopping assistants read. Each feed has its own private address and is built again every day.') . '</div>' . (($product_feeds_notice !== '') ? '
                                            <div class="form-text fg-warning">' . h($product_feeds_notice) . '</div>' : '') . '
                                        </div>
                                        ' . $product_feeds_rows . '
                                        <div class="col-12 mb-0"><h6 class="fg-secondary text-uppercase">' . lang('Seller') . '</h6></div>
                                        <div class="pg-f-md">
                                            <label for="product_feed_seller_name" class="form-label">' . lang('Seller name') . '</label>
                                            <input type="text" name="product_feed_seller_name" id="product_feed_seller_name" class="form-control" maxlength="255" value="' . h(($product_feeds_settings['seller_name'] !== '') ? $product_feeds_settings['seller_name'] : (defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '')) . '"/>
                                            <div class="form-text">' . lang('The name shoppers see as the seller. The OpenAI feed needs it.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="product_feed_default_brand" class="form-label">' . lang('Brand for products without one') . '</label>
                                            <input type="text" name="product_feed_default_brand" id="product_feed_default_brand" class="form-control" maxlength="255" value="' . h($product_feeds_settings['default_brand']) . '"/>
                                            <div class="form-text">' . lang('Usually your own brand. The OpenAI feed leaves out a product with no brand.') . '</div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="product_feed_seller_url" class="form-label">' . lang('Seller website') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_seller_url" id="product_feed_seller_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['seller_url']) . '" placeholder="https://"/>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="product_feed_privacy_policy_url" class="form-label">' . lang('Privacy policy address') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_privacy_policy_url" id="product_feed_privacy_policy_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['privacy_policy_url']) . '" placeholder="https://"/>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="product_feed_terms_url" class="form-label">' . lang('Terms of sale address') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_terms_url" id="product_feed_terms_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['terms_url']) . '" placeholder="https://"/>
                                            <div class="form-text">' . lang('Full addresses starting with https://. The OpenAI feed passes them on with every product.') . '</div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label' . (($product_feeds_group_options !== '') ? ' for="product_feed_excluded_groups"' : '') . ' class="form-label">' . lang('Leave out these product groups') . '</label>
                                            ' . (($product_feeds_group_options !== '')
                                                ? '<select multiple size="6" name="product_feed_excluded_groups[]" id="product_feed_excluded_groups" class="form-control">' . $product_feeds_group_options . '</select>
                                            <div class="form-text">' . lang('A product in a chosen group, or in a group under it, is in no feed. Hold Ctrl (or ⌘) to choose several.') . '</div>'
                                                : '<div class="form-text">' . lang('No catalog shows a product group yet.') . '</div>') . '
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text mb-3">' . lang('Changes reach the feeds at the next daily build. A feed you switch on is built when you save.') . '</div>
                                            <button type="submit" name="product_feed_build" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Save and build now') . '</button>
                                        </div>' . $product_feeds_merchant . '
                                   </div>
                                </div>
                            </div>
                        </div>';

// ── Visitor Tracking ──
// The Google Analytics figures part of the card ($google_analytics_figures).
require(PG_FUNCTIONS_DIR . '/includes/settings/seo_analytics.php');

$pg_settings_cards[] = '
                        <div id="pgset-analytics" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Visitor Tracking') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $visitor_tracking_checked . ' class="collapse-switcher" type="checkbox" id="visitor_tracking" name="visitor_tracking" data-bs-target="#visitor_tracking_row"/></div>
                                                <label class="" for="visitor_tracking">' . lang('Enable Visitor Tracking') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="visitor_tracking_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">
                                                        <div class="pg-f-xs">
                                                            <label for="tracking_code_duration" class="form-label">' . lang('Tracking Code Duration') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                            <label class="form-control form-adorn form-adorn-end">
                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                                <input type="text" required name="tracking_code_duration" id="tracking_code_duration" class="form-ghost" value="' . h($tracking_code_duration) . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="1" style="text-align: right;" min="1" max="365" />
                                                            </label>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="pay_per_click_flag" class="form-label">' . lang('Pay Per Click Tracking Code Flag') . '</label>
                                                            <input type="text" name="pay_per_click_flag" id="pay_per_click_flag" class="form-control" value="' . h($pay_per_click_flag) . '" />
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $google_analytics_checked . ' class="collapse-switcher" type="checkbox" id="google_analytics" name="google_analytics" data-bs-target="#google_analytics_row"/></div>
                                                                <label class="" for="google_analytics">' . lang('Enable Google Analytics') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 " id="google_analytics_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-5">
                                                                    <div class="col-12 ">
                                                                        <label for="stats_url" class="form-label">' . lang('Website Analytics URL') . '</label>
                                                                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="stats_url" id="stats_url" class="form-control" value="' . h($stats_url) . '" />
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
                                        </div>' . ($visitor_retention_available ? '
                                        <div class="pg-f-xs">
                                            <label for="visitor_retention_days" class="form-label">' . lang('Keep visitor records for') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                <input type="text" name="visitor_retention_days" id="visitor_retention_days" class="form-ghost" value="' . h($visitor_retention_days) . '" size="5" maxlength="4" inputmode="numeric" style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang(array('string' => 'Older visitor records are removed a little at a time by the general job, so the table stays small. 0 keeps every record; otherwise at least {var:1} and at most {var:2} days.', 'vars' => array(PG_VISITOR_RETENTION_MIN_DAYS, PG_VISITOR_RETENTION_MAX_DAYS)))) . '</div>
                                        </div>
                                        ' . ($visitor_ip_available ? '<div class="pg-f-xs">
                                            <label for="visitor_ip_days" class="form-label">' . lang('Remove IP addresses after') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                <input type="text" name="visitor_ip_days" id="visitor_ip_days" class="form-ghost" value="' . h($visitor_ip_days) . '" size="5" maxlength="4" inputmode="numeric" style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang(array('string' => 'The IP address of a visit is taken out of its record after this many days; the record, its source and its figures stay. 0 keeps it as long as the record; otherwise at least {var:1} and at most {var:2} days.', 'vars' => array(PG_VISITOR_IP_MIN_DAYS, PG_VISITOR_IP_MAX_DAYS)))) . '</div>
                                        </div>
                                        ' : '') . '<div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($visitor_summary ? ' checked="checked"' : '') . ' class="" type="checkbox" id="visitor_summary" name="visitor_summary"/></div>
                                                <label class="" for="visitor_summary">' . lang('Keep a daily summary before records are removed') . '</label>
                                                <div class="form-text">' . lang('Each day is added up by source, landing page, campaign, place and device first, so the visitor report and the API still count it. Turned off, those days are gone from the visitor report; the dashboard charts keep them either way.') . '</div>
                                                <div class="form-text"><a href="database_engine.php#visitor-records">' . lang('The size of the table, and a cleanup you can start yourself, are on the Database Engine screen.') . '</a></div>
                                            </div>
                                        </div>' : '') . $google_analytics_figures . '
                                   </div>
                                </div>
                            </div>
                        </div>';

// ── Cookie Consent ──
// The notice itself is drawn by pg_consent_markup() (includes/fn/consent.php).
$pg_settings_cards[] = '
                        <div id="pgset-cookies" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Cookie Consent') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($cookie_consent ? ' checked="checked"' : '') . ' class="" type="checkbox" id="cookie_consent" name="cookie_consent"/></div>
                                                <label class="" for="cookie_consent">' . lang('Ask visitors for cookie consent') . '</label>
                                                <div class="form-text">' . lang('A small notice in the bottom-left corner lists the cookies the site uses. Necessary cookies are always on; Google Analytics, the visitor statistics cookies and the referral cookie start only after the visitor allows them. Turned off, every cookie is set as before.') . '</div>
                                            </div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="cookie_consent_policy_url" class="form-label">' . lang('Cookie Policy Address') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="cookie_consent_policy_url" id="cookie_consent_policy_url" class="form-control" maxlength="500" value="' . h($cookie_consent_policy_url) . '" placeholder="/cookie-policy"/>
                                            <div class="form-text">' . lang('A page that explains the cookies in detail, shown as a link in the notice. Start it with / or https://; leave it empty for no link.') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text mb-3">' . lang(array('string' => 'After a choice the notice folds into a cookie button in the bottom-left corner, so the visitor can change it later. Put a link to {var:1} on the site (in the footer, for example) and the link reopens the notice; the button is then hidden.', 'vars' => '<code>#cookie-settings</code>')) . '</div>
                                            <div class="form-text mb-3">' . lang(array('string' => 'To hold a script of your own until the visitor agrees, write its tag as {var:1} (or {var:2}). The notice lists it under that category with the name you give it.', 'vars' => array('<code>' . h('<script type="text/plain" data-pg-consent="analytics" data-pg-consent-name="Hotjar">') . '</code>', '<code>data-pg-consent="marketing"</code>'))) . '</div>
                                            <div class="form-text">' . lang(array('string' => 'Google tags you add yourself (Tag Manager, Google Ads) are told the answer through Google Consent Mode v2: they set no cookies until the visitor allows statistics or marketing. Such a tag can stay as it is; for the notice to ask about its category, add an empty held tag such as {var:1}.', 'vars' => '<code>' . h('<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Google Ads"></script>') . '</code>')) . '</div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';
