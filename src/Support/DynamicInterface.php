<?php

namespace Graphicms\GraphQL\Support;

interface DynamicInterface
{
    public function deferred_type(): DynamicInterface;
}