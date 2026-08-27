<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parentDocument()
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    public function requiresSignature(): bool
    {
        // Legacy document types keep their signed-PDF workflow. New confirmations use an
        // explicit portal decision tied to an immutable PDF version instead.
        return in_array($this->type, ['contract', 'scope_of_work', 'change_order', 'care_support_agreement'], true);
    }

    public function usesPortalConfirmation(): bool
    {
        return in_array($this->type, ['project_confirmation', 'change_confirmation', 'delivery_confirmation'], true);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('decided_at')->latest('id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function currentVersionRecord(): ?DocumentVersion
    {
        return $this->versions()->where('version', $this->current_version ?: 1)->first();
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['accepted', 'accepted_with_minor_items', 'signed'], true);
    }
}
