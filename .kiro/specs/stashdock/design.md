# Design Document: StashDock

## Overview

StashDock is a local web admin application that gives developers a visual control center for managing Git operations across multiple local projects. The application runs on `localhost` via Laravel's built-in server, is gated behind Laravel Breeze authentication, and communicates with local Git repositories by executing shell commands through a dedicated service layer.

The design is intentionally "offline-first" — no external API calls except the Git remote operations that the user explicitly triggers. The database is a single SQLite file, keeping the setup zero-configuration.

---

## Architecture

StashDock follows the standard **Laravel MVC pattern** extended with a dedicated Service layer for all shell command execution. The architecture separates concerns cleanly:

```
Browser (Blade + Alpine.js + Chart.js)
        │  HTTP request
        ▼
  Laravel Router (web.php)
        │
        ▼
  Middleware (auth)
        │
        ▼
  Controller  ──────────────▶  Service Layer (GitService, SettingsService)
        │                              │
        ▼                              ▼
  Eloquent Model              shell_exec / proc_open
        │                              │
        ▼                              ▼
  SQLite Database              Local Git Repositories
```

**Key architectural decisions:**

- **Service layer over fat controllers** — All Git command execution lives in `GitService`. Controllers only coordinate HTTP request/response and call services.
- **No queues for MVP** — Sync Graph scans run synchronously on the `/dashboard/sync-logs` request. For the number of local projects typical in development (10–50), this is acceptable. The endpoint is designed to be replaceable by a queued job later.
- **Cache table, not live queries** — `git_activities_log` is the only source for chart data on the Dashboard. Live `git log` is only called during an explicit Sync Graph operation.
- **PAT never in session or response** — The decrypted PAT is held only in a local PHP variable within the GitService method scope, never serialized or logged.

---

## Components and Interfaces

### Controllers

| Controller | Route | Responsibility |
|---|---|---|
| `DashboardController` | `GET /` and `GET /dashboard` | Load summary widgets and chart data from `git_activities_log`; redirect to `/projects` on first load |
| `DashboardController@syncLogs` | `POST /dashboard/sync-logs` | Run `git log` across all project folders and upsert `git_activities_log` |
| `ProjectController@index` | `GET /projects` | Scan subfolders, run per-project git status, return project table data |
| `ProjectController@gitAction` | `POST /projects/{project}/git` | Dispatcher for all Git actions (init, add, commit, push, fetch, pull, diff, branch ops, stash, reset, clean) |
| `SettingsController@show` | `GET /settings` | Display current settings (PAT field always empty) |
| `SettingsController@update` | `POST /settings` | Validate and save settings; encrypt PAT before persist |
| `CloneController@store` | `POST /clone` | Execute `git clone` with PAT-injected URL |

### Services

#### `GitService`

The central shell execution service. All methods accept an absolute path to a project directory and return a structured result:

```php
interface GitResult {
    bool $success;
    string $output;   // raw stdout
    string $error;    // raw stderr
    int $exitCode;
}
```

**Public methods:**

```php
class GitService
{
    public function isGitRepo(string $path): bool
    public function getStatus(string $path): GitResult          // git status --porcelain
    public function getDiff(string $path): GitResult            // git diff
    public function getBranches(string $path): array            // git branch --list
    public function getActiveBranch(string $path): string       // git branch --show-current
    public function getLog(string $path, int $limit = 5): array // git log --oneline -n $limit
    public function init(string $path): GitResult
    public function addRemote(string $path, string $url): GitResult
    public function add(string $path): GitResult                // git add .
    public function commit(string $path, string $message): GitResult
    public function push(string $path, string $patUrl): GitResult
    public function fetch(string $path): GitResult
    public function pull(string $path): GitResult
    public function switchBranch(string $path, string $branch): GitResult
    public function createBranch(string $path, string $branch): GitResult
    public function stash(string $path): GitResult
    public function stashPop(string $path): GitResult
    public function softReset(string $path): GitResult
    public function hardReset(string $path, string $commitId): GitResult
    public function clean(string $path): GitResult
    public function clone(string $repoUrl, string $destinationPath, string $patUrl): GitResult
    public function syncActivityLog(string $path, string $projectName): void
}
```

Shell commands are executed with `proc_open` (not `shell_exec`) so that stdout and stderr are captured separately and exit codes are inspectable.

#### `SettingsService`

```php
class SettingsService
{
    public function getSettings(): ?SystemSettings
    public function saveSettings(array $data): void   // encrypts PAT before save
    public function buildPatUrl(string $repoName): string  // decrypt + inject PAT into URL
}
```

#### `ScannerService`

```php
class ScannerService
{
    public function scanProjects(string $parentDir): array  // returns array of ProjectDTO
    public function detectFramework(string $path): string  // 'Laravel' | 'React/Node' | 'Unknown'
    public function getExcludedFolders(): array             // reads from config
}
```

### Models

| Model | Table | Notes |
|---|---|---|
| `User` | `users` | Standard Laravel Breeze user |
| `SystemSettings` | `system_settings` | Single row; `github_token` is stored encrypted |
| `GitActivityLog` | `git_activities_log` | Append/upsert log; no soft deletes |

### Data Transfer Objects (DTOs)

```php
// Returned by ScannerService::scanProjects()
class ProjectDTO
{
    public string $name;
    public string $path;
    public bool $isGitRepo;
    public string $framework;      // 'Laravel' | 'React/Node' | 'Unknown'
    public string $activeBranch;
    public string $localStatus;    // 'Clean' | 'Dirty' | 'Not Initialized'
    public string $remoteStatus;   // 'Synced' | 'Need Push' | 'Unknown' | 'Not Initialized'
    public array $branches;
}
```

---

## Data Models

### SQLite Schema

The following tables are created via Laravel migrations.

#### `users` (created by Breeze)

```sql
CREATE TABLE users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    email      TEXT    NOT NULL UNIQUE,
    password   TEXT    NOT NULL,
    remember_token TEXT,
    created_at DATETIME,
    updated_at DATETIME
);
```

#### `system_settings`

```sql
CREATE TABLE system_settings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    github_nickname TEXT,
    github_email    TEXT,
    github_token    TEXT,   -- Crypt::encryptString value
    created_at      DATETIME,
    updated_at      DATETIME
);
```

Single-row table. The application always reads `id = 1` and creates it on first save if absent (`updateOrCreate`).

#### `git_activities_log`

```sql
CREATE TABLE git_activities_log (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    project_name  TEXT    NOT NULL,
    activity_type TEXT    NOT NULL CHECK (activity_type IN ('commit', 'push')),
    executed_at   DATE    NOT NULL,
    created_at    DATETIME,
    updated_at    DATETIME
);

CREATE INDEX idx_activities_date ON git_activities_log (executed_at, activity_type);
```

`executed_at` stores the calendar date (not datetime) of the activity. During Sync Graph, rows are upserted using `(project_name, activity_type, executed_at)` as the natural key.

### Eloquent Models

```php
// app/Models/SystemSettings.php
class SystemSettings extends Model
{
    protected $fillable = ['github_nickname', 'github_email', 'github_token'];
    protected $hidden = ['github_token'];  // never serialized in JSON responses
}

// app/Models/GitActivityLog.php
class GitActivityLog extends Model
{
    protected $fillable = ['project_name', 'activity_type', 'executed_at'];
    protected $casts = ['executed_at' => 'date'];
}
```

---

## Directory Structure

```
stashdock/                          # Laravel project root
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/               # Laravel Breeze controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── SettingsController.php
│   │   │   └── CloneController.php
│   │   └── Middleware/
│   │       └── (standard auth middleware)
│   ├── Models/
│   │   ├── User.php
│   │   ├── SystemSettings.php
│   │   └── GitActivityLog.php
│   ├── Services/
│   │   ├── GitService.php
│   │   ├── SettingsService.php
│   │   └── ScannerService.php
│   └── DTOs/
│       └── ProjectDTO.php
├── config/
│   └── stashdock.php               # parent_dir, excluded_folders
├── database/
│   ├── database.sqlite
│   └── migrations/
│       ├── xxxx_create_users_table.php
│       ├── xxxx_create_system_settings_table.php
│       └── xxxx_create_git_activities_log_table.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php       # main layout with nav
│       ├── auth/                   # Breeze auth views
│       ├── dashboard.blade.php
│       ├── projects/
│       │   ├── index.blade.php     # project table
│       │   └── partials/
│       │       ├── project-row.blade.php
│       │       ├── quick-sync-modal.blade.php
│       │       ├── diff-modal.blade.php
│       │       ├── branch-modal.blade.php
│       │       └── danger-modal.blade.php
│       └── settings/
│           └── index.blade.php
└── routes/
    └── web.php
```

### Configuration: `config/stashdock.php`

```php
return [
    'parent_dir'       => env('STASHDOCK_PARENT_DIR', dirname(base_path())),
    'excluded_folders' => array_filter(array_map('trim',
        explode(',', env('STASHDOCK_EXCLUDED', 'git-dashboard-tools,stashdock'))
    )),
];
```

The excluded folder list is read from `.env` so it can be adjusted without code changes. The default includes both the PRD-specified name (`git-dashboard-tools`) and `stashdock` (the actual app folder name). The parent directory defaults to one level above the Laravel project root, which is the typical deployment layout.

---

## API / Route Design

All routes are protected by `auth` middleware except the Breeze auth routes.

```php
// routes/web.php

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/dashboard/sync-logs', [DashboardController::class, 'syncLogs'])
         ->name('dashboard.sync-logs');

    // Projects
    Route::get('/projects',                       [ProjectController::class, 'index'])
         ->name('projects.index');
    Route::post('/projects/{project}/git',        [ProjectController::class, 'gitAction'])
         ->name('projects.git');

    // Clone
    Route::post('/clone', [CloneController::class, 'store'])->name('clone.store');

    // Settings
    Route::get('/settings',  [SettingsController::class, 'show'])->name('settings.show');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
```

### `POST /projects/{project}/git` — Action Dispatcher

The `{project}` segment is a URL-encoded project folder name. The request body carries an `action` field:

| `action` | Git command | Danger Zone | Body params |
|---|---|---|---|
| `init` | `git init` | No | — |
| `add-remote` | `git remote add origin <url>` | No | `remote_url` |
| `diff` | `git diff` | No | — |
| `fetch` | `git fetch` | No | — |
| `pull` | `git pull` | No | — |
| `quick-sync` | `git add . && commit && push` | No | `message` |
| `switch-branch` | `git switch <branch>` | No | `branch` |
| `create-branch` | `git checkout -b <branch>` | No | `branch` |
| `stash` | `git stash` | No | — |
| `stash-pop` | `git stash pop` | No | — |
| `soft-reset` | `git reset --soft HEAD~1` | No | — |
| `hard-reset` | `git reset --hard <id>` | **Yes** | `commit_id`, `confirm` |
| `clean` | `git clean -fd` | **Yes** | `confirm` |

Danger Zone actions require `confirm = "CONFIRMED"` in the request body. If absent, the controller returns a 422 response without executing the command.

### `POST /dashboard/sync-logs` — Sync Graph

Synchronous endpoint. Iterates all project folders, runs `git log --format="%ad" --date=short` per project, and upserts `git_activities_log` rows. Returns JSON:

```json
{
  "status": "ok",
  "projects_scanned": 12,
  "projects_failed": 1,
  "failed_projects": ["my-broken-repo"]
}
```

---

## Git Command Execution Layer

### Why `proc_open` Instead of `shell_exec`

`shell_exec` merges stdout and stderr and provides no exit code. `proc_open` gives independent stdout/stderr pipes and a reliable exit code, which is essential for error handling and user feedback.

### Execution Pattern

```php
private function run(array $command, string $cwd): GitResult
{
    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd, null, ['bypass_shell' => true]);

    if (!is_resource($process)) {
        return new GitResult(false, '', 'Failed to start process', -1);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return new GitResult($exitCode === 0, $stdout, $stderr, $exitCode);
}
```

The `$command` parameter is **an array** (not a string), which completely prevents shell injection — each argument is passed directly to the OS without going through a shell interpreter.

### PAT Injection Flow

```php
public function buildPatUrl(string $repoName): string
{
    $settings = SystemSettings::first();
    if (!$settings) {
        throw new \RuntimeException('GitHub settings not configured.');
    }
    $token    = Crypt::decryptString($settings->github_token);
    $nickname = $settings->github_nickname;
    // $token is a local variable only — never returned from this method
    return "https://{$token}@github.com/{$nickname}/{$repoName}.git";
}
```

The returned URL string is passed directly into `proc_open` as part of the command array and is never stored, logged, or serialized. After the Git command completes, the variable goes out of scope.

### Command Safety

- Commands are **never constructed via string interpolation**; they are built as PHP arrays.
- The `cwd` parameter to `proc_open` is validated to be an absolute path inside the configured `parent_dir` (path traversal guard).
- Branch names, commit IDs, and commit messages are passed as discrete array elements, not concatenated into a shell string.

```php
// SAFE — argument is a separate array element
['git', 'commit', '-m', $message]

// UNSAFE — never done
"git commit -m {$message}"
```

---

## Security Design

### PAT Encryption / Decryption Flow

```
User submits PAT in settings form
        │
        ▼
SettingsController::update()
        │
        ├─ Validates form input (required, string, max:255)
        │
        ├─ Crypt::encryptString($pat)  ← AES-256-CBC, key = APP_KEY
        │
        └─ SystemSettings::updateOrCreate(['id' => 1], [...])
                  │
                  ▼
             SQLite database
             github_token = "eyJpdiI6Ii..."  (opaque encrypted blob)


When git push / clone is needed:
        │
        ▼
SettingsService::buildPatUrl($repoName)
        │
        ├─ Reads SystemSettings::first()
        │
        ├─ Crypt::decryptString($settings->github_token)
        │         └─ result stored in local $token variable
        │
        ├─ Builds URL: "https://{$token}@github.com/{$nick}/{$repo}.git"
        │
        └─ Passes URL to GitService::push() as an array element
                  │
                  ▼
             proc_open([..., $patUrl], ...)
                  │
                  ▼
             $token and $patUrl go out of scope → GC collected
```

### What is Never Persisted

| Data | Storage | Verdict |
|---|---|---|
| Plain PAT | Memory only (local var) | ✅ Never persisted |
| Encrypted PAT | `system_settings.github_token` | ✅ Encrypted at rest |
| PAT-injected URL | Memory only (array element) | ✅ Never persisted |
| Git command output | Returned to controller, sent to browser | ✅ No PAT in git output |

### Settings Page — PAT Field Policy

The settings form's PAT field always renders with `value=""`. On save, the controller checks whether the PAT field is non-empty before updating the token column. If the user submits with an empty PAT field, the existing encrypted token is preserved unchanged.

### HTTP Headers

The `app.blade.php` layout includes:
```html
<meta name="referrer" content="no-referrer">
```
to prevent PAT-injected URLs leaking via the `Referer` header (though since PAT URLs are only passed to shell commands and never appear in HTML, this is defense in depth).

---

## Chart Data Flow

```
git_activities_log table
        │
        ▼
DashboardController::index()
        │
        ├─ GitActivityLog::selectRaw(
        │      'executed_at, activity_type, COUNT(*) as count'
        │  )
        │  ->whereBetween('executed_at', [$weekStart, $weekEnd])
        │  ->groupBy('executed_at', 'activity_type')
        │  ->get()
        │
        ├─ Pivot: separate commits[] and pushes[] arrays, indexed Mon–Sun
        │
        └─ Pass to Blade view as $chartData JSON
                  │
                  ▼
        dashboard.blade.php
                  │
                  ▼
        <script>
        const chartData = @json($chartData);
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
                datasets: [
                    { label: 'Commits', data: chartData.commits,
                      borderColor: '#22c55e', tension: 0.4 },
                    { label: 'Pushes',  data: chartData.pushes,
                      borderColor: '#3b82f6', tension: 0.4 }
                ]
            }
        });
        </script>
```

### Sync Graph Flow

```
User clicks "Sync Graph"
        │
        ▼
Alpine.js: fetch('/dashboard/sync-logs', { method: 'POST', ... })
        │
        ▼
DashboardController::syncLogs()
        │
        ├─ ScannerService::scanProjects()  ← get all project paths
        │
        ├─ For each project path:
        │   ├─ GitService::getLog($path, limit=365)
        │   │         └─ git log --format="%ad %s" --date=short -n 365
        │   │
        │   ├─ Parse dates from log output
        │   │
        │   └─ GitActivityLog::updateOrCreate(
        │          ['project_name' => $name, 'activity_type' => 'commit', 'executed_at' => $date],
        │          []  ← no additional columns to update
        │      )
        │
        └─ Return JSON summary
                  │
                  ▼
Alpine.js receives response → triggers page reload or chart refresh
```

---

## Frontend Components

### Layout: `layouts/app.blade.php`

- Fixed top navigation bar with logo, nav links (Dashboard, Projects, Settings), and logout button.
- Tailwind `dark:` variant disabled for MVP (monochrome Zen design, light theme only).
- Alpine.js loaded via CDN; Chart.js loaded via CDN.
- CSRF meta tag for AJAX requests.

### Dashboard View: `dashboard.blade.php`

Three summary cards side by side:
```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Total Projects │  │  Synced         │  │  Need Attention │
│       14        │  │       9         │  │       5         │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

Chart area below cards with a "Sync Graph" button (top-right corner) and a "Clone New Project" button.

#### Alpine.js Component: `syncGraph`

```js
Alpine.data('syncGraph', () => ({
    syncing: false,
    async sync() {
        this.syncing = true;
        const res = await fetch('/dashboard/sync-logs', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const data = await res.json();
        this.syncing = false;
        window.location.reload();
    }
}));
```

#### Alpine.js Component: `cloneModal`

Opens a modal with a URL input. Submits via AJAX to `POST /clone`. Displays success or error inline.

### Projects View: `projects/index.blade.php`

Full-width table. Each row contains:
- **Project Name** (folder name)
- **Framework Badge** — colored pill (`bg-red-100 text-red-700` for Laravel, `bg-blue-100 text-blue-700` for React/Node, `bg-gray-100` for Unknown)
- **Active Branch** — text
- **Local Status Badge** — `bg-yellow-100 text-yellow-700` for Dirty, `bg-green-100 text-green-700` for Clean, `bg-gray-100` for Not Initialized
- **Remote Status Badge** — `bg-green-100` for Synced, `bg-orange-100 text-orange-700` for Need Push
- **Action Buttons** — Sync, Diff, Fetch, Pull, Branch (dropdown), Log, Stash, Reset, Clean

Each button has a `title` attribute for native tooltip and an `x-tooltip` attribute for Alpine-powered rich tooltip (plain English description).

#### Alpine.js Component: `quickSyncModal`

```js
Alpine.data('quickSyncModal', (projectName) => ({
    open: false,
    message: '',
    changedFiles: [],
    loading: false,
    error: null,
    async openModal() {
        // fetch changed file list
        this.open = true;
    },
    async submit() {
        if (!this.message.trim()) {
            this.error = 'Commit message cannot be empty.';
            return;
        }
        this.loading = true;
        // POST to /projects/{project}/git with action=quick-sync
    }
}));
```

#### Alpine.js Component: `dangerModal`

Two-step confirmation flow:
1. **Step 1**: Modal opens with warning text and "I Understand" button.
2. **Step 2**: After clicking "I Understand", a second confirmation appears with a red "Execute" button and a "Cancel" button.

```js
Alpine.data('dangerModal', (action, projectName) => ({
    open: false,
    step: 1,
    confirm() {
        if (this.step === 1) { this.step = 2; return; }
        // POST the danger action
    }
}));
```

### Settings View: `settings/index.blade.php`

Simple single-column form. PAT field is `type="password"` with `value=""` always. Form submits to `POST /settings`.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Invalid credentials always produce an error

*For any* combination of email and password that does not match a registered user, the authentication response SHALL display an error message and NOT redirect to any protected page.

**Validates: Requirements 1.3**

---

### Property 2: Protected routes redirect unauthenticated requests

*For any* route in the application that requires authentication, sending a request without a valid session SHALL result in a redirect to the login page.

**Validates: Requirements 1.4**

---

### Property 3: PAT encryption round-trip

*For any* PAT string submitted through the settings form, the value stored in `system_settings.github_token` SHALL differ from the original plaintext, AND decrypting the stored value using `Crypt::decryptString` SHALL return the original PAT string unchanged.

**Validates: Requirements 2.2, 12.1**

---

### Property 4: PAT-injected URL format correctness

*For any* valid repository name and decrypted PAT, the URL produced by `SettingsService::buildPatUrl` SHALL match the pattern `https://<TOKEN>@github.com/<NICKNAME>/<REPO>.git` and SHALL NOT be returned in any HTTP response body or stored in the database.

**Validates: Requirements 2.3, 12.4**

---

### Property 5: PAT never appears in HTTP responses

*For any* HTTP request to any application route, the response body SHALL NOT contain the decrypted PAT string.

**Validates: Requirements 12.3**

---

### Property 6: Excluded folders never appear in scan results

*For any* parent directory scan, the folder names listed in `config('stashdock.excluded_folders')` SHALL NOT appear in the returned project list.

**Validates: Requirements 3.2**

---

### Property 7: Git repository detection

*For any* subfolder in the parent directory, `ScannerService` SHALL classify it as a Git repository if and only if a `.git` directory is present within it.

**Validates: Requirements 3.3**

---

### Property 8: Framework badge detection

*For any* project folder, the Framework Badge SHALL be 'Laravel' if and only if `composer.json` is present, 'React/Node' if and only if `package.json` is present (and `composer.json` is absent), and 'Unknown' if neither file is present.

**Validates: Requirements 3.5**

---

### Property 9: Dashboard widget counts are consistent

*For any* set of project statuses, the sum of "Synced" count and "Need Attention" count displayed in the widgets SHALL equal the total project count widget.

**Validates: Requirements 4.1**

---

### Property 10: Chart data aggregation correctness

*For any* set of records in `git_activities_log`, aggregating by `(executed_at, activity_type)` SHALL produce daily counts where each day's commit count equals the number of 'commit' rows for that date and each day's push count equals the number of 'push' rows for that date.

**Validates: Requirements 4.2, 11.3**

---

### Property 11: Dashboard load does not execute git shell commands

*For any* request to `GET /dashboard` (without Sync Graph), no `proc_open` or `shell_exec` calls targeting project directories SHALL be made.

**Validates: Requirements 4.3, 11.4**

---

### Property 12: Sync Graph partial failure resilience

*For any* set of project folders where a subset causes `git log` to fail, the remaining valid projects SHALL still have their `git_activities_log` records upserted.

**Validates: Requirements 4.5**

---

### Property 13: Local status badge correctness

*For any* project, the Local Status badge SHALL be 'Dirty' if and only if `git status --porcelain` returns non-empty output, and 'Clean' if and only if it returns empty output.

**Validates: Requirements 5.2, 5.3**

---

### Property 14: Remote status badge correctness

*For any* project, the Remote Status badge SHALL be 'Need Push' if and only if `git log @{u}..HEAD --oneline` returns non-empty output.

**Validates: Requirements 5.4, 5.5**

---

### Property 15: Empty commit message is always rejected

*For any* string composed entirely of whitespace characters (including the empty string), submitting it as a Quick Sync commit message SHALL block all Git operations and display a validation error, leaving the task list unchanged.

**Validates: Requirements 6.4**

---

### Property 16: Successful UI actions are logged

*For any* commit or push operation successfully executed through the StashDock UI, a corresponding record SHALL be inserted into `git_activities_log` with the correct `project_name`, `activity_type`, and `executed_at` date.

**Validates: Requirements 11.1**

---

## Error Handling

### Git Command Failures

Every call to `GitService` returns a `GitResult`. Controllers check `$result->success` and return an appropriate JSON response:

```json
// Success
{ "status": "ok", "output": "..." }

// Failure
{ "status": "error", "message": "git push failed: ...", "output": "...", "error": "..." }
```

Frontend Alpine.js components display the `message` field inside the modal on failure without closing it (satisfies Requirement 6.6).

### Settings Not Configured

If `SystemSettings::first()` returns null when a Git push/clone is attempted, `SettingsService` throws a `\RuntimeException`. The controller catches it and returns:
```json
{ "status": "error", "message": "GitHub settings are not configured. Please visit Settings first." }
```

### Path Traversal Guard

`ScannerService` validates that every resolved path is a direct child of `parent_dir`:

```php
$realParent = realpath(config('stashdock.parent_dir'));
$realChild  = realpath($subfolder);

if (strpos($realChild, $realParent . DIRECTORY_SEPARATOR) !== 0) {
    // reject — path is outside parent directory
}
```

### Danger Zone — Missing Confirm Token

If `confirm !== 'CONFIRMED'` for a Danger Zone action, the controller returns HTTP 422:
```json
{ "status": "error", "message": "Action requires explicit confirmation." }
```

---

## Testing Strategy

### Dual Testing Approach

Both unit/example-based tests and property-based tests are used to achieve comprehensive coverage.

**Unit/Example tests** (PHPUnit):
- Authentication flows (login, logout, redirect behavior)
- Settings page rendering (fields present, PAT field empty)
- Route existence and middleware protection
- Specific Git action dispatching

**Property-based tests** (PestPHP with Faker-driven generators):
- All 16 correctness properties defined above
- Each property test runs a minimum of 100 iterations with varied inputs

### Property-Based Testing Library

PestPHP with custom data generators. PestPHP integrates cleanly with Laravel's testing infrastructure. For properties requiring filesystem interaction, tests use temporary directories (via `sys_get_temp_dir()`), real `git init`/`git commit`, and cleanup with `tearDown`.

For properties involving PAT/encryption (Properties 3–5), the test sets a known `APP_KEY`, generates random 40-character strings as PATs, and asserts round-trip and non-exposure invariants.

**Property test configuration:**
- Each property test MUST run a minimum of 100 iterations
- Tag format in test file comments: `Feature: stashdock, Property {N}: {property_text}`

### Unit Testing Balance

- Focus unit tests on: specific Git command parsing (branch list parsing, log parsing), settings form validation, Danger Zone confirm token check, framework badge detection logic.
- Use property tests for: all invariants, status badge correctness, aggregation math, encryption round-trips, scan exclusion.
- Avoid duplicating coverage between unit and property tests.

### Test Coverage Targets

| Layer | Strategy |
|---|---|
| `GitService` | Property tests with real temp git repos |
| `ScannerService` | Property tests with temp directory trees |
| `SettingsService` | Property tests (encrypt/decrypt round-trip, URL format) |
| `DashboardController` | Example tests (no shell on load, sync response shape) |
| `ProjectController` | Example tests per action type |
| `Blade views` | Not tested with PBT; use Browser tests if needed |
