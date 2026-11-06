<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Access Denied</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4">
    <div class="max-w-md w-full text-center">
        <div class="mb-6">
            <h1 class="text-7xl font-bold text-gray-900 dark:text-gray-100">403</h1>
        </div>

        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">
            Access Denied
        </h2>

        <p class="text-gray-600 dark:text-gray-400 mb-8">
            {{ $message ?? "You don't have permission to access this page." }}
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            @auth
                <a
                    href="{{ url('/') }}"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
                >
                    Go to Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
                >
                    Log In
                </a>
            @endauth

            <a
                href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
            >
                Go Back
            </a>
        </div>

        @auth
            <p class="mt-6 text-xs text-gray-500 dark:text-gray-500">
                Signed in as {{ auth()->user()->email }} ({{ auth()->user()->role_label }})
            </p>
        @endauth
    </div>
</body>
</html>