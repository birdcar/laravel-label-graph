<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Models;

use Birdcar\LabelTree\Exceptions\CycleDetectedException;
use Birdcar\LabelTree\Exceptions\SelfReferenceException;
use Birdcar\LabelTree\Services\CycleDetector;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $parent_label_id
 * @property string $child_label_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LabelRelationship extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'parent_label_id',
        'child_label_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabelRelationship $relationship): void {
            // Self-reference check
            if ($relationship->parent_label_id === $relationship->child_label_id) {
                throw new SelfReferenceException(
                    'Cannot create self-referential relationship'
                );
            }

            // Cycle detection
            /** @var CycleDetector $detector */
            $detector = app(CycleDetector::class);
            if ($detector->wouldCreateCycle($relationship)) {
                throw new CycleDetectedException(
                    'Creating this relationship would form a cycle'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Label::class, 'parent_label_id');
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Label::class, 'child_label_id');
    }

    public function getTable(): string
    {
        return config('label-tree.tables.relationships', 'label_relationships');
    }
}
