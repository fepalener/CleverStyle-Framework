--FILE--
<?php
namespace cs;
use cs\Page\Meta;
include __DIR__.'/../../../bootstrap.php';
Request::instance()->home_page = true;
Config::instance_stub(
	[
		'core'		=> [
			'multilingual'	=> false,
			'name'			=> ''
		]
	],
	[
		'base_url'	=> 'http://cscms.travis',
		'module'	=> False_class::instance()
	]
);
$Page	= Page::instance_stub([
	'canonical_url'	=> false
]);
Text::instance_stub([], [
	'process'	=> 'Web-site'
]);
Meta::instance()
	->article()
	->article('section', 'Framework')
	->render();
echo $Page->Head;
?>
--EXPECT--
<head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# article: http://ogp.me/ns/article#">
	<meta content="article" property="og:type">
	<meta content="Framework" property="article:section">
	<meta content="http://cscms.travis" property="og:url">
	<meta content="Web-site" property="og:site_name">
</head>
