<?php declare(strict_types=1);

namespace Shopware\DbalNestedSetTest;

use PHPUnit\Framework\TestCase;
use Shopware\DbalNestedSet\NestedSetConfig;
use Shopware\DbalNestedSet\NestedSetFactory;
use Shopware\DbalNestedSet\NestedSetQueryFactory;

class NestedSetQueryFactoryTest extends TestCase
{
    /**
     * @var NestedSetQueryFactory
     */
    private $queryFactory;

    public function setUp()
    {
        $connection = \NestedSetBootstrap::getConnection();
        \NestedSetBootstrap::importTable();
        \NestedSetBootstrap::insertDemoTree();
        \NestedSetBootstrap::insertDemoTree(2);
        $this->queryFactory = NestedSetFactory::createQueryFactory($connection, new NestedSetConfig('id', 'left', 'right', 'level'));
    }

    public function test_fetch_all_children()
    {
        $qb = $this->queryFactory->createChildrenQueryBuilder('tree', 't', 'root_id', 2)
            ->select('*');

        $sql = $qb->getSQL();

        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('Suits', $rows[0]['name']);
    }

    public function test_fetch_all_children_and_node()
    {
        $qb = $this->queryFactory->createParentAndChildrenQueryBuilder('tree', 't', 'root_id', 24)
            ->select('*');

        $sql = $qb->getSQL();

        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(3, $rows);
        $this->assertEquals('Suits', $rows[0]['name']);
    }

    public function test_fetch_subtree()
    {
        $qb = $this->queryFactory->createSubtreeQueryBuilder('tree', 't', 'root_id', 2)
            ->select('*');

        $sql = $qb->getSQL();

        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(3, $rows);
        $this->assertEquals('Suits', $rows[0]['name']);
        $this->assertEquals('Slacks', $rows[1]['name']);
        $this->assertEquals('Jackets', $rows[2]['name']);
    }

    public function test_fetch_parents()
    {
        $qb = $this->queryFactory->createParentsQueryBuilder('tree', 't', 'root_id', 2)
            ->select('*');

        $sql = $qb->getSQL();
        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('Clothing', $rows[0]['name']);
    }

    public function test_fetch_parents_on_leaf()
    {
        $qb = $this->queryFactory->createParentsQueryBuilder('tree', 't', 'root_id', 6)
            ->select('*');

        $sql = $qb->getSQL();
        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(3, $rows);
        $this->assertEquals('Suits', $rows[0]['name']);
        $this->assertEquals('Mens', $rows[1]['name']);
        $this->assertEquals('Clothing', $rows[2]['name']);
    }

    public function test_fetch_all_roots()
    {
        $qb = $this->queryFactory->createFetchRootsQueryBuilder('tree', 't')
            ->select('*');

        $sql = $qb->getSQL();
        $this->assertContains('tree', $sql);
        $this->assertContains('t.', $sql);

        $rows = $qb->execute()->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertEquals('Clothing', $rows[0]['name']);
        $this->assertEquals('Clothing', $rows[1]['name']);
    }

    public function test_fetch_subtree_with_root_only_selected()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [1])
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Women',
            ],
            $qb->execute()->fetchAll()
        );
    }

    public function test_fetch_subtree_with_a_single_selected_node_slacks()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [5])
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Suits',
                'Slacks',
                'Jackets',
                'Women',
            ],
            $qb->execute()->fetchAll()
        );
    }

    public function test_fetch_subtree_with_selected_nodes_mens_and_dresses()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [2, 7])
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Suits',
                'Women',
                'Dresses',
                'Evening Growns',
                'Sun Dresses',
                'Skirts',
                'Blouses',
            ],
            $qb->execute()->fetchAll()
        );
    }

    public function test_fetch_subtree_with_selected_nodes_mens_and_women()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [3, 2])
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Suits',
                'Women',
                'Dresses',
                'Skirts',
                'Blouses',
            ],
            $qb->execute()->fetchAll()
        );
    }

    public function test_fetch_subtree_with_selected_nodes_with_a_two_as_a_depth_parameter()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [2, 3], 2)
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Suits',
                'Slacks',
                'Jackets',
                'Women',
                'Dresses',
                'Evening Growns',
                'Sun Dresses',
                'Skirts',
                'Blouses',
            ],
            $qb->execute()->fetchAll()
        );
    }

    public function test_fetch_subtree_with_selected_nodes_with_a_zero_depth_parameter()
    {
        $qb = $this->queryFactory
            ->createSubtreeThroughMultipleNodesQueryBuilder('tree', 't', 'root_id', [3, 2], 0)
            ->select('*');

        $this->assertSubTree(
            [
                'Clothing',
                'Mens',
                'Women',
            ],
            $qb->execute()->fetchAll()
        );
    }

    private function assertSubTree(array $expectedNames, array $rows)
    {
        $names = array_map(function (array $node) {
            return $node['name'];
        }, $rows);

        $this->assertEquals($expectedNames, $names, 'Got: ' . print_r($names, true) . "\n and expected: " . print_r($expectedNames, true));
    }
}
