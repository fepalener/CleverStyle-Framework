--FILE--
<?php
namespace cs\Page;
include __DIR__.'/../../unit.php';
echo Includes_processing::js(file_get_contents(__DIR__.'/script.js'));
?>
--EXPECT--
var bar = 'bar'; /* another comment */var foo = 'foo'; // Single-line after code
(function (bar, foo) {return foo + bar +(10 * 15 / 5);})(bar, foo);if ( !( bar > foo ) ){console . log (foo), console.log(bar
);}var script_code = "<script>JS here<\/script>";
