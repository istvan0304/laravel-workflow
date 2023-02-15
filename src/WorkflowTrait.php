<?php

namespace Istvan0304\Workflow;

use Illuminate\Support\Facades\Schema;
use Istvan0304\Workflow\Exceptions\WorkflowException;

trait WorkflowTrait
{
    private $workflowStatusAttribute = 'status';

    /**
     * @throws WorkflowException
     */
    public function __construct()
    {
        if (!class_exists($this->workflowClass)) {
            throw new WorkflowException("Unable to load class: $this->workflowClass");
        }

        if (!method_exists($this->workflowClass, 'statusLabels')) {
            throw new WorkflowException("Function not exists: statusLabels()");
        }

        if (!method_exists($this->workflowClass, 'statusActionLabels')) {
            throw new WorkflowException("Function not exists: statusActionLabels()");
        }

        if (!method_exists($this->workflowClass, 'getDefinition')) {
            throw new WorkflowException("Function not exists: getDefinition()");
        }

        parent::__construct();
    }

    /**
     * @return void
     */
    public function start()
    {
        $attribute = $this->getWorkflowAttribute();

        $workflowDefinition = $this->workflowClass::getDefinition();
        $this->$attribute = $workflowDefinition['initialStatus'];
    }

    /**
     * @return mixed|string
     */
    public function sendToStatus($status)
    {
        $attribute = $this->getWorkflowAttribute();

        if ($this->$attribute != null) {
            $this->$attribute = $status;
        }
    }

    /**
     * @param $workflowClass
     * @return string
     */
    protected static function getWorkflowName($workflowClass)
    {
        return (class_exists($workflowClass) ? class_basename($workflowClass) : '');
    }

    /**
     * Get next statuses
     * @return array
     * @throws WorkflowException
     */
    public function getNextStatuses($withLabel = false)
    {
        $nextStatuses = [];

        if ($this->workflowClass != null) {
            $workflowDefinition = $this->workflowClass::getDefinition();
            $currentStatus = $this->getStatus();

            if (is_array($workflowDefinition) && isset($workflowDefinition['status'][$currentStatus])) {
                $transition = self::getTransaction($workflowDefinition['status'][$currentStatus]['transition']);

                if (is_iterable($transition)) {
                    foreach ($transition as $status) {
                        if ($withLabel) {
                            $nextStatuses[$status] = $this->getActionStatusLabel($this->workflowClass, $status);
                        } else {
                            $nextStatuses[] = $status;
                        }
                    }
                }
            }
        }

        return $nextStatuses;
    }

    /**
     * @param $transition
     * @return array
     */
    protected static function getTransaction($transition)
    {
        if (is_callable($transition)) {
            return $transition();
        }

        return $transition;
    }

    /**
     * @param $status
     * @return mixed|string
     */
    public function getStatusLabel()
    {
        $currentStatus = $this->getStatus();

        if (array_key_exists($this->getStatus(), $this->workflowClass::statusLabels())) {
            return $this->workflowClass::statusLabels()[$currentStatus];
        }

        return null;
    }

    /**
     * @param $status
     * @return mixed|null
     */
    public static function getActionStatusLabel($workflowClass, $status)
    {
        if (array_key_exists($status, $workflowClass::statusActionLabels())) {
            return $workflowClass::statusActionLabels()[$status];
        }

        return null;
    }

    /**
     * @return string
     * @throws WorkflowException
     */
    protected function getWorkflowAttribute()
    {
        $attribute = $this->workflowStatusAttribute;

        if (Schema::hasColumn($this->getTable(), $attribute)) {
            return $attribute;
        } else {
            throw new WorkflowException("Missing attribute from table: $attribute");
        }
    }

    /**
     * Get model status short name. (status_name)
     * @return string|void|null
     */
    public function getStatus()
    {
        $attribute = $this->getWorkflowAttribute();

        return ($this->$attribute ?? null);
    }

    /**
     * @return array
     */
    public function getStatusDropDownList($action = false)
    {
        $workflowDefinition = $this->workflowClass::getDefinition();
        $list = [];

        if (is_array($workflowDefinition) && isset($workflowDefinition['status'])) {
            foreach ($workflowDefinition['status'] as $status => $statusArray) {
                $list[] = ['id' => $status, 'label' => ($action ? self::getActionStatusLabel($this->workflowClass, $status) : $this->workflowClass::statusLabels()[$status])];
            }
        }

        return $list;
    }

    /**
     * @return void
     * @throws WorkflowException
     */
    public function transitionValidate()
    {
        $attribute = $this->getWorkflowAttribute();
        $workflowDefinition = $this->workflowClass::getDefinition();

        $oldStatus = $this->getOriginal($attribute);
        $nextStatus = $this->$attribute;

        if ($this->exists !== false && $oldStatus !== $nextStatus) {
            if (array_key_exists($nextStatus, $workflowDefinition['status'])) {
                $oldStatusTransition = self::getTransaction($workflowDefinition['status'][$oldStatus]['transition']);

                if (is_array($oldStatusTransition)) {
                    if (!in_array($nextStatus, $oldStatusTransition)) {
                        throw new WorkflowException('No transition between ' . $oldStatus . ' and ' . $nextStatus);
                    }
                } else {
                    throw new WorkflowException($nextStatus . ' status transitions is invalid!');
                }
            } else {
                throw new WorkflowException($nextStatus . ' status not exists in ' . $this->workflowClass . ' getDefinition function!');
            }
        }
    }
}
