<?php

namespace laxity7\yii2\gendoc;

use phpDocumentor\Reflection\DocBlock\Tag;
use yii\base\Model;

class MethodParameter extends SwaggerObject
{

	public $required;

	public $name;

	public $description;

	public $in;

	//IN_BODY
	public $schema;

	//Other
	public $type;

	public $format;

	public $minLength;

	public $maxLength;

	public $min;

	public $max;

	/** @var boolean Shows whether this is a file parameter */
	protected $_isFile = false;

	/** @var string mime type this parameter consumes */
	protected $_consumes;

	function __construct(Tag $tag)
	{
		parent::__construct();
		$doc = $tag->getDescription();
		$this->notice('=== Parameter');
		$attributes = $this->getMethodParamAttributes($doc);
		if (!isset($attributes['in'])) {
			$this->error('Attribute "in" not specified');
			throw new \InvalidArgumentException();
		}
		if (!isset($attributes['name'])) {
			$this->error('Attribute "name" not specified');
			throw new \InvalidArgumentException();
		}
		if (!isset($attributes['type']) && !isset($attributes['schema'])) {
			$this->error('Neither "type" nor "schema" specified');
			throw new \InvalidArgumentException();
		}
		\Yii::configure($this, $attributes);
	}

	/**
	 * Parses a string containing api-param description.
	 * Example string: in:formData type:array Description
	 *
	 * @param string $description
	 *
	 * @return array parsed tags ['description'=>'Description of the parameter', 'in'=>'How to pass parameter', 'type'=>'Parameter type']
	 */
	protected function getMethodParamAttributes($description)
	{
		$result = [];
		$options = preg_split('/ /', $description);

		if (count($options) < 2) {
			$this->warning('Parameter must have at least type and description');

			return [];
		}
		//parsing two first attributes: can be required and type or just type
		$first = $options[0];
		$second = $options[1];
		if ($first === '*' || $second === '*') {
			$result['required'] = true;
			$type = $first === '*' ? $second : $first;
			array_splice($options, 0, 2);
		} else {
			$type = $first;
			$result['required'] = false;
			array_splice($options, 0, 1);
		}

		$canType = SwaggerGenerator::getCanonicalType($type);
		if (!is_null($canType)) {
			$result = array_merge($result, $canType);
			if ($canType['type'] === 'file') {
				$this->_isFile = true;
				$this->_consumes = 'multipart/form-data';
			}
		} else {
			/** @var Model $schema */
			$schema = SwaggerGenerator::parseModel($type, $this->name);
			if (!is_null($schema)) {
				$result['schema'] = $schema;
			} else {
				$this->warning('Unknown parameter type: ' . $type);

				return [];
			}
		}
		$descStart = -1; //split part at which description starts
		for ($i = 0, $l = count($options); $i < $l; $i++) {
			$option = $options[$i];
			$paramParse = preg_split('/:/', $option);
			if (count($paramParse) !== 2) {
				$descStart = $i;
				break;
			}
			$result[$paramParse[0]] = $paramParse[1];
		}
		if ($descStart === -1) {
			$this->warning('Parameter does not have a description');
		} else {
			$result['description'] = implode(' ', array_slice($options, $descStart));
		}

		return $result;
	}

	public function isFile()
	{
		return $this->_isFile;
	}

	public function consumes()
	{
		return $this->_consumes;
	}
}
