<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class Connection
{
	/** @var string */
	private $connectionConfig = '';

	/** @var int */
	private $errorVerbosity = \PGSQL_ERRORS_DEFAULT;

	/** @var bool */
	private $connectForceNew = FALSE;

	/** @var bool */
	private $connectAsync = FALSE;

	/** @var int */
	private $connectAsyncWaitSeconds = 15;

	/** @var resource|NULL */
	private $resource;

	/** @var bool */
	private $connected = FALSE;

	/** @var resource */
	private $asyncStream;

	/** @var RowFactory */
	private $defaultRowFactory;

	/** @var DataTypeParser */
	private $dataTypeParser;

	/** @var DataTypeCache|NULL */
	private $dataTypeCache;

	/** @var AsyncQuery|string|NULL */
	private $asyncQuery;

	/** @var Transaction */
	private $transaction;

	/** @var callable[] function (Connection $connection) {} */
	private $onConnect = [];

	/** @var callable[] function (Connection $connection) {} */
	private $onClose = [];

	/** @var callable[] function (Connection $connection, Query $query, float $time) {} */
	private $onQuery = [];


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function __construct(string $connectionConfig = '', bool $connectForceNew = FALSE, bool $connectAsync = FALSE)
	{
		$this->connectionConfig = $connectionConfig;
		$this->connectForceNew = $connectForceNew;
		$this->connectAsync = $connectAsync;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function connect(): self
	{
		if ($this->connectionConfig === '') {
			throw Exceptions\ConnectionException::noConfigException();
		}

		$connectType = 0;
		if ($this->connectForceNew === TRUE) {
			$connectType |= \PGSQL_CONNECT_FORCE_NEW;
		}
		if ($this->connectAsync === TRUE) {
			$connectType |= \PGSQL_CONNECT_ASYNC;
		}

		$resource = @\pg_connect($this->connectionConfig, $connectType); // intentionally @
		if (!\is_resource($resource)) {
			throw Exceptions\ConnectionException::connectionFailedException();
		} elseif (\pg_connection_status($resource) === \PGSQL_CONNECTION_BAD) {
			throw Exceptions\ConnectionException::badConnectionException();
		}

		$this->resource = $resource;

		if ($this->connectAsync === TRUE) {
			$stream = \pg_socket($resource);
			if ($stream === FALSE) {
				throw Exceptions\ConnectionException::asyncStreamFailedException();
			}
			$this->asyncStream = $stream;
		} else {
			if ($this->errorVerbosity !== \PGSQL_ERRORS_DEFAULT) {
				\pg_set_error_verbosity($this->resource, $this->errorVerbosity);
			}
			$this->connected = TRUE;
			if ($this->onConnect !== []) {
				$this->onConnect();
			}
		}

		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function isConnected(bool $waitForConnect = FALSE): bool
	{
		if ($waitForConnect === TRUE) {
			$this->getConnectedResource();
		}
		return $this->connected;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function ping(): bool
	{
		return \pg_ping($this->getConnectedResource());
	}


	public function setConnectionConfig(string $config): self
	{
		if ($this->isConnected()) {
			throw Exceptions\ConnectionException::cantChangeConnectionSettings();
		}
		$this->connectionConfig = $config;
		return $this;
	}


	public function getConnectionConfig(): string
	{
		return $this->connectionConfig;
	}


	public function setConnectForceNew(bool $forceNew = TRUE): self
	{
		if ($this->isConnected()) {
			throw Exceptions\ConnectionException::cantChangeConnectionSettings();
		}
		$this->connectForceNew = $forceNew;
		return $this;
	}


	public function setConnectAsync(bool $async = TRUE): self
	{
		if ($this->isConnected()) {
			throw Exceptions\ConnectionException::cantChangeConnectionSettings();
		}
		$this->connectAsync = $async;
		return $this;
	}


	public function setConnectAsyncWaitSeconds(int $seconds): self
	{
		if ($this->isConnected()) {
			throw Exceptions\ConnectionException::cantChangeConnectionSettings();
		}
		$this->connectAsyncWaitSeconds = $seconds;
		return $this;
	}


	public function setErrorVerbosity(int $errorVerbosity): self
	{
		if ($this->errorVerbosity !== $errorVerbosity) {
			$this->errorVerbosity = $errorVerbosity;
			if ($this->isConnected()) {
				\pg_set_error_verbosity($this->getConnectedResource(), $this->errorVerbosity);
			}
		}
		return $this;
	}


	public function addOnConnect(callable $callback): self
	{
		$this->onConnect[] = $callback;
		return $this;
	}


	public function addOnClose(callable $callback): self
	{
		$this->onClose[] = $callback;
		return $this;
	}


	public function addOnQuery(callable $callback): self
	{
		$this->onQuery[] = $callback;
		return $this;
	}


	public function close(): self
	{
		if ($this->isConnected()) {
			$this->onClose();
		}

		if (\is_resource($this->resource)) {
			\pg_close($this->resource);
		}

		$this->resource = NULL;
		$this->connected = FALSE;

		return $this;
	}


	public function setDefaultRowFactory(RowFactory $rowFactory): self
	{
		$this->defaultRowFactory = $rowFactory;
		return $this;
	}


	private function getDefaultRowFactory(): RowFactory
	{
		if ($this->defaultRowFactory === NULL) {
			$this->defaultRowFactory = new RowFactories\Basic();
		}

		return $this->defaultRowFactory;
	}


	public function setDataTypeParser(DataTypeParser $dataTypeParser): self
	{
		$this->dataTypeParser = $dataTypeParser;
		return $this;
	}


	private function getDataTypeParser(): DataTypeParser
	{
		if ($this->dataTypeParser === NULL) {
			$this->dataTypeParser = new DataTypeParsers\Basic();
		}

		return $this->dataTypeParser;
	}


	public function setDataTypeCache(DataTypeCache $dataTypeCache): self
	{
		$this->dataTypeCache = $dataTypeCache;
		return $this;
	}


	private function getDataTypesCache(): ?array
	{
		return $this->dataTypeCache === NULL ? NULL : $this->dataTypeCache->load($this);
	}


	/**
	 * @param string|Query $query
	 * @param mixed ...$params
	 * @return Result
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function query($query, ...$params): Result
	{
		return $this->queryArgs($query, $params);
	}


	/**
	 * @param string|Query $query
	 * @param array $params
	 * @return Result
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function queryArgs($query, array $params): Result
	{
		$query = Helper::prepareSql($this->normalizeQuery($query, $params));

		$start = $this->onQuery !== [] ? \microtime(TRUE) : NULL;

		$queryParams = $query->getParams();
		if ($queryParams === []) {
			$resource = @\pg_query($this->getConnectedResource(), $query->getSql()); // intentionally @
		} else {
			$resource = @\pg_query_params($this->getConnectedResource(), $query->getSql(), $queryParams); // intentionally @
		}
		if ($resource === FALSE) {
			throw Exceptions\QueryException::queryFailed($query, $this->getLastError());
		}

		if ($start !== NULL) {
			$this->onQuery($query, \microtime(TRUE) - $start);
		}

		return $this->createResult($resource);
	}


	/**
	 * @param resource $resource
	 * @return Result
	 */
	private function createResult($resource): Result
	{
		return new Result($resource, $this->getDefaultRowFactory(), $this->getDataTypeParser(), $this->getDataTypesCache());
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function execute(string $sql): self
	{
		$start = $this->onQuery !== [] ? \microtime(TRUE) : NULL;

		$resource = @\pg_query($this->getConnectedResource(), $sql); // intentionally @
		if ($resource === FALSE) {
			throw Exceptions\QueryException::queryFailed(new Query($sql), $this->getLastError());
		}

		if ($start !== NULL) {
			$this->onQuery(new Query($sql), \microtime(TRUE) - $start);
		}

		return $this;
	}


	/**
	 * @param string|Query $query
	 * @param mixed ...$params
	 * @return AsyncQuery
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function asyncQuery($query, ...$params): AsyncQuery
	{
		return $this->asyncQueryArgs($query, $params);
	}


	/**
	 * @param string|Query $query
	 * @param array $params
	 * @return AsyncQuery
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function asyncQueryArgs($query, array $params): AsyncQuery
	{
		$query = Helper::prepareSql($this->normalizeQuery($query, $params));
		$this->asyncQuery = new AsyncQuery($this, $query);

		$queryParams = $query->getParams();
		if ($queryParams === []) {
			$querySuccess = @\pg_send_query($this->getConnectedResource(), $query->getSql()); // intentionally @
		} else {
			$querySuccess = @\pg_send_query_params($this->getConnectedResource(), $query->getSql(), $query->getParams()); // intentionally @
		}
		if ($querySuccess === FALSE) {
			throw Exceptions\ConnectionException::asyncQueryAlreadySentException();
		}

		if ($this->onQuery !== []) {
			$this->onQuery($query);
		}

		return $this->asyncQuery;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function asyncExecute(string $sql): self
	{
		$this->asyncQuery = $sql;

		$querySuccess = @\pg_send_query($this->getConnectedResource(), $sql); // intentionally @
		if ($querySuccess === FALSE) {
			throw Exceptions\ConnectionException::asyncQueryAlreadySentException();
		}

		if ($this->onQuery !== []) {
			$this->onQuery(new Query($sql));
		}

		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function completeAsyncExecute(): self
	{
		if (!\is_string($this->asyncQuery)) {
			throw Exceptions\ConnectionException::asyncNoExecuteWasSentException();
		}

		while (TRUE) {
			$resource = \pg_get_result($this->getConnectedResource());
			if ($resource === FALSE) {
				break;
			}
			self::checkAsyncQueryResult($resource, $this->asyncQuery);
		}

		$this->asyncQuery = NULL;

		return $this;
	}


	/**
	 * @return AsyncQuery|string|NULL
	 */
	public function getAsyncQuery()
	{
		return $this->asyncQuery;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function getNextAsyncQueryResult(): Result
	{
		if (!($this->asyncQuery instanceof AsyncQuery)) {
			throw Exceptions\ConnectionException::asyncNoQueryWasSentException();
		}

		$resource = \pg_get_result($this->getConnectedResource());
		if ($resource === FALSE) {
			$query = $this->asyncQuery->getQuery();
			$this->asyncQuery = NULL;
			throw Exceptions\ResultException::noOtherAsyncResult($query);
		}

		self::checkAsyncQueryResult($resource, $this->asyncQuery->getQuery());

		return $this->createResult($resource);
	}


	/**
	 * @param resource $result
	 * @param Query|string $query
	 * @throws Exceptions\QueryException
	 */
	private static function checkAsyncQueryResult($result, $query): void
	{
		if (\in_array(\pg_result_status($result), [\PGSQL_BAD_RESPONSE, \PGSQL_NONFATAL_ERROR, \PGSQL_FATAL_ERROR], TRUE)) {
			throw Exceptions\QueryException::asyncQueryFailed(
				$query instanceof Query ? $query : new Query($query),
				(string) \pg_result_error_field($result, \PGSQL_DIAG_SQLSTATE),
				(string) \pg_result_error($result)
			);
		}
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function cancelAsyncQuery(): self
	{
		if (!\pg_cancel_query($this->getConnectedResource())) {
			throw Exceptions\ConnectionException::asyncCancelFailedException();
		}

		$this->asyncQuery = NULL;

		return $this;
	}


	public function getNotices(bool $clearAfterRead = TRUE): array
	{
		/** @var array|FALSE $notices */
		$notices = \pg_last_notice($this->getConnectedResource(), \PGSQL_NOTICE_ALL);
		if ($notices === FALSE) {
			throw Exceptions\ConnectionException::cantGetNoticesException();
		}

		if ($clearAfterRead) {
			\pg_last_notice($this->getConnectedResource(), \PGSQL_NOTICE_CLEAR);
		}

		return $notices;
	}


	public function transaction(): Transaction
	{
		if ($this->transaction === NULL) {
			$this->transaction = new Transaction($this);
		}
		return $this->transaction;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function isBusy(): bool
	{
		return \pg_connection_busy($this->getConnectedResource());
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function isInTransaction(): bool
	{
		return !\in_array(\pg_transaction_status($this->getConnectedResource()), [\PGSQL_TRANSACTION_UNKNOWN, \PGSQL_TRANSACTION_IDLE], TRUE);
	}


	/**
	 * @return resource
	 * @throws Exceptions\ConnectionException
	 */
	public function getResource()
	{
		return $this->getConnectedResource();
	}


	/**
	 * @param string|Query $query
	 * @param array $params
	 * @return Query
	 * @throws Exceptions\QueryException
	 */
	private function normalizeQuery($query, array $params): Query
	{
		if ($query instanceof Query) {
			if ($params !== []) {
				throw Exceptions\QueryException::cantPassParams();
			}
		} else {
			$query = self::createQueryArgs($query, $params);
		}

		return $query;
	}


	public function getLastError(): string
	{
		return \is_resource($this->resource)
			? \pg_last_error($this->resource)
			: \pg_last_error();
	}


	/**
	 * @return resource
	 * @throws Exceptions\ConnectionException
	 */
	private function getConnectedResource()
	{
		if (!\is_resource($this->resource)) {
			$this->connect();
		}

		if ($this->connected === FALSE) {
			$start = \microtime(TRUE);
			do {
				$test = \microtime(TRUE);
				switch (\pg_connect_poll($this->resource)) {
					case \PGSQL_POLLING_READING:
						while (!self::isReadable($this->asyncStream));
						break;
					case \PGSQL_POLLING_WRITING:
						while (!self::isWritable($this->asyncStream));
						break;
					case \PGSQL_POLLING_FAILED:
						throw Exceptions\ConnectionException::asyncConnectFailedException();
					case \PGSQL_POLLING_OK:
					case \PGSQL_POLLING_ACTIVE: // this can't happen?
						if ($this->errorVerbosity !== \PGSQL_ERRORS_DEFAULT) {
							\pg_set_error_verbosity($this->resource, $this->errorVerbosity);
						}
						$this->connected = TRUE;
						$this->onConnect();
						return $this->resource;
				}
			} while (($test - $start) <= $this->connectAsyncWaitSeconds);
			throw Exceptions\ConnectionException::asyncConnectTimeoutException($test, $this->connectAsyncWaitSeconds);
		}

		return $this->resource;
	}


	private function onConnect(): void
	{
		foreach ($this->onConnect as $event) {
			$event($this);
		}
	}


	private function onClose(): void
	{
		foreach ($this->onClose as $event) {
			$event($this);
		}
	}


	private function onQuery(Query $query, ?float $time = NULL): void
	{
		foreach ($this->onQuery as $event) {
			$event($this, $query, $time);
		}
	}


	/**
	 * @param resource $stream
	 * @return bool
	 */
	private static function isReadable($stream): bool
	{
		$read = [$stream];
		$write = $ex = [];
		return (bool) \stream_select($read, $write, $ex, $usec = 1, 0);
	}


	/**
	 * @param resource $stream
	 * @return bool
	 */
	private static function isWritable($stream): bool
	{
		$write = [$stream];
		$read = $ex = [];
		return (bool) \stream_select($read, $write, $ex, $usec = 1, 0);
	}


	/**
	 * @param string $query
	 * @param mixed ...$params
	 * @return Query
	 */
	public static function createQuery(string $query, ...$params): Query
	{
		return self::createQueryArgs($query, $params);
	}


	public static function createQueryArgs(string $query, array $params): Query
	{
		return new Query($query, $params);
	}


	/**
	 * @param string $value
	 * @param mixed ...$params
	 * @return Literal
	 */
	public static function literal(string $value, ...$params): Literal
	{
		return new Literal($value, ...$params);
	}


	public static function literalArgs(string $value, array $params): Literal
	{
		return new Literal($value, ...$params);
	}

}
