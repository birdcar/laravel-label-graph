<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * @property string $id
 * @property string $label_route_id
 * @property string $labelable_type
 * @property string $labelable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Labelable extends MorphPivot
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return config('label-tree.tables.labelables', 'labelables');
    }
}
