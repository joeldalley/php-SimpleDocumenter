<?php
/**
 * The World's simplest phpdoc comment analyzer.
 * @author Joel Dalley
 * @version 2015/Feb/28
 */
class SimpleDocumenter {

    /** @const PUBLIC_METHODS Alias ReflectionMethod::IS_PUBLIC. */
    const PUBLIC_METHODS = ReflectionMethod::IS_PUBLIC;

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

    /** @var ReflectionClass $refl */
    private $refl = NULL;

    /** @var array $tree Doc comments parse tree */
    private $tree = array(
        'methods' => array(),
        'class'   => array(),
        );


    ////////////////////////////////////////////////////////////////
    // Static methods.
    ////////////////////////////////////////////////////////////////

    /** @return array Tags array. */
    public static function newParsed() { return self::$tags; }


    ////////////////////////////////////////////////////////////////
    // Object interface.
    ////////////////////////////////////////////////////////////////

    /**
     * Constructor.
     *   Here is more about the constructor.
     *   Here is even more. It just keeps going.
     *
     * @param string $class A class name.
     * @example 
     *
     * # Echoes the php doc comment for class Foo.
     * $simple = new SimpleDocumenter('Foo');
     * echo $simple->classComment(), "\n";
     *
     * # Print out public method names.
     * foreach ($simple->methodNames() as $name) {
     *     print "Public method Foo::{$name}\n";
     * }
     *
     * @throws InvalidArgumentException If $class isn't a class.
     * @return SimpleDocumenter
     *
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
        $methods = array_keys($this->tree['methods']);
        sort($methods);
        return $methods;
    }

    /** 
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment, all tags, as string or array. 
     */
    public function classComment($toStr = FALSE) {
        $node = $this->tree['class'];
        return $toStr === TRUE
             ? $this->reconstructComment($node)
             : $this->parse_all($node, function($tag, $branch) {
                   return array($tag => $branch);
               });
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment, all tags, as string or array.
     */
    public function methodComment($method, $toStr = FALSE) {
        $node = $this->methodNode($method);
        return $toStr === TRUE
             ? $this->reconstructComment($node)
             : $this->parse_all($node, function($tag, $branch) {
                   return array($tag => $branch);
               });
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@return' as string or array.
     */
    public function methodReturn($method, $toStr = FALSE) {
        $return = $this->parse_tag('@return', $this->methodNode($method));
        list($type, $note) = array($return[0][0], $return[0][1]);
        return $toStr === TRUE ? $type . ($note ? " $note" : '') : $return[0];
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@throws' as string or array.
     */
    public function methodThrows($method, $toStr = FALSE) {
        $throws = $this->parse_tag('@throws', $this->methodNode($method));
        return $toStr === TRUE ? join(', ', $throws) : $throws;
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@example' as string or array.
     */
    public function methodExample($method, $toStr = FALSE) {
        $ex = $this->parse_tag('@example', $this->methodNode($method));
        return $toStr === TRUE ? join(', ', $ex) : $ex;
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@param' as string or array.
     */
    public function methodParams($method, $toStr = FALSE) {
        $params = $this->parse_tag('@param', $this->methodNode($method));
        return $toStr === TRUE ? join(', ', $params) : $params;
    }

    /**
     * @param string $method A method name.
     * @param bool $toStr TRUE for string, FALSE for array. Default: FALSE.
     * @return string|array Parsed doc comment '@note' as string or array.
     */
    public function methodNote($method, $sep = "\n") {
        try {
            $node = $this->methodNode($method);
            $note = $this->parse_tag('@note', $node);
            return $note[0][0];
        }
        catch(InvalidArgumentException $e) { return ''; }
    }

    /**
     * @param string $method A method name.
     * @return string A function signature string.
     */
    public function methodSignature($method) {
        list($type) = $this->methodReturn($method);
        $map = function($_) { return join(' ', array_slice($_, 0, 2)); };
        $args = join(', ', array_map($map, $this->methodParams($method)));
        return ($type ? "$type " : '') . "$method($args);";
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
        $this->tree['class'] = $this->parseDocComment($refl->getDocComment());
        foreach ($refl->getMethods(self::PUBLIC_METHODS) as $obj) {
            $parsed = $this->parseDocComment($obj->getDocComment());
            $this->tree['methods'][$obj->name] = $parsed;
        }

        return $this->tree;
    }

    /**
     * @param string $comment A doc comment      .
     * @return array Parsed doc comment data structure.
     */
    private function parseDocComment($comment) {
        $parsed = self::newParsed();
        $parsed['@note'] = array();

        if (!$comment) { return $parsed; }
       
        // Remove leading "/**" and trailing "*/".
        $bounds = array(
            '/^[[:space:]]*\/\*\*[[:space:]]*[\r\n]?/',
            '/^[[:space:]]*\*\/[[:space:]]*$/',
            '/^[[:space:]]*\/\*\*[\r\n]?$/',
            '/\*\/[[:space:]]*[\r\n]?$/'
            );
        $comment = preg_replace($bounds, '', $comment);
 
        // Remove leading "*" from lines within the comment body.
        $lines = preg_split('/[\r\n]/', $comment);
        if (count($lines) == 1) {
            $lines[0] = trim($lines[0]);
        }
        elseif (count($lines) > 1) {
            foreach ($lines as &$_) {
                $_ = preg_replace('/^[[:blank:]]*\*/', '', $_);
            }
        }

        // Loop on word-ish chunks, looking for phpdoc tags,
        // and organizing the comment contents by tag.
        $tagIdx = 0;
        $tag = '@note';
        $inTag = FALSE;
        $words = preg_split('/[[:blank:]]/', join("\n", $lines));
        foreach ($words as $word) {
            if (array_key_exists($word, self::$tags)) {
                if (isset($parsed[$tag][$tagIdx])) {
                    $count = count($parsed[$tag][$tagIdx]);
                    $_ = &$parsed[$tag][$tagIdx][0];
                    $_ = ltrim($_);
                    $_ = &$parsed[$tag][$tagIdx][$count-1];
                    $_ = rtrim($_);
                }
                $inTag = $word;
                $tagIdx = count($parsed[$word]);
            }
            else {
                $tag = $inTag ? $inTag : '@note';
                if (!isset($parsed[$tag][$tagIdx])) {
                    $parsed[$tag][$tagIdx] = array();
                }
                $parsed[$tag][$tagIdx][] = $word;
            }
        }

        return $parsed;
    }

    /**
     * @param string $tag A tag name.
     * @param array $branch A branch of $this->tree.
     * @throws InvalidArgumentException If $tag isn't a valid tag name.
     * @return array A list of tag entries.
     */
    private function parse_tag($tag, $branch) {
        $config = array(
            '@param' => function($_) { 
                $name = strpos((string) $_[1], '$') === 0 ? $_[1] : NULL;
                return array(
                    $_[0],                                     // type
                    (string) $name,                            // name
                    join(' ', array_slice($_, $name ? 2 : 1)), // note
                    );
                },
            '@return' => function($_) {
                return array(
                    $_[0],                        // type
                    join(' ', array_slice($_, 1)) // note
                    );
                },
            '@throws' => function($_) {
                return array(
                    $_[0],                         // type
                    join(' ', array_slice($_, 1)), // note
                    );
                }
            );
        
        $list = array();
        foreach ($branch[$tag] as $node) {
            $parser = isset($config[$tag])
                    ? $config[$tag]
                    : function($_) { return array(join(' ', $_)); };
            $list[] = $parser($node);
        }
        return $list;
    }

    /** 
     * Parses the given branch, applying the given callback to each tag.
     * @param array $branch A branch within $this->tree.
     * @param function $callback A function to apply to each tag.
     * @return array A list of the processed tags, from the given branch.
     */
    private function parse_all($branch, $callback) {
        $tags = array_merge(array('@note'), array_keys(self::$tags));
        $list = array();
        foreach ($tags as $tag) {
            foreach ($this->parse_tag($tag, $branch) as $parsed) { 
                $list[] = $callback($tag, $parsed);
            }
        }
        return $list;
    }


    ////////////////////////////////////////////////////////////////
    // Helper methods.
    ////////////////////////////////////////////////////////////////

    /**
     * @param array $branch A branch within $this->tree.
     * @return string Reconstructed doc comment.
     */
    private function reconstructComment($branch) {
        $toString = function($tag, $parsed) { 
            $sift = function($_) { return !empty($_); };
            return ($tag == '@note' ? '' : "$tag ")
                 . join(' ', array_filter($parsed, $sift));
        };
        $tags = $this->parse_all($branch, $toString);
        return join("\n", $tags);
    }
 
    /**
     * @param string $method A method name.
     * @return array Parsed doc comment data structure.
     * @throws InvalidArgumentException If method isn't valid.
     */
    private function methodNode($method) {
        $method = (string) $method;
        if (!isset($this->tree['methods'][$method])) {
            $e = "No method `$method` in parse tree";
            throw new InvalidArgumentException($e);
        }
        return $this->tree['methods'][$method];
    }
}
