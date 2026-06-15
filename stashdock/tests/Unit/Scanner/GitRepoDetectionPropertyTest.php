<?php
// Feature: stashdock, Property 7: Git repository detection
use App\Services\GitService;
use App\Services\ScannerService;

test('git repository detection is accurate', function () {
    $tmpDir = sys_get_temp_dir() . '/stashdock_gitrepo_' . uniqid();
    mkdir($tmpDir);
    config(['stashdock.parent_dir' => $tmpDir]);
    config(['stashdock.excluded_folders' => []]);

    $gitFolder    = $tmpDir . '/has-git';
    $nonGitFolder = $tmpDir . '/no-git';
    mkdir($gitFolder);
    mkdir($nonGitFolder);
    mkdir($gitFolder . '/.git'); // make it a git repo

    $service  = new ScannerService(new GitService());
    $projects = $service->scanProjects($tmpDir);

    $byName = [];
    foreach ($projects as $p) {
        $byName[$p->name] = $p;
    }

    expect($byName['has-git']->isGitRepo)->toBeTrue();
    expect($byName['no-git']->isGitRepo)->toBeFalse();

    // Cleanup
    rmdir($gitFolder . '/.git');
    rmdir($gitFolder);
    rmdir($nonGitFolder);
    rmdir($tmpDir);
});
