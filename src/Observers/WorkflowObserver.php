<?php

namespace Istvan0304\Workflow\Observers;

class WorkflowObserver
{
    /**
     * @param $model
     * @return void
     */
    public function saving($model)
    {
        $model->transitionValidate();
    }
}
