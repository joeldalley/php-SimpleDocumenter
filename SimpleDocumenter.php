<?php
/**
 * The World's simplest phpdoc comment analyzer.
 *
 * @author Joel Dalley
 * @version 2015/Mar/05
 * @see https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenter {

    const BRANCH_CLASS   = 'class';
    const BRANCH_CONST   = 'constants';
    const BRANCH_PROPS   = 'properties';
    const BRANCH_METHODS = 'methods';

    /** @var array $tree Stores SimpleDocumenterTags, after parsing. */
    private $tree = array(             # After parsing, values become:
        self::BRANCH_CLASS   => NULL,    # A SimpleDocumenterTag
        self::BRANCH_METHODS => array(), # Zero or more SimpleDocumenterTags
        self::BRANCH_PROPS   => array(), # Zero or more SimpleDocumenterTags
        self::BRANCH_CONST   => array()  # Zero or more SimpleDocumenterTags
        );

    
    /** @var string $startsWithTag Regexp for matching tag names */
    private $startsWithTag = NULL;

    /** @var array $commentTrims Regexps that trim doc comments */
    private static $commentTrims = array(
        '/^[[:space:]]*\/\*\*[[:space:]]*/',
        '/^[[:space:]]*\*\/[[:space:]]*$/',
        '/^[[:space:]]*\/\*\*$/',
        '/\*\/[[:space:]]*$/'
        );

    /** @var array $tags Phpdoc tags. */
    private static $tags = array(
        '@access', '@author', '@const', '@example', '@package', 
        '@param', '@return', '@see', '@throws', '@var', '@version'
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
     *
     * # Print out public method names.
     * foreach ($simple->methodNames() as $name) {
     *     print "Public method Foo::{$name}\n";
     * }
     *
     * @param string|NULL $class If a class name is provided, then that class
     *                           is parsed upon construction. Default: NULL.
     * @throws ErrorException If given a class that doesn't exist in memory.
     * @return SimpleDocumenter
     */
    public function __construct($class = NULL) {
        $class = (string) $class;

        // Matches a tag at the beginning of a line.
        $this->startsWithTag = '/^(' . implode('|', self::$tags) . ')(\s|)/';

        if (!empty($class)) {
            // A couple simple-minded guesses to include it.
            $ext = array('.php', '.class.php');
            while (!class_exists($class) && count($ext)) {
                @include $class . array_shift($ext);
            }
            // If it still doesn't exist, parsing cannot proceed.
            if (!class_exists($class)) {
                throw new ErrorException("Class `$class` doesn't exist");
            }
            $this->parseClass(new ReflectionClass($class));
        }
    }

    /** @return SimpleDocumenterTag[] Zero or more SimpleDocumenterTags. */
    public function constantTags() { return $this->tree[self::BRANCH_CONST]; }

    /** 
     * @param string $name A class name.
     * @param string $tag A doc comment tag name.
     * @param bool $first TRUE to return the first SimpleDocumenterTag in
     *                    the array for $tag, or FALSE for all of them.
     * @return SimpleDocumenterTag|SimpleDocumenterTag[] 
     */
    public function classTags($name, $tag, $first = FALSE) {
        $node = $this->node(self::BRANCH_CLASS, $name);
        return $this->tags($node, $tag, $first);
    }

    /** @return string[] The property names from the parsed class. */
    public function propertyNames() {
        return array_keys($this->tree[self::BRANCH_PROPS]);
    }

    /** 
     * @param string $name A property name.
     * @param string $tag A doc comment tag name.
     * @param bool $first TRUE to return the first SimpleDocumenterTag in
     *                    the array for $tag, or FALSE for all of them.
     * @return SimpleDocumenterTag|SimpleDocumenterTag[] 
     */
    public function propertyTags($name, $tag, $first = FALSE) {
        $node = $this->node(self::BRANCH_PROPS, $name);
        return $this->tags($node, $tag, $first);
    }

    /** @return string[] The method names from the parsed class. */
    public function methodNames() {
        return array_keys($this->tree[self::BRANCH_METHODS]);
    }

    /** 
     * @param string $name A method name.
     * @param string $tag A doc comment tag name.
     * @param bool $first TRUE to return the first SimpleDocumenterTag in
     *                    the array for $tag, or FALSE for all of them.
     * @return SimpleDocumenterTag|SimpleDocumenterTag[] 
     */
    public function methodTags($name, $tag, $first = FALSE) {
        $node = $this->node(self::BRANCH_METHODS, $name);
        return $this->tags($node, $tag, $first);
    }


    ////////////////////////////////////////////////////////////////
    // Parsers.
    ////////////////////////////////////////////////////////////////

    /**
     * Side-effects only, on $this->tree.
     * @param ReflectionClass $refl Instance of ReflectionClass.
     * @return SimpleDocumenter Returns $this.
     */
    public function parseClass($refl) {
        $this->reflectToTags($refl, &$this->tree[self::BRANCH_CLASS]);

        foreach ($refl->getProperties() as $_) {
            $this->reflectToTags($_, &$this->tree[self::BRANCH_PROPS]);
        }

        foreach ($refl->getMethods() as $_) {
            $this->reflectToTags($_, &$this->tree[self::BRANCH_METHODS]);
        }

        foreach ($refl->getConstants() as $name => $value) {
            $simpDocTag = new SimpleDocumenterTag(array(
                'tag'   => '@const',
                'name'  => $name,
                'value' => $value
            ));
            $this->tree[self::BRANCH_CONST][] = $simpDocTag;
        }

        return $this;
    }

    /**
     * Side-effect on $this->tree. 
     * Parse a single doc comment into SimpleDocumenterTags, and store them.
     *
     * @param object $reflector A ReflectionX object.
     * @param array $branch A branch of the SimpleDocumenterTag object tree.
     * @return void
     */
    private function reflectToTags($reflector, $branch) {
        $comment = $reflector->getDocComment();

        if (!$comment) {
            return; 
        }

        // Parse state defaults.
        $keys = array_merge(self::$tags, array('@note'));
        $values = array_fill(0, count($keys), array());
        $tagObjs = array_combine($keys, $values);
        list($idx, $tag, $objCount) = array(0, '@note', 0);

        // Trim and split comment; most parsing is done per-line.
        $comment = preg_replace(self::$commentTrims, '', $comment);
        $lines = preg_split('/[\r\n]/', $comment);

        while (count($lines)) {
            $_ = array_shift($lines);

            // Trim line.
            $_ = preg_replace('/^[[:blank:]]*\*([[:blank:]]|)/', '', $_);
            $_ = preg_replace('/[[:space:]]*$/', '', $_);

            // Entering a new occurrence of a tag:
            if (preg_match($this->startsWithTag, $_, $match)) {
                if (isset($tagObjs[$tag][$idx])) {
                    $simpDocTag = $tagObjs[$tag][$idx];
                    $simpDocTag->analyzeText();
                }
                $tag = $match[1];
                $idx = count($tagObjs[$tag]);
                $_ = preg_replace($this->startsWithTag, '', $_);
            }

            if (!isset($tagObjs[$tag][$idx])) { 
                $tagObjs[$tag][$idx] = new SimpleDocumenterTag(array(
                    'refl' => $reflector,
                    'tag'  => $tag,
                    'text' => NULL
                ));
                $objCount += 1;
            }
            elseif (strlen($tagObjs[$tag][$idx]->text())) {
                $_ = "\n$_"; // Replace horz. space from preg_split.
            }

            $tagObjs[$tag][$idx]->appendText($_);
            count($lines) < 2 and $tagObjs[$tag][$idx]->analyzeText();
        }

        $isProp = $reflector instanceof ReflectionProperty;
        $nodeName = ($isProp ? '$' : '') . $reflector->name;

        // Special magic to make it so that if a class property
        // has a doc comment, but is missing the @var entry, here
        // it is made to look like it had "@var $PropertyName".
        $noVar = !count($tagObjs['@var']) || !$tagObjs['@var'][0]->name();
        if ($isProp && $noVar) {
            $tagObjs['@var'][0] = new SimpleDocumenterTag(array(
                'refl' => $reflector,
                'tag'  => '@var',
                'text' => $nodeName,
                'name' => $nodeName,
            ));
            $objCount += 1;
        }

        // Store the node if the doc comment had at least one tag in it.
        $objCount and $branch[$nodeName] = $tagObjs;
    }


    ////////////////////////////////////////////////////////////////
    // Helper methods.
    ////////////////////////////////////////////////////////////////

    /**
     * @param string $branchName A SimplerDocumenterTag tree branch name.
     * @param string $nodeName The name of node within a tree branch.
     * @return array The SimpleDocumenterTags stored in the specified node.
     * @throws InvalidArgumentException If the specified node can't be found.
     */
    private function node($branchName, $nodeName) {
        if (!isset($this->tree[$branchName][$nodeName])) {
            throw new InvalidArgumentException("No $branchName `$nodeName`");
        }
        return $this->tree[$branchName][$nodeName];
    }

    /** 
     * @param array $node A tree node containing SimpleDocumenterTags.
     * @param string $tag A tag name.
     * @param bool $first TRUE to return only the first SimpleDocumenterTag
     *                    found at $node for $tag, or FALSE for all of them.
     * @return SimpleDocumenterTag|SimpleDocumenterTag[] One or all.
     */
    private function tags($node, $tag, $first = FALSE) {
        if (!in_array($tag, array_merge(self::$tags, array('@note')))) {
            throw new InvalidArgumentException("No tag `$tag`");
        }

        // Zero or more SimpleDocumenterTags.
        $objs = !empty($node) && isset($node[$tag]) ? $node[$tag] : array();

        // Type guarantee: ensure a SimpleDocumenterTag 
        // is returned, if a single object was requested.
        if ($first && empty($objs)) {
             $objs[] = new SimpleDocumenterTag(array('tag' => $tag));
        }

        return $first ? array_shift($objs) : $objs;
    }
}

/**
 * Express a single doc comment tag as an object.
 *
 * @author Joel Dalley
 * @version 2015/Mar/05
 * @see https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenterTag {

    /** @var array $fields Object fields. */
    private $fields = array(
        'refl'  => NULL, 
        'tag'   => '',
        'text'  => '',
        'name'  => '',
        'value' => '',
        'type'  => '',
        'note'  => '',
        );

    /**
     * Constructor.
     * @param array $pairs Pairs of (field name => value).
     * @return SimpleDocumenterTag 
     */
    public function __construct($pairs) {
        foreach ($pairs as $key => $value) {
            if (array_key_exists($key, $this->fields)) {
                $this->fields[$key] = $value;
            }
        }
    }

    /**
     * @param string $fieldOrMethod An object field name or method name.
     * @param array|NULL $args Optional arguments array (if calling a method).
     * @return mixed One of: the object field value, the method return value,
     *                       or NULL, depending on caller arguments.
     */
    public function __call($fieldOrMethod, $args = array()) {
        if (array_key_exists($fieldOrMethod, $this->fields)) {
            if (count($args) == 1) {
                $this->fields[$fieldOrMethod] = array_shift($args);
            }
            return $this->fields[$fieldOrMethod];
        }
        try { 
            $callable = array($this->refl(), $fieldOrMethod);
            return call_user_func_array($fieldOrMethod, $args);
        }
        catch(Exception $e) { 
            return NULL;
        }
    }

    /** @return string The object's text field value */
    public function __toString() { return (string) $this->text(); }

    /** 
     * @param string $text Text to append to the text field value.
     * @return void
     */
    public function appendText($text) { $this->fields['text'] .= $text; }
 
    /**
     * Side-effect on object's properties, per the analysis.
     * Analyze the object's text, looking for pieces within certain tags.
     * For instance, @param might have a type, a name and a descriptive note.
     * @return void
     */
    public function analyzeText() {
        $config = array('@param', '@var', '@return', '@throws');
        $text = $this->text();

        if (is_null($text) || !in_array($this->tag(), $config)) {
            return;
        }

        // Try to find (type, name, note), from tag text.
        $patterns = array(
            '/^\s*([\w\|\[\]]+)\s+(\&?\$\w+)\s?(.*)/s' => function($m) {
                return array($m[1], $m[2], $m[3]);
            },
            '/^\s*([\w\|\[\]]+)\s?(.*)/s' => function($m) {
                return array($m[1], NULL, $m[2]);
            },
            '/^\s*(\&?\$\w+)\s?(.*)/s' => function($m) {
                return array(NULL, $m[1], $m[2]);
            });

        foreach ($patterns as $pat => $sub) {
            if (preg_match($pat, $text, $match)) {
                // Matched: set object properties, and stop.
                list($type, $name, $note) = $sub($match);
                $this->type($type);
                $this->name($name);
                $this->note($note);
                return;
            }
        }
    }
}
