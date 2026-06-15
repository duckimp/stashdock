<?php

namespace App\DTOs;

class ProjectDTO
{
    public function __construct(
        public string $name,
        public string $path,
        public bool $isGitRepo,
        public string $framework,      // 'Laravel' | 'React/Node' | 'Unknown'
        public string $activeBranch,
        public string $localStatus,    // 'Clean' | 'Dirty' | 'Not Initialized'
        public string $remoteStatus,   // 'Synced' | 'Need Push' | 'Unknown' | 'Not Initialized'
        public array $branches,
    ) {}
}
