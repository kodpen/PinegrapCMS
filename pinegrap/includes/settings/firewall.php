<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Firewall and HTTPS cards: Secure Mode and the HTTPS
 * headers, the request filter, the limits on one address, the bot policy,
 * the address lists, the country restriction, the trusted proxies and the
 * logs.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/firewall_settings_fold.php.
 * A switch's fields sit under it, drawn whatever its state. Which cards
 * belong here is said once, in includes/settings/registry.php; this file
 * holds them, and firewall.save.php writes what they edit. The variables they
 * read are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/firewall_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// The address the firewall resolves for the operator's own request, for the
// notice beside Trusted Proxies.
$waf_own_address = function_exists('waf_client_ip') ? (string) waf_client_ip() : '';

// A proxy on a public address that is not listed: this request's own peer
// when it names another public client in a forwarding header, and the ones
// signed-in staff requests have come through (waf_unlisted_proxy_record()).
// firewall.save.php computes the same entries again when the box is ticked.
$output_waf_unlisted_proxy = '';

if (!empty($waf_schema_ready) && function_exists('waf_unlisted_proxy_offer')) {
    $waf_proxy_offer = waf_unlisted_proxy_offer(
        waf_unlisted_proxy_finding($_SERVER, waf_proxy_ranges($waf_trusted_proxies), waf_cloudflare_ranges()),
        waf_unlisted_proxies());

    if ($waf_proxy_offer) {
        $output_waf_unlisted_proxy = '
                        <div class="form-text fg-warning">' . lang(array('string' => 'Requests from signed-in staff reach the site through {var:1}, a proxy on a public address that is not listed here. Every visitor behind it looks like that one address, so the address limits and automatic bans leave it alone for now. If it is your own load balancer or CDN, list it; a CDN moves between addresses, so list all of its published ranges as well.', 'vars' => h(implode(', ', array_keys($waf_proxy_offer))))) . '</div>
                        <div class="form-field mt-3">
                            <input value="1" class="check" type="checkbox" id="waf_trust_unlisted_proxy" name="waf_trust_unlisted_proxy"/>
                            <label class="" for="waf_trust_unlisted_proxy">' . lang('Add this proxy to Trusted Proxies') . '</label>
                            <div class="form-text">' . lang(array('string' => 'With this box ticked, saving lists {var:1}.', 'vars' => h(implode(', ', $waf_proxy_offer)))) . '</div>
                        </div>';
    }
}


// Each part's card and its line of state, by row key; drawn as rows in
// pg_firewall_fold_order() once every card is built.
$firewall_parts = array();

// ── Firewall ──
// The AI bot switches live on SEO › AI Crawlers, next to what robots.txt
// tells the same bots; this card only says how they stand.
$output_waf_ai_state = '';

if ($waf_ai_ready) {
    $waf_ai_catalog = pg_ai_crawler_catalog();
    $waf_ai_agents = implode(', ', $waf_ai_catalog['agent']);
    $waf_ai_search = implode(', ', $waf_ai_catalog['search']);

    $output_waf_ai_state = '
                    <div class="col-12">
                        <div class="form-text">' . h($waf_allow_ai_fetchers
                            ? lang(array('string' => 'Assistants ({var:1}): let in by the firewall.', 'vars' => array($waf_ai_agents)))
                            : lang(array('string' => 'Assistants ({var:1}): turned away by the firewall.', 'vars' => array($waf_ai_agents)))) . '</div>
                        <div class="form-text">' . h($waf_allow_ai_search
                            ? lang(array('string' => 'AI search ({var:1}): let in by the firewall.', 'vars' => array($waf_ai_search)))
                            : lang(array('string' => 'AI search ({var:1}): turned away by the firewall.', 'vars' => array($waf_ai_search)))) . '</div>
                        <div class="form-text"><a class="pg-link-secondary" href="' . h(pg_settings_link('seo', 'pgset-ai')) . '">' . lang('Change under AI Crawlers') . '</a></div>
                    </div>';
}

$firewall_parts['waf'] = array('html' => '
    <div id="pgset-waf" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Firewall') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    ' . (($output_waf_external . $output_waf_self_ban . $output_waf_ip_check . $output_waf_identity) !== ''
                        ? '<div class="col-12">' . $output_waf_external . $output_waf_self_ban . $output_waf_ip_check . $output_waf_identity . '</div>'
                        : '') . '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_enabled_checked . ' id="waf_enabled" name="waf_enabled" aria-describedby="waf_enabled_help"/>
                            <label class="pg-text-2" for="waf_enabled">' . lang('Use the firewall') . '</label>
                        </div>
                        <div class="form-text" id="waf_enabled_help">' . lang('Inspects every request for injection, scripting and traversal attacks, limits request rates, and enforces the IP lists below.') . '</div>
                    </div>
                    <div class="col-12">
                        <fieldset><legend class="form-label pg-field-label">' . lang('Mode') . '</legend>
                        <div class="pg-seg flex-wrap">
                            <label class="pg-seg-item" for="waf_mode_monitor"><input class="visually-hidden" type="radio" name="waf_mode" id="waf_mode_monitor" value="monitor"' . $waf_mode_monitor_checked . '/>' . lang('Monitor') . '</label>
                            <label class="pg-seg-item" for="waf_mode_block"><input class="visually-hidden" type="radio" name="waf_mode" id="waf_mode_block" value="block"' . $waf_mode_block_checked . '/>' . lang('Block') . '</label>
                        </div>
                        </fieldset>
                        <div class="form-text"><strong>' . lang('Monitor') . '</strong> &mdash; ' . lang('record only, block nothing new') . '. ' . lang('Start here. The log fills with what blocking would have stopped, so you can confirm on your own traffic that nothing legitimate is caught before you switch it on.') . ' <strong>' . lang('The IP lists below are the exception: an address you banned by hand stays banned in Monitor mode, and only turning the firewall off releases it. Automatic bans are not enforced until you switch back to Block.') . '</strong></div>
                        <div class="form-text"><strong>' . lang('Block') . '</strong> &mdash; ' . lang('reject attacking requests') . '. ' . lang('Attacks receive a 403 response and repeat offenders are banned temporarily.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="waf_sensitivity" class="form-label">' . lang('Sensitivity') . '</label>
                        <select name="waf_sensitivity" id="waf_sensitivity" class="form-control">' . $output_waf_sensitivity_options . '</select>
                        <div class="form-text">' . lang('Each rule contributes a score. Sensitivity sets how much evidence is needed before a request counts as an attack.') . ' ' . lang('It moves only this threshold of the signature scan: the rate limits and automatic bans keep their own settings.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_signature_scan_checked . ' id="waf_signature_scan" name="waf_signature_scan" aria-describedby="waf_signature_scan_help"/>
                            <label class="pg-text-2" for="waf_signature_scan">' . lang('Attack signature scanning') . '</label>
                        </div>
                        <div class="form-text" id="waf_signature_scan_help">' . lang('Checks query strings, form data, cookies and headers for SQL injection, cross-site scripting, path traversal and command injection. Signed-in staff are never scanned, because designers legitimately submit HTML, JavaScript and SQL.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_block_attack_tools_checked . ' id="waf_block_attack_tools" name="waf_block_attack_tools" aria-describedby="waf_block_attack_tools_help"/>
                            <label class="pg-text-2" for="waf_block_attack_tools">' . lang('Block penetration testing tools') . '</label>
                        </div>
                        <div class="form-text" id="waf_block_attack_tools_help">' . lang('Rejects sqlmap, Nikto, Acunetix, nuclei and similar scanners by their user agent, whatever else they claim to be.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_verify_bots_checked . ' id="waf_verify_bots" name="waf_verify_bots" aria-describedby="waf_verify_bots_help"/>
                            <label class="pg-text-2" for="waf_verify_bots">' . lang('Verify search engine crawlers') . '</label>
                        </div>
                        <div class="form-text" id="waf_verify_bots_help">' . lang('Confirms by reverse DNS that a visitor claiming to be Googlebot really is one. Anyone can type Googlebot into a user agent; without this check, doing so grants crawler privileges. A failed lookup is never treated as a forgery, so a DNS outage cannot block a real crawler.') . '</div>
                    </div>' . $output_waf_ai_state . '
                    <div class="pg-f-lg">
                        <label for="waf_exclusions" class="form-label">' . lang('Excluded paths') . '</label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h(waf_list_to_string($waf_exclusions)) . '" name="waf_exclusions" id="waf_exclusions" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add Path') . '"/>
                        <div class="form-text">' . lang('Script names or URL paths that skip inspection entirely, before any other rule. Matched against the path only — never the query string, which anyone could append to steal the exemption. Intended for payment gateway callbacks, where losing a request loses a paid order, and for your own licence or update endpoint if this site serves other Pinegrap installations.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_waf(!empty($waf_schema_ready), $waf_enabled_checked !== '', $waf_mode_block_checked !== '' ? 'block' : 'monitor', $output_waf_self_ban !== '');

// ── Limits ──
// Rate limiting, the in-flight cap and the automatic bans: every limit the
// firewall puts on one address. firewall.save.php writes them as before.
// Under them, the limits Accounts and sign-in keeps as value rows: shown
// here, saved there (a category writes only its own columns).
$firewall_limit_values = '';

foreach (pg_settings_limits_overview(
    array('ready' => !empty($login_throttle_ready), 'on' => isset($login_throttle) && $login_throttle == 1,
        'attempts' => isset($login_throttle_attempts) ? $login_throttle_attempts : 0, 'minutes' => isset($login_throttle_minutes) ? $login_throttle_minutes : 0,
        'lockout' => isset($login_throttle_lockout) ? $login_throttle_lockout : 0),
    array('ready' => !empty($form_limits_ready), 'ip_hour' => isset($form_limit_ip_hour) ? $form_limit_ip_hour : 0,
        'recipient_day' => isset($form_limit_recipient_day) ? $form_limit_recipient_day : 0, 'domain_hour' => isset($form_limit_domain_hour) ? $form_limit_domain_hour : 0)) as $firewall_limit_value) {
    $firewall_limit_values .= '
                            <div class="pg-value-row">
                                <span class="pg-icon-tile pg-tone-' . $firewall_limit_value['tone'] . '" aria-hidden="true"><i class="bi ' . $firewall_limit_value['icon'] . '"></i></span>
                                <span class="pg-value-row-text"><span class="pg-value-row-name">' . h($firewall_limit_value['name']) . '</span><span class="pg-value-row-line">' . h($firewall_limit_value['line']) . '</span></span>'
        . ($firewall_limit_value['href'] !== ''
            ? '
                                <a class="pg-button pg-button-quiet pg-button-sm" href="' . h($firewall_limit_value['href']) . '" aria-label="' . h(lang('Change') . ': ' . $firewall_limit_value['name']) . '">' . h(lang('Change')) . '</a>'
            : '') . '
                            </div>';
}

$firewall_parts['limits'] = array('html' => '
    <div id="pgset-limits" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Limits') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_rate_limit_checked . ' id="waf_rate_limit" name="waf_rate_limit" aria-describedby="waf_rate_limit_help"/>
                            <label class="pg-text-2" for="waf_rate_limit">' . lang('Rate limiting') . '</label>
                        </div>
                        <div class="form-text" id="waf_rate_limit_help">' . lang('Caps how many requests one address may make per minute. Only crawlers whose address proves their name are exempt; with bot verification off, no bot is. All Requests counts pages and files before the session starts, so it spares a signed-in user only on the software\'s own screens and endpoints; the other limits spare staff only.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_rate_limit_requests" class="form-label">' . lang('All Requests') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('per minute') . '</span>
                            <input type="text" name="waf_rate_limit_requests" id="waf_rate_limit_requests" class="form-ghost text-end" value="' . (int) $waf_rate_limit_requests . '" size="5" maxlength="5" inputmode="numeric"/>
                        </label>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_rate_limit_sensitive" class="form-label">' . lang('Sign-in, Forms and Checkout') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('per minute') . '</span>
                            <input type="text" name="waf_rate_limit_sensitive" id="waf_rate_limit_sensitive" class="form-ghost text-end" value="' . (int) $waf_rate_limit_sensitive . '" size="5" maxlength="5" inputmode="numeric"/>
                        </label>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_rate_limit_api" class="form-label">' . lang('Live Chat and Store Pages') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('per minute') . '</span>
                            <input type="text" name="waf_rate_limit_api" id="waf_rate_limit_api" class="form-ghost text-end" value="' . (int) $waf_rate_limit_api . '" size="5" maxlength="5" inputmode="numeric"/>
                        </label>
                        <div class="form-text">' . lang('The requests your pages make in the background. An open chat window alone asks about thirty times a minute.') . '</div>
                    </div>
                    ' . ($security_ready ? '<div class="pg-f-xs">
                        <label for="waf_inflight_limit" class="form-label">' . lang('Open at Once') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('requests') . '</span>
                            <input type="text" name="waf_inflight_limit" id="waf_inflight_limit" class="form-ghost text-end" value="' . (int) $waf_inflight_limit . '" size="4" maxlength="3" inputmode="numeric"/>
                        </label>
                        <div class="form-text">' . lang('How many sign-in, form or checkout requests one address may have running at the same time. Catches a request that waits on e-mail or a payment gateway while holding a database connection, which the per-minute limits above cannot see. 0 turns it off.') . '</div>
                    </div>' : '') . '
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('Offices and schools share one address across many people. If visitors report being rate limited, raise these numbers or add the address to the allowed list.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $waf_auto_ban_checked . ' id="waf_auto_ban" name="waf_auto_ban" aria-describedby="waf_auto_ban_help"/>
                            <label class="pg-text-2" for="waf_auto_ban">' . lang('Automatic temporary bans') . '</label>
                        </div>
                        <div class="form-text" id="waf_auto_ban_help">' . lang('Repeat offenders are banned for a while instead of being blocked one request at a time. Bans always expire. In Monitor mode none are placed, and those already placed are not enforced until you switch back to Block.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_auto_ban_threshold" class="form-label">' . lang('Ban After') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('events / 10 min') . '</span>
                            <input type="text" name="waf_auto_ban_threshold" id="waf_auto_ban_threshold" class="form-ghost text-end" value="' . (int) $waf_auto_ban_threshold . '" size="4" maxlength="4" inputmode="numeric"/>
                        </label>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_auto_ban_minutes" class="form-label">' . lang('Ban Duration') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('minute(s)') . '</span>
                            <input type="text" name="waf_auto_ban_minutes" id="waf_auto_ban_minutes" class="form-ghost text-end" value="' . (int) $waf_auto_ban_minutes . '" size="5" maxlength="5" inputmode="numeric"/>
                        </label>
                    </div>
                    ' . ($security_ready ? '<div class="pg-f-xs">
                        <label for="waf_auto_ban_max_minutes" class="form-label">' . lang('Longest Ban') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('minute(s)') . '</span>
                            <input type="text" name="waf_auto_ban_max_minutes" id="waf_auto_ban_max_minutes" class="form-ghost text-end" value="' . (int) $waf_auto_ban_max_minutes . '" size="6" maxlength="6" inputmode="numeric"/>
                        </label>
                        <div class="form-text">' . lang('An address banned again after its ban ran out is banned four times as long each time, up to this ceiling. 10080 is seven days.') . '</div>
                    </div>' : '') . '
                    <div class="col-12">
                        <div class="pg-group-label mb-1">' . lang('Limits kept in Accounts and sign-in') . '</div>
                        <div class="pg-card-inner">' . $firewall_limit_values . '
                        </div>
                        <div class="pg-field-help">' . h(lang('Each is set and saved in Accounts and sign-in; Change opens its row there.')) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_limits($waf_rate_limit_checked !== '', $waf_rate_limit_requests, $waf_auto_ban_checked !== '', $waf_auto_ban_threshold);

// ── Bots ──
$firewall_parts['bots'] = array('html' => '
    <div id="pgset-bots" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Bots') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('Works only while the firewall above is enabled, and refuses bots only in Block mode; in Monitor mode it records the bots it would refuse. Search engines, social link previews, uptime monitors and payment gateway callbacks are recognised and never blocked.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $block_unknown_bots_checked . ' id="block_unknown_bots" name="block_unknown_bots" aria-describedby="block_unknown_bots_help"/>
                            <label class="pg-text-2" for="block_unknown_bots">' . lang('Block unknown bots') . '</label>
                        </div>
                        <div class="form-text" id="block_unknown_bots_help">' . lang('In Block mode, crawlers that are not recognised and not on your allowed list receive a 403 response, reducing server load and preventing visitor count inflation; in Monitor mode they are only recorded. The REST API is exempt, so your own integrations keep working.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="allowed_bots" class="form-label"><i class="bi bi-check-circle fg-success me-1"></i>' . lang('Allowed bots') . ' <span class="pg-pill pg-tone-teal">' . lang('never blocked') . '</span></label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h(waf_list_to_string($allowed_bots)) . '" name="allowed_bots" id="allowed_bots" class="form-control tagin min-height-tagin border-subtle-success" data-pg-chips data-placeholder="' . lang('Allow a crawler') . '"/>
                        <div class="form-text">' . lang('Extra crawlers to permit, on top of the built-in list. You do not need to list Google, Bing, Yandex, Facebook, Twitter or the payment gateways — those are already recognised.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="waf_blocked_agents" class="form-label"><i class="bi bi-slash-circle fg-danger me-1"></i>' . lang('Blocked bots') . ' <span class="pg-pill pg-tone-rose">' . lang('always blocked') . '</span></label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h(waf_list_to_string($waf_blocked_agents)) . '" name="waf_blocked_agents" id="waf_blocked_agents" class="form-control tagin min-height-tagin border-subtle-danger" data-pg-chips data-placeholder="' . lang('Block a crawler') . '"/>
                        <div class="form-text">' . lang('Extra crawlers to reject. Checked after the built-in lists, so these can never override a search engine or a payment gateway.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('SEO scrapers and other heavy crawlers (AhrefsBot, SemrushBot, PerplexityBot and similar) are already on the built-in reject list.') . '</div>
                        <div class="form-text">' . h(!empty($ai_crawlers['training'])
                            ? lang(array('string' => 'AI training crawlers ({var:1}): let in, because Allow AI training is on. They are counted against the limits like any visitor.', 'vars' => array(implode(', ', waf_ai_training_bots()))))
                            : lang(array('string' => 'AI training crawlers ({var:1}): turned away with the built-in reject list, because Allow AI training is off.', 'vars' => array(implode(', ', waf_ai_training_bots()))))) . '</div>
                        <div class="form-text"><a class="pg-link-secondary" href="' . h(pg_settings_link('seo', 'pgset-ai')) . '">' . lang('Change under AI Crawlers') . '</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_bots($block_unknown_bots_checked !== '', count(waf_parse_list($allowed_bots)), count(waf_parse_list($waf_blocked_agents)), !empty($ai_crawlers['training']));

// ── Addresses ──
// The hand-written lists, and a line on the automatic bans whose table and
// Release buttons open in a drawer.
$firewall_parts['iplists'] = array('html' => '
    <div id="pgset-iplists" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Addresses') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('Checked before every other rule, and enforced whether or not the firewall is enabled. Behind a CDN these are matched against the real visitor address, not the edge server — provided the edge is listed under Trusted proxies.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="allowed_ip_addresses" class="form-label"><i class="bi bi-check-circle fg-success me-1"></i>' . lang('Allowed IP addresses') . ' <span class="pg-pill pg-tone-teal">' . lang('never blocked') . '</span></label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h($output_allowed_ip_addresses) . '" name="allowed_ip_addresses" id="allowed_ip_addresses" class="form-control tagin min-height-tagin border-subtle-success" data-pg-chips data-placeholder="' . lang('Allow an address') . '"/>
                        <div class="form-text">' . lang('These addresses skip every firewall rule. Add your own office or VPN address before enabling blocking, so a mistuned rule cannot lock you out of your own site.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="banned_ip_addresses" class="form-label"><i class="bi bi-slash-circle fg-danger me-1"></i>' . lang('Banned IP addresses') . ' <span class="pg-pill pg-tone-rose">' . lang('always blocked') . '</span></label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h($output_banned_ip_addresses) . '" name="banned_ip_addresses" id="banned_ip_addresses" class="form-control tagin min-height-tagin border-subtle-danger" data-pg-chips data-placeholder="' . lang('Block an address') . '"/>
                        <div class="form-text">' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . ' ' . lang('Enforced whatever mode the firewall is in, including Monitor, and before the database is even opened. Allowed addresses above still win.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="pg-text-2">' . h(pg_firewall_fold_bans_sentence($waf_auto_ban_count)) . '</span>' . ($output_auto_bans !== '' ? '
                            <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#waf_bans_drawer" aria-controls="waf_bans_drawer"><i class="bi bi-list-ul" aria-hidden="true"></i>' . lang('See the bans') . '</button>' : '') . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_addresses(count(array_filter(explode(',', $output_allowed_ip_addresses), 'strlen')), count(array_filter(explode(',', $output_banned_ip_addresses), 'strlen')), $waf_auto_ban_count);

// The automatic bans with their Release buttons, beside the pane.
if ($output_auto_bans !== '') {
    $pg_settings_modals[] = pg_settings_drawer('waf_bans_drawer', h(lang('Active automatic bans')), '
                    <div class="table-responsive"><table class="table table-hover mb-0 pg-embedded-table">
                        <thead><tr>
                            <th>' . lang('IP Address') . '</th>
                            <th>' . lang('Reason') . '</th>
                            <th>' . lang('Expires') . '</th>
                            <th class="text-end">' . lang('Action') . '</th>
                        </tr></thead><tbody>' . $output_auto_bans . '</tbody></table></div>
                    <div class="form-text">' . lang('Placed by the firewall and lifted when they expire, or now with Release. Saving this screen does not clear them.') . '</div>', !empty($pg_settings_in_dialog), '', h(pg_firewall_fold_bans_sentence($waf_auto_ban_count)));
}

// Forms cannot be nested, so each Release button in the drawer submits this
// one by its form attribute, carrying the address as its own value.
$pg_settings_modals[] = '
            <form method="post" action="view_waf_log.php" id="pgset_waf_release" class="disable_shortcut d-none">
                ' . get_token_field() . '
                <input type="hidden" name="submit_release" value="1"/>
                <input type="hidden" name="release_return" value="settings"/>
            </form>';

// ── Countries ──
$waf_country_here_warn = false;

if (!empty($waf_country_ready)) {

    $output_waf_country_modes = '';
    $output_waf_country_texts = '';

    foreach (array(
        'off'   => array(lang('Off'), lang('Every country is let in.')),
        'block' => array(lang('Refuse the listed countries'), lang('Every other country is let in.')),
        'allow' => array(lang('Let in only the listed countries'), lang('Every other country is refused. List your own country and the countries of your customers and suppliers.')),
    ) as $waf_country_mode_key => $waf_country_mode_text) {
        $output_waf_country_modes .= '
                            <label class="pg-seg-item" for="waf_country_mode_' . $waf_country_mode_key . '"><input class="visually-hidden" type="radio" name="waf_country_mode" id="waf_country_mode_' . $waf_country_mode_key . '" value="' . $waf_country_mode_key . '"' . (($waf_country_mode === $waf_country_mode_key) ? ' checked="checked"' : '') . '/>' . h($waf_country_mode_text[0]) . '</label>';
        $output_waf_country_texts .= '
                        <div class="form-text"><strong>' . h($waf_country_mode_text[0]) . '</strong> &mdash; ' . h($waf_country_mode_text[1]) . '</div>';
    }

    $waf_country_names = array();

    foreach ($waf_country_list as $waf_country_code) {
        $waf_country_names[] = pg_geo_country_name($waf_country_code) . ' (' . $waf_country_code . ')';
    }

    $output_waf_country_exemptions = '';

    foreach (array(
        'waf_country_exempt_panel' => array($waf_country_exempt_panel, lang('The control panel'), lang('Its sign-in screens stay open from every country, so staff abroad can still sign in, and its other screens stay open to signed-in staff. The site\'s pages and files stay restricted, and so do the software\'s visitor addresses: comments, forms, registration and checkout. To open them for someone abroad as well, add their address to the Allowed IP Addresses.')),
        'waf_country_exempt_api'   => array($waf_country_exempt_api, lang('The API, MCP, OAuth and calendar subscription addresses'), lang('Integrations, AI applications and calendar services present their own credentials and run wherever their provider does.')),
        'waf_country_exempt_bots'  => array($waf_country_exempt_bots, lang('Verified search engine crawlers'), lang('Googlebot, Bingbot and the other search engines, once their address proves the name (Verify Search Engine Crawlers has to be on). Without this a site open to one country drops out of search results.')),
    ) as $waf_country_field => $waf_country_exemption) {
        $output_waf_country_exemptions .= '
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" class="pg-switch" value="1"' . ($waf_country_exemption[0] ? ' checked="checked"' : '') . ' id="' . $waf_country_field . '" name="' . $waf_country_field . '" aria-describedby="' . $waf_country_field . '_help"/>
                                    <label class="pg-text-2" for="' . $waf_country_field . '">' . h($waf_country_exemption[1]) . '</label>
                                </div>
                                <div class="form-text" id="' . $waf_country_field . '_help">' . h($waf_country_exemption[2]) . '</div>
                            </div>';
    }

    // What the saved settings do with the request that opened this screen.
    $waf_country_here = pg_geo_country();
    $output_waf_country_check = '';

    if ($waf_country_mode !== 'off') {
        $waf_country_here_label = ($waf_country_here['code'] !== '') ? pg_geo_country_name($waf_country_here['code']) . ' (' . $waf_country_here['code'] . ')' : lang('an unknown country');
        $waf_country_here_refused = waf_country_refused($waf_country_here['code'], $waf_country_mode, $waf_country_list, (bool) $waf_country_unknown);
        $waf_country_here_allowed = $waf_country_here_refused && waf_ip_is_allowed(waf_client_ip());

        if (!$waf_country_here_refused) {
            $waf_country_here_text = lang(array('string' => 'You are in {var:1}, which these settings let in.', 'vars' => array($waf_country_here_label)));
        } elseif ($waf_country_here_allowed) {
            $waf_country_here_text = lang(array('string' => 'You are in {var:1}, which these settings refuse; your address is on the Allowed IP Addresses, so the site still opens for you.', 'vars' => array($waf_country_here_label)));
        } else {
            $waf_country_here_text = lang(array('string' => 'You are in {var:1}, which these settings refuse: the site\'s pages do not open for you.', 'vars' => array($waf_country_here_label)));
        }

        $waf_country_here_warn = $waf_country_here_refused && !$waf_country_here_allowed;

        $output_waf_country_check = '
                    <div class="col-12">
                        <div class="alert ' . ($waf_country_here_warn ? 'theme-warning' : 'theme-secondary') . ' mb-0 py-3 small"><i class="bi ' . ($waf_country_here_warn ? 'bi-exclamation-triangle' : 'bi-person-check') . ' me-1" aria-hidden="true"></i>' . h($waf_country_here_text) . '</div>
                    </div>';
    }

    $output_waf_country = '
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang('Refuses requests by the country they come from. Applied while the firewall is enabled; in Monitor mode a request that would be refused is only recorded in the firewall log.') . '</div>
                    </div>
                    <div class="col-12">
                        <fieldset><legend class="form-label pg-field-label">' . lang('Restriction') . '</legend>
                        <div class="pg-seg flex-wrap">' . $output_waf_country_modes . '
                        </div>
                        </fieldset>' . $output_waf_country_texts . '
                    </div>
                    <div class="col-12">
                        <label for="waf_country_list" class="form-label"><i class="bi bi-globe2 me-1"></i>' . lang('Countries') . '</label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h(implode(',', $waf_country_list)) . '" name="waf_country_list" id="waf_country_list" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add a country code') . '"/>
                        <div class="form-text">' . lang('Two-letter country codes (ISO 3166-1), for example TR, DE, US. T1 is the Tor network, which only Cloudflare reports.') . ($waf_country_names ? '<br/>' . h(implode(', ', $waf_country_names)) : '') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . ($waf_country_unknown ? ' checked="checked"' : '') . ' id="waf_country_unknown" name="waf_country_unknown" aria-describedby="waf_country_unknown_help"/>
                            <label class="pg-text-2" for="waf_country_unknown">' . lang('Also refuse a request whose country is not known') . '</label>
                        </div>
                        <div class="form-text" id="waf_country_unknown_help">' . lang('Off: a request no source can place is let in, so a missing location database never shuts the site.') . '</div>
                    </div>
                    <div class="col-12 pb-0" role="group" aria-labelledby="waf_country_exempt_title">
                        <h3 class="pg-group-label mb-0" id="waf_country_exempt_title">' . lang('Always let in') . '</h3>
                        <div class="form-text">' . lang('Allowed IP addresses, robots.txt, certificate renewal (.well-known) and the uptime check address (health.php) are always let in.') . '</div>
                        <div class="row mt-5">' . $output_waf_country_exemptions . '
                        </div>
                    </div>' . $output_waf_country_check . '
                    <div class="col-12">' . pg_geo_settings_note() . '
                    </div>';
}

$firewall_parts['countries'] = array('html' => '
    <div id="pgset-countries" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Countries') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . (!empty($waf_country_ready) ? $output_waf_country : '
                    <div class="col-12">
                        <div class="alert theme-secondary mb-0 py-3 small">' . lang('These settings arrive with the 2026.4.10 upgrade, which this site has not run yet.') . '</div>
                    </div>') . '
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_countries(!empty($waf_country_ready), !empty($waf_country_ready) ? $waf_country_mode : 'off', !empty($waf_country_ready) ? count($waf_country_list) : 0, $waf_country_here_warn);

// ── Trusted proxies ──
// Its own row rather than under the firewall switch: the visitor address it
// decides is read whether or not the firewall is on (the IP lists, the bot
// policy and the sign-in throttle use it too). firewall.save.php writes it.
$firewall_parts['proxies'] = array('html' => '
    <div id="pgset-proxies" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Trusted proxies') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-lg">
                        <label for="waf_trusted_proxies" class="form-label">' . lang('Trusted Proxies') . '</label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h(waf_list_to_string($waf_trusted_proxies)) . '" name="waf_trusted_proxies" id="waf_trusted_proxies" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add Address') . '"/>
                        <div class="form-text">' . lang('Addresses of your own load balancer or CDN. Only these are allowed to declare a visitor address through forwarding headers, since those headers are trivially forged. A proxy is read through X-Forwarded-For unless the header it sets follows its address, for example 203.0.113.5 true-client-ip; the others are cf-connecting-ip, x-real-ip, x-sucuri-clientip, fastly-client-ip and incap-client-ip. An address saved before this choice existed keeps reading all of them until it is edited. Cloudflare is recognised automatically.') . '</div>
                        ' . (($waf_own_address !== '' && waf_ip_is_private_or_reserved($waf_own_address) && !waf_ip_in_any($waf_own_address, array('127.0.0.0/8', '::1/128'))) ? '<div class="form-text fg-warning">' . lang(array('string' => 'The firewall sees this request as coming from {var:1}, a private address: a proxy in front of the site that is not listed here, or a local network. Visitors behind it cannot be told apart by address, so the address limits do not apply to them.', 'vars' => h($waf_own_address))) . '</div>' : '') . '
                        ' . $output_waf_unlisted_proxy . '
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_proxies(count(waf_parse_list($waf_trusted_proxies)), $output_waf_unlisted_proxy !== '');

// ── HTTPS ──
// Secure Mode first, with its warning and the trusted-proxy offer; then the
// header switches and the policy, drawn whatever their state but only once
// their columns exist - a switch that saves nowhere is worse than no switch.
$security_headers_checked = ($security_ready && ($security_headers == 1)) ? ' checked="checked"' : '';
$security_csp_now = 'off';
$output_security_headers_block = '';

// The Secure Mode one-step offer. When this request came over plain HTTP
// through a proxy that reports HTTPS and is not trusted yet, the box lets the
// save list the peer's own address under Trusted Proxies and turn Secure Mode
// on together (firewall.save.php checks the peer against the list it writes).
// A public peer gets no box: a CDN or load balancer is listed with all of its
// published ranges by hand. Only while the mode is off; on, nothing is asked.
$output_secure_mode_offer = '';

if (($url_scheme !== 'https://') && !check_if_request_is_secure() && pg_forwarded_https($_SERVER)) {

    $secure_mode_peer = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $secure_mode_offer = pg_https_peer_offer($secure_mode_peer);

    if ($secure_mode_offer['entries'] && function_exists('waf_table_has_column') && waf_table_has_column('config', 'waf_trusted_proxies')) {
        $output_secure_mode_offer = '
                        <div class="form-field mt-3">
                            <input value="1" class="check" type="checkbox" id="secure_mode_trust_proxy" name="secure_mode_trust_proxy"/>
                            <label class="" for="secure_mode_trust_proxy">' . lang('Add this proxy to Trusted Proxies') . '</label>
                            <div class="form-text">' . lang(array('string' => 'This request reached the server over plain HTTP through a proxy at {var:1} that reports HTTPS. The proxy is not listed under Trusted Proxies, so Secure Mode cannot be turned on yet. With this box ticked, saving lists {var:2} there and turns Secure Mode on in one step.', 'vars' => array(h($secure_mode_peer), h(implode(', ', $secure_mode_offer['entries']))))) . '</div>
                        </div>';
    } elseif ($secure_mode_offer['kind'] === 'public') {
        $output_secure_mode_offer = '
                        <div class="form-text fg-warning mt-3">' . lang(array('string' => 'This request reached the server over plain HTTP through a CDN or load balancer at {var:1} that reports HTTPS. It is not listed under Trusted Proxies, so Secure Mode cannot be turned on yet: list all of its published address ranges there first (Cloudflare is recognised on its own), or switch it to Full (end-to-end) mode.', 'vars' => h($secure_mode_peer))) . '</div>';
    } else {
        $output_secure_mode_offer = '
                        <div class="form-text fg-warning mt-3">' . lang(array('string' => 'This request reached the server over plain HTTP through a proxy at {var:1} that reports HTTPS. It is not listed under Trusted Proxies, so Secure Mode cannot be turned on yet: list its address there first.', 'vars' => h($secure_mode_peer))) . '</div>';
    }
}

if ($security_ready) {

    $security_frame_protection_checked = ($security_frame_protection == 1) ? ' checked="checked"' : '';
    $security_hsts_checked             = ($security_hsts == 1) ? ' checked="checked"' : '';
    $security_csp_now                  = in_array($security_csp_mode, array('off', 'enforce'), true) ? $security_csp_mode : 'report';

    // The built-in policy, shown as the placeholder so the operator edits a
    // copy of what is actually sent rather than starting from nothing.
    $security_csp_placeholder = function_exists('waf_csp_policy') ? waf_csp_policy() : '';

    // While Maps is on and the site's own policy would refuse the tiles, the
    // line to add; the headers the software sends do not change with Maps.
    $security_csp_map_note = '';

    if (pg_schema_has('config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_tiles.php');
        $security_csp_map_note = pg_place_tiles_firewall_note($security_csp_policy);
    }

    if ($security_csp_map_note !== '') {
        $security_csp_map_note = '
                        <div class="alert theme-warning mt-3 mb-0 py-3 small">' . $security_csp_map_note . '</div>';
    }

    $output_security_csp_modes = '';
    $output_security_csp_texts = '';

    foreach (array(
        'off'     => array(lang('Off'), lang('No policy is sent.')),
        'report'  => array(lang('Report only'), lang('record what the policy would refuse, refuse nothing') . '. ' . lang('Start here. Browsers report every script, style, frame or connection the policy would have refused, and the Firewall Log screen lists them by source. When that list shows nothing the site needs, the policy is ready to enforce.')),
        'enforce' => array(lang('Enforce'), lang('refuse what the policy does not allow') . '. ' . lang('A source missing from the policy stops loading for every visitor. Enforce only after Report Only has run for a while on real traffic.')),
    ) as $security_csp_key => $security_csp_text) {
        $output_security_csp_modes .= '
                            <label class="pg-seg-item" for="security_csp_mode_' . $security_csp_key . '"><input class="visually-hidden" type="radio" name="security_csp_mode" id="security_csp_mode_' . $security_csp_key . '" value="' . $security_csp_key . '"' . (($security_csp_now === $security_csp_key) ? ' checked="checked"' : '') . '/>' . h($security_csp_text[0]) . '</label>';
        $output_security_csp_texts .= '
                        <div class="form-text"><strong>' . h($security_csp_text[0]) . '</strong> &mdash; ' . h($security_csp_text[1]) . '</div>';
    }

    $output_security_headers_block = '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $security_headers_checked . ' id="security_headers" name="security_headers" aria-describedby="security_headers_help"/>
                            <label class="pg-text-2" for="security_headers">' . lang('Send security headers') . '</label>
                        </div>
                        <div class="form-text" id="security_headers_help">' . lang('Adds X-Content-Type-Options, Referrer-Policy and Permissions-Policy to every response, plus the options below. These tell the browser what your pages are not allowed to do, and cost nothing.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $security_frame_protection_checked . ' id="security_frame_protection" name="security_frame_protection" aria-describedby="security_frame_protection_help"/>
                            <label class="pg-text-2" for="security_frame_protection">' . lang('Refuse framing by other sites') . '</label>
                        </div>
                        <div class="form-text" id="security_frame_protection_help">' . lang('Stops another site from showing your pages inside a frame, which is how a click on their page is turned into a click on yours. Turn off only if you embed this site in a frame on another domain on purpose.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $security_hsts_checked . ($secure_mode_checked === '' ? ' disabled="disabled"' : '') . ' id="security_hsts" name="security_hsts" aria-describedby="security_hsts_help"/>
                            <label class="pg-text-2" for="security_hsts">' . lang('Always HTTPS (HSTS)') . '</label>
                        </div>
                        <div class="form-text" id="security_hsts_help">' . lang('Tells browsers to use HTTPS for this site for a year, even when a link says http. Turn on only when the certificate has been in place and renewing reliably; a browser that has seen this header will refuse the site over plain HTTP until the year is up, and turning the setting off does not take that back.') . ' ' . lang('Available once Secure Mode is on and saved.') . '</div>
                        <script>
                            (function () {
                                var secure = document.getElementById("secure_mode");
                                var hsts = document.getElementById("security_hsts");
                                if (!secure || !hsts) { return; }
                                secure.addEventListener("change", function () {
                                    hsts.disabled = !secure.checked;
                                    if (!secure.checked) { hsts.checked = false; }
                                });
                            })();
                        </script>
                    </div>
                    <div class="col-12">
                        <fieldset><legend class="form-label pg-field-label">' . lang('Content security policy') . '</legend>
                        <div class="pg-seg flex-wrap">' . $output_security_csp_modes . '
                        </div>
                        </fieldset>' . $output_security_csp_texts . '
                    </div>
                    <div class="col-12">
                        <label for="security_csp_policy" class="form-label">' . lang('Policy') . '</label>
                        <textarea name="security_csp_policy" id="security_csp_policy" class="form-control font-monospace" rows="4" spellcheck="false" placeholder="' . h($security_csp_placeholder) . '">' . h($security_csp_policy) . '</textarea>
                        <div class="form-text">' . lang('Leave empty for the built-in policy shown above: the site\'s own files, inline code and the libraries the software itself uses are allowed, images, fonts and media may come from anywhere over https, and every other third-party source is reported. To allow one, copy the policy here and add the origin to the directive that reported it, for example https://www.googletagmanager.com to script-src. The report address is added for you.') . '</div>' . $security_csp_map_note . '
                        <a href="view_waf_log.php" class="pg-button pg-button-quiet pg-button-sm mt-3"><i class="bi bi-list-columns-reverse" aria-hidden="true"></i>' . lang('Policy reports') . '</a>
                    </div>';
}

$firewall_parts['headers'] = array('html' => '
    <div id="pgset-headers" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('HTTPS') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $secure_mode_checked . ' id="secure_mode" name="secure_mode" aria-describedby="secure_mode_help"/>
                            <label class="pg-text-2" for="secure_mode">' . lang('Secure Mode') . '</label>
                        </div>
                        <div class="form-text" id="secure_mode_help">' . lang('Every address the site writes starts with https:// and a plain http request is redirected. This is the switch the HSTS option below depends on.') . '</div>' . $output_secure_mode_offer . '
                        <div class="alert theme-warning mt-3 mb-0 py-3 small">
                            <p>' . lang(array('string' => 'Warning: Do not enable Secure Mode until you have {var:1} that your site has a working SSL Certificate.', 'vars' => array('<a class="alert-link" href="https://' . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/test_secure_mode.php" target="_blank">' . lang('verified') . '</a>'))) . '</p>
                            <p class="mb-0">' . lang('A padlock in your browser is not enough on its own. If SSL is terminated by a proxy or CDN before the request reaches this server (for example Cloudflare "Flexible" SSL), this server sees plain HTTP, and a proxy it does not trust would make Secure Mode redirect every request in an endless loop, making the site unreachable. The test page above reports what this server actually sees. When the request comes through a proxy that is not listed under Trusted Proxies yet, this card offers to list it as Secure Mode is turned on.') . '</p>
                        </div>
                    </div>' . $output_security_headers_block . '
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_https($secure_mode_checked !== '', (bool) $security_ready, $security_headers_checked !== '', $security_ready && ($security_hsts == 1), $security_csp_now);

// ── Logs ──
// The firewall log's retention and ceiling, and the plain-text block log a
// server's fail2ban can follow; the switch and its files only once its column
// exists.
$output_waf_text_log = '';

if ($security_ready) {

    $waf_text_log_path = function_exists('waf_text_log_file') ? waf_text_log_file() : '';

    // Everything a fail2ban jail needs, ready to paste. fail2ban cuts the
    // timestamp off before matching, so the expression tolerates the line
    // with or without it; the jail's own default ban action applies.
    $waf_fail2ban_snippet = "# /etc/fail2ban/filter.d/pinegrap.conf\n"
        . "[Definition]\n"
        . "failregex = ^(?:\\S+ \\S+ )?\\s*pinegrap-firewall (?:DENY|BAN) ip=<HOST>(?:\\s|$)\n"
        . "ignoreregex =\n"
        . "\n"
        . "# /etc/fail2ban/jail.d/pinegrap.conf\n"
        . "[pinegrap]\n"
        . "enabled  = true\n"
        . "filter   = pinegrap\n"
        . "logpath  = " . $waf_text_log_path . "\n"
        . "maxretry = 3\n"
        . "findtime = 600\n"
        . "bantime  = 3600";

    $output_waf_text_log = '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . (($waf_text_log == 1) ? ' checked="checked"' : '') . ' id="waf_text_log" name="waf_text_log" aria-describedby="waf_text_log_help"/>
                            <label class="pg-text-2" for="waf_text_log">' . lang('Plain-text block log') . '</label>
                        </div>
                        <div class="form-text" id="waf_text_log_help">' . lang('Writes one line per refused request and per automatic ban to a file that fail2ban can follow, so a server you manage yourself can drop a banned address at the network level instead of answering it. Kept under five megabytes; one older copy is retained.') . '</div>
                    </div>
                    <div class="col-12">
                        <details>
                            <summary class="form-text">' . lang('Log file and fail2ban') . '</summary>
                            <div class="pg-field-label mt-3">' . lang('Log File') . '</div>
                            <div class="font-monospace pg-text-meta text-break">' . h($waf_text_log_path) . '</div>
                            <div class="form-text">' . lang('Each line reads: date, time, pinegrap-firewall, DENY or BAN, then ip=, status=, rule=, the reference and path=. Not reachable from the web.') . '</div>
                            <div class="pg-field-label mt-5">' . lang('fail2ban Filter and Jail') . '</div>
                            <pre class="pg-text-meta bg-1 rounded-4 p-3 mb-0" style="white-space: pre-wrap;">' . h($waf_fail2ban_snippet) . '</pre>
                            <div class="form-text">' . lang('Two files, then restart fail2ban. On shared hosting there is nothing to install; the file is simply written and can be ignored, or switched off here.') . '</div>
                        </details>
                    </div>';
}

$firewall_parts['waf-logs'] = array('html' => '
    <div id="pgset-waf-logs" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Logs') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-xs">
                        <label for="waf_log_retention_days" class="form-label">' . lang('Log Retention') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                            <input type="text" name="waf_log_retention_days" id="waf_log_retention_days" class="form-ghost text-end" value="' . (int) $waf_log_retention_days . '" size="4" maxlength="4" inputmode="numeric"/>
                        </label>
                    </div>
                    <div class="pg-f-xs">
                        <label for="waf_log_max_rows" class="form-label">' . lang('Log Size Limit') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('row(s)') . '</span>
                            <input type="text" name="waf_log_max_rows" id="waf_log_max_rows" class="form-ghost text-end" value="' . (int) $waf_log_max_rows . '" size="7" maxlength="7" inputmode="numeric"/>
                        </label>
                        <div class="form-text">' . lang('Identical events within five minutes already share one row. This is the hard ceiling: the oldest rows are dropped beyond it, so an attack cannot fill the database.') . '</div>
                    </div>
                    <div class="col-12">
                        <a href="view_waf_log.php" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-list-columns-reverse" aria-hidden="true"></i>' . lang('Open the firewall log') . '</a>
                    </div>' . $output_waf_text_log . '
                </div>
            </div>
        </div>
    </div>') + pg_firewall_fold_logs($waf_log_retention_days, $waf_log_max_rows, (bool) $security_ready, $security_ready && ($waf_text_log == 1));

// ── The rows ──
$firewall_rows = array();

foreach (pg_firewall_fold_order() as $firewall_key) {
    $firewall_rows[] = array('key' => $firewall_key) + pg_firewall_fold_face($firewall_key) + $firewall_parts[$firewall_key];
}

$pg_settings_cards[] = pg_settings_fold($firewall_rows);
