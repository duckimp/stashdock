<x-app-layout>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Title -->
        <h1 class="text-xl font-semibold text-gray-900 mb-6">Settings</h1>

        <!-- Success Flash -->
        @if (session('success'))
            <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                ✓ {{ session('success') }}
            </div>
        @endif

        <!-- ── GitHub Configuration ──────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">GitHub Configuration</h2>
                <p class="text-xs text-gray-400 mt-0.5">Used for Git push and clone operations.</p>
            </div>

            <form method="POST" action="{{ route('settings.update') }}" class="px-6 py-6 space-y-5">
                @csrf

                <!-- GitHub Nickname -->
                <div>
                    <label for="github_nickname" class="block text-sm font-medium text-gray-700 mb-1">
                        GitHub Username
                    </label>
                    <input
                        type="text"
                        id="github_nickname"
                        name="github_nickname"
                        value="{{ old('github_nickname', $github_nickname) }}"
                        placeholder="your-github-username"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 @error('github_nickname') border-red-400 @enderror"
                        autocomplete="off"
                    >
                    @error('github_nickname')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- GitHub Email -->
                <div>
                    <label for="github_email" class="block text-sm font-medium text-gray-700 mb-1">
                        GitHub Email
                    </label>
                    <input
                        type="email"
                        id="github_email"
                        name="github_email"
                        value="{{ old('github_email', $github_email) }}"
                        placeholder="you@example.com"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 @error('github_email') border-red-400 @enderror"
                        autocomplete="off"
                    >
                    @error('github_email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- GitHub PAT -->
                <div>
                    <label for="github_token" class="block text-sm font-medium text-gray-700 mb-1">
                        GitHub Personal Access Token
                    </label>
                    <input
                        type="password"
                        id="github_token"
                        name="github_token"
                        value=""
                        placeholder="ghp_••••••••••••••••••••••••••••••••••••••"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 @error('github_token') border-red-400 @enderror"
                        autocomplete="new-password"
                    >
                    <p class="mt-1 text-xs text-gray-400">
                        Leave blank to keep your existing token. Stored encrypted and never displayed.
                    </p>
                    @error('github_token')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="px-5 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Change Password ──────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Change Password</h2>
                <p class="text-xs text-gray-400 mt-0.5">Update your login password.</p>
            </div>

            <form method="POST" action="{{ route('settings.password') }}" class="px-6 py-6 space-y-5">
                @csrf

                <!-- Current Password -->
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Current Password
                    </label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 @error('current_password') border-red-400 @enderror"
                        autocomplete="current-password"
                        required
                    >
                    @error('current_password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- New Password -->
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                        New Password
                    </label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 @error('new_password') border-red-400 @enderror"
                        autocomplete="new-password"
                        required
                        minlength="8"
                    >
                    <p class="mt-1 text-xs text-gray-400">Minimum 8 characters.</p>
                    @error('new_password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm New Password -->
                <div>
                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm New Password
                    </label>
                    <input
                        type="password"
                        id="new_password_confirmation"
                        name="new_password_confirmation"
                        class="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="px-5 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>
