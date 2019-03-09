<?php

require_once(__DIR__ . '/autoload.php');

$config = require __DIR__ .'/config.php';

try
{
    $category = new \lib\Category($config);

    switch ($argv[1])
    {
        case 'addNode':
            $id = $category->add($argv[2],$argv[3] ?? 0);
            echo 'Node "'.$argv[2].'" has been added with #'.$id . "\n";
            break;
        case 'deleteNode':
            $category->remove($argv[2]);
            echo 'Node #'.$argv[2].' has been deleted' . "\n";
            break;
        case 'renameNode':
            $category->rename($argv[2], $argv[3]);
            echo 'Node #'.$argv[2].' has been renamed' . "\n";
            break;
        case 'nodeUp':
            $category->up($argv[2]);
            echo 'Node #'.$argv[2].' has been moved' . "\n";
            break;
        case 'nodeDown':
            $category->down($argv[2]);
            echo 'Node #'.$argv[2].' has been moved' . "\n";
            break;
        case 'print':
            echo $category;
            break;
        default:
            echo 'USAGE php test-task.php addNode|deleteNode|renameNode|nodeUp|nodeDown|print';
    }
}catch (Exception $e)
{
    echo $e->getMessage() . ' ' . $e->getLine() . "\n";
}
