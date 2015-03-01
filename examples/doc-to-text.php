<?php
/**
 * Use sample classes to create text output. 
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

require_once 'common.inc.php';
require_once 'SimpleDocumenter.php';

foreach (classes() as $class => $file) { 
    require_once $file;
    $file = str_replace('\\', '-', basename($file)) . '.txt';
    file_put_contents("output/$file", reflect($class));
    echo "Wrote file output/$file\n";
}

function reflect($class) {
    $simple = new SimpleDocumenter($class);

    $name = $simple->className();
    $text = "Reflecting on class `$name`\n";

    $comment = $simple->classComment(TRUE);
    if ($comment) { $text .= "$comment\n\n"; }

    foreach ($simple->methodNames() as $name) {
        $comment = $simple->methodComment($name, TRUE);
        if ($comment) { $text .= "$comment\n"; }

        $sig = $simple->methodSignature($name);
        $text .= "$sig\n\n";
    }

    return $text;
}
