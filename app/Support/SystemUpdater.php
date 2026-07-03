<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Process\Process;

class SystemUpdater
{
    /**
     * Check if git is installed on the system.
     */
    public static function isGitInstalled(): bool
    {
        try {
            $process = new Process(['git', '--version']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if the application directory is a git repository.
     */
    public static function isGitRepo(): bool
    {
        return is_dir(base_path('.git'));
    }

    /**
     * Check if there are uncommitted changes in the repository.
     */
    public static function hasUncommittedChanges(): bool
    {
        try {
            if (!self::isGitRepo()) {
                return false;
            }

            // Exclude untracked files from dirty repo calculation
            $process = new Process(['git', 'status', '--porcelain', '--untracked-files=no'], base_path());
            $process->run();

            if (!$process->isSuccessful()) {
                return false;
            }

            $output = trim($process->getOutput());
            if (empty($output)) {
                return false;
            }

            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                // git status --porcelain outputs path at index 3 onwards
                $filePath = trim(substr($line, 2));

                // Skip public/install/* deletions and public/storage changes
                if (str_starts_with($filePath, 'public/install/')) {
                    continue;
                }

                if ($filePath === 'public/storage') {
                    continue;
                }

                // Any other tracked file modification means dirty repository
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get details of the current local commit or ZIP fallback version details.
     *
     * @return array{hash: string, message: string, date: string, author: string}|null
     */
    public static function getCurrentCommit(): ?array
    {
        if (self::isGitRepo()) {
            try {
                $process = new Process([
                    'git',
                    'log',
                    '-1',
                    '--pretty=format:%H|%s|%cd|%an',
                    '--date=format:%Y-%m-%d %H:%M:%S',
                ], base_path());
                $process->run();

                if ($process->isSuccessful()) {
                    $parts = explode('|', trim($process->getOutput()));
                    if (count($parts) >= 4) {
                        return [
                            'hash' => $parts[0],
                            'message' => $parts[1],
                            'date' => $parts[2],
                            'author' => $parts[3],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently and try version.json
            }
        }

        return self::getLocalVersionJson();
    }

    /**
     * Fetch raw config file from GitHub and parse the software version value.
     */
    public static function getRemoteVersion(): ?string
    {
        try {
            $url = 'https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/main/config/updater.php';
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PHP-ExpenseBuddy-Updater',
                    ],
                    'timeout' => 8,
                ],
            ];
            $context = stream_context_create($options);
            $content = @file_get_contents($url, false, $context);

            if ($content === false || empty($content)) {
                return null;
            }

            if (preg_match('/\'version\'\s*=>\s*\'([^\']+)\'/i', $content, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetch remote and check if local is behind remote main branch.
     *
     * @return array{behind: bool, message: string, remote?: array{hash: string, message: string, date: string, author: string}}
     */
    public static function checkForUpdates(): array
    {
        // 1. Try Git method first if possible
        if (self::isGitInstalled() && self::isGitRepo()) {
            return self::checkForUpdatesGit();
        }

        // 2. Fallback to API check
        $remote = self::getRemoteLatestCommit();
        if ($remote === null) {
            return [
                'behind' => false,
                'message' => 'Cannot check for updates: GitHub API is unreachable or rate limited.',
            ];
        }

        $local = self::getLocalVersionJson();
        if ($local === null) {
            return [
                'behind' => true,
                'message' => 'Update available! No local version file found (fresh install fallback).',
                'remote' => $remote,
            ];
        }

        if (trim(strtolower($local['hash'])) !== trim(strtolower($remote['hash']))) {
            return [
                'behind' => true,
                'message' => 'Update available! New commits found on origin/main (checked via GitHub API).',
                'remote' => $remote,
            ];
        }

        return [
            'behind' => false,
            'message' => 'Your application is up to date (checked via GitHub API).',
        ];
    }

    /**
     * Check updates via git commands.
     */
    private static function checkForUpdatesGit(): array
    {
        try {
            $fetch = new Process(['git', 'fetch', 'origin', 'main'], base_path());
            $fetch->run();

            if (!$fetch->isSuccessful()) {
                return [
                    'behind' => false,
                    'message' => 'Failed to fetch updates from remote origin: ' . $fetch->getErrorOutput(),
                ];
            }

            $localHashProcess = new Process(['git', 'rev-parse', 'HEAD'], base_path());
            $localHashProcess->run();
            $localHash = trim($localHashProcess->getOutput());

            $remoteHashProcess = new Process(['git', 'rev-parse', 'origin/main'], base_path());
            $remoteHashProcess->run();
            $remoteHash = trim($remoteHashProcess->getOutput());

            if ($localHash === $remoteHash) {
                return [
                    'behind' => false,
                    'message' => 'Your application is up to date.',
                ];
            }

            $behindProcess = new Process(['git', 'rev-list', 'HEAD..origin/main', '--count'], base_path());
            $behindProcess->run();
            $count = (int) trim($behindProcess->getOutput());

            if ($count > 0) {
                // Fetch commit details from git log
                $commitDetails = new Process([
                    'git',
                    'log',
                    'origin/main',
                    '-1',
                    '--pretty=format:%H|%s|%cd|%an',
                    '--date=format:%Y-%m-%d %H:%M:%S',
                ], base_path());
                $commitDetails->run();

                $remoteDetails = null;
                if ($commitDetails->isSuccessful()) {
                    $parts = explode('|', trim($commitDetails->getOutput()));
                    if (count($parts) >= 4) {
                        $remoteDetails = [
                            'hash' => $parts[0],
                            'message' => $parts[1],
                            'date' => $parts[2],
                            'author' => $parts[3],
                        ];
                    }
                }

                return [
                    'behind' => true,
                    'message' => "Update available! You are {$count} commit(s) behind remote origin/main.",
                    'remote' => $remoteDetails ?? [
                        'hash' => $remoteHash,
                        'message' => 'New commits found on origin/main',
                        'date' => now()->toDateTimeString(),
                        'author' => 'GitHub origin/main',
                    ],
                ];
            }

            return [
                'behind' => false,
                'message' => 'Local commits are ahead or diverged from remote.',
            ];
        } catch (\Throwable $e) {
            return [
                'behind' => false,
                'message' => 'Error checking for updates: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get local version.json file contents.
     */
    public static function getLocalVersionJson(): ?array
    {
        $path = base_path('version.json');
        if (!is_file($path)) {
            return null;
        }

        try {
            return json_decode(file_get_contents($path), true);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Write version.json file.
     */
    public static function writeLocalVersionJson(array $versionInfo): void
    {
        try {
            file_put_contents(base_path('version.json'), json_encode($versionInfo, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            // Silence write failures
        }
    }

    /**
     * Query GitHub API for latest commit.
     */
    public static function getRemoteLatestCommit(): ?array
    {
        try {
            $url = 'https://api.github.com/repos/Mohit-Hasan/expense-buddy/commits/main';
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PHP-ExpenseBuddy-Updater',
                    ],
                    'timeout' => 8,
                ],
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false || empty($response)) {
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['sha'])) {
                return null;
            }

            return [
                'hash' => $data['sha'],
                'message' => $data['commit']['message'] ?? 'No commit message',
                'date' => isset($data['commit']['author']['date']) ? str_replace(['T', 'Z'], [' ', ''], $data['commit']['author']['date']) : '',
                'author' => $data['commit']['author']['name'] ?? 'Unknown',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
    /**
     * Check if ZipArchive PHP extension is loaded.
     */
    public static function isZipExtensionLoaded(): bool
    {
        return extension_loaded('zip');
    }

    /**
     * Perform the full update process, reporting progress via callback.
     *
     * @param callable(string, string): void $logOutput
     * @param bool $force
     * @return bool
     */
    public static function runUpdateStreamed(callable $logOutput, bool $force = false): bool
    {
        $success = true;

        try {
            // 1. Enter maintenance mode
            $logOutput('MAINTENANCE', 'Entering maintenance mode...');
            $down = new Process(['php', 'artisan', 'down', '--refresh=15'], base_path());
            $down->run();
            $logOutput('MAINTENANCE', $down->getOutput() ?: $down->getErrorOutput());

            // If force is enabled, discard local changes before checking git or zip
            if ($force) {
                if (self::isGitInstalled() && self::isGitRepo()) {
                    $logOutput('FORCE_CLEAN', 'Force clean enabled: Discarding local modifications...');
                    $reset = new Process(['git', 'reset', '--hard', 'HEAD'], base_path());
                    $reset->run();
                    $logOutput('FORCE_CLEAN', $reset->getOutput() ?: $reset->getErrorOutput());

                    $clean = new Process(['git', 'clean', '-fd'], base_path());
                    $clean->run();
                    $logOutput('FORCE_CLEAN', $clean->getOutput() ?: $clean->getErrorOutput());
                }
            }

            // Get Remote Commit Details to write into version.json later
            $remote = self::getRemoteLatestCommit();
            $remoteHash = $remote['hash'] ?? 'main';

            // 2. Pull or Download ZIP
            if (self::isGitInstalled() && self::isGitRepo()) {
                $logOutput('GIT_PULL', 'Pulling updates from origin/main...');
                $pull = new Process(['git', 'pull', 'origin', 'main'], base_path());
                $pull->run();
                $logOutput('GIT_PULL', $pull->getOutput() ?: $pull->getErrorOutput());

                if (!$pull->isSuccessful()) {
                    $success = false;
                    $logOutput('GIT_PULL', 'ERROR: Git pull failed!');
                }
            } else {
                $logOutput('ZIP_FALLBACK', 'Git not initialized or missing. Initializing ZIP extraction fallback...');
                if (!self::isZipExtensionLoaded()) {
                    $success = false;
                    $logOutput('ZIP_FALLBACK', 'ERROR: ZipArchive extension is not loaded in PHP!');
                } else {
                    $zipSuccess = self::runZipUpdate($remoteHash, $remote, $logOutput);
                    if (!$zipSuccess) {
                        $success = false;
                    }
                }
            }

            if ($success) {
                // 3. Database Migrations
                $logOutput('MIGRATION', 'Running database migrations...');
                $migrate = new Process(['php', 'artisan', 'migrate', '--force'], base_path());
                $migrate->run();
                $logOutput('MIGRATION', $migrate->getOutput() ?: $migrate->getErrorOutput());

                if (!$migrate->isSuccessful()) {
                    $success = false;
                    $logOutput('MIGRATION', 'ERROR: Migrations failed!');
                }
            }

            if ($success) {
                // 4. Cache Clear
                $logOutput('CACHE_CLEAR', 'Clearing application optimizations and caches...');
                $clear = new Process(['php', 'artisan', 'optimize:clear'], base_path());
                $clear->run();
                $logOutput('CACHE_CLEAR', $clear->getOutput() ?: $clear->getErrorOutput());
            }

        } catch (\Throwable $e) {
            $success = false;
            $logOutput('FATAL_ERROR', $e->getMessage());
        } finally {
            // 5. Exit maintenance mode
            $logOutput('MAINTENANCE', 'Bringing application back online...');
            $up = new Process(['php', 'artisan', 'up'], base_path());
            $up->run();
            $logOutput('MAINTENANCE', $up->getOutput() ?: $up->getErrorOutput());
        }

        return $success;
    }

    /**
     * Perform the full update process and return the logs.
     *
     * @return array{success: bool, logs: string}
     */
    public static function runUpdate(): array
    {
        $logs = [];
        $logOutput = function (string $step, string $output) use (&$logs): void {
            $logs[] = "[{$step}] " . trim($output);
        };

        $success = self::runUpdateStreamed($logOutput);

        return [
            'success' => $success,
            'logs' => implode("\n", $logs),
        ];
    }

    /**
     * Download and extract main.zip and overwrite application files.
     */
    private static function runZipUpdate(string $remoteHash, ?array $remoteCommitDetails, callable $logOutput): bool
    {
        try {
            $zipUrl = 'https://github.com/Mohit-Hasan/expense-buddy/archive/refs/heads/main.zip';
            $tempDir = storage_path('app/updater_temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $zipPath = $tempDir . '/update.zip';

            $logOutput('DOWNLOAD', 'Downloading update ZIP from GitHub...');

            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PHP-ExpenseBuddy-Updater',
                    ],
                    'timeout' => 20,
                ],
            ];
            $context = stream_context_create($options);
            $zipData = @file_get_contents($zipUrl, false, $context);

            if ($zipData === false || empty($zipData)) {
                $logOutput('DOWNLOAD', 'ERROR: Failed to download update zip from GitHub.');

                return false;
            }

            file_put_contents($zipPath, $zipData);
            $logOutput('DOWNLOAD', 'Download complete. Size: ' . strlen($zipData) . ' bytes.');

            // Extract the ZIP
            $logOutput('EXTRACTION', 'Extracting ZIP file...');
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $logOutput('EXTRACTION', 'ERROR: Failed to open ZIP archive.');

                return false;
            }
            $zip->extractTo($tempDir);
            $zip->close();
            $logOutput('EXTRACTION', 'Extraction complete.');

            // The extracted files will be under `expense-buddy-main` inside $tempDir
            $extractedDir = $tempDir . '/expense-buddy-main';
            if (!is_dir($extractedDir)) {
                $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
                if (count($dirs) > 0) {
                    $extractedDir = $dirs[0];
                } else {
                    $logOutput('EXTRACTION', 'ERROR: Extracted directory not found.');

                    return false;
                }
            }

            // Copy files recursively
            $logOutput('UPGRADE', 'Overwriting application files...');
            self::copyDirectory($extractedDir, base_path(), $logOutput);
            $logOutput('UPGRADE', 'File overwrite complete.');

            // Write version details
            if ($remoteCommitDetails) {
                self::writeLocalVersionJson($remoteCommitDetails);
            } else {
                self::writeLocalVersionJson([
                    'hash' => $remoteHash,
                    'message' => 'Updated via zip release fallback',
                    'date' => now()->toDateTimeString(),
                    'author' => 'System Updater',
                ]);
            }

            // Cleanup
            $logOutput('CLEANUP', 'Cleaning up temporary files...');
            self::recursiveRmdir($tempDir);
            $logOutput('CLEANUP', 'Cleanup complete.');

            return true;
        } catch (\Throwable $e) {
            $logOutput('FATAL_ERROR', 'Zip update failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Copy directory recursively while preserving user configurations and uploads.
     */
    private static function copyDirectory(string $src, string $dst, callable $logOutput): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // Skip critical environment files to avoid data loss
            if ($file === '.env' || $file === 'database.sqlite') {
                $logOutput('UPGRADE', "Skipping protected configuration/db file: {$file}");

                continue;
            }

            // Skip dependencies folders (node_modules, vendor)
            if ($file === 'node_modules' || $file === 'vendor') {
                continue;
            }

            if (is_dir($srcPath)) {
                // If it is the storage folder, skip copying to prevent overwriting user uploads
                if ($file === 'storage') {
                    $logOutput('UPGRADE', "Skipping user uploads storage directory: {$file}");

                    continue;
                }

                self::copyDirectory($srcPath, $dstPath, $logOutput);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Delete directory recursively.
     */
    private static function recursiveRmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (is_dir($dir . '/' . $object) && !is_link($dir . '/' . $object)) {
                        self::recursiveRmdir($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
