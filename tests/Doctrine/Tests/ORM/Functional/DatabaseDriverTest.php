<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;

class DatabaseDriverTest extends DatabaseDriverTestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    protected $_sm = null;

    public function setUp()
    {
        $this->useModelSet('cms');

        parent::setUp();

        $this->_sm = $this->_em->getConnection()->getSchemaManager();
    }

    /**
     * @group DDC-2059
     */
    public function testIssue2059()
    {
        if (!$this->_em->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $user = new \Doctrine\DBAL\Schema\Table("ddc2059_user");
        $user->addColumn('id', 'integer');
        $user->setPrimaryKey(array('id'));
        $project = new \Doctrine\DBAL\Schema\Table("ddc2059_project");
        $project->addColumn('id', 'integer');
        $project->addColumn('user_id', 'integer');
        $project->addColumn('user', 'string');
        $project->setPrimaryKey(array('id'));
        $project->addForeignKeyConstraint('ddc2059_user', array('user_id'), array('id'));

        $metadata = $this->convertToClassMetadata(array($project, $user), array());

        self::assertTrue(isset($metadata['Ddc2059Project']->fieldMappings['user']));
        self::assertTrue(isset($metadata['Ddc2059Project']->associationMappings['user2']));
    }

    public function testLoadMetadataFromDatabase()
    {
        if (!$this->_em->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $table = new \Doctrine\DBAL\Schema\Table("dbdriver_foo");
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addColumn('bar', 'string', array('notnull' => false, 'length' => 200));

        $this->_sm->dropAndCreateTable($table);

        $metadatas = $this->extractClassMetadata(array("DbdriverFoo"));

        self::assertArrayHasKey('DbdriverFoo', $metadatas);
        $metadata = $metadatas['DbdriverFoo'];

        self::assertArrayHasKey('id', $metadata->fieldMappings);
        self::assertEquals('id', $metadata->fieldMappings['id']['fieldName']);
        self::assertEquals('id', strtolower($metadata->fieldMappings['id']['columnName']));
        self::assertEquals('integer', $metadata->fieldMappings['id']['type']->getName());

        self::assertArrayHasKey('bar', $metadata->fieldMappings);
        self::assertEquals('bar', $metadata->fieldMappings['bar']['fieldName']);
        self::assertEquals('bar', strtolower($metadata->fieldMappings['bar']['columnName']));
        self::assertEquals('string', $metadata->fieldMappings['bar']['type']->getName());
        self::assertEquals(200, $metadata->fieldMappings['bar']['length']);
        self::assertTrue($metadata->fieldMappings['bar']['nullable']);
    }

    public function testLoadMetadataWithForeignKeyFromDatabase()
    {
        if (!$this->_em->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $tableB = new \Doctrine\DBAL\Schema\Table("dbdriver_bar");
        $tableB->addColumn('id', 'integer');
        $tableB->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($tableB);

        $tableA = new \Doctrine\DBAL\Schema\Table("dbdriver_baz");
        $tableA->addColumn('id', 'integer');
        $tableA->setPrimaryKey(array('id'));
        $tableA->addColumn('bar_id', 'integer');
        $tableA->addForeignKeyConstraint('dbdriver_bar', array('bar_id'), array('id'));

        $this->_sm->dropAndCreateTable($tableA);

        $metadatas = $this->extractClassMetadata(array("DbdriverBar", "DbdriverBaz"));

        self::assertArrayHasKey('DbdriverBaz', $metadatas);
        $bazMetadata = $metadatas['DbdriverBaz'];

        self::assertArrayNotHasKey('barId', $bazMetadata->fieldMappings, "The foreign Key field should not be inflected as 'barId' field, its an association.");
        self::assertArrayHasKey('id', $bazMetadata->fieldMappings);

        $bazMetadata->associationMappings = \array_change_key_case($bazMetadata->associationMappings, \CASE_LOWER);

        self::assertArrayHasKey('bar', $bazMetadata->associationMappings);
        self::assertEquals(ClassMetadata::MANY_TO_ONE, $bazMetadata->associationMappings['bar']['type']);
    }

    public function testDetectManyToManyTables()
    {
        if (!$this->_em->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $metadatas = $this->extractClassMetadata(array("CmsUsers", "CmsGroups", "CmsTags"));

        self::assertArrayHasKey('CmsUsers', $metadatas, 'CmsUsers entity was not detected.');
        self::assertArrayHasKey('CmsGroups', $metadatas, 'CmsGroups entity was not detected.');
        self::assertArrayHasKey('CmsTags', $metadatas, 'CmsTags entity was not detected.');

        self::assertEquals(3, count($metadatas['CmsUsers']->associationMappings));
        self::assertArrayHasKey('group', $metadatas['CmsUsers']->associationMappings);
        self::assertEquals(1, count($metadatas['CmsGroups']->associationMappings));
        self::assertArrayHasKey('user', $metadatas['CmsGroups']->associationMappings);
        self::assertEquals(1, count($metadatas['CmsTags']->associationMappings));
        self::assertArrayHasKey('user', $metadatas['CmsGroups']->associationMappings);
    }

    public function testIgnoreManyToManyTableWithoutFurtherForeignKeyDetails()
    {
        $tableB = new \Doctrine\DBAL\Schema\Table("dbdriver_bar");
        $tableB->addColumn('id', 'integer');
        $tableB->setPrimaryKey(array('id'));

        $tableA = new \Doctrine\DBAL\Schema\Table("dbdriver_baz");
        $tableA->addColumn('id', 'integer');
        $tableA->setPrimaryKey(array('id'));

        $tableMany = new \Doctrine\DBAL\Schema\Table("dbdriver_bar_baz");
        $tableMany->addColumn('bar_id', 'integer');
        $tableMany->addColumn('baz_id', 'integer');
        $tableMany->addForeignKeyConstraint('dbdriver_bar', array('bar_id'), array('id'));

        $metadatas = $this->convertToClassMetadata(array($tableA, $tableB), array($tableMany));

        self::assertEquals(0, count($metadatas['DbdriverBaz']->associationMappings), "no association mappings should be detected.");
    }

    public function testLoadMetadataFromDatabaseDetail()
    {
        if ( ! $this->_em->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $table = new \Doctrine\DBAL\Schema\Table("dbdriver_foo");

        $table->addColumn('id', 'integer', array('unsigned' => true));
        $table->setPrimaryKey(array('id'));
        $table->addColumn('column_unsigned', 'integer', array('unsigned' => true));
        $table->addColumn('column_comment', 'string', array('comment' => 'test_comment'));
        $table->addColumn('column_default', 'string', array('default' => 'test_default'));
        $table->addColumn('column_decimal', 'decimal', array('precision' => 4, 'scale' => 3));

        $table->addColumn('column_index1', 'string');
        $table->addColumn('column_index2', 'string');
        $table->addIndex(array('column_index1','column_index2'), 'index1');

        $table->addColumn('column_unique_index1', 'string');
        $table->addColumn('column_unique_index2', 'string');
        $table->addUniqueIndex(array('column_unique_index1', 'column_unique_index2'), 'unique_index1');

        $this->_sm->dropAndCreateTable($table);

        $metadatas = $this->extractClassMetadata(array("DbdriverFoo"));

        self::assertArrayHasKey('DbdriverFoo', $metadatas);

        $metadata = $metadatas['DbdriverFoo'];

        self::assertArrayHasKey('id', $metadata->fieldMappings);
        self::assertEquals('id', $metadata->fieldMappings['id']['fieldName']);
        self::assertEquals('id', strtolower($metadata->fieldMappings['id']['columnName']));
        self::assertEquals('integer', $metadata->fieldMappings['id']['type']->getName());

        // FIXME: Condition here is fugly.
        // NOTE: PostgreSQL and SQL SERVER do not support UNSIGNED integer
        if ( ! $this->_em->getConnection()->getDatabasePlatform() instanceof PostgreSqlPlatform AND
             ! $this->_em->getConnection()->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertArrayHasKey('columnUnsigned', $metadata->fieldMappings);
            self::assertTrue($metadata->fieldMappings['columnUnsigned']['options']['unsigned']);
        }

        self::assertArrayHasKey('columnComment', $metadata->fieldMappings);
        self::assertEquals('test_comment', $metadata->fieldMappings['columnComment']['options']['comment']);

        self::assertArrayHasKey('columnDefault', $metadata->fieldMappings);
        self::assertEquals('test_default', $metadata->fieldMappings['columnDefault']['options']['default']);

        self::assertArrayHasKey('columnDecimal', $metadata->fieldMappings);
        self::assertEquals(4, $metadata->fieldMappings['columnDecimal']['precision']);
        self::assertEquals(3, $metadata->fieldMappings['columnDecimal']['scale']);

        self::assertTrue( ! empty($metadata->table['indexes']['index1']['columns']));
        self::assertEquals(
            array('column_index1','column_index2'),
            $metadata->table['indexes']['index1']['columns']
        );

        self::assertTrue( ! empty($metadata->table['uniqueConstraints']['unique_index1']['columns']));
        self::assertEquals(
            array('column_unique_index1', 'column_unique_index2'),
            $metadata->table['uniqueConstraints']['unique_index1']['columns']
        );
    }
}
