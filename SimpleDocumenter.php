<?php
/**
 * The World's simplest phpdoc-comment analyzer.
 * @author Joel Dalley
 * @version 2015/Feb/28
 */
class SimpleDocumenter {

    /** @const NEWLINE Newline char placeholder. */
    const NEWLINE = '__NEWLINE__';

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
     * Constructor
     * @param string $class A class name.
     * @example 
     *
     *  $simple = new SimpleDocumenter('Foo');
     *  echo $simple->classComment(), "\n";
     *
     * @throws InvalidArgumentException If $class isn't a class.
     * @return SimpleDocumenter
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

    /** @return string The class being introspected. */
    public function className() { return $this->class; }

    /** @return string A re-constructed class doc comment. */
    public function classComment() {
        return $this->reconstructComment($this->tree['class']);
    }

    /** @return string[] The methods of the introspected class. */
    public function methodNames() {
        return array_keys($this->tree['methods']);
    }

    /**
     * @param string $method A method name.
     * @return string A re-constructed method doc comment. 
     */
    public function methodComment($method) {
        $method = $this->verifyMethod($method);
        return $this->reconstructComment($this->tree['methods'][$method]);
    }
 
    /**
     * @param string $method A method name.
     * @return string[] Zero or more example tag entries.
     */
    public function methodExamples($method) {
        $method = $this->verifyMethod($method);
        $branch = $this->tree['methods'][$method];
        return $this->parse_tag('@example', $branch);
    }

    /**
     * @param string $method A method name.
     * @return string A function signature string.
     */
    public function methodSignature($method) {
        $method = $this->verifyMethod($method);
        $branch = $this->tree['methods'][$method];
        $return = $this->parse_tag('@return', $branch);
        $map = function($_) { return join(' ', array_slice($_, 0, 2)); };
        $params = array_map($map, $this->parse_tag('@param', $branch));
        return ($return[0][0] ? "{$return[0][0]} " : '')
             . "$method(" . join(', ', $params) . ');';
    }

    /**
     * Parse all doc comments, and return parsed data structure.
     * @param int $filter A ReflectionMethod::x filter constant.
     * @return array Parsed doc comments data structure.
     */
    public function parse($filter = ReflectionMethod::IS_PUBLIC) { 
        $refl = $this->refl;

        $this->tree['class'] = $this->parseDocComment($refl->getDocComment());

        foreach ($refl->getMethods($filter) as $obj) {
            $parsed = $this->parseDocComment($obj->getDocComment());
            $this->tree['methods'][$obj->name] = $parsed;
        }

        return $this->tree;
    }

    /**
     * @param string $comment A doc comment      .
     * @return array Parsed doc comment data structure.
     */
    public function parseDocComment($comment) {
        $parsed = self::newParsed();
        $parsed['@comment'] = array();

        if (!$comment) { return $parsed; }

        $regexes = array(
            '/^\s*\/\*\**\s*/',
            '/\s*\**\*\/\s*$/',
            '/[\r\n]/', 
            '/\s*\*\s*/',
            );
        $replacements = array(' ', ' ', NEWLINE, ' ');
        $comment = preg_replace($regexes, $replacements,$comment);
        $words = preg_split('/\s+/', $comment);

        $tag = '@comment';
        $inTag = FALSE;
        $tagIdx = 0;

        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }
            elseif (array_key_exists($word, self::$tags)) {
                if (isset($parsed[$tag][$tagIdx])) {
                    $tagCount = count($parsed[$tag][$tagIdx]);
                    $_ = &$parsed[$tag][$tagIdx][$tagCount-1];
                    $_ = str_replace(NEWLINE, '', $_);
                }
                $inTag = $word;
                $tagIdx = count($parsed[$word]);
            }
            else {
                $tag = $inTag ? $inTag : '@comment';

                if (!isset($parsed[$tag][$tagIdx])) {
                    $parsed[$tag][$tagIdx] = array();
                }
                $parsed[$tag][$tagIdx][] = $word;
            }
        }

        return $parsed;
    }


    ////////////////////////////////////////////////////////////////
    // Individual tag parsers.
    ////////////////////////////////////////////////////////////////

    /**
     * @param string $tag A tag name.
     * @param array $branch A branch of $this->tree.
     * @throws InvalidArgumentException If $tag isn't a valid tag name.
     * @return array A list of tag entries.
     */
    private function parse_tag($tag, $branch) {

        $config = array(
            '@param' => function($_) { 
                return array(
                    $_[0],                         // type
                    $_[1],                         // name
                    join(' ', array_slice($_, 2)), // note
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
        foreach ($list as &$_) { $_ = str_replace(NEWLINE, "\n", $_); }
        return $list;
    }

    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of comment tag entries.
     */
    private function parse_comment($branch) {
        $list = array();
        foreach ($branch['@comment'] as $comment) {
            $list[] = array(join(' ', $comment));
        }
        return $list;
    }


    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of author tag entries.
     */
    private function parse_author($branch) {
        $list = array();
        foreach ($branch['@author'] as $author) {
            $list[] = array(join(' ', $author));
        }
        return $list;
    }

    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of version tag entries.
     */
    private function parse_version($branch) {
        $list = array();
        foreach ($branch['@version'] as $version) {
            $list[] = array(join(' ', $version));
        }
        return $list;
    }

    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of example tag entries.
     */
    private function parse_example($branch) {
        $list = array();
        foreach ($branch['@example'] as $ex) {
            $list[] = array(join(' ', $ex));
        }
        return $list;
    }

    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of see tag entries.
     */
    private function parse_see($branch) { 
        $list = array();
        foreach ($branch['@see'] as $see) {
            $list[] = array(join(' ', $see));
        }
        return $list;
    }

    /**
     * @param array $branch A branch of $this->tree.
     * @return array A list of var tag entries.
     */
    private function parse_var($branch) {
        $list = array();
        foreach ($branch['@var'] as $var) {
            $list[] = array(join(' ', $var));
        }
        return $list;
    }


    ////////////////////////////////////////////////////////////////
    // Helper methods.
    ////////////////////////////////////////////////////////////////

    /**
     * @param array $branch A branch within $this->tree.
     * @param array|NULL $exclude Zero or more tags to exclude.
     * @return string Reconstructed doc comment.
     */
    private function reconstructComment($branch, $exclude = array()) {
        $tags = array();
        $list = array_merge(array('@comment'), array_keys(self::$tags));

        foreach ($list as $tag) {
            try {
                $list = $this->parse_tag($tag, $branch);
                foreach ($list as $parsed) {
                    $sift = function($_) { return !empty($_); };
                    $tags[] = ($tag == '@comment' ? '' : "$tag ")
                            . join(' ', array_filter($parsed, $sift));
                }
            }
            catch(Exception $e) {
                trigger_error("Warning: No parser for tag `$tag`");
            }
        }

        return join("\n", $tags);
    }

    /**
     * @param string $method A method name.
     * @throws InvalidArgumentException If method isn't valid.
     * @return string The given method.
     */
    private function verifyMethod($method) {
        $method = (string) $method;
        if (!isset($this->tree['methods'][$method])) {
            $e = "No method `$method` in parse tree";
            throw new InvalidArgumentException($e);
        }
        return $method;
    }
}
