<?php

namespace XF\SubContainer;

use XF\Api\Docs\AnnotationParser;
use XF\Api\Docs\ClassParser;
use XF\Api\Docs\Compiler;
use XF\Api\Docs\Renderer\RendererInterface;
use XF\Container;

class ApiDocs extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['parser.annotations'] = function (Container $c)
		{
			$class = $this->extendClass(AnnotationParser::class);
			return new $class();
		};
		$container['parser.class'] = function (Container $c)
		{
			$class = $this->extendClass(ClassParser::class);
			return new $class($c['parser.annotations']);
		};
		$container['compiler'] = function (Container $c)
		{
			$class = $this->extendClass(Compiler::class);
			return new $class($c['parser.annotations'], $c['parser.class']);
		};

		$container->factory('renderer', function ($type, array $params, Container $c)
		{
			$map = $c['rendererMap'];
			if (isset($map[$type]))
			{
				$type = $map[$type];
			}

			$class = \XF::stringToClass($type, '%s\Api\Docs\Renderer\%s');
			$class = $this->extendClass($class);

			if (!class_exists($class))
			{
				throw new \InvalidArgumentException("Unknown renderer class '$class'");
			}

			return new $class();
		}, false);

		$container['rendererMap'] = function (Container $c)
		{
			return [
				'simpleHtml' => 'XF:SimpleHtml',
				'xf2Html' => 'XF:Xf2Html',
			];
		};
	}

	/**
	 * @return AnnotationParser
	 */
	public function annotationParser()
	{
		return $this->container['parser.annotations'];
	}

	/**
	 * @return ClassParser
	 */
	public function classParser()
	{
		return $this->container['parser.class'];
	}

	/**
	 * @return Compiler
	 */
	public function compiler()
	{
		return $this->container['compiler'];
	}

	/**
	 * @param string $type
	 *
	 * @return RendererInterface
	 */
	public function renderer($type)
	{
		return $this->container->create('renderer', $type);
	}
}
