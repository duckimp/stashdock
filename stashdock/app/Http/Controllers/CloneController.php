<?php

namespace App\Http\Controllers;

use App\Services\GitService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloneController extends Controller
{
    public function __construct(
        private GitService      $gitService,
        private SettingsService $settingsService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'repo_url' => 'required|url',
        ]);

        $repoUrl = $request->input('repo_url');

        // Extract repo name from URL for destination folder name
        $repoName = basename(rtrim($repoUrl, '/'));
        $repoName = preg_replace('/\.git$/', '', $repoName);

        $parentDir   = config('stashdock.parent_dir');
        $destination = $parentDir . DIRECTORY_SEPARATOR . $repoName;

        try {
            $patUrl = $this->settingsService->buildPatUrl($repoName);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }

        $result = $this->gitService->cloneRepo($patUrl, $destination);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => trim($result->error ?: $result->output),
            ]);
        }

        return response()->json(['status' => 'ok', 'output' => $result->output]);
    }
}
