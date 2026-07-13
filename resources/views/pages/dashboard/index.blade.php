@extends('layouts.app')

@section('page-content')
<div class="page-container">
    <header class="page-header">
        <div>
            <h1>Dashboard</h1>
        </div>
        <div class="scan-meta">
            <span class="meta-label">Last scan</span>
            <strong>@if($last_refresh) {{ $last_refresh->created_at->format('M j, Y · H:i') }} @else Not run yet @endif</strong>
        </div>
    </header>

    <section class="summary-grid" aria-label="Monitor summary">
        <article class="summary-card summary-healthy">
            <div class="summary-icon"><i class="fas fa-circle-check"></i></div>
            <div>
                <span>Healthy</span>
                <strong>{{ $stats['healthy'] }}</strong>
            </div>
        </article>
        <article class="summary-card summary-unreachable">
            <div class="summary-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <span>Needs attention</span>
                <strong>{{ $stats['unreachable'] }}</strong>
            </div>
        </article>
        <article class="summary-card summary-updates">
            <div class="summary-icon"><i class="fas fa-box-open"></i></div>
            <div>
                <span>Updates available</span>
                <strong>{{ $stats['updates'] }}</strong>
            </div>
        </article>
        <article class="summary-card summary-total">
            <div class="summary-icon"><i class="fas fa-server"></i></div>
            <div>
                <span>Total monitors</span>
                <strong>{{ $monitors->count() }}</strong>
            </div>
        </article>
    </section>

    <section class="panel monitor-panel">
        <div class="panel-header">
            <div>
                <h2>Monitors</h2>
            </div>
            <span class="count-badge">{{ $monitors->count() }} {{ Str::plural('monitor', $monitors->count()) }}</span>
        </div>

        @if($monitors->isNotEmpty())
            <div class="table-wrap">
                <table class="monitor-table">
                    <thead>
                        <tr>
                            <th>Monitor</th>
                            <th>Status</th>
                            <th>Operating system</th>
                            <th>Updates</th>
                            <th>Uptime</th>
                            <th>Load</th>
                            <th>Docker</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monitors as $monitor)
                            <tr class="monitor-row">
                                <td data-label="Monitor">
                                    <div class="monitor-identity">
                                        <button
                                            type="button"
                                            class="details-toggle"
                                            data-action="toggle-monitor-details"
                                            data-id-monitor="{{ $monitor->id }}"
                                            data-monitor-name="{{ $monitor->name }}"
                                            aria-controls="monitor-details-{{ $monitor->id }}"
                                            aria-expanded="false"
                                            title="System details"
                                            aria-label="Show system details for {{ $monitor->name }}"
                                        ><i class="fas fa-chevron-right"></i></button>
                                        <span class="status-dot {{ $monitor->latest_check_positive ? 'is-healthy' : 'is-unreachable' }}"></span>
                                        <div>
                                            <strong>{{ $monitor->name }}</strong>
                                            <div class="monitor-meta">
                                                <span class="monitor-address">{{ $monitor->username }}&#64;{{ $monitor->hostname_ip }}</span>
                                                <span class="security-hints">
                                                    <span
                                                        class="security-indicator {{ $monitor->hasPublicIp() ? 'has-public-ip' : 'private-only' }}"
                                                        role="img"
                                                        aria-label="{{ $monitor->hasPublicIp() ? 'Public IP detected' : 'No public IP detected' }}"
                                                        title="{{ $monitor->hasPublicIp() ? 'Public IP detected' : 'No public IP detected' }}"
                                                    ><i class="fas fa-globe"></i></span>
                                                    <span
                                                        class="security-indicator {{ $monitor->firewallIsActive() ? 'firewall-active' : 'firewall-inactive' }}"
                                                        role="img"
                                                        aria-label="{{ $monitor->firewallIsActive() ? 'Firewall active' : 'Firewall inactive or unavailable' }}"
                                                        title="{{ $monitor->firewallIsActive() ? 'Firewall active' : 'Firewall inactive or unavailable' }}"
                                                    ><i class="fas fa-shield-halved"></i></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge {{ $monitor->latest_check_positive ? 'healthy' : 'unreachable' }}">
                                        {{ $monitor->latest_check_positive ? 'Healthy' : 'Unreachable' }}
                                    </span>
                                    <small class="last-good">Last good: {{ $monitor->latest_successful_check ? \Carbon\Carbon::parse($monitor->latest_successful_check)->format('M j, H:i') : '—' }}</small>
                                </td>
                                <td data-label="Operating system">
                                    <div class="os-line">{!! $monitor->asIcon() !!}<span>{{ $monitor->operating_system_full_version ?: 'Unknown' }}</span></div>
                                </td>
                                <td data-label="Updates">
                                    <span class="metric-value {{ $monitor->thresholdUpdatesAvailableTriggered() ? 'is-warning' : '' }}">{{ $monitor->updates_available ?? '—' }}</span>
                                </td>
                                <td data-label="Uptime">
                                    <span class="metric-value {{ $monitor->thresholdUptimeTriggered() ? 'is-warning' : '' }}">{{ $monitor->uptime !== null ? $monitor->uptime.' days' : '—' }}</span>
                                </td>
                                <td data-label="CPU load"><span class="metric-value">{{ $monitor->cpu_load ?: '—' }}</span></td>
                                <td data-label="Docker">
                                    @if($monitor->docker_daemon_running == 1)
                                        <span class="docker-status"><i class="fa-brands fa-docker"></i> {{ $monitor->docker_active_containers }} active</span>
                                    @else
                                        <span class="muted">Not running</span>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="monitor-actions">
                                        <button type="button" class="button secondary" data-action="refresh-monitor" data-id-monitor="{{ $monitor->id }}"><i class="fas fa-rotate"></i><span>Refresh</span></button>
                                        <a href="{{ route('monitors.edit', $monitor) }}" class="button icon-button" title="Edit monitor" aria-label="Edit {{ $monitor->name }}"><i class="fas fa-pen"></i></a>
                                        <button type="button" class="icon-button danger" data-action="delete-monitor" data-id-monitor="{{ $monitor->id }}" data-monitor-name="{{ $monitor->name }}" title="Delete monitor" aria-label="Delete {{ $monitor->name }}"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="monitor-details-row" id="monitor-details-{{ $monitor->id }}" hidden>
                                <td colspan="8">
                                    <div class="details-grid">
                                        <section>
                                            <h3>Connection</h3>
                                            <dl>
                                                <div><dt>Authentication</dt><dd>{{ $monitor->authMethod() }}</dd></div>
                                                <div><dt>IP addresses</dt><dd>{!! $monitor->ipAddresses() !!}</dd></div>
                                            </dl>
                                        </section>
                                        <section>
                                            <h3>Firewall</h3>
                                            <div class="technical-output">{!! $monitor->firewallRules() !!}</div>
                                        </section>
                                        <section class="details-wide">
                                            <h3>Disk usage</h3>
                                            <pre class="technical-output">{{ $monitor->disks_status ?: 'No disk information available.' }}</pre>
                                        </section>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-server"></i></div>
                <h2>No monitors yet</h2>
                <p>Add your first server to start tracking health and updates.</p>
                <a href="{{ route('monitors.new') }}" class="button primary"><i class="fas fa-plus"></i> Add monitor</a>
            </div>
        @endif
    </section>
</div>
@endsection

@section('page-js')
<script>
    $('button[data-action="toggle-monitor-details"]').on('click', function () {
        var button = $(this);
        var detailsRow = $('#monitor-details-' + button.attr('data-id-monitor'));
        var isExpanded = button.attr('aria-expanded') === 'true';
        var monitorName = button.attr('data-monitor-name');

        button.attr('aria-expanded', !isExpanded);
        button.attr('aria-label', (isExpanded ? 'Show' : 'Hide') + ' system details for ' + monitorName);
        button.attr('title', isExpanded ? 'System details' : 'Hide system details');
        detailsRow.prop('hidden', isExpanded);
    });

    $('button[data-action="refresh-monitor"]').on('click', function () {
        var idMonitor = $(this).attr('data-id-monitor');
        var button = $(this);

        button.prop('disabled', true).find('span').text('Requesting...');

        $.ajax({
            method: 'post',
            url: '/monitors/' + idMonitor + '/refresh',
            dataType: 'json',
            success: function (data) {
                if(!data.status) {
                    button.prop('disabled', false).find('span').text('Refresh');
                    toastr.error("Could not request monitor refresh", "Refresh failed");
                    return;
                }

                button.find('span').text('Requested');
                toastr.success("Refresh queued.");
            },
            error: function () {
                button.prop('disabled', false).find('span').text('Refresh');
                toastr.error("Could not request monitor refresh", "Refresh failed");
            }
        });
    });

    $('button[data-action="delete-monitor"]').on('click', function () {
        var idMonitor = $(this).attr('data-id-monitor');
        var monitorName = $(this).attr('data-monitor-name');

        if (!window.confirm('Delete "' + monitorName + '"? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            method: 'post',
            url: '/monitors/delete',
            dataType: 'json',
            data: { id_monitor: idMonitor },
            success: function (data) {
                if(!data.status) {
                    toastr.error("Could not delete monitor", "Delete failed");
                    return;
                }

                toastr.success("Monitor deleted.");
                setTimeout(function () { window.location.reload(); }, 2000);
            },
            error: function () {
                toastr.error("Could not delete monitor", "Delete failed");
            }
        });
    });

    $(document).ready(function () {
        setTimeout(function () { window.location.reload(); }, 1000 * 60 * 5);
    });
</script>
@endsection
