<?php

namespace Graphicms\GraphQL\Http\Middleware;

use Closure;
use Graphicms\GraphQL\Events\ServingGraphQL;
use Illuminate\Http\Request;

class EmitServingGraphQLEventMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        event(new ServingGraphQL);
        return $next($request);
    }
}