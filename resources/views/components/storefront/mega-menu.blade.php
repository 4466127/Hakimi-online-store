{{--
    منوی بزرگ «دسته‌بندی اجناس» (الگو: دیجی‌کالا).
      ستون راست: دسته‌های اصلی — با رفتن موس روی هر کدام، سمت چپ گروه‌ها و زیردسته‌هایش باز می‌شود.
    بی جاوااسکریپت هم کار می‌کند: دکمه به صفحه‌ی همه‌ی دسته‌ها (/categories) می‌رود.
    داده: CategoryMenu::menu() (cache شده؛ همه از پنل مدیریت).
--}}
@props(['menu'])

<div class="relative" x-data="{ open: false, active: {{ (int) ($menu[0]['id'] ?? 0) }} }"
     @mouseleave="open = false" @keydown.escape.window="open = false">
    <a href="{{ route('categories') }}"
       class="inline-flex min-h-11 items-center gap-2 border-b-2 px-1 font-bold text-gray-800"
       :class="open ? 'border-accent-500' : 'border-transparent'"
       @mouseenter="open = true" @click.prevent="open = ! open" :aria-expanded="open" aria-haspopup="true">
        @svg('heroicon-o-bars-3', 'size-5')
        {{ __('store.nav.all_categories') }}
    </a>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="absolute start-0 top-full z-50 flex h-[min(34rem,70vh)] w-[min(68rem,calc(100vw-2rem))] overflow-hidden rounded-b-2xl border border-gray-200 bg-white shadow-xl">
        @if (count($menu) === 0)
            <p class="m-auto text-sm text-gray-500">{{ __('store.menu.empty') }}</p>
        @else
            {{-- دسته‌های اصلی --}}
            <ul class="w-60 shrink-0 overflow-y-auto border-e border-gray-100 bg-gray-50 py-2">
                @foreach ($menu as $root)
                    <li>
                        <a href="{{ route('category.show', ['slug' => $root['slug']]) }}"
                           @mouseenter="active = {{ $root['id'] }}" @focus="active = {{ $root['id'] }}"
                           class="flex min-h-12 items-center gap-3 border-s-2 px-4 text-sm"
                           :class="active === {{ $root['id'] }} ? 'border-accent-500 bg-white font-bold text-brand-700' : 'border-transparent text-gray-700'">
                            @if ($root['icon'])
                                @svg($root['icon'], 'size-5 shrink-0')
                            @endif
                            <span>{{ $root['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- گروه‌ها و زیردسته‌های دسته‌ی انتخاب‌شده --}}
            <div class="min-w-0 flex-1 overflow-y-auto p-6">
                @foreach ($menu as $root)
                    <div x-show="active === {{ $root['id'] }}" @if (! $loop->first) x-cloak @endif>
                        <a href="{{ route('category.show', ['slug' => $root['slug']]) }}"
                           class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                            {{ __('store.menu.all_in', ['name' => $root['name']]) }}
                            @svg('heroicon-m-chevron-left', 'size-4')
                        </a>

                        <div class="mt-5 columns-3 gap-8">
                            @foreach ($root['children'] as $group)
                                <div class="mb-6 break-inside-avoid">
                                    <a href="{{ route('category.show', ['slug' => $group['slug']]) }}"
                                       class="flex items-center gap-1 border-s-2 border-accent-500 ps-2 text-sm font-bold text-gray-900 hover:text-brand-700">
                                        {{ $group['name'] }}
                                        @svg('heroicon-m-chevron-left', 'size-4 text-gray-400')
                                    </a>
                                    @if (count($group['children']) > 0)
                                        <ul class="mt-2 space-y-0.5">
                                            @foreach ($group['children'] as $item)
                                                <li>
                                                    <a href="{{ route('category.show', ['slug' => $item['slug']]) }}"
                                                       class="block py-1.5 text-sm text-gray-600 hover:text-brand-700">{{ $item['name'] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
