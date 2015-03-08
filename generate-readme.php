<?php
/**
 * Generate README.md.
 * @author Joel Dalley
 * @version 2015/Mar/08
 */

// $config is defined in doc-to-html.php.
require 'doc-to-html.php';

$SD = 'SimpleDocumenter';

$repl = array(
    '{simple-documenter-html-examples}' => NULL,
    '{vendor-html-examples}'            => NULL,
    '{example-1}'                       => example(1),
    '{example-2}'                       => example(2),
    );

$examples = array(
    $SD              => array(),
    'Composer'       => array(),
    'phpDocumentor2' => array(),
    'CodeIgniter'    => array(),
    );

$local = "/^($SD|Test)/";
$vendor = '/^(Composer|phpDocumentor2|CodeIgniter)/';

foreach ($config as $file => $documentedClasses) {
    foreach ($documentedClasses as $class) {
        preg_match($local, basename($file), $match) and $key = $SD;
        preg_match($vendor, basename($file), $match) and $key = $match[1];
        $examples[$key][] = mdlink($class, $file);
    }
}

$repl['{simple-documenter-html-examples}'] = implode('<br/>', $examples[$SD]);
unset($examples[$SD]);

$vendorBlocks = array();
foreach ($examples as $vendor => $links) {
    $vendorBlocks[] = "<i>$vendor</i>\n" . implode('<br/>', $links);
}
$repl['{vendor-html-examples}'] = implode("\n\n", $vendorBlocks);

$tmpl = file_get_contents('templates/README.md.tmpl');
foreach ($repl as $placeholder => $value) {
    $tmpl = str_replace($placeholder, $value, $tmpl);
}
file_put_contents('README.md', trim($tmpl));


////////////////////////////////////////////////////////////////


function example($num) {
    return trim(file_get_contents("examples/example-$num.php"));
}

function mdlink($class, $file) {
    global $SD; return "[$class](https://joeldalley.github.io/php-$SD/$file)";
}
