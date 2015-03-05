<?php
/**
 * Use sample classes to create html output. 
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

error_reporting(E_ALL);
require_once 'SimpleDocumenter.php';

$config = array(
    'Test' => 'test-classes/Test.class.php',
    'SimpleDocumenter' => 'SimpleDocumenter.php'
    );

foreach ($config as $class => $file) {
    require_once $file;
    $classes = get_php_classes(file_get_contents($file));
    foreach ($classes as $class) {
        $name = str_replace('/', '-', $file);
        $outfile = "html-output/$name-$class.html";
        file_put_contents($outfile, reflect($class));
        echo "Wrote file $outfile\n";
    }
}

function reflect($class) {
    $simple = new SimpleDocumenter($class);

    $note = $simple->classTags($class, '@note');
    $author = $simple->classTags($class, '@author');
    $version = $simple->classTags($class, '@version', TRUE);
    $constants = $simple->constantTags();
    $propNames = $simple->propertyNames();
    $methodNames = $simple->methodNames();

    return template('page', array(
        '{title}'        => "Class {class} Documentation",
        '{class}'        => $class,
        '{show-version}' => show("$version"),
        '{version}'      => "$version",
        '{show-author}'  => show($author),
        '{author}'       => implode(', ', $author),
        '{show-note}'    => show($note),
        '{note}'         => implode(', ', $note),

        '{show-props}' => show($propNames),
        '{props}'      => implode('', array_map(function($_) use ($simple) {
            $var = $simple->propertyTags($_, '@var', TRUE);
            $exmpls = $simple->propertyTags($_, '@example');
            return template('property', array(
                '{show-note}'    => show($var->note()),
                '{note}'         => $var->note(),
                '{show-type}'    => show($var->type()),
                '{type}'         => $var->type(),
                '{name}'         => $var->name(),
                '{show-tagsets}' => show($exmpls),
                '{show-example}' => show($exmpls),
                '{example}'      => implode('', array_map(function($_) {
                    return template('example', array(
                        '{note}' => htmlentities("$_")
                    ));
                }, $exmpls)),
            ));
         }, $propNames)),

        '{show-constants}' => show($constants),
        '{constants}'      => implode('', array_map(function($_) {
            return template('constant', array(
                '{name}'  => $_->name(),
                '{value}' => $_->value()
            ));
        }, $constants)),

        '{show-methods}' => show($methodNames),
        '{methods}'      => implode('', array_map(function($name) use ($simple) {
            $note = $simple->methodTags($name, '@note', TRUE);
            $exmpls = $simple->methodTags($name, '@example');
            $params = $simple->methodTags($name, '@param');
            $throws = $simple->methodTags($name, '@throws');
            $return = $simple->methodTags($name, '@return', TRUE);
            $haveTags = count($params) || count($throws) || count($exmpls);
            return template('method', array(
                '{name}'         => $name,
                '{show-note}'    => show("$note"),
                '{note}'         => "$note",
                '{show-return}'  => show("$return"),
                '{type}'         => $return->type(),
                '{return-note}'  => $return->note(),
                '{show-tagsets}' => show($haveTags),
                '{show-params}'  => show($params),
                '{params}'       => implode('', array_map(function($_) {
                    return template('param', array(
                        '{show-name}' => show($_->name()),
                        '{name}'      => $_->name(),
                        '{show-type}' => show($_->type()),
                        '{type}'      => $_->type(),
                        '{note}'      => htmlentities($_->note())
                    ));
                }, $params)),

                '{show-example}' => show($exmpls),
                '{example}'      => implode('', array_map(function($_) {
                    return template('example', array(
                        '{note}' => htmlentities("$_")
                    ));
                }, $exmpls)),

                '{show-throws}' => show($throws),
                '{throws}'      => implode('', array_map(function($_) {
                    return template('throws', array(
                        '{type}' => $_->type(),
                        '{note}' => htmlentities($_->note())
                    ));
                }, $throws)),

                '{sig}' => implode(', ', array_map(function($_) {
                    return template('sig-param', array(
                        '{show-type}' => show($_->type()),
                        '{type}'      => $_->type(),
                        '{show-name}' => show($_->name()),
                        '{name}'      => $_->name(),
                        '{note}'      => $_->note()
                    ));
                }, $params))
            ));
        }, $methodNames)),
    ));
}

function template($name, $replace = array()) {
    $tmpl = file_get_contents("templates/$name.html");
    foreach ($replace as $placeholder => $value) {
        $tmpl = str_replace($placeholder, $value, $tmpl);
    }
    return trim($tmpl);
}

function show($val) { return empty($val) ? 'none' : ''; }

function get_php_classes($text) {
    $tokens = token_get_all($text);
    $count = count($tokens);
    $classes = array();
    for ($i = 2; $i < $count; $i++) {
        if ($tokens[$i-2][0] == T_CLASS &&
            $tokens[$i  ][0] == T_STRING &&
            $tokens[$i-1][0] == T_WHITESPACE) {
            $class_name = $tokens[$i][1];
            $classes[] = $class_name;
        }
    }
    return $classes;
}
