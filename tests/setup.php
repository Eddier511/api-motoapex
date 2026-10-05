<?php
declare(strict_types=1);
$c=['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>'motoapex_test','db_user'=>'root','db_password'=>'test-only-password','allowed_origins'=>['https://admin.example.test'],'token_lifetime'=>3600];
file_put_contents(dirname(__DIR__).'/config.local.php',"<?php\nreturn ".var_export($c,true).";\n");
require dirname(__DIR__).'/src/bootstrap.php';
// Remove SQL line comments before splitting statements (comments can contain semicolons).
$schema=preg_replace('/^\s*--.*$/m','',file_get_contents(dirname(__DIR__).'/database/schema.sql'));
foreach (explode(';',$schema) as $statement) {
    if (trim($statement)==='') continue;
    try { db()->exec($statement); }
    catch (PDOException $e) {
        fwrite(STDERR,"Schema statement failed:\n".$statement."\n");
        fwrite(STDERR,query('SHOW ENGINE INNODB STATUS')->fetch()['Status']);
        throw $e;
    }
}
db()->exec(file_get_contents(dirname(__DIR__).'/database/002_api_support.sql'));
db()->exec(file_get_contents(dirname(__DIR__).'/database/003_promotions.sql'));
query('INSERT INTO users (name,email,password_hash,role_id) VALUES (?,?,?,(SELECT id FROM roles WHERE code=?))',['Test','admin@example.test',password_hash('test-password-123456',PASSWORD_DEFAULT),'admin']);
query('INSERT INTO users (name,email,password_hash,role_id) VALUES (?,?,?,(SELECT id FROM roles WHERE code=?))',['Sales','sales@example.test',password_hash('test-password-123456',PASSWORD_DEFAULT),'sales']);
