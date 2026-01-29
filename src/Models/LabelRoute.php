<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $path
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array<int, string> $segments
 */
class LabelRoute extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'path',
        'depth',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
        ];
    }

    /**
     * Get the labels in this route's path.
     *
     * @return Collection<int, Label>
     */
    public function labels(): Collection
    {
        $slugs = $this->segments;

        return Label::whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (Label $label): int|false => array_search($label->slug, $slugs, true));
    }

    /**
     * Get path segments as array.
     *
     * @return array<int, string>
     */
    public function getSegmentsAttribute(): array
    {
        return explode('.', $this->path);
    }

    public function getTable(): string
    {
        return config('label-tree.tables.routes', 'label_routes');
    }
}
