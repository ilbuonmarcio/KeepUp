<!DOCTYPE html>
<html>
<head>
    <title>KeepUp - Monitors Recap</title>

    <style>
        td {
            border: 0.5px solid #00000022;
            padding: 6px;
        }
    </style>
</head>
<body>
    <h3>Your daily monitors recap:</h3>

    <table style="margin-top: 32px;">
        <thead>
            <td>Name</td>
            <td>Hostname/IP</td>
            <td>Username</td>
            <td>Auth Method</td>
            <td>Operating System</td>
            <td>Uptime</td>
            <td>Updates Available</td>
            <td>IP Addresses</td>
            <td>CPU Load</td>
            <td>Disks Status</td>
            <td>Status</td>
        </thead>
        <tbody>
            @foreach($monitors as $monitor)
            <tr style="margin-bottom: 1px solid #00000033; margin-right: 1px solid #00000039;">
                <td {!! $monitor->latest_check_positive == 1 ? 'style="color: green;"' : 'style="color: red;"' !!}>{{ $monitor->name }}</td>
                <td>{{ $monitor->hostname_ip }}</td>
                <td>{{ $monitor->username }}</td>
                <td>{{ $monitor->authMethod() }}</td>
                <td>{{ $monitor->operating_system }}</td>
                <td {!! $monitor->thresholdUptimeTriggered() ? 'style="background-color: #ffff0022;"' : '' !!}>{{ $monitor->uptime }} days</td>
                <td {!! $monitor->thresholdUpdatesAvailableTriggered() ? 'style="background-color: #ffff0022;"' : '' !!}>{{ $monitor->updates_available }}</td>
                <td>{!! $monitor->ipAddresses() !!}</td>
                <td>{{ $monitor->cpu_load }}</td>
                <td><pre>{{ $monitor->disks_status }}</pre></td>
                <td {!! $monitor->latest_check_positive == 1 ? 'style="color: green;"' : 'style="color: red;"' !!}>{!! $monitor->status() !!}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>