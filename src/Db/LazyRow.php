<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class LazyRow extends Row
{
	/** @var Result */
	private $result;

	/** @var array<string, string|NULL> */
	private $rawValues;


	/**
	 * @param array<string, mixed> $rawValues
	 */
	public function __construct(Result $result, array $rawValues)
	{
		$this->result = $result;
		$this->rawValues = $rawValues;

		parent::__construct(\array_fill_keys(\array_keys($rawValues), NULL));
	}


	/**
	 * @return mixed
	 * @throws Exceptions\RowException
	 */
	public function __get(string $column)
	{
		if (\array_key_exists($column, $this->rawValues)) {
			$this->parseValue($column);
		}

		return parent::__get($column);
	}


	/**
	 * @param mixed $value
	 */
	public function __set(string $column, $value): void
	{
		unset($this->rawValues[$column]);
		parent::__set($column, $value);
	}


	public function __isset(string $column): bool
	{
		return $this->hasColumn($column) && ($this->__get($column) !== NULL);
	}


	public function __unset(string $column): void
	{
		unset($this->rawValues[$column]);
		parent::__unset($column);
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		// intentionally not using array_keys($this->rawValues) as $key - this is 2x faster
		foreach ($this->rawValues as $column => $value) {
			$this->parseValue($column);
		}
		return parent::toArray();
	}


	private function parseValue(string $column): void
	{
		$this->__set($column, $this->result->parseColumnValue($column, $this->rawValues[$column]));
	}


	public function getResult(): Result
	{
		return $this->result;
	}

}
