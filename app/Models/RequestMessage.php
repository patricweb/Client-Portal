<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function request()
    {
        return $this->belongsTo(SupportRequest::class, 'support_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
