<?php

namespace App\Http\Controllers;

use App\Models\GitActivityLog;
use App\Services\ScannerService;
use App\Services\GitService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private ScannerService $scannerService,
        private GitService $gitService,
    ) {}

    public function index()
    {
        // Chart data — read from cache table ONLY, no shell calls
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $rows = GitActivityLog::selectRaw('executed_at, activity_type, COUNT(*) as count')
            ->whereBetween('executed_at', [$weekStart, $weekEnd])
            ->groupBy('executed_at', 'activity_type')
            ->get();

        // Build 7-element arrays indexed Mon(0)…Sun(6)
        $commits = array_fill(0, 7, 0);
        $pushes  = array_fill(0, 7, 0);

        foreach ($rows as $row) {
            $dayIndex = (Carbon::parse($row->executed_at)->dayOfWeekIso - 1); // 0=Mon, 6=Sun
            if ($row->activity_type === 'commit') {
                $commits[$dayIndex] = (int) $row->count;
            } elseif ($row->activity_type === 'push') {
                $pushes[$dayIndex] = (int) $row->count;
            }
        }

        $chartData = ['commits' => $commits, 'pushes' => $pushes];

        // Summary widgets — scan projects (reads filesystem + git status)
        $parentDir = config('stashdock.parent_dir');
        $projects  = $this->scannerService->scanProjects($parentDir);

        $total        = count($projects);
        $synced       = count(array_filter($projects, fn($p) => $p->remoteStatus === 'Synced'));
        $needAttention = $total - $synced;

        return view('dashboard', compact('chartData', 'total', 'synced', 'needAttention'));
    }

    public function syncLogs(): JsonResponse
    {
        $parentDir = config('stashdock.parent_dir');
        $projects  = $this->scannerService->scanProjects($parentDir);

        $scanned = 0;
        $failed  = 0;
        $failedProjects = [];

        foreach ($projects as $project) {
            if (! $project->isGitRepo) {
                continue;
            }
            try {
                $this->gitService->syncActivityLog($project->path, $project->name);
                $scanned++;
            } catch (\Throwable $e) {
                $failed++;
                $failedProjects[] = $project->name;
            }
        }

        return response()->json([
            'status'           => 'ok',
            'projects_scanned' => $scanned,
            'projects_failed'  => $failed,
            'failed_projects'  => $failedProjects,
        ]);
    }
}
