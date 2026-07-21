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
            <div class="monitor-panel-heading">
                <h2>Monitors</h2>
                @if($labels->isNotEmpty())
                    <div class="label-filters" aria-label="Filter monitors by label">
                        @foreach($labels as $label)
                            <button
                                type="button"
                                class="label-filter"
                                data-label-filter="{{ $label->id }}"
                                aria-pressed="false"
                                style="--label-color: {{ $label->color() }}; --label-text-color: {{ $label->textColor() }}"
                            ><i class="fas fa-tag"></i>{{ $label->name }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
            <span class="count-badge" data-monitor-count data-total="{{ $monitors->count() }}">{{ $monitors->count() }} {{ Str::plural('monitor', $monitors->count()) }}</span>
        </div>

        @if($monitors->isNotEmpty())
            <div class="table-wrap">
                <table class="monitor-table">
                    <thead>
                        <tr>
                            <th aria-sort="ascending"><button type="button" class="sort-button" data-sort-key="name" data-sort-type="text">Monitor<i class="fas fa-arrow-up sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="status" data-sort-type="text">Status<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="os" data-sort-type="text">Operating system<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="updates" data-sort-type="number">Updates<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="uptime" data-sort-type="number">Uptime<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="load" data-sort-type="number">Load<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="sort-button" data-sort-key="docker" data-sort-type="number">Docker<i class="fas fa-sort sort-icon" aria-hidden="true"></i></button></th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monitors as $monitor)
                            <tr
                                class="monitor-row"
                                data-label-ids="{{ $monitor->labels->pluck('id')->implode(',') }}"
                                data-sort-name="{{ Str::lower($monitor->name) }}"
                                data-sort-status="{{ $monitor->latest_check_positive ? 'healthy' : 'unreachable' }}"
                                data-sort-os="{{ Str::lower($monitor->operating_system_full_version ?: 'unknown') }}"
                                data-sort-updates="{{ $monitor->updates_available ?? '' }}"
                                data-sort-uptime="{{ $monitor->uptime ?? '' }}"
                                data-sort-load="{{ $monitor->cpu_load !== null ? (float) $monitor->cpu_load : '' }}"
                                data-sort-docker="{{ $monitor->docker_daemon_running == 1 ? ($monitor->docker_active_containers ?? 0) : '' }}"
                            >
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
                                            <div class="monitor-labels" aria-label="Labels for {{ $monitor->name }}">
                                                @foreach($monitor->labels as $label)
                                                    <span class="monitor-label" style="--label-color: {{ $label->color() }}; --label-text-color: {{ $label->textColor() }}">
                                                        <span class="label-name">{{ $label->name }}</span>
                                                        <button
                                                            type="button"
                                                            data-action="remove-monitor-label"
                                                            data-id-monitor="{{ $monitor->id }}"
                                                            data-id-label="{{ $label->id }}"
                                                            data-label-name="{{ $label->name }}"
                                                            title="Remove {{ $label->name }} label"
                                                            aria-label="Remove {{ $label->name }} label from {{ $monitor->name }}"
                                                        ><i class="fas fa-xmark"></i></button>
                                                    </span>
                                                @endforeach
                                                <button
                                                    type="button"
                                                    class="add-label-button"
                                                    data-action="add-monitor-label"
                                                    data-id-monitor="{{ $monitor->id }}"
                                                    data-monitor-name="{{ $monitor->name }}"
                                                    title="Add label"
                                                    aria-label="Add label to {{ $monitor->name }}"
                                                ><i class="fas fa-tag"></i><i class="fas fa-plus"></i></button>
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
                        <tr class="filtered-empty-row" hidden>
                            <td colspan="8">
                                <div class="filtered-empty-state"><i class="fas fa-filter-circle-xmark"></i>No monitors match the selected labels.</div>
                            </td>
                        </tr>
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

<dialog class="label-dialog" id="label-dialog">
    <form id="label-form">
        <div class="label-dialog-icon"><i class="fas fa-tag"></i></div>
        <div>
            <h2>Add label</h2>
            <p>Add a label to <strong data-label-monitor-name></strong>.</p>
        </div>
        <label for="label-name">Label name</label>
        <input type="text" id="label-name" name="label" maxlength="50" placeholder="For example: Production" autocomplete="off" required>
        <div class="label-dialog-actions">
            <button type="button" class="button ghost" data-action="cancel-label">Cancel</button>
            <button type="submit" class="button primary"><i class="fas fa-plus"></i>Add label</button>
        </div>
    </form>
</dialog>
@endsection

@section('page-js')
<script>
    var selectedLabelFilters = new Set();

    function sortMonitors(sortKey, sortType, direction) {
        var tbody = $('.monitor-table tbody');
        var emptyRow = tbody.children('.filtered-empty-row').detach();
        var monitorGroups = tbody.children('.monitor-row').map(function (index, row) {
            var monitorRow = $(row);

            return {
                row: row,
                detailsRow: document.getElementById('monitor-details-' + monitorRow.find('[data-id-monitor]').first().attr('data-id-monitor')),
                value: monitorRow.attr('data-sort-' + sortKey),
                index: index
            };
        }).get();

        monitorGroups.sort(function (left, right) {
            var leftMissing = left.value === '' || left.value === undefined;
            var rightMissing = right.value === '' || right.value === undefined;

            if (leftMissing !== rightMissing) {
                return leftMissing ? 1 : -1;
            }

            var comparison = sortType === 'number'
                ? Number(left.value) - Number(right.value)
                : String(left.value).localeCompare(String(right.value), undefined, { numeric: true, sensitivity: 'base' });

            return comparison === 0 ? left.index - right.index : comparison * (direction === 'ascending' ? 1 : -1);
        });

        monitorGroups.forEach(function (group) {
            tbody.append(group.row);
            tbody.append(group.detailsRow);
        });
        tbody.append(emptyRow);
    }

    $('.sort-button').on('click', function () {
        var button = $(this);
        var header = button.closest('th');
        var currentDirection = header.attr('aria-sort');
        var direction = currentDirection === 'ascending' ? 'descending' : 'ascending';

        $('.monitor-table th[aria-sort]').removeAttr('aria-sort');
        $('.sort-button .sort-icon').attr('class', 'fas fa-sort sort-icon');
        header.attr('aria-sort', direction);
        button.find('.sort-icon').attr('class', 'fas fa-arrow-' + (direction === 'ascending' ? 'up' : 'down') + ' sort-icon');

        sortMonitors(button.attr('data-sort-key'), button.attr('data-sort-type'), direction);
    });

    function applyLabelFilters() {
        var visibleCount = 0;

        $('.monitor-row').each(function () {
            var row = $(this);
            var rowLabelIds = (row.attr('data-label-ids') || '').split(',').filter(Boolean);
            var isVisible = selectedLabelFilters.size === 0
                || rowLabelIds.some(function (labelId) { return selectedLabelFilters.has(labelId); });
            var detailsRow = $('#monitor-details-' + row.find('[data-id-monitor]').first().attr('data-id-monitor'));

            row.toggle(isVisible);
            detailsRow.toggle(isVisible && !detailsRow.prop('hidden'));

            if (isVisible) {
                visibleCount++;
            }
        });

        var totalCount = Number($('[data-monitor-count]').attr('data-total'));
        var countText = selectedLabelFilters.size === 0
            ? totalCount + ' ' + (totalCount === 1 ? 'monitor' : 'monitors')
            : visibleCount + ' of ' + totalCount + ' monitors';

        $('[data-monitor-count]').text(countText);
        $('.filtered-empty-row').prop('hidden', visibleCount !== 0);
    }

    $('button[data-label-filter]').on('click', function () {
        var button = $(this);
        var labelId = button.attr('data-label-filter');

        if (selectedLabelFilters.has(labelId)) {
            selectedLabelFilters.delete(labelId);
            button.removeClass('is-active').attr('aria-pressed', 'false');
        } else {
            selectedLabelFilters.add(labelId);
            button.addClass('is-active').attr('aria-pressed', 'true');
        }

        applyLabelFilters();
    });

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

    $('button[data-action="add-monitor-label"]').on('click', function () {
        var button = $(this);
        var dialog = document.getElementById('label-dialog');

        $('#label-form').attr('data-id-monitor', button.attr('data-id-monitor'));
        $('[data-label-monitor-name]').text(button.attr('data-monitor-name'));
        $('#label-name').val('');
        dialog.showModal();
        setTimeout(function () { $('#label-name').trigger('focus'); }, 0);
    });

    $('button[data-action="cancel-label"]').on('click', function () {
        document.getElementById('label-dialog').close();
    });

    $('#label-dialog').on('click', function (event) {
        if (event.target === this) {
            this.close();
        }
    });

    $('#label-form').on('submit', function (event) {
        event.preventDefault();

        var form = $(this);
        var labelName = $('#label-name').val().trim();
        var submitButton = form.find('button[type="submit"]');

        if (labelName.length === 0) {
            $('#label-name').trigger('focus');
            return;
        }

        submitButton.prop('disabled', true);

        $.ajax({
            method: 'post',
            url: '/monitors/' + form.attr('data-id-monitor') + '/labels',
            dataType: 'json',
            data: { label: labelName },
            success: function () {
                toastr.success('Label added.');
                window.location.reload();
            },
            error: function (xhr) {
                var message = xhr.responseJSON?.errors?.label?.[0] || 'Could not add label.';
                toastr.error(message, 'Label not added');
                submitButton.prop('disabled', false);
            }
        });
    });

    $('button[data-action="remove-monitor-label"]').on('click', function () {
        var button = $(this);

        button.prop('disabled', true);

        $.ajax({
            method: 'delete',
            url: '/monitors/' + button.attr('data-id-monitor') + '/labels/' + button.attr('data-id-label'),
            dataType: 'json',
            success: function () {
                toastr.success('Label removed.');
                window.location.reload();
            },
            error: function () {
                toastr.error('Could not remove label.', 'Label not removed');
                button.prop('disabled', false);
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
