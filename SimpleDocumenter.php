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

    /** @var array $tree Pairs of ( BRANCH_x => SimpleDocumenterNode[] ). */
    private $tree = array(               
        self::BRANCH_CLASS   => NULL,
        self::BRANCH_METHODS => array(),
        self::BRANCH_PROPS   => array(),
        self::BRANCH_CONST   => array()
        );

    /** @var string[] $tags The phpdoc tags this module pays attention to. */
    private static $tags = array(
        '@access', '@author', '@const', '@example', '@package', 
        '@param', '@return', '@see', '@throws', '@var', '@version',
        '@link'
        );
    
    /** @var string[] $commentTrims Regexps that trim doc comments. */
    private static $commentTrims = array(
        '/^[[:space:]]*\/\*\*[[:space:]]*/',
        '/^[[:space:]]*\*\/[[:space:]]*$/',
        '/^[[:space:]]*\/\*\*$/',
        '/\*\/[[:space:]]*$/'
        );


    ////////////////////////////////////////////////////////////////
    // Interface.
    ////////////////////////////////////////////////////////////////

    /**
     * Parses the php doc comments of the given class.
     *
     * @param string $class The name of an in-memory class.
     * @throws ErrorException If given the name of a class that doesn't exist.
     * @return SimpleDocumenter Contains the doc comment parse of $class.
     */
    public function __construct($class) {
        $class = (string) $class;

        if (!class_exists($class)) {
            throw new ErrorException("Class `$class` doesn't exist");
        }

        $refl = new ReflectionClass($class);
        $this->tree[self::BRANCH_CLASS] = $this->node($refl);

        foreach ($refl->getProperties() as $_) {
            $this->tree[self::BRANCH_PROPS]["\${$_->name}"] = $this->node($_);
        }

        foreach ($refl->getMethods() as $_) {
            $this->tree[self::BRANCH_METHODS][$_->name] = $this->node($_);
        }

        foreach ($refl->getConstants() as $name => $value) {
            $struct = array('@const' => array(new SimpleDocumenterTag(array(
                'tag'   => '@const',
                'name'  => $name,
                'value' => $value
            ))));
            $node = new SimpleDocumenterNode($refl, $struct);
            $this->tree[self::BRANCH_CONST][$name] = $node;
        }
    }

    /** 
     * @return SimpleDocumenterNode A node containing the tags found in
     *                              any doc comment for the class itself.
     */
    public function classNode() { return $this->tree[self::BRANCH_CLASS]; }

    /**
     * @param callable|NULL $filter Optional filter function, where each filter
     *                              argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                constants, possibly filtered.
     */
    public function constantNodes($filter = NULL) { 
        // Filter here will be some kind of anscestor check.
        return $this->tree[self::BRANCH_CONST]; 
    }

    /**
     * @param callable|NULL $filter Optional filter function, where each filter
     *                              argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                properties, possibly filtered.
     */
    public function propertyNodes($filter = NULL) { 
        $nodes = $this->tree[self::BRANCH_PROPS]; 
        return SimpleDocumenterUtil::filter($nodes, $filter);
    }

    /**
     * @param callable|NULL $filter Optional filter function, where each filter
     *                              argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                methods, possibly filtered.
     */
    public function methodNodes($filter = NULL) {
        $nodes = $this->tree[self::BRANCH_METHODS]; 
        return SimpleDocumenterUtil::filter($nodes, $filter);
    }

    /**
     * @param object $reflector A Reflection(Class|Method|Property) object.
     * @return SimpleDocumenterNode A node containing all of the parsed phpdoc
     *                              tags from the given Reflection object's
     *                              getDocComment() string.
     */
    private function node($reflector) {
        // Initialize the data structure of a SimpleDocumenterNode.
        $keys = array_merge(self::$tags, array('@note'));
        $values = array_fill(0, count($keys), array());
        $struct = array_combine($keys, $values);

        // Trim and split doc comment; most parsing is done per-line.
        $comment = $reflector->getDocComment();
        $comment = preg_replace(self::$commentTrims, '', $comment);
        $lines = preg_split('/[\r\n]/', $comment);

        // Loop state variables.
        list($idx, $tag) = array(0, '@note');

        while (count($lines)) {
            $_ = array_shift($lines);

            // Trim line.
            $_ = preg_replace('/^[[:blank:]]*\*([[:blank:]]|)/', '', $_);
            $_ = preg_replace('/[[:space:]]*$/', '', $_);

            // Entering a new occurrence of a tag:
            $isTag = '/^(' . implode('|', self::$tags) . ')(\s|)/';
            if (preg_match($isTag, $_, $match)) {
                isset($struct[$tag][$idx]) 
                    and $struct[$tag][$idx]->analyzeText();
                $tag = $match[1];
                $idx = count($struct[$tag]);
                $_ = preg_replace($isTag, '', $_);
            }

            if (!isset($struct[$tag][$idx])) { 
                $struct[$tag][$idx] = new SimpleDocumenterTag($tag);
            }
            elseif (strlen($struct[$tag][$idx]->text)) {
                $_ = "\n$_"; // Replace horz. space from preg_split.
            }

            $struct[$tag][$idx]->text = $struct[$tag][$idx]->text . $_;
            count($lines) < 2 and $struct[$tag][$idx]->analyzeText();
        }

        return new SimpleDocumenterNode($reflector, $struct);
    }
}

/**
 * A SimpleDocumenterNode encapsulates a Reflection object and the pairs
 * of ( Tag name => SimpleDocumenterTag[] ) from a php doc comment parse.
 *
 * @author Joel Dalley
 * @version 2015/Mar/06
 */
class SimpleDocumenterNode {

    /** @param object $reflector A Reflection(Class|Method|Property) object. */
    private $reflector;

    /**
     * @var array $struct Entries in the array are pairs of 
     *                    ( Tag name => SimpleDocumenterTag[] ).
     */
    private $struct;

    /**
     * @param object $reflector A Reflection(Class|Method|Property) object.
     * @param array $struct Pairs of ( Tag name => SimpleDocumenterTag[] ).
     * @return SimpleDocumenterNode
     */
    public function __construct($reflector, $struct = array()) {
        $this->reflector = $reflector;
        $this->struct = $struct;
    }

    /** @return object A Reflection(Class|Method|Property) object. */
    public function reflector() { return $this->reflector; }

    /**
     * @param string $name A phpdoc tag name, e.g., '@var'.
     * @throws InvalidArgumentException If $name isn't a supported tag name.
     * @return SimpleDocumenterTagList Contains zero or more SimpleDocumenterTags
     *                                 matching the given tag name.
     */
    public function tagList($name) {
        $name = (string) $name;
        if (!isset($this->struct[$name])) {
            throw new InvalidArgumentException("No tag `$name`");
        }

        $refl = $this->reflector();
        return new SimpleDocumenterTagList($refl, $this->struct[$name]);
    }
}

/**
 * A SimpleDocumenterTagList holds a Reflection object and an array of
 * SimpleDocumenterTags. 
 *
 * This object is an iterator, and provides methods like first(), count()
 * and join(), which operate on the internal array of SimpleDocumenterTags. 
 * The method, tags(), returns the internal array.
 * @author Joel Dalley
 * @version 2015/Mar/07
 */
class SimpleDocumenterTagList implements Iterator {

    /** @param object $reflector A Reflection(Class|Method|Property) object. */
    private $reflector;

    /** @var SimpleDocumenterTag[] $tags */
    private $tags = array();

    /**
     * @param object $reflector A Reflection(Class|Method|Property) object.
     * @param SimpleDocumenterTag[] $tags An array of SimpleDocumenterTags.
     *
     * @throws InvalidArgumentException If $tags isn't an array.
     * @throws InvalidArgumentException If any of the entries in $tags
     *                                  aren't SimpleDocumenterTags.
     * @return SimpleDocumenterTagList
     */
    public function __construct($reflector, $tags) {
        $this->reflector = $reflector;

        if (!is_array($tags)) {
            $msg = "Constructor expects the second argument"
                 . " to be an array of SimpleDocumenterTags";
            throw new InvalidArgumentException($msg);
        }

        foreach ($tags as $tag) {
            if (!($tag instanceof SimpleDocumenterTag)) {
                $msg = "The second constructor argument has "
                     . "to be an array of SimpleDocumenterTags.";
                throw new InvalidArgumentException($msg);
            }
            $this->tags[] = $tag;
        }
    }

    /** @return object A Reflection(Class|Method|Property) object. */
    public function reflector() { return $this->reflector; }

    /** 
     * @return SimpleDocumenterTag[] The entire array of SimpleDocumenterTags.
     */
    public function tags() { return $this->tags; }

    /** @return int The number of SimpleDocumenterTags in the array. */
    public function count() { return count($this->tags); }

    /**
     * @return SimpleDocumenterTag|NULL The first SimpleDocumenterTag, 
     *                                  or NULL if the array is empty.
     */
    public function first() { return count($this->tags) ? $this->tags[0] : NULL; }

    /**
     * @param string|NULL $sep A join separator. Default: " ".
     * @return string Joined SimpleDocumenterTags. Note that evaluating a
     *                SimpleDocumenterTag in string context yields its full text.
     */
    public function join($sep = ' ') { return implode($sep, $this->tags); }

    /** 
     * @return SimpleDocumenterTag The object at the iterator cursor position. 
     */
    public function current() { return current($this->tags); }

    /** @return int The array key at the iterator cursor position. */
    public function key() { return key($this->tags); }

    /** 
     * @return SimpleDocumenterTag|NULL The object at the next cursor position. 
     */
    public function next() { return next($this->tags); }

    /** 
     * Rewind the iterator cursor. 
     * @return void
     */
    public function rewind() { return reset($this->tags); }

    /** @return bool TRUE if cursor position is valid, or FALSE. */
    public function valid() { return !is_null(key($this->tags)); }
}

/**
 * Express a single doc comment tag as an object.
 *
 * @author Joel Dalley
 * @version 2015/Mar/05
 * @see https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenterTag {
    const FIELD_TAG = 'tag';
    const FIELD_TEXT = 'text';
    const FIELD_NAME = 'name';
    const FIELD_VALUE = 'value';
    const FIELD_TYPE = 'type';
    const FIELD_NOTE = 'note';

    /** @var array $fields Object fields. */
    private $fields = array(
        self::FIELD_TAG   => '',
        self::FIELD_TEXT  => '',
        self::FIELD_NAME  => '',
        self::FIELD_VALUE => '',
        self::FIELD_TYPE  => '',
        self::FIELD_NOTE  => '',
        );

    /**
     * @param array|string $arg Either a tag name, e.g. '@link', or
     *                          pairs of ( FIELD_x => value ).
     * @return SimpleDocumenterTag
     */
    public function __construct($arg) {
        if (is_array($arg)) {
            foreach ($arg as $field => $value) { 
                $this->$field = $value; 
            }
        }
        elseif (is_string($arg)) {
            $this->tag = $arg; 
        }
    }

    /**
     * @param string $field A FIELD_x field name.
     * @return string The field value. Default is empty string.
     */
    public function __get($field) {
        $has = array_key_exists($field, $this->fields);
        return $has ? $this->fields[$field] : '';
    }

    /** 
     * Side-effect on the given object field.
     * @param string $field A FIELD_x field name.
     * @param mixed $value The value to set into $field.
     * @return void
     */
    public function __set($field, $value) {
        $has = array_key_exists($field, $this->fields);
        $has and $this->fields[$field] = (string) $value;
    }

    /** @return string The value in the FIELD_TEXT field. */
    public function __toString() { return $this->text; }

    /**
     * Side-effect on object's properties, per the analysis.
     * Analyze the object's text, looking for pieces within certain tags.
     * For instance, '@param' might have a type, a name and a descriptive note.
     * @return void
     */
    public function analyzeText() {
        $config = array('@param', '@var', '@return', '@throws');

        if (is_null($this->text) || !in_array($this->tag, $config)) {
            return;
        }

        // Try to find (type, name, note), from tag text.
        $patterns = array(
            '/^\s*([\\\\\w\|\[\]]+)\s+(\&?\$\w+)\s?(.*)/s' => function($m) {
                return array($m[1], $m[2], $m[3]);
            },
            '/^\s*([\\\\\w\|\[\]]+)\s?(.*)/s' => function($m) {
                return array($m[1], NULL, $m[2]);
            },
            '/^\s*(\&?\$\w+)\s?(.*)/s' => function($m) {
                return array(NULL, $m[1], $m[2]);
            });

        foreach ($patterns as $pat => $sub) {
            // On match, save parse to object fields & stop looking.
            if (preg_match($pat, $this->text, $match)) {
                list($this->type, $this->name, $this->note) = $sub($match);
                return;
            }
        }
    }
}

/**
 * SimpleDocumenter-related utility functions.
 * @author Joel Dalley
 * @version 2015/Mar/07
 */
class SimpleDocumenterUtil {
    /**
     * @param array|SimpleDocumenterTagList $list The list to be filtered.
     * @param callable|NULL $callable A filtering function, or NULL.
     * @return array|SimpleDocumenterTagList The given list, which may have
     *                                       been filtered by $callable.
     */
    public static function filter($list, $callable = NULL) {
        if (!is_callable($callable)) { 
            return $list; 
        }

        $filtered = array();
        foreach ($list as $idx => $entry) {
            $callable($entry) and $filtered[$idx] = $entry; 
        }
        return $list instanceof SimpleDocumenterTagList
             ? new SimpleDocumenterTagList($list->reflector(), $filtered)
             : $filtered;
    }
}
