<?php

use Graphicms\GraphQL\Http\Controllers\GraphQLController;
use Graphicms\GraphQL\Http\Middleware\EmitServingGraphQLEventMiddleware;

Route::group(['middleware' => [EmitServingGraphQLEventMiddleware::class]], function() {
    Route::group(array_merge([
        'prefix'        => config('graphicms.graphql.prefix'),
        'middleware'    => config('graphicms.graphql.middleware', []),
    ], config('graphicms.graphql.route_group_attributes', [])), function($router)
    {
        // Routes
        $routes = config('graphicms.graphql.routes');
        $queryRoute = null;
        $mutationRoute = null;
        if(is_array($routes))
        {
            $queryRoute = array_get($routes, 'query');
            $mutationRoute = array_get($routes, 'mutation');
        }
        else
        {
            $queryRoute = $routes;
            $mutationRoute = $routes;
        }

        // Controllers
        $controllers = config('graphicms.graphql.controllers', GraphQLController::class . '@query');
        $queryController = null;
        $mutationController = null;
        if(is_array($controllers))
        {
            $queryController = array_get($controllers, 'query');
            $mutationController = array_get($controllers, 'mutation');
        }
        else
        {
            $queryController = $controllers;
            $mutationController = $controllers;
        }

        $schemaParameterPattern = '/\{\s*graphql\_schema\s*\?\s*\}/';

        // Query
        if($queryRoute)
        {
            if(preg_match($schemaParameterPattern, $queryRoute))
            {
                $defaultMiddleware = config('graphicms.graphql.schemas.' . config('graphicms.graphql.default_schema') . '.middleware', []);
                $defaultMethod = config('graphicms.graphql.schemas.' . config('graphicms.graphql.default_schema') . '.method', ['get', 'post']);
                Route::match($defaultMethod, preg_replace($schemaParameterPattern, '', $queryRoute), [
                    'uses'          => $queryController,
                    'middleware'    => $defaultMiddleware,
                ]);

                foreach(config('graphicms.graphql.schemas') as $name => $schema)
                {
                    Route::match(
                        array_get($schema, 'method', ['get', 'post']),
                        Rebing\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $queryRoute),
                        [
                            'uses'          => $queryController,
                            'middleware'    => array_get($schema, 'middleware', []),
                        ]
                    )->where($name, $name);
                }
            }
            else
            {
                Route::match(['get', 'post'], $queryRoute, [
                    'uses'  => $queryController
                ]);
            }
        }

        // Mutation
        if($mutationRoute)
        {
            if(preg_match($schemaParameterPattern, $mutationRoute))
            {
                $defaultMiddleware = config('graphicms.graphql.schemas.' . config('graphicms.graphql.default_schema') . '.middleware', []);
                $defaultMethod = config('graphicms.graphql.schemas.' . config('graphicms.graphql.default_schema') . '.method', ['get', 'post']);
                Route::match(
                    $defaultMethod,
                    preg_replace($schemaParameterPattern, '', $mutationRoute),
                    [
                        'uses'          => $mutationController,
                        'middleware'    => $defaultMiddleware,
                    ]
                );

                foreach(config('graphicms.graphql.schemas') as $name => $schema)
                {
                    Route::match(
                        array_get($schema, 'method', ['get', 'post']),
                        Rebing\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, $mutationRoute),
                        [
                            'uses'          => $mutationController,
                            'middleware'    => array_get($schema, 'middleware', []),
                        ]
                    )->where($name, $name);
                }
            }
            else
            {
                Route::match(['get', 'post'], $mutationRoute, [
                    'uses'  => $mutationController
                ]);
            }
        }
    });

    if (config('graphicms.graphql.graphiql.display', true))
    {
        Route::group([
            'prefix'        => config('graphicms.graphql.graphiql.prefix', 'graphiql'),
            'middleware'    => config('graphicms.graphql.graphiql.middleware', [])
        ], function ($router)
        {
            $graphiqlController =  config('graphicms.graphql.graphiql.controller', GraphQLController::class . '@graphiql');
            $schemaParameterPattern = '/\{\s*graphql\_schema\s*\?\s*\}/';
            foreach (config('graphicms.graphql.schemas') as $name => $schema)
            {
                Route::match(
                    ['get', 'post'],
                    Rebing\GraphQL\GraphQL::routeNameTransformer($name, $schemaParameterPattern, '{graphql_schema?}'),
                    ['uses' => $graphiqlController]
                )->where($name, $name);
            }

            Route::match(
                ['get', 'post'],
                '/',
                ['uses'  => $graphiqlController]
            );
        });
    }

});