<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/supplier_feed_drafts.php: a record's pictures, the
 * names a category is looked up by, a variant set's name, the spool file and
 * line of a record, and which records become which drafts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_sync.php');

function test_supplier_feed_drafts_record_images()
{
	$record = array(
		'Resimler/Resim'    => 'https://cdn.example.com/a.jpg',
		'Resimler/Resim[2]' => 'http://cdn.example.com/b.jpg',
		'Resimler/Resim[3]' => 'https://cdn.example.com/a.jpg',
		'Resimler/Resimx'   => 'https://cdn.example.com/other.jpg',
	);

	pg_assert_same(array('https://cdn.example.com/a.jpg', 'http://cdn.example.com/b.jpg'), pg_supplier_feed_record_images($record, 'Resimler/Resim'), 'the key and its repeats, each address once');
	pg_assert_same(array(), pg_supplier_feed_record_images($record, ''), 'no mapped field, no pictures');

	$one = array('images' => 'https://x.test/1.jpg, https://x.test/2,3.jpg;//x.test/4.jpg | https://x.test/5.jpg');

	pg_assert_same(array('https://x.test/1.jpg', 'https://x.test/2,3.jpg', '//x.test/4.jpg', 'https://x.test/5.jpg'), pg_supplier_feed_record_images($one, 'images'), 'one value split before each address, a comma inside one kept');
	pg_assert_same(array('https://x.test/1.jpg', 'https://x.test/2,3.jpg'), pg_supplier_feed_record_images($one, 'images', 2), 'at most $max');
	pg_assert_same(array(), pg_supplier_feed_record_images(array('images' => 'resim.jpg javascript:alert(1)'), 'images'), 'no web address, no picture');
}

function test_supplier_feed_drafts_category_names()
{
	pg_assert_same(array('Ev > Mutfak > Tencere', 'Tencere'), pg_supplier_feed_category_names(' Ev > Mutfak > Tencere '), 'the whole path, then its last step');
	pg_assert_same(array('Ev/Mutfak', 'Mutfak'), pg_supplier_feed_category_names('Ev/Mutfak'), 'a slash path');
	pg_assert_same(array('Mutfak'), pg_supplier_feed_category_names('Mutfak'), 'one name');
	pg_assert_same(array(), pg_supplier_feed_category_names('  '), 'none');
}

function test_supplier_feed_drafts_set_name()
{
	pg_assert_same('Pamuk Tişört', pg_supplier_feed_set_name(array('Pamuk Tişört - Kırmızı M', 'Pamuk tişört - Mavi L', 'Pamuk Tişört Siyah')), 'the words all names start with, no trailing separator');
	pg_assert_same('Kupa', pg_supplier_feed_set_name(array('Kupa', 'Bardak')), 'no shared word: the first name');
	pg_assert_same('Kupa Mavi', pg_supplier_feed_set_name(array('Kupa Mavi', 'Kupa Mavi')), 'the same names');
	pg_assert_same('', pg_supplier_feed_set_name(array('', ' ')), 'no names');
}

function test_supplier_feed_drafts_spool_bucket()
{
	pg_assert_same(pg_supplier_feed_spool_bucket('MDL-1', 'A'), pg_supplier_feed_spool_bucket('mdl-1', 'B'), 'one model, one file, whatever the key or case');

	$bucket = pg_supplier_feed_spool_bucket('', 'A-100');

	pg_assert_true(($bucket >= 0) && ($bucket < PG_SUPPLIER_FEED_SPOOL_BUCKETS), 'a file in range');
}

function test_supplier_feed_drafts_spool_line()
{
	$value = array('key' => 'A', 'model' => 'M1', 'name' => 'Kupa');

	pg_assert_same(array('m' => 'M1', 'k' => 'A', 'p' => 0, 'v' => $value), pg_supplier_feed_spool_line($value, array('product_id' => 0, 'dismissed' => 0)), 'a new record with its values');
	pg_assert_same(array('m' => 'M1', 'k' => 'A', 'p' => 7, 'v' => null), pg_supplier_feed_spool_line($value, array('product_id' => 7, 'dismissed' => 0)), 'a matched record of a model, without values');
	pg_assert_same(null, pg_supplier_feed_spool_line(array('key' => 'A', 'model' => ''), array('product_id' => 7, 'dismissed' => 0)), 'a matched record with no model: no line');
	pg_assert_same(null, pg_supplier_feed_spool_line(array('key' => 'A', 'model' => ''), array('product_id' => 0, 'dismissed' => 1)), 'a dismissed record makes no draft');
	pg_assert_same(null, pg_supplier_feed_spool_line($value, array('product_id' => 0, 'dismissed' => 1)), 'a dismissed record of a model: no line');
}

function test_supplier_feed_drafts_groups()
{
	$v = function ($key) {
		return array('key' => $key);
	};

	$rows = array(
		array('m' => 'M1', 'k' => 'A', 'p' => 0, 'v' => $v('A')),
		array('m' => 'm1', 'k' => 'B', 'p' => 0, 'v' => $v('B')),
		array('m' => 'M2', 'k' => 'C', 'p' => 0, 'v' => $v('C')),
		array('m' => 'M2', 'k' => 'D', 'p' => 9, 'v' => null),
		array('m' => '', 'k' => 'E', 'p' => 0, 'v' => $v('E')),
		array('m' => '', 'k' => 'e', 'p' => 0, 'v' => $v('e')),
		array('m' => 'M3', 'k' => 'F', 'p' => 0, 'v' => $v('F')),
		array('m' => 'M4', 'k' => 'G', 'p' => 0, 'v' => $v('G')),
	);
	$items = array(
		'f' => array('product_id' => 12, 'dismissed' => 0),
		'g' => array('product_id' => 0, 'dismissed' => 1),
	);

	pg_assert_same(array(
		array('model' => 'M1', 'new' => array($v('A'), $v('B')), 'products' => array()),
		array('model' => 'M2', 'new' => array($v('C')), 'products' => array(9)),
		array('model' => '', 'new' => array($v('e')), 'products' => array()),
	), pg_supplier_feed_draft_groups($rows, $items), 'a model in any case is one set; a matched record is what a new one joins; a key read twice counts once; a draft made already or a dismissed record makes none');
}

function test_supplier_feed_drafts_record_values()
{
	$values = pg_supplier_feed_record_values(
		array('code' => 'A', 'model' => ' M1 ', 'desc' => '<p>Kupa</p>', 'img' => 'https://x.test/1.jpg', 'img[2]' => 'https://x.test/2.jpg'),
		array('supplier_key' => 'code', 'model_code' => 'model', 'description' => 'desc', 'image' => 'img')
	);

	pg_assert_same('M1', $values['model'], 'the model code, trimmed');
	pg_assert_same('<p>Kupa</p>', $values['description'], 'the description');
	pg_assert_same(array('https://x.test/1.jpg', 'https://x.test/2.jpg'), $values['images'], 'the pictures');
}
