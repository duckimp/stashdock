<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settingsService) {}

    public function show()
    {
        $settings = $this->settingsService->getSettings();
        return view('settings.index', [
            'github_nickname' => $settings?->github_nickname ?? '',
            'github_email'    => $settings?->github_email    ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'github_nickname' => 'required|string|max:255',
            'github_email'    => 'required|email|max:255',
            'github_token'    => 'nullable|string|max:255',
        ]);

        $this->settingsService->saveSettings($request->only([
            'github_nickname', 'github_email', 'github_token',
        ]));

        return redirect()->route('settings.show')->with('success', 'Settings saved successfully.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password'      => ['required', 'string'],
            'new_password'          => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();

        // Verify current password is correct
        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->with('tab', 'password');
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('settings.show')
            ->with('success', 'Password changed successfully.');
    }
}
