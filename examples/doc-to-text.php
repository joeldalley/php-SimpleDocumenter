<?php
/**
 * Pull together some different classes, and run them through SimpleDocumenter.
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

set_path();
require_once 'SimpleDocumenter.php';

// Map of (class name => filename).
$classes = array(
    'phpDocumentor\Configuration' => 'Configuration.php',
    'phpDocumentor\Bootstrap' => 'Bootstrap.php',
    'SimpleDocumenter' => 'SimpleDocumenter.php',
    'Foo' => 'Foo.class.php',
    );

// Write example output to files.
foreach ($classes as $class => $file) { 
    require_once $file;
    $file = str_replace('\\', '-', $file) . '.txt';
    file_put_contents("output/$file", reflect($class));
    echo "Wrote file output/$file\n";
}

function reflect($class) {
    $simple = new SimpleDocumenter($class);

    $name = $simple->className();
    $text = "Reflecting on class `$name`\n";

    $comment = $simple->classComment();
    if ($comment) { $text .= "$comment\n"; }

    foreach ($simple->methodNames() as $name) {
        $comment = $simple->methodComment($name);
        if ($comment) { $text .= "$comment\n"; }

        $sig = $simple->methodSignature($name);
        $text .= "$sig\n\n";
    }

    return $text;
}

function set_path() {
    // My installation of phpDocumentor is located here:
    $docdir = '../../../Scratch/vendor/phpdocumentor/'
            . 'phpdocumentor/src/phpDocumentor';

    // Some example files I wrote are located here:
    $exdir = '../../phpdoc-examples/src';

    // SimpleDocumenter itself is here:
    $srcdir = '../';

    $path = join(':', array($srcdir, $exdir, $docdir));
    set_include_path(get_include_path() . $path);
}
