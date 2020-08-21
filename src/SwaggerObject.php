<?php

namespace laxity7\yii2\gendoc;

use yii\base\BaseObject;
use yii\base\UnknownPropertyException;

class SwaggerObject extends BaseObject implements LogInterface
{
	use LogTrait;

	const METHOD_GET = 'get';
	const METHOD_POST = 'post';
	const METHOD_PUT = 'put';
	const METHOD_OPTIONS = 'options';
	const METHOD_HEAD = 'head';
	const METHOD_DELETE = 'delete';

	const IN_FORM_DATA = 'formData';
	const IN_QUERY = 'query';
	const IN_HEADER = 'header';
	const IN_PATH = 'path';
	const IN_BODY = 'body';

	/** @var array */
	protected $allowedMethods = [self::METHOD_GET,self::METHOD_POST,self::METHOD_PUT,self::METHOD_OPTIONS,self::METHOD_HEAD,self::METHOD_DELETE];

	/**
	 * @param bool $skipNull
	 *
	 * @return array
	 */
	public function toArray(bool $skipNull = true) : array
	{
		$this->beforeToArray();

		return ArrayHelper::entityToArray($this, $skipNull);
	}

	protected function beforeToArray()
	{
	}

	/**
	 * Determines whether an HTTP method is a valid one
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	protected function isValidMethod(string $method): bool
	{
		return in_array(strtolower($method), $this->allowedMethods);
	}

	/** @inheritdoc */
	public function __set($name, $value)
	{
		try {
			parent::__set($name, $value);
		} catch (UnknownPropertyException $e) {
			$this->$name = $value;
		}
	}
}
