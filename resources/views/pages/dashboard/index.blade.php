@extends('layouts.app')

@section('page-content')
<div class="card">
    <h5>Dashboard</h5>

    @if(count($monitors))
    <table>
        <thead>
            <td>Name</td>
            <td>Hostname/IP</td>
            <td>Username</td>
            <td>Auth Method</td>
            <td>Operating System</td>
            <td>Uptime</td>
            <td>Updates Available</td>
            <td>IP Addresses</td>
            <td>Status</td>
            <td></td>
        </thead>
        <tbody>
            @foreach($monitors as $monitor)
            <tr>
                <td>{{ $monitor->name }}</td>
                <td>{{ $monitor->hostname_ip }}</td>
                <td>{{ $monitor->username }}</td>
                <td>{{ $monitor->authMethod() }}</td>
                <td>{{ $monitor->operating_system }}</td>
                <td>{{ $monitor->uptime }}</td>
                <td>{{ $monitor->updates_available }}</td>
                <td>{{ $monitor->ipAddresses() }}</td>
                <td>{!! $monitor->status() !!}</td>
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
</script>
@endsection
