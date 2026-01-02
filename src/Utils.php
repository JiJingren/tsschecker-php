<?php

class Plist {
    // 类型
    const PLIST_BOOLEAN = 'boolean';
    const PLIST_UINT = 'integer';
    const PLIST_REAL = 'real';
    const PLIST_STRING = 'string';
    const PLIST_ARRAY = 'array';
    const PLIST_DICT = 'dict';
    const PLIST_DATE = 'date';
    const PLIST_DATA = 'data';
    const PLIST_KEY = 'key';
    const PLIST_UID = 'uid';

    // libplist 节点结构
    public $type;
    public $value;
    public $children = []; // 用于字典/数组

    public function __construct($type, $value = null) {
        $this->type = $type;
        $this->value = $value;
    }

    public static function new_dict() {
        return new Plist(self::PLIST_DICT);
    }

    public static function new_array() {
        return new Plist(self::PLIST_ARRAY);
    }

    public static function new_string($val) {
        return new Plist(self::PLIST_STRING, $val);
    }

    public static function new_bool($val) {
        return new Plist(self::PLIST_BOOLEAN, $val);
    }

    public static function new_uint($val) {
        return new Plist(self::PLIST_UINT, $val);
    }

    public static function new_data($val) {
        return new Plist(self::PLIST_DATA, $val);
    }

    public static function dict_set_item($dict, $key, $item) {
        if ($dict->type !== self::PLIST_DICT) return;
        $dict->children[$key] = $item;
    }

    public static function dict_get_item($dict, $key) {
        if ($dict->type !== self::PLIST_DICT) return null;
        return isset($dict->children[$key]) ? $dict->children[$key] : null;
    }
    
    public static function dict_remove_item($dict, $key) {
        if ($dict->type !== self::PLIST_DICT) return;
        unset($dict->children[$key]);
    }

    public static function array_append_item($array, $item) {
        if ($array->type !== self::PLIST_ARRAY) return;
        $array->children[] = $item;
    }
    
    public static function array_get_item($array, $index) {
        if ($array->type !== self::PLIST_ARRAY) return null;
        return isset($array->children[$index]) ? $array->children[$index] : null;
    }

    public static function copy($node) {
        if (!$node) return null;
        $new = new Plist($node->type, $node->value);
        foreach ($node->children as $k => $child) {
            $new->children[$k] = self::copy($child);
        }
        return $new;
    }

    public static function to_xml($node) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        $imp = new DOMImplementation();
        $doctype = $imp->createDocumentType('plist', 
            '-//Apple//DTD PLIST 1.0//EN', 
            'http://www.apple.com/DTDs/PropertyList-1.0.dtd');
        $dom->appendChild($doctype);
        
        $plist = $dom->createElement('plist');
        $plist->setAttribute('version', '1.0');
        $dom->appendChild($plist);
        
        $plist->appendChild(self::node_to_xml($dom, $node));
        
        return $dom->saveXML();
    }

    private static function node_to_xml($dom, $node) {
        switch ($node->type) {
            case self::PLIST_DICT:
                $el = $dom->createElement('dict');
                foreach ($node->children as $key => $child) {
                    $el->appendChild($dom->createElement('key', $key));
                    $el->appendChild(self::node_to_xml($dom, $child));
                }
                return $el;
            case self::PLIST_ARRAY:
                $el = $dom->createElement('array');
                foreach ($node->children as $child) {
                    $el->appendChild(self::node_to_xml($dom, $child));
                }
                return $el;
            case self::PLIST_STRING:
                return $dom->createElement('string', $node->value);
            case self::PLIST_UINT:
                return $dom->createElement('integer', $node->value);
            case self::PLIST_BOOLEAN:
                return $dom->createElement($node->value ? 'true' : 'false');
            case self::PLIST_DATA:
                return $dom->createElement('data', base64_encode($node->value));
            default:
                return $dom->createElement('string', '');
        }
    }

    public static function from_xml($xml) {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $root = $xpath->query('/plist/*')->item(0);
        return self::xml_to_node($root);
    }

    private static function xml_to_node($el) {
        if (!$el) return null;
        switch ($el->nodeName) {
            case 'dict':
                $node = self::new_dict();
                $child = $el->firstChild;
                $key = null;
                while ($child) {
                    if ($child->nodeType !== XML_ELEMENT_NODE) {
                        $child = $child->nextSibling;
                        continue;
                    }
                    if ($child->nodeName === 'key') {
                        $key = $child->textContent;
                    } else {
                        if ($key !== null) {
                            $node->children[$key] = self::xml_to_node($child);
                            $key = null;
                        }
                    }
                    $child = $child->nextSibling;
                }
                return $node;
            case 'array':
                $node = self::new_array();
                $child = $el->firstChild;
                while ($child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $node->children[] = self::xml_to_node($child);
                    }
                    $child = $child->nextSibling;
                }
                return $node;
            case 'string':
                return self::new_string($el->textContent);
            case 'integer':
                return self::new_uint($el->textContent);
            case 'true':
                return self::new_bool(true);
            case 'false':
                return self::new_bool(false);
            case 'data':
                // 移除空白字符
                $data = preg_replace('/\s+/', '', $el->textContent);
                return self::new_data(base64_decode($data));
            default:
                return self::new_string($el->textContent);
        }
    }
}
