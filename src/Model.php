<?php

namespace laxity7\yii2\gendoc;

use ReflectionProperty;

class Model extends SwaggerObject
{

	/** @var string Name of the file containing the class */
	protected $file;

	/** @var string Class name */
	protected $className;

	/** @var string Full namespace of the class */
	protected $namespace;

	/** @var string Full class name (with namespace) */
	protected $fullName;

	/** @var string Scenario set for the parsed model */
	protected $_scenario = 'default';

	/** @var ReflectionProperty Name of the property currently being parsed */
	protected $_curProperty;

	/** @var array Array of properties extracted from the model (swagger-compatible format) */
	public $properties;

	/** @var string[] Required fields */
	public $required;

	/** @var string Swagger object type (only 'object') */
	public $type = 'object';

	/** @var string Model title */
	public $title;

	function __construct(string $className, string $scenario)
	{
		parent::__construct();
		$this->_scenario = $scenario;
		$rf = new \ReflectionClass($className);
		$this->file = $rf->getFileName();
		$this->fullName = $rf->getName();
		$this->className = $rf->getShortName();
		$this->namespace = $rf->getNamespaceName();

		$this->notice('==== Model ' . $this->fullName);

		$this->getProperties($rf);

	}

	protected function getProperties(\ReflectionClass $rf)
	{
		$this->properties = [];

		$props = $this->getScenarioProperties($rf);
		foreach ($props as $index => $prop) {
			$name = $prop['name'];
			$this->_curProperty = $prop['property'];
			$comment = $prop['comment'];
			preg_match('/@var (.*)\*\//s ', $comment, $matches);
			$commentClean = isset($matches[1]) ? preg_replace('/[*]/', '', $matches[1]) : '';
			$parts = preg_split('/ /', $commentClean, 2, PREG_SPLIT_NO_EMPTY);
			if (count($parts) !== 2) {
				$this->error('Malformed phpdoc for property (' . $name . '), skipping');
				continue;
			}
			$property = $this->parsePropertyType($parts);
			if ($property === false) {
				$this->error('Unknown type ' . $parts[0] . ' for property ' . $name . ', skipping');
				continue;
			}
			$this->properties[$name] = $property;
		}
	}

	/**
	 * Determines property type and creates a description
	 *
	 * @param array $parts Parts of the tag string
	 *
	 * @return array|bool
	 */
	protected function parsePropertyType(array $parts)
	{
		$type = SwaggerGenerator::getCanonicalType($parts[0]);
		if (is_null($type)) {
			$children = $this->getChildModels($parts[0]);
			if (is_null($children)) {
				return false;
			} else {
				$property = $children;
			}
		} else {
			$property = $type;
		}
		$descr = $parts[1];
		$property['description'] = $descr;

		return $property;
	}

	/**
	 * Looks for the child model classes in variable type declaration
	 *
	 * @param string $type Variable type
	 *
	 * @return Model|array|null Returns parsed model, swagger description of an array of models or null if not found
	 */
	protected function getChildModels(string $type)
	{
		$isModelArray = preg_match('/(.*)\[\]$/', $type, $matches);
		if ($isModelArray === 1) {
			$modelPath = $this->findClass($matches[1]);
			if (is_null($modelPath)) {
				$this->error('Cannot parse array of child models - model class not found (' . $type . ')');

				return null;
			}
			if ($this->fullName == $modelPath) { //Recursion
				$model = clone $this;
			} else {
				$model = new Model($modelPath, $this->_scenario);
			}
			$model->title = $this->_curProperty->name;
			$models = ['type' => 'array', 'items' => $model];

			return $models;
		}
		$modelPath = $this->findClass($type);
		if (is_null($modelPath)) {
			return null;
		}
		if ($this->fullName == $modelPath) {
			$model = clone $this;
		} else {
			$model = new Model($modelPath, $this->_scenario);
		}
		$model->title = $this->_curProperty->name;

		return $model->toArray();
	}

	/**
	 * Looks for the existing class by its name. Checks whether it can be initialized using its name, one of the use
	 * statements or whether it is in the same namespace as the model
	 *
	 * @param string $className Class name
	 *
	 * @return null|string Full class name or null if not found
	 */
	protected function findClass($className)
	{
		$curProperty = $this->_curProperty;
		$dC = $curProperty->getDeclaringClass();
		$useStatements = SwaggerGenerator::getUseStatements($dC->getFileName());
		if (class_exists($className)) {
			return $className;
		} elseif (array_key_exists($className, $useStatements) && class_exists($useStatements[$className])) {
			return $useStatements[$className];
		} elseif (class_exists($dC->getNamespaceName() . '\\' . $className)) {
			return $dC->getNamespaceName() . '\\' . $className;
		}

		return null;
	}

	/**
	 * Retrieves all parameters that are active in the current scenario
	 *
	 * @param \ReflectionClass $rf
	 *
	 * @return array
	 */
	private function getScenarioProperties(\ReflectionClass $rf)
	{
		$properties = $rf->getProperties();
		$scenarios = $this->getScenarios($rf);
		if (!isset($scenarios[$this->_scenario])) {
			return [];
		}
		$result = [];
		$attributes = $scenarios[$this->_scenario];
		foreach ($properties as $property) {
			$name = $property->getName();
			$attr = [];

			$attribute = array_values(preg_grep('/(\*)*' . $name . '/', $attributes));
			if (empty($attribute)) {
				continue;
			}
			$item = $attribute[0];
			$item = preg_replace('/\*/', '', $item, -1, $count);
			if ($item != $name) {
				continue;
			}
			$attr['name'] = $name;
			$attr['comment'] = $property->getDocComment();
			$attr['property'] = $property;
			if ($count !== 0) {
				$this->addRequiredProperty($attr['name']);
			}
			$result[] = $attr;
		}

		return $result;
	}

	/**
	 * Retrieves a scenarios from yii models
	 *
	 * @param \ReflectionClass $rf
	 *
	 * @return array
	 */
	protected function getScenarios(\ReflectionClass $rf)
	{
		if (!$rf->isInstantiable()) {
			$this->error('Failed to instantiate class ' . $this->fullName);

			return [];
		}
		/** @var \yii\base\Model $model */
		$model = new $this->fullName();
		$scenarios = $model instanceof \yii\base\Model ? $model->scenarios() : [];

		return $scenarios;
	}

	/**
	 * Adds a property to the list of required ones
	 *
	 * @param string $name Property name
	 */
	private function addRequiredProperty($name)
	{
		if (!isset($this->required)) {
			$this->required = [];
		}
		$this->required[] = $name;
	}
}
