<x-admin::layouts.anonymous>
    @php
        $safeUrl = auth()->guard('user')->check()
            ? app(\Webkul\Admin\Services\AclLandingPageService::class)->getUrl()
            : null;

        $safeUrl ??= route('admin.session.create');
    @endphp

    <!-- Page Title -->
    <x-slot:title>
        @lang("admin::app.errors.{$errorCode}.title")
    </x-slot>

    <!-- Error page Information -->
	<div class="flex h-[100vh] items-center justify-center bg-white dark:bg-gray-900">
        <div class="flex max-w-[745px] items-center gap-5">
            <div class="flex w-full flex-col gap-6">
                <img
                    src="{{ asset('images/logo-varbel.png') }}"
                    class="h-auto w-[150px] rounded-md bg-white p-2"
                    alt="Varbel Corps"
                />

				<div class="text-[38px] font-bold text-gray-800 dark:text-white">
                    {{ $errorCode }}
                </div>

                <p class="text-sm text-gray-800 dark:text-white">
                    @lang("admin::app.errors.{$errorCode}.description")
                </p>

                <div class="flex items-center gap-2.5">
                    <a
                        href="{{ $safeUrl }}"
                        class="cursor-pointer text-sm font-semibold text-blue-600 transition-all hover:underline"
                    >
                        @lang('admin::app.errors.go-back')
                    </a>

                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="6" height="7" viewBox="0 0 6 7" fill="none">
                            <circle cx="3" cy="3.5" r="3" fill="#9CA3AF"/>
                        </svg>
                    </span>

                    <a
                        href="{{ $safeUrl }}"
                        class="hover:underlsine text-sm font-semibold text-blue-600 transition-all"
                    >
                        @lang('admin::app.errors.dashboard')
                    </a>
                </div>

                <p class="text-sm text-gray-800 dark:text-white">
                    @lang('admin::app.errors.support', [
                        'link' => 'ask.me@captureitphotobooth.id.com',
                        'email' => 'ask.me@captureitphotobooth.id',
                        'class' => 'font-semibold text-blue-600 transition-all hover:underline',
                    ])
                </p>
            </div>

            <div class="w-full">
                <img src="{{ vite()->asset('images/error.svg') }}" />
            </div>
        </div>
	</div>
</x-admin::layouts.anonymous>
