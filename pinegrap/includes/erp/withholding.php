<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - VAT withholding (KDV tevkifatı).
 *
 * On some sales the buyer, not the seller, pays part of the VAT to the tax
 * office: the invoice still shows the whole VAT, names the withholding code
 * and its share (4/10, 9/10 ...), and the buyer owes the seller the total
 * less the withheld part. The ERP keeps that shape on every document:
 *
 *   tax_total          the whole VAT
 *   withholding_total  the part of it the buyer pays to the tax office
 *   grand_total        what the account owes - subtotal - discount + VAT
 *                      - withholding - so the ledger, the settlements and
 *                      the aging read the document the way they always have
 *
 * A line carries its code, its share as a percentage of the line's VAT (40
 * for 4/10) and the withheld amount.
 *
 * The code list is the tax authority's (GİB, UBL-TR Kod Listeleri v1.38,
 * September 2025): 601-627 with the share each service or delivery carries,
 * and 801-825, the same subjects withheld in full. A code the list does not
 * know - one added after this file, or on a document someone else issued -
 * is kept with the share it came with.
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
 * The withholding codes: code => ['rate' => percentage of the VAT, 'label'].
 *
 * @return array
 */
function erp_withholding_codes()
{
    static $codes = null;

    if ($codes !== null) {
        return $codes;
    }

    // Subject => [partial code, share in tenths, full code]; a subject with
    // no full code is withheld in part only.
    $subjects = array(
        array('601', 4, '801', 'Construction work and the engineering, architecture and survey-project services done with it'),
        array('602', 9, '802', 'Survey, plan-project, consultancy, supervision and similar services'),
        array('603', 7, '803', 'Alteration, maintenance and repair of machinery, equipment, fixtures and vehicles'),
        array('604', 5, '804', 'Catering service'),
        array('605', 5, '805', 'Organisation service'),
        array('606', 9, '806', 'Labour supply services'),
        array('607', 9, '807', 'Private security service'),
        array('608', 9, '808', 'Building inspection services'),
        array('609', 7, '809', 'Contract textile and garment work, bag and shoe sewing, and brokerage of such work'),
        array('610', 9, '810', 'Finding or bringing customers to tourist shops'),
        array('611', 9, '811', 'Sports clubs\' broadcasting, advertising and naming rights'),
        array('612', 9, '812', 'Cleaning service'),
        array('613', 9, '813', 'Environmental and garden maintenance services'),
        array('614', 5, '814', 'Staff and student transport service'),
        array('615', 7, '815', 'Printing and publishing services of all kinds'),
        array('616', 5, '', 'Other services'),
        array('617', 7, '816', 'Ingots made from scrap metal'),
        array('618', 7, '817', 'Copper, zinc, iron and steel, aluminium and lead ingots not made from scrap'),
        array('619', 7, '818', 'Copper, zinc and aluminium products'),
        array('620', 7, '819', 'Scrap and waste delivered by those who waived the exemption'),
        array('621', 9, '820', 'Raw material made from metal, plastic, rubber, paper and glass scrap and waste'),
        array('622', 9, '821', 'Cotton, mohair, wool and fleece, raw hides and skins'),
        array('623', 5, '822', 'Wood and forest products'),
        array('624', 2, '823', 'Freight transport service'),
        array('625', 3, '824', 'Commercial advertising services'),
        array('626', 2, '', 'Other deliveries'),
        array('627', 5, '825', 'Iron and steel products'),
    );

    $codes = array();
    $full = array();

    foreach ($subjects as $subject) {
        $label = lang($subject[3]);
        $codes[$subject[0]] = array('rate' => $subject[1] * 10.0, 'label' => $label);

        if ($subject[2] !== '') {
            $full[$subject[2]] = array('rate' => 100.0, 'label' => $label);
        }
    }

    ksort($full, SORT_STRING);

    return $codes = $codes + $full;
}

/**
 * The share of a withholding as the tax authority writes it: 40 is "4/10".
 *
 * @param float $rate  Percentage of the VAT
 * @return string
 */
function erp_withholding_rate_text($rate)
{
    $rate = (float) $rate;

    if ($rate <= 0) {
        return '';
    }

    $tenths = $rate / 10;

    return (abs($tenths - round($tenths)) < 0.0001)
        ? ((int) round($tenths) . '/10')
        : erp_percent_text($rate);
}

/**
 * The share a code carries, or 0 for a code the list does not know.
 *
 * @param string $code
 * @return float
 */
function erp_withholding_code_rate($code)
{
    $codes = erp_withholding_codes();
    $code = trim((string) $code);

    return isset($codes[$code]) ? (float) $codes[$code]['rate'] : 0.0;
}

/**
 * How a line's withholding reads on a screen or a document: "601 · 4/10 ·
 * Construction work ...".
 *
 * @param string $code
 * @param float  $rate
 * @param bool   $with_name
 * @return string
 */
function erp_withholding_label($code, $rate, $with_name = true)
{
    $code = trim((string) $code);

    if (($code === '') && ((float) $rate <= 0)) {
        return '';
    }

    $codes = erp_withholding_codes();
    $parts = array_filter(array($code, erp_withholding_rate_text($rate)), 'strlen');

    if ($with_name && isset($codes[$code])) {
        $parts[] = $codes[$code]['label'];
    }

    return implode(' · ', $parts);
}

/**
 * Whether the line table carries the withheld amount (4.68).
 *
 * @return bool
 */
function erp_invoice_lines_have_withholding()
{
    static $has = null;

    if ($has === null) {
        $has = function_exists('waf_table_has_column') && waf_table_has_column('erp_invoice_items', 'withholding_amount');
    }

    return $has;
}
