<?php
/**
 * Created by Vlad Varlamov (laxity.ru) on 21.03.2017.
 */

namespace laxity7\swagger;

trait LogTrait
{
	/** @var array */
	public $messages = [];
	/** @var array */
	protected $errorNames = [
		self::LOG_ERROR   => 'error',
		self::LOG_WARNING => 'warning',
		self::LOG_DEBUG   => 'debug',
		self::LOG_NOTICE  => 'notice',
	];

	/** @var array Keeps a list of the last processed objects of different types */
	protected static $currentObjects = [];

	/**
	 * @param string $message
	 */
	public function warning(string $message)
	{
		$this->log($message, static::LOG_WARNING);
	}

	/**
	 * @param string $message
	 */
	public function notice(string $message)
	{
		$this->log($message, self::LOG_NOTICE);
	}

	/**
	 * @param string $message
	 */
	public function debug(string $message)
	{
		$this->log($message, self::LOG_DEBUG);
	}

	/**
	 * @param string $message
	 */
	public function error(string $message)
	{
		$this->log($message, self::LOG_ERROR);
	}

	/**
	 * Logs a message
	 *
	 * @param string  $message Message
	 * @param integer $type    Event severity (one of LOG_*)
	 */
	protected function log(string $message, int $type): void
	{
		$this->messages[] = [
			$type,
			$message,
			$this->getCurrentObject($this::TYPE_CONTROLLER),
			$this->getCurrentObject($this::TYPE_METHOD),
			$this->getCurrentObject($this::TYPE_PARAMETER),
		];
	}

	/**
	 * @return array
	 */
	public function logs()
	{
		return $this->messages;
	}

	/**
	 * @param int $code
	 *
	 * @return string
	 */
	public function errorName(int $code): string
	{
		return $this->errorNames[$code];
	}

	/**
	 * Returns all log messages with level lower than specified
	 *
	 * @param int $maxLevel
	 *
	 * @return string
	 */
	public function renderLog($maxLevel = self::LOG_NOTICE)
	{
		$result = "";
		foreach ($this->logs() as $log) {
			$level = $log[0];
			if ($level <= $maxLevel) {
				$result .= "\n[{$this->errorName($level)}] {$log[1]}";
				if ($level <= static::LOG_WARNING) {
					$result .= ". Controller: " . $log[2] . ", Method: " . $log[3] . ", Tag number: " . $log[4];
				}
			}
		}

		return $result;
	}

	/**
	 * @param int    $objectType
	 * @param string $objectName
	 */
	public function setCurrentObject(int $objectType, string $objectName)
	{
		static::$currentObjects[$objectType] = $objectName;
	}

	/**
	 * @param int $objectType
	 *
	 * @return null|string
	 */
	public function getCurrentObject(int $objectType)
	{
		return isset(static::$currentObjects[$objectType]) ? static::$currentObjects[$objectType] : null;
	}
}