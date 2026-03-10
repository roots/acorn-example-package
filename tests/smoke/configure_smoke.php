#!/usr/bin/env php
<?php

declare(strict_types=1);

$sourceRoot = dirname(__DIR__, 2);
$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'acorn-example-configure-'.bin2hex(random_bytes(6));

recursiveCopy($sourceRoot, $tempRoot, ['.git', 'vendor']);

$args = [
    'php',
    'configure.php',
    '--no-interaction',
    '--author-name=Smoke User',
    '--author-email=smoke@example.com',
    '--vendor-slug=acme',
    '--vendor-namespace=Acme',
    '--package-slug=hello-package',
    '--class-name=HelloPackage',
    '--package-description=Smoke test package',
    '--yes',
];

$command = 'cd '.escapeshellarg($tempRoot).' && '.implode(' ', array_map('escapeshellarg', $args));
exec($command.' 2>&1', $output, $exitCode);
assertTrue($exitCode === 0, "configure.php failed:\n".implode("\n", $output));

$expectedFiles = [
    'src/HelloPackage.php',
    'src/Providers/HelloPackageServiceProvider.php',
    'src/Console/HelloPackageCommand.php',
    'src/Facades/HelloPackage.php',
    'config/hello-package.php',
    'resources/views/hello-package.blade.php',
];

$oldFiles = [
    'src/Example.php',
    'src/Providers/ExampleServiceProvider.php',
    'src/Console/ExampleCommand.php',
    'src/Facades/Example.php',
    'config/example.php',
    'resources/views/example.blade.php',
];

foreach ($expectedFiles as $file) {
    assertTrue(is_file($tempRoot.DIRECTORY_SEPARATOR.$file), "Expected file missing: {$file}");
}

foreach ($oldFiles as $file) {
    assertTrue(! file_exists($tempRoot.DIRECTORY_SEPARATOR.$file), "Old file still present: {$file}");
}

$removedScaffoldFiles = [
    'tests/smoke/configure_smoke.php',
    '.github/workflows/configure-smoke.yml',
];

foreach ($removedScaffoldFiles as $file) {
    assertTrue(! file_exists($tempRoot.DIRECTORY_SEPARATOR.$file), "Scaffold file still present: {$file}");
}

$composerPath = $tempRoot.DIRECTORY_SEPARATOR.'composer.json';
$composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

assertTrue(($composer['name'] ?? null) === 'acme/hello-package', 'composer.json name was not updated');
assertTrue(($composer['description'] ?? null) === 'Smoke test package', 'composer.json description was not updated');
assertTrue(($composer['authors'][0]['name'] ?? null) === 'Smoke User', 'composer author name was not updated');
assertTrue(($composer['authors'][0]['email'] ?? null) === 'smoke@example.com', 'composer author email was not updated');
assertTrue(isset($composer['autoload']['psr-4']['Acme\\HelloPackage\\']), 'composer psr-4 key was not updated');
assertTrue(! isset($composer['scripts']['test:configure']), 'template-only composer script should be removed');

$providerFile = (string) file_get_contents($tempRoot.DIRECTORY_SEPARATOR.'src/Providers/HelloPackageServiceProvider.php');
assertContains($providerFile, 'namespace Acme\\HelloPackage\\Providers;', 'provider namespace mismatch');
assertContains($providerFile, "singleton('HelloPackage'", 'provider singleton key mismatch');
assertContains($providerFile, 'config/hello-package.php', 'provider config path mismatch');
assertContains($providerFile, "'hello-package'", 'provider config key mismatch');
assertContains($providerFile, "configPath('hello-package.php')", 'provider config publish path mismatch');

$commandFile = (string) file_get_contents($tempRoot.DIRECTORY_SEPARATOR.'src/Console/HelloPackageCommand.php');
assertContains($commandFile, 'namespace Acme\\HelloPackage\\Console;', 'command namespace mismatch');
assertContains($commandFile, 'class HelloPackageCommand extends Command', 'command class mismatch');
assertContains($commandFile, "protected \$signature = 'hello-package';", 'command signature mismatch');

$facadeFile = (string) file_get_contents($tempRoot.DIRECTORY_SEPARATOR.'src/Facades/HelloPackage.php');
assertContains($facadeFile, 'namespace Acme\\HelloPackage\\Facades;', 'facade namespace mismatch');
assertContains($facadeFile, 'class HelloPackage extends Facade', 'facade class mismatch');
assertContains($facadeFile, "return 'HelloPackage';", 'facade accessor mismatch');

$readmeFile = (string) file_get_contents($tempRoot.DIRECTORY_SEPARATOR.'README.md');
assertContains($readmeFile, 'composer require acme/hello-package', 'README package install line mismatch');
assertContains($readmeFile, "@include('HelloPackage::hello-package')", 'README blade usage mismatch');
assertContains($readmeFile, '$ wp acorn hello-package', 'README command usage mismatch');

recursiveRemove($tempRoot);

echo "configure smoke test passed\n";

function recursiveCopy(string $source, string $target, array $excludeNames = []): void
{
    if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
        throw new RuntimeException("Unable to create directory: {$target}");
    }

    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException("Unable to read directory: {$source}");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (in_array($item, $excludeNames, true)) {
            continue;
        }

        $srcPath = $source.DIRECTORY_SEPARATOR.$item;
        $dstPath = $target.DIRECTORY_SEPARATOR.$item;

        if (is_dir($srcPath)) {
            recursiveCopy($srcPath, $dstPath, $excludeNames);

            continue;
        }

        if (! copy($srcPath, $dstPath)) {
            throw new RuntimeException("Failed to copy {$srcPath}");
        }
    }
}

function recursiveRemove(string $path): void
{
    if (! file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        recursiveRemove($path.DIRECTORY_SEPARATOR.$item);
    }

    rmdir($path);
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, $message."\n");
        exit(1);
    }
}

function assertContains(string $haystack, string $needle, string $message): void
{
    assertTrue(str_contains($haystack, $needle), $message);
}
