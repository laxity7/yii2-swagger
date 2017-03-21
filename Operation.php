<?php

namespace laxity7\swagger;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use ReflectionMethod;

class Operation extends SwaggerObject
{

	public $summary;

	public $description;

	public $produces;

	public $parameters;

	public $responses;

	public $tags;

	/** @var array Types of content this operation consumes */
	public $consumes;

	/** @var Tag[] All api-param tags */
	protected $paramTags;

	/** @var Tag[] All api-response tags */
	protected $responseTags;

	/** @var string REST method */
	protected $method;

	protected $path;

	public function fields()
	{
		return ['summary', 'description', 'produces', 'parameters', 'responses'];
	}

	function __construct(ReflectionMethod $method)
	{
		parent::__construct();
		$db = new DocBlock($method);
		$this->method = $this->parseMethodTag($method);
		$this->path = $this->parsePathTag($method);
		$this->parseTagsTag($method);

		$this->notice('== Method "' . $method->getName() . '"');
		$this->setCurrentObject(self::TYPE_METHOD, $method->name);
		$this->processTags($db);

		$params = $this->getMethodParams() ?: null;
		$responses = $this->getMethodResponses();
		$this->summary = $db->getShortDescription();
		$this->description = $db->getLongDescription()
		                        ->getContents();
		$this->responses = $responses ?: null;
		$this->parameters = $params ?: null;
	}

	/**
	 * Retrieves the HTTP method for the operation
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Retrieves all parameters specified for the method
	 * todo: default "in" parameters: for post is formData, for get in url
	 * @return MethodParameter[]
	 */
	private function getMethodParams(): array
	{
		$params = $this->paramTags;
		$result = [];
		foreach ($params as $number => $param) {
			try {
				$this->setCurrentObject(self::TYPE_PARAMETER, $number);
				$pres = new MethodParameter($param);

				if ($pres->isFile()) {
					$this->addConsumeType($pres->consumes());
				}
				$result[] = $pres;
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	protected function getMethodResponses(): array
	{
		$result = [];
		foreach ($this->responseTags as $responseTag) {
			try {
				$resp = new MethodResponse($responseTag);
				$result[$resp->getCode()] = $resp;
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}
		if (empty($result)) {
			$this->warning('No responses set for a method! Setting a default response "ok"');
			$result[200] = ['description' => 'ok'];
		}

		return $result;
	}

	/**
	 * Sorts all tags of the method into groups
	 *
	 * @param DocBlock $docBlock
	 */
	protected function processTags(DocBlock $docBlock): void
	{
		$this->responseTags = $docBlock->getTagsByName('api-response');
		$this->paramTags = $docBlock->getTagsByName('api-param');
	}

	/**
	 * Determines HTTP method of the function
	 *
	 * @param ReflectionMethod $method
	 *
	 * @return string HTTP method
	 */
	private function parseMethodTag(ReflectionMethod $method): string
	{
		$docblock = new DocBlock($method);
		$methodTags = $docblock->getTagsByName('api-method');
		if (!isset($methodTags[0])) {
			$this->debug('No method tag for method ' . $method->name . ', skipping');
			throw new \InvalidArgumentException('No method tag for the method');
		}
		$mth = $methodTags[0]->getContent();
		if (!$this->isValidMethod($mth)) {
			$this->warning('Unsupported REST method: ' . $mth);
			throw new \InvalidArgumentException('Unsupported REST method');
		}

		return $mth;
	}

	/**
	 * @param ReflectionMethod $method
	 *
	 * @return string
	 */
	private function parsePathTag(ReflectionMethod $method): string
	{
		$docblock = new DocBlock($method);
		$pathTags = $docblock->getTagsByName('api-path');
		if (!isset($pathTags[0])) {
			return '';
		}

		return $pathTags[0]->getContent();
	}

	/**
	 * @param ReflectionMethod $method
	 */
	private function parseTagsTag(ReflectionMethod $method): void
	{
		$docblock = new DocBlock($method);
		$tagsTags = $docblock->getTagsByName('api-tags');
		if (!isset($tagsTags[0])) {
			return;
		}
		$tag = preg_replace('/ /', '', preg_replace('/(@api-tags)/', '', $tagsTags[0]));
		$this->addTags(explode(',', $tag));
	}

	/**
	 * Adds tags to the operation
	 *
	 * @param array $tags
	 */
	public function addTags(array $tags): void
	{
		if (empty($this->tags)) {
			$this->tags = $tags;
		} else {
			$this->tags = array_merge($this->tags, $tags);
		}
	}

	/**
	 * Adds a consume type
	 *
	 * @param string $type Mime-type like "multipart/form-data"
	 */
	public function addConsumeType(string $type): void
	{
		if (is_null($this->consumes)) {
			$this->consumes = [];
		}
		$this->consumes[] = $type;
	}
}
