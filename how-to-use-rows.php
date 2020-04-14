<?php declare(strict_types=1);

use Forrest79\PhPgSql\Db;

final class Facade
{
	/** @var Db\Connection */
	private $connection;

	public function getNick(int $id): Db\Row
	{
		$user = $this->connection->query('SELECT nick FROM users WHERE id = ?', $id)->fetch();
		return $user ?? new Db\Row(['nick' => '']); // tady můžu jednodušše vrátit ručně definovaou Row, kdežto pokud vrátí něco DB, dostanu to jako LazyRow, ale ta jenom rozšiřuje Row o trochu logiky, takže mi tahle funkce krásně vrací jenom Db\Row a ať si ji udělám ručně nebo mi ji vrátí DB, dostanu funčkně tu samou Row se stejnou funkčností

		// Kdybych mel jenom LazyRow, tak nemůžu udělat `new Db\LazyRow()`, protože ta už má v sobě nějakou logiku a ta vyžaduje definovat a předat Result
	}

}

$facade = new Facade();
$row = $facade->getNick(1)->nick; // vždycky se můžu spolednou, že to dostanu...

// Takhle se mi to líbí, jak je to rozdělené, samotná `Db\Row` vlastně jenom definuje API - array access a property access (zbytek neni důležitý)

// Problém za mě nastane, když mi chceme v aplikaci nějakou vlastní Row...

namespace App\Database;

class Row extends Db\LazyRow
{

	/**
	 * Nereálné, jenom na ukázku...
	 * @return string
	 */
	public function image(): string
	{
		return $this->offsetGet('image_url') ?? '';
	}

}

// Protože, kdybych teď chtěl napsat to samé, ale s naší Row, tak jsem v háji...

final class Facade
{
	/** @var Db\Connection */
	private $connection;

	public function getNick(int $id): Database\Row
	{
		$user = $this->connection->query('SELECT nick FROM users WHERE id = ?', $id)->fetch();
		return $user ?? new Database\Row(['nick' => '']); // tohle už neudělám, protože Database\Row dědí od Db\LazyRow a ta nejde takhle jednodušše udělat ručně

		// pokud bych Database\Row, dědil od Row, tak jí můžu udělat ručně, ale zase do ní nedostanu to LazyRow rozšíření, aby to mohla vracet DB knihovna
	}

}

// Přestože se mi líbí, že rozdělila Db\Row jakožto hloupý value object, který implementuje přístup k datům, který chci a Db\LazyRow s logikou na parsování dat, tak se začínám klonit k tomu, udělat to tak, že se vše zase spojí rovnou do jedné `Row` a ta bude moc být i hloupá. Pokud jí předám `Result`, tak bude počítat, že ve values dostala surová data, která musí ještě naparsovat a pokud jí nepředám Result, tak si to všechno uloží jako values a bude se chovat ja hloupý value object.
// Tím pak bude rozšiřitelnost v pohodě a půjde jednodušše udělat i ručně. Možná bych to pak naznačil na API nějakou takovou statickou funkcí:

Row::from($values)

// Obecně se nebráním ani tomu vytvořit si na Row interface, ale já prostě pořád nevidím moc výhodu - dopadne to jako tohle https://github.com/nette/database/blob/master/src/Database/IRow.php
// kde je to takový marker a ten array access, property access si budeš implementovat tak jako tak a na to by ti měla posloužit ta základní Row
// a jestli se nepletu, tak ten property access si ani skrze rozhraní nijak nevynutíš, aby všichni (IDE a PHPStan) věděli, že je to OK...
