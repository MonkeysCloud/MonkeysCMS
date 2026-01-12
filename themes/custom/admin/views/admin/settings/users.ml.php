@extends('layouts.admin')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">User Settings</h1>
                <p class="mt-1 text-sm text-gray-500">Manage global user configuration and session policies.</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">Settings saved successfully.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/settings/users" class="bg-white shadow rounded-lg divide-y divide-gray-200">
            <!-- Session Settings -->
            <div class="p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Session Configuration</h3>
                
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Session Lifetime -->
                    <div class="sm:col-span-4">
                        <x-form.number 
                            name="session_lifetime" 
                            label="Session Lifetime (seconds)"
                            :value="$session_lifetime"
                            min="60"
                            help="How long a user session lasts before expiring (e.g. 7200 = 2 hours)."
                        />
                    </div>

                    <!-- Secure Cookies -->
                    <div class="sm:col-span-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="session_secure" 
                                       name="session_secure" 
                                       type="checkbox" 
                                       <?= $session_secure ? 'checked' : '' ?>
                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="session_secure" class="font-medium text-gray-700">Require Secure Cookies</label>
                                <p class="text-gray-500">Enforce HTTPS-only cookies. Recommended for production environments.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Actions -->
            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 rounded-b-lg">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
@endsection
