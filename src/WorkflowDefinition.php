<?php

namespace Istvan0304\Workflow;

interface WorkflowDefinition
{
    public static function statusLabels();

    public static function statusActionLabels();

    public static function getDefinition();
}
