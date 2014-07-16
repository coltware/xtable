<?php
namespace coltware\xtable;

use coltware\xtable\type\AbstractBuilder as Builder;

class Table {

    private $table_name;
    private $description;

    private $pkeys;
    private $fields;

    public function setTableName($name){
        $this->table_name = $name;
    }

    public function getTableName(){
        return $this->table_name;
    }

    public function setDescription($title){
        $this->description = $title;
    }

    public function setPrimaryKeys($keys){
        $this->pkeys = $keys;
    }
    public function getPrimaryKeys(){
        return $this->pkeys;
    }

    public function addPrimaryKey($key){
        $this->pkeys[] = $key;
    }

    public function setFields($fields){
        $this->fields = $fields;
    }

    public function getFieldList(){
        $keys = array_keys($this->fields);
        return $keys;
    }

    public function addField($field_name,$field){
        $this->fields[$field_name] = $field;
    }

    public function getField($field_name){
        if(isset($this->fields[$field_name])){
            return $this->fields[$field_name];
        }
        return null;
    }
} 