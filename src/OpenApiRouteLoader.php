<?php

declare(strict_types=1);

namespace Tobion\OpenApiSymfonyRouting;

use Swagger\Annotations\Operation;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class OpenApiRouteLoader implements RouteLoaderInterface
{
    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var array<string, int>
     */
    private $routeNames = [];

    public function __construct(Finder $finder)
    {
        $this->finder = $finder;
    }

    public static function fromDirectories(string $dir, string ...$moreDirs): self
    {
        return new self(
            (new Finder())->in($dir)->in($moreDirs)->files()->name('*.php')->sortByName()->followLinks()
        );
    }

    /**
     * Looks for OpenAPI/Swagger annotations in the symfony flex default "src" directory based on a composer install.
     */
    public static function fromSrcDirectory(): self
    {
        return self::fromDirectories(__DIR__.'/../../../../src');
    }

    public function __invoke(): RouteCollection
    {
        $fullSwagger = \Swagger\scan($this->finder);
        $routeCollection = new RouteCollection();

        $globalFormatSuffixConfig = FormatSuffixConfig::fromAnnotation($fullSwagger);

        foreach ($fullSwagger->paths as $path) {
            $pathFormatSuffixConfig = FormatSuffixConfig::fromAnnotation($path, $globalFormatSuffixConfig);

            $this->addRouteFromSwaggerOperation($routeCollection, $path->get, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->put, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->post, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->delete, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->options, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->head, $pathFormatSuffixConfig);
            $this->addRouteFromSwaggerOperation($routeCollection, $path->patch, $pathFormatSuffixConfig);
        }

        $this->routeNames = [];

        return $routeCollection;
    }

    private function addRouteFromSwaggerOperation(RouteCollection $routeCollection, ?Operation $operation, FormatSuffixConfig $parentFormatSuffixConfig): void
    {
        if (null === $operation) {
            return;
        }

        $controller = $this->getControllerFromSwaggerOperation($operation);
        $name = $this->getRouteName($operation, $controller);
        $route = $this->createRoute($operation, $controller, $parentFormatSuffixConfig);
        $routeCollection->add($name, $route);
    }

    private function createRoute(Operation $operation, string $controller, FormatSuffixConfig $parentFormatSuffixConfig): Route
    {
        $formatSuffixConfig = FormatSuffixConfig::fromAnnotation($operation, $parentFormatSuffixConfig);

        $path = $formatSuffixConfig->enabled ? $operation->path.'.{_format}' : $operation->path;
        $route = new Route($path);
        $route->setMethods($operation->method);
        $route->setDefault('_controller', $controller);

        if ($formatSuffixConfig->enabled) {
            $route->setDefault('_format', null);

            if (null !== $formatSuffixConfig->pattern) {
                $route->setRequirement('_format', $formatSuffixConfig->pattern);
            }
        }
        if (null !== $operation->parameters) {
            foreach ($operation->parameters as $parameter) {
                if ('path' === $parameter->in && null !== $parameter->pattern) {
                    $route->setRequirement($parameter->name, $parameter->pattern);
                }
            }
        }

        return $route;
    }

    private function getControllerFromSwaggerOperation(Operation $operation): string
    {
        $classOrService = ltrim($operation->_context->fullyQualifiedName($operation->_context->class), '\\');

        return $classOrService.'::'.$operation->_context->method;
    }

    private function getRouteName(Operation $operation, string $controller): string
    {
        return \Swagger\UNDEFINED === $operation->operationId ? $this->getDefaultRouteName($controller) : $operation->operationId;
    }

    /**
     * @see \Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader::getDefaultRouteName
     */
    private function getDefaultRouteName(string $controller): string
    {
        $name = str_replace(['\\', '::'], '_', $controller);
        $name = \function_exists('mb_strtolower') && preg_match('//u', $name) ? mb_strtolower($name, 'UTF-8') : strtolower($name);

        $name = preg_replace([
            '/(bundle|controller)_/',
            '/action(_\d+)?$/',
            '/__/',
        ], [
            '_',
            '\\1',
            '_',
        ], $name);

        // handle several routes for the same controller
        if (isset($this->routeNames[$name])) {
            ++$this->routeNames[$name];

            $name .= '_'.$this->routeNames[$name];
        } else {
            $this->routeNames[$name] = 0;
        }

        return $name;
    }
}
