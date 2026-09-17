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
 * Take the next number in a series.
 *
 * Must be called inside a transaction: the row lock it takes lives only as long
 * as that transaction, and the number is not really taken until the document
 * that carries it is committed alongside it.
 *
 * @param string $series
 * @param string $doc_kind   sales_invoice | proforma | waybill | collection | payment
 * @param int    $issue_year Numbering restarts each year
 * @return array ['success' => bool, 'number' => int, 'full' => string, 'error' => string]
 */
function erp_next_number($series, $doc_kind, $issue_year = 0)
{
    $series = trim((string) $series);
    $issue_year = ((int) $issue_year > 0) ? (int) $issue_year : (int) date('Y');

    if ($series === '') {
        return array('success' => false, 'number' => 0, 'full' => '', 'error' => lang('No invoice series is set.'));
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
            return array('success' => false, 'number' => 0, 'full' => '', 'error' => erp_db_error());
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

            return array('success' => false, 'number' => 0, 'full' => '',
                'error' => ($error !== '') ? $error : lang(array(
                    'string' => 'The {var:1} number series could not be opened.',
                    'vars' => $doc_kind,
                )));
        }
    }

    $number = ((int) $row['last_number']) + 1;

    if (erp_query("UPDATE erp_document_series SET last_number = '" . $number . "' WHERE id = '" . (int) $row['id'] . "'") === false) {
        return array('success' => false, 'number' => 0, 'full' => '', 'error' => erp_db_error());
    }

    $padding = max(1, (int) $row['padding']);
    $full = ((string) $row['prefix']) . $issue_year . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

    return array('success' => true, 'number' => $number, 'full' => $full, 'error' => '');
}
