<?php

namespace App\Jobs;

use App\Models\WorkItem;
use App\Services\WorkItemChannelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncWorkItemChannels implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(public int $workItemId) {}

    public function handle(WorkItemChannelService $channels): void
    {
        $workItem = WorkItem::with(['project', 'company', 'assignee'])->find($this->workItemId);
        if ($workItem) {
            $channels->sync($workItem);
        }
    }

    public function failed(?Throwable $exception): void
    {
        WorkItem::whereKey($this->workItemId)->update([
            'channel_sync_status' => 'failed',
            'channel_sync_error' => str($exception?->getMessage() ?? 'Channel synchronization failed.')->limit(2000),
        ]);
    }
}
