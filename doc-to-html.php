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

function reflect($className) {
    $documenter = new SimpleDocumenter($className);
    $classNode = $documenter->classNode();
    $constantNodes = $documenter->constantNodes();
    $propertyNodes = $documenter->propertyNodes();
    $methodNodes = $documenter->methodNodes();

    $constHtml = '';
    foreach ($constantNodes as $name => $node) {
        $const = $node->tagList('@const')->first();
        $constHtml .= template('constant', array(
            '{name}'  => $const ? $const->name : '',
            '{value}' => $const ? $const->value : ''
        ));
    }

    $propsHtml = '';
    foreach ($propertyNodes as $name => $node) {
        $var = $node->tagList('@var')->first();
        $refl = $node->reflector();
 
        // Skip class properties that don't have a @var tag.
        if ($var) {
            $exmplHtml = '';
            foreach ($node->tagList('@example') as $exmpl) {
                $exmplHtml .= template('example', array(
                    '{note}' => htmlentities("$exmpl")
                ));
            }

            // A little help here for a @var tag that didn't
            // name the variable; the node's ReflectionProperty
            // object has the property's name, and we prepend "$".
            $var->name or $var->name = "\${$refl->name}";

            $propsHtml .= template('property', array(
                '{icon}'         => icon($refl),
                '{show-note}'    => show($var->note),
                '{note}'         => $var->note,
                '{show-type}'    => show($var->type),
                '{type}'         => $var->type,
                '{name}'         => $var->name,
                '{show-tagsets}' => show($exmplHtml),
                '{show-example}' => show($exmplHtml),
                '{example}'      => $exmplHtml
            ));
        }
     }

    $methodsHtml = '';
    foreach ($methodNodes as $name => $node) {
        $sigHtml = '';
        foreach ($node->tagList('@param') as $param) {
            $sigHtml .= ($sigHtml ? ', ' : '');
            $sigHtml .= template('sig-param', array(
                '{show-type}' => show($param->type),
                '{type}'      => $param->type,
                '{show-name}' => show($param->name),
                '{name}'      => $param->name,
                '{note}'      => $param->note
            ));
        }
 
        $throwsHtml = '';
        foreach ($node->tagList('@throws') as $throws) {
            $throwsHtml .= template('throws', array(
                '{type}' => $throws->type,
                '{note}' => htmlentities($throws->note)
            ));
        }

        $exmplHtml = '';
        foreach ($node->tagList('@example') as $exmpl) {
            $exmplHtml .= template('example', array(
                '{note}' => htmlentities("$exmpl")
            ));
        }

        $paramHtml = '';
        foreach ($node->tagList('@param') as $param) {
            $paramHtml .= template('param', array(
                '{show-name}' => show($param->name),
                '{name}'      => $param->name,
                '{show-type}' => show($param->type),
                '{type}'      => $param->type,
                '{note}'      => htmlentities($param->note)
            ));
        }

        $note = $node->tagList('@note')->first();
        $return = $node->tagList('@return')->first();
        $methodsHtml .= template('method', array(
            '{icon}'         => icon($node->reflector()),
            '{name}'         => $name,
            '{show-note}'    => show($note && "$note"),
            '{note}'         => $note ? "$note" : '',
            '{show-return}'  => show($return && "$return"),
            '{type}'         => $return ? $return->type : '',
            '{return-note}'  => $return ? $return->note : '',
            '{show-tagsets}' => show($throwsHtml || $exmplHtml || $paramHtml),
            '{show-params}'  => show($paramHtml),
            '{params}'       => $paramHtml,
            '{show-example}' => show($exmplHtml),
            '{example}'      => $exmplHtml,
            '{show-throws}'  => show($throwsHtml),
            '{throws}'       => $throwsHtml,
            '{sig}'          => $sigHtml
        ));
    }

    $note = $classNode->tagList('@note')->first();
    $version = $classNode->tagList('@version')->first();
    $authors = $classNode->tagList('@author')->join(', ');
    $show = ($note && "$note") || ($version && "$version") || $authors;
    return template('page', array(
        '{title}'           => "Class {class} Documentation",
        '{class}'           => $className,
        '{show-class-tags}' => show($show),
        '{show-version}'    => show($version && "$version"),
        '{version}'         => $version ? "$version" : '',
        '{show-author}'     => show($authors),
        '{author}'          => $authors,
        '{show-note}'       => show($note && "$note"),
        '{note}'            => $note ? "$note" : '',
        '{show-props}'      => show($propsHtml),
        '{props}'           => $propsHtml,
        '{show-constants}'  => show($constHtml),
        '{constants}'       => $constHtml,
        '{show-methods}'    => show($methodsHtml),
        '{methods}'         => $methodsHtml
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

function icon($refl) { return $refl->isPublic() ? 'open' : 'closed'; }

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
