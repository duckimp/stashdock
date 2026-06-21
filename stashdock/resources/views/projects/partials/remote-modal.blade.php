{{-- Remote Modal Partial --}}
{{-- Requires: $project (ProjectDTO) --}}

<div x-data="remoteModal('{{ urlencode($project->name) }}')">

    <button
        @click="openModal()"
        title="Configure remote repository URL (git remote add/set-url origin)"
        class="btn-action">
        Remote
    </button>

    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         @keydown.escape.window="open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.outside="open = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                    Configure Remote — <span class="font-mono text-sm text-gray-600">{{ $project->name }}</span>
                </h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>

            <!-- Messages -->
            <div x-show="error" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700" x-text="error" x-cloak></div>
            <div x-show="success" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700" x-text="success" x-cloak></div>

            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-3">
                    Connect this local Git repository to a remote repository on GitHub so you can sync and push your changes.
                </p>

                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">GitHub Repository URL</label>
                
                <div class="relative">
                    <input
                        type="url"
                        x-model="remoteUrl"
                        placeholder="https://github.com/username/repository.git"
                        :disabled="loading || saving"
                        @keydown.enter="submit()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 disabled:bg-gray-50"
                    >
                    <div x-show="loading" class="absolute right-3 top-2.5" x-cloak>
                        <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button @click="open = false" :disabled="saving"
                        class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-50">
                    Cancel
                </button>
                <button @click="submit()" :disabled="loading || saving"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!saving">Save URL</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>
