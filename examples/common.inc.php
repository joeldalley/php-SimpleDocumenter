<?php
/**
 * Common inlcude functions, for exmaple scripts.
 * @author Joel Dalley
 * @version 2015/Feb/28
 */

define('BASEPATH', TRUE); // For CodeIgniter.

set_path();

// Map of (class name => filename).
function classes() {
    return array(
        'CI_Controller' => 'system/core/Controller.php',
        'CI_Parser' => 'system/libraries/Parser.php',
        'CI_Calendar' => 'system/libraries/Calendar.php',
        'CI_Router' => 'system/core/Router.php',
        'CI_Output' => 'system/core/Output.php',
        'phpDocumentor\Configuration' => 'Configuration.php',
        'phpDocumentor\Bootstrap' => 'Bootstrap.php',
        'SimpleDocumenter' => 'SimpleDocumenter.php',
        'Foo' => 'Foo.class.php',
        'Composer\Autoload\ClassLoader' => 'ClassLoader.php',
        );
}

// Set include path.
function set_path() {
    $dirs = array(
        '../',
        '../../../Scratch/CodeIgniter_2.2.0',
        '../../../Scratch/vendor/phpdocumentor/phpdocumentor/src/phpDocumentor',
        '../../../Scratch/vendor/composer',
        '../../phpdoc-examples/src',
        );
    set_include_path(get_include_path() . join(':', $dirs));
}
