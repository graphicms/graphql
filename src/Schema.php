<?php

namespace Graphicms\GraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\Utils;
use Traversable;

class Schema extends \GraphQL\Type\Schema
{
    private $resolvedTypes = [];

    private $config;

    private $fullyLoaded = false;

    private $validationErrors;

    public function __construct($config)
    {
        if (is_array($config)) {
            $config = SchemaConfig::create($config);
        }

        // If this schema was built from a source known to be valid, then it may be
        // marked with assumeValid to avoid an additional type system validation.
        if ($config->getAssumeValid()) {
            $this->validationErrors = [];
        } else {
            // Otherwise check for common mistakes during construction to produce
            // clear and early error messages.
            Utils::invariant(
                $config instanceof SchemaConfig,
                'Schema constructor expects instance of GraphQL\Type\SchemaConfig or an array with keys: %s; but got: %s',
                implode(
                    ', ',
                    [
                        'query',
                        'mutation',
                        'subscription',
                        'types',
                        'directives',
                        'typeLoader',
                    ]
                ),
                Utils::getVariableType($config)
            );
            Utils::invariant(
                ! $config->types || is_array($config->types) || is_callable($config->types),
                '"types" must be array or callable if provided but got: ' . Utils::getVariableType($config->types)
            );
            Utils::invariant(
                ! $config->directives || is_array($config->directives),
                '"directives" must be Array if provided but got: ' . Utils::getVariableType($config->directives)
            );
        }

        $this->config            = $config;
        $this->extensionASTNodes = $config->extensionASTNodes;

        if ($config->query) {
            $this->resolvedTypes[$config->query->name] = $config->query;
        }
        if ($config->mutation) {
            $this->resolvedTypes[$config->mutation->name] = $config->mutation;
        }
        if ($config->subscription) {
            $this->resolvedTypes[$config->subscription->name] = $config->subscription;
        }
        if (is_array($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                if (isset($this->resolvedTypes[$type->name])) {
                    Utils::invariant(
                        $type === $this->resolvedTypes[$type->name],
                        sprintf(
                            'Schema must contain unique named types but contains multiple types named "%s" (see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
                            $type
                        )
                    );
                }
                $this->resolvedTypes[$type->name] = $type;
            }
        }
        $this->resolvedTypes += Type::getStandardTypes() + Introspection::getTypes();

        if ($this->config->typeLoader) {
            return;
        }

        // Perform full scan of the schema
        $this->getTypeMap();
    }

    public function getType($name)
    {
        if (! isset($this->resolvedTypes[$name])) {
            $type = $this->loadType($name);
            if (! $type) {
                return null;
            }
            $this->resolvedTypes[$name] = $type;
        }

        return $this->resolvedTypes[$name];
    }


    public function getTypeMap()
    {
        if (! $this->fullyLoaded) {
            $this->resolvedTypes = $this->collectAllTypes();
            $this->fullyLoaded   = true;
        }

        return $this->resolvedTypes;
    }

    public function getDirectives()
    {
        return $this->config->directives ?: GraphQL::getStandardDirectives();
    }

    public function getQueryType()
    {
        return $this->config->query;
    }

    public function getMutationType()
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription
     *
     * @return ObjectType|null
     *
     * @api
     */
    public function getSubscriptionType()
    {
        return $this->config->subscription;
    }

    /**
     * @return SchemaConfig
     *
     * @api
     */
    public function getConfig()
    {
        return $this->config;
    }

    private function collectAllTypes()
    {
        $typeMap = [];
        foreach ($this->resolvedTypes as $type) {
            $typeMap = TypeInfo::extractTypes($type, $typeMap);
        }
        foreach ($this->getDirectives() as $directive) {
            if (!($directive instanceof Directive)) {
                continue;
            }

            $typeMap = TypeInfo::extractTypesFromDirectives($directive, $typeMap);
        }
        // When types are set as array they are resolved in constructor
        if (is_callable($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                $typeMap = TypeInfo::extractTypes($type, $typeMap);
            }
        }

        return $typeMap;
    }

    private function resolveAdditionalTypes()
    {
        $types = $this->config->types ?: [];

        if (is_callable($types)) {
            $types = $types();
        }

        if (! is_array($types) && ! $types instanceof Traversable) {
            throw new InvariantViolation(sprintf(
                'Schema types callable must return array or instance of Traversable but got: %s',
                Utils::getVariableType($types)
            ));
        }

        foreach ($types as $index => $type) {
            if (! $type instanceof Type) {
                throw new InvariantViolation(sprintf(
                    'Each entry of schema types must be instance of GraphQL\Type\Definition\Type but entry at %s is %s',
                    $index,
                    Utils::printSafe($type)
                ));
            }
            yield $type;
        }
    }

    private function loadType($typeName)
    {
        $typeLoader = $this->config->typeLoader;

        if (! $typeLoader) {
            return $this->defaultTypeLoader($typeName);
        }

        $type = $typeLoader($typeName);

        if (! $type instanceof Type) {
            throw new InvariantViolation(
                sprintf(
                    'Type loader is expected to return valid type "%s", but it returned %s',
                    $typeName,
                    Utils::printSafe($type)
                )
            );
        }
        if ($type->name !== $typeName) {
            throw new InvariantViolation(
                sprintf('Type loader is expected to return type "%s", but it returned "%s"', $typeName, $type->name)
            );
        }

        return $type;
    }

    private function defaultTypeLoader($typeName)
    {
        // Default type loader simply fallbacks to collecting all types
        $typeMap = $this->getTypeMap();

        return $typeMap[$typeName] ?? null;
    }
}