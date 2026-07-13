<div class="nav-inner">
    <div id="nav-left">
        <a href="{{ route('dashboard.index') }}" id="logo-link" aria-label="KeepUp dashboard">
            <img src="/images/logo.png" alt="KeepUp"/>
        </a>
        <a href="{{ route('dashboard.index') }}" class="nav-link {{ request()->routeIs('dashboard.*') ? 'is-active' : '' }}">Dashboard</a>
    </div>
    <div id="nav-right">
        <a href="{{ route('monitors.run-ondemand') }}" class="nav-link" aria-label="Request scan" title="Request scan"><i class="fas fa-rotate"></i><span>Request scan</span></a>
        <a href="{{ route('monitors.new') }}" class="button primary" aria-label="Add monitor" title="Add monitor"><i class="fas fa-plus"></i><span>Add monitor</span></a>
        <button id="logout-button" class="icon-button" type="button" title="Logout" aria-label="Logout"><i class="fas fa-arrow-right-from-bracket"></i></button>
    </div>
</div>
