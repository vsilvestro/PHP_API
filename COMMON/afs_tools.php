<?php

/** @brief Replace first occurrence of @a search by @a replace.
 *
 * Well, you can use @a preg_replace instead of this function but don't forget
 * to escape characters properly and test whether you get better performances or
 * not...
 *
 * @param $search [in] string to look for.
 * @param $replace [in] string to use in place of first @a search found.
 * @param $subject [in] input string to modify.
 *
 * @return @a subject with first occurrence of @a search replaced by @a replace.
 * If @a search is not present in @a subject, then @a subject is returned
 * unmodified.
 */
function str_replace_first($search, $replace, $subject)
{
    $pos = strpos($subject, $search);
    if ($pos === false) {
        return $subject;
    } else {
        return substr($subject, 0, $pos) . $replace . substr($subject, $pos + strlen($search));
    }
}


/** @brief Helpers for DOM elements. */
class DOMNodeHelper
{
    /** @brief Retrieve text from children text nodes.
     *
     * @param $node [in] base node from which XML text nodes are looked for.
     * @param $callbacks [in] array of callbacks (default: empty array). Array
     *        keys should correspond to XML node types, array values are
     *        @c callbacks to be called for specific node names. These callbacks
     *        should accept a @a DOMNode as first parameter.
     *
     * @return merged text from various text nodes.
     *
     * @par Simple example:
     * Input XML document:
     * @verbatim
       <root>
         <child_1>some content</child_1>
         <child_2>other content</child_2>
       </root>
       @endverbatim
     * Let's suppose @a node variable points to @a DOMElement @a child_1. You
     * can extract text content of @a child_1 node by calling:
     * @code
     * assert(DOMNodeHelper::get_text($node) == 'some content');
     * @endcode
     *
     * @par Example with callback:
     * Input XML document:
     * @verbatim
       <root>
         <child_1>some content</child_1>
         <child_2>other <match>specific</match> content</child_2>
       </root>
       @endverbatim
     * Let's suppor @a node variable points to @a DOMElement @a child_2. You
     * can extract text content of @a child_2 node and its child @a match by
     * calling:
     * @code
     * $filter = new FilterNode('match');
     * assert(DOMNodeHelper::get_text($node, array(XML_ELEMENT_NODE => $filter)) == 'other specific content');
     * @endcode
     *
     * If you want some formatting you can define you own @a FilterNode class or
     * use provided one:
     * @code
     * $filter = new BoldFilterNode('match');
     * assert(DOMNodeHelper::get_text($node, array(XML_ELEMENT_NODE => $filter)) == 'other <b>specific</b> content');
     * @endcode
     *
     * If you want different filters, each for different node name, you can
     * provide array of filter instead of just one filter. For example:
     * @code
     * $func_filter = new FilterNode('func');
     * $match_filter = new BoldFilterNode('match');
     * DOMNodeHelper::get_text($node, array(XML_ELEMENT_NODE => array($func_filter, $match_filter)));
     * @endcode
     */
    public static function get_text(DOMNode $node, array $callbacks=array())
    {
        $filter_mgr = new FilterNodeManager();
        $filter_mgr->add_callbacks($callbacks);

        $result = '';
        $children = $node->childNodes;
        for ($i = 0; $i < $children->length; $i++) {
            $child = $children->item($i);
            $type = $child->nodeType;
            if ($filter_mgr->is_managed_type($type)) {
                $result .= $filter_mgr->apply_filter($child);
            } elseif ($type == XML_TEXT_NODE) {
                $result .= $child->textContent;
            }
        }
        return $result;
    }
}


/** @brief Generate node identifier. */
class IdGenerator
{
    /** @brief Generates identifier from XML DOM element.
     * @param $node [in] node for which identifier should be generated.
     * @return node identifier.
     */
    public static function from_node($node)
    {
        return IdGenerator::from_values($node->localName, $node->namespaceURI);
    }

    /** @brief Generates identifier from provided parameters.
     * @param $name [in] local node name.
     * @param $ns_uri [in] XML namespace URI.
     * @return identifier.
     */
    public static function from_values($name, $ns_uri)
    {
        return $name . '{' . $ns_uri . '}';
    }
}

/** @brief Filter node callback manager. */
class FilterNodeManager
{
    private $callbacks = array();

    /** @brief Adds one or more callbacks.
     *
     * This override any existing callback with same identifier.
     * @param $callbacks [in] one callback or array filled with one or more
     *        callbacks of type FilterNode or one of its subclass.
     */
    public function add_callbacks(array $callbacks)
    {
        foreach ($callbacks as $node_type => $callback) {
            $this->callbacks[$node_type] = $this->get_callbacks_with_id($callback);
        }
    }

    /** @brief Checks whether provided node type is managed.
     * @param $node_type [in] node type to check.
     * @return @c True when given node type is managed, @c false otherwise.
     */
    public function is_managed_type($node_type)
    {
        if (array_key_exists($node_type, $this->callbacks)) {
            return true;
        } else {
            return false;
        }
    }

    /** @brief Applies registered callback for given node
     * @param $node [in] node to work on.
     * @return transformed node text or empty string when given node
     *         does not match any registered callback.
     */
    public function apply_filter($node)
    {
        if ($this->is_managed_type($node->nodeType)) {
            foreach ($this->callbacks[$node->nodeType] as $id => $callback) {
                if ($id == IdGenerator::from_node($node)) {
                    return $callback->format_text($node->textContent);
                }
            }
        }
        return '';
    }

    private function get_callbacks_with_id($callbacks)
    {
        $result = array();
        if (is_array($callbacks)) {
            foreach ($callbacks as $callback) {
                $this->update_with_callback($result, $callback);
            }
        } else {
            $this->update_with_callback($result, $callbacks);
        }
        return $result;
    }
    private function update_with_callback(array& $result, FilterNode $callback)
    {
        $result[$callback->get_id()] = $callback;
    }
}


/** @brief Filter node and retrieve node text contents. */
class FilterNode
{
    private $local_name;
    private $ns_uri;

    /** @brief Construct new instance.
     * @param $local_name [in] @a DOMElement local name.
     * @param $ns_uri [in] namespace URI of the @a DOMElement (default: null).
     */
    public function __construct($local_name, $ns_uri=null)
    {
        $this->local_name = $local_name;
        $this->ns_uri = $ns_uri;
    }

    /** @brief Retrieves filter identifier.
     *
     * Internal use only.
     * @return instance identifier.
     */
    final public function get_id()
    {
        return IdGenerator::from_values($this->local_name, $this->ns_uri);
    }

    /** @brief Checks whether provided node should treated by this filter.
     *
     * Both local name and namespace must match.
     * @param $node [in] @a DOMElement to test.
     * @return @c True when provided node matches this filter, @c false
     * otherwise.
     */
    final public function match($node)
    {
        if ($node->localName == $this->local_name
                && $node->namespaceURI == $this->ns_uri) {
            return true;
        } else {
            return false;
        }
    }

    /** @brief Format text node content.
     *
     * Default implementation does nothing. You can extends this class and
     * overload this method to do specific formatting.
     * @param $text [in] text node content.
     * @return @a text as is.
     */
    public function format_text($text)
    {
        return $text;
    }
}


/** @brief Filter node and retrieve text node content between bold HTML tags. */
class BoldFilterNode extends FilterNode
{
    public function __construct($local_name, $ns_uri=null)
    {
        parent::__construct($local_name, $ns_uri);
    }

    /** @brief Embrase text node content between bold HTML tags.
     * @param $text [in] text node content.
     * @return @verbatim'<b>' . $text . '</b>'@endverbatim
     */
    final public function format_text($text)
    {
        return '<b>' . $text . '</b>';
    }
}

/** @brief Filters node and returns three dots. */
class TruncatedFilterNode extends FilterNode
{
    public function __construct($local_name, $ns_uri=null)
    {
        parent::__construct($local_name, $ns_uri);
    }

    /** @brief Replace tag by three dots.
     * @param $text [in] ignored
     * @return @c ...
     */
    final public function format_text($text)
    {
        return '...';
    }
}


/** @brief Base class for pseudo enumerators.
 *
 * You whould derive from this class to create your own pseudo enum. */
abstract class BasicEnum {

    private static function get_constants()
    {
        $reflect = new ReflectionClass(get_called_class());
        return $reflect->getConstants();
    }

    /** @brief Checks whether provided variable name is valid.
     * @param $name [in] variable name to test.
     * @return @c True on valid name, @c false otherwise.
     */
    public static function is_valid_name($name)
    {
        $constants = self::get_constants();
        return array_key_exists($name, $constants);
    }

    /** @brief Checks whether provided variable value is valid.
     * @param $value [in] variable value to test.
     * @return @c True on valid value, @c false otherwise.
     */
    public static function is_valid_value($value)
    {
        $values = array_values(self::get_constants());
        return in_array($value, $values, $strict=true);
    }

    /** @brief Checks whether the value is valid.
     *
     * @param $value [in] value to test.
     * @param $msg [in] message of the launched exception on failling test.
     *
     * @exception InvalidArgumentException when provided value is invalid.
     */
    public static function check_value($value, $msg=null)
    {
        if (! self::is_valid_value($value)) {
            if (is_null($msg)) {
                $msg = 'Invalid value: ';
            }
            throw new InvalidArgumentException($msg . $value);
        }

    }
}


