@extends('layouts.app')

@section('page-content')
<div class="page-container page-container-narrow">
    <header class="page-header">
        <div>
            <a href="{{ route('dashboard.index') }}" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h1>Add monitor</h1>
        </div>
    </header>

    <div class="panel form-panel">
        <section class="form-section">
            <div class="section-heading">
                <div>
                    <h2>Connection details</h2>
                </div>
            </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-name">Monitor name</label>
            <input type="text" id="monitor-name" name="monitor-name" value="" autocomplete="off"/>
        </div>
    </div>

    <div class="input-row columns-3">
        <div class="input-cell">
            <label for="monitor-hostname-ip">Hostname or IP</label>
            <input type="text" id="monitor-hostname-ip" name="monitor-hostname-ip" value="" autocomplete="off"/>
        </div>

        <div class="input-cell">
            <label for="monitor-username">Username</label>
            <input type="text" id="monitor-username" name="monitor-username" value="" autocomplete="off"/>
        </div>

        <div class="input-cell">
            <label for="monitor-auth-method">Authentication</label>
            <select id="monitor-auth-method" name="monitor-auth-method" autocomplete="off">
                <option value="password" selected>Password</option>
                <option value="ssh_private_key">SSH private key</option>
            </select>
        </div>
    </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-password">Password</label>
            <input type="password" id="monitor-password" name="monitor-password" value="" autocomplete="new-password"/>
        </div>
    </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-ssh-key-source">Private key source</label>
            <select id="monitor-ssh-key-source" name="monitor-ssh-key-source" autocomplete="off">
                <option value="upload" selected>Upload a new private key</option>
                @foreach($sshKeySources as $keySource)
                    <option value="{{ $keySource->id }}">Reuse key from {{ $keySource->name }} ({{ $keySource->username }}&#64;{{ $keySource->hostname_ip }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="input-row columns-1">
        <div class="input-cell">
            <label for="monitor-ssh-private-key">SSH private key</label>
            <input type="file" id="monitor-ssh-private-key" name="monitor-ssh-private-key" autocomplete="off"/>
            <small>The key is encrypted before it is stored. KeepUp only decrypts it temporarily while connecting.</small>
        </div>
    </div>


        </section>

        <section class="form-section">
            <div class="section-heading">
                <div>
                    <h2>Alert thresholds</h2>
                </div>
            </div>
    <div class="input-row columns-2">
        <div class="input-cell">
            <label for="monitor-threshold-uptime">Uptime threshold</label>
            <input type="number" id="monitor-threshold-uptime" name="monitor-threshold-uptime" value="365" min="0" step="1" autocomplete="off"/>
        </div>

        <div class="input-cell">
            <label for="monitor-threshold-updates-available">Updates threshold</label>
            <input type="number" id="monitor-threshold-updates-available" name="monitor-threshold-updates-available" value="1" min="1" step="1" autocomplete="off"/>
        </div>
    </div>

        </section>

        <div class="form-actions">
            <button type="button" class="button primary" data-action="create-new-monitor"><i class="fas fa-plus"></i><span>Add monitor</span></button>
            <button type="button" class="button ghost" data-action="abort-new-monitor">Cancel</button>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function () {
        $('#monitor-ssh-key-source').parent().parent().hide();
        $('#monitor-ssh-private-key').parent().parent().hide();
    });

    function updateSshKeyInput() {
        var isUpload = $('#monitor-ssh-key-source').val() === 'upload';
        $('#monitor-ssh-private-key').parent().parent().toggle(isUpload);
    }

    $('#monitor-auth-method').on('change', function () {
        var selectedAuthMethod = $(this).val();

        switch(selectedAuthMethod) {
            case 'password': {
                $('#monitor-ssh-key-source').parent().parent().hide();
                $('#monitor-ssh-private-key').parent().parent().hide();
                $('#monitor-password').parent().parent().show();
                break;
            }
            case 'ssh_private_key': {
                $('#monitor-password').parent().parent().hide();
                $('#monitor-ssh-key-source').parent().parent().show();
                updateSshKeyInput();
            }
        }
    });

    $('#monitor-ssh-key-source').on('change', updateSshKeyInput);

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
        $('#monitor-ssh-key-source').removeClass('input-field-missing-insertion');
        $('#monitor-ssh-private-key').removeClass('input-field-missing-insertion');
        $('#monitor-threshold-uptime').removeClass('input-field-missing-insertion');
        $('#monitor-threshold-updates-available').removeClass('input-field-missing-insertion');

        var sshKeySource = $('#monitor-ssh-key-source').val();
        var payload = {
            name: $('#monitor-name').val().trim(),
            hostname_ip: $('#monitor-hostname-ip').val().trim(),
            username: $('#monitor-username').val().trim(),
            auth_method: $('#monitor-auth-method').val().trim(),
            password: $('#monitor-password').val().trim(),
            ssh_private_key: $('#monitor-ssh-private-key')[0].files[0],
            existing_ssh_key_monitor_id: sshKeySource === 'upload' ? '' : sshKeySource,
            threshold_uptime: $('#monitor-threshold-uptime').val().trim(),
            threshold_updates_available: $('#monitor-threshold-updates-available').val().trim()
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

        if(payload.auth_method == 'ssh_private_key' && sshKeySource === 'upload' && payload.ssh_private_key === undefined) {
            $('#monitor-ssh-private-key').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.threshold_uptime.length == 0) {
            $('#monitor-threshold-uptime').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(payload.threshold_updates_available.length == 0) {
            $('#monitor-threshold-updates-available').addClass('input-field-missing-insertion');
            hasEmptyMissing = true;
        }

        if(hasEmptyMissing) {
            toastr.error("Complete all required fields.", "Missing information")
            return;
        }

        // Create temporary form data so that I can upload the file if needed
        var formData = new FormData();
        for(var key of Object.keys(payload)) {
            if(payload[key] !== undefined && payload[key] !== null) {
                formData.append(key, payload[key]);
            }
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
                    toastr.error(`Could not add monitor (${data.errorMessage})`, "Save failed");
                }

                toastr.success('Monitor added.');
                setTimeout(function () {
                    window.location.href = '/'; // Return to the dashboard to see all monitors
                }, 2000);
            },
            error: function () {
                toastr.error("Could not add monitor", "Save failed");
            },
            complete: function () {
                $('button[data-action="create-new-monitor"]').html(_btnHTML);
            }
        });
    });
</script>
@endsection
