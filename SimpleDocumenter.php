<?php
/**
 * The World's simplest phpdoc comment analyzer.
 *
 * @author Joel Dalley
 * @version 2015/Feb/28
 * @see https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenter {

    const PUBLIC_METHODS = ReflectionMethod::IS_PUBLIC;
    const PUBLIC_PROPERTIES = ReflectionProperty::IS_PUBLIC;

    /** @var array $tags Phpdoc tags. */
    private static $tags = array(
        '@package',
        '@author',
        '@version',
        '@var',
        '@see',
        '@example',
        '@param',
        '@return',
        '@throws',
        '@access',
        );

    /** @var array $tree Doc comments parse tree */
    private $tree = array(
        'properties' => array(),
        'constants'  => array(),
        'methods'    => array(),
        'class'      => array(),
        );

    /** @var string $class The class to introspect. */
    private $class = NULL;

    /** @var ReflectionClass $refl Helps in parsing. */
    private $refl = NULL;


    ////////////////////////////////////////////////////////////////
    // Interface.
    ////////////////////////////////////////////////////////////////

    /**
     * Constructor.
     *
     * @example 
     * # Echoes the php doc comment for class Foo.
     * $simple = new SimpleDocumenter('Foo');
     *
     * # Print out public method names.
     * foreach ($simple->methodNames() as $name) {
     *     print "Public method Foo::{$name}\n";
     * }
     *
     * @param string $class A class name.
     * @return SimpleDocumenter
     * @throws InvalidArgumentException If $class isn't loaded in memory.
     */
    public function __construct($class) {
        $this->class = (string) $class;

        if (!class_exists($this->class)) {
            $e = "Class `{$this->class}` doesn't exist";
            throw new InvalidArgumentException($e);
        }

        $this->refl = new ReflectionClass($this->class);
        $this->parse();
    }

    /** @return string The class being introspected. */
    public function className() { return $this->class; }

    /**
     * @param string $tag A tag name.
     * @param bool $first Optionally request only the first array entry.
     * @return array|string Either an array of entries, or only the first one.
     */
    public function classTag($tag, $first = FALSE) {
        $entry = $this->parseNode($this->tree['class'], $tag, $name);
        return $first ? $entry[0] : $entry;
    }

    /** @return array Pairs of (constant name => constant value). */
    public function getConstants() { return $this->tree['constants']; }

    /** @return string[] Property names per ReflectionClass::getProperties(). */
    public function propertyNames() { return $this->sortedNames('properties'); }

    /**
     * @param string $name A property name (without the leading '$').
     * @param string $tag A tag name.
     * @param bool $first Optionally request only the first array entry.
     * @return array|string Either an array of entries, or only the first one.
     */
    public function propertyTag($name, $tag, $first = FALSE) {
        $entry = $this->parseNode($this->node('properties', $name), $tag, $name);
        return $first ? $entry[0] : $entry;
    }

    /** @return string[] Property names per ReflectionClass::getMethods(). */
    public function methodNames() { return $this->sortedNames('methods'); }

    /**
     * @param string $name A method name.
     * @param string $tag A tag name.
     * @param bool $first Optionally request only the first array entry.
     * @return array|string Either an array of entries, or only the first one.
     */
    public function methodTag($name, $tag, $first = FALSE) {
        $entry = $this->parseNode($this->node('methods', $name), $tag, $name);
        return $first ? $entry[0] : $entry;
    }


    ////////////////////////////////////////////////////////////////
    // Parsers.
    ////////////////////////////////////////////////////////////////

    /**
     * Side-effect only, on $this->tree.
     * Parse all doc comments, and return parsed data structure.
     * @return void
     */
    private function parse() {
        $refl = $this->refl;

        $this->parseDocComment($this->tree['class'], $refl->getDocComment());

        foreach ($refl->getConstants() as $name => $value) {
            $this->tree['constants'][$name] = $value;
        }

        $config = array(
            'properties' => array(self::PUBLIC_PROPERTIES, 'getProperties'),
            'methods'    => array(self::PUBLIC_METHODS,    'getMethods')
            );

        foreach ($config as $nodeName => $entry) {
            list($filter, $method) = $entry;
            foreach ($refl->$method($filter) as $obj) {
                $this->tree[$nodeName][$obj->name] = NULL;
                $_ = &$this->tree[$nodeName][$obj->name];
                $this->parseDocComment($_, $obj->getDocComment(), $obj->name);
            }
        }
    }

    /**
     * Side-effect only, on &$node reference.
     * @param array &$node A parse tree node.
     * @param string $comment A doc comment.
     * @param string $name|NULL Optional ReflectionClass object name.
     * @return void
     */
    private function parseDocComment(&$node, $comment, $name = NULL) {
        $tags = array_merge(self::$tags, array('@note'));
        $node = array_combine($tags, array_fill(0, count($tags), array()));

        if (!$comment) { return $parsed; }

        // Remove leading "/**" and trailing "*/", and split into lines.
        $bounds = array(
            '/^[[:space:]]*\/\*\*[[:space:]]*/',
            '/^[[:space:]]*\*\/[[:space:]]*$/',
            '/^[[:space:]]*\/\*\*$/',
            '/\*\/[[:space:]]*$/'
            );
        $comment = preg_replace($bounds, '', $comment);
        $lines = preg_split('/[\r\n]/', $comment);

        // Matches a tag at the beginning of a line.
        $tagRegex = '/^(' . implode('|', self::$tags) . ')(\s|)/';

        // Parse state defaults.
        list($idx, $tag) = array(0, '@note');

        foreach ($lines as &$_) {
            // Remove leading whitespace, any leading "*" or "* ".
            $_ = preg_replace('/^[[:blank:]]*\*([[:blank:]]|)/', '', $_);
            // Remove trailing spaces.
            $_ = preg_replace('/[[:space:]]*$/', '', $_);

            // Entering a new tag?
            if (!empty($_) && preg_match($tagRegex, $_, $match)) {
                $tag = $match[1];
                $idx = count($node[$tag]);
                $_ = preg_replace($tagRegex, '', $_);
            }

            // The cursor is either positioned inside of a multi-line tag, or
            // the current line is the first line for a new occurrence of $tag.
            // Initialize or re-establish "\n" from preg_split(), accordingly.
            if (!isset($node[$tag][$idx])) { $node[$tag][$idx] = ''; }
            elseif (strlen($node[$tag][$idx])) { $_ = "\n$_"; }

            // Append to current tag.
            $node[$tag][$idx] .= $_;
        }
    }

    /**
     * @param array &$node A parse tree node.
     * @param string $tag A tag name.
     * @param string $name ReflectionClass object name.
     * @return array A list of tag entries.
     */
    private function parseNode(&$node, $tag, $name) {
        // A commentless property can be made to look 
        // as if it had a doc comment like "@var $name".
        $tag == '@var' and $this->guaranteeMinimalVar($node, $name);

        // Parse tag into (type, name, note).
        $triplet = function($text) use ($name, $tag) {
            $patterns = array(
                '/^\s*([\w\|\[\]]+)\s+(\&?\$\w+)\s?(.*)/s' => function($m) {
                    return array($m[1], $m[2], $m[3]);
                },
                '/^\s*([\w\|\[\]]+)\s?(.*)/s' => function($m) {
                    return array($m[1], NULL, $m[2]);
                },
                '/^\s*(\$\w+)\s?(.*)/s' => function($m) {
                    return array(NULL, $m[1], $m[2]);
                });

            list($type, $var, $note) = array();
            foreach ($patterns as $pattern => $lister) {
                if (preg_match($pattern, $text, $match)) { 
                    list($type, $var, $note) = $lister($match);
                    if ($tag == '@var' && !$var) { $var = "\$$name"; }
                    break;
                }
            }
            return array($type, $var, $note);
        };
        // Parse tag into (type, note).
        $pair = function($text) use ($triplet) {
            list($type, $var, $note) = $triplet($text);
            return array($type, $note);
        };
        // Return the given text.
        $identity = function($text) { return $text; };

        // Complex tags require complex parsing. All others use $identity().
        $config = array(
            '@param'  => $triplet,
            '@var'    => $triplet,
            '@return' => $pair, 
            '@throws' => $pair
            );

        $list = array();
        foreach ($node[$tag] as $text) {
            $parser = isset($config[$tag]) ? $config[$tag] : $identity;
            $list[] = $parser($text);
        }
        return $list;
    }


    ////////////////////////////////////////////////////////////////
    // Helper methods.
    ////////////////////////////////////////////////////////////////

    /**
     * @param string $nodeName A tree node name, e.g. 'methods'.
     * @param string $name ReflectionClass object name.
     * @throws InvalidArgumentException If ($nodeName, $name) don't locate a node.
     * @return array Parsed doc comment data structure.
     */
    private function node($nodeName, $name) {
        if (!isset($this->tree[$nodeName])) {
            throw new InvalidArgumentException("No root `$nodeName`");
        }
        if (!isset($this->tree[$nodeName][$name])) {
            $e = "No `$name` in parse tree, under `$nodeName`";
            throw new InvalidArgumentException($e);
        }
        return $this->tree[$nodeName][$name];
    }

    /**
     * @param string $nodeName A tree node name, e.g. 'methods'.
     * @return array Sorted array of ReflectionClass object names.
     */
    private function sortedNames($nodeName) {
        $names = array_keys($this->tree[$nodeName]);
        sort($names);
        return $names;
    }

    /**
     * Side-effect only, on &$node reference.
     * @param array &$node A parse tree node.
     * @param string $name ReflectionClass object name.
     * @return void
     */
    private function guaranteeMinimalVar(&$node, $name) {
        $empty = !count($node['@var']) || 
                 (count($node['@var']) == 1 && empty($node['@var'][0]));
        $empty and $node['@var'] = array("\$$name");
    }
}
