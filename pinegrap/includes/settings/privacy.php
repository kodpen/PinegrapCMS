<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Visitors and privacy cards: visitor tracking, how long
 * the site keeps data, cookie consent, the checkout's legal documents, the
 * KVKK notice, the accessibility statement and the way to the Privacy centre.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/privacy_settings_fold.php.
 * A switch's fields sit under it, drawn whatever its state. Fills
 * $pg_settings_cards with the one list of rows. Which cards belong here is
 * said once, in includes/settings/registry.php; this file holds them, and
 * privacy.save.php writes what they edit. The variables they read are
 * prepared by includes/settings/prep.php.
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
// The Google tag and the Google Analytics figures are set on Google
// Analytics' page of Connections; this card shows the connection and leads
// there.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_privacy_fold_order().
$privacy_parts = array();

$google_analytics_connected = ($google_analytics == 1) && (pg_ga_tag_id($google_analytics_web_property_id) !== '');
$google_analytics_figures_on = pg_ga_linked();

$google_analytics_line = $google_analytics_connected
    ? lang(array('string' => 'The Google tag {var:1} counts visits on the site\'s pages. Its ID, the analytics address and the dashboard figures are kept on Google Analytics\' page of Connections.', 'vars' => pg_ga_tag_id($google_analytics_web_property_id)))
    : lang('With a Google tag ID, Google Analytics counts visits on the site\'s pages and its figures can be shown on the dashboard. Both are set on Google Analytics\' page of Connections.');

if ($google_analytics_figures_on) {
    $google_analytics_line .= ' ' . lang('The dashboard shows its figures.');
}

$privacy_parts['analytics'] = array('html' => '
                        <div id="pgset-analytics" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Visitor tracking') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . $visitor_tracking_checked . ' id="visitor_tracking" name="visitor_tracking" aria-describedby="visitor_tracking_help"/>
                                                <label class="pg-text-2" for="visitor_tracking">' . h(lang('Record visits')) . '</label>
                                            </div>
                                            <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="visitor_tracking_help">' . h(lang('Where visitors come from and which pages they open, for the visitor reports.')) . '</p>
                                        </div>
                                        <div class="pg-f-xs">
                                            <label for="tracking_code_duration" class="form-label">' . lang('Remember a tracking code for') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                <input type="text" required name="tracking_code_duration" id="tracking_code_duration" class="form-ghost text-end" value="' . h($tracking_code_duration) . '" size="7" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="1" aria-describedby="tracking_code_duration_help" />
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text mt-0" id="tracking_code_duration_help">' . h(lang('A link\'s tracking code is kept in a cookie this many days, so a later order still counts for it.')) . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="pay_per_click_flag" class="form-label">' . lang('Paid tracking code mark') . '</label>
                                            <input type="text" name="pay_per_click_flag" id="pay_per_click_flag" class="form-control" value="' . h($pay_per_click_flag) . '" aria-describedby="pay_per_click_flag_help" />
                                            <div class="form-text" id="pay_per_click_flag_help">' . h(lang('A tracking code holding this text counts as paid advertising.')) . '</div>
                                        </div>
                                        <div id="pgsub-google-analytics" class="col-12">
                                            <div class="pg-card-inner connections-list">
                                                <div class="pg-card-inner-row connections-row">
                                                    <span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-google"></i></span>
                                                    <span class="connections-row-main"><span class="connections-row-name">Google Analytics</span><span class="pg-text-meta d-block">' . h($google_analytics_line) . '</span></span>
                                                    <span class="connections-row-side">' . ($google_analytics_connected
                                                        ? '<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>'
                                                        : '<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>') . '<a class="pg-button pg-button-quiet pg-button-sm" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/connections.php?service=google_analytics">' . h($google_analytics_connected ? lang('Manage in Connections') : lang('Connect in Connections')) . '</a></span>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>') + pg_privacy_fold_tracking($visitor_tracking_checked !== '', $google_analytics_connected);

// ── How long we keep data ──
// The visitor records' periods and their daily summary, then one value row a
// period set elsewhere (pg_privacy_retention_overview()): its value today and
// Change opening the card that sets it; a fixed period has no Change.
$retention_overview = pg_privacy_retention_overview(array(
    'audit_years'   => $audit_retention_ready ? $audit_retention_years : null,
    'consent_years' => pg_privacy_retention_limits()['consent_years'],
    'waf_days'      => $waf_log_retention_days,
    'waf_rows'      => $waf_log_max_rows,
    'api_days'      => $api_log_retention_days,
    'booking_days'  => pg_booking_ready() ? pg_booking_retention_limits()['days'] : null,
    'rules'         => pg_privacy_rules(),
));
$retention_rows = '';

foreach ($retention_overview as $retention_row) {
    $retention_change = ($retention_row['url'] !== '')
        ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $retention_row['url']
        : (($retention_row['category'] !== '') ? pg_settings_link($retention_row['category'], $retention_row['card']) : '');

    $retention_rows .= '<div class="pg-value-row">'
        . '<span class="pg-icon-tile ' . h($retention_row['tone']) . '" aria-hidden="true"><i class="bi ' . h($retention_row['icon']) . '"></i></span>'
        . '<span class="pg-value-row-text"><span class="pg-value-row-name">' . h($retention_row['name']) . '</span><span class="pg-value-row-line">' . h($retention_row['line']) . '</span></span>'
        . (($retention_change !== '') ? '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($retention_change) . '" aria-label="' . h(lang('Change') . ': ' . $retention_row['name']) . '">' . h(lang('Change')) . '</a>' : '')
        . '</div>';
}

$privacy_parts['retention'] = array('html' => '
                        <div id="pgset-retention" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('How long we keep data') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . ($visitor_retention_available ? '
                                        <div class="pg-f-xs">
                                            <label for="visitor_retention_days" class="form-label">' . lang('Keep visitor records for') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                <input type="text" name="visitor_retention_days" id="visitor_retention_days" class="form-ghost text-end" value="' . h($visitor_retention_days) . '" size="5" maxlength="4" inputmode="numeric" />
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang(array('string' => 'Older visitor records are removed a little at a time by the general job, so the table stays small. 0 keeps every record; otherwise at least {var:1} and at most {var:2} days.', 'vars' => array(PG_VISITOR_RETENTION_MIN_DAYS, PG_VISITOR_RETENTION_MAX_DAYS)))) . '</div>
                                        </div>
                                        ' . ($visitor_ip_available ? '<div class="pg-f-xs">
                                            <label for="visitor_ip_days" class="form-label">' . lang('Remove IP addresses after') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                <input type="text" name="visitor_ip_days" id="visitor_ip_days" class="form-ghost text-end" value="' . h($visitor_ip_days) . '" size="5" maxlength="4" inputmode="numeric" />
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang(array('string' => 'The IP address of a visit is taken out of its record after this many days; the record, its source and its figures stay. 0 keeps it as long as the record; otherwise at least {var:1} and at most {var:2} days.', 'vars' => array(PG_VISITOR_IP_MIN_DAYS, PG_VISITOR_IP_MAX_DAYS)))) . '</div>
                                        </div>
                                        ' : '') . '<div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . ($visitor_summary ? ' checked="checked"' : '') . ' id="visitor_summary" name="visitor_summary" aria-describedby="visitor_summary_help"/>
                                                <label class="pg-text-2" for="visitor_summary">' . lang('Keep a daily summary of every day') . '</label>
                                            </div>
                                            <div class="form-text" id="visitor_summary_help">' . lang('Every day through yesterday is added up by source, landing page, campaign, place and device, whatever the period above, so the visitor overview reads it quickly and the visitor report and the API still count a day once its records are removed. Turned off, removed days are gone from the visitor report; the dashboard charts keep them either way.') . '</div>
                                            <div class="form-text"><a href="database_engine.php#visitor-records">' . lang('The size of the table, and a cleanup you can start yourself, are on the Database Engine screen.') . '</a></div>
                                        </div>' : '') . '
                                        <div class="col-12">
                                            <div class="pg-card-inner">' . $retention_rows . '</div>
                                            <div class="pg-field-help">' . h(lang(array('string' => 'Erasures and retention rule runs are destruction records: their lines stay at least {var:1} years, whatever is set here.', 'vars' => array(pg_privacy_retention_limits()['record_years'])))) . '</div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>') + pg_privacy_fold_retention($visitor_retention_available ? $visitor_retention_days : null, $visitor_ip_available ? $visitor_ip_days : null);

// ── Cookie Consent ──
// The notice itself is drawn by pg_consent_markup() (includes/fn/consent.php).
$privacy_parts['cookies'] = array('html' => '
                        <div id="pgset-cookies" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Cookie consent') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . ($cookie_consent ? ' checked="checked"' : '') . ' id="cookie_consent" name="cookie_consent" aria-describedby="cookie_consent_help"/>
                                                <label class="pg-text-2" for="cookie_consent">' . lang('Ask visitors for cookie consent') . '</label>
                                            </div>
                                            <div class="form-text" id="cookie_consent_help">' . lang('A small notice in the bottom-left corner lists the cookies the site uses. Necessary cookies are always on; Google Analytics, the visitor statistics cookies and the referral cookie start only after the visitor allows them. Turned off, every cookie is set as before.') . '</div>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="cookie_consent_policy_url" class="form-label">' . lang('Cookie Policy Address') . '</label>
                                            <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="cookie_consent_policy_url" id="cookie_consent_policy_url" class="form-control" maxlength="500" value="' . h($cookie_consent_policy_url) . '" placeholder="/cookie-policy"/>
                                            <div class="form-text">' . lang('A page that explains the cookies in detail, shown as a link in the notice. Start it with / or https://; leave it empty for no link.') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">' . lang(array('string' => 'After a choice the notice folds into a cookie button in the bottom-left corner, so the visitor can change it later. Put a link to {var:1} on the site (in the footer, for example) and the link reopens the notice; the button is then hidden.', 'vars' => '<code>#cookie-settings</code>')) . '</div>
                                        </div>
                                        <div class="col-12">
                                            <details>
                                                <summary class="form-text">' . h(lang('Your own scripts')) . '</summary>
                                                <div class="form-text mb-3">' . lang(array('string' => 'To hold a script of your own until the visitor agrees, write its tag as {var:1} (or {var:2}). The notice lists it under that category with the name you give it.', 'vars' => array('<code>' . h('<script type="text/plain" data-pg-consent="analytics" data-pg-consent-name="Hotjar">') . '</code>', '<code>data-pg-consent="marketing"</code>'))) . '</div>
                                                <div class="form-text">' . lang(array('string' => 'Google tags you add yourself (Tag Manager, Google Ads) are told the answer through Google Consent Mode v2: they set no cookies until the visitor allows statistics or marketing. Such a tag can stay as it is; for the notice to ask about its category, add an empty held tag such as {var:1}.', 'vars' => '<code>' . h('<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Google Ads"></script>') . '</code>')) . '</div>
                                            </details>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>') + pg_privacy_fold_cookies((bool) $cookie_consent);

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

    $legal_on = ($ecommerce_legal_documents_checked !== '');

    $privacy_parts['legal'] = array('html' => '
    <div id="pgset-legal" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Legal documents') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $ecommerce_legal_documents_checked . ' id="ecommerce_legal_documents" name="ecommerce_legal_documents" aria-describedby="ecommerce_legal_documents_help"/>
                            <label class="pg-text-2" for="ecommerce_legal_documents">' . lang('Ask customers to accept the pre-information form and the distance sales contract') . '</label>
                        </div>
                        <div class="form-text" id="ecommerce_legal_documents_help">' . lang('Both documents are filled from the order and a copy is kept with it as the customer saw it, so a later change to a template never changes an accepted copy.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('The templates are a starting point. Have them reviewed for your business before switching this on, and add what only you know, such as your MERSİS number and your return carrier. Clear a template to go back to the built-in one.') . '</div>
                    </div>' . $legal_template_fields . '
                    <div class="col-12">
                        <details>
                            <summary class="form-text">' . lang('Fields you can use in a template') . '</summary>
                            <div class="form-text">' . lang('[[text ^^field^^ text]] shows the text only when the field has a value.') . '</div>
                            <table class="table table-hover mt-3 mb-0 pg-embedded-table"><tbody>' . $legal_placeholder_rows . '</tbody></table>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_privacy_fold_legal($legal_on);

    // The KVKK lines of the checkout and the sign-up forms: a link to the
    // store's own notice page and an optional explicit consent box, shown
    // while their own switch is on, whatever Legal documents says
    // (pg_legal_kvkk_settings()).
    if ($legal_kvkk_ready) {
        $privacy_parts['kvkk'] = array('html' => '
    <div id="pgset-kvkk" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('KVKK notice') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . ($ecommerce_kvkk_enabled ? ' checked="checked"' : '') . ' id="ecommerce_kvkk_enabled" name="ecommerce_kvkk_enabled" aria-describedby="ecommerce_kvkk_enabled_help"/>
                            <label class="pg-text-2" for="ecommerce_kvkk_enabled">' . h(lang('Show the KVKK notice')) . '</label>
                        </div>
                        <div class="form-text" id="ecommerce_kvkk_enabled_help">' . h(lang('The checkout and the sign-up forms link the notice page and show the consent box, whether or not Legal documents is on.')) . '</div>
                    </div>
                    <div class="col-12 md:col-6">
                        <label class="form-label" for="ecommerce_kvkk_page_id">' . lang('KVKK Notice Page') . '</label>
                        ' . pg_picker_field('page', 'ecommerce_kvkk_page_id', $ecommerce_kvkk_page_id ?: '', array('placeholder' => lang('Choose a page'))) . pg_picker_script() . '
                        <div class="form-text">' . lang('The checkout and the sign-up forms link this page where they explain how personal data is processed.') . '</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ecommerce_kvkk_consent_text">' . lang('Explicit Consent Sentence') . '</label>
                        <textarea class="form-control" id="ecommerce_kvkk_consent_text" name="ecommerce_kvkk_consent_text" rows="3">' . h($ecommerce_kvkk_consent_text) . '</textarea>
                        <div class="form-text">' . lang('Shown beside a box the customer may leave unticked; the answer is kept with the order, or in the consent log on sign-up. Leave it empty for no box.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_privacy_fold_kvkk($ecommerce_kvkk_enabled, $ecommerce_kvkk_page_id > 0, $ecommerce_kvkk_consent_text);
    }
}

// ── Accessibility Statement ──
// The fixed texts of the statement the Accessibility Statement widget and
// view_accessibility.php?statement=1 draw (includes/fn/widgets_accessibility.php);
// the conformance and the known limitations come from the nightly report.
// One config_text row as JSON, so the card needs only that table.
if (pg_schema_has('config_text')) {
    $a11y_statement = pg_a11y_statement_settings();
    $a11y_statement_limits = pg_a11y_statement_fields();
    $a11y_statement_field = function ($field, $label, $help, $rows) use ($a11y_statement, $a11y_statement_limits) {
        $id = 'a11y_statement_' . $field;
        $control = ($rows > 1)
            ? '<textarea class="form-control" id="' . $id . '" name="' . $id . '" rows="' . (int) $rows . '" maxlength="' . (int) $a11y_statement_limits[$field] . '">' . h($a11y_statement[$field]) . '</textarea>'
            : '<input type="text" class="form-control" id="' . $id . '" name="' . $id . '" maxlength="' . (int) $a11y_statement_limits[$field] . '" value="' . h($a11y_statement[$field]) . '"/>';

        return '
                                        <div class="col-12">
                                            <label class="form-label" for="' . $id . '">' . h($label) . '</label>
                                            ' . $control . '
                                            <div class="form-text">' . h($help) . '</div>
                                        </div>';
    };

    $privacy_parts['a11y-statement'] = array('html' => '
                        <div id="pgset-a11y-statement" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Accessibility statement') . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="a11y_statement_card" value="1"/>
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('The EU Accessibility Act asks for a statement on the site. Place the Accessibility Statement widget on a page in the Page Design Studio: it shows these texts with the conformance and the known limitations of the nightly accessibility report.')) . '</div>
                                            <a class="pg-button pg-button-quiet pg-button-sm mt-2" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_accessibility.php?statement=1') . '" target="_blank" rel="noopener">' . h(lang('Print the statement')) . '</a>
                                        </div>'
        . $a11y_statement_field('organisation', lang('Organisation Name'), lang('Who makes the statement. Empty: the site name.'), 1)
        . $a11y_statement_field('scope', lang('Scope'), lang('What the statement applies to, such as the site address and its online shop. Empty: the site name.'), 2)
        . $a11y_statement_field('contact', lang('Contact Address'), lang('An e-mail address, telephone number or postal address for accessibility questions.'), 2)
        . $a11y_statement_field('feedback', lang('Feedback Mechanism'), lang('How visitors report a barrier and how soon they get an answer.'), 3)
        . $a11y_statement_field('enforcement', lang('Enforcement Procedure'), lang('The authority a visitor may turn to when the answer does not satisfy them, with its address. Empty: the section is left out.'), 3) . '
                                        <div class="pg-f-sm">
                                            <label class="form-label" for="a11y_statement_date">' . lang('Statement Date') . '</label>
                                            <input type="date" data-pg-date class="form-control" id="a11y_statement_date" name="a11y_statement_date" value="' . h($a11y_statement['date']) . '" aria-describedby="a11y_statement_date_help"/>' . get_date_picker_format() . pg_fields_script() . '
                                            <div class="form-text" id="a11y_statement_date_help">' . h(lang('When the statement was prepared or last reviewed. Empty: today.')) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_privacy_fold_a11y(($a11y_statement['date'] !== '') ? get_absolute_time(array('timestamp' => strtotime($a11y_statement['date'] . ' 12:00:00'), 'format' => 'plain_text', 'type' => 'date')) : '');
}

// ── Privacy centre ──
// The personal data inventory, its retention rules, transfers, requests and
// downloads live on their own screen under Contacts (privacy_centre.php);
// this row says what the centre holds and leads there.
$privacy_parts['privacy-centre'] = array('html' => '
                        <div id="pgset-privacy-centre" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Privacy centre') . '
                                </div>
                                <div class="card-body">
                                    <p class="pg-field-help mt-0">' . h(lang('The personal data the site keeps, why and for how long, and the outside services it goes to; the retention rules that delete what is no longer needed, such as old site chats; the privacy requests people send; an Excel and a PDF download for your KVKK or GDPR records.')) . '</p>
                                    <a class="pg-button pg-button-quiet pg-button-sm" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/privacy_centre.php">' . h(lang('Open the Privacy centre')) . '</a>
                                </div>
                            </div>
                        </div>') + pg_privacy_fold_centre();

// ── The rows ──
$privacy_rows = array();

foreach (pg_privacy_fold_order() as $privacy_key) {
    if (isset($privacy_parts[$privacy_key])) {
        $privacy_rows[] = array('key' => $privacy_key) + pg_privacy_fold_face($privacy_key) + $privacy_parts[$privacy_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($privacy_rows);
