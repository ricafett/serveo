@php
$user = auth()->user();
if (! $user) return;

$navItems = [];

// Home nav item (all authenticated users)
$navItems[] = [
    'route' => 'home',
    'label' => __('dashboard.title'),
    'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>',
    'matches' => ['home'],
];

// Server nav items
if ($user->hasRole('SERVER')) {
    $navItems[] = [
        'route' => 'floor',
        'label' => __('floor.title'),
        'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
        'matches' => ['floor', 'orders.new', 'billing-groups.detail'],
    ];
}

// Cashier nav items
if ($user->hasRole('CASHIER')) {
    $navItems[] = [
        'route' => 'lookup',
        'label' => __('cashier.title'),
        'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>',
        'matches' => ['lookup', 'checkout', 'reprint.group'],
    ];
}

// Admin fallback when no other roles provide operational navigation
if ($user->hasRole('ADMIN') && count($navItems) <= 1) {
    $navItems[] = [
        'route' => 'floor',
        'label' => __('floor.title'),
        'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
        'matches' => ['floor', 'orders.new', 'billing-groups.detail'],
    ];
    $navItems[] = [
        'route' => 'lookup',
        'label' => __('cashier.title'),
        'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>',
        'matches' => ['lookup', 'checkout', 'reprint.group'],
    ];
}

// Resolve active state per item
foreach ($navItems as &$item) {
    $item['active'] = collect($item['matches'])->contains(fn ($m) => request()->routeIs($m));
}
unset($item);
@endphp

@if(count($navItems) > 0)
    {{-- Mobile Bottom Bar --}}
    <nav class="fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 sm:hidden safe-area-pb">
        <div class="flex items-center justify-around">
            @foreach($navItems as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="flex flex-col items-center justify-center py-2 px-3 min-h-[56px] min-w-[64px] text-sm font-medium transition-colors
                        {{ $item['active']
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    {!! $item['icon'] !!}
                    <span class="mt-0.5">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Desktop Side Navigation --}}
    <nav class="hidden sm:flex fixed left-0 top-14 bottom-0 w-16 lg:w-56 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex-col py-4 z-20">
        <div class="flex-1 space-y-1 px-2">
            @foreach($navItems as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="flex items-center gap-3 rounded-lg px-2 py-2.5 text-base font-medium transition-colors min-h-[44px]
                        {{ $item['active']
                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                >
                    {!! $item['icon'] !!}
                    <span class="hidden lg:inline">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        {{-- Desktop-only: App version / footer --}}
        <div class="px-3 py-2 text-base text-gray-400 dark:text-gray-600 text-center hidden lg:block">
            Serveo
        </div>
    </nav>

    {{-- Desktop main content offset --}}
    <style>
        @media (min-width: 640px) {
            main { margin-left: 4rem; }
        }
        @media (min-width: 1024px) {
            main { margin-left: 14rem; }
        }
    </style>
@endif
