<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Tests\Benchmark;

use Birdcar\LabelTree\Query\PathQueryAdapter;
use Birdcar\LabelTree\Query\PostgresAdapter;
use Illuminate\Support\Facades\DB;

final class BenchmarkResultCollector
{
    /** @var array<string, array{iterations: int, times: array<int, float>, summary: array<string, mixed>}> */
    private array $results = [];

    /** @var array<string, mixed>|null */
    private ?array $metadata = null;

    private static ?self $instance = null;

    private ?string $outputPath = null;

    private bool $shutdownRegistered = false;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Set the output path and register shutdown handler.
     */
    public function setOutputPath(string $path): self
    {
        $this->outputPath = $path;

        if (! $this->shutdownRegistered) {
            register_shutdown_function([$this, 'writeOnShutdown']);
            $this->shutdownRegistered = true;
        }

        return $this;
    }

    /**
     * Write results on shutdown if output path is set.
     */
    public function writeOnShutdown(): void
    {
        if ($this->outputPath !== null && ! empty($this->results)) {
            $this->writeResultsOnly($this->outputPath);
        }
    }

    /**
     * Run a benchmark and record results.
     *
     * @param  callable(): void  $callback
     * @return array<string, mixed>
     */
    public function measure(string $name, callable $callback, int $iterations = 100): array
    {
        // Capture metadata during first measurement when Laravel is available
        if ($this->metadata === null) {
            $this->captureMetadata();
        }

        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $times[] = (hrtime(true) - $start) / 1_000_000; // Convert to ms
        }

        sort($times);

        $result = [
            'name' => $name,
            'iterations' => $iterations,
            'avg_ms' => array_sum($times) / count($times),
            'min_ms' => $times[0],
            'max_ms' => $times[count($times) - 1],
            'p95_ms' => $times[(int) floor(count($times) * 0.95)],
            'p99_ms' => $times[(int) floor(count($times) * 0.99)],
        ];

        $this->results[$name] = [
            'iterations' => $iterations,
            'times' => $times,
            'summary' => $result,
        ];

        return $result;
    }

    /**
     * Capture environment metadata while Laravel is available.
     */
    private function captureMetadata(): void
    {
        $ltreeAvailable = false;

        try {
            $adapter = app(PathQueryAdapter::class);
            $ltreeAvailable = $adapter instanceof PostgresAdapter && $adapter->hasLtreeSupport();
        } catch (\Exception) {
            // Container not available in some test contexts
        }

        try {
            $driver = DB::connection()->getDriverName();
            $database = config('database.default');
        } catch (\Exception) {
            $driver = 'unknown';
            $database = 'unknown';
        }

        $this->metadata = [
            'php_version' => PHP_VERSION,
            'database' => $database,
            'driver' => $driver,
            'ltree_available' => $ltreeAvailable,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Get environment metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        if ($this->metadata === null) {
            $this->captureMetadata();
        }

        return $this->metadata;
    }

    /**
     * Output all results as JSON.
     */
    public function toJson(): string
    {
        return json_encode([
            'metadata' => $this->getMetadata(),
            'results' => array_map(fn ($r) => $r['summary'], $this->results),
        ], JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * Write results to file (used by shutdown handler).
     */
    private function writeResultsOnly(string $path): void
    {
        $data = [
            'metadata' => $this->metadata ?? [
                'php_version' => PHP_VERSION,
                'database' => 'unknown',
                'driver' => 'unknown',
                'ltree_available' => false,
                'timestamp' => date('c'),
            ],
            'results' => array_map(fn ($r) => $r['summary'], $this->results),
        ];

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT) ?: '{}');
    }

    /**
     * Write results to file.
     */
    public function writeToFile(string $path): void
    {
        file_put_contents($path, $this->toJson());
    }

    /**
     * Reset for fresh run.
     */
    public function reset(): self
    {
        $this->results = [];
        $this->metadata = null;

        return $this;
    }

    /**
     * Get all collected results.
     *
     * @return array<string, array{iterations: int, times: array<int, float>, summary: array<string, mixed>}>
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
