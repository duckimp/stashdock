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

test('addRemote and getRemoteUrl work correctly', function () {
    $service = new GitService();

    // Create a real temp git repo
    $tmpDir = sys_get_temp_dir() . '/stashdock_git_remote_' . uniqid();
    mkdir($tmpDir);
    config(['stashdock.parent_dir' => dirname($tmpDir)]);

    exec("git init {$tmpDir} 2>/dev/null");

    // Initially getRemoteUrl should fail / not succeed
    $res = $service->getRemoteUrl($tmpDir);
    expect($res->success)->toBeFalse();

    // Add remote
    $res = $service->addRemote($tmpDir, 'https://github.com/example/repo.git');
    expect($res->success)->toBeTrue();

    // Get remote should succeed and return the URL
    $res = $service->getRemoteUrl($tmpDir);
    expect($res->success)->toBeTrue();
    expect(trim($res->output))->toBe('https://github.com/example/repo.git');

    // Update remote URL
    $res = $service->addRemote($tmpDir, 'https://github.com/example/new-repo.git');
    expect($res->success)->toBeTrue();

    $res = $service->getRemoteUrl($tmpDir);
    expect($res->success)->toBeTrue();
    expect(trim($res->output))->toBe('https://github.com/example/new-repo.git');

    // Cleanup
    exec("rm -rf {$tmpDir}");
});
