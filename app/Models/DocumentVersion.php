<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'locked_at' => 'datetime', 'published_at' => 'datetime', 'signed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->isDirty(['content', 'snapshot', 'document_id', 'version', 'created_by'])) {
                throw new \LogicException('Document versions are immutable. Create a new version.');
            }
        });
    }

    public function signedAttachments()
    {
        return $this->hasMany(Attachment::class, 'document_version_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
