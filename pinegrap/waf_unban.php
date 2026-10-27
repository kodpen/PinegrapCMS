<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Lift a firewall ban from the server's command line, run from the software
// directory:
//
//   php waf_unban.php 203.0.113.7         every block row covering the address
//   php waf_unban.php 2001:db8:1:2::/64   the row naming this range
//   php waf_unban.php --all               every automatic ban
//
// The rescue for an operator the firewall shuts out: Release, Allow and the
// settings save are panel screens behind the ban shield and the IP list, and
// no script is ever served to a banned address. It removes the rows, the
// offence buckets that earned them and the shield's block lines, and prints
// what it did. See pg_waf_unban().

// Command line only, checked before anything loads: over HTTP this script
// answers nothing, whoever asks.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

include('init.php');

$waf_unban_parsed = pg_waf_unban_parse_args(array_slice((array) ($_SERVER['argv'] ?? array()), 1));

if ($waf_unban_parsed === null) {
    echo lang('Lifts a firewall ban. Run from the software directory:') . "\n"
        . '  ' . pg_waf_unban_command('<address>') . '   ' . lang('every block entry covering the address, automatic or manual') . "\n"
        . '  ' . pg_waf_unban_command('<range>') . '     ' . lang('the block entry naming this range') . "\n"
        . '  ' . pg_waf_unban_command('--all') . '       ' . lang('every automatic ban') . "\n";
    exit(2);
}

$waf_unban_removed = pg_waf_unban($waf_unban_parsed);

if ($waf_unban_removed === false) {
    echo lang('The firewall ban list could not be read.') . "\n";
    exit(1);
}

foreach ($waf_unban_removed as $waf_unban_row) {
    echo lang(array(
        'string' => 'Removed {var:1} ({var:2}).',
        'vars'   => array($waf_unban_row['ip_address'], $waf_unban_row['source']))) . "\n";

    log_activity(lang(array(
        'string' => 'removed the firewall ban on {var:1} from the command line',
        'vars'   => $waf_unban_row['ip_address'])), 'waf_unban.php');
}

if (!$waf_unban_removed) {
    echo lang('No block entry matched; nothing was removed.') . "\n";
}

echo lang('The offence counters were cleared and the ban shield was rewritten.') . "\n";
