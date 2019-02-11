<?php

namespace Graphicms\GraphQL;

use Graphicms\GraphQL\Support\DynamicInterface;
use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Config;
use Rebing\GraphQL\Error\AuthorizationError;
use Rebing\GraphQL\Error\ValidationError;
use Rebing\GraphQL\Exception\SchemaNotFound;
use Rebing\GraphQL\Support\PaginationType;

class GraphQL extends \Rebing\GraphQL\GraphQL
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    public function addNewScheme($name, $config = [])
    {
        Config::set('graphicms_graphql.schemas.'.$name, $config);
        $this->addSchema($name, $config);
    }

    public function addType($class, $name = null)
    {
        if(!$name)
        {
            $type = is_object($class) ? $class:app($class);
            $name = $type->name;
        }

        if(!array_key_exists($name, $this->types)) {
            $this->types[$name] = $class;
        }
    }

    public function addDynamicQuery(DynamicInterface $query, $schema = 'default')
    {
        if (!array_key_exists($schema, $this->schemas)) {
            throw new \Exception('Schema ['.$schema.'] is not defined.');
        }
        if(!isset($this->schemas[$schema]['query'])) {
            $this->schemas[$schema]['query'] = [];
        }
        array_push($this->schemas[$schema]['query'], $query);
    }

    public function addDynamicMutation(DynamicInterface $query, $schema = 'default')
    {
        if (!array_key_exists($schema, $this->schemas)) {
            throw new \Exception('Schema ['.$schema.'] is not defined.');
        }
        if(!isset($this->schemas[$schema]['mutation'])) {
            $this->schemas[$schema]['mutation'] = [];
        }
        array_push($this->schemas[$schema]['mutation'], $query);
    }

    public function objectType($type, $opts = [])
    {
        // If it's already an ObjectType, just update properties and return it.
        // If it's an array, assume it's an array of fields and build ObjectType
        // from it. Otherwise, build it from a string or an instance.
        $objectType = null;
        if ($type instanceof ObjectType) {
            $objectType = $type;
            foreach ($opts as $key => $value) {
                if (property_exists($objectType, $key)) {
                    $objectType->{$key} = $value;
                }
                if (isset($objectType->config[$key])) {
                    $objectType->config[$key] = $value;
                }
            }
        } elseif (is_array($type)) {
            $objectType = $this->buildObjectTypeFromFields($type, $opts);
        } else {
            $objectType = $this->buildObjectTypeFromClass($type, $opts);
        }

        return $objectType;
    }

    protected function buildObjectTypeFromFields($fields, $opts = [])
    {
        $typeFields = [];
        foreach ($fields as $name => $field) {
            if (is_string($field)) {
                $field = $this->app->make($field);
                $name = is_numeric($name) ? $field->name : $name;
                $field->name = $name;
                $field = $field->toArray();
            } else {
                if ($field instanceof DynamicInterface) {
                    $field = $field->deferred_type()->toArray();
                }
                $name = is_numeric($name) ? $field['name'] : $name;
                $field['name'] = $name;
            }
            $typeFields[$name] = $field;
        }

        return new ObjectType(array_merge([
            'fields' => $typeFields
        ], $opts));
    }

    public function queryAndReturnResult($query, $params = [], $opts = [])
    {
        $context = array_get($opts, 'context');
        $schemaName = array_get($opts, 'schema');
        $operationName = array_get($opts, 'operationName');

        $schema = $this->schema($schemaName);

        $errorFormatter = config('graphicms_graphql.error_formatter', [static::class, 'formatError']);
        $errorsHandler = config('graphicms_graphql.errors_handler', [static::class, 'handleErrors']);

        $result = GraphQLBase::executeQuery($schema, $query, null, $context, $params, $operationName)
            ->setErrorsHandler($errorsHandler)
            ->setErrorFormatter($errorFormatter);
        return $result;
    }

    public function paginate($typeName, $customName = null)
    {
        $name = $customName ?: $typeName . '_pagination';

        if (!isset($this->typesInstances[$name])) {
            $paginationType = config('graphicms_graphql.pagination_type', PaginationType::class);
            $this->typesInstances[$name] = new $paginationType($typeName, $customName);
        }

        return $this->typesInstances[$name];
    }

    public static function formatError(Error $e)
    {
        $debug = config('app.debug') ? (Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE) : 0;
        $formatter = FormattedError::prepareFormatter(null, $debug);
        $error = $formatter($e);

        $previous = $e->getPrevious();
        if ($previous && $previous instanceof ValidationError) {
            $error['validation'] = $previous->getValidatorMessages();
        }

        return $error;
    }

    public static function handleErrors(array $errors, callable $formatter)
    {
        $handler = app()->make(ExceptionHandler::class);
        foreach ($errors as $error) {
            // Try to unwrap exception
            $error = $error->getPrevious() ?: $error;
            // Don't report certain GraphQL errors
            if ($error instanceof ValidationError
                || $error instanceof AuthorizationError
                || !($error instanceof \Exception)) {
                continue;
            }
            $handler->report($error);
        }
        return array_map($formatter, $errors);
    }

    protected function getSchemaConfiguration($schema)
    {
        $schemaName = is_string($schema) ? $schema : config('graphicms_graphql.default_schema', 'default');

        if (!is_array($schema) && !isset($this->schemas[$schemaName])) {
            throw new SchemaNotFound('Type ' . $schemaName . ' not found.');
        }

        return is_array($schema) ? $schema : $this->schemas[$schemaName];
    }

    public function schema($schema = null)
    {
        if($schema instanceof \GraphQL\Type\Schema)
        {
            return $schema;
        }

//        $this->typesInstances = [];
        foreach($this->types as $name => $type)
        {
            $this->type($name);
        }

        $schema = $this->getSchemaConfiguration($schema);

        $schemaQuery = array_get($schema, 'query', []);
        $schemaMutation = array_get($schema, 'mutation', []);
        $schemaSubscription = array_get($schema, 'subscription', []);
        $schemaTypes = array_get($schema, 'types', []);

        //Get the types either from the schema, or the global types.
        $types = [];
        if (sizeof($schemaTypes)) {
            foreach ($schemaTypes as $name => $type) {
                $objectType = $this->objectType($type, is_numeric($name) ? []:[
                    'name' => $name
                ]);
                $this->typesInstances[$name] = $objectType;
                $types[] = $objectType;
            }
        } else {
            foreach ($this->types as $name => $type) {
                $types[] = $this->type($name);
            }
        }

        $query = $this->objectType($schemaQuery, [
            'name' => 'Query'
        ]);

        $mutation = $this->objectType($schemaMutation, [
            'name' => 'Mutation'
        ]);

        $subscription = $this->objectType($schemaSubscription, [
            'name' => 'Subscription'
        ]);

        return new Schema([
            'query'         => $query,
            'mutation'      => !empty($schemaMutation) ? $mutation : null,
            'subscription'  => !empty($schemaSubscription) ? $subscription : null,
            'types'         => $types
        ]);
    }

    public function type($name, $fresh = false)
    {
        if(!isset($this->types[$name]))
        {
            throw new \Exception('Type '.$name.' not found.');
        }

        if(!$fresh && isset($this->typesInstances[$name]))
        {
            return $this->typesInstances[$name];
        }

        $type = $this->types[$name];
        if(!is_object($type))
        {
            $type = app($type);
        }

        $instance = $type->toType();
        $this->typesInstances[$name] = $instance;

        return $instance;
    }


}