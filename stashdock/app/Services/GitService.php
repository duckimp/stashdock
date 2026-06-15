<?php

namespace App\Services;

use App\Models\GitActivityLog;

class GitService
{
    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Execute a Git command in the given working directory.
     *
     * $command is always an array of arguments — never a string — to prevent
     * shell injection. bypass_shell ensures the OS receives the argument list
     * directly without passing it through a shell interpreter.
     */
    private function run(array $command, string $cwd): GitResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        // Disable any interactive password prompts — git runs headless from a web server
        $env = array_merge($_ENV ?: [], [
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ASKPASS'         => 'echo',
            'HOME'                => $_SERVER['HOME'] ?? getenv('HOME') ?: '/tmp',
        ]);

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $cwd,
            $env,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            return new GitResult(false, '', 'Failed to start process', -1);
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new GitResult($exitCode === 0, $stdout, $stderr, $exitCode);
    }

    /**
     * Validate that $path is a real path that begins with the configured
     * parent_dir. Throws \InvalidArgumentException on failure.
     *
     * Task 4.6 — path traversal guard.
     */
    private function validatePath(string $path): void
    {
        $realParent = realpath(config('stashdock.parent_dir'));
        $realPath   = realpath($path);

        if ($realParent === false || $realPath === false) {
            throw new \InvalidArgumentException("Path could not be resolved: {$path}");
        }

        if (strpos($realPath, $realParent . DIRECTORY_SEPARATOR) !== 0 && $realPath !== $realParent) {
            throw new \InvalidArgumentException("Path is outside the allowed parent directory: {$path}");
        }
    }

    // -------------------------------------------------------------------------
    // Task 4.2 — Repo inspection
    // -------------------------------------------------------------------------

    /**
     * Check whether a directory is an initialised Git repository.
     * Uses a filesystem check only — no shell call required.
     */
    public function isGitRepo(string $path): bool
    {
        $this->validatePath($path);

        return is_dir($path . DIRECTORY_SEPARATOR . '.git');
    }

    /**
     * Return the porcelain status output for the repository at $path.
     */
    public function getStatus(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'status', '--porcelain'], $path);
    }

    /**
     * Return the diff output for the repository at $path.
     */
    public function getDiff(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'diff'], $path);
    }

    /**
     * Return the currently checked-out branch name, or an empty string on
     * failure (e.g. detached HEAD or uninitialised repo).
     */
    public function getActiveBranch(string $path): string
    {
        $this->validatePath($path);

        // Optimization: read the HEAD file directly to avoid spawning a process
        $headFile = $path . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';
        if (file_exists($headFile)) {
            $headContent = @file_get_contents($headFile);
            if ($headContent !== false) {
                $headContent = trim($headContent);
                if (str_starts_with($headContent, 'ref: refs/heads/')) {
                    return substr($headContent, 16);
                }
            }
        }

        $result = $this->run(['git', 'branch', '--show-current'], $path);

        return $result->success ? trim($result->output) : '';
    }

    /**
     * Get the remote status (difference between upstream and local HEAD).
     */
    public function getRemoteStatus(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'log', '@{u}..HEAD', '--oneline'], $path);
    }

    /**
     * Return a flat array of branch names for the repository at $path.
     * The leading "* " marker (current branch) is stripped.
     */
    public function getBranches(string $path): array
    {
        $this->validatePath($path);

        $result = $this->run(['git', 'branch', '--list'], $path);

        if (! $result->success || empty(trim($result->output))) {
            return [];
        }

        return array_values(array_filter(array_map(function (string $line): string {
            return trim(ltrim($line, '* '));
        }, explode("\n", trim($result->output)))));
    }

    // -------------------------------------------------------------------------
    // Task 4.3 — Log, init, remote, stage, commit, push
    // -------------------------------------------------------------------------

    /**
     * Return up to $limit one-line log entries as an array of strings.
     */
    public function getLog(string $path, int $limit = 5): array
    {
        $this->validatePath($path);

        $result = $this->run(['git', 'log', '--oneline', '-n', (string) $limit], $path);

        if (! $result->success || empty(trim($result->output))) {
            return [];
        }

        return array_filter(explode("\n", trim($result->output)));
    }

    /**
     * Initialise a new Git repository at $path.
     */
    public function init(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'init'], $path);
    }

    /**
     * Add a remote named "origin" pointing to $url.
     */
    public function addRemote(string $path, string $url): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'remote', 'add', 'origin', $url], $path);
    }

    /**
     * Stage all changes in the working tree (git add .).
     */
    public function add(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'add', '.'], $path);
    }

    /**
     * Create a commit with the given message.
     * The message is passed as a discrete array element — never interpolated.
     */
    public function commit(string $path, string $message): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'commit', '-m', $message], $path);
    }

    /**
     * Push to the remote using a PAT-injected URL.
     * Sets the remote URL temporarily, pushes, then restores the original remote.
     */
    public function push(string $path, string $patUrl): GitResult
    {
        $this->validatePath($path);

        // Get current remote URL to restore after push
        $getRemote = $this->run(['git', 'remote', 'get-url', 'origin'], $path);
        $originalUrl = trim($getRemote->output);

        // Temporarily set remote URL with PAT injected
        $this->run(['git', 'remote', 'set-url', 'origin', $patUrl], $path);

        // Push to origin current branch
        $result = $this->run(['git', 'push', 'origin', 'HEAD'], $path);

        // Restore original URL (strip PAT from stored remote)
        if ($originalUrl) {
            $this->run(['git', 'remote', 'set-url', 'origin', $originalUrl], $path);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Task 4.4 — Fetch, pull, branch ops, stash, reset, clean, clone
    // -------------------------------------------------------------------------

    /**
     * Fetch from all configured remotes.
     */
    public function fetch(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'fetch'], $path);
    }

    /**
     * Pull from the tracking remote branch.
     */
    public function pull(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'pull'], $path);
    }

    /**
     * Switch to an existing branch.
     * Branch name is a discrete array element.
     */
    public function switchBranch(string $path, string $branch): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'switch', $branch], $path);
    }

    /**
     * Create and check out a new branch.
     * Branch name is a discrete array element.
     */
    public function createBranch(string $path, string $branch): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'checkout', '-b', $branch], $path);
    }

    /**
     * Stash all local modifications.
     */
    public function stash(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'stash'], $path);
    }

    /**
     * Apply the most recently stashed changes and remove them from the stash.
     */
    public function stashPop(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'stash', 'pop'], $path);
    }

    /**
     * Undo the last commit while keeping its changes staged (soft reset).
     */
    public function softReset(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'reset', '--soft', 'HEAD~1'], $path);
    }

    /**
     * Hard-reset the working tree to a specific commit.
     * Commit ID is a discrete array element.
     */
    public function hardReset(string $path, string $commitId): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'reset', '--hard', $commitId], $path);
    }

    /**
     * Remove untracked files and directories from the working tree.
     */
    public function clean(string $path): GitResult
    {
        $this->validatePath($path);

        return $this->run(['git', 'clean', '-fd'], $path);
    }

    /**
     * Clone a repository into $destinationPath.
     *
     * The command runs in the parent directory of $destinationPath so that Git
     * creates the target folder itself. Both the PAT-injected URL and the
     * destination folder name are discrete array elements.
     */
    public function cloneRepo(string $patUrl, string $destinationPath): GitResult
    {
        $parentDir = dirname($destinationPath);

        $this->validatePath($parentDir);

        return $this->run(['git', 'clone', $patUrl, basename($destinationPath)], $parentDir);
    }

    // -------------------------------------------------------------------------
    // Task 4.5 — Activity log sync
    // -------------------------------------------------------------------------

    /**
     * Read the last 365 commit dates for the project at $path and upsert
     * GitActivityLog records keyed on (project_name, activity_type, executed_at).
     */
    public function syncActivityLog(string $path, string $projectName): void
    {
        $this->validatePath($path);

        $result = $this->run(['git', 'log', '--format=%ad', '--date=short', '-n', '365'], $path);

        if (! $result->success || empty(trim($result->output))) {
            return;
        }

        $dates = array_filter(array_map('trim', explode("\n", trim($result->output))));

        foreach ($dates as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            GitActivityLog::updateOrCreate(
                ['project_name' => $projectName, 'activity_type' => 'commit', 'executed_at' => $date],
                []
            );
        }
    }
}
