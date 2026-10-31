<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Visitors and privacy cards: visitor tracking, cookie consent and the checkout's legal documents.
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

// ── Visitor Tracking ──
// The Google Analytics figures part of the card ($google_analytics_figures).
require(PG_FUNCTIONS_DIR . '/includes/settings/privacy_analytics.php');

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

// ── Legal Documents ──
// The pre-information form and the distance sales contract, filled from the
// order. Drawn once the schema has its fields; the save writes them only then.
if ($legal_documents_ready) {
    $legal_placeholder_rows = '';

    foreach (pg_legal_document_placeholders() as $legal_name => $legal_description) {
        $legal_placeholder_rows .= '<tr><td class="text-nowrap"><code>^^' . h($legal_name) . '^^</code></td><td>' . h($legal_description) . '</td></tr>';
    }

    $legal_template_fields = '';

    foreach (pg_legal_document_types() as $legal_type => $legal_label) {
        $legal_field = 'ecommerce_' . $legal_type . '_template';
        $legal_template_fields .= '
                                                        <div class="col-12">
                                                            <label class="form-label" for="' . $legal_field . '">' . h($legal_label) . '</label>
                                                            <textarea class="form-control font-monospace" id="' . $legal_field . '" name="' . $legal_field . '" rows="12" spellcheck="false">' . h($ecommerce_legal_templates[$legal_type]) . '</textarea>
                                                        </div>';
    }

    // The KVKK lines of the checkout: a link to the store's own notice page
    // and an optional explicit consent box.
    $legal_kvkk_fields = '';

    if ($legal_kvkk_ready) {
        $legal_kvkk_fields = '
                                    <div class="col-12 md:col-6">
                                        <label class="form-label" for="ecommerce_kvkk_page_id">' . lang('KVKK Notice Page') . '</label>
                                        <select class="form-control" id="ecommerce_kvkk_page_id" name="ecommerce_kvkk_page_id">
                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>
                                            ' . select_page($ecommerce_kvkk_page_id ?: '') . '
                                        </select>
                                        <div class="form-text">' . lang('The checkout and the sign-up forms link this page where they explain how personal data is processed.') . '</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="ecommerce_kvkk_consent_text">' . lang('Explicit Consent Sentence') . '</label>
                                        <textarea class="form-control" id="ecommerce_kvkk_consent_text" name="ecommerce_kvkk_consent_text" rows="3">' . h($ecommerce_kvkk_consent_text) . '</textarea>
                                        <div class="form-text">' . lang('Shown beside a box the customer may leave unticked; the answer is kept with the order, or in the consent log on sign-up. Leave it empty for no box.') . '</div>
                                    </div>';
    }

    $pg_settings_cards[] = '
    <div id="pgset-legal" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Legal Documents') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $ecommerce_legal_documents_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_legal_documents" name="ecommerce_legal_documents" data-bs-target="#ecommerce_legal_documents_row"/></div>
                            <label class="" for="ecommerce_legal_documents">' . lang('Ask customers to accept the pre-information form and the distance sales contract') . '</label>
                        </div>
                        <div class="form-text">' . lang('Both documents are filled from the order and a copy is kept with it as the customer saw it, so a later change to a template never changes an accepted copy.') . '</div>
                        <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_legal_documents_row">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row gy-5">
                                    <div class="col-12">
                                        <div class="form-text">' . lang('The templates are a starting point. Have them reviewed for your business before switching this on, and add what only you know, such as your MERSİS number and your return carrier. Clear a template to go back to the built-in one.') . '</div>
                                    </div>' . $legal_template_fields . $legal_kvkk_fields . '
                                    <div class="col-12">
                                        <details>
                                            <summary class="form-text">' . lang('Fields you can use in a template') . '</summary>
                                            <div class="form-text">' . lang('[[text ^^field^^ text]] shows the text only when the field has a value.') . '</div>
                                            <table class="table table-sm mt-3 mb-0"><tbody>' . $legal_placeholder_rows . '</tbody></table>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}
