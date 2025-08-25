@extends('layouts.app')

@section('page-content')
<div class="card">
    @if(count($monitors))
    <div class="input-row columns-3">
        <div class="card input-cell monitor-status-good-bg">
            <h1><span class="monitor-status-good">Good: {{ $monitors->filter(function ($elem) { return $elem->latest_check_positive == 1; })->count() }}</span></h1>
        </div>
        <div class="card input-cell monitor-status-bad-bg">
            <h1><span class="monitor-status-bad">Bad: {{ $monitors->filter(function ($elem) { return $elem->latest_check_positive == 0; })->count() }}</span></h1>
        </div>
         <div class="card input-cell monitor-status-warning-bg">
            <h1><span class="monitor-status-warning">Updates available: {{ $monitors->map(function ($elem) { return $elem->updates_available; })->sum() }}</span></h1>
        </div>
    </div>

    <div style="margin-top: 32px; width: 100%; text-align: right;">Last monitor scan: @if(!is_null($last_refresh)) {{ $last_refresh->created_at->format('Y-d-m H:i') }} @else - @endif</div>

    <table style="margin-top: 32px;">
        <thead>
            <td>Name<br>(Hostname/IP)</td>
            <td>Username<br>(Auth Method)</td>
            <td>Operating System</td>
            <td>Uptime</td>
            <td>Updates Available</td>
            <td><i class="fa-brands fa-docker color-docker"></i></td>
            <td>IP Addresses</td>
            <td>CPU Load</td>
            <td>Disks Status</td>
            <td></td>
        </thead>
        <tbody>
            @foreach($monitors as $monitor)
            <tr>
                <td {!! $monitor->latest_check_positive == 1 ? 'class="monitor-status-good-bg"' : 'class="monitor-status-bad-bg"' !!}>
                    <div><span class="monitor-status-{{ $monitor->latest_check_positive == 1 ? 'good' : 'bad' }}">{{ $monitor->name }}</span></div>
                    <div><small>({{ $monitor->hostname_ip }})</small></div>
                    <div>{!! $monitor->status() !!}</div>
                </td>
                <td>
                    <div>{{ $monitor->username }}</div>
                    <div><small>{{ $monitor->authMethod() }}</small></div>
                </td>
                <td class="os-line"><div>{!! $monitor->asIcon() !!} {{ $monitor->operating_system_full_version }}</div></td>
                <td {!! $monitor->thresholdUptimeTriggered() ? 'class="monitor-status-warning-bg"' : '' !!}>{{ $monitor->uptime }} days</td>
                <td {!! $monitor->thresholdUpdatesAvailableTriggered() ? 'class="monitor-status-warning-bg"' : '' !!}>{{ $monitor->updates_available }}</td>
                <td>{{ $monitor->docker_daemon_running == 1 ? 'Yes' : 'No' }} @if($monitor->docker_daemon_running == 1) ({{ $monitor->docker_active_containers }}) @endif</td>
                <td>{!! $monitor->ipAddresses() !!}</td>
                <td>{{ $monitor->cpu_load }}</td>
                <td><pre>{{ $monitor->disks_status }}</pre></td>
                <td><button type="button" class="delete" data-action="delete-monitor" data-id-monitor="{{ $monitor->id }}">Delete Monitor</button></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p>There are currently no active monitors. Add one by <span class="brand-color">clicking on the top right button</span>.</p>
    @endif
</div>
@endsection

@section('page-js')
<script>
    $('button[data-action="delete-monitor"]').on('dblclick', function () {
        var idMonitor = $(this).attr('data-id-monitor');

        $.ajax({
            method: 'post',
            url: '/monitors/delete',
            dataType: 'json',
            data: {
                id_monitor: idMonitor
            },
            success: function (data) {
                if(!data.status) {
                    toastr.error("Error while deleting monitor", "Monitor Delete Error");
                    return;
                }

                toastr.success("Monitor deleted successfully!", "Monitor Delete");
                setTimeout(function () {
                    window.location.reload();
                }, 2000);
            },
            error: function (error) {
                console.error(error);
                toastr.error("Error while deleting monitor", "Monitor Delete Error");
            }
        })
    })

    $(document).ready(function () {
        // Auto page reload after 5 minutes for static view content page on monitor dedicated to monitoring
        setTimeout(function () {
            window.location.reload();
        }, 1000 * 60 * 5); // 5 minutes
    });
</script>
@endsection
