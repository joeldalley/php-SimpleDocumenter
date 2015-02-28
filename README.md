Why php-SimpleDocumenter?
=========================

SimpleDocumenter is an extremely small, minimal and intentionally simple-minded phpdoc comment analyzer.

Example
=======

```php
require 'SimpleDocumenter.php';
$simple = new SimpleDocumenter('SimpleDocumenter');

$tmpl = "REFLECTING ON CLASS: %s\n%s\n\n";
echo sprintf($tmpl, $simple->className(), $simple->classComment());

foreach ($simple->methodNames() as $name) {
    $comment = $simple->methodComment($name);
    echo $comment ? "$comment\n" : '';
    echo $simple->methodSignature($name), "\n\n";
}
```

Copyright & License
===================

php-SimpleDocumenter is copyright &copy; Joel Dalley 2015.<br/>
php-SimpleDocumenter is distributed under the same license as Perl.
