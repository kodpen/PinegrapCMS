<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Word paragraph's kind (includes/fn/office_docx_kind.php): the
 * kinds the preview reads, a save that uses the document's own heading
 * styles and numbering, one that adds the product's own to a document that
 * has none, the paragraph properties kept in their order, the untouched
 * parts and paragraphs byte for byte, and the documents refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_kind_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// A document package written from parts; the content types, the package
// relationships and the main part's relationships are made from the list
// of parts unless given.
function office_docx_kind_test_file($parts, $relationships = null)
{
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';

	if ($relationships === null) {
		$relationships = '';
		$number = 1;

		foreach (array('styles', 'numbering', 'settings') as $kind) {
			if (isset($parts['word/' . $kind . '.xml'])) {
				$relationships .= '<Relationship Id="rId' . $number++ . '" Type="' . $rel . $kind . '" Target="' . $kind . '.xml"/>';
			}
		}
	}

	$parts += array(
		'[Content_Types].xml' => $types,
		'_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'officeDocument" Target="word/document.xml"/></Relationships>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>',
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_kind_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

function office_docx_kind_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_docx_kind_test_done($path)
{
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	@unlink($path);
}

function office_docx_kind_test_body($paragraphs)
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:document xmlns:w="' . office_docx_kind_test_w() . '"><w:body>'
		. implode('', $paragraphs) . '<w:sectPr/></w:body></w:document>';
}

// A document with its own headings (a Turkish Word's ids), a List Bullet
// style, a bulleted and a numbered numbering.
function office_docx_kind_test_own_parts()
{
	$w = office_docx_kind_test_w();

	return array(
		'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:styles xmlns:w="' . $w . '" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006">'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Balk1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="a2"><w:name w:val="heading 2"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="ListeMaddemi"><w:name w:val="List Bullet"/><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr></w:style>'
			. '<w:style w:type="paragraph" w:styleId="ListeParagraf"><w:name w:val="List Paragraph"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="GvdeMetni"><w:name w:val="Body Text"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/></w:style>'
			. '</w:styles>',
		'word/numbering.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:numbering xmlns:w="' . $w . '">'
			. '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl></w:abstractNum>'
			. '<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum>'
			. '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
			. '<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>'
			. '</w:numbering>',
		'word/document.xml' => office_docx_kind_test_body(array(
			'<w:p><w:pPr><w:pStyle w:val="Balk1"/></w:pPr><w:r><w:t>Başlık</w:t></w:r></w:p>',
			'<w:p><w:pPr><w:pStyle w:val="a2"/></w:pPr><w:r><w:t>Alt başlık</w:t></w:r></w:p>',
			'<w:p><w:pPr><w:pStyle w:val="ListeMaddemi"/></w:pPr><w:r><w:t>Madde</w:t></w:r></w:p>',
			'<w:p><w:pPr><w:pStyle w:val="ListeParagraf"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Bir</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Yanında</w:t></w:r></w:p>',
			'<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>Kapak</w:t></w:r></w:p>',
			'<w:p><w:pPr><w:pStyle w:val="GvdeMetni"/><w:jc w:val="center"/></w:pPr><w:r><w:t>Gövde</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Tek başına</w:t></w:r></w:p>',
			'<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>',
		)),
	);
}

function test_office_docx_kind_request()
{
	pg_assert_same('heading2', pg_office_docx_kind_request('heading2'), 'a kind');
	pg_assert_same('number', pg_office_docx_kind_request('number'), 'a numbered list');
	pg_assert_same(null, pg_office_docx_kind_request('heading4'), 'a heading the choice does not offer');
	pg_assert_same(null, pg_office_docx_kind_request(array('bullet')), 'not a word');
	pg_assert_same(null, pg_office_docx_kind_request(''), 'nothing');
}

function test_office_docx_kind_read()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the read is not run');
		return;
	}

	$path = office_docx_kind_test_file(office_docx_kind_test_own_parts());
	$read = pg_office_read($path, 'rapor.docx', true);

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array('0' => 'heading1', '1' => 'heading2', '2' => 'bullet', '3' => 'number', '4' => 'normal', '5' => 'other', '6' => 'normal', '7' => 'normal', '8' => 'normal'),
		json_decode(json_encode($read['edit']['kinds']), true), 'each paragraph\'s kind, by the style\'s built-in name and the numbering');
	pg_assert_contains('<h2 data-p="1">Alt başlık</h2>', $read['html'], 'a heading whose id does not say so is drawn as one by its name');

	office_docx_kind_test_done($path);
}

function test_office_docx_kind_save_with_own_styles()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_docx_kind_test_own_parts();
	$path = office_docx_kind_test_file($parts);
	$read = pg_office_read($path, 'rapor.docx', true);
	$directory = sys_get_temp_dir();

	$refused = pg_office_docx_save($path, array(array('p' => 4, 'kind' => 'heading9')), $read['version'], $directory);
	pg_assert_same('none', $refused['code'], 'a kind that is not one');

	$saved = pg_office_docx_save($path, array(
		array('p' => 4, 'kind' => 'number'),
		array('p' => 6, 'kind' => 'bullet'),
		array('p' => 7, 'kind' => 'number'),
		array('p' => 0, 'kind' => 'normal'),
		array('p' => 2, 'kind' => 'heading1'),
		array('p' => 1, 'kind' => 'heading2'),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(5, $saved['changed'], 'five paragraphs, the one that had its kind already left alone');

	$back = pg_office_read($path, 'rapor.docx', true);
	pg_assert_same(array('0' => 'normal', '1' => 'heading2', '2' => 'heading1', '3' => 'number', '4' => 'number', '5' => 'other', '6' => 'bullet', '7' => 'number', '8' => 'normal'),
		json_decode(json_encode($back['edit']['kinds']), true), 'read back as asked');

	$xml = office_docx_kind_test_part($path, 'word/document.xml');
	pg_assert_contains('<w:p><w:pPr><w:pStyle w:val="ListeParagraf"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Yanında</w:t></w:r></w:p>', $xml, 'next to a numbered list, it joins that list with the document\'s list style');
	pg_assert_contains('<w:p><w:pPr><w:pStyle w:val="GvdeMetni"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Gövde</w:t></w:r></w:p>', $xml, 'a plain style and the alignment kept, the numbering put in its place');
	pg_assert_contains('<w:p><w:pPr><w:pStyle w:val="ListeParagraf"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="3"/></w:numPr></w:pPr><w:r><w:t>Tek başına</w:t></w:r></w:p>', $xml, 'a numbered list on its own starts again');
	pg_assert_contains('<w:p><w:pPr/><w:r><w:t>Başlık</w:t></w:r></w:p>', $xml, 'normal text has no style');
	pg_assert_contains('<w:p><w:pPr><w:pStyle w:val="Balk1"/></w:pPr><w:r><w:t>Madde</w:t></w:r></w:p>', $xml, 'the document\'s own heading, the list style gone');
	pg_assert_contains('<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>Kapak</w:t></w:r></w:p><w:p>', $xml, 'an untouched paragraph byte for byte');
	pg_assert_contains('<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>', $xml, 'another untouched paragraph byte for byte');

	pg_assert_same($parts['word/styles.xml'], office_docx_kind_test_part($path, 'word/styles.xml'), 'the styles untouched: the document had its headings');

	$numbering = office_docx_kind_test_part($path, 'word/numbering.xml');
	$cut = strpos($parts['word/numbering.xml'], '</w:numbering>');
	pg_assert_same(substr($parts['word/numbering.xml'], 0, $cut), substr($numbering, 0, $cut), 'the numbering kept up to the new num');
	pg_assert_same('<w:num w:numId="3"><w:abstractNumId w:val="1"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num></w:numbering>', substr($numbering, $cut), 'a new num on the document\'s own numbered list, from 1');
	pg_assert_same(false, strpos(office_docx_kind_test_part($path, '[Content_Types].xml'), 'numbering'), 'no part added');

	office_docx_kind_test_done($path);
}

function test_office_docx_kind_save_adds_its_own()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$w = office_docx_kind_test_w();
	$parts = array(
		'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:styles xmlns:w="' . $w . '">'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			. '<w:style w:type="character" w:styleId="Heading2"><w:name w:val="Something"/></w:style></w:styles>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '"/>',
		'word/document.xml' => office_docx_kind_test_body(array(
			'<w:p><w:r><w:t>Giriş</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Elma</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Armut</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Birinci</w:t></w:r></w:p>',
			'<w:p><w:r><w:t>Son</w:t></w:r></w:p>',
		)),
	);
	$path = office_docx_kind_test_file($parts);
	$read = pg_office_read($path, 'yeni.docx', true);

	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'kind' => 'heading2', 'text' => 'Giriş bölümü'),
		array('p' => 1, 'kind' => 'bullet'),
		array('p' => 2, 'kind' => 'bullet'),
		array('p' => 3, 'kind' => 'number'),
	), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(4, $saved['changed'], 'four paragraphs');

	$back = pg_office_read($path, 'yeni.docx', true);
	pg_assert_same(array('0' => 'heading2', '1' => 'bullet', '2' => 'bullet', '3' => 'number', '4' => 'normal'), json_decode(json_encode($back['edit']['kinds']), true), 'read back as asked');
	pg_assert_contains('<h2 data-p="0">Giriş bölümü</h2>', $back['html'], 'the new text under the added heading');
	pg_assert_contains('<ul class="office-list"><li class="office-lvl-0" data-p="1">Elma</li><li class="office-lvl-0" data-p="2">Armut</li></ul><ol class="office-list">', $back['html'], 'the two bullets one list, the numbered one another');

	$styles = office_docx_kind_test_part($path, 'word/styles.xml');
	$cut = strpos($parts['word/styles.xml'], '</w:styles>');
	pg_assert_same(substr($parts['word/styles.xml'], 0, $cut), substr($styles, 0, $cut), 'the styles kept, the heading appended');
	pg_assert_contains('<w:style w:type="paragraph" w:styleId="Heading2Pg2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>', $styles, 'a heading style whose id is free');

	$document = office_docx_kind_test_part($path, 'word/document.xml');
	pg_assert_contains('<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Elma</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Armut</w:t></w:r></w:p>', $document, 'the second bullet joins the first');
	pg_assert_contains('<w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Birinci</w:t>', $document, 'the numbered list on a numbering of its own');
	pg_assert_contains('<w:p><w:r><w:t>Son</w:t></w:r></w:p>', $document, 'an untouched paragraph byte for byte');

	$numbering = office_docx_kind_test_part($path, 'word/numbering.xml');
	pg_assert_contains('<w:abstractNum w:abstractNumId="0">', $numbering, 'a bulleted numbering added');
	pg_assert_contains('<w:abstractNum w:abstractNumId="1">', $numbering, 'and a numbered one');
	pg_assert_true(strpos($numbering, '<w:abstractNum w:abstractNumId="1">') < strpos($numbering, '<w:num '), 'every abstract numbering before the nums');
	pg_assert_contains('Target="numbering.xml"', office_docx_kind_test_part($path, 'word/_rels/document.xml.rels'), 'the numbering named in the relationships');
	pg_assert_contains('<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/></Types>', office_docx_kind_test_part($path, '[Content_Types].xml'), 'and in the content types');
	pg_assert_same($parts['word/settings.xml'], office_docx_kind_test_part($path, 'word/settings.xml'), 'another part untouched');

	office_docx_kind_test_done($path);
}

function test_office_docx_kind_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$w = office_docx_kind_test_w();
	$document = office_docx_kind_test_body(array('<w:p><w:r><w:t>Metin</w:t></w:r></w:p>'));

	// The styles where the relationships do not say.
	$path = office_docx_kind_test_file(array(
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => $document,
	), '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="other/styles.xml"/>');
	$read = pg_office_read($path, 'a.docx', true);
	$before = file_get_contents($path);
	$refused = pg_office_docx_save($path, array(array('p' => 0, 'kind' => 'heading1')), $read['version'], sys_get_temp_dir());
	pg_assert_same('kind_unusual', $refused['code'], 'styles read from another part than the relationships name');
	pg_assert_same($before, file_get_contents($path), 'the refused file left as it was');
	office_docx_kind_test_done($path);

	// A numbering whose elements carry no prefix: an added one could not
	// be written in its namespace.
	$path = office_docx_kind_test_file(array(
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"></w:styles>',
		'word/numbering.xml' => '<?xml version="1.0"?><numbering xmlns="' . $w . '"/>',
		'word/document.xml' => $document,
	));
	$read = pg_office_read($path, 'b.docx', true);
	$refused = pg_office_docx_save($path, array(array('p' => 0, 'kind' => 'bullet')), $read['version'], sys_get_temp_dir());
	pg_assert_same('kind_unusual', $refused['code'], 'a numbering written without a prefix');
	pg_assert_true($refused['error'] !== '', 'with its reason');

	// The same file takes a text change: only kinds need the numbering.
	$saved = pg_office_docx_save($path, array(array('p' => 0, 'text' => 'Yeni')), $read['version'], sys_get_temp_dir());
	pg_assert_true($saved['ok'], 'a text change still saved: ' . $saved['error']);
	office_docx_kind_test_done($path);
}

function test_office_docx_kind_paragraph_properties_order()
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<w:document xmlns:w="' . office_docx_kind_test_w() . '"><w:body><w:p><w:pPr><w:keepNext/><w:spacing w:after="0"/><w:rPr><w:b/></w:rPr></w:pPr></w:p></w:body></w:document>');
	$properties = $document->getElementsByTagNameNS(office_docx_kind_test_w(), 'pPr')->item(0);

	pg_office_docx_ppr_put($properties, $document->createElementNS(office_docx_kind_test_w(), 'w:numPr'));
	pg_office_docx_ppr_put($properties, $document->createElementNS(office_docx_kind_test_w(), 'w:pStyle'));

	$names = array();

	foreach ($properties->childNodes as $child) {
		$names[] = $child->localName;
	}

	pg_assert_same(array('pStyle', 'keepNext', 'numPr', 'spacing', 'rPr'), $names, 'each property in the order the format requires');
}
