SimpleDocumenter
================

SimpleDocumenter is a zero-dependency phpdoc comment analyzer, packaged as a single file.

Generated API Documentation
===========================

SimpleDocumenter + an HTML procedure produced the following API documentation Web pages:

<b>SimpleDocumenter Classes</b>

[SimpleDocumenter](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenter.html)<br/>[SimpleDocumenterNode](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterNode.html)<br/>[SimpleDocumenterTagList](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTagList.html)<br/>[SimpleDocumenterTag](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterTag.html)<br/>[SimpleDocumenterUtil](https://joeldalley.github.io/php-SimpleDocumenter/html-output/SimpleDocumenter.php-SimpleDocumenterUtil.html)<br/>[Test](https://joeldalley.github.io/php-SimpleDocumenter/html-output/test-classes-Test.class.php-Test.html)

<b>Vendor Classes</b>

<i>Composer</i><br/>[\Composer\Composer](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-Composer.php-Composer.html)<br/>[\Composer\Compiler](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-Compiler.php-Compiler.html)<br/>[\Composer\Json\JsonFile](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-Json-JsonFile.php-JsonFile.html)<br/>[\Composer\Autoload\ClassLoader](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-Autoload-ClassLoader.php-ClassLoader.html)<br/>[\Composer\Autoload\ClassMapGenerator](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-Autoload-ClassMapGenerator.php-ClassMapGenerator.html)<br/>[\Composer\EventDispatcher\EventDispatcher](https://joeldalley.github.io/php-SimpleDocumenter/html-output/Composer-src-Composer-EventDispatcher-EventDispatcher.php-EventDispatcher.html)

<i>phpDocumentor2</i><br/>[\phpDocumentor\Bootstrap](https://joeldalley.github.io/php-SimpleDocumenter/html-output/phpDocumentor2-src-phpDocumentor-Bootstrap.php-Bootstrap.html)<br/>[\phpDocumentor\Compiler\Compiler](https://joeldalley.github.io/php-SimpleDocumenter/html-output/phpDocumentor2-src-phpDocumentor-Compiler-Compiler.php-Compiler.html)<br/>[\phpDocumentor\Transformer\Transformation](https://joeldalley.github.io/php-SimpleDocumenter/html-output/phpDocumentor2-src-phpDocumentor-Transformer-Transformation.php-Transformation.html)

<i>CodeIgniter</i><br/>[CI_Controller](https://joeldalley.github.io/php-SimpleDocumenter/html-output/CodeIgniter-system-core-Controller.php-CI_Controller.html)<br/>[CI_Model](https://joeldalley.github.io/php-SimpleDocumenter/html-output/CodeIgniter-system-core-Model.php-CI_Model.html)<br/>[CI_Router](https://joeldalley.github.io/php-SimpleDocumenter/html-output/CodeIgniter-system-core-Router.php-CI_Router.html)<br/>[CI_Loader](https://joeldalley.github.io/php-SimpleDocumenter/html-output/CodeIgniter-system-core-Loader.php-CI_Loader.html)<br/>[CI_Input](https://joeldalley.github.io/php-SimpleDocumenter/html-output/CodeIgniter-system-core-Input.php-CI_Input.html)

See [doc-to-html.php](https://github.com/joeldalley/php-SimpleDocumenter/blob/master/doc-to-html.php)

Add SimpleDocumenter To Your Project
====================================
```
perl -MLWP::Simple -e 'getprint "https://raw.githubusercontent.com/joeldalley/php-SimpleDocumenter/master/SimpleDocumenter.php"'
```

Basic Usage
===========
```php
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
```

<b>Outputs:</b>
```
The method, addTag, has 1 parameters, and returns type void.
The method, __construct, has 1 parameters, and returns type SimpleDocumenter.
The method, classNode, returns type SimpleDocumenterNode.
The method, constantNodes, has 1 parameters, and returns type SimpleDocumenterNode[].
The method, propertyNodes, has 1 parameters, and returns type SimpleDocumenterNode[].
The method, methodNodes, has 1 parameters, and returns type SimpleDocumenterNode[].
```

For a more complex use case, see [doc-to-html.php](https://github.com/joeldalley/php-SimpleDocumenter/blob/master/doc-to-html.php)

Why SimpleDocumenter?
=====================

<b>Absolute Portability</b>

SimpleDocumenter is a single file with no dependencies, and is only 419 source lines of code.

As such, portability is absolute. A local copy of SimpleDocumenter can be gotten by copying & pasting 
the [source file](https://raw.githubusercontent.com/joeldalley/php-SimpleDocumenter/master/SimpleDocumenter.php).

<b>Callback Filtering</b>
```php
<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Mar/07
 */

require '../SimpleDocumenter.php';
$documenter = new SimpleDocumenter('SimpleDocumenterTag');

// Show only methods with no @return tag, or which specify return type 'void'.
$voidOrUndefined = function($node) {
    $return = $node->tagList('@return')->first();
    return !$return || $return->type == 'void';
};
foreach ($documenter->methodNodes($voidOrUndefined) as $name => $node) {
    print "{$name}() either returns void or has no @return doc comment tag.\n";
}
```

<b>Outputs:</b>
```
addAnalyzer() either returns void or has no @return doc comment tag.
__set() either returns void or has no @return doc comment tag.
analyze() either returns void or has no @return doc comment tag.
```

`SimpleDocumenterNode::from()` makes it easy to filter inheritance heirarchies:

```php
<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Mar/08
 */

// Simple inheritance heirarchry: Animal -> Mammal -> Primate.
class Animal                 { const VEGETABLE  = FALSE; public function move()  {} }
class Mammal  extends Animal { const HAS_HAIR   = TRUE;  public function shed()  {} }
class Primate extends Mammal { const HAS_THUMBS = TRUE;  public function grasp() {} }

require '../SimpleDocumenter.php';
$doc = new SimpleDocumenter('Primate');

// Only the nodes whose constants are defined in--or methods 
// and properties are declared in--the child class, 'Primate'.
$primate = function($node) { return $node->from() == 'Primate'; };

$pairs = array(
    'Primate defines the following constants' => $doc->constantNodes($primate),
    'All constants available in Primate'      => $doc->constantNodes(),
    'Primate declares the following methods'  => $doc->methodNodes($primate),
    'All methods available in Primate'        => $doc->methodNodes()
    );

foreach ($pairs as $phrase => $nodes) {
    print "$phrase: " . implode(', ', array_keys($nodes)) . "\n";
}
```

<b>Outputs:</b>
```
Primate defines the following constants: HAS_THUMBS
All constants available in Primate: HAS_THUMBS, HAS_HAIR, VEGETABLE
Primate declares the following methods: grasp
All methods available in Primate: grasp, shed, move
```

<b>Extensibility</b>

Easily add support for tags that aren't in SimpleDocumenter's default tag list, using `addTag()`.<br/>
And just as easily, you can add your own text analyzer functions to precisely direct how any given tag's text gets parsed. 
(That said, to extend the SimpleDocumenter core, you will have to know all the details about a SimpleDocumenterTag object.)<br/>

```php
<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Apr/21
 */

require '../SimpleDocumenter.php';

// A silly example, it but gets the point across: You can invent your own
// phpdoc tags (or just add ones that aren't supported by default), and you
// can create and register arbitrary tag comment analyzer functions for them.
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
```

<b>Outputs:</b>
```
Foo::bar() has this phpdoc note: `A simple tag comment, with only a note after the tag.`
```

<b>Trade-offs</b>

SimpleDocumenter only works on classes. It can't analyze functions in the global namespace.

Copyright & License
===================

php-SimpleDocumenter is copyright &copy; Joel Dalley 2015.<br/>
php-SimpleDocumenter is distributed under the same license as Perl.