<?php
/**
 * The World's simplest phpdoc comment analyzer.
 *
 * @author Joel Dalley
 * @version 2015/Mar/05
 * @link https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenter {
    const CLASSNODE  = 1;
    const CONSTANTS  = 2;
    const PROPERTIES = 3;
    const METHODS    = 4;

    /** @var array $tree Structure in which SimpleDocumenterNodes are stored. */
    private $tree = array(               
        self::CLASSNODE  => NULL,
        self::METHODS    => array(),
        self::PROPERTIES => array(),
        self::CONSTANTS  => array()
        );

    /** @var string[] $tags The phpdoc tags SimpleDocumenter processes. */
    private static $tags = array(
        '@access', '@author', '@const', '@example', '@link', '@package',
        '@param', '@return', '@see', '@throws', '@var', '@version',
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
        $this->tree[self::CLASSNODE] = $this->node($refl, $class);

        foreach ($refl->getProperties() as $_) {
            $name = '$' . $_->name;
            $from = $_->getDeclaringClass()->name;
            $this->tree[self::PROPERTIES][$name] = $this->node($_, $from);
        }

        foreach ($refl->getMethods() as $_) {
            $from = $_->getDeclaringClass()->name;
            $this->tree[self::METHODS][$_->name] = $this->node($_, $from);
        }

        // Instead of going through node(), just inline SimpleDocumenterNode
        // assembly right here, for constants. This procedure differs from 
        // the node() procedure, in that contants are much simpler to analyze,
        // except we have to walk the anscestor tree to determine in which 
        // class a given constant is actually defined ("from").
        foreach ($refl->getConstants() as $name => $value) {
            list($temp, $from) = array($refl, $class);
            while ($from == $class && $parent = $temp->getParentClass()) {
                $parentNames = array_keys($parent->getConstants());
                in_array($name, $parentNames) and $from = $parent->name;
                $temp = $parent;
            }
            $pairs = array('@const' => array(new SimpleDocumenterTag(array(
                SimpleDocumenterTag::FIELD_TAG   => '@const',
                SimpleDocumenterTag::FIELD_NAME  => $name,
                SimpleDocumenterTag::FIELD_VALUE => $value,
            ))));
            $node = new SimpleDocumenterNode($refl, $from, $pairs);
            $this->tree[self::CONSTANTS][$name] = $node;
        }
    }

    /** 
     * @return SimpleDocumenterNode A node containing the tags found in
     *                              any doc comment for the class itself.
     */
    public function classNode() { return $this->tree[self::CLASSNODE]; }

    /**
     * @param Closure|NULL $filter Optional filter function, where each filter
     *                             argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                constants, possibly filtered.
     */
    public function constantNodes(Closure $filter = NULL) {
        $nodes = $this->tree[self::CONSTANTS]; 
        return SimpleDocumenterUtil::filter($nodes, $filter);
    }

    /**
     * @param Closure|NULL $filter Optional filter function, where each filter
     *                             argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                properties, possibly filtered.
     */
    public function propertyNodes(Closure $filter = NULL) { 
        $nodes = $this->tree[self::PROPERTIES]; 
        return SimpleDocumenterUtil::filter($nodes, $filter);
    }

    /**
     * @param Closure|NULL $filter Optional filter function, where each filter
     *                             argument is a SimpleDocumenterNode.
     * @return SimpleDocumenterNode[] Nodes containing the parsed class's 
     *                                methods, possibly filtered.
     */
    public function methodNodes(Closure $filter = NULL) {
        $nodes = $this->tree[self::METHODS];
        return SimpleDocumenterUtil::filter($nodes, $filter);
    }

    /**
     * @param object $reflector A Reflection(Class|Method|Property) object.
     * @param string $from The class in which the node object is defined.
     * @return SimpleDocumenterNode A node containing all of the parsed phpdoc
     *                              tags from the given Reflection object's
     *                              getDocComment() string.
     */
    private function node($reflector, $from) {
        // Initialize pairs of (Tag name => array()), which will
        // constitute the internal data structure of a SimpleDocumenterNode.
        $keys = array_merge(self::$tags, array('@note'));
        $pairs = array_combine($keys, array_fill(0, count($keys), array()));

        // Trim and split doc comment; most parsing is done per-line.
        $lines = preg_split('/[\r\n]/', preg_replace(array(
            '/^[[:space:]]*\/\*\*[[:space:]]*/',
            '/^[[:space:]]*\*\/[[:space:]]*$/',
            '/^[[:space:]]*\/\*\*$/',
            '/\*\/[[:space:]]*$/'
        ), '', $reflector->getDocComment()));

        // While loop variables.
        list($idx, $tag) = array(0, '@note');
        $regex = '/^(' . implode('|', self::$tags) . ')(\s|)/';

        while (count($lines)) {
            $_ = array_shift($lines); // Single line; trim some more...
            $_ = preg_replace('/^[[:blank:]]*\*([[:blank:]]|)/', '', $_);
            $_ = preg_replace('/[[:space:]]*$/', '', $_);

            // Look for a new tag, & udpate state vars, upon entering one.
            if (preg_match($regex, $_, $match)) {
                isset($pairs[$tag][$idx]) and $pairs[$tag][$idx]->analyze();
                $tag = $match[1];
                $idx = count($pairs[$tag]);
                $_ = preg_replace($regex, '', $_);
            }

            if (!isset($pairs[$tag][$idx])) {
                $pairs[$tag][$idx] = new SimpleDocumenterTag($tag);
            }
            elseif (strlen($pairs[$tag][$idx]->text)) {
                $_ = "\n$_"; // Replace horz. space from preg_split.
            }

            $pairs[$tag][$idx]->text = $pairs[$tag][$idx]->text . $_;
            count($lines) < 2 and $pairs[$tag][$idx]->analyze();
        }

        return new SimpleDocumenterNode($reflector, $from, $pairs);
    }
}

/**
 * Encapsulates a Reflection object, the class in which the node object
 * was defined, and the pairs of ( Node name => SimpleDocumenterTags[] )
 * which embody a parsed php doc comment.
 *
 * @author Joel Dalley
 * @version 2015/Mar/06
 * @link https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenterNode {

    /** @param string $from The class in which the node object was defined. */
    private $from;

    /** @param object $reflector A Reflection(Class|Method|Property) object. */
    private $reflector;

    /**
     * @var array $pairs Entries in the array are pairs of 
     *                    ( Tag name => SimpleDocumenterTag[] ).
     */
    private $pairs;

    /**
     * @param object $reflector A Reflection(Class|Method|Property) object.
     * @param string $from The class in which the node object was defined.
     * @param array $pairs Pairs of ( Tag name => SimpleDocumenterTag[] ).
     * @return SimpleDocumenterNode
     */
    public function __construct($reflector, $from, $pairs) {
        $this->reflector = $reflector;
        $this->from = $from;
        $this->pairs = $pairs;
    }

    /** @return object A Reflection(Class|Method|Property) object. */
    public function reflector() { return $this->reflector; }

    /** @return string The class in which the node object was defined. */
    public function from() { return $this->from; }

    /**
     * @param string $name A phpdoc tag name, e.g., '@var'.
     * @throws InvalidArgumentException If $name isn't a supported tag name.
     * @return SimpleDocumenterTagList Contains zero or more SimpleDocumenterTags
     *                                 matching the given tag name.
     */
    public function tagList($name) {
        $name = (string) $name;
        if (!isset($this->pairs[$name])) {
            throw new InvalidArgumentException("No tag `$name`");
        }

        $refl = $this->reflector();
        return new SimpleDocumenterTagList($refl, $this->pairs[$name]);
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
 * @link https://github.com/joeldalley/php-SimpleDocumenter
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
 * @link https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenterTag {
    const FIELD_TAG   = 'tag';
    const FIELD_TEXT  = 'text';
    const FIELD_NAME  = 'name';
    const FIELD_VALUE = 'value';
    const FIELD_TYPE  = 'type';
    const FIELD_NOTE  = 'note';

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
     * @return string The field value. The default is an empty string.
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
    public function analyze() {
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
 * @link https://github.com/joeldalley/php-SimpleDocumenter
 */
class SimpleDocumenterUtil {
    /**
     * @param array|SimpleDocumenterTagList $list The list to be filtered.
     * @param Closure|NULL $filter A filtering function, or NULL.
     * @return array|SimpleDocumenterTagList The given list, which may have
     *                                       been filtered by $filter.
     */
    public static function filter($list, Closure $filter = NULL) {
        if (!is_null($filter)) {
            $filtered = array();
            foreach ($list as $idx => $entry) {
                $filter($entry) and $filtered[$idx] = $entry; 
            }
            $list = $list instanceof SimpleDocumenterTagList
                  ? new SimpleDocumenterTagList($list->reflector(), $filtered)
                  : $filtered;
        }
        return $list;
    }
}
