<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Exceptions;

class ConnectionException extends Exception
{
	public const NO_CONFIG = 1;
	public const CANT_CHANGE_CONNECTION_SETTINGS = 2;
	public const CONNECTION_FAILED = 3;
	public const BAD_CONNECTION = 4;
	public const CANT_GET_NOTICES = 5;
	public const ASYNC_STREAM_FAILED = 6;
	public const ASYNC_CONNECT_FAILED = 7;
	public const ASYNC_CONNECT_TIMEOUT = 8;
	public const ASYNC_CANCEL_FAILED = 9;
	public const ASYNC_QUERY_ALREADY_SENT = 10;
	public const ASYNC_NO_QUERY_WAS_SENT = 11;
	public const ASYNC_NO_EXECUTE_WAS_SENT = 12;


	public static function noConfigException(): self
	{
		return new self('No configuration was provided.', self::NO_CONFIG);
	}


	public static function cantChangeConnectionSettings(): self
	{
		return new self('You can\'t change connection settings when connected.', self::CANT_CHANGE_CONNECTION_SETTINGS);
	}


	public static function connectionFailedException(): self
	{
		$message = '.';
		$lastPhpError = \error_get_last();
		if ($lastPhpError !== NULL && $lastPhpError['type'] === \E_WARNING) {
			$message = ': ' . $lastPhpError['message'];
		}
		return new self('Connection failed' . $message, self::CONNECTION_FAILED);
	}


	public static function badConnectionException(): self
	{
		return new self('Connection failed (bad connection).', self::BAD_CONNECTION);
	}


	public static function cantGetNoticesException(): self
	{
		return new self('Can\'t get notices from connection. Is notice message tracking not ignored in php.ini - pgsql.ignore_notice = 0 is the right value.', self::CANT_GET_NOTICES);
	}


	public static function asyncStreamFailedException(): self
	{
		return new self('Asynchronous connection error.', self::ASYNC_STREAM_FAILED);
	}


	public static function asyncConnectFailedException(): self
	{
		return new self('Asynchronous connection error.', self::ASYNC_CONNECT_FAILED);
	}


	public static function asyncConnectTimeoutException(float $afterSeconds, int $configSeconds): self
	{
		return new self(\sprintf('Asynchronous connection timeout after %f seconds (%d seconds are configured).', $afterSeconds, $configSeconds), self::ASYNC_CONNECT_TIMEOUT);
	}


	public static function asyncCancelFailedException(): self
	{
		return new self('Cancelation of async query failed.', self::ASYNC_CANCEL_FAILED);
	}


	public static function asyncQueryAlreadySentException(): self
	{
		return new self('There is already running async query. Did you call waitForAsyncQuery method before new async query?', self::ASYNC_QUERY_ALREADY_SENT);
	}


	public static function asyncNoQueryWasSentException(): self
	{
		return new self('No async query was sent.', self::ASYNC_NO_QUERY_WAS_SENT);
	}


	public static function asyncNoExecuteWasSentException(): self
	{
		return new self('No async execute was sent.', self::ASYNC_NO_EXECUTE_WAS_SENT);
	}

}
