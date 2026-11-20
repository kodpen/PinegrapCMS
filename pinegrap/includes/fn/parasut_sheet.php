<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The two import sheets Paraşüt hands out - sales invoices, and customers
 * and suppliers - as data: the help note, the heading row and the column
 * widths of each, written above the data rows by pg_xlsx_write_layout().
 *
 * Orders > Export for Paraşüt Selected (edit_orders.php) and the ERP
 * export's Paraşüt profiles (includes/erp/export.php) both write through
 * them. The headings are Paraşüt's own words and stay Turkish whatever the
 * panel language: its importer reads the data from row 4 under them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The first data row of both Paraşüt sheets: the note, an empty row and the
 * headings come before it.
 */
if (!defined('PG_PARASUT_SHEET_FIRST_ROW')) {
    define('PG_PARASUT_SHEET_FIRST_ROW', 4);
}

/**
 * The layout of one Paraşüt import sheet (pg_xlsx_write_layout()).
 *
 * @param string $kind  invoices | contacts
 * @return array
 */
function pg_parasut_sheet_layout($kind)
{
    if ($kind === 'contacts') {
        $note = "Müşteri ve Tedarikçiler\n\n" .
            "- Yıldız ile belirlenen alanları doldurmanız yeterlidir.\n" .
            "- Firmalar için Vergi Dairesi ve Vergi Numarasını yazın.\n" .
            "- Kişiler için T.C. Kimlik Numarasını yazın.\n" .
            "- Tablonun sütun yapısını bozmayın.\n" .
            "- Bu yardım metnini silmeyin.\n" .
            "- Açılış bakiyesi girişi yapılırken müşteri/tedarikçinin bakiye türünü iki harfli kodlardan biri olarak belirtin:\n" .
            "-- Müşteri/tedarikçinin Borcu Var: BV\n" .
            "-- Müşteri/tedarikçinin Alacağı Var: AV\n" .
            "-- Müşteri/tedarikçiye Verilmiş Avans var: VA\n" .
            "-- Müşteri/tedarikçiden Alınmış Avans var: AA\n" .
            "- Açılış bakiyesi döviz cinsi TRL, USD, EUR veya GBP olabilir. Döviz cinsi belirtilmediği takdirde TRL olarak kabul edilir.\n" .
            "- Şirketlerin müşteriniz mi tedarikçiniz mi olduğunu \"Müşteri/Tedarikçi\" kolonunda \"M\" veya \"T\" olarak belirtin. İkisi de geçerliyse bir tanesini yazmanız yeterlidir.\n\n" .
            "- Destek için destek@parasut.com veya 0212 292 04 94";

        $headings = array('FİRMA ADI * ', 'KISA ADI', 'MÜŞTERİ/TEDARİKÇİ *', 'E-POSTA', 'KATEGORİ', 'ADRES', 'İLÇE', 'İL', 'TELEFON', 'FAKS',
            'VERGİ DAİRESİ', 'VERGİ NUMARASI', 'TC KİMLİK NO', 'AÇILIŞ BAKİYESİ TARİHİ', 'AÇILIŞ BAKİYESİ DÖVİZ CİNSİ', 'AÇILIŞ BAKİYESİ TUTARI', 'AÇILIŞ BAKİYESİ TÜRÜ');

        return array(
            'title' => 'Musteri ve Tedarikciler',
            'first_row' => PG_PARASUT_SHEET_FIRST_ROW,
            'columns' => count($headings),
            'widths' => array(38.43, 29.29, 21, 21.71, 21.71, 28.86, 21.71, 21.71, 21.71, 21.71, 22.86, 27.86, 23.43, 21.86, 27.86, 23.43, 25.14),
            'head' => array(
                1 => array('style' => 'note', 'size' => 8, 'height' => 183.75, 'cells' => array(0 => $note)),
                2 => array('style' => 'note', 'height' => 14.1, 'cells' => array()),
                3 => array('style' => 'heading', 'cells' => $headings),
            ),
            'merge' => array('A1:E1'),
            'formats' => array(),
        );
    }

    $note = "Satış Faturaları\n\n" .
        "- Yıldız ile belirlenen alanları doldurmanız yeterlidir.\n" .
        "- Faturalar ile beraber Paraşüt’te kayıtlı olmayan Müşteriler ve Hizmet/Ürünler de oluşturulur.\n" .
        "- Paraşütte kayıtlı olan müşteriler içeri alınan faturalar ile ilişkilendirilir.\n" .
        "- Fatura Türü, “Fatura”, \"Taslak\" (ya da “Proforma”) veya \"Konaklama\" olabilir. Boş bırakmanız halinde “Fatura” olarak kaydedilir.\n" .
        "- Fatura döviz cinsi TRL, USD, EUR veya GBP olabilir. Döviz cinsi belirtilmediği takdirde TRL olarak kabul edilir.\n" .
        "- Proforma faturalarda fatura döviz kuru boş bırakılmalıdır. Eğer bir kur belirtilmişse göz ardı edilir. Faturalarda ise döviz kuru zorunludur.\n" .
        "- Vade tarihi olmayan veya ileri bir tarihe denk gelen faturalar açık fatura olarak içeri alınır. Geçmiş tarihli tahsilatlar gerçekleşti olarak varsayılır ve kasa hesabınıza eklenir.\n" .
        "- Yabancı döviz cinsinden kesilen faturalar için yapılan tahsilatların Türk Lirası karşılıklarınin girilmesi zorunludur. TL faturalarda ve diğer açık faturalarda bu alan boş bırakılmalıdır.\n" .
        "- Bir faturaya birden fazla hizmet/ürün eklemek için faturayı takip eden satırlarda sadece hizmet/ürün detaylarını doldurun.\n" .
        "- KDV Oranı 10 Temmuz 2023 itibariyle 0, 1, 10 veya 20 olmalıdır.\n" .
        "- Fatura Sıra Numarasının başına sıfır eklemenize gerek yoktur.\n" .
        "- Deponun belirtilmemesi durumunda ürünler varsayılan deponuzan çıkmış olarak kabul edilir.\n" .
        "- Konaklama Vergisi Oranı belirtilmemiş ise Konaklama Vergisi yok, oran 0 ise Konaklama Vergisi istisna kabul edilir.\n" .
        "- Tablonun sütun yapısını bozmayın.\n" .
        "- Bu yardım metnini silmeyin.\n\n" .
        "- Destek için destek@parasut.com veya 0212 292 04 94";

    $headings = array('MÜŞTERİ ÜNVANI *', 'FATURA İSMİ', 'FATURA TARİHİ', 'DÖVİZ CİNSİ', 'DÖVİZ KURU', 'VADE TARİHİ', 'TAHSİLAT TL KARŞILIĞI',
        'FATURA TÜRÜ', 'FATURA SERİ', 'FATURA SIRA NO', 'KATEGORİ', 'HİZMET/ÜRÜN *', 'HİZMET/ÜRÜN AÇIKLAMASI', 'ÇIKIŞ DEPOSU *', 'MİKTAR *',
        'BİRİM FİYATI *', 'İNDİRİM TUTARI', 'KDV ORANI *', 'ÖİV ORANI', 'KONAKLAMA VERGİSİ ORANI');

    return array(
        'title' => 'Satış Faturaları',
        'first_row' => PG_PARASUT_SHEET_FIRST_ROW,
        'columns' => count($headings),
        'widths' => array(44.5, 43.7, 15.2, 15.2, 15.2, 16.5, 16.5, 14.2, 13.3, 16.5, 10.8, 31.7, 31.7, 31.7, 9.5, 13.3, 13.3, 13.3, 12.7, 27.5),
        'head' => array(
            1 => array('style' => 'note', 'size' => 11, 'height' => 304.05, 'cells' => array(0 => $note)),
            2 => array('style' => 'note', 'height' => 14.25, 'cells' => array()),
            3 => array('style' => 'heading', 'height' => 16.5, 'cells' => $headings),
        ),
        'merge' => array('A1:F1'),
        'formats' => array(),
    );
}

/**
 * Cells set by column letter and row number as the data rows of a layout:
 * one row for each number from $first_row to the last one set, keyed by
 * column index. A number that was never set is an empty row.
 *
 * @param array $cells      row number => array(column letters => value)
 * @param int   $first_row
 * @return array
 */
function pg_parasut_sheet_rows($cells, $first_row)
{
    $rows = array();

    if (empty($cells)) {
        return $rows;
    }

    $last = max(array_map('intval', array_keys($cells)));

    for ($number = (int) $first_row; $number <= $last; $number++) {
        $row = array();

        foreach ((array) ($cells[$number] ?? array()) as $letters => $value) {
            $index = 0;
            foreach (str_split(strtoupper((string) $letters)) as $letter) {
                $index = ($index * 26) + (ord($letter) - 64);
            }
            $row[$index - 1] = $value;
        }

        ksort($row);
        $rows[] = $row;
    }

    return $rows;
}
