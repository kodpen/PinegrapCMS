<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's document reader (assets/js/pg_document_reader.js,
 * window.PGDocumentReader): the paragraphs of a pasted text or a document
 * in, with their styles, the page's sections out, each with its kind and an
 * unsure mark where the rules cannot decide. Runs in Node; skipped where
 * Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGDocumentReader loaded (as R); its
// `out` comes back decoded. Null when Node cannot run.
function document_reader_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_document_reader.js') . "\n"
		. "var R = window.PGDocumentReader, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgdocread');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Reads paragraphs (an array) or a pasted text (a string); the whole answer.
function document_reader_test_read($input)
{
	$json = json_encode($input, JSON_UNESCAPED_UNICODE);

	return document_reader_test_node(is_string($input)
		? 'out = R.read(R.paragraphs(' . $json . '));'
		: 'out = R.read(' . $json . ');');
}

// Each section as kind, heading, unsure, reason.
function document_reader_test_kinds($result)
{
	$kinds = array();

	foreach ($result['sections'] as $section) {
		$kinds[] = array($section['kind'], $section['heading'], $section['unsure'], $section['reason']);
	}

	return $kinds;
}

// A pasted Markdown text with one section of every kind is read in order,
// each sure, with its items in the words they were written in.
function test_document_reader_reads_every_kind()
{
	$text = "# Fresh Bakery\n\nBread baked every morning in the old town.\n\n"
		. "## Our services\n\n- **Wedding cakes** Made to order, three days ahead.\n- **Catering:** For offices and events.\n- **Workshops** Learn to bake sourdough.\n\n"
		. "## In numbers\n\n250+ happy clients\n12 years\n98% repeat orders\n\n"
		. "## FAQ\n\nDo you deliver?\n\nYes, within the city.\n\nAre your breads vegan?\n\nMost of them are.\n\n"
		. "## What clients say\n\n> \"The best croissant in town.\"\n\n— Ayşe Yılmaz, Istanbul\n\n"
		. "## Prices\n\n| Item | Price |\n|---|---|\n| Loaf | 45 TL |\n| Cake | 600 TL |\n\n"
		. "## Contact\n\ninfo@bakery.com\n+90 212 555 12 34\nBağdat Caddesi No: 12, Kadıköy\n\n"
		. "## Our story\n\nWe started in 1990 with one oven.\n\n"
		. "## Inside\n\n![The oven](oven.jpg)\n\n![The shop](shop.jpg)\n";

	$result = document_reader_test_read($text);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('intro', 'Fresh Bakery', false, null),
		array('cards', 'Our services', false, null),
		array('figures', 'In numbers', false, null),
		array('questions', 'FAQ', false, null),
		array('testimonial', 'What clients say', false, null),
		array('prices', 'Prices', false, null),
		array('contact', 'Contact', false, null),
		array('text', 'Our story', false, null),
		array('gallery', 'Inside', false, null),
	), document_reader_test_kinds($result), 'one section of every kind');

	$s = $result['sections'];
	pg_assert_same('p0', $s[0]['id'], 'a section is named by its first paragraph');
	pg_assert_same('Bread baked every morning in the old town.', $s[0]['items'][0]['text'], 'the intro keeps its lead');
	pg_assert_same(array('title' => 'Catering', 'text' => 'For offices and events.'), $s[1]['items'][1], 'a card from a bold lead');
	pg_assert_same(array('value' => '250+', 'label' => 'happy clients'), $s[2]['items'][0], 'a figure');
	pg_assert_same(array('question' => 'Do you deliver?', 'answer' => 'Yes, within the city.'), $s[3]['items'][0], 'a question and its answer');
	pg_assert_same(array('quote' => '"The best croissant in town."', 'name' => 'Ayşe Yılmaz', 'role' => 'Istanbul'), $s[4]['items'][0], 'a quotation with its name');
	pg_assert_same(array(array('Item', 'Price'), array('Loaf', '45 TL'), array('Cake', '600 TL')), $s[5]['items'][0]['rows'], 'the table rows');
	pg_assert_same(array('email', 'phone', 'address'), array_column($s[6]['items'], 'type'), 'the contact lines');
	pg_assert_same('+90 212 555 12 34', $s[6]['items'][1]['value'], 'the phone as written');
	pg_assert_same(array('oven.jpg', 'shop.jpg'), array_column($s[8]['items'], 'src'), 'the pictures');
}

// Paragraphs from a document carry its own style names: Title, Heading 2,
// List Paragraph with a bold lead, Quote. Short subheadings with their text
// are cards too; a table without prices is a table.
function test_document_reader_document_styles()
{
	$result = document_reader_test_read(array(
		array('style' => 'Title', 'text' => 'Kodpen Studio'),
		array('style' => 'Normal', 'text' => 'We build websites.'),
		array('style' => 'Heading 2', 'text' => 'What we do'),
		array('style' => 'Heading 3', 'text' => 'Design'),
		array('style' => 'Normal', 'text' => 'Pages that fit your brand.'),
		array('style' => 'Heading 3', 'text' => 'Hosting'),
		array('style' => 'Normal', 'text' => 'Fast and safe servers.'),
		array('style' => 'Heading 2', 'text' => 'Opening hours'),
		array('style' => 'table', 'rows' => array(array('Monday', '9-18'), array('Saturday', '10-14'))),
		array('style' => 'Heading 2', 'text' => 'Team'),
		array('style' => 'List Paragraph', 'text' => 'Ali Kaya – founder and designer', 'lead' => 'Ali Kaya'),
		array('style' => 'List Paragraph', 'text' => 'Zeynep Ak – developer', 'lead' => 'Zeynep Ak'),
	));

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('intro', 'Kodpen Studio', false, null),
		array('cards', 'What we do', false, null),
		array('table', 'Opening hours', false, null),
		array('cards', 'Team', false, null),
	), document_reader_test_kinds($result), 'document styles read');

	$s = $result['sections'];
	pg_assert_same(array('title' => 'Hosting', 'text' => 'Fast and safe servers.'), $s[1]['items'][1], 'a card from a subheading');
	pg_assert_same(array('title' => 'Ali Kaya', 'text' => 'founder and designer'), $s[3]['items'][0], 'the lead and the rest after its dash');
	pg_assert_same(array(2, 3, 4, 5, 6), $s[1]['paragraphs'], 'the paragraphs a section was read from');
}

// What the rules cannot decide is asked: a heading that names a kind its
// text does not show, and a body two readings fit equally.
function test_document_reader_unsure()
{
	$result = document_reader_test_read("# Shop\n\nHello.\n\n"
		. "## Frequently asked questions\n\nWe answer every mail within a day.\n\n"
		. "## Good to know\n\nIs it open on Sunday?\n\nYes.\n\nCan I park?\n\nYes.\n\n- **Parking** Behind the shop.\n- **Wifi** Free for guests.\n\n"
		. "## Questions\n\nIs it open on Sunday?\n\nYes.\n\nCan I park?\n\nYes.\n\n- **Parking** Behind the shop.\n- **Wifi** Free for guests.\n");

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('intro', 'Shop', false, null),
		array('text', 'Frequently asked questions', true, 'words'),
		array('questions', 'Good to know', true, 'mixed'),
		array('questions', 'Questions', false, null),
	), document_reader_test_kinds($result), 'unsure sections');

	$s = $result['sections'];
	pg_assert_same(array('text', 'questions'), $s[1]['options'], 'the kinds to ask about: the text and the heading');
	pg_assert_same(array('questions', 'cards'), $s[2]['options'], 'the kinds to ask about: both readings');
	pg_assert_same(array(), $s[3]['options'], 'the heading decides between two readings');
}

// A text with no marked headings: a short lone line before a block is a
// heading; a line broken mid-sentence is joined; content before the first
// heading is a section of its own; a long text is marked long.
function test_document_reader_plain_text()
{
	$paragraphs = document_reader_test_node('out = R.paragraphs(' . json_encode(
		"Fresh Bakery\n\nBread baked every morning.\n\nWhy choose us\n\nWe use stone mills and slow\nfermentation for all of our bread.\n\nContact us\n\nWrite to hello@bakery.com any time.",
		JSON_UNESCAPED_UNICODE
	) . ');');

	if ($paragraphs === null) {
		return;
	}

	pg_assert_same(array('heading', 'paragraph', 'heading', 'paragraph', 'heading', 'paragraph'), array_column($paragraphs, 'style'), 'plain headings found');
	pg_assert_same(array(1, 2, 2), array_values(array_filter(array_column($paragraphs, 'level'))), 'the first is the title');
	pg_assert_same('We use stone mills and slow fermentation for all of our bread.', $paragraphs[3]['text'], 'a broken line joined');

	$long = str_repeat('Words that go on. ', 120);
	$result = document_reader_test_read(array(
		array('text' => 'A line before any heading.'),
		array('style' => 'h1', 'text' => 'Title'),
		array('style' => 'h2', 'text' => 'Story'),
		array('text' => $long),
	));

	if ($result === null) {
		return;
	}

	pg_assert_same(array('text', 'intro', 'text'), array_column($result['sections'], 'kind'), 'content before the first heading');
	pg_assert_same(true, $result['sections'][2]['long'], 'a long text is marked');
	pg_assert_same(false, $result['sections'][0]['long'], 'a short one is not');
}

// Empty paragraphs are skipped by their index; a document past the limit is
// read up to it and says how much was left; no input has no sections.
function test_document_reader_skipped_and_cut()
{
	$result = document_reader_test_node('var many = []; for (var i = 0; i < 2005; i++) { many.push({text: "Line " + i + "."}); }'
		. 'out = [R.read([{style: "h1", text: "A"}, {text: "  "}, {style: "picture"}, {style: "table", rows: [["", ""]]}, {text: "B."}]), R.read(many).cut, R.read(null)];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(1, 2, 3), $result[0]['skipped'], 'empty paragraphs skipped');
	pg_assert_same(array(0, 4), $result[0]['sections'][0]['paragraphs'], 'the intro with its lead');
	pg_assert_same(5, $result[1], 'five paragraphs past the limit');
	pg_assert_same(array(), $result[2]['sections'], 'no input has no sections');
}

// The kinds, and heading words folded and matched in English and Turkish.
function test_document_reader_kinds_and_words()
{
	$result = document_reader_test_node('out = [R.KINDS, R.wordKinds("Sıkça Sorulan Sorular"), R.wordKinds("İletişim"), R.wordKinds("Our story")];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('intro', 'text', 'cards', 'questions', 'figures', 'testimonial', 'table', 'prices', 'contact', 'picture', 'gallery'), $result[0], 'the kinds');
	pg_assert_same(array('questions'), $result[1], 'a Turkish heading');
	pg_assert_same(array('contact'), $result[2], 'dotted capital I folded');
	pg_assert_same(array(), $result[3], 'a heading that names no kind');
}

// fits(): every kind a section's words can fill, with the items each
// would carry: its own reading first, the other readings, then text,
// which any words fill. The intro offers intro and text.
function test_document_reader_fits()
{
	$result = document_reader_test_node('var p = R.paragraphs("# Spa\n\nRest by the lake.\n\n## Treatments\n\n- **Massage** 60 minutes, 900 TL\n- **Sauna** 30 minutes\n");'
		. 'var s = R.read(p).sections; out = [R.fits(p, s[0]).map(function (f) { return f.kind; }), R.fits(p, s[1]), R.fits(p, null)];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('intro', 'text'), $result[0], 'the intro offers intro and text');
	pg_assert_same('cards', $result[1][0]['kind'], 'its own reading first');
	pg_assert_same(array('title' => 'Massage', 'text' => '60 minutes, 900 TL'), $result[1][0]['items'][0], 'with its items');
	pg_assert_same('text', $result[1][count($result[1]) - 1]['kind'], 'text last');
	pg_assert_same('item', $result[1][count($result[1]) - 1]['items'][0]['type'], 'text keeps the paragraphs');
	pg_assert_same(array(), $result[2], 'no section fills nothing');
}
