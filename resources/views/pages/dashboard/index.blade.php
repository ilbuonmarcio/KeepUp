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
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p>There are currently no active monitors. Add one by <span class="brand-color">clicking on the top right button</span>.</p>
    @endif
</div>
@endsection

