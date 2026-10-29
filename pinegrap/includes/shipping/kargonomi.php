<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Kargonomi label driver: Kargonomi is a Turkish shipping aggregator with
 * one REST API (https://app.kargonomi.com.tr/api/v1, a bearer token) in
 * front of its own contracts with Aras, Sürat, PTT, Kolay Gelsin, HepsiJet
 * and other carriers. The driver follows its public reference
 * (www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/).
 *
 * Kargonomi has no test mode: confirming the price of a shipment buys a
 * real, charged label.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Kargonomi carrier code such as aras or surat). $reference is
 * Kargonomi's shipment id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_kargonomi_info()
{
    return array(
        'carriers' => array('surat', 'aras', 'ptt'),
        'formats'  => array('pdf'),
    );
}

function pg_label_kargonomi_fields()
{
    return array(
        array(
            'name'     => 'api_token',
            'type'     => 'secret',
            'label'    => lang('API Token'),
            'help'     => lang('Created in the Kargonomi panel. Kargonomi has no test mode: confirming the price buys a real, charged label.'),
            'required' => true,
        ),
        array(
            'name'       => 'warehouse_id',
            'label'      => lang('Warehouse ID'),
            'help'       => lang('The ID of a warehouse (sender address) in the Kargonomi panel. Shipments leave from it.'),
            'required'   => true,
            'max_length' => 20,
        ),
        array(
            'name'       => 'provider',
            'label'      => lang('Carrier Code'),
            'help'       => lang('A Kargonomi carrier code such as aras, surat or ptt. Empty takes the cheapest offer.'),
            'max_length' => 32,
        ),
    );
}
