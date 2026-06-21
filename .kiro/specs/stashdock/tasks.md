# Implementation Plan: StashDock

## Overview

Build StashDock from scratch as a Laravel 11/12 application. The implementation follows a bottom-up dependency order: project scaffolding → database layer → service layer → controllers → Blade views → tests. Each task produces working, integrated code that the next task can build upon.

Tech stack: Laravel 11/12, Laravel Breeze, SQLite, Tailwind CSS, Alpine.js, Chart.js, PestPHP.

---

## Tasks

- [x] 1. Scaffold Laravel project and install core dependencies
  - [x] 1.1 Create a new Laravel 11/12 project named `stashdock` using `composer create-project laravel/laravel stashdock`
    - Confirm PHP ≥ 8.2 and Composer are available
    - _Requirements: (all — foundational)_

  - [x] 1.2 Install and configure Laravel Breeze (Blade + Alpine.js stack)
    - Run `composer require laravel/breeze --dev` then `php artisan breeze:install blade`
    - Run `npm install && npm run build` to compile Tailwind CSS assets
    - _Requirements: 1.1_

  - [x] 1.3 Configure SQLite as the sole database driver
    - Set `DB_CONNECTION=sqlite` and `DB_DATABASE=/absolute/path/to/database/database.sqlite` in `.env`
    - Create the empty `database/database.sqlite` file
    - _Requirements: (all — foundational)_

  - [x] 1.4 Create `config/stashdock.php` application configuration file
    - Add `parent_dir` key defaulting to `dirname(base_path())`
    - Add `excluded_folders` key reading from `STASHDOCK_EXCLUDED` env var, defaulting to `git-dashboard-tools,stashdock`
    - _Requirements: 3.1, 3.2_

  - [x] 1.5 Add `STASHDOCK_PARENT_DIR` and `STASHDOCK_EXCLUDED` entries to `.env` and `.env.example`
    - _Requirements: 3.1, 3.2_

- [x] 2. Create database migrations and Eloquent models
  - [x] 2.1 Write migration for `system_settings` table
    - Columns: `id`, `github_nickname` (nullable text), `github_email` (nullable text), `github_token` (nullable text — stores encrypted value), `created_at`, `updated_at`
    - _Requirements: 2.1, 2.2, 12.1_

  - [x] 2.2 Write migration for `git_activities_log` table
    - Columns: `id`, `project_name` (text, not null), `activity_type` (text, not null, CHECK IN ('commit','push')), `executed_at` (date, not null), `created_at`, `updated_at`
    - Add composite index on `(executed_at, activity_type)`
    - _Requirements: 4.2, 11.1, 11.3_

  - [x] 2.3 Run all migrations: `php artisan migrate`
    - Confirm both new tables plus Breeze `users` table are created
    - _Requirements: (all — foundational)_

  - [x] 2.4 Implement `SystemSettings` Eloquent model (`app/Models/SystemSettings.php`)
    - `$fillable`: `github_nickname`, `github_email`, `github_token`
    - `$hidden`: `github_token` (prevent JSON serialization of encrypted token)
    - _Requirements: 2.2, 12.1, 12.3_

  - [x] 2.5 Implement `GitActivityLog` Eloquent model (`app/Models/GitActivityLog.php`)
    - `$fillable`: `project_name`, `activity_type`, `executed_at`
    - `$casts`: `executed_at` → `'date'`
    - _Requirements: 11.1, 11.3_

- [x] 3. Implement the `ProjectDTO` data transfer object
  - [x] 3.1 Create `app/DTOs/ProjectDTO.php` with typed public properties
    - Properties: `string $name`, `string $path`, `bool $isGitRepo`, `string $framework`, `string $activeBranch`, `string $localStatus`, `string $remoteStatus`, `array $branches`
    - Add a constructor that accepts and assigns all properties
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 5.1_

- [ ] 4. Implement `GitService` — core shell execution service
  - [x] 4.1 Create `app/Services/GitService.php` with the private `run(array $command, string $cwd): GitResult` method
    - Use `proc_open` with an array command (not string) and separate stdout/stderr pipes
    - Return a `GitResult` value object with `bool $success`, `string $output`, `string $error`, `int $exitCode`
    - Create `app/Services/GitResult.php` as a simple readonly class/DTO
    - _Requirements: (all Git operations — foundational)_

  - [x] 4.2 Implement `isGitRepo`, `getStatus`, `getDiff`, `getActiveBranch`, and `getBranches` methods
    - `isGitRepo`: check for `.git` directory existence (no shell call needed)
    - `getStatus`: `git status --porcelain`
    - `getDiff`: `git diff`
    - `getActiveBranch`: `git branch --show-current`
    - `getBranches`: `git branch --list`, split output into array
    - _Requirements: 3.3, 5.2, 5.3, 5.4, 5.6, 8.1, 9.1_

  - [x] 4.3 Implement `getLog`, `init`, `addRemote`, `add`, `commit`, and `push` methods
    - `getLog(string $path, int $limit = 5)`: `git log --oneline -n $limit`, return parsed array
    - `init`: `git init`
    - `addRemote`: `git remote add origin <url>`
    - `add`: `git add .`
    - `commit`: `git commit -m <message>` (message as separate array element)
    - `push`: `git push` using PAT-injected URL
    - _Requirements: 6.3, 7.1, 7.2, 8.1, 9.4_

  - [x] 4.4 Implement `fetch`, `pull`, `switchBranch`, `createBranch`, `stash`, `stashPop`, `softReset`, `hardReset`, `clean`, and `clone` methods
    - `fetch`: `git fetch`
    - `pull`: `git pull`
    - `switchBranch`: `git switch <branch>`
    - `createBranch`: `git checkout -b <branch>`
    - `stash`: `git stash`
    - `stashPop`: `git stash pop`
    - `softReset`: `git reset --soft HEAD~1`
    - `hardReset`: `git reset --hard <commitId>`
    - `clean`: `git clean -fd`
    - `clone`: `git clone <patUrl> <destinationPath>`
    - All branch names, commit IDs, messages passed as discrete array elements — never string-interpolated
    - _Requirements: 8.2, 8.3, 9.2, 9.3, 10.1–10.5_

  - [x] 4.5 Implement `syncActivityLog` method in `GitService`
    - Execute `git log --format="%ad" --date=short -n 365` for the project path
    - Parse date lines and upsert `GitActivityLog` records using `(project_name, activity_type, executed_at)` as natural key
    - _Requirements: 4.4, 11.2_

  - [x] 4.6 Add path traversal guard to `GitService`
    - Before any `run()` call, validate the `$cwd` is a real path that begins with `realpath(config('stashdock.parent_dir')) . DIRECTORY_SEPARATOR`
    - Throw `\InvalidArgumentException` if the path is outside the configured parent directory
    - _Requirements: (security — foundational)_

- [ ] 5. Implement `SettingsService`
  - [x] 5.1 Create `app/Services/SettingsService.php` with `getSettings(): ?SystemSettings` and `saveSettings(array $data): void`
    - `saveSettings` MUST call `Crypt::encryptString($data['github_token'])` before persisting; if PAT field is empty, preserve existing encrypted token
    - Use `SystemSettings::updateOrCreate(['id' => 1], [...])` pattern
    - _Requirements: 2.1, 2.2, 2.4, 2.5, 12.1_

  - [ ] 5.2 Implement `buildPatUrl(string $repoName): string` in `SettingsService`
    - Retrieve `SystemSettings::first()`; throw `\RuntimeException` if null
    - Decrypt token with `Crypt::decryptString`; store only in local variable
    - Return `"https://{$token}@github.com/{$nickname}/{$repoName}.git"`
    - Token variable MUST NOT be returned from this method independently or logged
    - _Requirements: 2.3, 12.2, 12.3, 12.4_

- [ ] 6. Implement `ScannerService`
  - [ ] 6.1 Create `app/Services/ScannerService.php` with `getExcludedFolders(): array` and `detectFramework(string $path): string`
    - `getExcludedFolders` reads `config('stashdock.excluded_folders')` and returns the array
    - `detectFramework`: return `'Laravel'` if `composer.json` exists; `'React/Node'` if `package.json` exists and `composer.json` absent; `'Unknown'` otherwise
    - _Requirements: 3.2, 3.5_

  - [ ] 6.2 Implement `scanProjects(string $parentDir): array` returning `ProjectDTO[]`
    - List immediate subdirectories only (not recursive)
    - Exclude folders in `getExcludedFolders()`
    - Apply path traversal guard: `realpath` check against `parent_dir`
    - For each valid subfolder, use `GitService` to populate `ProjectDTO` fields
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [ ] 7. Checkpoint — run `php artisan test` and verify service layer is wired correctly
  - Ensure all migrations pass, models load, and services instantiate without errors
  - Ask the user if any questions arise before proceeding to controllers.

- [ ] 8. Implement routes and controllers
  - [ ] 8.1 Define all authenticated routes in `routes/web.php`
    - `GET /` → `DashboardController@index` (named `dashboard`)
    - `GET /dashboard` → `DashboardController@index`
    - `POST /dashboard/sync-logs` → `DashboardController@syncLogs` (named `dashboard.sync-logs`)
    - `GET /projects` → `ProjectController@index` (named `projects.index`)
    - `POST /projects/{project}/git` → `ProjectController@gitAction` (named `projects.git`)
    - `POST /clone` → `CloneController@store` (named `clone.store`)
    - `GET /settings` → `SettingsController@show` (named `settings.show`)
    - `POST /settings` → `SettingsController@update` (named `settings.update`)
    - Wrap all routes in `Route::middleware('auth')->group(...)`
    - _Requirements: 1.1, 1.4, 2.1_

  - [ ] 8.2 Implement `SettingsController` (`app/Http/Controllers/SettingsController.php`)
    - `show()`: load `SystemSettings::first()` via `SettingsService`; pass nickname and email to view; PAT field value always `''`
    - `update()`: validate (`github_nickname` required string max:255, `github_email` required email max:255, `github_token` nullable string max:255); call `SettingsService::saveSettings()`; redirect back with success flash
    - _Requirements: 2.1, 2.2, 2.4, 2.5_

  - [ ] 8.3 Implement `DashboardController` (`app/Http/Controllers/DashboardController.php`)
    - `index()`: query `GitActivityLog` for current week (Mon–Sun), pivot into `commits[]` and `pushes[]` arrays (7 elements each), query `ScannerService` for project count summary (total / synced / need-attention), pass all as `$chartData` and `$widgets` to `dashboard.blade.php`
    - `syncLogs()`: call `ScannerService::scanProjects()`, iterate, call `GitService::syncActivityLog()` per project catching exceptions for partial failure, return JSON `{ status, projects_scanned, projects_failed, failed_projects }`
    - `index()` MUST NOT call any `proc_open`/`shell_exec` — data comes exclusively from `git_activities_log`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 11.2, 11.3, 11.4_

  - [ ] 8.4 Implement `ProjectController` (`app/Http/Controllers/ProjectController.php`)
    - `index()`: call `ScannerService::scanProjects()`, return project list to `projects/index.blade.php`
    - `gitAction(Request $request, string $project)`: URL-decode `$project`, resolve absolute path, validate path is inside `parent_dir`, dispatch `$request->action` to the matching `GitService` method
    - Danger Zone actions (`hard-reset`, `clean`) MUST check `$request->confirm === 'CONFIRMED'`; return HTTP 422 JSON if missing
    - Log successful `commit` and `push` actions to `git_activities_log` (insert record with correct `project_name`, `activity_type`, `executed_at`)
    - Return JSON `{ status: 'ok'|'error', output, message }` for all actions
    - _Requirements: 5.1–5.6, 6.1–6.6, 7.1–7.3, 8.1–8.3, 9.1–9.4, 10.1–10.7, 11.1_

  - [ ] 8.5 Implement `CloneController` (`app/Http/Controllers/CloneController.php`)
    - `store()`: validate `repo_url` (required, url); call `SettingsService::buildPatUrl()` to build authenticated URL; call `GitService::clone()`; return JSON success/error
    - _Requirements: 4.6, 2.3, 12.2, 12.4_

- [ ] 9. Build Blade views and Alpine.js components
  - [ ] 9.1 Create `resources/views/layouts/app.blade.php` main layout
    - Fixed top navbar with StashDock logo, nav links (Dashboard, Projects, Settings), logout button
    - Include `@vite(['resources/css/app.css', 'resources/js/app.js'])` or CDN fallback for Tailwind, Alpine.js, and Chart.js
    - Add `<meta name="csrf-token">` and `<meta name="referrer" content="no-referrer">`
    - `@yield('content')` or `{{ $slot }}` for page content
    - _Requirements: 1.1_

  - [ ] 9.2 Create `resources/views/dashboard.blade.php`
    - Three summary cards: Total Projects, Synced, Need Attention
    - Canvas element for Chart.js line chart; pass `$chartData` via `@json()` to inline `<script>`
    - Initialize Chart.js with `type: 'line'`, `tension: 0.4`, green commits line, blue pushes line, Mon–Sun X-axis labels
    - "Sync Graph" button wired to Alpine.js `syncGraph` component (POST `/dashboard/sync-logs`, then `window.location.reload()`)
    - "Clone New Project" button wired to Alpine.js `cloneModal` component
    - _Requirements: 4.1, 4.2, 4.6_

  - [ ] 9.3 Create `resources/views/projects/index.blade.php` project table
    - Full-width table with columns: Project Name, Framework Badge, Active Branch, Local Status, Remote Status, Actions
    - Framework badge color coding: Laravel = red pill, React/Node = blue pill, Unknown = gray pill
    - Local Status badge: Dirty = yellow, Clean = green, Not Initialized = gray
    - Remote Status badge: Synced = green, Need Push = orange
    - Each row includes action buttons: Sync, Diff, Fetch, Pull, Branch dropdown, Log, Stash, Soft Reset, Hard Reset [DANGER], Clean [DANGER]
    - Each button has a `title` attribute with plain-English tooltip text
    - "Init" button shown only for non-initialized projects
    - _Requirements: 3.4, 5.1–5.6, 7.1, 8.4, 9.1–9.4, 10.1–10.7_

  - [ ] 9.4 Create `resources/views/projects/partials/quick-sync-modal.blade.php` with Alpine.js `quickSyncModal` component
    - `openModal()`: fetch changed file list via `git status -s` (POST `projects.git` with action `diff` or a dedicated status fetch)
    - Display list of changed files in modal body
    - Commit message `<input>` bound to Alpine `message` data property
    - On submit: validate `message.trim() !== ''`; if empty show error `'Commit message cannot be empty.'`; otherwise POST `action=quick-sync` to `projects.git`
    - On success: close modal, update badges without full page reload (or trigger reload)
    - On error: display Git error output inside modal, keep modal open
    - _Requirements: 6.1–6.6_

  - [ ] 9.5 Create `resources/views/projects/partials/diff-modal.blade.php`
    - Opens on "Diff" button click; fetches `git diff` output via POST; displays raw diff in `<pre>` block with monospace font
    - _Requirements: 8.1_

  - [ ] 9.6 Create `resources/views/projects/partials/branch-modal.blade.php` with Alpine.js `branchModal` component
    - Branch dropdown (select) populated with `$project->branches`; `@change` triggers POST `action=switch-branch`
    - "Create Branch" form with branch name input; submit triggers POST `action=create-branch`
    - "Show Log" section displaying last 5 `git log --oneline` entries; "Show More" link or count selector to fetch more
    - _Requirements: 9.1–9.4_

  - [ ] 9.7 Create `resources/views/projects/partials/danger-modal.blade.php` with Alpine.js `dangerModal` component
    - Two-step confirmation: Step 1 shows warning text + "I Understand" button; Step 2 shows red "Execute" button + "Cancel" button
    - `confirm()` method advances from step 1 → step 2, then POSTs with `confirm=CONFIRMED` on step 2
    - Cancel at any step closes modal and resets to step 1 without executing any operation
    - Used for both `hard-reset` and `clean` actions
    - Educational tooltip text describing irreversible nature
    - _Requirements: 10.4–10.7_

  - [ ] 9.8 Create `resources/views/settings/index.blade.php` settings form
    - Single-column form with fields: GitHub Nickname (text), GitHub Email (email), GitHub PAT (password, always `value=""`)
    - Pre-populate Nickname and Email from `$settings` (if set)
    - Submit to `POST /settings`; display success/error flash messages
    - _Requirements: 2.1, 2.4, 2.5_

- [ ] 10. Checkpoint — run `php artisan serve` and manually verify full request/response cycle
  - Confirm login, dashboard, project table, settings page all load without 500 errors
  - Confirm Breeze auth routes work: login, logout, registration
  - Ask the user if any questions arise before proceeding to tests.

- [ ] 11. Write PestPHP property-based and unit tests
  - [ ] 11.1 Install and configure PestPHP: `composer require pestphp/pest --dev` and `./vendor/bin/pest --init`
    - Configure `Pest.php` to use `RefreshDatabase` trait and set test database to SQLite in-memory (`DB_CONNECTION=sqlite, DB_DATABASE=:memory:`)
    - _Requirements: (all — testing foundational)_

  - [ ]* 11.2 Write property test for Property 1: invalid credentials always produce an error
    - Generate random email/password pairs that are NOT registered user credentials (100+ iterations)
    - Assert response contains auth error message and does NOT redirect to any protected route
    - Tag: `Feature: stashdock, Property 1: invalid credentials always produce an error`
    - **Validates: Requirements 1.3**

  - [ ]* 11.3 Write property test for Property 2: protected routes redirect unauthenticated requests
    - For each protected route (`/`, `/dashboard`, `/projects`, `/settings`, `/clone`, `/dashboard/sync-logs`), send request without session
    - Assert response is a redirect to the login page
    - Tag: `Feature: stashdock, Property 2: protected routes redirect unauthenticated requests`
    - **Validates: Requirements 1.4**

  - [ ]* 11.4 Write property test for Property 3: PAT encryption round-trip
    - Generate 100+ random alphanumeric strings of length 20–60 as fake PATs
    - Call `SettingsService::saveSettings()` with each PAT
    - Assert `system_settings.github_token` differs from the plaintext PAT
    - Assert `Crypt::decryptString($stored)` equals the original PAT
    - Tag: `Feature: stashdock, Property 3: PAT encryption round-trip`
    - **Validates: Requirements 2.2, 12.1**

  - [ ]* 11.5 Write property test for Property 4: PAT-injected URL format correctness
    - Generate random repo names and PAT strings (100+ iterations)
    - Call `SettingsService::buildPatUrl($repoName)` with seeded settings
    - Assert URL matches regex `^https://[^@]+@github\.com/[^/]+/[^/]+\.git$`
    - Assert the returned URL is NOT stored in the database (query `system_settings` and `git_activities_log` after call)
    - Tag: `Feature: stashdock, Property 4: PAT-injected URL format correctness`
    - **Validates: Requirements 2.3, 12.4**

  - [ ]* 11.6 Write property test for Property 5: PAT never appears in HTTP responses
    - Seed settings with a known PAT value
    - Make authenticated HTTP requests to all protected routes
    - Assert no response body contains the decrypted PAT string
    - Tag: `Feature: stashdock, Property 5: PAT never appears in HTTP responses`
    - **Validates: Requirements 12.3**

  - [ ]* 11.7 Write property test for Property 6: excluded folders never appear in scan results
    - Create temp directories with mixed names (some matching excluded list, some not)
    - Call `ScannerService::scanProjects($tempDir)` for each generated set
    - Assert no `ProjectDTO->name` in result matches any entry from `config('stashdock.excluded_folders')`
    - Tag: `Feature: stashdock, Property 6: excluded folders never appear in scan results`
    - **Validates: Requirements 3.2**

  - [ ]* 11.8 Write property test for Property 7: Git repository detection
    - Create temp directories; randomly place or omit a `.git` subdirectory in each (100+ iterations)
    - Call `ScannerService::scanProjects()` and inspect `ProjectDTO->isGitRepo`
    - Assert `isGitRepo === true` iff `.git` directory is present
    - Tag: `Feature: stashdock, Property 7: Git repository detection`
    - **Validates: Requirements 3.3**

  - [ ]* 11.9 Write property test for Property 8: framework badge detection
    - Create temp project directories with four combinations: both files, only `composer.json`, only `package.json`, neither (25+ iterations each)
    - Call `ScannerService::detectFramework($path)` for each
    - Assert `'Laravel'` iff only/both `composer.json`; `'React/Node'` iff only `package.json`; `'Unknown'` iff neither
    - Tag: `Feature: stashdock, Property 8: framework badge detection`
    - **Validates: Requirements 3.5**

  - [ ]* 11.10 Write property test for Property 9: dashboard widget counts are consistent
    - Generate random arrays of `ProjectDTO` with varied `remoteStatus` values (100+ iterations)
    - Feed them into the widget-count calculation logic extracted from `DashboardController`
    - Assert `synced_count + need_attention_count === total_count`
    - Tag: `Feature: stashdock, Property 9: dashboard widget counts are consistent`
    - **Validates: Requirements 4.1**

  - [ ]* 11.11 Write property test for Property 10: chart data aggregation correctness
    - Seed `git_activities_log` with random sets of records across 7-day windows (100+ iterations)
    - Call the aggregation query used in `DashboardController::index()`
    - Assert each day's commit count equals the count of 'commit' rows for that date, and push count equals 'push' row count
    - Tag: `Feature: stashdock, Property 10: chart data aggregation correctness`
    - **Validates: Requirements 4.2, 11.3**

  - [ ]* 11.12 Write property test for Property 11: dashboard load does not execute git shell commands
    - Spy on `GitService` or mock `proc_open` via a test double
    - Make `GET /dashboard` as authenticated user
    - Assert no `proc_open` or `shell_exec` call was made targeting any path under `parent_dir`
    - Tag: `Feature: stashdock, Property 11: dashboard load does not execute git shell commands`
    - **Validates: Requirements 4.3, 11.4**

  - [ ]* 11.13 Write property test for Property 12: Sync Graph partial failure resilience
    - Create a mix of valid temp git repos and invalid (non-repo) directories (100+ iterations with varied ratios)
    - Call `DashboardController::syncLogs()` route
    - Assert all valid projects have upserted `git_activities_log` records
    - Assert the response `status` is still `'ok'` with correct `projects_failed` count
    - Tag: `Feature: stashdock, Property 12: Sync Graph partial failure resilience`
    - **Validates: Requirements 4.5**

  - [ ]* 11.14 Write property test for Property 13: local status badge correctness
    - Use real temp git repos; randomly stage or leave files unstaged (100+ iterations)
    - Call `GitService::getStatus()` and derive local status using the same logic as `ScannerService`
    - Assert `'Dirty'` iff `getStatus()` output is non-empty; `'Clean'` iff empty
    - Tag: `Feature: stashdock, Property 13: local status badge correctness`
    - **Validates: Requirements 5.2, 5.3**

  - [ ]* 11.15 Write property test for Property 14: remote status badge correctness
    - Set up temp git repos with and without unpushed commits (100+ iterations)
    - Derive remote status using `git log @{u}..HEAD --oneline` output
    - Assert `'Need Push'` iff output non-empty; `'Synced'` iff empty
    - Tag: `Feature: stashdock, Property 14: remote status badge correctness`
    - **Validates: Requirements 5.4, 5.5**

  - [ ]* 11.16 Write property test for Property 15: empty commit message is always rejected
    - Generate 100+ strings composed only of whitespace characters (spaces, tabs, newlines, empty string)
    - Submit each via `POST /projects/{project}/git` with `action=quick-sync` and `message=$string`
    - Assert response is an error (HTTP 422 or JSON `status: 'error'`) and no git commands were executed
    - Tag: `Feature: stashdock, Property 15: empty commit message is always rejected`
    - **Validates: Requirements 6.4**

  - [ ]* 11.17 Write property test for Property 16: successful UI actions are logged
    - For each action type that triggers a commit or push, execute the action against a real temp git repo
    - After execution, query `git_activities_log`
    - Assert a record exists with correct `project_name`, `activity_type` ('commit' or 'push'), and `executed_at` = today's date
    - Run 100+ iterations with varied project names and commit messages
    - Tag: `Feature: stashdock, Property 16: successful UI actions are logged`
    - **Validates: Requirements 11.1**

  - [ ]* 11.18 Write unit tests for `GitService` command parsing
    - Test `getBranches()` output parsing (handles `* main`, `  develop`, extra whitespace)
    - Test `getLog()` output parsing (correct number of entries, handles empty repo)
    - Test `getActiveBranch()` on empty repo vs. initialized repo
    - _Requirements: 5.6, 9.1, 9.4_

  - [ ]* 11.19 Write unit tests for `SettingsController` and `SettingsService`
    - Test settings form renders with nickname/email pre-filled and PAT field empty
    - Test validation: all three fields required except PAT (nullable)
    - Test that submitting empty PAT preserves existing encrypted token
    - _Requirements: 2.1, 2.4, 2.5_

  - [ ]* 11.20 Write unit tests for Danger Zone confirm token enforcement
    - POST `hard-reset` and `clean` without `confirm=CONFIRMED` → assert HTTP 422
    - POST with `confirm=CONFIRMED` → assert command is dispatched
    - _Requirements: 10.4, 10.5, 10.6_

  - [ ]* 11.21 Write unit tests for authentication flows
    - Test login with valid credentials redirects to dashboard
    - Test logout invalidates session and redirects to login
    - Test registration creates user and logs in
    - _Requirements: 1.1, 1.2, 1.5_

- [ ] 12. Final checkpoint — run full test suite and confirm all tests pass
  - Run `./vendor/bin/pest --coverage`
  - Ensure all migrations, services, controllers, and property tests execute without errors
  - Ask the user if any questions arise.

---

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP; all 16 property tests are optional sub-tasks.
- Property tests (11.2–11.17) require real temp directories and real `git` CLI available on the host machine.
- Each property test must run a minimum of 100 iterations per the design specification.
- The path traversal guard (Task 4.6) is a security requirement and must be implemented before any controller routes go live.
- `proc_open` command arrays (not strings) are mandatory in `GitService` — never use string interpolation for Git commands.
- The PAT must never appear in any HTTP response body, log file, or database column in plain text.
- Chart.js and Alpine.js can be loaded via CDN in `app.blade.php` for simplicity; Vite compilation is optional for MVP.
- The `system_settings` table uses `updateOrCreate(['id' => 1], ...)` — it is always a single row.

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "1.4", "1.5"] },
    { "id": 2, "tasks": ["2.1", "2.2", "3.1"] },
    { "id": 3, "tasks": ["2.3"] },
    { "id": 4, "tasks": ["2.4", "2.5"] },
    { "id": 5, "tasks": ["4.1"] },
    { "id": 6, "tasks": ["4.2", "4.3", "4.4", "4.5", "4.6", "5.1"] },
    { "id": 7, "tasks": ["5.2", "6.1"] },
    { "id": 8, "tasks": ["6.2"] },
    { "id": 9, "tasks": ["8.1"] },
    { "id": 10, "tasks": ["8.2", "8.3", "8.4", "8.5"] },
    { "id": 11, "tasks": ["9.1"] },
    { "id": 12, "tasks": ["9.2", "9.8"] },
    { "id": 13, "tasks": ["9.3"] },
    { "id": 14, "tasks": ["9.4", "9.5", "9.6", "9.7"] },
    { "id": 15, "tasks": ["11.1"] },
    { "id": 16, "tasks": ["11.2", "11.3", "11.4", "11.5", "11.6", "11.7", "11.8", "11.9", "11.10", "11.11", "11.12", "11.13", "11.14", "11.15", "11.16", "11.17"] },
    { "id": 17, "tasks": ["11.18", "11.19", "11.20", "11.21"] }
  ]
}
```
