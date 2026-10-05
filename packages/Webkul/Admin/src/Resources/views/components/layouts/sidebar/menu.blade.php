@foreach ($groups as $group)
    <section class="crm-nav-group" aria-label="{{ $group['label'] }}">
        <h2 class="crm-nav-heading">{{ $group['label'] }}</h2>
        <ul class="crm-nav-list">
            @foreach ($group['items'] as $menuItem)
                @php
                    $menuLabel = core()->getConfigData('general.settings.menu.'.$menuItem->getKey()) ?: $menuItem->getName();
                    $hasSubmenu = ! in_array($menuItem->getKey(), ['settings', 'configuration'], true) && $menuItem->haveChildren();
                    $isActive = $menuItem->isActive();
                @endphp
                <li>
                    @if ($hasSubmenu)
                        <details class="crm-nav-disclosure" @if ($isActive) open @endif>
                            <summary class="crm-nav-link {{ $isActive ? 'is-active-parent' : '' }}" title="{{ $menuLabel }}" @click="expandSubmenu($event)">
                                <span class="crm-nav-icon {{ $menuItem->getIcon() ?: 'icon-note' }}" aria-hidden="true"></span>
                                <span class="crm-nav-label">{{ $menuLabel }}</span>
                                <svg class="crm-nav-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" /></svg>
                            </summary>
                            <ul class="crm-nav-children">
                                @foreach ($menuItem->getChildren() as $subMenuItem)
                                    <li>
                                        <a href="{{ $subMenuItem->getUrl() }}" class="crm-nav-child {{ $subMenuItem->isActive() ? 'is-active' : '' }}" @if ($subMenuItem->isActive()) aria-current="page" @endif>
                                            {{ core()->getConfigData('general.settings.menu.'.$subMenuItem->getKey()) ?: $subMenuItem->getName() }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @else
                        <a href="{{ $menuItem->getUrl() }}" class="crm-nav-link {{ $isActive ? 'is-active' : '' }}" title="{{ $menuLabel }}" @if ($isActive) aria-current="page" @endif>
                            <span class="crm-nav-icon {{ $menuItem->getIcon() ?: 'icon-note' }}" aria-hidden="true"></span>
                            <span class="crm-nav-label">{{ $menuLabel }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
