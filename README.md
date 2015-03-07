SimpleDocumenter
================

SimpleDocumenter is a zero-dependency phpdoc comment analyzer, packaged as a single file.

Examples
========

SimpleDocumenter + an HTML procedure produced the following API documentation Web pages:

[Class: SimpleDocumenter](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenter.html)<br/>
[Class: SimpleDocumenterNode](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterNode.html)<br/>
[Class: SimpleDocumenterTagList](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTagList.html)<br/>
[Class: SimpleDocumenterTag](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTag.html)<br/>
[Class: SimpleDocumenterUtil](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterUtil.html)

See [doc-to-html.php](https://github.com/joeldalley/php-SimpleDocumenter/blob/master/doc-to-html.php)

Add SimpleDocumenter To Your Project
====================================
```
perl -MLWP::Simple -e 'getprint "https://raw.githubusercontent.com/joeldalley/php-SimpleDocumenter/master/SimpleDocumenter.php"'
```

Example Script
==============
```php
require 'SimpleDocumenter.php';
$documenter = new SimpleDocumenter('SimpleDocumenter');

$filter = function($node) { return $node->reflector()->isPublic(); };
$nodes = $documenter->methodNodes($filter);

// Loop on public methods.
foreach ($nodes as $name => $node) {
    // $return is NULL if the tag list is empty, otherwise 
    // it's the first SimpleDocumenterTag in the tag list.
    $return = $node->tagList('@return')->first(); 
    $type = $return && $return->type ? $return->type : 'Not Documented';

    $paramCount = $node->tagList('@param')->count(); // int, 0 or more.

    print "The method `$name`";
    $paramCount and print " has $paramCount parameters, and";
    print " returns type `$type`.\n";
}

// Outputs:
// The method `__construct` has 1 parameters, and returns type `SimpleDocumenter`.
// The method `classNode` returns type `SimpleDocumenterNode`.
// The method `constantNodes` has 1 parameters, and returns type `SimpleDocumenterNode[]`.
// The method `propertyNodes` has 1 parameters, and returns type `SimpleDocumenterNode[]`.
// The method `methodNodes` has 1 parameters, and returns type `SimpleDocumenterNode[]`.
```

For a more complex use case, see [doc-to-html.php](https://github.com/joeldalley/php-SimpleDocumenter/blob/master/doc-to-html.php)

Why SimpleDocumenter?
=====================

<b>Small and Simple</b>

SimpleDocumenter is a single file with no dependencies, and as of this writing is under 400 source lines of code.

<b>Callback Filtering</b>
```php
// Show only methods with no @return tag, or which specify return type 'void'.
require 'SimpleDocumenter.php';
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
```

<b>Trade-offs</b>

SimpleDocumenter only works on classes.  It can't analyze functions in the global namespace.

Copyright & License
===================

php-SimpleDocumenter is copyright &copy; Joel Dalley 2015.<br/>
php-SimpleDocumenter is distributed under the same license as Perl.
