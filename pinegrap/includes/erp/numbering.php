<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - document numbering.
 *
 * A sales invoice series has to run without gaps, and a gap is the one thing
 * the tax authority does ask about. Two things follow from that:
 *
 * The next number is taken under a row lock, so two operators saving at the
 * same moment cannot be handed the same one.
 *
 * The number is spent only once the document is saved. Spending it when a form
 * opens leaves a hole in the series every time somebody opens one and walks
 * away.
 *
 * Only outgoing documents are numbered here. A purchase bill carries the
 * supplier's number, and a proforma is not an invoice, so they have their own
 * series or none at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The shapes a document number can take, keyed by the setting's value, each
 * with an example built on the given series.
 *
 *   gib         PGF2026000000001  series, year, nine digits; restarts yearly.
 *                                 The shape GİB gives e-invoice numbers.
 *   year        PGF-2026-0001     series, year, four digits or more; restarts
 *                                 yearly.
 *   continuous  PGF-000001        series and six digits or more; never
 *                                 restarts.
 *
 * @param string $series
 * @return array  style => example
 */
function erp_number_styles($series = 'PGF')
{
    $series = (trim((string) $series) !== '') ? trim((string) $series) : 'PGF';
    $year = date('Y');

    return array(
        'gib' => $series . $year . '000000001',
        'year' => $series . '-' . $year . '-0001',
        'continuous' => $series . '-000001',
    );
}

/**
 * The shape document numbers are given (config.erp_number_style), 'gib' when
 * it is not set or the upgrade has not run. Read once per request.
 *
 * @return string  gib | year | continuous
 */
function erp_number_style()
{
    static $style = null;

    if ($style === null) {
        $style = function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_number_style')
            ? (string) db_value("SELECT erp_number_style FROM config LIMIT 1")
            : 'gib';

        if (!array_key_exists($style, erp_number_styles())) {
            $style = 'gib';
        }
    }

    return $style;
}

/**
 * Take the next number in a series.
 *
 * Must be called inside a transaction: the row lock it takes lives only as long
 * as that transaction, and the number is not really taken until the document
 * that carries it is committed alongside it.
 *
 * @param string $series
 * @param string $doc_kind   sales_invoice | proforma | waybill | collection | payment
 * @param int    $issue_year Numbering restarts each year, unless the numbers
 *                           are continuous (erp_number_style())
 * @return array ['success' => bool, 'number' => int, 'full' => string, 'error' => string,
 *               'year' => int]  year: what the document stores as issue_year
 */
function erp_next_number($series, $doc_kind, $issue_year = 0)
{
    $series = trim((string) $series);
    $style = erp_number_style();
    $number_year = ((int) $issue_year > 0) ? (int) $issue_year : (int) date('Y');

    // A continuous series is one counter for all years: it is kept on the row
    // of year 0. The yearly shapes share their year's row, so a store that
    // moves between them carries on counting and never repeats a number.
    // The document stores the same year as its issue_year: a continuous
    // number belongs to no year, and filed under the issue date's year it
    // would meet the yearly number of the same value on the documents'
    // unique key (series, number, issue_year) - the first continuous
    // invoice of a store that had issued PGF2026000000001 would be refused.
    $issue_year = ($style === 'continuous') ? 0 : $number_year;

    if ($series === '') {
        return array('success' => false, 'number' => 0, 'full' => '', 'error' => lang('No invoice series is set.'), 'year' => $issue_year);
    }

    $row = db_item("SELECT id, last_number, prefix, padding FROM erp_document_series
        WHERE series = '" . escape($series) . "'
          AND doc_kind = '" . escape($doc_kind) . "'
          AND issue_year = '" . $issue_year . "'
        LIMIT 1 FOR UPDATE");

    if (!is_array($row)) {
        // First document of the year in this series.
        if (erp_query("INSERT INTO erp_document_series (series, doc_kind, issue_year, last_number, prefix, padding)
            VALUES ('" . escape($series) . "', '" . escape($doc_kind) . "', '" . $issue_year . "', 0, '" . escape($series) . "', 9)") === false) {
            return array('success' => false, 'number' => 0, 'full' => '', 'error' => erp_db_error(), 'year' => $issue_year);
        }

        $row = db_item("SELECT id, last_number, prefix, padding FROM erp_document_series
            WHERE series = '" . escape($series) . "'
              AND doc_kind = '" . escape($doc_kind) . "'
              AND issue_year = '" . $issue_year . "'
            LIMIT 1 FOR UPDATE");

        if (!is_array($row)) {
            // The insert reported no error but the row is not there. That is
            // what a value the column will not store looks like from here, and
            // an empty error message is the least useful thing to hand back.
            $error = erp_db_error();

            return array('success' => false, 'number' => 0, 'full' => '', 'year' => $issue_year,
                'error' => ($error !== '') ? $error : lang(array(
                    'string' => 'The {var:1} number series could not be opened.',
                    'vars' => $doc_kind,
                )));
        }
    }

    $number = ((int) $row['last_number']) + 1;

    if (erp_query("UPDATE erp_document_series SET last_number = '" . $number . "' WHERE id = '" . (int) $row['id'] . "'") === false) {
        return array('success' => false, 'number' => 0, 'full' => '', 'error' => erp_db_error(), 'year' => $issue_year);
    }

    switch ($style) {
        case 'year':
            $full = $series . '-' . $number_year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            break;

        case 'continuous':
            $full = $series . '-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            break;

        default:
            // The row's prefix and padding: the GİB shape as it was opened.
            $padding = max(1, (int) $row['padding']);
            $full = ((string) $row['prefix']) . $number_year . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    return array('success' => true, 'number' => $number, 'full' => $full, 'error' => '', 'year' => $issue_year);
}
