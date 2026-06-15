{{-- Danger Modal Partial --}}
{{-- Requires: $project (ProjectDTO) --}}

{{-- Hard Reset --}}
<div x-data="dangerModal('{{ urlencode($project->name) }}', 'hard-reset')">
    <button @click="openModal()" title="Permanently discard all uncommitted changes and reset to a specific commit (git reset --hard)" class="btn-danger">Hard Reset ⚠</button>
    @include('projects.partials._danger-modal-overlay', ['actionLabel'=>'Hard Reset','warningText'=>'Hard Reset will permanently discard ALL uncommitted changes. Any unsaved work will be lost forever.','requiresId'=>true,'idLabel'=>'Commit ID (leave blank for HEAD)','idPlaceholder'=>'e.g. a1b2c3d or HEAD'])
</div>

{{-- Clean --}}
<div x-data="dangerModal('{{ urlencode($project->name) }}', 'clean')">
    <button @click="openModal()" title="Remove ALL untracked files and directories permanently (git clean -fd)" class="btn-danger">Clean ⚠</button>
    @include('projects.partials._danger-modal-overlay', ['actionLabel'=>'Clean','warningText'=>'Clean will permanently DELETE all untracked files and directories. These cannot be recovered.','requiresId'=>false,'idLabel'=>'','idPlaceholder'=>''])
</div>
