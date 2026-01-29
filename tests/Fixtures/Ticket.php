<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Tests\Fixtures;

use Birdcar\LabelTree\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Test fixture model for testing HasLabels trait.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Ticket extends Model
{
    use HasLabels;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['title', 'description'];
}
