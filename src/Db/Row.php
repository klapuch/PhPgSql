<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

/**
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
class Row implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
	/** @var array<string, mixed> */
	private $values;


	/**
	 * @param array<string, mixed> $values
	 */
	public function __construct(array $values)
	{
		$this->values = $values;
	}


	/**
	 * @return mixed
	 * @throws Exceptions\RowException
	 */
	public function __get(string $column)
	{
		if (!\array_key_exists($column, $this->values)) {
			throw Exceptions\RowException::noParam($column);
		}

		return $this->values[$column];
	}


	/**
	 * @param mixed $value
	 */
	public function __set(string $column, $value): void
	{
		$this->values[$column] = $value;
	}


	public function __isset(string $column): bool
	{
		return isset($this->values[$column]);
	}


	public function __unset(string $column): void
	{
		unset($this->values[$column]);
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->values;
	}


	public function count(): int
	{
		return \count($this->values);
	}


	/**
	 * @return \ArrayIterator<string, mixed>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->toArray());
	}


	/**
	 * @param mixed $column
	 * @return mixed
	 * @throws Exceptions\RowException
	 */
	public function offsetGet($column)
	{
		if (!\is_string($column)) {
			throw Exceptions\RowException::notStringKey();
		}
		return $this->__get($column);
	}


	/**
	 * @param mixed $column
	 * @param mixed $value
	 */
	public function offsetSet($column, $value): void
	{
		if (!\is_string($column)) {
			throw Exceptions\RowException::notStringKey();
		}
		$this->__set($column, $value);
	}


	/**
	 * @param mixed $column
	 */
	public function offsetExists($column): bool
	{
		if (!\is_string($column)) {
			throw Exceptions\RowException::notStringKey();
		}
		return $this->__isset($column);
	}


	/**
	 * @param mixed $column
	 */
	public function offsetUnset($column): void
	{
		if (!\is_string($column)) {
			throw Exceptions\RowException::notStringKey();
		}
		$this->__unset($column);
	}


	public function hasColumn(string $column): bool
	{
		return \array_key_exists($column, $this->values);
	}


	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray();
	}

}
