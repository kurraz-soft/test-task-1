<?php
/**
 * Created by PhpStorm.
 * User: Kurraz
 */

namespace lib;

class Category
{
    private $db;
    public $table_name;

    public function __construct($config)
    {
        $this->db= Db::connect($config);
        $this->table_name = $config['table_name'];
    }

    public function __destruct()
    {
        $this->db = null;
    }

    /**
     * @param $id
     * @return array
     * @throws NotFoundException
     */
    public function findById($id)
    {
        $st = $this->db->prepare('SELECT * FROM `'.$this->table_name.'` WHERE id = :id');
        $st->bindParam('id', $id);
        $st->execute();
        $res = $st->fetch();
        if(!$res) throw new NotFoundException('Error, node with id '.$id.' is not found');
        return $res;
    }

    public function add($title, $parent_id = 0)
    {
        $this->db->beginTransaction();

        $parent_level = 0;

        if($parent_id)
        {
            $parent_node = $this->findById($parent_id);
            $right_key = $parent_node['rgt'];
            $parent_level = $parent_node['lvl'];

            $st = $this->db->prepare('UPDATE `'.$this->table_name.'` SET rgt = rgt + 2, lft = IF(lft > :right_key, lft + 2, lft) WHERE rgt >= :right_key2');
            $st->bindParam(':right_key', $right_key);
            $st->bindParam(':right_key2', $right_key);
            $st->execute();
        }else
        {
            $right_key = (int)$this->db->query('SELECT MAX(rgt) FROM `'.$this->table_name.'`')->fetchColumn() + 1;
        }

        $st = $this->db->prepare('INSERT INTO `'.$this->table_name.'` SET lft = :right_key, rgt = :right_key2 + 1, lvl = :level + 1, title = :title');
        $st->bindParam('title', $title);
        $st->bindParam('right_key',$right_key);
        $st->bindParam('right_key2',$right_key);
        $st->bindParam('level', $parent_level);
        $st->execute();
        $id = $this->db->lastInsertId();

        $this->db->commit();

        return $id;
    }

    public function remove($id)
    {
        $node = $this->findById($id);

        $this->db->beginTransaction();

        $st = $this->db->prepare('DELETE FROM `'.$this->table_name.'` WHERE lft >= :left_key AND rgt <= :right_key');
        $st->bindParam('right_key', $node['rgt']);
        $st->bindParam('left_key', $node['lft']);
        $st->execute();

        $st = $this->db->prepare('UPDATE `'.$this->table_name.'` 
            SET lft = IF(lft > :left_key, lft - (:right_key - :left_key2 + 1), lft),
                rgt = rgt - (:right_key2 - :left_key3 + 1) 
            WHERE rgt > :right_key3');

        $st->bindParam('right_key', $node['rgt']);
        $st->bindParam('right_key2', $node['rgt']);
        $st->bindParam('right_key3', $node['rgt']);
        $st->bindParam('left_key', $node['lft']);
        $st->bindParam('left_key2', $node['lft']);
        $st->bindParam('left_key3', $node['lft']);
        $st->execute();

        $this->db->commit();
    }

    public function rename($id, $new_title)
    {
        $node = $this->findById($id);

        $st = $this->db->prepare('UPDATE `'.$this->table_name.'` SET title=:title WHERE id='.$node['id']);
        $st->bindParam('title', $new_title);
        $st->execute();
    }

    private function getParentNode($node)
    {
        //Get parent
        $st = $this->db->query('
          SELECT * 
          FROM `'.$this->table_name.'` 
          WHERE lft <= '.$node['lft'].' AND rgt >= '.$node['rgt'].' AND lvl = '.$node['lvl'].' - 1 
          ORDER BY lft
        ');
        return $st->fetch();
    }

    private function getPrevNodes($node, $parent, $limit = 1)
    {
        if($parent)
        {
            //Get parent node branch without node branch side, only first child parent generation + parent node
            $st = $this->db->query('
              SELECT * 
              FROM `'.$this->table_name.'` 
              WHERE lft >= '.$parent['lft'].' 
                AND rgt <= '.$parent['rgt'].' 
                AND lft < '.$node['lft'].' 
                AND lvl <= '.$node['lvl'].'
              ORDER BY lft DESC LIMIT '.$limit.'
            ');
        }else
        {
             $st = $this->db->query('
              SELECT * 
              FROM `'.$this->table_name.'` 
              WHERE lvl = 1 
                AND lft < '.$node['lft'].' 
                AND lvl = '.$node['lvl'].'
              ORDER BY lft DESC LIMIT '.$limit.'
            ');
        }

        $ret = [];
        while ($res = $st->fetch())
        {
            $ret[] = $res;
        }

        return $ret;
    }

    private function getNextNodes($node, $parent, $limit = 1)
    {
        if($parent)
        {
            //Get parent node branch without node branch side, only first child parent generation + parent node
            $st = $this->db->query('
              SELECT * 
              FROM `'.$this->table_name.'` 
              WHERE lft >= '.$parent['lft'].' 
                AND rgt <= '.$parent['rgt'].' 
                AND rgt > '.$node['rgt'].' 
                AND lvl <= '.$node['lvl'].'
              ORDER BY rgt ASC LIMIT '.$limit.' 
            ');
        }else
        {
             $st = $this->db->query('
              SELECT * 
              FROM `'.$this->table_name.'` 
              WHERE lvl = 1 
                AND rgt > '.$node['rgt'].' 
                AND lvl = '.$node['lvl'].'
              ORDER BY lft DESC LIMIT '.$limit.'
            ');
        }

        $ret = [];
        while ($res = $st->fetch())
        {
            $ret[] = $res;
        }

        return $ret;
    }

    public function up($id)
    {
        $node = $this->findById($id);

        //Get parent
        $parent = $this->getParentNode($node);

        $parent_level = $parent ? $parent['lvl'] : 0;

        list($prev_node, $prev_prev_node) = $this->getPrevNodes($node, $parent, 2);

        $prev_node_has_children = false;
        if($prev_node)
        {
            //check prev_node descendants
            $st = $this->db->query('
                  SELECT id 
                  FROM `'.$this->table_name.'` 
                  WHERE lft >= '.$prev_node['lft'].' 
                    AND rgt <= '.$prev_node['rgt'].' 
                    AND lvl = '.$prev_node['lvl'].' + 1 
                  ORDER BY lft DESC LIMIT 1
                ');
            $res = $st->fetch();
            if($res) $prev_node_has_children = true;
        }else
            throw new \Exception("Error. Can't move up");

        if($prev_node['lvl'] == $node['lvl'] && $prev_node_has_children)
        {
            //Move Level Down to children, attach to right side
            $right_key_near = $prev_node['rgt'] - 1;
            $parent_level = $prev_node['lvl'];
        }elseif($prev_node['lvl'] == $node['lvl'])
        {
            //Switch position with prev node
            if($prev_prev_node['lvl'] != $node['lvl'])
            {
                //Attach to far left side
                $right_key_near = $parent['lft'];
            }else
            {
                $right_key_near = $prev_prev_node['rgt'];
            }
        }else
        {
            //Move Level Up to parent node, attach to left side of parent node
            $parent_prev_node = $this->getParentNode($prev_node);
            list($prev_parent_node) = $this->getPrevNodes($parent, $parent_prev_node);
            $parent_level = $parent_prev_node ? $parent_prev_node['lvl'] : 0;
            if($prev_parent_node['lvl'] == $parent['lvl'])
            {
                //Attach next to parent node
                $right_key_near = $prev_parent_node['rgt'];
            }else
            {
                //Attach to far left side of parent node
                if($parent_prev_node)
                    $right_key_near = $parent_prev_node['lft'];
                else
                {
                    //if ROOT
                    $right_key_near = 0;
                }
            }
        }

        $skew_level = $parent_level - $node['lvl'] + 1;
        $skew_tree = $node['rgt'] - $node['lft'] + 1;

        //Get current node branch ids
        $st = $this->db->query('SELECT id FROM `'.$this->table_name.'` WHERE lft >= '.$node['lft'].' AND rgt <= '.$node['rgt']);
        $ids_edit = [];
        while ($res = $st->fetch())
        {
            $ids_edit[] = $res['id'];
        }

        $skew_edit = $right_key_near - $node['lft'] + 1;

        //Change rest tree
        $this->db->beginTransaction();
        $query = '
            UPDATE `'.$this->table_name.'`
                SET rgt = rgt + '.$skew_tree.'
            WHERE rgt < '.$node['lft'].'
                AND rgt > '.$right_key_near.' 
                AND id NOT IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        $query = '
            UPDATE `'.$this->table_name.'`
                SET lft = lft + '.$skew_tree.'
            WHERE lft < '.$node['lft'].'
                AND lft > '.$right_key_near.' 
                AND id NOT IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        //----------------

        //Change current node
        $query = '
            UPDATE `'.$this->table_name.'`
            SET lft = lft + '.$skew_edit.',
                rgt = rgt + '.$skew_edit.',
                lvl = lvl + '.$skew_level.'
            WHERE id IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        $this->db->commit();
    }

    public function down($id)
    {
        $node = $this->findById($id);

        //Get parent
        $parent = $this->getParentNode($node);

        $parent_level = $parent ? $parent['lvl'] : 0;

        list($next_node) = $this->getNextNodes($node, $parent);

        if(!$next_node) throw new \Exception("Error. Can't move down");

        //check next_node descendants
        $st = $this->db->query('
              SELECT * 
              FROM `'.$this->table_name.'` 
              WHERE lft >= '.$next_node['lft'].' 
                AND rgt <= '.$next_node['rgt'].' 
                AND lvl = '.$next_node['lvl'].' + 1 
              ORDER BY lft LIMIT 1
            ');
        $res = $st->fetch();
        $next_node_has_children = $res ? true : false;

        if($next_node['lvl'] == $node['lvl'] && $next_node_has_children)
        {
            //Move Level Down to children, attach to left side
            $right_key_near = $next_node['lft']; //*******CHECKED**********

            $parent_level = $next_node['lvl'];
        }elseif($next_node['lvl'] == $node['lvl'])
        {
            //Switch position with next node
            $right_key_near = $next_node['rgt']; //*******CHECKED**********
        }else
        {
            //Move Level Up to parent node, attach to right side of parent node
            $parent_next_node = $this->getParentNode($next_node);
            $parent_level = $parent_next_node? $parent_next_node['lvl'] : 0;
            //Attach next to parent node
            $right_key_near = $parent['rgt']; //*******CHECKED**********
        }

        $skew_level = $parent_level - $node['lvl'] + 1;
        $skew_tree = $node['rgt'] - $node['lft'] + 1;
        $skew_edit = $right_key_near - $node['lft'] + 1 - $skew_tree;

        //Get current node branch ids
        $st = $this->db->query('SELECT id FROM `'.$this->table_name.'` WHERE lft >= '.$node['lft'].' AND rgt <= '.$node['rgt']);
        $ids_edit = [];
        while ($res = $st->fetch())
        {
            $ids_edit[] = $res['id'];
        }

        //Change rest tree
        $this->db->beginTransaction();
        $query = '
            UPDATE `'.$this->table_name.'`
                SET rgt = rgt - '.$skew_tree.'
            WHERE rgt > '.$node['rgt'].'
                AND rgt <= '.$right_key_near.' 
                AND id NOT IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        $query = '
            UPDATE `'.$this->table_name.'`
                SET lft = lft - '.$skew_tree.'
            WHERE lft > '.$node['rgt'].'
                AND lft <= '.$right_key_near.' 
                AND id NOT IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        //----------------

        //Change current node
        $query = '
            UPDATE `'.$this->table_name.'`
            SET lft = lft + '.$skew_edit.',
                rgt = rgt + '.$skew_edit.',
                lvl = lvl + '.$skew_level.'
            WHERE id IN ('.implode(',',$ids_edit).')
        ';
        $this->db->query($query);
        $this->db->commit();
    }

    public function findAll()
    {
        $st = $this->db->query('SELECT * FROM `'.$this->table_name.'` ORDER BY lft');
        return $st->fetchAll();
    }

    public function __toString()
    {
        $items = $this->findAll();

        $out = '';

        foreach ($items as $item)
        {
            $out .= str_repeat('*',$item['lvl']) . ' ' . $item['title'] . '(#'.$item['id'].')' . "\n";
        }

        return $out;
    }
}