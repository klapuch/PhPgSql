<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
class DataTypeCacheTest extends TestCase
{
	/** @var string */
	private $cacheDirectory;


	protected function setUp(): void
	{
		parent::setUp();
		$this->cacheDirectory = \sprintf('%s/plpgsql-data-types-cache-%s', \sys_get_temp_dir(), \uniqid());
	}


	public function testCache(): void
	{
		$dataTypeCache = $this->createDataTypeCache();

		$this->connection->setDataTypeCache($dataTypeCache);

		Tester\Assert::false(\file_exists($this->getDataTypeCacheFile($this->connection)));

		$cacheFromDb = $dataTypeCache->load($this->connection); // load from DB
		Tester\Assert::true(\count($cacheFromDb) > 0); // there must be some types

		Tester\Assert::true($this->connection->query('SELECT TRUE')->fetchSingle()); // test valid boolean parsing

		$cacheCache = $dataTypeCache->load($this->connection); // load from cache
		Tester\Assert::same($cacheFromDb, $cacheCache);

		$dataTypeCacheNew = $this->createDataTypeCache();

		$cacheFromFile = $dataTypeCacheNew->load($this->connection); // load from file
		Tester\Assert::same($cacheFromDb, $cacheFromFile);

		$cacheCacheNew = $dataTypeCacheNew->load($this->connection); // load from cache
		Tester\Assert::same($cacheFromDb, $cacheCacheNew);

		$dataTypeCache->clean($this->connection);
		$dataTypeCacheNew->clean($this->connection);
	}


	public function testLoadCacheFromFile(): void
	{
		$type = 'phppgsql-test-type';

		$dataTypeCache = $this->createDataTypeCache();

		$cacheDb = $dataTypeCache->load($this->connection); // load from DB
		Tester\Assert::true(\count($cacheDb) > 0); // there must be some types
		Tester\Assert::false(\array_search($type, $cacheDb, TRUE)); // but no $type

		\file_put_contents(
			$this->getDataTypeCacheFile($this->connection),
			'<?php declare(strict_types=1);' . \PHP_EOL . 'return [1=>\'' . $type . '\'];'
		);

		$dataTypeCacheNew = $this->createDataTypeCache();
		$cacheFromFile = $dataTypeCacheNew->load($this->connection); // load from file
		Tester\Assert::same([1 => $type], $cacheFromFile);
	}


	private function getDataTypeCacheFile(Db\Connection $connection): string
	{
		return $this->cacheDirectory . \DIRECTORY_SEPARATOR . \md5($connection->getConnectionConfig()) . '.php';
	}


	private function createDataTypeCache(): Db\DataTypeCaches\PhpFile
	{
		return new Db\DataTypeCaches\PhpFile($this->cacheDirectory);
	}

}

\run(DataTypeCacheTest::class);
