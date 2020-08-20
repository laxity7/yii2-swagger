<?php

namespace laxity7\yii2\gendoc;

use phpDocumentor\Reflection\DocBlock;

class Application extends SwaggerObject implements \JsonSerializable
{
	public $swagger = "2.0";
	public $info;
	public $host;
	public $basePath;

	/** @var Resource[] */
	public $paths;
	public $tags;
	/** @var array Definitions of the objects used in the api */
	protected $definitions;

	function __construct(array $data = [])
	{
		parent::__construct();
		foreach ($data as $key => $param) {
			$this->$key = $param;
		}
		$this->paths = [];
	}

	/** @inheritdoc */
	public function jsonSerialize(): string
	{
		return json_encode($this->toArray());
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	public function addMethodsFromController(string $path): bool
	{
		try {
			$this->parseController($path);
		} catch (\InvalidArgumentException $e) {
			return false;
		}

		return true;
	}

	protected function parseController(string $path): void
	{
		$rf = new \ReflectionClass($path);
		$controllerTags = $this->processTags($rf);
		$this->notice('= Controller "' . $path . '"');
		$methods = $rf->getMethods();
		$validMethods = 0;
		foreach ($methods as $method) {
			if ($method->class === $rf->name) {
				$result = $this->addOperation($controllerTags, $method);
				if ($result) {
					$validMethods++;
				}
			}
		}
		if ($validMethods === 0) {
			$this->warning('No valid methods in controller, skipping');
			throw new \InvalidArgumentException('No methods in resource!');
		}
	}

	/**
	 * Adds an operation to this resource. Parses data from a method doc-block
	 *
	 * @param array             $controllerTags
	 * @param \ReflectionMethod $method
	 *
	 * @return bool Whether addition was successful
	 */
	public function addOperation(array $controllerTags, \ReflectionMethod $method)
	{
		$path = $controllerTags['path'];
		try {
			$operation = new Operation($method);
		} catch (\InvalidArgumentException $e) {
			return false;
		}
		$fullPath = $path . $operation->getPath(); //path to the actual resource. Compiled from api-path tags in controller and method
		if (!array_key_exists($fullPath, $this->paths)) {
			$this->paths[$fullPath] = new Resource($controllerTags);
		}
		$resource = $this->paths[$fullPath];
		$resource->addOperation($operation);

		return true;
	}

	/**
	 * Processes api-path tag for controller. Looks for path parameters
	 *
	 * @param \ReflectionClass $rfController
	 *
	 * @return array ['path','path-params'=>[['in','name'...],...], 'tags' => ['tag1','tag2']] Where path-params and tags are optional
	 */
	private function processTags(\ReflectionClass $rfController)
	{
		$result = [];
		$name = $rfController->getName();
		$docs = new DocBlock($rfController);
		$pathTag = $docs->getTagsByName('api-path');
		if (!isset($pathTag[0])) {
			$this->debug('Controller ' . $name . ' does not have the api-path tag, skipping');
			throw new \InvalidArgumentException('No api-path for controller');
		}
		$result['path'] = $pathTag[0]->getContent();
		preg_match_all('/{(.+)}/U', $result['path'], $pathParams);
		$params = [];
		if (!empty($pathParams)) {
			foreach ($pathParams[1] as $pathParam) {
				$params[] = [
					'in'       => 'path',
					'name'     => $pathParam,
					'required' => true,
					'type'     => 'integer',
				];
			}
			$result['parameters'] = $params;
		}
		$tagTag = $docs->getTagsByName('api-tags');
		if (isset($tagTag[0])) {
			$tag = preg_replace('/ /', '', preg_replace('/(@api-tags)/', '', $tagTag[0]));
			$result['tags'] = explode(',', $tag);
		}

		return $result;
	}

	public function addDefinition($path, $apiLink, $schema)
	{
	}
}
