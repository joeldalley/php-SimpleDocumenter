<?php
/**
 * Use sample classes to create html output. 
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

require_once 'SimpleDocumenter.php';

$classes = array(
    'Test' => 'test-classes/Test.class.php'
    );

foreach ($classes as $class => $file) { 
    require_once $file;
    $file = str_replace('\\', '-', basename($file)) . '.html';
    file_put_contents("html-output/$file", reflect($class));
    echo "Wrote file output/$file\n";
}

function reflect($class) {
    $simple = new SimpleDocumenter($class);
    $methodNames = $simple->methodNames();
    $propNames = $simple->propertyNames();
    $constants = $simple->getConstants();

    return template('page', array(
        '{title}'              => "Class {class} Documentation",
        '{class}'              => $class,
        '{display-constants}'  => count($constants) ? '' : 'none',
        '{display-methods}'    => count($methodNames) ? '' : 'none',
        '{display-props}'      => count($propNames) ? '' : 'none',
        '{props}' => implode('', array_map(function($_) use ($simple, $propNames) {
                         $var = $simple->propertyVar($_);
                         $ex = $simple->propertyExamples($_);
                         return template('property', array(
                             '{display-note}'    => $note ? '' : 'none',
                             '{display-note}'    => strlen($var[2]) ? '' : 'none',
                             '{display-example}' => count($ex) ? '' : 'none',
                             '{display-tagsets}' => count($ex) ? '' : 'none',
                             '{type}' => (string) $var[0],
                             '{name}' => (string) $var[1],
                             '{note}' => $var[2],
                             '{example}' => implode('', array_map(function($_) {
                                               $tmpl = template('example', array(
                                                   '{note}' => htmlentities($_)
                                               ));
                                               return trim($tmpl);
                                           }, $ex)),
                         ));
                     }, $propNames)),
        '{constants}' => implode('', array_map(function($_) use ($constants) {
                             return template('constant', array(
                                 '{name}'  => $_,
                                 '{value}' => $constants[$_]
                             ));
                         }, array_keys($constants))),
        '{methods}' => implode('', array_map(function($name) use ($simple) {
                           $note = $simple->methodNote($name);
                           $params = $simple->methodParams($name);
                           $ex = $simple->methodExamples($name);
                           $throws = $simple->methodThrows($name);
                           $haveTags = count($params) || count($throws) || count($ex);
                           $return = $simple->methodReturn($name);
                           return template('method', array(
                               '{type}'            => (string) $return[0],
                               '{name}'            => $name,
                               '{methodNote}'      => $note,
                               '{display-note}'    => strlen($note) ? '' : 'none',
                               '{display-params}'  => count($params) ? '' : 'none',
                               '{display-return}'  => count($return) ? '' : 'none',
                               '{display-throws}'  => count($throws) ? '' : 'none',
                               '{display-example}' => count($ex) ? '' : 'none',
                               '{display-tagsets}' => $haveTags ? '' : 'none',
                               '{returnNote}'      => (string) $return[1],
                               '{params}' => implode('', array_map(function($_) {
                                                 list($type, $name, $note) = $_;
                                                 $note = htmlentities((string) $note);
                                                 $tmpl = template('param', array(
                                                     '{type}' => (string) $type,
                                                     '{name}' => (string) $name,
                                                     '{note}' => $note,
                                                     '{display-param-name}' =>
                                                         (bool) $name ? '' : 'none',
                                                     '{display-param-type}' =>
                                                         (bool) $type ? '' : 'none'
                                                 ));
                                                 return trim($tmpl);
                                             }, $params)),
                               '{example}' => implode('', array_map(function($_) {
                                                 $tmpl = template('example', array(
                                                     '{note}' => htmlentities($_)
                                                 ));
                                                 return trim($tmpl);
                                             }, $ex)),
                               '{throws}' => implode('', array_map(function($_) {
                                                 list($type, $note) = $_;
                                                 $note = htmlentities((string) $note);
                                                 $tmpl = template('throws', array(
                                                     '{type}' => (string) $type,
                                                     '{note}' => $note,
                                                 ));
                                                 return trim($tmpl);
                                             }, $throws)),
                               '{sig}' => implode(', ', array_map(function($_) {
                                              list($type, $name, $note) = $_;
                                              $note = htmlentities((string) $note);
                                              $tmpl = template('sig-param', array(
                                                  '{type}' => $type,
                                                  '{name}' => $name ? " $name" : '',
                                                  '{note}' => $note,
                                                  '{display-param-type}' =>
                                                      (bool) $type ? '' : 'none',
                                                  '{display-param-name}' =>
                                                      (bool) $name ? '' : 'none'
                                              ));
                                              return trim($tmpl);
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
    return $tmpl;
}
