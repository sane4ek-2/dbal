<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Driver\OCI8\Driver as OCI8Driver;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use function is_resource;
use function random_bytes;
use function str_replace;
use function stream_get_contents;

class BinaryTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        /** @see https://github.com/doctrine/dbal/issues/2787 */
        if ($this->_conn->getDriver() instanceof OCI8Driver) {
            $this->markTestSkipped('Filtering by binary fields is currently not supported on Oracle');
        }

        $table = new Table('binary_table');
        $table->addColumn('id', 'binary', [
            'length' => 16,
            'fixed' => true,
        ]);
        $table->addColumn('val', 'binary', ['length' => 64]);
        $table->setPrimaryKey(['id']);

        $sm = $this->_conn->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsertAndSelect()
    {
        $id1 = random_bytes(16);
        $id2 = random_bytes(16);

        $value1 = random_bytes(64);
        $value2 = random_bytes(64);

        /** @see https://bugs.php.net/bug.php?id=76322 */
        if ($this->_conn->getDriver() instanceof DB2Driver) {
            $value1 = str_replace("\x00", "\xFF", $value1);
            $value2 = str_replace("\x00", "\xFF", $value2);
        }

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        $this->assertSame($value1, $this->select($id1));
        $this->assertSame($value2, $this->select($id2));
    }

    private function insert(string $id, string $value) : void
    {
        $result = $this->_conn->insert('binary_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::LARGE_OBJECT,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertSame(1, $result);
    }

    private function select(string $id)
    {
        $value = $this->_conn->fetchColumn(
            'SELECT val FROM binary_table WHERE id = ?',
            [$id],
            0,
            [ParameterType::LARGE_OBJECT]
        );

        // Currently, `BinaryType` mistakenly converts string values fetched from the DB to a stream.
        // It should be the opposite. Streams should be used to represent large objects, not binary
        // strings. The confusion comes from the PostgreSQL's type system where binary strings and
        // large objects are represented by the same BYTEA type
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $value;
    }
}
