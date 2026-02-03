<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property string|null $icon
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Label extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Label $label): void {
            if (empty($label->slug)) {
                $label->slug = Str::slug($label->name);
            }
        });
    }

    /**
     * @return HasMany<LabelRelationship, $this>
     */
    public function relationships(): HasMany
    {
        return $this->hasMany(LabelRelationship::class, 'parent_label_id');
    }

    /**
     * @return HasMany<LabelRelationship, $this>
     */
    public function reverseRelationships(): HasMany
    {
        return $this->hasMany(LabelRelationship::class, 'child_label_id');
    }

    public function getTable(): string
    {
        return config('label-graph.tables.labels', 'labels');
    }
}
