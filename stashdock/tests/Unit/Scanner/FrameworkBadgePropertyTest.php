<?php
// Feature: stashdock, Property 8: framework badge detection
use App\Services\ScannerService;
use App\Services\GitService;

test('framework badge detection is correct', function () {
    $tmpDir = sys_get_temp_dir() . '/stashdock_fw_' . uniqid();
    mkdir($tmpDir);

    $cases = [
        'laravel-project' => ['composer.json' => true,  'package.json' => false, 'expected' => 'Laravel'],
        'react-project'   => ['composer.json' => false, 'package.json' => true,  'expected' => 'React/Node'],
        'both-project'    => ['composer.json' => true,  'package.json' => true,  'expected' => 'Laravel'],
        'unknown-project' => ['composer.json' => false, 'package.json' => false, 'expected' => 'Unknown'],
    ];

    $service = new ScannerService(new GitService());

    foreach ($cases as $folderName => $case) {
        $path = $tmpDir . '/' . $folderName;
        mkdir($path);

        if ($case['composer.json']) file_put_contents($path . '/composer.json', '{}');
        if ($case['package.json'])  file_put_contents($path . '/package.json',  '{}');

        $result = $service->detectFramework($path);
        expect($result)->toBe($case['expected'], "Failed for {$folderName}");

        // Cleanup files
        if ($case['composer.json']) unlink($path . '/composer.json');
        if ($case['package.json'])  unlink($path . '/package.json');
        rmdir($path);
    }

    rmdir($tmpDir);
});
