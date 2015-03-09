<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Mar/07
 */

require '../SimpleDocumenter.php';
$documenter = new SimpleDocumenter('SimpleDocumenter');

// Only the public methods.
$filter = function($node) { return $node->reflector()->isPublic(); };
$nodes = $documenter->methodNodes($filter);

foreach ($nodes as $name => $node) {
    // $return is NULL if the tag list is empty, otherwise 
    // it's the first SimpleDocumenterTag in the tag list.
    $return = $node->tagList('@return')->first(); 
    $type = $return && $return->type ? $return->type : 'Not Documented';

    $count = $node->tagList('@param')->count(); // int, 0 or more.

    print "The method, $name,";
    $count and print " has $count parameters, and";
    print " returns type $type.\n";
}
