<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the SEO cards: how the site reads from outside.
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
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Site Icon, Social Sharing, Structured Data ──
// One card each for the blocks includes/settings/prep.php builds. A block
// exists only where the columns behind it do, so a card with nothing to
// show is not drawn; Structured Data always has its switch.
foreach (array(
    'pgset-site-icon'  => array(lang('Site Icon'), $output_site_icon_picker . $output_app_icon_picker),
    'pgset-social'     => array(lang('Social Sharing'), $output_share_image_pickers),
    'pgset-structured' => array(lang('Structured Data'), $output_structured_data_section),
) as $seo_card_id => $seo_card) {
    if ($seo_card[1] === '') {
        continue;
    }

    $pg_settings_cards[] = '
                        <div id="' . $seo_card_id . '" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . $seo_card[0] . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . $seo_card[1] . '
                                   </div>
                                </div>
                            </div>
                        </div>';
}

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
// What robots.txt tells AI crawlers, and llms.txt (includes/fn/ai_crawlers.php),
// with the firewall's two AI bot switches ($output_waf_ai_block, prepared in
// includes/settings/prep.php): this card is the one place for AI bots, so
// robots.txt and the firewall never say different things. The Firewall card
// only reports the switches. The fields are drawn before the 11.0.21 step as
// well; seo.save.php writes nothing until the columns are there.
$ai_crawlers_catalog = pg_ai_crawler_catalog();

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
                                        ' . (($output_waf_ai_block !== '') ? '<div class="col-12 mb-0"><h6 class="fg-secondary text-uppercase">' . lang('Firewall') . '</h6></div>
                                        <div class="col-12">
                                            <div class="form-text">' . lang('Whether the firewall lets assistants and AI search in; robots.txt says the same for them.') . '</div>
                                        </div>' . $output_waf_ai_block : '') . '
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
