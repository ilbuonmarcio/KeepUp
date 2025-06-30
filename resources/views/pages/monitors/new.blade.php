@extends('layouts.app')

@section('page-content')
<div class="card">
    <h5>New Monitor</h5>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-name">Monitor Name</label>
            <input type="text" id="monitor-name" name="monitor-name" value="" autocomplete="off" placeholder="Insert your machine monitor name that will be used as a label on KeepUp"/>
        </div>
    </div>

    <div class="input-row columns-3">
        <div class="input-cell">
            <label for="monitor-hostname-ip">Hostname/IP</label>
            <input type="text" id="monitor-hostname-ip" name="monitor-hostname-ip" value="" autocomplete="off" placeholder="Insert the remote machine's hostname or ip"/>
        </div>

        <div class="input-cell">
            <label for="monitor-username">Username</label>
            <input type="text" id="monitor-username" name="monitor-username" value="" autocomplete="off" placeholder="Insert the remote machine's username with privileges compatible with KeepUp checking scripts"/>
        </div>

        <div class="input-cell">
            <label for="monitor-auth-method">Auth Method</label>
            <select id="monitor-auth-method" name="monitor-auth-method" autocomplete="off">
                <option value="password" selected>Login via Password</option>
                <option value="ssh_private_key">Login via SSH Private Key</option>
            </select>
        </div>
    </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-password">Password</label>
            <input type="text" id="monitor-password" name="monitor-password" value="" autocomplete="off" placeholder="Insert the remote machine's username related password for remote login"/>
        </div>
    </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-ssh-private-key">SSH Private Key</label>
            <textarea id="monitor-ssh-private-key" rows="12" name="monitor-ssh-private-key" value="" autocomplete="off" placeholder="Copy-paste your remote machine's SSH private key for remote login for this username"></textarea>
        </div>
    </div>

    <div class="input-row confirm-row">
        <button type="button" class="confirm" data-action="create-new-monitor">Confirm</button>
        <button type="button" class="abort" data-action="abort-new-monitor">Abort</button>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function () {
        $('#monitor-ssh-private-key').parent().parent().hide();
    });

    $('#monitor-auth-method').on('change', function () {
        var selectedAuthMethod = $(this).val();

        switch(selectedAuthMethod) {
            case 'password': {
                $('#monitor-ssh-private-key').parent().parent().hide();
                $('#monitor-password').parent().parent().show();
                break;
            }
            case 'ssh_private_key': {
                $('#monitor-password').parent().parent().hide();
                $('#monitor-ssh-private-key').parent().parent().show();
            }
        }
    });

    $('button[data-action="abort-new-monitor"]').on('click', function () {
        window.location.href = '/';
    });

    $('button[data-action="create-new-monitor"]').on('click', function () {
        // TODO: implement!
    });
</script>
@endsection
