<?php

namespace App\Http\Controllers;

use App\Models\GitActivityLog;
use App\Services\GitService;
use App\Services\GitResult;
use App\Services\ScannerService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private GitService      $gitService,
        private ScannerService  $scannerService,
        private SettingsService $settingsService,
    ) {}

    public function index()
    {
        $parentDir = config('stashdock.parent_dir');
        $projects  = $this->scannerService->scanProjects($parentDir);
        return view('projects.index', compact('projects'));
    }

    public function gitAction(Request $request, string $project): JsonResponse
    {
        // Resolve the absolute project path safely
        $parentDir   = realpath(config('stashdock.parent_dir'));
        $projectPath = realpath($parentDir . DIRECTORY_SEPARATOR . urldecode($project));

        if ($projectPath === false || strpos($projectPath, $parentDir . DIRECTORY_SEPARATOR) !== 0) {
            return response()->json(['status' => 'error', 'message' => 'Invalid project path.'], 422);
        }

        $action = $request->input('action');

        // Danger Zone confirmation guard
        if (in_array($action, ['hard-reset', 'clean'], true)) {
            if ($request->input('confirm') !== 'CONFIRMED') {
                return response()->json(['status' => 'error', 'message' => 'Action requires explicit confirmation.'], 422);
            }
        }

        try {
            $result = match ($action) {
                'init'          => $this->gitService->init($projectPath),
                'add-remote'    => $this->gitService->addRemote($projectPath, $request->input('remote_url', '')),
                'get-remote'    => $this->gitService->getRemoteUrl($projectPath),
                'diff'          => $this->gitService->getDiff($projectPath),
                'status'        => $this->gitService->getStatus($projectPath),
                'fetch'         => $this->gitService->fetch($projectPath),
                'pull'          => $this->gitService->pull($projectPath),
                'quick-sync'    => $this->handleQuickSync($request, $projectPath, $project),
                'switch-branch' => $this->gitService->switchBranch($projectPath, $request->input('branch', '')),
                'create-branch' => $this->gitService->createBranch($projectPath, $request->input('branch', '')),
                'stash'         => $this->gitService->stash($projectPath),
                'stash-pop'     => $this->gitService->stashPop($projectPath),
                'soft-reset'    => $this->gitService->softReset($projectPath),
                'hard-reset'    => $this->gitService->hardReset($projectPath, $request->input('commit_id', 'HEAD')),
                'clean'         => $this->gitService->clean($projectPath),
                default         => null,
            };

            if ($result === null) {
                return response()->json(['status' => 'error', 'message' => "Unknown action: {$action}"], 422);
            }

            if (! $result->success) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $this->scrubSensitive(trim($result->error ?: $result->output)),
                    'output'  => $this->scrubSensitive($result->output),
                    'error'   => $this->scrubSensitive($result->error),
                ]);
            }

            return response()->json(['status' => 'ok', 'output' => $result->output]);

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function handleQuickSync(Request $request, string $projectPath, string $projectName): GitResult
    {
        // Check if there are local changes to commit
        $status = $this->gitService->getStatus($projectPath);
        $isDirty = $status->success && trim($status->output) !== '';

        if ($isDirty) {
            $message = trim($request->input('message', ''));

            if ($message === '') {
                // Return a fake failure GitResult — validation error
                return new GitResult(false, '', 'Commit message cannot be empty.', 1);
            }

            $add = $this->gitService->add($projectPath);
            if (! $add->success) return $add;

            $commit = $this->gitService->commit($projectPath, $message);
            if (! $commit->success) return $commit;

            // Log commit activity
            GitActivityLog::create([
                'project_name'  => $projectName,
                'activity_type' => 'commit',
                'executed_at'   => now()->toDateString(),
            ]);
        }

        // Build PAT URL for push
        try {
            $patUrl = $this->settingsService->buildPatUrl(basename($projectPath));
        } catch (\RuntimeException $e) {
            return new GitResult(false, '', $e->getMessage(), 1);
        }

        $push = $this->gitService->push($projectPath, $patUrl);

        if ($push->success) {
            GitActivityLog::create([
                'project_name'  => $projectName,
                'activity_type' => 'push',
                'executed_at'   => now()->toDateString(),
            ]);
        }

        return $push;
    }

    /**
     * Remove any GitHub PAT tokens from output before sending to browser.
     * Replaces https://<TOKEN>@github.com with https://github.com
     */
    private function scrubSensitive(string $text): string
    {
        return preg_replace('#https://[^@\s]+@github\.com#', 'https://github.com', $text ?? '');
    }
}
