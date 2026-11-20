<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Privacy centre (Contacts): the personal data the site keeps, table by
 * table, with its categories, whose data it is, why it is kept, the
 * suggested legal ground and retention and live counts; the outside services
 * that receive personal data and which of them sit abroad. Downloads the
 * same as an Excel workbook with the VERBİS columns and as a PDF that also
 * lists the destruction records (includes/fn/privacy_centre.php).
 *
 * Managers and up, as the personal data export and erasure beside it. The
 * screen and its downloads name no person: only counts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_centre.php');

include_once('liveform.class.php');
$liveform = new liveform('privacy_centre');

$privacy_centre_translate = 'lang';
$privacy_centre_country = function_exists('pg_erp_store_country') ? (string) pg_erp_store_country() : '';
$privacy_centre_transfers = pg_privacy_transfers_live($privacy_centre_country);
$privacy_centre_rows = pg_privacy_centre_rows(pg_privacy_inventory(), pg_privacy_inventory_counts(), $privacy_centre_transfers, $privacy_centre_translate);
$privacy_centre_transfer_rows = pg_privacy_centre_transfer_rows($privacy_centre_transfers, $privacy_centre_translate);
$privacy_centre_pdf_ready = is_file(PG_FUNCTIONS_DIR . '/includes/dompdf/autoload.inc.php');

// A download: the file, or the screen again with the reason it was not made.
$privacy_centre_download = is_string($_GET['download'] ?? null) ? $_GET['download'] : '';

if ($privacy_centre_download === 'xlsx') {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/xlsx_write.php');

    $privacy_centre_sheet = pg_privacy_centre_sheet($privacy_centre_rows, $privacy_centre_translate);
    $privacy_centre_directory = PG_FUNCTIONS_DIR . '/data/temp';
    $privacy_centre_path = (is_dir($privacy_centre_directory) || @mkdir($privacy_centre_directory, 0755, true)) ? @tempnam($privacy_centre_directory, 'privacy_') : false;

    if (($privacy_centre_path !== false) && pg_xlsx_write_plain($privacy_centre_sheet['headings'], $privacy_centre_sheet['rows'], $privacy_centre_path, lang('Personal data inventory'))) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . pg_privacy_centre_filename('xlsx') . '"');
        header('Content-Length: ' . (int) filesize($privacy_centre_path));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($privacy_centre_path);
        @unlink($privacy_centre_path);
        exit();
    }

    if ($privacy_centre_path !== false) {
        @unlink($privacy_centre_path);
    }

    $liveform->add_error(lang('The Excel file could not be made on this server.'));
} elseif (($privacy_centre_download === 'pdf') && $privacy_centre_pdf_ready) {
    $privacy_centre_bytes = pg_privacy_export_pdf(pg_privacy_centre_pdf_html(
        $privacy_centre_rows,
        $privacy_centre_transfer_rows,
        pg_privacy_centre_destruction_records(),
        array(
            'site'         => defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : (string) HOSTNAME,
            'prepared_by'  => (string) ($user['username'] ?? ''),
            'generated_at' => time(),
        ),
        $privacy_centre_translate
    ), array(0, 0, 841.89, 595.28));

    if (is_string($privacy_centre_bytes) && ($privacy_centre_bytes !== '')) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . pg_privacy_centre_filename('pdf') . '"');
        header('Content-Length: ' . strlen($privacy_centre_bytes));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        echo $privacy_centre_bytes;
        exit();
    }

    $liveform->add_error(lang('The PDF could not be made on this server. The Excel file holds the same inventory.'));
}

// The inventory, one line per table.
$privacy_centre_count = function ($value) {
    return ($value === null)
        ? '<span class="fg-secondary" title="' . h(lang('Not known: this site has no such table, or it could not be read.')) . '">–</span>'
        : pg_format_number((int) $value, 0);
};

$privacy_centre_lines = '';

foreach ($privacy_centre_rows as $privacy_centre_row) {
    $privacy_centre_lines .= '
                        <tr>
                            <td>' . h($privacy_centre_row['purpose']) . '<div class="small fg-secondary text-break">' . h($privacy_centre_row['table']) . '</div></td>
                            <td>' . h($privacy_centre_row['categories']) . '</td>
                            <td>' . h($privacy_centre_row['subjects']) . '</td>
                            <td>' . h($privacy_centre_row['ground']) . '</td>
                            <td>' . h($privacy_centre_row['retention']) . '</td>
                            <td class="text-end">' . $privacy_centre_count($privacy_centre_row['records']) . '</td>
                            <td class="text-end">' . $privacy_centre_count($privacy_centre_row['people']) . '</td>
                        </tr>';
}

$privacy_centre_inventory = '
            <div class="card mb-7">
                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Personal data')) . '</h2>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Purpose')) . '</th>
                                <th>' . h(lang('Data category')) . '</th>
                                <th>' . h(lang('Data subject group')) . '</th>
                                <th>' . h(lang('Legal ground')) . '</th>
                                <th>' . h(lang('Retention')) . '</th>
                                <th class="text-end">' . h(lang('Records')) . '</th>
                                <th class="text-end">' . h(lang('People')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $privacy_centre_lines . '
                        </tbody>
                    </table>
                </div>
            </div>';

// The transfers: services receiving personal data, abroad first.
$privacy_centre_transfer_lines = '';

foreach ($privacy_centre_transfer_rows as $privacy_centre_transfer) {
    $privacy_centre_transfer_lines .= '
                        <tr>
                            <td>' . h($privacy_centre_transfer['name']) . ($privacy_centre_transfer['unknown'] ? '<div class="small fg-secondary">' . h(lang('may be in use')) . '</div>' : '') . '</td>
                            <td>' . h($privacy_centre_transfer['country']) . ($privacy_centre_transfer['abroad'] ? ' <span class="pg-pill pg-tone-amber">' . h(lang('Abroad')) . '</span>' : '') . '</td>
                            <td>' . h($privacy_centre_transfer['data']) . '</td>
                        </tr>';
}

$privacy_centre_transfers_card = '
            <div class="card mb-7">
                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Transfers to outside services')) . '</h2>
                ' . (($privacy_centre_transfer_lines !== '') ? '<div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Service')) . '</th>
                                <th>' . h(lang('Country')) . '</th>
                                <th>' . h(lang('Data it receives')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $privacy_centre_transfer_lines . '
                        </tbody>
                    </table>
                </div>' : '<div class="card-body fg-secondary">' . h(lang('No outside service receives personal data.')) . '</div>') . '
                <div class="card-body pt-0 small fg-secondary">' . h(lang('The services that are connected, or whose state cannot be told, on the Connections screen. The country is where the company that runs the service is seated; check it for your own notice.')) . '</div>
            </div>';

$privacy_centre_toolbar = pg_screen_toolbar(array(
    'primary'   => array('label' => lang('Download Excel'), 'url' => 'privacy_centre.php?download=xlsx', 'icon' => 'bi-file-earmark-spreadsheet'),
    'secondary' => array_merge(
        $privacy_centre_pdf_ready ? array(array('label' => lang('Download PDF'), 'url' => 'privacy_centre.php?download=pdf', 'icon' => 'bi-filetype-pdf')) : array(),
        array(
            array('label' => lang('Personal data export'), 'url' => 'privacy_export.php', 'icon' => 'bi-person-down'),
            array('label' => lang('Personal data erasure'), 'url' => 'privacy_erase.php', 'icon' => 'bi-person-x'),
        )
    ),
));

echo
pg_page_shell(array(
        'title'               => lang('Privacy centre'),
        'extra classes'       => 'contact pg-measure-list',
        'icon'                => 'contact',
        'heading'             => lang('Privacy centre'),
        'heading_description' => lang('The personal data the site keeps, why and for how long, and the outside services it goes to, for your KVKK or GDPR records.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . $privacy_centre_toolbar . '
    <div class="alert theme-info mb-7">' . h(lang('The legal grounds and retentions are suggested defaults, not legal advice. Check them for your own notice and your VERBİS record. Erased data stays in backups made before the erasure until those backups are removed.')) . '</div>
    ' . $privacy_centre_inventory . '
    ' . $privacy_centre_transfers_card . '
</main>
' .
output_footer();

$liveform->remove_form();
