<?php

namespace Graphicms\GraphQL;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\Utils;

class TypeInfo extends \GraphQL\Utils\TypeInfo
{
    public static function extractTypes($type, ?array $typeMap = null)
    {
        if (! $typeMap) {
            $typeMap = [];
        }
        if (! $type) {
            return $typeMap;
        }

        if ($type instanceof WrappingType) {
            return self::extractTypes($type->getWrappedType(true), $typeMap);
        }

        if (! $type instanceof Type) {
            // Preserve these invalid types in map (at numeric index) to make them
            // detectable during $schema->validate()
            $i            = 0;
            $alreadyInMap = false;
            while (isset($typeMap[$i])) {
                $alreadyInMap = $alreadyInMap || $typeMap[$i] === $type;
                $i++;
            }
            if (! $alreadyInMap) {
                $typeMap[$i] = $type;
            }
            return $typeMap;
        }

        if (! empty($typeMap[$type->name])) {
            Utils::invariant(
                $typeMap[$type->name] === $type,
                sprintf('Schema must contain unique named types but contains multiple types named "%s" ', $type) .
                '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
            );

            return $typeMap;
        }
        $typeMap[$type->name] = $type;

        $nestedTypes = [];

        if ($type instanceof UnionType) {
            $nestedTypes = $type->getTypes();
        }
        if ($type instanceof ObjectType) {
            $nestedTypes = array_merge($nestedTypes, $type->getInterfaces());
        }
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            foreach ((array) $type->getFields() as $fieldName => $field) {
                if (! empty($field->args)) {
                    $fieldArgTypes = array_map(
                        static function (FieldArgument $arg) {
                            return $arg->getType();
                        },
                        $field->args
                    );

                    $nestedTypes = array_merge($nestedTypes, $fieldArgTypes);
                }
                $nestedTypes[] = $field->getType();
            }
        }
        if ($type instanceof InputObjectType) {
            foreach ((array) $type->getFields() as $fieldName => $field) {
                $nestedTypes[] = $field->getType();
            }
        }
        foreach ($nestedTypes as $type) {
            $typeMap = self::extractTypes($type, $typeMap);
        }

        return $typeMap;
    }
}