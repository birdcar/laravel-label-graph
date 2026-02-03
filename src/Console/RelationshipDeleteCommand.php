<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Exceptions\RoutesInUseException;
use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Illuminate\Console\Command;

class RelationshipDeleteCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:relationship:delete
        {parent : Parent label slug}
        {child : Child label slug}
        {--cascade : Delete routes and attachments}
        {--replace= : Migrate attachments to this route before deleting}
        {--force : Skip confirmation}';

    /** @var string */
    protected $description = 'Delete a label relationship';

    public function handle(): int
    {
        $parent = Label::where('slug', $this->argument('parent'))->first();
        $child = Label::where('slug', $this->argument('child'))->first();

        if ($parent === null || $child === null) {
            $this->error('Parent or child label not found.');

            return Command::FAILURE;
        }

        $relationship = LabelRelationship::where('parent_label_id', $parent->id)
            ->where('child_label_id', $child->id)
            ->first();

        if ($relationship === null) {
            $this->error('Relationship not found.');

            return Command::FAILURE;
        }

        $affectedCount = $relationship->getAffectedAttachmentCount();

        if ($affectedCount > 0) {
            $this->warn("This relationship has {$affectedCount} attachments on affected routes.");

            if ($this->option('cascade')) {
                if (! $this->option('force') && ! $this->confirm('Delete all attachments?')) {
                    return Command::FAILURE;
                }
                $relationship->deleteAndCascade();
                $this->info('Relationship and attachments deleted.');
            } elseif ($replacePath = $this->option('replace')) {
                /** @var string $replacePath */
                $relationship->deleteAndReplace($replacePath);
                $this->info("Attachments migrated to {$replacePath}, relationship deleted.");
            } else {
                $this->error('Cannot delete: attachments exist. Use --cascade or --replace=<path>');

                return Command::FAILURE;
            }
        } else {
            try {
                $relationship->delete();
                $this->info('Relationship deleted.');
            } catch (RoutesInUseException $e) {
                $this->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
