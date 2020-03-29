<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\RowFactories;

use Forrest79\PhPgSql\Db;

class Full implements Db\RowFactory
{

	/**
	 * @param array<string, mixed> $values
	 */
	public function createRow(array $values, Db\Result $result): Db\Row
	{
		foreach ($values as $column => $rawValue) {
			$values[$column] = $result->parseColumnValue($column, $rawValue);
		}

		return new Db\Row($values);
	}

}
