<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboxMediaUpload extends Model
{
    protected $table = 'inbox_media_uploads';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'mime',
        'size',
        'original_name',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $upload): void {
            if (! $upload->uuid) {
                $upload->uuid = (string) Str::uuid();
            }
        });
    }

    public function publicUrl(): string
    {
        return url('/media/'.$this->uuid);
    }

    public function storageUrl(): ?string
    {
        if ($this->disk === MediaStorage::DISK) {
            return MediaStorage::url($this->path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function contents(): ?string
    {
        try {
            return Storage::disk($this->disk)->get($this->path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function touchUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
