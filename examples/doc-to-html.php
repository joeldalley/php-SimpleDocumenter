<?php
/**
 * Use sample classes to create html output. 
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

require_once 'common.inc.php';
require_once 'SimpleDocumenter.php';

foreach (classes() as $class => $file) { 
    require_once $file;
    $file = str_replace('\\', '-', basename($file)) . '.html';
    file_put_contents("output/$file", reflect($class));
    echo "Wrote file output/$file\n";
}

function reflect($class) {
    $simple = new SimpleDocumenter($class);

    return template('page', array(
        '{title}'   => "Class {class} Documentation",
        '{class}'   => $class,
        '{methods}' => implode('', array_map(function($name) use ($simple) {
                           $note = $simple->methodNote($name, $sep = '<br/>');
                           $params = $simple->methodParams($name);
                           $ex = $simple->methodExample($name);
                           $throws = $simple->methodThrows($name);
                           $haveTags = count($params) || count($throws) || count($ex);
                           $return = $simple->methodReturn($name);
                           return template('method', array(
                               '{type}'            => (string) $return[0],
                               '{name}'            => $name,
                               '{methodNote}'      => $note,
                               '{display-note}'    => $note ? '' : 'none',
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
                                                 ));
                                                 return trim($tmpl);
                                             }, $params)),
                               '{example}' => implode('', array_map(function($_) {
                                                 $note = htmlentities((string) $_[0]);
                                                 $tmpl = template('example', array(
                                                     '{note}' => $note
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
                                                  '{note}' => $note
                                              ));
                                              return trim($tmpl);
                                          }, $params))
                           ));
                       }, $simple->methodNames())),
    ));
}

function template($name, $replace = array()) {
    $tmpl = file_get_contents("templates/$name.html");
    foreach ($replace as $placeholder => $value) {
        $tmpl = str_replace($placeholder, $value, $tmpl);
    }
    return $tmpl;
}
