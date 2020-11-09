<?php
namespace coltware\xtable\type;

class MySQLBuilder extends AbstractBuilder{

    protected $index_list;
    private $auto_increment = 0;

    public function __construct($opts = null){
        parent::__construct($opts);
        $this->fields = array();
        $this->index_list = array();
    }

    public function addIndex($attr,$fields){

        $name = isset($attr['name'])? $attr['name'] :null;

        $this->index_list[] = array(
            "name"      => $name,
            "fields"    => $fields,
        );
    }

    public function addField($name,$field,$attrs){

        $field_line = array();
        $type       = $field['type'];

        switch($type['type']){
            case 'serial':
                $f_type = "BIGINT UNSIGNED";
                if(isset($attrs['start']) && ctype_digit($attrs['start'])){
                    $this->auto_increment = $attrs['start'];
                }
                break;
            case 'string':
                $f_type = sprintf("%s(%s)","varchar",$attrs['size']);
                break;
            case 'text':
                $f_type = "TEXT";
                break;
            case 'integer':
                $f_type = "INTEGER";
                break;
            case 'bigint':
            case 'long':
                $f_type = "BIGINT";
                break;
            case 'tinyint':
                $f_type = "TINYINT";
                break;
            case 'smallint':
                $f_type = "SMALLINT";
                break;
            case 'decimal':
                $f_type = sprintf("%s(%s)","DECIMAL",$attrs['size']);
                break;
            case 'numeric':
                $f_type = sprintf("%s(%s)","NUMERIC",$attrs['size']);
                break;
            case 'bool':
                $f_type = "TINYINT(1)";
                break;
            case 'date':
                $f_type = "DATE";
                break;
            case 'datetime':
                $f_type = "DATETIME";
                break;
            case 'blob':
            case 'mediumblob':
                $f_type = strtoupper($type['type']);
                break;
            case 'json':
                $f_type = "JSON";
                break;
            default:
                var_dump(array($name,$field,$type));
                break;
        }

        $field_line[] = str_pad(sprintf('`%s`',$name),15," ");

        $field_line[] = str_pad($f_type,12," ");

        if(isset($attrs['unique'])){
            $unique = substr(strtolower($attrs['unique']),0,1);
            if($unique == '1' || $unique == 't'){
                $field_line[] = "UNIQUE";
            }
        }

        if(isset($attrs['charset'])){
            $field_line[] = sprintf("CHARACTER SET %s",$attrs['charset']);
        }

        $def = isset($attrs['default'])? $attrs['default']: null;
        if($def !== '' && $def !== null){
            if($type['type'] == 'string'){
                $field_line[] = sprintf("DEFAULT '%s'",$def);
            }
            else if($type['type'] == 'bool'){
                $def = substr(strtolower($def),0,1);
                if($def === 't' || $def === '1'){
                    $field_line[] = "DEFAULT 1";
                }
                else{
                    $field_line[] = "DEFAULT 0";
                }
            }
            else if($type['type'] == 'datetime'){
                $def = strtolower($def);
                if($def == 'now()'){
                    $def = "CURRENT_TIMESTAMP";
                }
                $field_line[] = "DEFAULT ".$def;
            }
            else{
                $field_line[] = "DEFAULT ".$def;
            }
        }

        if($field['required']) {
            $field_line[] = "NOT NULL";
        }
        if($type['type'] == 'serial'){
            $field_line[] = "AUTO_INCREMENT";
        }

        if(isset($attrs['comment'])){
            $field_line[] = sprintf("COMMENT '%s'",$attrs['comment']);
        }

        $since = isset($attrs['since'])? $attrs['since'] : 1;
        $this->fields[$name] = array(
            'sql'   => join(" ",$field_line),
            'since' => $since
        );
    }

    public function toDDL($drop = false){
        $sql  = array();
        if($drop){
            $sql[] = $this->dropTable();
        }
        $sql[] = $this->createTable();
        return join(PHP_EOL,$sql);
    }

    public function createTable($version = null){
        if($version == null){
            $version = PHP_INT_MAX;
        }
        $ddl = array();

        $ddl[] = sprintf("CREATE TABLE `%s`(",$this->table_name);
        $line = array();

        $target = array();

        foreach($this->fields as $name => $field){
            if($version >= $field['since']) {
                $line[] = "    ".$field['sql'];
                $target[] = $name;
            }
        }

        if($this->pkeys){
            $line[] = sprintf("    PRIMARY KEY(%s)",join(",",$this->pkeys));
        }

        $ddl[] = join(",".PHP_EOL,$line);


        $table_attrs = array();

        if($this->auto_increment > 0){
            $table_attrs[] = sprintf("AUTO_INCREMENT = %s",$this->auto_increment);
        }

        if(isset($this->table_attrs['mysql-engine'])){
            $table_attrs[] = sprintf("ENGINE=%s",$this->table_attrs['mysql-engine']);
        }
        if(isset($this->table_attrs['charset'])){
            $charset = $this->table_attrs['charset'];
            if(strtolower($charset) == 'utf-8'){
                $charset = 'utf8';
            }
            $table_attrs[] = sprintf(" DEFAULT CHARSET=%s",$charset);
            if(isset($this->table_attrs['collate'])){
                $table_attrs[] = sprintf(" COLLATE=%s",$this->table_attrs['collate']);
            }
        }

        if($this->getOption("comment",true) == true && isset($this->table_attrs['comment'])){
            $table_attrs[] = sprintf("COMMENT = '%s'",$this->table_attrs['comment']);
        }

        $ddl[] = sprintf(")%s;",join(" ",$table_attrs));


        if(count($this->index_list)){
            foreach($this->index_list as $num => $idx){
                $idx_name = $idx['name'];
                if(!$idx_name){
                    $cnt = $num + 1;
                    $idx_name = sprintf("%s_idx%s",$this->table_name,$cnt);
                }
                $_flds = array();
                foreach($idx['fields'] as $f){
                    $cnt = count($f['attrs']);
                    if($cnt == 0) {
                        $_flds[] = sprintf('`%s`', $f['name']);
                    }
                    else{
                        $attrs = $f['attrs'];
                        if(isset($attrs['size'])){
                            $_flds[] = sprintf('`%s`(%s)',$f['name'],$attrs['size']);
                        }
                    }
                }
                $idx_fields = join(",",$_flds);
                $ddl[] = sprintf("ALTER TABLE `%s` ADD INDEX %s(%s);",$this->table_name,$idx_name,$idx_fields);
            }
        }

        return join(PHP_EOL,$ddl);
    }

    public function dropTable(){
        $ddl = sprintf('DROP TABLE IF EXISTS `%s`;',$this->table_name);
        return $ddl;
    }

    public function alterTable($from,$to = null){
        if($to == null){
            $to = isset($this->table_attrs['version'])? $this->table_attrs['version'] : PHP_INT_MAX;
        }
        if($from == $to){
            $from = $from - 1;
        }
        $target = array();
        $ex_field = "";
        foreach($this->fields as $name => $field){
            $since = $field['since'];
            if($since > $from && $to >= $since){
                $target[$name] = array(
                    'before' => $ex_field,
                    'field' => $field
                );
            }
            $ex_field = $name;
        }
        $sql = array();
        foreach($target as $name => $field){
            $sql_field = array();
            $sql_field[] = sprintf('ALTER TABLE `%s`',$this->table_name);
            $sql_field[] = " ADD COLUMN ";
            $sql_field[] = $field['field']['sql'];
            if($field['before']){
                $sql_field[] = sprintf(" AFTER `%s`",$field['before']);
            }
            $sql[] = join(" ",$sql_field).";";
        }
        return join(PHP_EOL,$sql);
    }
}