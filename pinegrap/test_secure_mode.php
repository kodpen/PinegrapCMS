<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// -----------------------------------------------------------------------------
// Secure Mode diagnostics.
//
// This script deliberately does NOT load init.php. Secure Mode enforcement lives
// in init.php, so loading it here would mean this page is the first thing to go
// down when Secure Mode is misconfigured - exactly when it is needed. It stays
// standalone so that it keeps answering while the rest of the site is locked out.
//
// The previous version of this page only echoed static HTML. That proved the
// browser had a TLS connection to *something*, which under Cloudflare Flexible
// SSL is always true, and said nothing about what this server sees. Enabling
// Secure Mode on that evidence produced an infinite redirect loop. This version
// reports what the origin actually receives.
//
// Only a fixed whitelist of protocol-related server variables is displayed, so
// no credentials or session data can leak. No database connection is made.
// -----------------------------------------------------------------------------

// Load the config file for TRUST_PROXY_SSL_HEADERS / REQUIRE_SECURE_MODE.
// It only defines constants, so this is safe without the rest of the bootstrap.
$config_file = dirname(__FILE__) . '/data/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
}

// The forwarded-scheme parser and the peer offer: the same code Secure Mode
// decides with, loadable without functions.php. The peer check loads waf.php
// for the Cloudflare ranges, which only defines. No database is opened: the
// Trusted Proxies list lives there, so this page says what would be listed,
// not whether it is.
if (!defined('PG_FUNCTIONS_DIR')) {
    define('PG_FUNCTIONS_DIR', dirname(__FILE__));
}
require_once(PG_FUNCTIONS_DIR . '/includes/fn/request_scheme.php');

// Minimal HTML escaper - h() lives in functions.php, which is not loaded here.
function tsm_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Minimal translator, reading the same language files as lang().
// SOFTWARE_LANGUAGE is stored in the database, which is not read here, so this
// falls back to the config file overrides and then to English.
function tsm_lang($string)
{
    static $translations = null;

    if ($translations === null) {
        $translations = array();

        $language = 'en';
        if (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE) {
            $language = ENFORCEMENT_SOFTWARE_LANGUAGE;
        } elseif (defined('DEFAULT_SOFTWARE_LANGUAGE') && DEFAULT_SOFTWARE_LANGUAGE) {
            $language = DEFAULT_SOFTWARE_LANGUAGE;
        }

        // The order pg_lang_map() (includes/fn/lang.php, not loaded here)
        // reads a language in: its shards in includes/local/<code>/, merged in
        // name order, else the single includes/local/<code>.json.
        $local = dirname(__FILE__) . '/includes/local/' . basename($language);
        $paths = glob($local . '/*.json');
        if (!is_array($paths) || (count($paths) === 0)) {
            $paths = array($local . '.json');
        }
        sort($paths, SORT_STRING);

        foreach ($paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $decoded = @json_decode((string) @file_get_contents($path), true);
                if (is_array($decoded)) {
                    foreach ($decoded as $key => $value) {
                        $translations[$key] = $value;
                    }
                }
            }
        }
    }

    return isset($translations[$string]) ? $translations[$string] : $string;
}

// The name of the header that claims HTTPS, or an empty string, for the
// report. KEEP IN SYNC with pg_forwarded_https() (includes/fn/request_scheme.php),
// which decides; this only names the header.
function tsm_detect_proxy_ssl_header()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded_proto = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if (strtolower(trim($forwarded_proto[0])) === 'https') {
            return 'X-Forwarded-Proto';
        }
    }
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) {
        return 'CF-Visitor';
    }
    if (!empty($_SERVER['HTTP_FORWARDED']) && preg_match('/proto\s*=\s*"?https"?/i', $_SERVER['HTTP_FORWARDED'])) {
        return 'Forwarded';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return 'X-Forwarded-SSL';
    }
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') {
        return 'Front-End-Https';
    }
    if (!empty($_SERVER['HTTP_X_ARR_SSL'])) {
        return 'X-ARR-SSL';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443') {
        return 'X-Forwarded-Port';
    }
    return '';
}


// --- Gather the facts ---------------------------------------------------------

// TLS terminated on this server. Set by the web server, cannot be forged by a client.
$direct_tls = (
    (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')
);

$proxy_header      = tsm_detect_proxy_ssl_header();
$proxy_tls         = pg_forwarded_https($_SERVER);
$trust_headers     = (defined('TRUST_PROXY_SSL_HEADERS') && TRUST_PROXY_SSL_HEADERS === true);
$behind_cloudflare = (!empty($_SERVER['HTTP_CF_RAY']) || !empty($_SERVER['HTTP_CF_VISITOR']));

// The peer that sent the headers, what the Firewall screen would offer to
// list for it, and whether it is trusted without the stored list (the
// config constant, or a Cloudflare edge address).
$peer         = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
$peer_offer   = pg_https_peer_offer($peer);
$peer_trusted = pg_https_peer_trusted($peer, '');

// What check_if_request_is_secure() returns for this request while the peer
// is not on Trusted Proxies; listed there, a forwarded claim counts too.
$effective_secure = ($direct_tls || ($proxy_tls && $peer_trusted));
$secure_if_listed = (!$effective_secure && $proxy_tls);


// --- Decide the verdict -------------------------------------------------------

if (!$direct_tls && !$proxy_tls) {
    // Nothing anywhere suggests TLS. Either the page was opened over plain HTTP,
    // or a proxy is terminating TLS without announcing it in any known header.
    $level   = 'warning';
    $title   = tsm_lang('This request did not arrive over HTTPS.');
    $summary = tsm_lang('Open this page again using an "https://" address. If you already did, then a proxy in front of this server is terminating SSL without sending any recognised forwarding header, and Secure Mode cannot be used safely.');
    $verdict = tsm_lang('Do not enable Secure Mode yet.');

} elseif ($direct_tls) {
    // Ideal case: TLS reaches this server.
    $level   = 'success';
    $title   = tsm_lang('SSL is terminated on this server.');
    $summary = tsm_lang('This server can see the encrypted connection directly, so Secure Mode will work without any extra configuration.');
    $verdict = tsm_lang('It is safe to enable Secure Mode.');

} elseif ($proxy_tls && $peer_trusted) {
    // Proxy topology, the peer trusted without the stored list: a Cloudflare
    // edge address, or the config constant.
    $level   = 'success';
    $title   = tsm_lang('SSL is terminated by a proxy this server trusts.');
    $summary = tsm_lang('The connection between this server and the proxy is plain HTTP, but the proxy is one whose forwarded headers count (a Cloudflare edge address, or TRUST_PROXY_SSL_HEADERS is enabled), so Secure Mode reads them correctly.');
    $verdict = tsm_lang('It is safe to enable Secure Mode.');

} else {
    // The headers say https from a peer this page cannot vouch for: Secure
    // Mode works once the peer is on Trusted Proxies, which this page does
    // not read. The Firewall screen checks the stored list and offers the
    // peer's own address when it is a loopback or private one.
    $level   = 'warning';
    $title   = str_replace('{var:1}', $peer, tsm_lang('SSL is terminated by a proxy at {var:1}.'));

    if ($peer_offer['entries']) {
        $summary = str_replace('{var:1}', implode(', ', $peer_offer['entries']), tsm_lang('Your browser has a secure connection to the proxy, which forwards the request to this server over plain HTTP. Secure Mode works once this proxy is listed under Settings > Firewall > Trusted Proxies; the Firewall screen offers to list {var:1} when you turn Secure Mode on. This page reads no site settings, so it cannot tell whether the proxy is listed already.'));
    } elseif ($peer_offer['kind'] === 'public') {
        $summary = tsm_lang('Your browser has a secure connection to the CDN or load balancer, which forwards the request to this server over plain HTTP. List it under Settings > Firewall > Trusted Proxies with all of its published address ranges before turning Secure Mode on; Cloudflare is recognised on its own. This page reads no site settings, so it cannot tell whether it is listed already.');
    } else {
        $summary = tsm_lang('Your browser has a secure connection to the proxy, which forwards the request to this server over plain HTTP. List its address under Settings > Firewall > Trusted Proxies before turning Secure Mode on. This page reads no site settings, so it cannot tell whether the proxy is listed already.');
    }

    $verdict = tsm_lang('Turn Secure Mode on from the Firewall settings, which check the proxy against Trusted Proxies before saving.');
}

// The kind of the peer's address, for the report.
$peer_kinds = array(
    'loopback' => tsm_lang('a loopback address'),
    'private'  => tsm_lang('a private address'),
    'public'   => tsm_lang('a public address'),
    'other'    => tsm_lang('a reserved address'),
);


// --- Server variables to display ---------------------------------------------

$rows = array(
    'HTTPS'             => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : null,
    'SERVER_PORT'       => isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : null,
    'REQUEST_SCHEME'    => isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : null,
    'REMOTE_ADDR'       => ($peer !== '') ? $peer : null,
    'X-Forwarded-Proto' => isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : null,
    'X-Forwarded-Port'  => isset($_SERVER['HTTP_X_FORWARDED_PORT']) ? $_SERVER['HTTP_X_FORWARDED_PORT'] : null,
    'X-Forwarded-SSL'   => isset($_SERVER['HTTP_X_FORWARDED_SSL']) ? $_SERVER['HTTP_X_FORWARDED_SSL'] : null,
    'Front-End-Https'   => isset($_SERVER['HTTP_FRONT_END_HTTPS']) ? $_SERVER['HTTP_FRONT_END_HTTPS'] : null,
    'X-ARR-SSL'         => isset($_SERVER['HTTP_X_ARR_SSL']) ? $_SERVER['HTTP_X_ARR_SSL'] : null,
    'Forwarded'         => isset($_SERVER['HTTP_FORWARDED']) ? $_SERVER['HTTP_FORWARDED'] : null,
    'CF-Visitor'        => isset($_SERVER['HTTP_CF_VISITOR']) ? $_SERVER['HTTP_CF_VISITOR'] : null,
    'CF-Ray'            => isset($_SERVER['HTTP_CF_RAY']) ? $_SERVER['HTTP_CF_RAY'] : null,
);

$not_set = tsm_lang('not set');

$alert_colors = array(
    'success' => array('#0f5132', '#d1e7dd', '#badbcc'),
    'warning' => array('#664d03', '#fff3cd', '#ffecb5'),
    'danger'  => array('#842029', '#f8d7da', '#f5c2c7'),
);
list($alert_text_color, $alert_background_color, $alert_border_color) = $alert_colors[$level];

$html_language = (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE)
    ? ENFORCEMENT_SOFTWARE_LANGUAGE
    : ((defined('DEFAULT_SOFTWARE_LANGUAGE') && DEFAULT_SOFTWARE_LANGUAGE) ? DEFAULT_SOFTWARE_LANGUAGE : 'en');

?>
<!DOCTYPE html>
<html lang="<?php echo tsm_h($html_language); ?>">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo tsm_h(tsm_lang('Secure Mode Test')); ?></title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5; margin: 0; padding: 2rem 1rem; background: #f8f9fa; color: #212529; }
        .wrap { max-width: 820px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin: 0 0 1.5rem; }
        h2 { font-size: 1.1rem; margin: 2rem 0 .75rem; }
        .alert { border: 1px solid; border-radius: .5rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
        .alert strong { display: block; margin-bottom: .5rem; font-size: 1.05rem; }
        .verdict { margin-top: .75rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: .5rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        th, td { text-align: left; padding: .55rem .85rem; border-bottom: 1px solid #dee2e6; font-size: .9rem; vertical-align: top; }
        th { width: 40%; font-weight: 600; background: #f1f3f5; }
        tr:last-child th, tr:last-child td { border-bottom: 0; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85em; background: #f1f3f5; padding: .1rem .35rem; border-radius: .25rem; word-break: break-all; }
        .muted { color: #6c757d; font-style: italic; }
        .note { background: #fff; border: 1px solid #dee2e6; border-left: 4px solid #6c757d; border-radius: .35rem; padding: .85rem 1.1rem; font-size: .9rem; margin-top: 1rem; }
        ol { padding-left: 1.25rem; }
        li { margin-bottom: .5rem; }
        .footer { margin-top: 2rem; font-size: .85rem; }
    </style>
</head>
<body>
<div class="wrap">

    <h1><?php echo tsm_h(tsm_lang('Secure Mode Test')); ?></h1>

    <div class="alert" style="color: <?php echo $alert_text_color; ?>; background: <?php echo $alert_background_color; ?>; border-color: <?php echo $alert_border_color; ?>;">
        <strong><?php echo tsm_h($title); ?></strong>
        <?php echo tsm_h($summary); ?>
        <div class="verdict"><?php echo tsm_h($verdict); ?></div>
    </div>

    <?php if ($secure_if_listed) { ?>
        <h2><?php echo tsm_h(tsm_lang('How to proceed')); ?></h2>
        <ol>
            <li>
                <?php echo tsm_h(tsm_lang('Preferred: change the proxy so that it connects to this server over HTTPS as well. In Cloudflare this means switching the SSL/TLS mode from "Flexible" to "Full" or "Full (strict)", which requires a certificate installed on this server.')); ?>
            </li>
            <li>
                <?php if ($peer_offer['entries']) { ?>
                    <?php echo tsm_h(str_replace('{var:1}', implode(', ', $peer_offer['entries']), tsm_lang('Or list the proxy under Settings > Firewall > Trusted Proxies: the Firewall screen offers to add {var:1} when you turn Secure Mode on, so no file needs editing.'))); ?>
                <?php } elseif ($peer_offer['kind'] === 'public') { ?>
                    <?php echo tsm_h(tsm_lang('Or list the CDN or load balancer under Settings > Firewall > Trusted Proxies with all of its published address ranges (Cloudflare is recognised on its own), then turn Secure Mode on.')); ?>
                <?php } else { ?>
                    <?php echo tsm_h(tsm_lang('Or list the proxy\'s address under Settings > Firewall > Trusted Proxies, then turn Secure Mode on.')); ?>
                <?php } ?>
            </li>
        </ol>
        <div class="note">
            <?php echo tsm_h(tsm_lang('If Secure Mode is already enabled and the site is unreachable, you do not need to edit the database. Add the line below to your config file to release the redirect loop, then load this page again.')); ?>
            <br><code>define('REQUIRE_SECURE_MODE', false);</code>
        </div>
    <?php } ?>

    <?php if ($behind_cloudflare && !$direct_tls) { ?>
        <div class="note">
            <?php echo tsm_h(tsm_lang('Cloudflare was detected in front of this server, and the connection from Cloudflare to this server is plain HTTP. This is the "Flexible" SSL mode.')); ?>
        </div>
    <?php } ?>

    <h2><?php echo tsm_h(tsm_lang('What this server sees')); ?></h2>

    <table>
        <?php foreach ($rows as $label => $value) { ?>
            <tr>
                <th><?php echo tsm_h($label); ?></th>
                <td>
                    <?php if ($value === null || $value === '') { ?>
                        <span class="muted"><?php echo tsm_h($not_set); ?></span>
                    <?php } else { ?>
                        <code><?php echo tsm_h($value); ?></code>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </table>

    <h2><?php echo tsm_h(tsm_lang('Result')); ?></h2>

    <table>
        <tr>
            <th><?php echo tsm_h(tsm_lang('SSL terminated on this server')); ?></th>
            <td><?php echo tsm_h($direct_tls ? tsm_lang('Yes') : tsm_lang('No')); ?></td>
        </tr>
        <tr>
            <th><?php echo tsm_h(tsm_lang('Forwarded header reporting HTTPS')); ?></th>
            <td><?php echo $proxy_tls ? '<code>' . tsm_h($proxy_header) . '</code>' : tsm_h(tsm_lang('No')); ?></td>
        </tr>
        <tr>
            <th><?php echo tsm_h(tsm_lang('Proxy address (REMOTE_ADDR)')); ?></th>
            <td><?php echo ($peer !== '') ? '<code>' . tsm_h($peer) . '</code>' : '<span class="muted">' . tsm_h($not_set) . '</span>'; ?></td>
        </tr>
        <tr>
            <th><?php echo tsm_h(tsm_lang('Kind of the proxy address')); ?></th>
            <td><?php echo isset($peer_kinds[$peer_offer['kind']]) ? tsm_h($peer_kinds[$peer_offer['kind']]) : '<span class="muted">' . tsm_h($not_set) . '</span>'; ?></td>
        </tr>
        <tr>
            <th><?php echo tsm_h(tsm_lang('Entries the Firewall screen offers for Trusted Proxies')); ?></th>
            <td><?php echo $peer_offer['entries'] ? '<code>' . tsm_h(implode(', ', $peer_offer['entries'])) . '</code>' : tsm_h(tsm_lang('nothing to add')); ?></td>
        </tr>
        <tr>
            <th><code>TRUST_PROXY_SSL_HEADERS</code></th>
            <td><?php echo tsm_h($trust_headers ? 'true' : (defined('TRUST_PROXY_SSL_HEADERS') ? 'false' : $not_set)); ?></td>
        </tr>
        <tr>
            <th><code>REQUIRE_SECURE_MODE</code></th>
            <td><?php echo tsm_h(defined('REQUIRE_SECURE_MODE') ? (REQUIRE_SECURE_MODE ? 'true' : 'false') : $not_set); ?></td>
        </tr>
        <tr>
            <th><?php echo tsm_h(tsm_lang('This request counts as secure')); ?></th>
            <td><strong><?php echo tsm_h($effective_secure ? tsm_lang('Yes') : ($secure_if_listed ? tsm_lang('Yes, once the proxy is listed under Trusted Proxies') : tsm_lang('No'))); ?></strong></td>
        </tr>
    </table>

    <p class="footer">[<a href="#" onclick="window.close(); return false;"><?php echo tsm_h(tsm_lang('Close Window')); ?></a>]</p>

</div>
</body>
</html>
