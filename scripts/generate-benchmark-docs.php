<?php

declare(strict_types=1);

$artifactsDir = __DIR__.'/../benchmark-artifacts';
$outputFile = __DIR__.'/../docs/BENCHMARKS.md';

$databases = [
    'sqlite' => 'SQLite',
    'mysql' => 'MySQL 8.4',
    'pgsql' => 'PostgreSQL 17',
    'pgsql-ltree' => 'PostgreSQL 17 + ltree',
];

$results = [];

foreach ($databases as $key => $name) {
    $file = "{$artifactsDir}/benchmark-{$key}/benchmark-results.json";
    if (file_exists($file)) {
        $results[$key] = json_decode(file_get_contents($file), true);
    }
}

// Generate markdown
$md = "# Benchmark Results\n\n";
$md .= 'Last updated: '.date('Y-m-d H:i:s T')."\n\n";
$md .= "These benchmarks are automatically generated on each release.\n\n";

$md .= "## Environment\n\n";
$md .= "- PHP: 8.3\n";
$md .= "- Runner: GitHub Actions ubuntu-latest\n\n";

$md .= "## Summary\n\n";
$md .= "| Test | SQLite | MySQL | PostgreSQL | PostgreSQL + ltree |\n";
$md .= "|------|--------|-------|------------|--------------------|\n";

// Collect all test names
$testNames = [];
foreach ($results as $data) {
    foreach ($data['results'] ?? [] as $result) {
        $testNames[$result['name']] = true;
    }
}

foreach (array_keys($testNames) as $testName) {
    $row = "| {$testName} |";
    foreach (array_keys($databases) as $db) {
        $value = '-';
        foreach ($results[$db]['results'] ?? [] as $result) {
            if ($result['name'] === $testName) {
                $value = sprintf('%.2f ms', $result['avg_ms']);
                break;
            }
        }
        $row .= " {$value} |";
    }
    $md .= $row."\n";
}

$md .= "\n## ltree Performance Impact\n\n";
$md .= "Comparison of PostgreSQL with and without ltree extension:\n\n";

if (isset($results['pgsql'], $results['pgsql-ltree'])) {
    $md .= "| Test | Without ltree | With ltree | Improvement |\n";
    $md .= "|------|---------------|------------|-------------|\n";

    foreach ($results['pgsql']['results'] ?? [] as $result) {
        $name = $result['name'];
        $without = $result['avg_ms'];
        $with = null;

        foreach ($results['pgsql-ltree']['results'] ?? [] as $ltreeResult) {
            if ($ltreeResult['name'] === $name) {
                $with = $ltreeResult['avg_ms'];
                break;
            }
        }

        if ($with !== null) {
            $improvement = (($without - $with) / $without) * 100;
            $improvementStr = $improvement > 0
                ? sprintf('+%.1f%%', $improvement)
                : sprintf('%.1f%%', $improvement);

            $md .= sprintf(
                "| %s | %.2f ms | %.2f ms | %s |\n",
                $name,
                $without,
                $with,
                $improvementStr
            );
        }
    }
}

$md .= "\n## Detailed Results\n\n";

foreach ($databases as $key => $name) {
    if (! isset($results[$key])) {
        continue;
    }

    $data = $results[$key];
    $md .= "### {$name}\n\n";

    $meta = $data['metadata'] ?? [];
    $md .= '- Driver: '.($meta['driver'] ?? 'unknown')."\n";
    $md .= '- ltree available: '.($meta['ltree_available'] ? 'Yes' : 'No')."\n\n";

    $md .= "| Test | Avg | Min | Max | P95 | P99 |\n";
    $md .= "|------|-----|-----|-----|-----|-----|\n";

    foreach ($data['results'] ?? [] as $result) {
        $md .= sprintf(
            "| %s | %.2f ms | %.2f ms | %.2f ms | %.2f ms | %.2f ms |\n",
            $result['name'],
            $result['avg_ms'],
            $result['min_ms'],
            $result['max_ms'],
            $result['p95_ms'],
            $result['p99_ms']
        );
    }

    $md .= "\n";
}

$md .= "## Running Locally\n\n";
$md .= "```bash\n";
$md .= "# Run benchmarks against default database (SQLite)\n";
$md .= "composer benchmark\n\n";
$md .= "# Run against specific database\n";
$md .= "DB_CONNECTION=mysql composer benchmark\n";
$md .= "DB_CONNECTION=pgsql composer benchmark\n";
$md .= "```\n";

// Ensure docs directory exists
$docsDir = dirname($outputFile);
if (! is_dir($docsDir)) {
    mkdir($docsDir, 0755, true);
}

file_put_contents($outputFile, $md);
echo "Generated {$outputFile}\n";
