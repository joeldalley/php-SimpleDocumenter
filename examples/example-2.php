<?php
// Show only methods with no @return tag, or which specify return type 'void'.
require '../SimpleDocumenter.php';
$documenter = new SimpleDocumenter('SimpleDocumenterTag');

$voidOrUndefined = function($node) {
    $return = $node->tagList('@return')->first();
    return !$return || $return->type == 'void';
};
foreach ($documenter->methodNodes($voidOrUndefined) as $name => $node) {
    print "{$name}() either returns void or has no @return doc comment tag.\n";
}

// Outputs:
// __set() either returns void or has no @return doc comment tag.
// analyzeText() either returns void or has no @return doc comment tag.
