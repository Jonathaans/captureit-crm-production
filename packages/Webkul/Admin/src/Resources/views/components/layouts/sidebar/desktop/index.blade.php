@include('admin::components.layouts.sidebar.navigation', [
    'variant' => 'desktop',
    'groups' => app(\Webkul\Admin\Services\SidebarNavigationService::class)->getGroups(),
])
