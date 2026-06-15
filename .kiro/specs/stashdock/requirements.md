# Requirements Document

## Introduction

StashDock is a local web admin application (localhost) that serves as an automated control center for developers who manage multiple Git projects on their local machine. The application provides a visual dashboard to monitor Git status across all project subfolders in a parent directory, execute common Git commands through a UI without touching the terminal, and offers interactive educational tooltips for each Git command. It targets developers — especially beginners — who struggle to track uncommitted or unpushed code across dozens of projects.

The application is built with Laravel 11/12, Laravel Breeze for authentication, SQLite as the database, Tailwind CSS + Alpine.js for the frontend, and Chart.js for data visualization.

---

## Glossary

- **Dashboard**: The main landing page after login showing summary widgets and activity charts.
- **Project Manager**: The `/projects` page displaying all scanned local subfolders as a project table.
- **Quick Sync**: A modal action that runs `git add . && git commit -m "<msg>" && git push` for a selected project.
- **PAT (Personal Access Token)**: A GitHub authentication token used instead of a password for Git CLI operations.
- **System_Settings**: The single-row settings table that stores the GitHub nickname, email, and encrypted PAT.
- **Git_Activities_Log**: The SQLite table used as a cache for daily commit and push counts powering the dashboard chart.
- **Sync Graph**: The on-demand operation triggered by the "Sync Graph" button that re-scans git log history and refreshes the Git_Activities_Log cache.
- **Framework Badge**: An auto-detected label (e.g., "Laravel", "React/Node") derived from the presence of `composer.json` or `package.json` in a project folder.
- **Local Status**: A badge indicating whether a project has uncommitted changes (e.g., "Clean", "Dirty").
- **Remote Status**: A badge indicating whether a project has unpushed commits (e.g., "Synced", "Need Push").
- **Danger Zone**: A category of destructive Git operations that require a double-confirmation modal before execution.
- **StashDock_App**: The StashDock Laravel application itself, which must be excluded from its own project scanning.

---

## Requirements

### Requirement 1: User Authentication

**User Story:** As a developer, I want to log in to a locally secured admin panel, so that my projects and GitHub token are protected from unauthorized access on the local machine.

#### Acceptance Criteria

1. THE StashDock_App SHALL provide a login page powered by Laravel Breeze at the application root URL.
2. WHEN a user submits valid credentials, THE StashDock_App SHALL redirect the user to the Dashboard.
3. WHEN a user submits invalid credentials, THE StashDock_App SHALL display an authentication error message and remain on the login page.
4. WHILE a user is not authenticated, THE StashDock_App SHALL redirect all protected routes to the login page and display an explicit error message if the redirect mechanism fails.
5. WHEN a user logs out, THE StashDock_App SHALL invalidate the session and redirect to the login page.

---

### Requirement 2: Global Settings Management

**User Story:** As a developer, I want to configure my GitHub nickname, email, and Personal Access Token once, so that all Git push and clone operations are authenticated automatically.

#### Acceptance Criteria

1. THE StashDock_App SHALL provide a settings page at `/settings` with form fields for GitHub Nickname, GitHub Email, and GitHub PAT.
2. WHEN a user submits the settings form, THE StashDock_App SHALL encrypt the GitHub PAT using Laravel's `Crypt::encryptString` before storing it in the `system_settings` table.
3. WHEN Git push or clone commands are executed, THE StashDock_App SHALL decrypt the stored PAT and inject it into the Git remote URL in the format `https://<DECRYPTED_TOKEN>@github.com/<GITHUB_NICKNAME>/<REPO_NAME>.git`; IF URL injection fails due to network issues or a malformed repository name, THEN THE StashDock_App SHALL fail the Git operation immediately and display an error message to the user.
4. WHEN a user revisits the settings page, THE StashDock_App SHALL display the currently saved GitHub Nickname and GitHub Email (the PAT field SHALL remain empty for security).
5. IF the `system_settings` table has no saved record, THEN THE StashDock_App SHALL display empty form fields on the settings page.

---

### Requirement 3: Project Scanning and Directory Exclusion

**User Story:** As a developer, I want the application to automatically scan all local project subfolders, so that I can see all my projects in one place without manual configuration.

#### Acceptance Criteria

1. WHEN a user visits the Project Manager page, THE StashDock_App SHALL scan all immediate subfolders of the configured parent directory and list them as project rows.
2. THE StashDock_App SHALL exclude the folder named `git-dashboard-tools` (the StashDock application's own folder) from all scanning operations.
3. WHEN scanning subfolders, THE StashDock_App SHALL detect whether each subfolder is a Git repository by checking for the presence of a `.git` directory.
4. WHEN a subfolder is not a Git repository, THE StashDock_App SHALL display it in the project table with a status of "Not Initialized" and show an "Init" button.
5. THE StashDock_App SHALL detect the framework of each project by checking for `composer.json` (Laravel) or `package.json` (React) and display the corresponding Framework Badge; when both files are absent, the badge SHALL display "Unknown".

---

### Requirement 4: Dashboard Summary and Activity Chart

**User Story:** As a developer, I want to see a summary of my projects' Git statuses and a weekly activity chart, so that I can quickly understand my productivity trends without opening each project individually.

#### Acceptance Criteria

1. THE Dashboard SHALL display three summary widgets: total number of scanned projects, number of projects with "Synced" status, and number of projects with "Need Attention" status.
2. THE Dashboard SHALL display a multi-line smooth spline chart using Chart.js (tension: 0.4) with a green line for daily commits and a blue line for daily pushes across the current week (Monday–Sunday on the X-axis, activity count on the Y-axis).
3. WHEN the Dashboard page loads, THE StashDock_App SHALL read chart data exclusively from the `git_activities_log` table and SHALL NOT execute live `git log` commands against project folders.
4. WHEN a user clicks the "Sync Graph" button, THE StashDock_App SHALL execute a background scan via the `/dashboard/sync-logs` route to read actual `git log` history from all project folders and update the `git_activities_log` table; the `git_activities_log` table SHALL only be updated through this user-initiated sync action.
5. IF `git log` commands fail or return no data for some projects during a sync, THEN THE StashDock_App SHALL continue processing remaining projects and mark failed projects with empty or error entries in `git_activities_log`.
6. THE Dashboard SHALL provide a "Clone New Project" button that opens a modal form where the user can enter a Git repository URL to execute `git clone <url>`.

---

### Requirement 5: Project Table and Status Display

**User Story:** As a developer, I want to view all my projects in a table with their Git status at a glance, so that I can immediately identify which projects need attention.

#### Acceptance Criteria

1. THE Project Manager page SHALL display all scanned projects in a table with the following columns: Project Name, Framework Badge, Active Branch, Local Status, and Remote Status.
2. WHEN `git status` detects uncommitted changes in a project, THE StashDock_App SHALL display a "Dirty" badge in the Local Status column; the badge MUST be visible on screen for this requirement to be considered satisfied.
3. WHEN `git status` shows a clean working tree, THE StashDock_App SHALL display a "Clean" badge in the Local Status column.
4. WHEN a project has unpushed commits, THE StashDock_App SHALL display a "Need Push" badge in the Remote Status column.
5. WHEN a project is fully synced with its remote, THE StashDock_App SHALL display a "Synced" badge in the Remote Status column.
6. THE StashDock_App SHALL display the name of the currently active branch in the Active Branch column for each initialized Git repository.

---

### Requirement 6: Quick Sync Modal

**User Story:** As a developer, I want to stage, commit, and push changes from a single modal dialog, so that I can perform my daily sync workflow without switching to the terminal.

#### Acceptance Criteria

1. WHEN a user clicks the "Sync" button for a project, THE StashDock_App SHALL open a Quick Sync modal displaying the list of changed files retrieved via `git status -s`.
2. THE Quick Sync modal SHALL provide a text input field for the commit message.
3. WHEN a user submits the Quick Sync modal with a non-empty commit message, THE StashDock_App SHALL execute `git add .`, `git commit -m "<message>"`, and `git push` sequentially for the selected project.
4. IF a commit message is empty when the user attempts to submit, THEN THE StashDock_App SHALL block all Git operations and display a validation error message inside the modal.
5. WHEN the Quick Sync operation completes successfully, THE StashDock_App SHALL close the modal and refresh the project's Local Status and Remote Status badges.
6. IF the Quick Sync operation fails (e.g., push rejected, network error), THEN THE StashDock_App SHALL display the Git error output inside the modal without closing it.

---

### Requirement 7: Git Setup Commands (Category A)

**User Story:** As a developer, I want to initialize a Git repository and set a remote origin for a project through the UI, so that I can onboard new projects without using the terminal.

#### Acceptance Criteria

1. WHEN a project folder is not yet a Git repository, THE StashDock_App SHALL display a "Init" button that executes `git init` for that folder.
2. WHEN a user provides a remote URL in the remote origin form for a project, THE StashDock_App SHALL execute `git remote add origin <url>` for that project.
3. WHEN `git init` executes successfully, THE StashDock_App SHALL attempt to refresh the project row to reflect its new initialized state; IF the refresh fails, THE StashDock_App SHALL leave the display stale and continue normal operation.

---

### Requirement 8: Daily Workflow Commands (Category B)

**User Story:** As a developer, I want to view file diffs, fetch, and pull changes through the UI, so that I can stay up to date with remote repositories without using the terminal.

#### Acceptance Criteria

1. WHEN a user clicks the "Diff" button for a project, THE StashDock_App SHALL execute `git diff` and display the output in a modal preview.
2. WHEN a user clicks the "Fetch" button for a project, THE StashDock_App SHALL execute `git fetch` for that project and update the Remote Status badge.
3. WHEN a user clicks the "Pull" button for a project, THE StashDock_App SHALL execute `git pull` for that project and refresh the project's Local Status and Active Branch display; THE StashDock_App SHALL only trigger `git pull` execution and UI updates when the user explicitly clicks the Pull button.
4. THE StashDock_App SHALL display an educational tooltip for each Git command button describing what the command does in plain English.

---

### Requirement 9: Branching and History Commands (Category C)

**User Story:** As a developer, I want to view branches, switch between them, create new branches, and see recent commit history through the UI, so that I can manage my branching workflow without the terminal.

#### Acceptance Criteria

1. THE StashDock_App SHALL display a dropdown populated with all local branch names from `git branch` for each initialized project.
2. WHEN a user selects a branch from the dropdown, THE StashDock_App SHALL execute `git switch <branch>` for that project and update the Active Branch display.
3. WHEN a user submits the "Create Branch" modal form with a branch name, THE StashDock_App SHALL execute `git checkout -b <branch>` and update the branch dropdown and Active Branch display.
4. THE StashDock_App SHALL display the last 5 entries from `git log --oneline` in the project detail view, and SHALL provide a "Show More" option or configurable count to view additional entries.
5. THE StashDock_App SHALL display educational tooltips for all branching and history commands.

---

### Requirement 10: Danger Zone Commands (Category D)

**User Story:** As a developer, I want access to stash, reset, and clean operations with mandatory double confirmation, so that I can recover from mistakes safely without accidentally destroying work.

#### Acceptance Criteria

1. THE StashDock_App SHALL provide a "Stash" button that executes `git stash` for the selected project.
2. THE StashDock_App SHALL provide a "Pop Stash" button that executes `git stash pop` for the selected project.
3. THE StashDock_App SHALL provide a "Soft Reset" button that executes `git reset --soft HEAD~1` for the selected project.
4. WHEN a user clicks the "Hard Reset" [DANGER] button, THE StashDock_App SHALL display a double-confirmation modal with a red warning before executing `git reset --hard <commit_id>`.
5. WHEN a user clicks the "Clean" [DANGER] button, THE StashDock_App SHALL display a double-confirmation modal with a red warning before executing `git clean -fd`.
6. IF a user does not confirm the second confirmation step, THEN THE StashDock_App SHALL cancel the operation and close the modal; WHILE the confirmation modal is open, THE StashDock_App SHALL wait indefinitely for user input without auto-cancelling.
7. THE StashDock_App SHALL display educational tooltips for all Danger Zone commands describing the irreversible nature of the operation.

---

### Requirement 11: Activity Log and Graph Sync

**User Story:** As a developer, I want my commit and push activity to be logged and displayed in a chart, so that I can track my productivity over the current week.

#### Acceptance Criteria

1. WHEN a commit or push is successfully executed through the StashDock_App UI, THE StashDock_App SHALL insert a record into `git_activities_log` with the `project_name`, `activity_type` ('commit' or 'push'), and `executed_at` timestamp.
2. WHEN a user clicks "Sync Graph", THE StashDock_App SHALL call the `/dashboard/sync-logs` route, which SHALL execute `git log` scans across all project folders and upsert records into `git_activities_log`.
3. THE StashDock_App SHALL aggregate `git_activities_log` records by day and activity type to produce the data series for the Dashboard chart.
4. WHEN the Dashboard loads without a "Sync Graph" request, THE StashDock_App SHALL NOT execute any `git log` shell commands against project folders.

---

### Requirement 12: Security and Token Protection

**User Story:** As a developer, I want my GitHub Personal Access Token stored securely, so that it cannot be read in plain text if someone accesses the database file.

#### Acceptance Criteria

1. THE StashDock_App SHALL encrypt the GitHub PAT using Laravel's `Crypt::encryptString` before writing it to the `system_settings` table.
2. THE StashDock_App SHALL decrypt the PAT using Laravel's `Crypt::decryptString` only at the moment a Git remote command (push or clone) is about to be executed.
3. THE StashDock_App SHALL never expose the decrypted PAT value in any HTTP response, HTML output, or application log.
4. THE StashDock_App SHALL inject the decrypted PAT into Git remote URLs exclusively in the format `https://<TOKEN>@github.com/<NICKNAME>/<REPO>.git`, SHALL NOT store this injected URL persistently anywhere in the system, and SHALL discard it immediately after the Git command completes.
