<?php
namespace coltware\xtable\type;

abstract class AbstractBuilder {

    protected $table_name;
    protected $table_attrs;

    protected $fields;
    protected $pkeys;

    protected $options = array(
        'comment' => true
    );

    public function __construct($opts = null){
        if(is_array($opts)){
            $this->options = array_merge($opts,$this->options,$opts);
        }
    }

    public function setTableName($table){
        $this->table_name = $table;
    }

    public function setTableAttrs($attrs){
        $this->table_attrs = $attrs;
    }

    public function setPrimaryKeys($keys){
        $this->pkeys = $keys;
    }

    public function getOption($name,$def = null){
        if(isset($this->options[$name])){
            return $this->options[$name];
        }
        else{
            return $def;
        }
    }

    public abstract function addField($field_name,$field,$attrs);

    public abstract function addIndex($attrs,$fields);

    public abstract function toDDL();

    public abstract function alterTable($from,$to = null);
} 