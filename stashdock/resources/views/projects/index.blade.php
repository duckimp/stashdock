<x-app-layout>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Title -->
        <h1 class="text-xl font-semibold text-gray-900 mb-6">Project Manager</h1>

        <!-- Project Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-300 bg-gray-50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Project Name</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Framework</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Branch</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Local Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Remote Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($projects as $project)
                        <tr x-data="projectRow('{{ urlencode($project->name) }}')"
                            class="hover:bg-gray-50 transition-colors align-top border-b border-gray-200">

                            <!-- Project Name -->
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                                {{ $project->name }}
                            </td>

                            <!-- Framework Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($project->framework === 'Laravel')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Laravel</span>
                                @elseif ($project->framework === 'React/Node')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">React/Node</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Unknown</span>
                                @endif
                            </td>

                            <!-- Active Branch -->
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $project->activeBranch ?: '—' }}
                            </td>

                            <!-- Local Status Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($project->localStatus === 'Dirty')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Dirty</span>
                                @elseif ($project->localStatus === 'Clean')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Clean</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Not Initialized</span>
                                @endif
                            </td>

                            <!-- Remote Status Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($project->remoteStatus === 'Synced')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Synced</span>
                                @elseif ($project->remoteStatus === 'Need Push')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Need Push</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">{{ $project->remoteStatus }}</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">

                                    @if (! $project->isGitRepo)
                                        {{-- Init button — only for non-git projects --}}
                                        <button
                                            title="Initialize a new Git repository in this folder"
                                            @click="gitAction('init')"
                                            class="btn-action">
                                            Init
                                        </button>
                                    @else
                                        {{-- Sync --}}
                                        @include('projects.partials.quick-sync-modal', ['project' => $project])

                                        {{-- Diff --}}
                                        @include('projects.partials.diff-modal', ['project' => $project])

                                        {{-- Fetch --}}
                                        <button
                                            title="Download changes from the remote without merging (git fetch)"
                                            @click="gitAction('fetch')"
                                            class="btn-action">
                                            Fetch
                                        </button>

                                        {{-- Pull --}}
                                        <button
                                            title="Download and merge changes from the remote branch (git pull)"
                                            @click="gitAction('pull')"
                                            class="btn-action">
                                            Pull
                                        </button>

                                        {{-- Branch --}}
                                        @include('projects.partials.branch-modal', ['project' => $project])

                                        {{-- Stash --}}
                                        <button
                                            title="Temporarily save uncommitted changes so you can switch branches safely (git stash)"
                                            @click="gitAction('stash')"
                                            class="btn-action">
                                            Stash
                                        </button>

                                        {{-- Pop Stash --}}
                                        <button
                                            title="Restore the most recently stashed changes back to your working directory (git stash pop)"
                                            @click="gitAction('stash-pop')"
                                            class="btn-action">
                                            Pop Stash
                                        </button>

                                        {{-- Soft Reset --}}
                                        <button
                                            title="Undo the last commit but keep the changes staged and ready to re-commit (git reset --soft HEAD~1)"
                                            @click="gitAction('soft-reset')"
                                            class="btn-action">
                                            Soft Reset
                                        </button>

                                        {{-- Danger: Hard Reset + Clean --}}
                                        @include('projects.partials.danger-modal', ['project' => $project])
                                    @endif

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                No projects found. Make sure <code>STASHDOCK_PARENT_DIR</code> is configured correctly.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <!-- Shared button styles -->
    <style>
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: #374151;
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .btn-action:hover {
            color: #111827;
            background-color: #f9fafb;
            border-color: #9ca3af;
            transform: translateY(-0.5px);
            box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-action:active {
            transform: translateY(0);
            background-color: #f3f4f6;
        }
        .btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: #dc2626;
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .btn-danger:hover {
            color: #991b1b;
            background-color: #fee2e2;
            border-color: #fca5a5;
            transform: translateY(-0.5px);
            box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-danger:active {
            transform: translateY(0);
            background-color: #fcd3d3;
        }
    </style>

    <!-- All Alpine.js component definitions — defined ONCE here, never in partials -->
    <script>
        document.addEventListener('alpine:init', () => {

            // ── Shared git request helper ────────────────────────────────
            window.gitActionRequest = async function(projectEncoded, action, extraData = {}) {
                const res = await fetch(`/projects/${projectEncoded}/git`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action, ...extraData })
                });
                return res.json();
            };

            // ── projectRow — simple one-click git actions ────────────────
            Alpine.data('projectRow', (projectEncoded) => ({
                async gitAction(action, extra = {}) {
                    const data = await window.gitActionRequest(projectEncoded, action, extra);
                    if (data.status === 'ok') {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || data.error || 'Unknown error'));
                    }
                }
            }));

            // ── quickSyncModal ───────────────────────────────────────────
            Alpine.data('quickSyncModal', (projectEncoded) => ({
                open: false, message: '', changedFiles: [], loadingFiles: false,
                loading: false, error: null, success: null,

                async openModal() {
                    this.open = true; this.error = null; this.success = null;
                    this.message = ''; this.changedFiles = []; this.loadingFiles = true;
                    try {
                        const data = await window.gitActionRequest(projectEncoded, 'status');
                        if (data.status === 'ok' && data.output) {
                            const lines = data.output.split('\n').filter(l => l.trim() !== '');
                            this.changedFiles = lines.map(l => l.substring(3).trim());
                        }
                    } catch(e) {} finally { this.loadingFiles = false; }
                },

                async submit() {
                    if (this.changedFiles.length > 0 && !this.message.trim()) { this.error = 'Commit message cannot be empty.'; return; }
                    this.loading = true; this.error = null; this.success = null;
                    try {
                        const data = await window.gitActionRequest(projectEncoded, 'quick-sync', { message: this.message });
                        this.loading = false;
                        if (data.status === 'ok') {
                            this.success = 'Repository synced successfully!';
                            this.message = '';
                            setTimeout(() => { this.open = false; window.location.reload(); }, 1200);
                        } else { this.error = data.message || data.error || 'Sync failed.'; }
                    } catch(e) { this.loading = false; this.error = 'Network error. Please try again.'; }
                }
            }));

            // ── diffModal ────────────────────────────────────────────────
            Alpine.data('diffModal', (projectEncoded) => ({
                open: false, loading: false, error: null, diffOutput: null,

                async openModal() {
                    this.open = true; this.loading = true; this.error = null; this.diffOutput = null;
                    try {
                        const data = await window.gitActionRequest(projectEncoded, 'diff');
                        if (data.status === 'ok') { this.diffOutput = data.output || null; }
                        else { this.error = data.message || 'Failed to load diff.'; }
                    } catch(e) { this.error = 'Network error. Please try again.'; }
                    finally { this.loading = false; }
                },

                parseDiff(diff) {
                    if (!diff) return [];
                    return diff.split('\n').map(line => {
                        let type = 'normal';
                        if (line.startsWith('diff --git')) {
                            type = 'file-header';
                        } else if (line.startsWith('+++') || line.startsWith('---') || line.startsWith('index ')) {
                            type = 'meta-header';
                        } else if (line.startsWith('+')) {
                            type = 'added';
                        } else if (line.startsWith('-')) {
                            type = 'deleted';
                        } else if (line.startsWith('@@')) {
                            type = 'range';
                        } else if (line.startsWith('old mode') || line.startsWith('new mode') || line.startsWith('similarity index') || line.startsWith('rename from') || line.startsWith('rename to')) {
                            type = 'mode';
                        }
                        return { text: line, type };
                    });
                }
            }));

            // ── branchModal ──────────────────────────────────────────────
            Alpine.data('branchModal', (el) => {
                // Read safely from data-* attributes — avoids JS string escaping issues
                const projectEncoded = el.dataset.project;
                const activeBranchInit = el.dataset.activeBranch || '';
                let branchesInit = [];
                try { branchesInit = JSON.parse(el.dataset.branches || '[]'); } catch(e) {}

                return {
                    open: false,
                    activeBranch: activeBranchInit,
                    selectedBranch: activeBranchInit,
                    branches: branchesInit,
                    newBranchName: '',
                    creatingBranch: false,
                    error: null,
                    success: null,

                    async switchBranch() {
                        if (this.selectedBranch === this.activeBranch) return;
                        this.error = null; this.success = null;
                        try {
                            const data = await window.gitActionRequest(projectEncoded, 'switch-branch', { branch: this.selectedBranch });
                            if (data.status === 'ok') {
                                this.success = `Switched to branch "${this.selectedBranch}".`;
                                this.activeBranch = this.selectedBranch;
                                setTimeout(() => { this.open = false; window.location.reload(); }, 1000);
                            } else { this.error = data.message || 'Failed to switch branch.'; this.selectedBranch = this.activeBranch; }
                        } catch(e) { this.error = 'Network error.'; this.selectedBranch = this.activeBranch; }
                    },

                    async createBranch() {
                        const name = this.newBranchName.trim();
                        if (!name) return;
                        this.creatingBranch = true; this.error = null; this.success = null;
                        try {
                            const data = await window.gitActionRequest(projectEncoded, 'create-branch', { branch: name });
                            if (data.status === 'ok') {
                                this.success = `Branch "${name}" created and checked out.`;
                                this.branches.push(name); this.activeBranch = name;
                                this.selectedBranch = name; this.newBranchName = '';
                                setTimeout(() => { this.open = false; window.location.reload(); }, 1000);
                            } else { this.error = data.message || 'Failed to create branch.'; }
                        } catch(e) { this.error = 'Network error.'; }
                        finally { this.creatingBranch = false; }
                    }
                };
            });

            // ── dangerModal ──────────────────────────────────────────────
            Alpine.data('dangerModal', (projectEncoded, action) => ({
                open: false, step: 1, commitId: '', executing: false, error: null, success: null,

                openModal() { this.open = true; this.step = 1; this.commitId = ''; this.error = null; this.success = null; },
                cancel()    { this.open = false; this.step = 1; this.commitId = ''; this.error = null; this.success = null; },
                advance()   { this.step = 2; },

                async execute() {
                    this.executing = true; this.error = null; this.success = null;
                    const extra = { confirm: 'CONFIRMED' };
                    if (action === 'hard-reset') extra.commit_id = this.commitId.trim() || 'HEAD';
                    try {
                        const data = await window.gitActionRequest(projectEncoded, action, extra);
                        if (data.status === 'ok') {
                            this.success = `${action === 'hard-reset' ? 'Hard Reset' : 'Clean'} completed.`;
                            setTimeout(() => { this.open = false; window.location.reload(); }, 1200);
                        } else { this.error = data.message || 'Operation failed.'; }
                    } catch(e) { this.error = 'Network error. Please try again.'; }
                    finally { this.executing = false; }
                }
            }));

        }); // end alpine:init
    </script>
</x-app-layout>