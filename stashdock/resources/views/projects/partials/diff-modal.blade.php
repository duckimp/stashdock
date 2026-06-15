{{-- Diff Modal Partial --}}
{{-- Requires: $project (ProjectDTO) --}}

<div x-data="diffModal('{{ urlencode($project->name) }}')">
    <button @click="openModal()" title="Show the full diff of all changes in this project (git diff)" class="btn-action">Diff</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @keydown.escape.window="open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl mx-4 flex flex-col" style="max-height: 85vh;" @click.outside="open = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <h3 class="text-base font-semibold text-gray-900">Diff — <span class="font-mono text-sm text-gray-600">{{ $project->name }}</span></h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div class="flex-1 overflow-auto px-6 py-4">
                <template x-if="loading"><p class="text-sm text-gray-400">Loading diff…</p></template>
                <template x-if="error"><p class="text-sm text-red-600" x-text="error"></p></template>
                <template x-if="!loading && !error && diffOutput">
                    <div class="bg-slate-950 border border-slate-800 rounded-xl overflow-hidden shadow-inner font-mono text-xs leading-relaxed max-w-full">
                        <div class="flex flex-col divide-y divide-slate-900/30 overflow-x-auto p-4 whitespace-pre select-text">
                            <template x-for="line in parseDiff(diffOutput)">
                                <div class="px-2 py-0.5"
                                     :class="{
                                         'text-emerald-400 bg-emerald-950/30 border-l-2 border-emerald-500 font-medium': line.type === 'added',
                                         'text-rose-400 bg-rose-950/30 border-l-2 border-rose-500 font-medium': line.type === 'deleted',
                                         'text-sky-400 bg-slate-900/60 font-semibold pt-2 pb-1 first:pt-0 border-l-2 border-sky-500': line.type === 'file-header',
                                         'text-slate-500 bg-slate-900/20 italic': line.type === 'meta-header',
                                         'text-purple-400/90 bg-purple-950/20 font-semibold': line.type === 'range',
                                         'text-amber-400 bg-amber-950/20 italic': line.type === 'mode',
                                         'text-slate-300': line.type === 'normal'
                                     }"
                                     x-text="line.text"></div>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="!loading && !error && !diffOutput">
                    <p class="text-sm text-gray-400 italic">No changes to display. Working tree is clean.</p>
                </template>
            </div>
            <div class="flex justify-end px-6 py-4 border-t border-gray-100 shrink-0">
                <button @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">Close</button>
            </div>
        </div>
    </div>
</div>
