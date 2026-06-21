{{-- Quick Sync Modal Partial --}}
{{-- Requires: $project (ProjectDTO) --}}

<div x-data="quickSyncModal('{{ urlencode($project->name) }}')">

    <button
        @click="openModal()"
        title="Stage all changes, commit with a message, and push to the remote (git add + commit + push)"
        class="btn-action">
        Sync
    </button>

    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         @keydown.escape.window="open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6" @click.outside="open = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                    Quick Sync — <span class="font-mono text-sm text-gray-600">{{ $project->name }}</span>
                </h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div x-show="error" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700" x-text="error"></div>
            <div x-show="success" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700" x-text="success"></div>
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Changed Files</p>
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 max-h-40 overflow-y-auto">
                    <template x-if="loadingFiles"><p class="text-xs text-gray-400">Loading…</p></template>
                    <template x-if="!loadingFiles && changedFiles.length === 0"><p class="text-xs text-gray-400 italic">No changes detected.</p></template>
                    <template x-if="!loadingFiles && changedFiles.length > 0">
                        <ul class="space-y-0.5">
                            <template x-for="file in changedFiles" :key="file">
                                <li class="text-xs font-mono text-gray-700" x-text="file"></li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>
            <div class="mb-4" x-show="changedFiles.length > 0">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Commit Message <span class="text-red-500">*</span></label>
                <input type="text" x-model="message" placeholder="Describe your changes…" @keydown.enter="submit()"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
            </div>
            <div class="mb-4" x-show="changedFiles.length === 0 && !loadingFiles" x-cloak>
                <p class="text-sm text-gray-600">
                    Working tree is clean. Ready to push your existing unpushed commits directly.
                </p>
            </div>
            <div class="flex justify-end gap-2">
                <button @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">Cancel</button>
                <button @click="submit()" :disabled="loading" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading" x-text="changedFiles.length > 0 ? 'Commit & Push' : 'Push'"></span>
                    <span x-show="loading" x-cloak>Pushing…</span>
                </button>
            </div>
        </div>
    </div>
</div>
