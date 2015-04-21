<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Apr/21
 */

require '../SimpleDocumenter.php';

// A silly example, it but gets the point across: You can invent your
// own phpdoc tags, and you can create and register arbitrary tag 
// comment analyzer functions.
class Foo {
    /** @bar A simple tag comment, with only a note after the tag. */
     function bar() {}
}

// So that '@bar' is recognized and treated as a tag:
SimpleDocumenter::addTag('@bar');

// And add a custom analyzer for '@bar' tag comments:
SimpleDocumenterTag::addAnalyzer('@bar', function(SimpleDocumenterTag $obj) {
    if (preg_match('/\s*(.+)/', $obj->text, $match)) {
        $obj->note = $match[1];
    }
});

// Parse Foo, get its 'bar' node, and print that node's '@bar' comment note.
$documenter = new SimpleDocumenter('Foo');
$nodes = $documenter->methodNodes();
$bar = $nodes['bar']->tagList('@bar')->first();
print "Foo::bar() has this phpdoc note: `{$bar->note}`\n";
