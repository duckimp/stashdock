<?php
// Unit tests for GitService command parsing
use App\Services\GitService;

test('getBranches parses branch list correctly', function () {
    $service = new GitService();

    // Create a real temp git repo
    $tmpDir = sys_get_temp_dir() . '/stashdock_git_' . uniqid();
    mkdir($tmpDir);
    config(['stashdock.parent_dir' => dirname($tmpDir)]);

    exec("git init {$tmpDir} 2>/dev/null");
    exec("git -C {$tmpDir} config user.email 'test@test.com' 2>/dev/null");
    exec("git -C {$tmpDir} config user.name 'Test' 2>/dev/null");

    // Create an initial commit so branches exist
    file_put_contents($tmpDir . '/README.md', '# Test');
    exec("git -C {$tmpDir} add . 2>/dev/null");
    exec("git -C {$tmpDir} commit -m 'init' 2>/dev/null");

    $branches = $service->getBranches($tmpDir);
    expect($branches)->toBeArray()->not->toBeEmpty();
    expect($branches[0])->toBeString()->not->toContain('*');
    expect($branches[0])->not->toStartWith(' ');

    // getActiveBranch should also work
    $activeBranch = $service->getActiveBranch($tmpDir);
    expect($activeBranch)->toBeString()->not->toBeEmpty();

    // getLog on empty-ish repo
    $log = $service->getLog($tmpDir, 5);
    expect($log)->toBeArray();

    // Cleanup
    exec("rm -rf {$tmpDir}");
});
