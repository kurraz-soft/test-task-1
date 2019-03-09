Config
---
Db Schema  
`db_schema.sql`  
Db Config  
`config.php`  
Db Config for unit tests  
`config-test.php`

Run tests
---
`php .\phpunit.phar --bootstrap autoload.php --testdox tests`

USAGE
---
Print tree  
`php ./test-task.php print`  
Add node  
`php ./test-task.php addNode <id> <parent_id=0>`  
Delete node  
`php ./test-task.php deleteNode <id>`  
Rename node  
`php ./test-task.php renameNode <id> <new_title>`  
Node up  
`php ./test-task.php nodeUp <id>`  
Node down  
`php ./test-task.php nodeDown <id>`  
