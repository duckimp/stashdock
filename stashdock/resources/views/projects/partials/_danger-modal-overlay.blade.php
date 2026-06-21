{{-- _danger-modal-overlay.blade.php --}}
{{-- Shared modal overlay used by danger-modal.blade.php --}}
{{-- Variables: $actionLabel, $warningText, $requiresId, $idLabel, $idPlaceholder --}}

<div x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
     @keydown.escape.window="cancel()">

    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
         @click.outside="cancel()">

        <!-- Error / Success -->
        <div x-show="error" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700" x-text="error"></div>
        <div x-show="success" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700" x-text="success"></div>

        <!-- Step 1: Warning -->
        <div x-show="step === 1">
            <div class="flex items-start gap-3 mb-4">
                <span class="text-2xl shrink-0">⚠️</span>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 mb-1">
                        {{ $actionLabel }} — Are you sure?
                    </h3>
                    <p class="text-sm text-gray-600">
                        {{ $warningText }}
                    </p>
                </div>
            </div>
            <p class="text-xs text-red-600 font-medium mb-4">
                ⚠️ This operation is irreversible! Proceed only if you are certain.
            </p>
            <div class="flex justify-end gap-2">
                <button
                    @click="cancel()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">
                    Cancel
                </button>
                <button
                    @click="advance()"
                    class="px-4 py-2 text-sm font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded-md hover:bg-orange-100 transition-colors">
                    I Understand →
                </button>
            </div>
        </div>

        <!-- Step 2: Final Confirmation -->
        <div x-show="step === 2">
            <div class="flex items-start gap-3 mb-4">
                <span class="text-2xl shrink-0">🛑</span>
                <div>
                    <h3 class="text-base font-semibold text-red-700 mb-1">
                        Final Confirmation — {{ $actionLabel }}
                    </h3>
                    <p class="text-sm text-gray-600">
                        You are about to execute <strong>{{ $actionLabel }}</strong>.
                        This action <strong>cannot be undone</strong>.
                    </p>
                </div>
            </div>

            @if ($requiresId)
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {{ $idLabel }}
                    </label>
                    <input
                        type="text"
                        x-model="commitId"
                        placeholder="{{ $idPlaceholder }}"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-400 font-mono"
                    >
                    <p class="mt-1 text-xs text-gray-400">
                        Leave blank to reset to HEAD (last commit).
                    </p>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <button
                    @click="cancel()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">
                    Cancel
                </button>
                <button
                    @click="execute()"
                    :disabled="executing"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!executing">Execute {{ $actionLabel }}</span>
                    <span x-show="executing" x-cloak>Executing…</span>
                </button>
            </div>
        </div>

    </div>
</div>
