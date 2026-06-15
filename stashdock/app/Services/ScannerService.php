<?php

namespace App\Services;

use App\DTOs\ProjectDTO;

class ScannerService
{
    public function __construct(private GitService $gitService) {}

    /**
     * Return the list of folder names that should be excluded from scanning.
     * Reads from config('stashdock.excluded_folders').
     */
    public function getExcludedFolders(): array
    {
        return config('stashdock.excluded_folders', []);
    }

    /**
     * Detect the framework of a project folder.
     *
     * Rules:
     * - 'Laravel'    if composer.json is present
     * - 'React/Node' if package.json is present AND composer.json is absent
     * - 'Unknown'    if neither file is present
     */
    public function detectFramework(string $path): string
    {
        if (file_exists($path . DIRECTORY_SEPARATOR . 'composer.json')) {
            return 'Laravel';
        }

        if (file_exists($path . DIRECTORY_SEPARATOR . 'package.json')) {
            return 'React/Node';
        }

        return 'Unknown';
    }

    /**
     * Scan immediate subdirectories of $parentDir and return a ProjectDTO for each.
     *
     * - Only one level deep (not recursive).
     * - Skips any folder name matching getExcludedFolders().
     * - Applies a path traversal guard: each resolved subfolder must start with realpath($parentDir).
     *
     * @return ProjectDTO[]
     */
    public function scanProjects(string $parentDir): array
    {
        $realParent = realpath($parentDir);

        if ($realParent === false || ! is_dir($realParent)) {
            return [];
        }

        $excluded = $this->getExcludedFolders();
        $projects = [];

        $entries = glob($realParent . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            $folderName = basename($entry);

            // Skip excluded folder names
            if (in_array($folderName, $excluded, true)) {
                continue;
            }

            // Path traversal guard: resolved path must be a direct child of $realParent
            $realEntry = realpath($entry);

            if ($realEntry === false) {
                continue;
            }

            if (strpos($realEntry, $realParent . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }

            $projects[] = $this->buildProjectDTO($folderName, $realEntry);
        }

        return $projects;
    }

    /**
     * Build a ProjectDTO for the given subfolder using GitService.
     */
    private function buildProjectDTO(string $name, string $path): ProjectDTO
    {
        $isGitRepo = $this->gitService->isGitRepo($path);

        if (! $isGitRepo) {
            return new ProjectDTO(
                name: $name,
                path: $path,
                isGitRepo: false,
                framework: $this->detectFramework($path),
                activeBranch: '',
                localStatus: 'Not Initialized',
                remoteStatus: 'Not Initialized',
                branches: [],
            );
        }

        // Determine local status
        $statusResult = $this->gitService->getStatus($path);
        $localStatus  = 'Clean';

        if ($statusResult->success && trim($statusResult->output) !== '') {
            $localStatus = 'Dirty';
        }

        return new ProjectDTO(
            name: $name,
            path: $path,
            isGitRepo: true,
            framework: $this->detectFramework($path),
            activeBranch: $this->gitService->getActiveBranch($path),
            localStatus: $localStatus,
            remoteStatus: $this->getRemoteStatus($path),
            branches: $this->gitService->getBranches($path),
        );
    }

    /**
     * Determine the remote sync status of a git repository.
     *
     * Returns:
     * - 'Not Initialized' if not a git repo
     * - 'Need Push'       if unpushed commits exist
     * - 'Synced'          if no unpushed commits
     * - 'Unknown'         if the upstream is not configured or the command fails
     */
    private function getRemoteStatus(string $path): string
    {
        if (! $this->gitService->isGitRepo($path)) {
            return 'Not Initialized';
        }

        $result = $this->gitService->getRemoteStatus($path);

        if (! $result->success) {
            return 'Unknown';
        }

        return trim($result->output) !== '' ? 'Need Push' : 'Synced';
    }
}
