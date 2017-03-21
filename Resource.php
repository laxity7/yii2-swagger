<?php

namespace laxity7\swagger;

class Resource extends SwaggerObject
{
	public $parameters;

	protected $path;
	protected $tags;

	function __construct(array $config)
	{
		parent::__construct();
		foreach ($config as $key => $value) {
			$this->$key = $value;
		}
	}

	public function addOperation(Operation $operation)
	{
		$method = $operation->getMethod();
		if (isset($this->tags)) {
			$operation->addTags($this->tags);
		}
		$this->$method = $operation;
	}

	public function getPath()
	{
		return $this->path;
	}
}
