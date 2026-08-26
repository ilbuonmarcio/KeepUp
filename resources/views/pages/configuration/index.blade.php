@extends('layouts.app')

@section('page-content')
<div class="page-container page-container-narrow configuration-page">
    <header class="page-header">
        <div>
            <a href="{{ route('dashboard.index') }}" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h1>Configuration</h1>
        </div>
    </header>

    @if(session('status'))
        <div class="configuration-notice is-success"><i class="fas fa-circle-check"></i>{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="configuration-notice is-error"><i class="fas fa-triangle-exclamation"></i>{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="configuration-notice is-error">
            <i class="fas fa-triangle-exclamation"></i>
            <div><strong>Correct the following fields:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="configuration-heading">
        <div>
            <h2>Windows domains</h2>
            <p>Discover Active Directory computers through LDAP/LDAPS and manage their shared SSH connection centrally.</p>
        </div>
    </div>

    @foreach($domains as $domain)
        <div class="panel form-panel domain-panel">
            @include('pages.configuration._domain-form', ['domain' => $domain])
            <div class="domain-secondary-actions">
                <form method="post" action="{{ route('configuration.domains.sync', $domain) }}">
                    @csrf
                    <button class="button secondary" type="submit"><i class="fas fa-rotate"></i>Synchronize now</button>
                </form>
                <form method="post" action="{{ route('configuration.domains.destroy', $domain) }}" onsubmit="return confirm('Remove this domain and all of its discovered monitors?')">
                    @csrf
                    @method('DELETE')
                    <button class="button ghost domain-delete" type="submit"><i class="fas fa-trash"></i>Remove domain</button>
                </form>
            </div>
        </div>
    @endforeach

    <div class="panel form-panel domain-panel">
        @include('pages.configuration._domain-form', ['domain' => new \App\Models\WindowsDomain])
    </div>
</div>
@endsection

@section('page-js')
<script>
    function updateDomainAuthentication(form) {
        var usesKey = form.find('[data-domain-auth-method]').val() === 'ssh_private_key';
        form.find('[data-domain-key-row]').toggle(usesKey);
        form.find('[data-domain-password-row]').toggle(!usesKey);
    }

    $('[data-domain-form]').each(function () { updateDomainAuthentication($(this)); });
    $('[data-domain-auth-method]').on('change', function () { updateDomainAuthentication($(this).closest('form')); });

    $('[data-ldap-security]').on('change', function () {
        var port = $(this).closest('form').find('[name="ldap_port"]');
        if (port.val() === '389' || port.val() === '636') {
            port.val($(this).val() === 'ldaps' ? '636' : '389');
        }
    });
</script>
@endsection
