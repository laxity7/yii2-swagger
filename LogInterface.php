<?php
/**
 * Created by Vlad Varlamov (laxity.ru) on 21.03.2017.
 */

namespace laxity7\swagger;

interface LogInterface
{
	const LOG_DEBUG = 5;
	const LOG_NOTICE = 3;
	const LOG_WARNING = 2;
	const LOG_ERROR = 1;

	const TYPE_CONTROLLER = 0;
	const TYPE_MODEL = 1;
	const TYPE_METHOD = 2;
	const TYPE_PARAMETER = 3;

	/**
	 * @param string $message
	 */
	public function warning(string $message);

	/**
	 * @param string $message
	 */
	public function notice(string $message);

	/**
	 * @param string $message
	 */
	public function debug(string $message);

	/**
	 * @param string $message
	 */
	public function error(string $message);

}