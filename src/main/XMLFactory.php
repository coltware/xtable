<?php
/**
 *
 * @author coltware@gmail.com
 */
namespace coltware\xtable;

use \PDO as PDO;
use \coltware\xtable\type\AbstractBuilder;

class XMLFactory
{
    /**
     * @param $xml_file
     * @return Table
     * @throws \RuntimeException
     */
    public static function parseFile($xml_file,AbstractBuilder $builder = null){
        if(!file_exists($xml_file)){
            throw new \RuntimeException(sprintf("Not Found [%s] file",$xml_file));
        }
        $dom = simplexml_load_file($xml_file);
        return XMLFactory::parseDom($dom,$builder);
    }

    /**
     * @param \SimpleXMLElement $dom
     * @return Table
     */
    public static function parseDom(\SimpleXMLElement $dom,AbstractBuilder $builder = null){

        $table = new Table();
        $root_attrs = $dom->attributes();

        $_table_attrs = array();
        foreach($root_attrs as $key => $val){
            $key = strtolower($key);
            $val = (string)$val;
            switch($key){
                case 'name':
                    $table->setTableName($val);
                    break;
                default:
                    $_table_attrs[$key] = $val;
                    break;
            }
        }

        $nodes = $dom->children();
        foreach($nodes as $node){
            $name = $node->getName();
            $name = strtolower($name);
            if($name == 'fields'){
                $field_list = $node->xpath("field");
                foreach($field_list as $field) {
                    XMLFactory::parse_field($table, $field, $builder);
                }
            }
            else if($name == 'index'){
                if($builder){
                    $index_children = $node->children();
                    $index_attrs = $node->attributes();
                    $_attrs = array();
                    $_idx   = array();
                    foreach($index_attrs as $key => $val){
                        $key = strtolower($key);
                        $_attrs[$key] = (string)$val;
                    }

                    foreach($index_children as $idx){
                        $fname = (string)$idx;
                        $_idx[] = $fname;
                    }

                    $builder->addIndex($_attrs,$_idx);

                }
            }
            else{
                if($builder){
                    if($name == 'comment'){
                        $_table_attrs['comment'] = (string)$node;
                    }
                }
            }
        }
        if($builder){
            $builder->setTableName($table->getTableName());
            $builder->setTableAttrs($_table_attrs);
            $builder->setPrimaryKeys($table->getPrimaryKeys());
        }
        return $table;
    }

    protected static function parse_field(Table $table, \SimpleXMLElement $field,AbstractBuilder $builder = null){
        $attrs = $field->attributes();
        $field_name = "";

        $field_type = "";
        $is_primary = false;
        $has_default = false;

        $size = 0;
        $required = false;

        $_attrs = array();

        foreach($attrs as $key => $val) {
            $key = strtolower($key);
            $val = (string)$val;

            $_attrs[$key] = $val;

            switch ($key) {
                case 'name':
                    $field_name = $val;
                    break;
                case 'type':
                    $field_type = XMLFactory::_detect_field_type($val);
                    break;
                case 'size':
                    if(ctype_digit($val)) {
                        $size = $val;
                    }
                    break;
                case 'required':
                    $val = strtolower($val);
                    if($val == '1' || substr($val,0,1) == 't'){
                        $required = true;
                    }
                    break;
                case 'primary':
                    $is_primary = true;
                    break;
                case 'default':
                    $has_default = true;
                    break;
            }
        }

        if($field_type){
            switch($field_type['type']){
                case 'serial':
                    $is_primary = true;
                    break;
                case 'string':
                    if($size){
                        $field_type['size'] = $size;
                    }
            }
        }

        if($field_name){
            $f = array(
                'type' => $field_type,
                'required' => $required,
                'has_default' => $has_default
            );
            $table->addField($field_name,$f);
            if($is_primary) {
                $table->addPrimaryKey($field_name);
            }

            if($builder){
                $comment = $field->xpath("comment[1]");
                if(count($comment)){
                    $_attrs['comment'] = (string)$comment[0];
                }
                $builder->addField($field_name,$f,$_attrs);
            }
        }
    }

    private static function _detect_field_type($val){
        $val = strtolower($val);
        switch($val){
            case 'auto':
            case 'auto_increment':
            case 'serial':
                return array('type' => 'serial', 'pdo' => PDO::PARAM_INT);
            case 'string':
            case 'varchar':
            case 'char':
            case 'character':
                return array('type' => 'string', 'pdo' => PDO::PARAM_STR);
            case 'text':
                return array('type' => 'text', 'pdo' => PDO::PARAM_STR);
            case 'decimal' :
                return array('type' => 'decimal', 'pod' => PDO::PARAM_STR);
            case 'numeric' :
                return array('type' => 'numeric', 'pod' => PDO::PARAM_STR);
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'enum':
                return array('type' => 'integer', 'pdo' => PDO::PARAM_INT);
            case 'bigint':
                return array('type' => 'long', 'pdo' => PDO::PARAM_INT);
            case 'bool':
            case 'boolean':
                return array('type' => 'bool', 'pdo' => PDO::PARAM_INT);
            case 'date':
                return array('type' => 'date', 'pdo' => PDO::PARAM_INT);
            case 'datetime':
            case 'timestamp':
                return array('type' => 'datetime','pdo' => PDO::PARAM_STR);
            default:
                return null;
        }
    }
}