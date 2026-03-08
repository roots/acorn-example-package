#!/usr/bin/env php
<?php

declare(strict_types=1);

const MANIFEST_FILES = [
    'composer.json',
    'README.md',
    'src/Example.php',
    'src/Providers/ExampleServiceProvider.php',
    'src/Console/ExampleCommand.php',
    'src/Facades/Example.php',
    'config/example.php',
    'resources/views/example.blade.php',
];

const RENAME_MAP = [
    'src/Example.php' => 'src/{class_name}.php',
    'src/Providers/ExampleServiceProvider.php' => 'src/Providers/{class_name}ServiceProvider.php',
    'src/Console/ExampleCommand.php' => 'src/Console/{class_name}Command.php',
    'src/Facades/Example.php' => 'src/Facades/{class_name}.php',
    'config/example.php' => 'config/{package_slug}.php',
    'resources/views/example.blade.php' => 'resources/views/{package_slug}.blade.php',
];

const SCAFFOLD_CLEANUP_FILES = [
    'tests/smoke/configure_smoke.php',
    '.github/workflows/configure-smoke.yml',
];

const SCAFFOLD_CLEANUP_DIRECTORIES = [
    'tests/smoke',
    'tests',
    '.github/workflows',
    '.github',
];

main($argv);

function main(array $argv): void
{
    $options = parseOptions($argv);

    if ($options['help']) {
        printHelp();

        return;
    }

    ensureManifestExists();

    $values = resolveValues($options);
    $values['command_signature'] = $values['package_slug'];

    printSummary($values, $options);

    if (! $options['dry_run'] && ! $options['no_interaction'] && ! $options['yes']) {
        if (! confirm('Apply these changes?', true)) {
            fwrite(STDERR, "Aborted.\n");
            exit(1);
        }
    }

    $changes = 0;

    $changes += updateComposerJson($values, $options['dry_run']);
    $changes += updateTextFiles($values, $options['dry_run']);
    $changes += renameFiles($values, $options['dry_run']);
    $changes += cleanupScaffoldFiles($options['dry_run']);

    if ($options['dry_run']) {
        echo "\nDry run complete. Planned changes: {$changes}\n";

        return;
    }

    echo "\nDone. Applied changes: {$changes}\n";
}

function parseOptions(array $argv): array
{
    $longOptions = [
        'author-name:',
        'author-email:',
        'vendor-slug:',
        'vendor-namespace:',
        'package-slug:',
        'class-name:',
        'package-description:',
        'dry-run',
        'no-interaction',
        'yes',
        'help',
    ];

    $parsed = getopt('', $longOptions);

    return [
        'author_name' => getStringOpt($parsed, 'author-name'),
        'author_email' => getStringOpt($parsed, 'author-email'),
        'vendor_slug' => getStringOpt($parsed, 'vendor-slug'),
        'vendor_namespace' => getStringOpt($parsed, 'vendor-namespace'),
        'package_slug' => getStringOpt($parsed, 'package-slug'),
        'class_name' => getStringOpt($parsed, 'class-name'),
        'package_description' => getStringOpt($parsed, 'package-description'),
        'dry_run' => isset($parsed['dry-run']),
        'no_interaction' => isset($parsed['no-interaction']),
        'yes' => isset($parsed['yes']),
        'help' => isset($parsed['help']),
    ];
}

function resolveValues(array $options): array
{
    $composer = readComposerJson();

    $defaults = [
        'author_name' => trim((string) run('git config user.name')) ?: (string) ($composer['authors'][0]['name'] ?? ''),
        'author_email' => trim((string) run('git config user.email')) ?: (string) ($composer['authors'][0]['email'] ?? ''),
        'vendor_slug' => 'vendor-name',
        'vendor_namespace' => 'VendorName',
        'package_slug' => slugify((string) basename(getcwd() ?: 'example-package')) ?: 'example-package',
        'class_name' => 'ExamplePackage',
        'package_description' => (string) ($composer['description'] ?? 'An example package for Roots Acorn.'),
    ];

    if ($options['no_interaction']) {
        $requiredOptions = [
            'author_name',
            'author_email',
            'vendor_slug',
            'vendor_namespace',
            'package_slug',
            'class_name',
            'package_description',
        ];

        $missing = [];
        foreach ($requiredOptions as $requiredOption) {
            $value = $options[$requiredOption] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $missing[] = $requiredOption;
            }
        }

        if ($missing !== []) {
            fwrite(STDERR, 'Missing required options for --no-interaction: ' . implode(', ', $missing) . "\n");
            exit(1);
        }

        $values = [];
        foreach (array_keys($defaults) as $key) {
            $values[$key] = (string) $options[$key];
        }

        assertRequired($values);

        return normalizeValues($values);
    }

    $values = [];
    $values['author_name'] = ask('Author name', $options['author_name'] ?: $defaults['author_name']);
    $values['author_email'] = ask('Author email', $options['author_email'] ?: $defaults['author_email']);
    $values['vendor_slug'] = ask('Vendor slug', $options['vendor_slug'] ?: $defaults['vendor_slug']);
    $values['vendor_namespace'] = ask('Vendor namespace', $options['vendor_namespace'] ?: deriveNamespace($values['vendor_slug'], $defaults['vendor_namespace']));
    $values['package_slug'] = ask('Package slug', $options['package_slug'] ?: $defaults['package_slug']);
    $values['class_name'] = ask('Class name', $options['class_name'] ?: deriveClassName($values['package_slug'], $defaults['class_name']));
    $values['package_description'] = ask('Package description', $options['package_description'] ?: $defaults['package_description']);

    assertRequired($values);

    return normalizeValues($values);
}

function normalizeValues(array $values): array
{
    $values['vendor_slug'] = slugify($values['vendor_slug']);
    $values['package_slug'] = slugify($values['package_slug']);
    $values['vendor_namespace'] = normalizePhpIdentifierPath($values['vendor_namespace']);
    $values['class_name'] = normalizePhpIdentifier($values['class_name']);

    assertRequired($values);

    return $values;
}

function assertRequired(array $values): void
{
    $required = [
        'author_name',
        'author_email',
        'vendor_slug',
        'vendor_namespace',
        'package_slug',
        'class_name',
        'package_description',
    ];

    foreach ($required as $field) {
        if (trim((string) ($values[$field] ?? '')) === '') {
            fwrite(STDERR, "Missing required value: {$field}\n");
            exit(1);
        }
    }
}

function ensureManifestExists(): void
{
    foreach (MANIFEST_FILES as $file) {
        if (! is_file($file)) {
            fwrite(STDERR, "Expected file not found: {$file}\n");
            exit(1);
        }
    }
}

function updateComposerJson(array $values, bool $dryRun): int
{
    $composer = readComposerJson();

    $composer['name'] = "{$values['vendor_slug']}/{$values['package_slug']}";
    $composer['description'] = $values['package_description'];
    $composer['authors'][0]['name'] = $values['author_name'];
    $composer['authors'][0]['email'] = $values['author_email'];

    $namespace = "{$values['vendor_namespace']}\\{$values['class_name']}\\";
    $composer['autoload']['psr-4'] = [$namespace => 'src/'];

    $provider = "{$values['vendor_namespace']}\\{$values['class_name']}\\Providers\\{$values['class_name']}ServiceProvider";
    $aliasFqcn = "{$values['vendor_namespace']}\\{$values['class_name']}\\Facades\\{$values['class_name']}";

    $composer['extra']['acorn']['providers'] = [$provider];
    $composer['extra']['acorn']['aliases'] = [
        $values['class_name'] => $aliasFqcn,
    ];

    // Remove template-maintenance script from generated packages.
    if (isset($composer['scripts']) && is_array($composer['scripts'])) {
        unset($composer['scripts']['test:configure']);
        if ($composer['scripts'] === []) {
            unset($composer['scripts']);
        }
    }

    $new = encodeComposerJson($composer);
    $old = (string) file_get_contents('composer.json');

    if ($new === $old) {
        return 0;
    }

    printChange(($dryRun ? 'Would update' : 'Updated') . ' composer.json');

    if (! $dryRun) {
        file_put_contents('composer.json', $new);
    }

    return 1;
}

function updateTextFiles(array $values, bool $dryRun): int
{
    $changes = 0;

    $fileReplacements = [
        'README.md' => [
            '# Acorn Example Package' => "# Acorn {$values['class_name']} Package",
            'composer require vendor-name/example-package' => "composer require {$values['vendor_slug']}/{$values['package_slug']}",
            '--provider="VendorName\\ExamplePackage\\Providers\\ExampleServiceProvider"' => "--provider=\"{$values['vendor_namespace']}\\{$values['class_name']}\\Providers\\{$values['class_name']}ServiceProvider\"",
            "@include('Example::example')" => "@include('{$values['class_name']}::{$values['package_slug']}')",
            '$ wp acorn example' => "$ wp acorn {$values['command_signature']}",
        ],
        'src/Example.php' => [
            'namespace VendorName\\ExamplePackage;' => "namespace {$values['vendor_namespace']}\\{$values['class_name']};",
            'class Example' => "class {$values['class_name']}",
            "config('example.quotes')" => "config('{$values['package_slug']}.quotes')",
        ],
        'src/Providers/ExampleServiceProvider.php' => [
            'namespace VendorName\\ExamplePackage\\Providers;' => "namespace {$values['vendor_namespace']}\\{$values['class_name']}\\Providers;",
            'use VendorName\\ExamplePackage\\Console\\ExampleCommand;' => "use {$values['vendor_namespace']}\\{$values['class_name']}\\Console\\{$values['class_name']}Command;",
            'use VendorName\\ExamplePackage\\Example;' => "use {$values['vendor_namespace']}\\{$values['class_name']}\\{$values['class_name']};",
            'class ExampleServiceProvider extends ServiceProvider' => "class {$values['class_name']}ServiceProvider extends ServiceProvider",
            "singleton('Example'" => "singleton('{$values['class_name']}'",
            'new Example(' => "new {$values['class_name']}(",
            '__DIR__.\'/../../config/example.php\'' => "__DIR__.'/../../config/{$values['package_slug']}.php'",
            "            'example'\n        );" => "            '{$values['package_slug']}'\n        );",
            "configPath('example.php')" => "configPath('{$values['package_slug']}.php')",
            "'Example'," => "'{$values['class_name']}',",
            'ExampleCommand::class' => "{$values['class_name']}Command::class",
            "make('Example')" => "make('{$values['class_name']}')",
        ],
        'src/Console/ExampleCommand.php' => [
            'namespace VendorName\\ExamplePackage\\Console;' => "namespace {$values['vendor_namespace']}\\{$values['class_name']}\\Console;",
            'use VendorName\\ExamplePackage\\Facades\\Example;' => "use {$values['vendor_namespace']}\\{$values['class_name']}\\Facades\\{$values['class_name']};",
            'class ExampleCommand extends Command' => "class {$values['class_name']}Command extends Command",
            "'example';" => "'{$values['command_signature']}';",
            'Example::getQuote()' => "{$values['class_name']}::getQuote()",
        ],
        'src/Facades/Example.php' => [
            'namespace VendorName\\ExamplePackage\\Facades;' => "namespace {$values['vendor_namespace']}\\{$values['class_name']}\\Facades;",
            'class Example extends Facade' => "class {$values['class_name']} extends Facade",
            "'Example'" => "'{$values['class_name']}'",
        ],
        'config/example.php' => [
            'Example Package' => "{$values['class_name']} Package",
        ],
        'resources/views/example.blade.php' => [
            '{{ Example::getQuote() }}' => "{{ {$values['class_name']}::getQuote() }}",
        ],
    ];

    foreach ($fileReplacements as $file => $replacements) {
        $contents = (string) file_get_contents($file);
        $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);

        if ($updated === $contents) {
            continue;
        }

        printChange(($dryRun ? 'Would update' : 'Updated') . " {$file}");

        if (! $dryRun) {
            file_put_contents($file, $updated);
        }

        $changes++;
    }

    return $changes;
}

function renameFiles(array $values, bool $dryRun): int
{
    $changes = 0;

    foreach (RENAME_MAP as $source => $targetTemplate) {
        $target = strtr($targetTemplate, [
            '{class_name}' => $values['class_name'],
            '{package_slug}' => $values['package_slug'],
        ]);

        if (! is_file($source)) {
            fwrite(STDERR, "Rename source not found: {$source}\n");
            exit(1);
        }

        if ($source === $target) {
            continue;
        }

        if (file_exists($target)) {
            fwrite(STDERR, "Rename target already exists: {$target}\n");
            exit(1);
        }

        printChange(($dryRun ? 'Would rename' : 'Renamed') . " {$source} -> {$target}");

        if (! $dryRun) {
            rename($source, $target);
        }

        $changes++;
    }

    return $changes;
}

function cleanupScaffoldFiles(bool $dryRun): int
{
    $changes = 0;

    foreach (SCAFFOLD_CLEANUP_FILES as $file) {
        if (! file_exists($file)) {
            continue;
        }

        printChange(($dryRun ? 'Would remove' : 'Removed') . " {$file}");

        if (! $dryRun) {
            unlink($file);
        }

        $changes++;
    }

    foreach (SCAFFOLD_CLEANUP_DIRECTORIES as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        if (! isDirectoryEmpty($directory)) {
            continue;
        }

        printChange(($dryRun ? 'Would remove empty directory' : 'Removed empty directory') . " {$directory}");

        if (! $dryRun) {
            rmdir($directory);
        }

        $changes++;
    }

    return $changes;
}

function isDirectoryEmpty(string $path): bool
{
    $items = scandir($path);

    if ($items === false) {
        return false;
    }

    return count(array_diff($items, ['.', '..'])) === 0;
}

function encodeComposerJson(array $composer): string
{
    $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (! is_string($json)) {
        throw new RuntimeException('Failed to encode composer.json');
    }

    $json = preg_replace_callback('/^( +)/m', static function (array $matches): string {
        return str_repeat(' ', intdiv(strlen($matches[1]), 2));
    }, $json);

    return (string) $json . "\n";
}

function readComposerJson(): array
{
    $json = (string) file_get_contents('composer.json');
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('composer.json is not a valid object');
    }

    return $decoded;
}

function printSummary(array $values, array $options): void
{
    echo "\nResolved values:\n";
    echo "- author_name: {$values['author_name']}\n";
    echo "- author_email: {$values['author_email']}\n";
    echo "- vendor_slug: {$values['vendor_slug']}\n";
    echo "- vendor_namespace: {$values['vendor_namespace']}\n";
    echo "- package_slug: {$values['package_slug']}\n";
    echo "- class_name: {$values['class_name']}\n";
    echo "- package_description: {$values['package_description']}\n";
    echo "- command_signature: {$values['command_signature']}\n";
    echo "- mode: " . ($options['dry_run'] ? 'dry-run' : 'apply') . "\n\n";
}

function printHelp(): void
{
    echo <<<TXT
Usage:
  php configure.php [options]

Options:
  --author-name=VALUE
  --author-email=VALUE
  --vendor-slug=VALUE
  --vendor-namespace=VALUE
  --package-slug=VALUE
  --class-name=VALUE
  --package-description=VALUE
  --dry-run
  --no-interaction
  --yes
  --help

TXT;
}

function printChange(string $line): void
{
    echo "- {$line}\n";
}

function ask(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? " ({$default})" : '';
    $answer = readline("{$question}{$suffix}: ");

    if (! is_string($answer)) {
        return $default;
    }

    $answer = trim($answer);

    return $answer === '' ? $default : $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $choice = $default ? 'Y/n' : 'y/N';
    $answer = strtolower(ask("{$question} [{$choice}]"));

    if ($answer === '') {
        return $default;
    }

    return in_array($answer, ['y', 'yes'], true);
}

function run(string $command): string
{
    $output = shell_exec($command);

    return trim((string) $output);
}

function slugify(string $value): string
{
    return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
}

function deriveNamespace(string $value, string $fallback): string
{
    $slug = slugify($value);

    if ($slug === '') {
        return $fallback;
    }

    return implode('', array_map('ucfirst', explode('-', $slug)));
}

function deriveClassName(string $value, string $fallback): string
{
    $slug = slugify($value);

    if ($slug === '') {
        return $fallback;
    }

    return implode('', array_map('ucfirst', explode('-', $slug)));
}

function normalizePhpIdentifierPath(string $value): string
{
    $parts = preg_split('/\\\\+/', trim($value));
    $parts = is_array($parts) ? $parts : [$value];

    $normalized = [];
    foreach ($parts as $part) {
        $normalizedPart = normalizePhpIdentifier($part);
        if ($normalizedPart !== '') {
            $normalized[] = $normalizedPart;
        }
    }

    return implode('\\', $normalized);
}

function normalizePhpIdentifier(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed) === 1) {
        return $trimmed;
    }

    $normalized = preg_replace('/[^A-Za-z0-9_]+/', ' ', $trimmed);
    $normalized = str_replace('_', ' ', (string) $normalized);
    $normalized = ucwords(strtolower(trim((string) $normalized)));
    $normalized = str_replace(' ', '', $normalized);

    return preg_replace('/^[^A-Za-z_]+/', '', $normalized) ?: '';
}

function getStringOpt(array $options, string $key): ?string
{
    if (! array_key_exists($key, $options)) {
        return null;
    }

    $value = $options[$key];

    if (! is_string($value)) {
        return null;
    }

    return trim($value);
}
