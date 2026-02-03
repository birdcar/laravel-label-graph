<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Tests\Fixtures;

use Birdcar\LabelGraph\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Ticket extends Model
{
    use HasLabels;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'priority' => 'string',
        ];
    }
}
