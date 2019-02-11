<?php

namespace Graphicms\GraphQL;

use Graphicms\GraphQL\Events\CmsQLBooted;
use Illuminate\Support\ServiceProvider;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;

class GraphQLServiceProvider extends ServiceProvider
{
    protected $defer = false;
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();

        $this->bootTypes();

        $this->bootSchemas();

        $this->bootRouter();

        event(new CmsQLBooted);
    }

    /**
     * Bootstrap router
     *
     * @return void
     */
    protected function bootRouter()
    {
        if(config('graphicms_graphql.routes'))
        {
            include __DIR__.'/Http/routes.php';
        }
    }

    /**
     * Bootstrap publishes
     *
     * @return void
     */
    protected function bootPublishes()
    {
        $configPath = __DIR__.'/../config';

        $this->publishes([
            $configPath.'/config.php' => config_path('graphicms/graphql.php'),
        ], 'config');

        $viewsPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewsPath, 'graphicms_graphql');
    }

    /**
     * Bootstrap publishes
     *
     * @return void
     */
    protected function bootTypes()
    {
        $configTypes = config('graphicms_graphql.types');
        foreach($configTypes as $name => $type)
        {
            if(is_numeric($name))
            {
                $this->app['graphicms_graphql']->addType($type);
            }
            else
            {
                $this->app['graphicms_graphql']->addType($type, $name);
            }
        }
    }

    /**
     * Add schemas from config
     *
     * @return void
     */
    protected function bootSchemas()
    {
        $configSchemas = config('graphicms_graphql.schemas');
        foreach ($configSchemas as $name => $schema) {
            $this->app['graphicms_graphql']->addSchema($name, $schema);
        }
    }

    /**
     * Configure security from config
     *
     * @return void
     */
    protected function applySecurityRules()
    {
        $maxQueryComplexity = config('graphicms_graphql.security.query_max_complexity');
        if ($maxQueryComplexity !== null) {
            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setMaxQueryComplexity($maxQueryComplexity);
        }

        $maxQueryDepth = config('graphicms_graphql.security.query_max_depth');
        if ($maxQueryDepth !== null) {
            /** @var QueryDepth $queryDepth */
            $queryDepth = DocumentValidator::getRule('QueryDepth');
            $queryDepth->setMaxQueryDepth($maxQueryDepth);
        }

        $disableIntrospection = config('graphicms_graphql.security.disable_introspection');
        if ($disableIntrospection === true) {
            /** @var DisableIntrospection $disableIntrospection */
            $disableIntrospection = DocumentValidator::getRule('DisableIntrospection');
            $disableIntrospection->setEnabled(DisableIntrospection::ENABLED);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerGraphQL();
    }

    public function registerGraphQL()
    {
        $configPath = __DIR__.'/../config';
        $this->mergeConfigFrom($configPath.'/config.php', 'graphicms_graphql');
        $this->app->singleton('graphicms_graphql', function($app)
        {
            $graphql = new GraphQL($app);

            $this->applySecurityRules();

            return $graphql;
        });
    }
}
