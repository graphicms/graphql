<?php

namespace Graphicms\GraphQL;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Graphicms\GraphQL\Skeleton\SkeletonClass
 */
class GraphQLFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'graphicms_graphql';
    }
}
