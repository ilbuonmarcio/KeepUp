@php($isEditing = $domain->exists)
<form method="post" action="{{ $isEditing ? route('configuration.domains.update', $domain) : route('configuration.domains.store') }}" enctype="multipart/form-data" class="domain-form" data-domain-form>
    @csrf
    @if($isEditing) @method('PUT') @endif

    <section class="form-section">
        <div class="section-heading">
            <div>
                <h2>{{ $isEditing ? $domain->name : 'Add Windows domain' }}</h2>
                <p>Discover enabled Active Directory computers over LDAP and monitor them over SSH.</p>
            </div>
            @if($isEditing)
                <span class="count-badge">{{ $domain->monitors_count }} {{ Str::plural('computer', $domain->monitors_count) }}</span>
            @endif
        </div>

        <div class="input-row columns-3">
            <div class="input-cell">
                <label>Configuration name</label>
                <input name="name" value="{{ old('name', $domain->name) }}" required>
            </div>
            <div class="input-cell">
                <label>Domain DNS name</label>
                <input name="domain_name" value="{{ old('domain_name', $domain->domain_name) }}" placeholder="example.local" required>
                <small>The Active Directory domain, not the domain controller hostname.</small>
            </div>
            <div class="input-cell">
                <label>Base DN</label>
                <input name="base_dn" value="{{ old('base_dn', $domain->base_dn) }}" placeholder="DC=example,DC=local">
                <small>Leave blank to derive it, or enter a DNS domain or LDAP distinguished name.</small>
            </div>
        </div>

        <div class="input-row columns-3">
            <div class="input-cell">
                <label>Domain controller</label>
                <input name="ldap_host" value="{{ old('ldap_host', $domain->ldap_host) }}" placeholder="dc01.example.local" required>
            </div>
            <div class="input-cell">
                <label>LDAP security</label>
                <select name="ldap_security" data-ldap-security>
                    <option value="ldaps" @selected(old('ldap_security', $domain->ldap_security ?: 'ldaps') === 'ldaps')>LDAPS</option>
                    <option value="starttls" @selected(old('ldap_security', $domain->ldap_security) === 'starttls')>LDAP with StartTLS</option>
                    <option value="ldap" @selected(old('ldap_security', $domain->ldap_security) === 'ldap')>LDAP (unencrypted)</option>
                </select>
                <small>Use LDAPS or StartTLS when credentials cross the network.</small>
            </div>
            <div class="input-cell">
                <label>LDAP port</label>
                <input type="number" name="ldap_port" value="{{ old('ldap_port', $domain->ldap_port ?: 636) }}" min="1" max="65535" required>
            </div>
        </div>

        <div class="input-row columns-2">
            <div class="input-cell">
                <label>Discovery bind username</label>
                <input name="discovery_username" value="{{ old('discovery_username', $domain->discovery_username) }}" placeholder="discovery@example.local" required>
                <small>This account only needs permission to read Active Directory computer objects.</small>
            </div>
            <div class="input-cell">
                <label>Discovery bind password</label>
                <input type="password" name="discovery_password" autocomplete="new-password" @required(!$isEditing)>
                @if($isEditing)<small>Leave blank to keep the stored encrypted password.</small>@endif
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading">
            <div>
                <h2>Inherited monitor connection</h2>
                <p>Every computer discovered in this domain uses these SSH credentials and thresholds.</p>
            </div>
        </div>

        <div class="input-row columns-2">
            <div class="input-cell">
                <label>SSH username</label>
                <input name="monitor_username" value="{{ old('monitor_username', $domain->monitor_username) }}" placeholder="monitor@example.local" required>
            </div>
            <div class="input-cell">
                <label>Authentication</label>
                <select name="auth_method" data-domain-auth-method>
                    <option value="ssh_private_key" @selected(old('auth_method', $domain->auth_method ?: 'ssh_private_key') === 'ssh_private_key')>SSH private key</option>
                    <option value="password" @selected(old('auth_method', $domain->auth_method) === 'password')>Password</option>
                </select>
            </div>
        </div>

        <div class="input-row columns-1" data-domain-password-row>
            <div class="input-cell">
                <label>SSH password</label>
                <input type="password" name="monitor_password" autocomplete="new-password">
                @if($isEditing && $domain->auth_method === 'password')<small>Leave blank to keep the stored encrypted password.</small>@endif
            </div>
        </div>

        <div class="input-row columns-1" data-domain-key-row>
            <div class="input-cell">
                <label>SSH private key</label>
                <input type="file" name="ssh_private_key">
                <small>{{ $isEditing && $domain->ssh_private_key ? 'Leave blank to keep the current encrypted private key.' : 'Upload the private key matching the public key deployed to the domain computers.' }}</small>
            </div>
        </div>

        <div class="input-row columns-2">
            <div class="input-cell">
                <label>Uptime threshold</label>
                <input type="number" name="threshold_uptime" value="{{ old('threshold_uptime', $domain->threshold_uptime ?? 365) }}" min="0" step="1" required>
            </div>
            <div class="input-cell">
                <label>Updates threshold</label>
                <input type="number" name="threshold_updates_available" value="{{ old('threshold_updates_available', $domain->threshold_updates_available ?? 1) }}" min="1" required>
            </div>
        </div>
    </section>

    <div class="form-actions">
        @if($isEditing)
            <span class="domain-sync-status">
                @if($domain->last_error)
                    <span class="status-badge unreachable" title="{{ $domain->last_error }}">Discovery failed</span>
                @elseif($domain->last_discovered_at)
                    Last discovery {{ $domain->last_discovered_at->format('M j, Y · H:i') }}
                @else
                    Not synchronized yet
                @endif
            </span>
        @endif
        <button class="button primary" type="submit"><i class="fas fa-check"></i>{{ $isEditing ? 'Save and synchronize' : 'Add and synchronize' }}</button>
    </div>
</form>
