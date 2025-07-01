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
            <input type="file" id="monitor-ssh-private-key" name="monitor-ssh-private-key" value="" autocomplete="off" placeholder="Copy-paste your remote machine's SSH private key for remote login for this username"/>
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
        // Removing input field missing visuals before going on
        $('#monitor-name').removeClass('input-field-missing-insertion');
        $('#monitor-hostname-ip').removeClass('input-field-missing-insertion');
        $('#monitor-username').removeClass('input-field-missing-insertion');
        $('#monitor-auth-method').removeClass('input-field-missing-insertion');
        $('#monitor-password').removeClass('input-field-missing-insertion');
        $('#monitor-ssh-private-key').removeClass('input-field-missing-insertion');

        var payload = {
            name: $('#monitor-name').val().trim(),
            hostname_ip: $('#monitor-hostname-ip').val().trim(),
            username: $('#monitor-username').val().trim(),
            auth_method: $('#monitor-auth-method').val().trim(),
            password: $('#monitor-password').val().trim(),
            ssh_private_key: $('#monitor-ssh-private-key')[0].files[0]
        };

        // Validation checkings of current inserted data
        var hasEmptyMissing = false;

        if(payload.name.length == 0) {
            $('#monitor-name').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.hostname_ip.length == 0) {
            $('#monitor-hostname-ip').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.username.length == 0) {
            $('#monitor-username').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }
        
        if(payload.auth_method.length == 0) {
            $('#monitor-auth-method').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.auth_method == 'password' && payload.password.length == 0) {
            $('#monitor-password').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.auth_method == 'ssh_private_key' && payload.ssh_private_key === undefined) {
            $('#monitor-ssh-private-key').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(hasEmptyMissing) {
            toastr.error("Be sure to insert every field before confirming!", "Missing Fields")
            return;
        }

        // Create temporary form data so that I can upload the file if needed
        var formData = new FormData();
        for(var key of Object.keys(payload)) {
            formData.append(key, payload[key]);
        }

        // All good, save to the system!
        var _btnHTML = $('button[data-action="create-new-monitor"]').html();
        $.ajax({
            method: 'post',
            url: '/monitors/new',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                $('button[data-action="create-new-monitor"]').html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            },
            success: function (data) {
                if(!data.status) {
                    toastr.error(`Error while saving new monitor (${data.errorMessage})`, "New Monitor Save");
                }

                toastr.success('New monitor saved successfully!', 'New Monitor Save');
                setTimeout(function () {
                    window.location.href = '/'; // Return to the dashboard to see all monitors
                }, 2000);
            },
            error: function (error) {
                console.error(error);
                toastr.error("Error while saving new monitor", "New Monitor Save");
            },
            complete: function () {
                $('button[data-action="create-new-monitor"]').html(_btnHTML);
            }
        });
    });
</script>
@endsection
