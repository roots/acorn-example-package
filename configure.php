#!/usr/bin/env php
<?php

function ask(string $question, string $default = ''): string
{
    $answer = readline($question.($default ? " ({$default})" : null).': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question.' ('.($default ? 'Y/n' : 'y/N').')');

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function writeln(string $line): void
{
    echo $line.PHP_EOL;
}

function run(string $command): string
{
    return trim((string) shell_exec($command));
}

function str_after(string $subject, string $search): string
{
    $pos = strrpos($subject, $search);

    if ($pos === false) {
        return $subject;
    }

    return substr($subject, $pos + strlen($search));
}

function slugify(string $subject): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $subject), '-'));
}

function title_case(string $subject): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $subject)));
}

function replace_in_file(string $file, array $replacements): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function determineSeparator(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function replaceForWindows(): array
{
    return preg_split('/\\r\\n|\\r|\\n/', run('dir /S /B * | findstr /v /i .git\ | findstr /v /i vendor | findstr /v /i '.basename(__FILE__).' | findstr /r /i /M /F:/ ":author :vendor :package VendorName vendor_name vendor_slug author@domain.com"'));
}

function replaceForAllOtherOSes(): array
{
    return explode(PHP_EOL, run('grep -E -r -l -i ":author|:vendor|:package|VendorName|vendor_name|vendor_slug|author@domain.com" --exclude-dir=vendor ./* ./.github/* | grep -v '.basename(__FILE__)));
}

function getGitHubApiEndpoint(string $endpoint): ?stdClass
{
    try {
        $curl = curl_init("https://api.github.com/{$endpoint}");
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: spatie-configure-script/1.0',
            ],
        ]);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($statusCode === 200) {
            return json_decode($response);
        }
    } catch (Exception $e) {
        // ignore
    }

    return null;
}

function searchCommitsForGitHubUsername(): string
{
    $authorName = strtolower(trim(shell_exec('git config user.name')));

    $committersRaw = shell_exec("git log --author='@users.noreply.github.com' --pretty='%an:%ae' --reverse");
    $committersLines = explode("\n", $committersRaw ?? '');
    $committers = array_filter(array_map(function ($line) use ($authorName) {
        $line = trim($line);
        [$name, $email] = explode(':', $line) + [null, null];

        return [
            'name' => $name,
            'email' => $email,
            'isMatch' => strtolower($name) === $authorName && ! str_contains($name, '[bot]'),
        ];
    }, $committersLines), fn ($item) => $item['isMatch']);

    if (empty($committers)) {
        return '';
    }

    $firstCommitter = reset($committers);

    return explode('@', $firstCommitter['email'])[0] ?? '';
}

function guessGitHubUsernameUsingCli()
{
    try {
        if (preg_match('/ogged in to github\.com as ([a-zA-Z-_]+).+/', shell_exec('gh auth status -h github.com 2>&1'), $matches)) {
            return $matches[1];
        }
    } catch (Exception $e) {
        // ignore
    }

    return '';
}

function guessGitHubUsername(): string
{
    $username = searchCommitsForGitHubUsername();
    if (! empty($username)) {
        return $username;
    }

    $username = guessGitHubUsernameUsingCli();
    if (! empty($username)) {
        return $username;
    }

    // fall back to using the username from the git remote
    $remoteUrl = shell_exec('git config remote.origin.url');
    $remoteUrlParts = explode('/', str_replace(':', '/', trim($remoteUrl)));

    return $remoteUrlParts[1] ?? '';
}

function guessGitHubVendorInfo($authorName, $username): array
{
    $remoteUrl = shell_exec('git config remote.origin.url');
    $remoteUrlParts = explode('/', str_replace(':', '/', trim($remoteUrl)));

    $response = getGitHubApiEndpoint("orgs/{$remoteUrlParts[1]}");

    if ($response === null) {
        return [$authorName, $username];
    }

    return [$response->name ?? $authorName, $response->login ?? $username];
}

$gitName = run('git config user.name');
$authorName = ask('Author name', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Author email', $gitEmail);
$authorUsername = ask('Author username', guessGitHubUsername());

$guessGitHubVendorInfo = guessGitHubVendorInfo($authorName, $authorUsername);

$vendorName = ask('Vendor name', $guessGitHubVendorInfo[0]);
$vendorUsername = ask('Vendor username', $guessGitHubVendorInfo[1] ?? slugify($vendorName));
$vendorSlug = slugify($vendorUsername);

$vendorNamespace = str_replace(' ', '', str_replace('-', '', ucwords($vendorName)));
$vendorNamespace = ask('Vendor namespace', $vendorNamespace);

$currentDirectory = getcwd();
$folderName = basename($currentDirectory);

$packageName = ask('Package name', $folderName);
$packageSlug = slugify($packageName);

$className = title_case($packageName);
$className = ask('Class name', $className);
$variableName = lcfirst($className);
$description = ask('Package description', "This is my package {$packageSlug}");

writeln('------');
writeln("Author     : {$authorName} ({$authorUsername}, {$authorEmail})");
writeln("Vendor     : {$vendorName} ({$vendorSlug})");
writeln("Package    : {$packageSlug} <{$description}>");
writeln("Namespace  : {$vendorNamespace}\\{$className}");
writeln("Class name : {$className}");
writeln('------');

writeln('This script will replace the above values in all relevant files in the project directory.');

if (! confirm('Modify files?', true)) {
    exit(1);
}

$files = (str_starts_with(strtoupper(PHP_OS), 'WIN') ? replaceForWindows() : replaceForAllOtherOSes());

foreach ($files as $file) {
    replace_in_file($file, [
        ':author_name' => $authorName,
        ':author_username' => $authorUsername,
        ':author_email' => $authorEmail,
        ':vendor_name' => $vendorNamespace,
        ':vendor_slug' => $vendorSlug,
        'VendorName' => $vendorNamespace,
        ':package_name' => $packageName,
        ':package_slug' => $packageSlug,
        'Example' => $className,
        'ExamplePackage' => $className,
        'example' => $packageSlug,
        ':package_description' => $description,
        ':class_name' => $className
    ]);

    match (true) {
        str_contains($file, determineSeparator('src/Example.php')) => rename($file, determineSeparator('./src/'.$className.'.php')),
        str_contains($file, determineSeparator('src/Providers/ExampleServiceProvider.php')) => rename($file, determineSeparator('./src/providers/'.$className.'ServiceProvider.php')),
        str_contains($file, determineSeparator('src/Console/ExampleCommand.php')) => rename($file, determineSeparator('./src/console/'.$className.'.php')),
        str_contains($file, determineSeparator('src/Facades/Example.php')) => rename($file, determineSeparator('./src/Facades/'.$className.'.php')),
        str_contains($file, determineSeparator('resources/views/example.blade.php')) => rename($file, determineSeparator('./resources/views/'.$packageSlug.'.blade.php')),
        default => [],
    };
}

rename(determineSeparator('config/example.php'), determineSeparator('./config/'.$packageSlug.'.php'));

confirm('Execute `composer install`?') && run('composer install');

confirm('Let this script delete itself?', true) && unlink(__FILE__);
