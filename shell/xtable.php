#!/usr/bin/env php
<?php
$dir = realpath(dirname($_SERVER['PHP_SELF'])."/../");
$basename = basename($dir);
if($basename == 'vendor') {
    require_once($dir . "/autoload.php");
}
else{
    require_once($dir."/vendor/autoload.php");
}
$opts = array();

$longopts = array(
    "create",
    "alter",
    "drop"
);

$options = getopt("f:",$longopts);

$files = array();
$since = 0;
if(isset($options['f'])){
    $file = $options['f'];
    if(file_exists($file)){
        $files[] = $file;
    }
}
else{
    $len = count($argv);
    $tmp = array();
    for($i = $len - 1; $i > 0; $i--){
        $file = $argv[$i];
        if(file_exists($file)){
            $tmp[] = $file;
        }
    }
    $files = array_reverse($tmp);
}



$types = array();

if(isset($options['drop'])){
    $types[] = "drop";
}

if(isset($options['create'])){
    $types[] = "create";
}
else if(isset($options['alter'])){
    $types[] = "alter";
}
$ddl = array();
foreach($files as $file) {
    if (is_file($file) && file_exists($file)) {
        $builder = new coltware\xtable\type\MySQLBuilder($opts);
        $table = coltware\xtable\XMLFactory::parseFile($file, $builder);

        if (in_array('drop',$types)){
            $ddl[] = $builder->dropTable();
        }
        if (in_array('alter',$types)) {
            $ddl[] = $builder->alterTable($since);
        }
        else{
            $ddl[] = $builder->createTable($since);
        }
    }
}
print join(PHP_EOL,$ddl);
print PHP_EOL;

?>
