<?php

namespace Webkul\Admin\Services;

class AclLandingPageService
{
    public function __construct(
        protected SidebarNavigationService $navigation,
    ) {}

    /**
     * Return the first page that the authenticated user may access.
     */
    public function getUrl(): ?string
    {
        $user = auth()->guard('user')->user();

        if (! $user?->role) {
            return null;
        }

        return $this->navigation->getItems()->first()?->getUrl();
    }
}
