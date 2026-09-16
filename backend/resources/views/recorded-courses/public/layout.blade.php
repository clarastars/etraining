<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'جسارة للتدريب')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        jasarah: {
                            DEFAULT: '#214BE4',
                            dark: '#1839B5',
                            tint: '#eef2ff',
                            page: '#f7f8ff',
                            ink: '#17211d',
                            muted: '#66746f',
                            soft: '#40504a',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: Tajawal, ui-sans-serif, system-ui, sans-serif; }
        .lesson-active { background: #214BE4; color: #fff; }
    </style>
    @stack('head')
</head>
<body class="bg-jasarah-page text-jasarah-ink antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-40 border-b border-jasarah/10 bg-jasarah-page/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-1.5 shadow-lg shadow-jasarah/15 ring-1 ring-jasarah/10">
                        <img src="{{ asset('img/jasarah-logo.png') }}" alt="شعار جسارة" class="h-full w-full object-contain">
                    </span>
                    <span>
                        <span class="block text-lg font-extrabold text-jasarah">جسارة</span>
                        <span class="block text-xs font-medium text-jasarah-muted">للتدريب والاستشارات</span>
                    </span>
                </div>
                @hasSection('header_actions')
                    <div class="flex items-center gap-2">
                        @yield('header_actions')
                    </div>
                @endif
            </div>
        </header>

        <main>
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
