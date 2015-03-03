<?php
/**
 * The World's simplest phpdoc comment analyzer.
 * @author Joel Dalley
 * @version 2015/Feb/28
 */
class SimpleDocumenter {

    const PUBLIC_METHODS = ReflectionMethod::IS_PUBLIC;
    const PUBLIC_PROPERTIES = ReflectionProperty::IS_PUBLIC;

    /** @var array $tags Phpdoc tags. */
    private static $tags = array(
        '@package' => array(),
        '@author'  => array(),
        '@version' => array(),
        '@var'     => array(),
        '@see'     => array(),
        '@example' => array(),
        '@param'   => array(),
        '@return'  => array(),
        '@throws'  => array(),
        '@access'  => array(),
        );

    /** @var string $class The class to introspect. */
    private $class = NULL;

    /** @var ReflectionClass $refl The ReflectionClass used to help parse. */
    private $refl = NULL;

    /** @var array $tree Doc comments parse tree */
    private $tree = array(
        'properties' => array(),
        'constants'  => array(),
        'methods'    => array(),
        'class'      => array(),
        );


    ////////////////////////////////////////////////////////////////
    // Interface.
    ////////////////////////////////////////////////////////////////

    /**
     * Constructor.
     *
     * @example 
     * # Echoes the php doc comment for class Foo.
     * $simple = new SimpleDocumenter('Foo');
     * echo $simple->classComment(), "\n";
     *
     * # Print out public method names.
     * foreach ($simple->methodNames() as $name) {
     *     print "Public method Foo::{$name}\n";
     * }
     *
     * @param string $class A class name.
     *
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
        $this->tree = $this->parse();
    }

    /**
     * Returns the name of the class being introspected.
     * @return string The class being introspected. 
     */
    public function className() { return $this->class; }

    /** @return string[] The methods of the introspected class. */
    public function methodNames() {
        $names = array_keys($this->tree['methods']);
        sort($names);
        return $names;
    }

    /** @return string[] The properties of the introspected class. */
    public function propertyNames() {
        $names = array_keys($this->tree['properties']);
        sort($names);
        return $names;
    }

    /** @return array Pairs of (Constant name => Constant value). */
    public function getConstants() { return $this->tree['constants']; }

    /** 
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment, all tags, as string or array. 
     */
    public function classComment($str = FALSE) {
        return $this->fullComment($this->tree['class'], $str);
    }

    /**
     * @param string $name A property name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment, all tags, as string or array.
     */
    public function propertyComment($name, $str = FALSE) {
        return $this->fullComment($this->propNode($name), $str);
    }

    /**
     * @param string $name A property name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@example' as string or array.
     */
    public function propertyExample($name, $str = FALSE) {
        return $this->parseSimple($this->propNode($name), '@example', $name, $str);
    }

    /**
     * @param string $name A property name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@var' as string or array.
     */
    public function propertyVar($name, $str = FALSE) {
        $var = $this->parseNode('@var', $this->propNode($name), $name);
        return $str === TRUE ? implode(', ', $var[0]) : $var[0];
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment, all tags, as string or array.
     */
    public function methodComment($name, $str = FALSE) {
        return $this->fullComment($this->methodNode($name), $str);
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@return' as string or array.
     */
    public function methodReturn($name, $str = FALSE) {
        $return = $this->parseNode('@return', $this->methodNode($name), $name);
        list($type, $note) = array($return[0][0], $return[0][1]);
        return $str === TRUE ? $type . ($note ? " $note" : '') : $return[0];
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@throws' as string or array.
     */
    public function methodThrows($name, $str = FALSE) {
        return $this->parseSimple($this->methodNode($name), '@throws', $name, $str);
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@example' as string or array.
     */
    public function methodExample($name, $str = FALSE) {
        return $this->parseSimple($this->methodNode($name), '@example', $name, $str);
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@param' as string or array.
     */
    public function methodParams($name, $str = FALSE) {
        return $this->parseSimple($this->methodNode($name), '@param', $name, $str);
    }

    /**
     * @param string $name A method name.
     * @param bool $str TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@note' as string or array.
     */
    public function methodNote($name, $sep = "\n") {
        try {
            $node = $this->methodNode($name);
            $note = $this->parseNode('@note', $node, $name);
            return $note[0][0];
        }
        catch(InvalidArgumentException $e) { return ''; }
    }

    /**
     * @param string $name A method name.
     * @return string A function signature string.
     */
    public function methodSignature($name) {
        list($type) = $this->methodReturn($name);
        $map = function($_) { return implode(' ', array_slice($_, 0, 2)); };
        $args = implode(', ', array_map($map, $this->methodParams($name)));
        return ($type ? "$type " : '') . "$name($args);";
    }


    ////////////////////////////////////////////////////////////////
    // Parsers.
    ////////////////////////////////////////////////////////////////

    /**
     * Parse all doc comments, and return parsed data structure.
     * @return array Parsed doc comments data structure.
     */
    private function parse() {
        $refl = $this->refl;

        $this->parseDocComment($refl->getDocComment(), $this->tree['class']);

        foreach ($refl->getConstants() as $name => $value) {
            $this->tree['constants'][$name] = $value;
        }

        foreach ($refl->getProperties(self::PUBLIC_PROPERTIES) as $obj) {
            $this->tree['properties'][$obj->name] = NULL;
            $_ = &$this->tree['properties'][$obj->name];
            $this->parseDocComment($obj->getDocComment(), $_, $obj->name);
        }

        foreach ($refl->getMethods(self::PUBLIC_METHODS) as $obj) {
            $this->tree['methods'][$obj->name] = NULL;
            $_ = &$this->tree['methods'][$obj->name];
            $this->parseDocComment($obj->getDocComment(), $_, $obj->name);
        }

        return $this->tree;
    }

    /**
     * @param string $comment A doc comment.
     * @param array &$node A parse tree node.
     * @param string $name|NULL Optional ReflectionClass object name.
     * @return void
     */
    private function parseDocComment($comment, &$node, $name = NULL) {
        $node = self::$tags;
        $node['@note'] = array();

        if (!$comment) { return $parsed; }

        $tagRegex = '/^(' . implode('|', array_keys(self::$tags)) . ')(\s|)/';

        // Remove leading "/**" and trailing "*/", and split into lines.
        $bounds = array(
            '/^[[:space:]]*\/\*\*[[:space:]]*/',
            '/^[[:space:]]*\*\/[[:space:]]*$/',
            '/^[[:space:]]*\/\*\*$/',
            '/\*\/[[:space:]]*$/'
            );
        $comment = preg_replace($bounds, '', $comment);
        $lines = preg_split('/[\r\n]/', $comment);

        // Parse state defaults.
        list($idx, $tag) = array(0, '@notes');

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

            // Either an empty line or a line that had only a tag on it.
            if (empty($_)) { continue; }
            
            // The cursor is either positioned inside of a multi-line tag, or
            // the current line is the first line for a new occurrence of $tag.
            // Initialize or re-establish "\n" from preg_split(), accordingly.
            if (!isset($node[$tag][$idx])) { $node[$tag][$idx] = ''; }
            else { $_ = "\n$_"; }

            // Append to current tag.
            $node[$tag][$idx] .= $_;
        }
    }

    /**
     * @param string $tag A tag name.
     * @param array &$node A node of $this->tree.
     * @param string|NULL $name Optional: a node object name, or NULL.
     * @return array A list of tag entries.
     */
    private function parseNode($tag, &$node, $name = NULL) {
        $typeNameNote = function($_) use ($name, $tag) {
            $patterns = array(
                '/^\s*([\w\|\[\]]+)\s+(\&?\$\w+)\s?(.*)?$/' => function($m) {
                    return array($m[1], $m[2], $m[3]);
                },
                '/^\s*([\w\|\[\]]+)\s?(.*)?$/' => function($m) {
                    return array($m[1], NULL, $m[2]);
                },
                '/^\s*(\$\w+)\s?(.*)?$/' => function($m) {
                    return array(NULL, $m[1], $m[2]);
                });

            list($type, $var, $note) = array();
            foreach ($patterns as $p => $lister) {
                if (preg_match($p, $_, $m)) { 
                    list($type, $var, $note) = $lister($m);
                    if ($tag == '@var' && !$var) { $var = "\$$name"; }
                    break;
                }
            }
            return array($type, $var, $note);
        };
        $typeNote = function($_) use ($typeNameNote) {
            list($type, $var, $note) = $typeNameNote($_);
            return array($type, $note);
        };

        $config = array(
            '@param'  => $typeNameNote,
            '@var'    => $typeNameNote,
            '@return' => $typeNote, 
            '@throws' => $typeNote
            );

        // Special magic to make doc comment-less class properties
        // appear as if they had a "@var $name" doc comment.
        if ($tag == '@var') {
            $empty = !count($node['@var']) || 
                     (count($node['@var']) <= 1 && empty($node['@var'][0]));
            $empty and $node['@var'] = array("\$$name");
        }

        $list = array();
        foreach ($node[$tag] as $text) {
            $parser = isset($config[$tag]) 
                    ? $config[$tag] : function($_) { return $_; };
            $list[] = $parser($text);
        }
        return $list;
    }

    /**
     * @param array $node A node of $this->tree.
     * @param string $tag A tag name.
     * @param string|NULL $name Optional: a node object name, or NULL.
     * @param bool $str TRUE to return as string, FALSE for array.
     * @return array A list of tag entries.
     */
    private function parseSimple($node, $tag, $name, $str) {
        $parsed = $this->parseNode($tag, $node, $name);
        return $str === TRUE ? implode(', ', $parsed) : $parsed;
    }

    /** 
     * Parses the given node, applying the given callback to each tag.
     * @param array $node A node within $this->tree.
     * @param function $callback A function to apply to each tag.
     * @return array A list of the processed tags, from the given node.
     */
    private function parseAllNodes($node, $callback) {
        $tags = array_merge(array('@note'), array_keys(self::$tags));
        $list = array();
        foreach ($tags as $tag) {
            foreach ($this->parseNode($tag, $node) as $parsed) { 
                $list[] = $callback($tag, $parsed);
            }
        }
        return $list;
    }


    ////////////////////////////////////////////////////////////////
    // Helper methods.
    ////////////////////////////////////////////////////////////////

    /**
     * @param array $node A node within $this->tree.
     * @param bool $str TRUE to return a string, or FALSE for array.
     * @return string|array Parsed doc comment as string or array.
     */
    private function fullComment($node, $str = FALSE) {
        return $str === TRUE
             ? $this->reconstructComment($node)
             : $this->parseAllNodes($node, function($tag, $node) {
                   return array($tag => $node);
               });
    }

    /**
     * @param array $node A node within $this->tree.
     * @return string Reconstructed doc comment.
     */
    private function reconstructComment($node) {
        $string = function($tag, $parsed) { 
            $sift = function($_) { return !empty($_); };
            return ($tag == '@note' ? '' : "$tag ")
                 . implode(' ', array_filter($parsed, $sift));
        };
        $tags = $this->parseAllNodes($node, $string);
        return implode("\n", $tags);
    }

    /**
     * @param string $root A root node name, e.g., 'methods'.
     * @param string $name A parsed object name.
     * @throws InvalidArgumentException If ($root, $node) don't locate a node.
     * @return array Parsed doc comment data structure.
     */
    private function node($root, $name) {
        list($root, $name) = array((string) $root, (string) $name);
        if (!isset($this->tree[$root])) {
            throw new InvalidArgumentException("No root `$root`");
        }
        if (!isset($this->tree[$root][$name])) {
            $e = "No `$name` in parse tree, under `$root`";
            throw new InvalidArgumentException($e);
        }
        return $this->tree[$root][$name];
    }

    /**
     * @param string $name A property name.
     * @return array Parsed doc comment data structure.
     */
    private function propNode($name) { return $this->node('properties', $name); }
 
    /**
     * @param string $name A method name.
     * @return array Parsed doc comment data structure.
     */
    private function methodNode($name) { return $this->node('methods', $name); }
}
