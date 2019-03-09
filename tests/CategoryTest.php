<?php
/**
 * Created by PhpStorm.
 * User: Kurraz
 */

class CategoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PDO
     */
    private static $db;
    /**
     * @var \lib\Category
     */
    private static $category;
    private static $config;

    public static function setUpBeforeClass()
    {
        $config = require __DIR__ .'/../config-test.php';
        self::$config = $config;

        self::$db = \lib\Db::connect($config);

        self::$category = new \lib\Category($config);
    }

    public static function tearDownAfterClass()
    {
        self::$db = null;
        self::$category = null;
    }

    protected function setUp()
    {
        $this->cleanUp();
    }

    private function cleanUp()
    {
        self::$db->query('TRUNCATE TABLE `'.self::$config['table_name'].'`');
    }

    private function fillStartData()
    {
        self::$category->add('ROOT');
        self::$category->add('Node2', 1);
        self::$category->add('Node3', 1);
        self::$category->add('Node4', 2);
        self::$category->add('Node5', 2);
        self::$category->add('Node6', 3);
        self::$category->add('Node7', 3);
        self::$category->add('Node8', 3);
        self::$category->add('Node9', 1);
        self::$category->add('Node10', 1);
        self::$category->add('Node11', 10);

        /*
         * Start Tree
          '*1',
          '**2',
          '***4',
          '***5',
          '**3',
          '***6',
          '***7',
          '***8',
          '**9',
          '**10',
          '***11',
         */

        //echo self::$category;
    }

    private function moveSingleNodeUp($node, $expected_position)
    {
        $this->fillStartData();

        self::$category->up($node['id']);

        $items = self::$category->findAll();

        $this->assertArraySubset($node, $items[$expected_position]);
    }

    private function moveSingleNodeDown($node, $expected_position)
    {
        $this->fillStartData();

        self::$category->down($node['id']);

        $items = self::$category->findAll();

        $this->assertArraySubset($node, $items[$expected_position]);
    }

    private function getDataInLeveledArray()
    {
        $items = self::$category->findAll();
        $out = [];
        foreach ($items as $item)
        {
            $out[] = str_repeat('*',$item['lvl']) . $item['id'];
        }

        return $out;
    }

    public function testAddNode()
    {
        $id = self::$category->add('node');

        $this->assertEquals(self::$category->findById($id)['title'], 'node');
    }

    public function testRenameNode()
    {
        $id = self::$category->add('node');
        self::$category->rename($id, 'node_new');

        $this->assertEquals(self::$category->findById($id)['title'], 'node_new');
    }

    public function testDeleteSingleNode()
    {
        $expected = [
          '*1',
          '**2',
          '***5',
          '**3',
          '***6',
          '***7',
          '***8',
          '**9',
          '**10',
          '***11',
        ];

        $this->fillStartData();

        self::$category->remove(4);
        $this->assertEquals($expected, $this->getDataInLeveledArray());
    }

    public function testDeleteNodeWithChildren()
    {
        $expected = [
          '*1',
          '**2',
          '***4',
          '***5',
          '**9',
          '**10',
          '***11',
        ];

        $this->fillStartData();

        self::$category->remove(3);
        $this->assertEquals($expected, $this->getDataInLeveledArray());
    }

    public function testDeleteRoot()
    {
        $expected = [
        ];

        $this->fillStartData();

        self::$category->remove(1);
        $this->assertEquals($expected, $this->getDataInLeveledArray());
    }

    public function testMoveUpSingleNodeWithOneSingleNeighbour()
    {
        $expected_position = 2;
        $node = [
            'id' => 5,
            'lvl' => 3,
        ];

        $this->moveSingleNodeUp($node, $expected_position);
    }

    public function testMoveUpSingleNodeWithManySingleNeighbours()
    {
        $expected_position = 6;
        $node = [
            'id' => 8,
            'lvl' => 3,
        ];

        $this->moveSingleNodeUp($node, $expected_position);
    }

    public function testMoveUpSingleNodeInLastPositionOnLevel()
    {
        $expected_position = 4;
        $node = [
            'id' => 6,
            'lvl' => 2,
        ];

        $this->moveSingleNodeUp($node, $expected_position);
    }

    public function testMoveUpSingleNodeWithNextNeighbourWithChildren()
    {
        $expected_position = 8;
        $node = [
            'id' => 9,
            'lvl' => 3,
        ];

        $this->moveSingleNodeUp($node, $expected_position);
    }

    public function testMoveUpMultipleNodeWithNeighbour()
    {
        $node_id = 3;
        $expected_items = [
            '*1',
            '**2',
            '***4',
            '***5',
            '***3',
            '****6',
            '****7',
            '****8',
            '**9'
        ];

        $this->fillStartData();

        self::$category->up($node_id);

        $items = $this->getDataInLeveledArray();
        $this->assertArraySubset($expected_items, $items);
    }

    public function testMoveDownSingleNodeWithOneSingleNeighbour()
    {
        $node = [
            'id' => 4,
            'lvl' => 3,
        ];
        $expected_position = 3;

        $this->moveSingleNodeDown($node, $expected_position);
    }

    public function testMoveDownSingleNodeWithManySingleNeighbours()
    {
        $expected_position = 6;
        $node = [
            'id' => 6,
            'lvl' => 3,
        ];

        $this->moveSingleNodeDown($node, $expected_position);
    }

    public function testMoveDownSingleNodeInLastPositionOnLevel()
    {
        $expected_position = 3;
        $node = [
            'id' => 5,
            'lvl' => 2,
        ];

        $this->moveSingleNodeDown($node, $expected_position);
    }

    public function testMoveDownSingleNodeWithNextNeighbourWithChildren()
    {
        $expected_position = 9;
        $node = [
            'id' => 9,
            'lvl' => 3,
        ];

        $this->moveSingleNodeDown($node, $expected_position);
    }

    public function testMoveDownMultipleNodeWithNeighbour()
    {
        $node_id = 2;
        $expected_items = [
             '*1',
             '**3',
             '***2',
             '****4',
             '****5',
             '***6',
             '***7',
             '***8',
             '**9',
             '**10',
             '***11',
        ];

        $this->fillStartData();

        self::$category->down($node_id);

        $items = $this->getDataInLeveledArray();
        $this->assertArraySubset($expected_items, $items);
    }
}