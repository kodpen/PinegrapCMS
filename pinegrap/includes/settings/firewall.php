<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Firewall cards: the request filter, the bot policy and the address lists.
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

// ── Firewall ──
$pg_settings_cards[] = '
    <div id="pgset-waf" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Firewall') . '</span>
                <a href="view_waf_log.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-columns-reverse me-1"></i>' . lang('Firewall Log') . '</a>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    ' . (($output_waf_external . $output_waf_self_ban . $output_waf_ip_check . $output_waf_identity) !== ''
                        ? '<div class="col-12">' . $output_waf_external . $output_waf_self_ban . $output_waf_ip_check . $output_waf_identity . '</div>'
                        : '') . '
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $waf_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_enabled" name="waf_enabled" data-bs-target="#waf_enabled_row"/>
                                                <label class="form-check-label" for="waf_enabled">' . lang('Enable Firewall') . '</label>
                                                <div class="form-text">' . lang('Inspects every request for injection, scripting and traversal attacks, limits request rates, and enforces the IP lists above.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="waf_enabled_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="col-12 ">
                                                            <label class="form-label">' . lang('Mode') . '</label>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="waf_mode" id="waf_mode_monitor" value="monitor"' . $waf_mode_monitor_checked . '/>
                                                                <label class="form-check-label" for="waf_mode_monitor"><strong>' . lang('Monitor') . '</strong> &mdash; ' . lang('record only, block nothing new') . '</label>
                                                                <div class="form-text">' . lang('Start here. The log fills with what blocking would have stopped, so you can confirm on your own traffic that nothing legitimate is caught before you switch it on.') . ' <strong>' . lang('The IP lists below are the exception: an address you banned stays banned in Monitor mode. Only turning the firewall off releases it.') . '</strong></div>
                                                            </div>
                                                            <div class="form-check ">
                                                                <input class="form-check-input" type="radio" name="waf_mode" id="waf_mode_block" value="block"' . $waf_mode_block_checked . '/>
                                                                <label class="form-check-label" for="waf_mode_block"><strong>' . lang('Block') . '</strong> &mdash; ' . lang('reject attacking requests') . '</label>
                                                                <div class="form-text">' . lang('Attacks receive a 403 response and repeat offenders are banned temporarily.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="waf_sensitivity" class="form-label">' . lang('Sensitivity') . '</label>
                                                            <select name="waf_sensitivity" id="waf_sensitivity" class="form-select">' . $output_waf_sensitivity_options . '</select>
                                                            <div class="form-text">' . lang('Each rule contributes a score. Sensitivity sets how much evidence is needed before a request counts as an attack.') . '</div>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="waf_log_retention_days" class="form-label">' . lang('Log Retention') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="waf_log_retention_days" id="waf_log_retention_days" class="form-control" value="' . (int) $waf_log_retention_days . '" size="4" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="waf_log_max_rows" class="form-label">' . lang('Log Size Limit') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="waf_log_max_rows" id="waf_log_max_rows" class="form-control" value="' . (int) $waf_log_max_rows . '" size="7" maxlength="7" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('row(s)') . '</span>
                                                            </div>
                                                            <div class="form-text">' . lang('Identical events within five minutes already share one row. This is the hard ceiling: the oldest rows are dropped beyond it, so an attack cannot fill the database.') . '</div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_signature_scan_checked . ' class="form-check-input" type="checkbox" id="waf_signature_scan" name="waf_signature_scan"/>
                                                                <label class="form-check-label" for="waf_signature_scan">' . lang('Attack Signature Scanning') . '</label>
                                                                <div class="form-text">' . lang('Checks query strings, form data, cookies and headers for SQL injection, cross-site scripting, path traversal and command injection. Signed-in staff are never scanned, because designers legitimately submit HTML, JavaScript and SQL.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_block_attack_tools_checked . ' class="form-check-input" type="checkbox" id="waf_block_attack_tools" name="waf_block_attack_tools"/>
                                                                <label class="form-check-label" for="waf_block_attack_tools">' . lang('Block Penetration Testing Tools') . '</label>
                                                                <div class="form-text">' . lang('Rejects sqlmap, Nikto, Acunetix, nuclei and similar scanners by their user agent, whatever else they claim to be.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_verify_bots_checked . ' class="form-check-input" type="checkbox" id="waf_verify_bots" name="waf_verify_bots"/>
                                                                <label class="form-check-label" for="waf_verify_bots">' . lang('Verify Search Engine Crawlers') . '</label>
                                                                <div class="form-text">' . lang('Confirms by reverse DNS that a visitor claiming to be Googlebot really is one. Anyone can type Googlebot into a user agent; without this check, doing so grants crawler privileges. A failed lookup is never treated as a forgery, so a DNS outage cannot block a real crawler.') . '</div>
                                                            </div>
                                                        </div>' . $output_waf_ai_block . '
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch ">
                                                                <input value="1"' . $waf_rate_limit_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_rate_limit" name="waf_rate_limit" data-bs-target="#waf_rate_limit_row"/>
                                                                <label class="form-check-label" for="waf_rate_limit">' . lang('Rate Limiting') . '</label>
                                                                <div class="form-text">' . lang('Caps how many requests one address may make per minute. Signed-in staff and verified crawlers are exempt.') . '</div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="waf_rate_limit_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="pg-f-xs">
                                                                            <label for="waf_rate_limit_requests" class="form-label">' . lang('All Requests') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_rate_limit_requests" id="waf_rate_limit_requests" class="form-control" value="' . (int) $waf_rate_limit_requests . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('per minute') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="pg-f-xs">
                                                                            <label for="waf_rate_limit_sensitive" class="form-label">' . lang('Sign-in, Forms and Checkout') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_rate_limit_sensitive" id="waf_rate_limit_sensitive" class="form-control" value="' . (int) $waf_rate_limit_sensitive . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('per minute') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="pg-f-xs">
                                                                            <label for="waf_rate_limit_api" class="form-label">' . lang('Live Chat and Store Pages') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_rate_limit_api" id="waf_rate_limit_api" class="form-control" value="' . (int) $waf_rate_limit_api . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('per minute') . '</span>
                                                                            </div>
                                                                            <div class="form-text">' . lang('The requests your pages make in the background. An open chat window alone asks about thirty times a minute.') . '</div>
                                                                        </div>
                                                                        ' . ($security_ready ? '<div class="pg-f-xs">
                                                                            <label for="waf_inflight_limit" class="form-label">' . lang('Open at Once') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_inflight_limit" id="waf_inflight_limit" class="form-control" value="' . (int) $waf_inflight_limit . '" size="4" maxlength="3" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('requests') . '</span>
                                                                            </div>
                                                                            <div class="form-text">' . lang('How many sign-in, form or checkout requests one address may have running at the same time. Catches a request that waits on e-mail or a payment gateway while holding a database connection, which the per-minute limits above cannot see. 0 turns it off.') . '</div>
                                                                        </div>' : '') . '
                                                                        <div class="col-12">
                                                                            <div class="form-text">' . lang('Offices and schools share one address across many people. If visitors report being rate limited, raise these numbers or add the address to the allowed list.') . '</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch ">
                                                                <input value="1"' . $waf_auto_ban_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_auto_ban" name="waf_auto_ban" data-bs-target="#waf_auto_ban_row"/>
                                                                <label class="form-check-label" for="waf_auto_ban">' . lang('Automatic Temporary Bans') . '</label>
                                                                <div class="form-text">' . lang('Repeat offenders are banned for a while instead of being blocked one request at a time. Bans always expire, and are never placed in Monitor mode.') . '</div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="waf_auto_ban_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="pg-f-xs">
                                                                            <label for="waf_auto_ban_threshold" class="form-label">' . lang('Ban After') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_auto_ban_threshold" id="waf_auto_ban_threshold" class="form-control" value="' . (int) $waf_auto_ban_threshold . '" size="4" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('events / 10 min') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="pg-f-xs">
                                                                            <label for="waf_auto_ban_minutes" class="form-label">' . lang('Ban Duration') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_auto_ban_minutes" id="waf_auto_ban_minutes" class="form-control" value="' . (int) $waf_auto_ban_minutes . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('minute(s)') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        ' . ($security_ready ? '<div class="pg-f-xs">
                                                                            <label for="waf_auto_ban_max_minutes" class="form-label">' . lang('Longest Ban') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_auto_ban_max_minutes" id="waf_auto_ban_max_minutes" class="form-control" value="' . (int) $waf_auto_ban_max_minutes . '" size="6" maxlength="6" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('minute(s)') . '</span>
                                                                            </div>
                                                                            <div class="form-text">' . lang('An address banned again after its ban ran out is banned four times as long each time, up to this ceiling. 10080 is seven days.') . '</div>
                                                                        </div>' : '') . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="waf_trusted_proxies" class="form-label">' . lang('Trusted Proxies') . '</label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_trusted_proxies)) . '" name="waf_trusted_proxies" id="waf_trusted_proxies" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add Address') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_trusted_proxies"))){
                                                                    tagin(document.querySelector("#waf_trusted_proxies"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Addresses of your own load balancer or CDN, one per line. Only these are allowed to declare a visitor address through forwarding headers, since those headers are trivially forged. Cloudflare is recognised automatically.') . '</div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="waf_exclusions" class="form-label">' . lang('Excluded Paths') . '</label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_exclusions)) . '" name="waf_exclusions" id="waf_exclusions" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add Path') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_exclusions"))){
                                                                    tagin(document.querySelector("#waf_exclusions"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Script names or URL paths that skip inspection entirely, before any other rule. Matched against the path only — never the query string, which anyone could append to steal the exemption. Intended for payment gateway callbacks, where losing a request loses a paid order, and for your own licence or update endpoint if this site serves other Pinegrap installations.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                </div>
            </div>
        </div>
    </div>';

// ── Bot Filtering ──
$pg_settings_cards[] = '
    <div id="pgset-bots" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Bot Filtering') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                                            <div class="form-text ">' . lang('Works on its own, whether or not the firewall above is enabled. Search engines, social link previews, uptime monitors and payment gateway callbacks are recognised and never blocked.') . '</div>
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $block_unknown_bots_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="block_unknown_bots" name="block_unknown_bots" data-bs-target="#block_unknown_bots_row"/>
                                                <label class="form-check-label" for="block_unknown_bots">' . lang('Block Unknown Bots') . '</label>
                                                <div class="form-text">' . lang('Crawlers that are not recognised and not on your allowed list receive a 403 response, reducing server load and preventing visitor count inflation. The REST API is exempt, so your own integrations keep working.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="block_unknown_bots_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="pg-f-lg">
                                                            <label for="allowed_bots" class="form-label"><i class="bi bi-check-circle text-success me-1"></i>' . lang('Allowed Bots') . ' <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">' . lang('never blocked') . '</span></label>
                                                            <input type="text" value="' . h(waf_list_to_string($allowed_bots)) . '" name="allowed_bots" id="allowed_bots" class="form-control tagin min-height-tagin border-success-subtle" data-placeholder="' . lang('Allow a crawler') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#allowed_bots"))){
                                                                    tagin(document.querySelector("#allowed_bots"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Extra crawlers to permit, on top of the built-in list. You do not need to list Google, Bing, Yandex, Facebook, Twitter or the payment gateways — those are already recognised.') . '</div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="waf_blocked_agents" class="form-label"><i class="bi bi-slash-circle text-danger me-1"></i>' . lang('Blocked Bots') . ' <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">' . lang('always blocked') . '</span></label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_blocked_agents)) . '" name="waf_blocked_agents" id="waf_blocked_agents" class="form-control tagin min-height-tagin border-danger-subtle" data-placeholder="' . lang('Block a crawler') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_blocked_agents"))){
                                                                    tagin(document.querySelector("#waf_blocked_agents"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Extra crawlers to reject. Checked after the built-in lists, so these can never override a search engine or a payment gateway.') . '</div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-text">' . lang('SEO scrapers and AI training crawlers (AhrefsBot, SemrushBot, GPTBot, ClaudeBot, Bytespider and similar) are already on the built-in reject list.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── IP Lists ──
$pg_settings_cards[] = '
    <div id="pgset-iplists" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('IP Lists') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                                            <div class="form-text ">' . lang('Checked before every other rule, and enforced whether or not the firewall is enabled. Behind a CDN these are matched against the real visitor address, not the edge server — provided the edge is listed under Trusted Proxies above.') . '</div>
                                           <div class="row gy-3">
                                                <div class="pg-f-lg">
                                                    <label for="allowed_ip_addresses" class="form-label"><i class="bi bi-check-circle text-success me-1"></i>' . lang('Allowed IP Addresses') . ' <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">' . lang('never blocked') . '</span></label>
                                                    <input type="text" value="' . h($output_allowed_ip_addresses) . '" name="allowed_ip_addresses" id="allowed_ip_addresses" class="form-control tagin min-height-tagin border-success-subtle" data-placeholder="' . lang('Allow an address') . '"/>
                                                    <div class="form-text">' . lang('These addresses skip every firewall rule. Add your own office or VPN address before enabling blocking, so a mistuned rule cannot lock you out of your own site.') . '</div>
                                                    <script>
                                                        if(document.body.contains(document.querySelector("input#allowed_ip_addresses"))){
                                                            tagin(document.querySelector("#allowed_ip_addresses"));
                                                        }
                                                    </script>
                                                </div>
                                                <div class="pg-f-lg">
                                                    <label for="banned_ip_addresses" class="form-label"><i class="bi bi-slash-circle text-danger me-1"></i>' . lang('Banned IP Addresses') . ' <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">' . lang('always blocked') . '</span></label>
                                                    <input type="text" value="' . h($output_banned_ip_addresses) . '" name="banned_ip_addresses" id="banned_ip_addresses" class="form-control tagin min-height-tagin border-danger-subtle" data-placeholder="' . lang('Block an address') . '"/>
                                                    <div class="form-text">' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . ' ' . lang('Enforced whatever mode the firewall is in, including Monitor, and before the database is even opened. Allowed addresses above still win.') . '</div>
                                                    <script>
                                                        if(document.body.contains(document.querySelector("input#banned_ip_addresses"))){
                                                            tagin(document.querySelector("#banned_ip_addresses"));
                                                        }
                                                    </script>
                                                </div>
                                                <div class="col-12">
                                                    <label for="banned_email_addresses" class="form-label"><i class="bi bi-envelope-slash text-danger me-1"></i>' . lang('Blocked Email Addresses') . '</label>
                                                    <input type="text" value="' . h($banned_email_addresses) . '" name="banned_email_addresses" id="banned_email_addresses" class="form-control tagin min-height-tagin border-danger-subtle" data-placeholder="' . lang('Block an address') . '"/>
                                                    <div class="form-text">' . lang('A whole address or a domain. For example: spam@example.com, tempmail.com, @disposable.net. A blocked address cannot register through any form, cannot connect Google, and cannot sign in.') . '</div>
                                                    <script>
                                                        if(document.body.contains(document.querySelector("input#banned_email_addresses"))){
                                                            tagin(document.querySelector("#banned_email_addresses"));
                                                        }
                                                    </script>
                                                </div>
                                                ' . ($output_auto_bans ? '<div class="col-12 ">
                                                    <label class="form-label">' . lang('Automatic Temporary Bans') . '</label>
                                                    <div>' . $output_auto_bans . '</div>
                                                    <div class="form-text">' . lang('Placed by the firewall and released automatically when they expire. Saving this screen does not clear them.') . '</div>
                                                </div>' : '') . '
                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── HTTPS and Security Headers ──
// Secure Mode (url_scheme) is always shown; the header, policy and text log
// switches only once their columns exist - a switch that saves nowhere is
// worse than no switch.
$security_headers_checked = '';
$output_security_headers_block = '';

if ($security_ready) {

    $security_headers_checked          = ($security_headers == 1) ? ' checked="checked"' : '';
    $security_frame_protection_checked = ($security_frame_protection == 1) ? ' checked="checked"' : '';
    $security_hsts_checked             = ($security_hsts == 1) ? ' checked="checked"' : '';
    $security_csp_off_checked          = ($security_csp_mode === 'off') ? ' checked="checked"' : '';
    $security_csp_enforce_checked      = ($security_csp_mode === 'enforce') ? ' checked="checked"' : '';
    $security_csp_report_checked       = ($security_csp_off_checked === '' && $security_csp_enforce_checked === '') ? ' checked="checked"' : '';
    $waf_text_log_checked              = ($waf_text_log == 1) ? ' checked="checked"' : '';

    // The built-in policy, shown as the placeholder so the operator edits a
    // copy of what is actually sent rather than starting from nothing.
    $security_csp_placeholder = function_exists('waf_csp_policy') ? waf_csp_policy() : '';

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

    $output_security_headers_block = '
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $security_headers_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="security_headers" name="security_headers" data-bs-target="#security_headers_row"/>
                                                <label class="form-check-label" for="security_headers">' . lang('Send Security Headers') . '</label>
                                                <div class="form-text">' . lang('Adds X-Content-Type-Options, Referrer-Policy and Permissions-Policy to every response, plus the options below. These tell the browser what your pages are not allowed to do, and cost nothing.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="security_headers_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="col-12 ">
                                                            <div class="form-check">
                                                                <input value="1"' . $security_frame_protection_checked . ' class="form-check-input" type="checkbox" id="security_frame_protection" name="security_frame_protection"/>
                                                                <label class="form-check-label" for="security_frame_protection">' . lang('Refuse Framing by Other Sites') . '</label>
                                                                <div class="form-text">' . lang('Stops another site from showing your pages inside a frame, which is how a click on their page is turned into a click on yours. Turn off only if you embed this site in a frame on another domain on purpose.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check">
                                                                <input value="1"' . $security_hsts_checked . ($secure_mode_checked === '' ? ' disabled="disabled"' : '') . ' class="form-check-input" type="checkbox" id="security_hsts" name="security_hsts"/>
                                                                <label class="form-check-label" for="security_hsts">' . lang('Always HTTPS (HSTS)') . '</label>
                                                                <div class="form-text">' . lang('Tells browsers to use HTTPS for this site for a year, even when a link says http. Turn on only when the certificate has been in place and renewing reliably; a browser that has seen this header will refuse the site over plain HTTP until the year is up, and turning the setting off does not take that back.') . ' ' . lang('Available once Secure Mode is on and saved.') . '</div>
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
                                                        </div>
                                                        <div class="col-12 ">
                                                            <label class="form-label">' . lang('Content Security Policy') . '</label>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="security_csp_mode" id="security_csp_mode_off" value="off"' . $security_csp_off_checked . '/>
                                                                <label class="form-check-label" for="security_csp_mode_off"><strong>' . lang('Off') . '</strong></label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="security_csp_mode" id="security_csp_mode_report" value="report"' . $security_csp_report_checked . '/>
                                                                <label class="form-check-label" for="security_csp_mode_report"><strong>' . lang('Report Only') . '</strong> &mdash; ' . lang('record what the policy would refuse, refuse nothing') . '</label>
                                                                <div class="form-text">' . lang('Start here. Browsers report every script, style, frame or connection the policy would have refused, and the Firewall Log screen lists them by source. When that list shows nothing the site needs, the policy is ready to enforce.') . '</div>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="security_csp_mode" id="security_csp_mode_enforce" value="enforce"' . $security_csp_enforce_checked . '/>
                                                                <label class="form-check-label" for="security_csp_mode_enforce"><strong>' . lang('Enforce') . '</strong> &mdash; ' . lang('refuse what the policy does not allow') . '</label>
                                                                <div class="form-text">' . lang('A source missing from the policy stops loading for every visitor. Enforce only after Report Only has run for a while on real traffic.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <label for="security_csp_policy" class="form-label">' . lang('Policy') . '</label>
                                                            <textarea name="security_csp_policy" id="security_csp_policy" class="form-control font-monospace" rows="4" spellcheck="false" placeholder="' . h($security_csp_placeholder) . '">' . h($security_csp_policy) . '</textarea>
                                                            <div class="form-text">' . lang('Leave empty for the built-in policy shown above: the site\'s own files, inline code and the libraries the software itself uses are allowed, images, fonts and media may come from anywhere over https, and every other third-party source is reported. To allow one, copy the policy here and add the origin to the directive that reported it, for example https://www.googletagmanager.com to script-src. The report address is added for you.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $waf_text_log_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_text_log" name="waf_text_log" data-bs-target="#waf_text_log_row"/>
                                                <label class="form-check-label" for="waf_text_log">' . lang('Plain-text Block Log') . '</label>
                                                <div class="form-text">' . lang('Writes one line per refused request and per automatic ban to a file that fail2ban can follow, so a server you manage yourself can drop a banned address at the network level instead of answering it. Kept under five megabytes; one older copy is retained.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="waf_text_log_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="col-12 ">
                                                            <label class="form-label">' . lang('Log File') . '</label>
                                                            <div class="font-monospace small text-break">' . h($waf_text_log_path) . '</div>
                                                            <div class="form-text">' . lang('Each line reads: date, time, pinegrap-firewall, DENY or BAN, then ip=, status=, rule= and reference. Not reachable from the web.') . '</div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <label class="form-label">' . lang('fail2ban Filter and Jail') . '</label>
                                                            <pre class="small bg-body-tertiary border rounded p-2 mb-0" style="white-space: pre-wrap;">' . h($waf_fail2ban_snippet) . '</pre>
                                                            <div class="form-text">' . lang('Two files, then restart fail2ban. On shared hosting there is nothing to install; the file is simply written and can be ignored, or switched off here.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
';
}

$pg_settings_cards[] = '
    <div id="pgset-headers" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('HTTPS and Security Headers') . '</span>
                <a href="view_waf_log.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-columns-reverse me-1"></i>' . lang('Policy Reports') . '</a>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $secure_mode_checked . ' class="form-check-input" type="checkbox" id="secure_mode" name="secure_mode"/>
                                                <label class="form-check-label" for="secure_mode">' . lang('Secure Mode') . '</label>
                                                <div class="form-text">' . lang('Every address the site writes starts with https:// and a plain http request is redirected. This is the switch the HSTS option below depends on.') . '</div>
                                            </div>
                                            <div class="alert alert-warning mt-2 mb-0">
                                                <p>' . lang(array('string'=>'Warning: Do not enable Secure Mode until you have {var:1} that your site has a working SSL Certificate.','vars'=>array('<a class="alert-link" href="https://' . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/test_secure_mode.php" target="_blank">' . lang('verified') . '</a>') )) . '</p>
                                                <p class="mb-0 small">' . lang('A padlock in your browser is not enough on its own. If SSL is terminated by a proxy or CDN before the request reaches this server (for example Cloudflare "Flexible" SSL), this server sees plain HTTP and Secure Mode will redirect every request in an endless loop, making the site unreachable. The test page above reports what this server actually sees. If it asks you to, set TRUST_PROXY_SSL_HEADERS in the config file before enabling Secure Mode.') . '</p>
                                            </div>
                                        </div>
                    ' . $output_security_headers_block . '
                </div>
            </div>
        </div>
    </div>';
