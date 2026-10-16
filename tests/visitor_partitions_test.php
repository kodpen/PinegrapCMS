<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/visitor_partitions.php: the months
 * a visitors table is split into, the statement that splits it, how its
 * partitions are read back, which months the general job opens and which the
 * cleanup drops, and where the files of a split table are.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $test with the site's clock in $zone, and puts the clock back.
function pg_test_visitor_partition_in_zone($zone, $test)
{
	$previous = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$test();
	} finally {
		date_default_timezone_set($previous);
	}
}

// A row of information_schema.PARTITIONS.
function pg_test_visitor_partition_row($name, $description, $method = 'RANGE', $expression = '`start_timestamp`')
{
	return array(
		'PARTITION_NAME' => $name,
		'PARTITION_METHOD' => $method,
		'PARTITION_EXPRESSION' => $expression,
		'PARTITION_DESCRIPTION' => $description,
	);
}

// A month starts at midnight of its first day by the site's clock, also in a
// month that changes to or from summer time; a partition is named after the
// month its last second falls in.
function test_visitor_partition_months_by_the_site_clock()
{
	pg_test_visitor_partition_in_zone('Europe/Berlin', function () {

		$october = mktime(0, 0, 0, 10, 1, 2026);

		pg_assert_same($october, pg_visitor_partition_month(mktime(23, 59, 59, 10, 31, 2026)));
		pg_assert_same($october, pg_visitor_partition_month($october));
		pg_assert_same('2026-11-01 00:00', date('Y-m-d H:i', pg_visitor_partition_next_month(mktime(12, 0, 0, 10, 25, 2026))), 'summer time ends on 25 October');
		pg_assert_same('2027-01-01 00:00', date('Y-m-d H:i', pg_visitor_partition_next_month(mktime(8, 0, 0, 12, 31, 2026))), 'into the next year');
		pg_assert_same('p202610', pg_visitor_partition_name(mktime(0, 0, 0, 11, 1, 2026)));
		pg_assert_same('p202612', pg_visitor_partition_name(mktime(0, 0, 0, 1, 1, 2027)));
	});
}

// One month from the oldest visit's to two months after now, each bounded by
// the start of the next month.
function test_visitor_partition_plan_from_the_oldest_visit()
{
	pg_test_visitor_partition_in_zone('Europe/Istanbul', function () {

		$now = mktime(14, 0, 0, 10, 16, 2026);
		$plan = pg_visitor_partition_plan(mktime(9, 30, 0, 7, 20, 2026), $now);

		pg_assert_same(array('p202607', 'p202608', 'p202609', 'p202610', 'p202611', 'p202612'), array_keys($plan));
		pg_assert_same(mktime(0, 0, 0, 8, 1, 2026), $plan['p202607']);
		pg_assert_same(mktime(0, 0, 0, 1, 1, 2027), $plan['p202612']);

		$bound = 0;

		foreach ($plan as $month_bound) {
			pg_assert_true($month_bound > $bound, 'bounds rise');
			$bound = $month_bound;
		}
	});
}

// An empty table starts at the current month; a visit from a clock running
// ahead does not move the start; very old visits share the first of at most
// 36 past months.
function test_visitor_partition_plan_edges()
{
	pg_test_visitor_partition_in_zone('UTC', function () {

		$now = mktime(14, 0, 0, 10, 16, 2026);

		pg_assert_same(array('p202610', 'p202611', 'p202612'), array_keys(pg_visitor_partition_plan(0, $now)), 'no visit yet');
		pg_assert_same(array('p202610', 'p202611', 'p202612'), array_keys(pg_visitor_partition_plan(mktime(0, 0, 0, 3, 1, 2027), $now)), 'oldest visit in the future');

		$plan = pg_visitor_partition_plan(mktime(0, 0, 0, 1, 1, 2015), $now);

		pg_assert_same(PG_VISITOR_PARTITION_PAST + 1 + PG_VISITOR_PARTITION_AHEAD, count($plan));
		pg_assert_same('p202310', key($plan), 'the first holds everything up to October 2023');
	});
}

// The key is widened only where it is (id); the partitions follow the other
// changes without a comma; any other key is not changed.
function test_visitor_partition_alter_sql()
{
	$months = array('p202610' => 1793491200, 'p202611' => 1796083200);
	$list = '(PARTITION `p202610` VALUES LESS THAN (1793491200), PARTITION `p202611` VALUES LESS THAN (1796083200), PARTITION `pmax` VALUES LESS THAN MAXVALUE)';

	pg_assert_same($list, pg_visitor_partition_list_sql($months));

	pg_assert_same(
		'DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `start_timestamp`) PARTITION BY RANGE (`start_timestamp`) ' . $list,
		pg_visitor_partition_alter_sql(array('id'), $months));

	pg_assert_same(
		'PARTITION BY RANGE (`start_timestamp`) ' . $list,
		pg_visitor_partition_alter_sql(array('ID', 'start_timestamp'), $months),
		'the key is right already');

	pg_assert_same(
		'ENGINE=InnoDB, ROW_FORMAT=DYNAMIC, DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `start_timestamp`) PARTITION BY RANGE (`start_timestamp`) ' . $list,
		pg_visitor_partition_alter_sql(array('id'), $months, array('ENGINE=InnoDB', 'ROW_FORMAT=DYNAMIC')));

	pg_assert_same(null, pg_visitor_partition_alter_sql(array('start_timestamp', 'id'), $months));
	pg_assert_same(null, pg_visitor_partition_alter_sql(array(), $months), 'no primary key');
}

// A table that is not partitioned, this file's months, and anything else.
function test_visitor_partition_layout()
{
	pg_assert_same(array('state' => 'missing', 'months' => array()), pg_visitor_partition_layout(array()));
	pg_assert_same('none', pg_visitor_partition_layout(array(pg_test_visitor_partition_row(null, null, null, null)))['state']);

	$monthly = array(
		pg_test_visitor_partition_row('p202609', '1790812800'),
		pg_test_visitor_partition_row('p202610', '1793491200'),
		pg_test_visitor_partition_row('pmax', 'MAXVALUE'),
	);

	pg_assert_same(array('state' => 'monthly', 'months' => array('p202609' => 1790812800, 'p202610' => 1793491200)), pg_visitor_partition_layout($monthly));

	$monthly[0]['PARTITION_EXPRESSION'] = 'start_timestamp';
	pg_assert_same('monthly', pg_visitor_partition_layout($monthly)['state'], 'MySQL 5.7 writes the column without backticks');

	$other = $monthly;
	$other[2] = pg_test_visitor_partition_row('p_future', 'MAXVALUE');
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'no pmax');

	$other = $monthly;
	array_pop($other);
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'nothing for later visits');

	$other = $monthly;
	$other[1]['PARTITION_DESCRIPTION'] = '1790812800';
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'bounds that do not rise');

	$other = $monthly;
	$other[1]['PARTITION_EXPRESSION'] = 'to_days(`stop_timestamp`)';
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'another expression');

	$other = array(pg_test_visitor_partition_row('p0', null, 'HASH', '`id`'), pg_test_visitor_partition_row('p1', null, 'HASH', '`id`'));
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'by hash');

	$other = $monthly;
	$other[0]['PARTITION_NAME'] = 'p`x';
	pg_assert_same('other', pg_visitor_partition_layout($other)['state'], 'a name that cannot go between backticks');
}

// The months up to two after now are opened after the last one; none when
// they are there; a name the table has already gets its bound added.
function test_visitor_partition_to_open()
{
	pg_test_visitor_partition_in_zone('Europe/Istanbul', function () {

		$now = mktime(10, 0, 0, 10, 16, 2026);
		$layout = array('state' => 'monthly', 'months' => array(
			'p202609' => mktime(0, 0, 0, 10, 1, 2026),
			'p202610' => mktime(0, 0, 0, 11, 1, 2026),
		));

		pg_assert_same(array(
			'p202611' => mktime(0, 0, 0, 12, 1, 2026),
			'p202612' => mktime(0, 0, 0, 1, 1, 2027),
		), pg_visitor_partition_to_open($layout, $now));

		$layout['months']['p202611'] = mktime(0, 0, 0, 12, 1, 2026);
		$layout['months']['p202612'] = mktime(0, 0, 0, 1, 1, 2027);

		pg_assert_same(array(), pg_visitor_partition_to_open($layout, $now), 'the coming months are there');
		pg_assert_same(array('p202701' => mktime(0, 0, 0, 2, 1, 2027)), pg_visitor_partition_to_open($layout, mktime(0, 0, 0, 11, 1, 2026)), 'a new month, one more');
		pg_assert_same(array(), pg_visitor_partition_to_open(array('state' => 'none', 'months' => array()), $now));

		// A bound three hours short of midnight, after a change of the time
		// zone: the next month starts there and ends at the next midnight.
		$layout = array('state' => 'monthly', 'months' => array('p202612' => mktime(21, 0, 0, 12, 31, 2026)));
		$open = pg_visitor_partition_to_open($layout, mktime(10, 0, 0, 10, 16, 2026));

		pg_assert_same(array('p202612_' . mktime(0, 0, 0, 1, 1, 2027)), array_keys($open));
		pg_assert_same(mktime(0, 0, 0, 1, 1, 2027), reset($open));
	});
}

// Every month wholly before the boundary goes; the month the boundary falls
// in stays, and so does the last month however old.
function test_visitor_partition_to_drop()
{
	$layout = array('state' => 'monthly', 'months' => array('p202606' => 100, 'p202607' => 200, 'p202608' => 300, 'p202609' => 400));

	pg_assert_same(array('p202606', 'p202607'), pg_visitor_partition_to_drop($layout, 200), 'a bound on the boundary holds only older visits');
	pg_assert_same(array('p202606'), pg_visitor_partition_to_drop($layout, 199));
	pg_assert_same(array('p202606', 'p202607', 'p202608'), pg_visitor_partition_to_drop($layout, 5000), 'never the last month');
	pg_assert_same(array(), pg_visitor_partition_to_drop($layout, 0), 'no boundary');
	pg_assert_same(array(), pg_visitor_partition_to_drop(array('state' => 'other', 'months' => array()), 5000));
}

// A partition's file is <table>#p#<name>.ibd on MySQL 8 and #P# before; the
// folder is also tried in lower case.
function test_visitor_partition_file_patterns()
{
	pg_assert_same(array('/var/lib/mysql/site/visitors#[pP]#*.ibd'), pg_visitor_partition_file_patterns('/var/lib/mysql/', 'site', 'visitors'));
	pg_assert_same(
		array('C:\\Data/My@002dSite/visitors#[pP]#*.ibd', 'C:\\Data/my@002dsite/visitors#[pP]#*.ibd'),
		pg_visitor_partition_file_patterns('C:\\Data\\', 'My-Site', 'visitors'));
	pg_assert_same(array(), pg_visitor_partition_file_patterns('', 'site', 'visitors'));
	pg_assert_same(array(), pg_visitor_partition_file_patterns('/var/lib/mysql', 'şirket', 'visitors'));
}

// A restored CREATE TABLE loses its partitions in either server's spelling;
// one without partitions is not touched.
function test_visitor_partition_restore_without_partitions()
{
	$mysql = "CREATE TABLE `pgr_new_visitors` (\n  `id` int unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`,`start_timestamp`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n/*!50100 PARTITION BY RANGE (`start_timestamp`)\n(PARTITION p202610 VALUES LESS THAN (1793491200) ENGINE = InnoDB,\n PARTITION pmax VALUES LESS THAN MAXVALUE ENGINE = InnoDB) */;\n";

	pg_assert_same("CREATE TABLE `pgr_new_visitors` (\n  `id` int unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`,`start_timestamp`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n", pg_restore_without_partitions($mysql));

	$mariadb = "CREATE TABLE `pgr_new_visitors` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`,`start_timestamp`)\n) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4\n PARTITION BY RANGE (`start_timestamp`)\n(PARTITION `p202610` VALUES LESS THAN (1793491200) ENGINE = InnoDB,\n PARTITION `pmax` VALUES LESS THAN MAXVALUE ENGINE = InnoDB);\n";

	pg_assert_same("CREATE TABLE `pgr_new_visitors` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`,`start_timestamp`)\n) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;", pg_restore_without_partitions($mariadb));

	pg_assert_same(null, pg_restore_without_partitions("CREATE TABLE `pgr_new_page` (\n  `id` int NOT NULL\n) ENGINE=InnoDB;\n"));
}
