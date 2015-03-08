<?php
/**
 * Use sample classes to create html output. 
 * @author Joel Dalley
 * @version 2015/Feb/28
 */
error_reporting(E_ALL);

$CI_DIR = '../CodeIgniter';
$PHPDOC_DIR = '../phpDocumentor2/src';
$COMPOSER_DIR = '../Composer/src/Composer';

// Pairs of (File => Namespace|NULL) to generate docs for.
$config = array(
    'SimpleDocumenter.php'        => NULL,
    'test-classes/Test.class.php' => NULL,
    );

// Generate some vendor class documentation, if the *_DIR paths are found.
if (file_exists($COMPOSER_DIR)) {
    set_include_path(get_include_path() . ":$COMPOSER_DIR");
    $config["$COMPOSER_DIR/Composer.php"]                        = '\Composer';
    $config["$COMPOSER_DIR/Compiler.php"]                        = '\Composer';
    $config["$COMPOSER_DIR/Json/JsonFile.php"]                   = '\Composer\Json';
    $config["$COMPOSER_DIR/Autoload/ClassLoader.php"]            = '\Composer\Autoload';
    $config["$COMPOSER_DIR/Autoload/ClassMapGenerator.php"]      = '\Composer\Autoload';
    $config["$COMPOSER_DIR/EventDispatcher/EventDispatcher.php"] = '\Composer\EventDispatcher';
}
if (file_exists($PHPDOC_DIR)) {
    set_include_path(get_include_path() . ":$PHPDOC_DIR");
    $config["$PHPDOC_DIR/phpDocumentor/Bootstrap.php"] = '\phpDocumentor';
    $config["$PHPDOC_DIR/phpDocumentor/Compiler/Compiler.php"] = '\phpDocumentor\Compiler';
    $config["$PHPDOC_DIR/phpDocumentor/Transformer/Transformation.php"] 
        = '\phpDocumentor\Transformer';
}
if (file_exists($CI_DIR)) {
    define('BASEPATH', TRUE);
    set_include_path(get_include_path() . ":$CI_DIR");
    $config["$CI_DIR/system/core/Controller.php"] = NULL;
    $config["$CI_DIR/system/core/Model.php"] = NULL;
    $config["$CI_DIR/system/core/Router.php"] = NULL;
    $config["$CI_DIR/system/core/Loader.php"] = NULL;
    $config["$CI_DIR/system/core/Input.php"] = NULL;
}

// For each config entry, require its php file, extract classes from the file,
// parse the php doc comments from each class, and write API docs to an HTML file.
foreach ($config as $file => $namespace) {
    require_once $file;

    foreach (classes(file_get_contents($file)) as $class) {
        $name = str_replace(array('../', '/'), array('', '-'), $file);
        $outfile = "html-output/$name-$class.html";
        echo "Writing file $outfile\n";

        $fullClass = $namespace ? "$namespace\\$class" : $class;

        file_put_contents($outfile, document($fullClass));
        isset($config[$outfile]) or $config[$outfile] = array();
        $config[$outfile][] = $fullClass;
        unset($config[$file]);
    }
}

// Takes a class name and returns a Web page HTML string.
function document($className) {
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
                    '{note}' => br(htmlentities("$exmpl"))
                ));
            }

            // A little help here for a @var tag that didn't
            // name the variable; the node's ReflectionProperty
            // object has the property's name, and we prepend "$".
            $var->name or $var->name = "\${$refl->name}";

            $propsHtml .= template('property', array(
                '{icon}'         => icon($refl),
                '{show-note}'    => show($var->note),
                '{note}'         => br(htmlentities($var->note)),
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
            ));
        }
 
        $throwsHtml = '';
        foreach ($node->tagList('@throws') as $throws) {
            $throwsHtml .= template('throws', array(
                '{type}' => $throws->type,
                '{note}' => br(htmlentities($throws->note))
            ));
        }

        $exmplHtml = '';
        foreach ($node->tagList('@example') as $exmpl) {
            $exmplHtml .= template('example', array(
                '{note}' => br(htmlentities("$exmpl"))
            ));
        }

        $paramHtml = '';
        foreach ($node->tagList('@param') as $param) {
            $paramHtml .= template('param', array(
                '{show-name}' => show($param->name),
                '{name}'      => $param->name,
                '{show-type}' => show($param->type),
                '{type}'      => $param->type,
                '{note}'      => br(htmlentities($param->note))
            ));
        }

        $linkHtml = '';
        foreach ($node->tagList('@link') as $link) {
            $linkHtml .= template('link', array(
                '{anchor}' => template('anchor', array(
                    // In a more serious app, make sure $link->text a URL =)
                    '{href}'   => trim($link->text), 
                    '{text}'   => trim($link->text),
                    '{class}'  => 'link',
                    '{target}' => '_blank',
                ))
            ));
        }

        $seeHtml = '';
        foreach ($node->tagList('@see') as $see) {
            $seeHtml .= template('see', array('{see}' => "$see"));
        }

        $note = $node->tagList('@note')->first();
        $return = $node->tagList('@return')->first();
        $prefix = array();
        $prefix[] = $node->reflector()->isFinal() ? 'final' : '';
        $prefix[] = $node->reflector()->isAbstract() ? 'abstract' : '';
        $prefix[] = $node->reflector()->isStatic() ? ' static' : '';
        $prefix = implode('', $prefix);

        $methodsHtml .= template('method', array(
            '{icon}'         => icon($node->reflector()),
            '{show-prefix}'  => show($prefix),
            '{prefix}'       => $prefix,
            '{name}'         => $name,
            '{show-note}'    => show($note && "$note"),
            '{note}'         => $note ? br(htmlentities("$note")) : '',
            '{show-return}'  => show($return && "$return"),
            '{type}'         => $return ? $return->type : '',
            '{return-note}'  => $return ? $return->note : '',
            '{show-tagsets}' => show($throwsHtml || $exmplHtml 
                                  || $paramHtml || $linkHtml
                                  || $seeHtml),
            '{show-params}'  => show($paramHtml),
            '{params}'       => $paramHtml,
            '{show-example}' => show($exmplHtml),
            '{example}'      => $exmplHtml,
            '{show-throws}'  => show($throwsHtml),
            '{throws}'       => $throwsHtml,
            '{see}'          => $seeHtml,
            '{show-see}'     => show($seeHtml),
            '{link}'         => $linkHtml,
            '{show-link}'    => show($linkHtml),
            '{sig}'          => $sigHtml
        ));
    }

    $linkHtml = array();
    foreach ($classNode->tagList('@link') as $link) {
        $linkHtml[] = template('anchor', array(
            // In a more serious app, make sure $link->text a URL =)
            '{href}'   => trim($link->text), 
            '{text}'   => trim($link->text),
            '{class}'  => 'link',
            '{target}' => '_blank',
        ));
    }
    $linkHtml = implode('<br/>', $linkHtml);

    $note = $classNode->tagList('@note')->first();
    $version = $classNode->tagList('@version')->first();
    $authors = $classNode->tagList('@author')->join(', ');
    $see = $classNode->tagList('@see')->join();

    $show = ($note && "$note") 
         || ($version && "$version") 
         || $authors || $see || $linkHtml;

    return template('page', array(
        '{title}'           => "Class {class} Documentation",
        '{class}'           => $className,
        '{show-class-tags}' => show($show),
        '{show-version}'    => show($version && "$version"),
        '{version}'         => $version ? "$version" : '',
        '{show-author}'     => show($authors),
        '{author}'          => $authors,
        '{show-note}'       => show($note && "$note"),
        '{note}'            => $note ? br(htmlentities("$note")) : '',
        '{see}'             => $see,
        '{show-see}'        => show($see),
        '{link}'            => $linkHtml,
        '{show-link}'       => show($linkHtml),
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

function br($str) { return preg_replace('/[\r\n]/', '<br/>', $str); }

function show($val) { return empty($val) ? 'none' : ''; }

function icon($refl) { return $refl->isPublic() ? 'open' : 'closed'; }

function classes($text) {
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
