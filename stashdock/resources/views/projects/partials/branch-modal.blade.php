{{-- Branch Modal Partial --}}
{{-- Requires: $project (ProjectDTO) --}}
{{-- Uses data-* attributes to safely pass PHP values to Alpine without JS string escaping issues --}}

<div x-data="branchModal($el)"
     data-project="{{ urlencode($project->name) }}"
     data-active-branch="{{ e($project->activeBranch) }}"
     data-branches="{{ e(json_encode($project->branches)) }}">

    <button @click="open = true"
            title="Switch branches, create a new branch, or view recent commit history"
            class="btn-action">Branch ▾</button>

    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         @keydown.escape.window="open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6" @click.outside="open = false">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-900">Branch —
                    <span class="font-mono text-sm text-gray-600">{{ $project->name }}</span>
                </h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div x-show="error" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700" x-text="error"></div>
            <div x-show="success" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700" x-text="success"></div>
            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Switch Branch</label>
                <select x-model="selectedBranch" @change="switchBranch()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 bg-white">
                    <template x-for="branch in branches" :key="branch">
                        <option :value="branch" :selected="branch === activeBranch" x-text="branch"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-400">Switches to the selected branch (git switch).</p>
            </div>
            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Create New Branch</label>
                <div class="flex gap-2">
                    <input type="text" x-model="newBranchName" placeholder="feature/my-new-branch"
                           @keydown.enter="createBranch()"
                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                    <button @click="createBranch()" :disabled="!newBranchName.trim() || creatingBranch"
                            class="px-3 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!creatingBranch">Create</span>
                        <span x-show="creatingBranch" x-cloak>Creating…</span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400">Creates and switches to a new branch (git checkout -b).</p>
            </div>
            <div class="flex justify-end mt-5">
                <button @click="open = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
