<?php
require_once __DIR__ . '/../config/config.conn.php';
require_once __DIR__ . '/../helper/auth.helper.php';
require_api_login(['admin']);
audit_log($conn, 'unduh_backup', 'database', DB_NAME);
$filename = 'simpel_cbt_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$tables=[];$q=mysqli_query($conn,'SHOW TABLES');while($q&&$r=mysqli_fetch_row($q))$tables[]=$r[0];
foreach($tables as $table){
    $safe='`'.str_replace('`','``',$table).'`';
    $create=mysqli_query($conn,"SHOW CREATE TABLE $safe");$row=$create?mysqli_fetch_row($create):null;
    if(!$row)continue;
    echo "DROP TABLE IF EXISTS $safe;\n".$row[1].";\n";
    $data=mysqli_query($conn,"SELECT * FROM $safe");
    while($data&&$record=mysqli_fetch_assoc($data)){
        $cols=[];$vals=[];
        foreach($record as $col=>$value){$cols[]='`'.str_replace('`','``',$col).'`';$vals[]=$value===null?'NULL':"'".mysqli_real_escape_string($conn,$value)."'";}
        echo "INSERT INTO $safe (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n";
    }
    echo "\n";
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
