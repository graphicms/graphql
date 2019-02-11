<?php
Route::group(['middleware' => [\Graphicms\GraphQL\Http\Middleware\EmitServingGraphQLEventMiddleware::class]], function() {
    Route::group(array_merge([
        'prefix'        => config('graphicms_graphql.prefix'),
        'middleware'    => config('graphicms_graphql.middleware', []),
    ], config('graphicms_graphql.route_group_attributes', [])), function($router)
    {
        // Routes
        $routes = config('graphicms_graphql.routes');
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
        $controllers = config('graphicms_graphql.controllers', \Graphicms\GraphQL\Http\Controllers\GraphQLController::class . '@query');
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
                $defaultMiddleware = config('graphicms_graphql.schemas.' . config('graphicms_graphql.default_schema') . '.middleware', []);
                $defaultMethod = config('graphicms_graphql.schemas.' . config('graphicms_graphql.default_schema') . '.method', ['get', 'post']);
                Route::match($defaultMethod, preg_replace($schemaParameterPattern, '', $queryRoute), [
                    'uses'          => $queryController,
                    'middleware'    => $defaultMiddleware,
                ]);

                foreach(config('graphicms_graphql.schemas') as $name => $schema)
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
                $defaultMiddleware = config('graphicms_graphql.schemas.' . config('graphicms_graphql.default_schema') . '.middleware', []);
                $defaultMethod = config('graphicms_graphql.schemas.' . config('graphicms_graphql.default_schema') . '.method', ['get', 'post']);
                Route::match(
                    $defaultMethod,
                    preg_replace($schemaParameterPattern, '', $mutationRoute),
                    [
                        'uses'          => $mutationController,
                        'middleware'    => $defaultMiddleware,
                    ]
                );

                foreach(config('graphicms_graphql.schemas') as $name => $schema)
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

    if (config('graphicms_graphql.graphiql.display', true))
    {
        Route::group([
            'prefix'        => config('graphicms_graphql.graphiql.prefix', 'graphiql'),
            'middleware'    => config('graphicms_graphql.graphiql.middleware', [])
        ], function ($router)
        {
            $graphiqlController =  config('graphicms_graphql.graphiql.controller', \Graphicms\GraphQL\Http\Controllers\GraphQLController::class . '@graphiql');
            $schemaParameterPattern = '/\{\s*graphql\_schema\s*\?\s*\}/';
            foreach (config('graphicms_graphql.schemas') as $name => $schema)
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