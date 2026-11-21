<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's QR poster: a printable page with the place's
 * name, its services and a large QR code leading to its number page
 * (queue.php, from=qr), printed or saved as a PDF by the browser. Opened
 * from the place's screen and the places list; roles 0-2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_waiting_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$poster_place = (isset($_GET['id']) && is_scalar($_GET['id'])) ? pg_waiting_place_get((int) $_GET['id']) : null;

if ($poster_place === null) {
    output_error(lang('The place was not found.'), 404);
}

$poster_url = pg_waiting_place_url($poster_place['id'], 'qr');
$poster_site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
$poster_services = '';

foreach ($poster_place['services'] as $service) {
    $poster_services .= '<li><b>' . h($service['letter']) . '</b> ' . h($service['name']) . '</li>';
}

header('Cache-Control: no-store, private');

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h(lang('QR poster') . ' · ' . $poster_place['name']) . '</title>
<style>
@page { size: A4; margin: 15mm; }
body { margin: 0; font-family: system-ui, sans-serif; color: #111; background: #fff; text-align: center; }
.poster { max-width: 170mm; margin: 0 auto; padding: 10mm 0; }
.poster-site { margin: 0; font-size: 14pt; color: #555; }
.poster h1 { margin: 4mm 0 2mm; font-size: 34pt; line-height: 1.15; }
.poster-address { margin: 0; font-size: 13pt; color: #555; }
.poster h2 { margin: 12mm 0 6mm; font-size: 26pt; }
.poster-qr svg { width: 110mm; height: 110mm; }
.poster ul { list-style: none; margin: 8mm 0 0; padding: 0; font-size: 16pt; line-height: 1.6; }
.poster-link { margin-top: 8mm; font-size: 11pt; color: #555; word-break: break-all; }
.poster-print { margin: 6mm 0 0; padding: 2.5mm 6mm; font: inherit; font-size: 12pt; cursor: pointer; }
@media print { .poster-print { display: none; } }
</style>
</head>
<body>
<main class="poster">
    ' . (($poster_site !== '') ? '<p class="poster-site">' . h($poster_site) . '</p>' : '') . '
    <h1>' . h($poster_place['name']) . '</h1>
    ' . (($poster_place['address'] !== '') ? '<p class="poster-address">' . h($poster_place['address']) . '</p>' : '') . '
    <h2>' . h(lang('Scan to take a number')) . '</h2>
    <div class="poster-qr">' . pg_qr_svg($poster_url, 0, array('level' => 'Q', 'label' => lang('QR code to take a number'))) . '</div>
    ' . (($poster_services !== '') ? '<ul>' . $poster_services . '</ul>' : '') . '
    <p class="poster-link">' . h($poster_url) . '</p>
    <button type="button" class="poster-print" onclick="window.print()">' . h(lang('Print or save as PDF')) . '</button>
</main>
</body>
</html>';
