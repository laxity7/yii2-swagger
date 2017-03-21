<?php
namespace laxity7\swagger;

use phpDocumentor\Reflection\DocBlock\Tag;

class MethodResponse extends SwaggerObject
{

	public $description;

	public $schema;

	protected $_code;

	function __construct(Tag $tag)
	{
		parent::__construct();
		$this->getAttributes($tag->getDescription());
		$this->notice('=== Response ');
	}

	public function getCode()
	{
		return $this->_code;
	}

	protected function getAttributes($description)
	{
		$attributes = preg_split('/ /', $description, 2);

		if (count($attributes) < 2) {
			$this->warning('Response must have at least status and description, skipping');
			throw new \InvalidArgumentException();
		}
		//parsing two first attributes: can be required and type or just type
		$code = $attributes[0];
		if (is_int($code)) {
			$this->warning('Response does not have a valid code, skipping');
			throw new \InvalidArgumentException();
		}
		$this->_code = $code;
		$inlineSchema = $this->getInlineSchema($attributes[1]);
		if ($inlineSchema) {
			$schema = json_decode($inlineSchema);
			if (is_null($schema)) {
				$this->warning('Failed to parse response schema, skipping');
				throw new \InvalidArgumentException();
			}
			$this->schema = $schema;
			$this->description = substr($attributes[1], strlen($inlineSchema) + 1);
		} else {
			$attr2 = preg_split('/ /', $attributes[1], 2);
			if (count($attr2) === 1) { //just a description
				$this->description = $attr2[0];

				return;
			}
			$type = $attr2[0];
			$canType = SwaggerGenerator::getCanonicalType($type);
			if (!is_null($canType)) {
				$this->schema = $canType;
				$this->description = $attr2[1];
			} else {
				$schema = SwaggerGenerator::parseModel($type);
				if (!is_null($schema)) {
					$this->schema = $schema;
					$this->description = $attr2[1];
				} else {
					$this->description = $attr2[0] . ' ' . $attr2[1];
				}
			}
		}
	}

	/**
	 * Looks for the schema definition in a string
	 * Schema must be
	 *
	 * @param $string
	 *
	 * @return bool
	 */
	protected function getInlineSchema($string)
	{
		$found = preg_match('/^{.*}/', $string, $matches);
		if ($found) {
			return $matches[0];
		} else {
			return false;
		}
	}
}
