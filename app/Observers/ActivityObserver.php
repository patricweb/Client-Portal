<?php

namespace App\Observers;

use App\Models\CareActivity;
use App\Models\Document;
use App\Models\ExternalCommunication;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectBrief;
use App\Models\ProjectStage;
use App\Models\RequestMessage;
use App\Models\SupportRequest;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    private function record(Model $model, string $action): void
    {
        [$companyId, $projectId] = $this->context($model);
        $visibility = $model instanceof ExternalCommunication || ($model instanceof RequestMessage && $model->is_internal) ? 'internal' : 'public';
        $label = class_basename($model);
        $properties = $action === 'updated' ? $model->getChanges() : $model->getAttributes();
        app(ActivityLogger::class)->log(
            str($label)->snake().'.'.$action,
            str($label)->headline()." {$action}.",
            $model,
            $visibility,
            $properties,
            $companyId,
            $projectId
        );
    }

    private function context(Model $model): array
    {
        if ($model instanceof ProjectStage || $model instanceof ProjectBrief) {
            return [$model->project?->company_id, $model->project_id];
        }
        if ($model instanceof Payment) {
            return [$model->invoice?->company_id, $model->invoice?->project_id];
        }
        if ($model instanceof RequestMessage) {
            return [$model->request?->company_id, $model->request?->project_id];
        }
        if ($model instanceof CareActivity) {
            return [$model->carePlan?->company_id, $model->carePlan?->project_id];
        }
        if ($model instanceof ExternalCommunication) {
            return [$model->company_id, $model->project_id];
        }
        if ($model instanceof Document || $model instanceof Invoice || $model instanceof SupportRequest) {
            return [$model->company_id, $model->project_id];
        }

        return [$model->company_id ?? null, $model->id ?? null];
    }
}
