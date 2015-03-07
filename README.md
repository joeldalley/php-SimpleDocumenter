SimpleDocumenter
================

SimpleDocumenter is a zero-dependency phpdoc comment analyzer, packaged as a single file.

<b>Examples of API Documentation Web Pages:</b><br/>
[Class: SimpleDocumenter](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenter.html)
[Class: SimpleDocumenterNode](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterNode.html)
[Class: SimpleDocumenterTagList](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTagList.html)
[Class: SimpleDocumenterTag](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTag.html)

<b>Add SimpleDocumenter To Your Project:</b><br/>
```
perl -MLWP::Simple -e 'getprint "https://raw.githubusercontent.com/joeldalley/php-SimpleDocumenter/master/SimpleDocumenter.php"'
```

<b>Usage</b>
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

Why SimpleDocumenter?
=====================

SimpleDocumenter provides only the part of phpDocumentor that I always found useful: the actual parsed phpdoc tags, so they can be used to make nice-looking API documentation Web pages. SimpleDocumenter is a single file, and has no dependencies.


Copyright & License
===================

php-SimpleDocumenter is copyright &copy; Joel Dalley 2015.<br/>
php-SimpleDocumenter is distributed under the same license as Perl.
