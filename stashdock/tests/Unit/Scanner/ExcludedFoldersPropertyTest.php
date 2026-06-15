<?php
// Feature: stashdock, Property 6: excluded folders never appear in scan results
use App\Services\GitService;
use App\Services\ScannerService;

test('excluded folders never appear in scan results', function () {
    $tmpDir = sys_get_temp_dir() . '/stashdock_test_' . uniqid();
    mkdir($tmpDir);

    // Create some normal folders and some excluded folders
    $excluded = ['git-dashboard-tools', 'stashdock'];
    $normal   = ['project-a', 'project-b', 'project-c'];

    foreach (array_merge($excluded, $normal) as $folder) {
        mkdir($tmpDir . '/' . $folder);
    }

    // Override the config for this test
    config(['stashdock.parent_dir'       => $tmpDir]);
    config(['stashdock.excluded_folders' => $excluded]);

    $service  = new ScannerService(new GitService());
    $projects = $service->scanProjects($tmpDir);

    $names = array_map(fn($p) => $p->name, $projects);

    foreach ($excluded as $excludedName) {
        expect($names)->not->toContain($excludedName);
    }

    foreach ($normal as $normalName) {
        expect($names)->toContain($normalName);
    }

    // Cleanup
    foreach (array_merge($excluded, $normal) as $folder) {
        rmdir($tmpDir . '/' . $folder);
    }
    rmdir($tmpDir);
});
