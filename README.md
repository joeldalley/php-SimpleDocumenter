SimpleDocumenter
================

SimpleDocumenter is a zero-dependency, single-file phpdoc comment analyzer.

*Add SimpleDocumenter To Your Project:*<br/>
```
perl -MLWP::Simple -e 'getprint "https://raw.githubusercontent.com/joeldalley/php-SimpleDocumenter/master/SimpleDocumenter.php"'
```

*Usage*
```php
require 'SimpleDocumenter.php';
$simple = new SimpleDocumenter('SimpleDocumenter');

print_r($simple->classTag('@author'));
// Array
// ( 
//     [0] => Joel Dalley 
// )

echo $simple->classTag('@author', $first = TRUE);
// Joel Dalley
```

Why SimpleDocumenter?
=====================

SimpleDocumenter provides only the part of phpDocumentor that I always found useful: the actual parsed phpdoc tags, so they can be used to make nice-looking API documentation Web pages. SimpleDocumenter is a single file, and has no dependencies.


Copyright & License
===================

php-SimpleDocumenter is copyright &copy; Joel Dalley 2015.<br/>
php-SimpleDocumenter is distributed under the same license as Perl.
