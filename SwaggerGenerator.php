<?php

namespace laxity7\swagger;

/**
 * Class SwaggerGenerator generates Swagger API docs from the given controllers.
 *
 * You can configure the generator by adding an array to your application config under components as shown in the
 * following example:
 *
 *```
 *'swagger' => [
 *  'baseNamespace'=>'app\controllers'
 *  'inputDirs'=>[
 *      '../a',
 *      '../b',
 *      'pathPrefix'=>'../c'
 *   ],
 *  'outputDir'=>'../docs', //Will generate swagger.json in this folder
 *  'filterByName'=>'Controller', //[optional] This option limits the files parsed to those with a certain string in
 * their name (usually Controller)
 *  'ignoreMethods'=>[ //Methods that should not be parsed. Supports regex
 *      '/.*rules$/',
 *      'methodName'
 *  ],
 *  'controllerPathRule'=>'', //Allows you to specify a regex rule that will extract a path suffix from controller
 * name.
 *  'methodPathRule'=>'', //Allows you to specify a regex rule that will extract a path suffix from method name. Useful
 * for frameworks that use a naming convention for methods (Yii2: actionIndex)
 *  'application'=>[ //this section describes the application. For additional info see {@link
 * http://swagger.io/specification/}
 *      'info'=>[
 *          'title'=>'My App',
 *          'version'='1.0'
 *      ],
 *      'basePath'=>''
 *  ]
 *]```
 *
 * Your directories will be parsed in the order they are specified. You can specify a path prefix for a directory if
 * you wish (planned) Each found controller will be parsed.
 *
 * ## Variable types
 *
 * Simple types supported: integer, number, boolean, string, array
 * Generator will parse a Yii2 model if a full path is specified. Model can specify a scenario after a pipe or set
 * itself as array with []
 * `@api-response 200 app\modules\property\models\forms\PropertyForm[]|view_form Returns the list of properties
 *
 * ## Controller docblocks
 *
 * \@api-path - Provides a /<path> that will be appended to the base path and directory path (if specified).
 * E.g. if base path was set to /api/v2, dir path to /people and path here to /friends, resulting path will be:
 * /api/v2/people/friends. To that path method names/paths will be appended
 * \@api-param - Provide a path parameter for all methods of the controller.
 * e.g: `@api-param * integer name:plan-id in:path Plan ID
 *
 * ## Method docblocks
 * \@api-path - Sets a path of the method. Alternatively you can set method name rules in config `@api-path post
 * /create`
 * \@api-param - A parameter
 * ```php
 * @api-param    array in:formData name:test Description of the parameter "test"
 * ```
 * \@api-response - Response to operation. Syntax is: 'HTTP_CODE [type|inline schema|Model] Description'
 * ```php
 * @api-response 200 app\modules\property\models\forms\PropertyForm|view_form Returns the created property
 * ```
 */
class SwaggerGenerator extends SwaggerObject
{

	/** @var string Directory in which swagger.json will be generated */
	public $outputDir = '@app/docs/api';

	/** @var array Directories in which generated will look for controllers */
	public $inputDirs = ['@app/controllers'];

	/** @var string Only parse php files which contain the given string */
	public $filterByName;

	/** @var string Base api path to which resources paths will be appended. E.g /api */
	public $baseApiPath = '';

	/** @var array Description of the application for which we create documentation */
	public $application;

	/** @var string */
	public $basePath = '@app';

	public $baseNamespace;

	public $viewConfig = [
		'layout' => '',
	    'title' => ''
	];

	/** @var Application Object representing API structure */
	protected $object;

	protected $paths = [];

	protected $useStatements;

	public static $simpleTypes = [
		'integer' => ['int', 'integer'],
		'boolean' => ['boolean', 'bool'],
		'string',
		'number'  => ['number', 'float'],
		'array',
		'file',
	];

	public function init()
	{
		foreach ($this->inputDirs as &$dir) {
			$dir = \Yii::getAlias($dir);
		}
		$this->object = new Application($this->application, $this);
	}

	public function generate()
	{
		foreach ($this->inputDirs as $dir) {
			if (!is_dir($dir)) {
				$this->warning('Skipping dir ' . $dir . ': no directory at this address!');
				continue;
			}
			$directory = new \DirectoryIterator($dir);
			$this->parseDir($directory);
		}

		return $this->object->toArray();
	}

	/**
	 * Parses a Yii2 model
	 *
	 * @param string $path  Full class name with namespace
	 * @param string $title Optional title to set for the resulting schema
	 *
	 * @return array|Model Swagger schema
	 */
	public static function parseModel(string $path, string $title = null)
	{
		$models = static::getModelsFromString($path);
		if (is_null($models) | !class_exists($models[0])) {
			return null;
		}
		$isArray = $models[2];
		$schema = new Model($models[0], $models[1]);
		$schema->title = $title;
		if ($isArray) {
			return ['type' => 'array', 'items' => $schema];
		}

		return $schema;
	}

	public function getApplication()
	{
		return $this->object;
	}

	/**
	 * Converts various version of php types into ones used in swagger
	 *
	 * @param $type string
	 *
	 * @return array|null ['type'=>string] or ['type'=>'array','items'=>[]]
	 */
	public static function getCanonicalType($type): ?array
	{
		$isArray = preg_match('/(.*)\[\]$/', $type, $matches);
		if ($isArray) {
			$type = $matches[1];
		}
		foreach (static::$simpleTypes as $canonical => $typeNames) {
			if (is_array($typeNames) && in_array($type, $typeNames)) {
				return $isArray ? ['type' => 'array', 'items' => ['type' => $canonical]] : ['type' => $canonical];
			} elseif (is_string($typeNames) && $type === $typeNames) {
				return $isArray ? ['type' => 'array', 'items' => ['type' => $typeNames]] : ['type' => $typeNames];
			}
		}

		return null;
	}

	/**
	 * Determines whether a Yii2 Model with given full name exists. Allows for the usage of scenarios after vertical
	 * bar (Form|update)
	 *
	 * @param string $className
	 *
	 * @return array}null ['model_path','scenario','is_array']
	 */
	public static function getModelsFromString(string $className): array
	{
		$result = [];
		$parts = preg_split('/\|/', $className, -1, PREG_SPLIT_NO_EMPTY);
		$result[1] = isset($parts[1]) ? $parts[1] : 'default';
		$classPath = $parts[0];
		$isArray = preg_match('/(.*)\[\]/', $classPath, $matches);
		if ($isArray) {
			$result[0] = $matches[1];
			$result[2] = true;
		} else {
			$result[0] = $classPath;
			$result[2] = false;
		}

		return $result;
	}

	protected function parseDir(\DirectoryIterator $dir)
	{
		foreach ($dir as $item) {
			if ($item->isDot()) {
				continue;
			} elseif ($item->isFile()) {
				$this->parseFile($item);
			} elseif ($item->isDir()) {
				$this->parseDir($item);
			} else {
				$this->warning("Found an item that is neither a file nor a directory" . var_export($item, true));
			}
		}
	}

	protected function parseFile(\DirectoryIterator $file)
	{
		if (isset($this->filterByName)) {
			$isController = preg_match($this->filterByName, $file->getFilename());
			if (!$isController) {
				return false;
			}
		}
		$path = $file->getRealPath();
		$namespace = $this->namespaceFromPath($path, \Yii::getAlias($this->basePath), $this->baseNamespace);
		$name = preg_replace('/\.php/', '', $file->getBasename());
		$this->setCurrentObject(static::TYPE_CONTROLLER, $name);

		return $this->object->addMethodsFromController($namespace . $name);
	}

	/**
	 * Retrieves the part of the path from [$basePath] to [$path] and
	 * converts it to namespace according to PSR-4.
	 *
	 * @param        $path
	 * @param        $basePath
	 * @param string $baseNamespace optional base namespace to which result will be appended (app\\)
	 *
	 * @return string
	 */
	public function namespaceFromPath($path, $basePath, $baseNamespace)
	{
		$separator = '\\\\|\/';
		$baseParts = preg_split('/' . $separator . '/', $basePath);
		$parts = preg_split('/' . $separator . '/', $path);
		array_pop($parts);
		$relParts = array_diff($parts, $baseParts);
		$result = $baseNamespace . implode('\\', $relParts) . '\\';

		return $result;
	}

	/**
	 * Retrieves all use statements from a file
	 *
	 * @param string $fileName Path to the file
	 *
	 * @return array ['class name'=>'class name with a namespace']
	 */
	public static function getUseStatements(string $fileName)
	{
		$file = file($fileName);
		$result = [];
		foreach ($file as $line) {
			if (preg_match('/^use (.*);\\r?\\n?$/', $line, $matches)) {
				$fullClass = $matches[1];
				$nmParts = preg_split('/\/|\\\\/', $fullClass);
				$class = $nmParts[count($nmParts) - 1];
				$result[$class] = $fullClass;
			}
		}

		return $result;
	}

	public function parseTagsTag($text)
	{

	}

}
