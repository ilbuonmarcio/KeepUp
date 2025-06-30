<div id="nav-left">
    <a href="/" id="logo-link"><img src="/images/logo.png"/></a>
    <a href="/">Dashboard</a>
</div>
<div id="nav-right">
    <a href="/monitors/new">+ Add Monitor</a>
    <button id="logout-button">Logout</button>
</div>

@section('page-js')
<script>
    document.getElementById('logout-button').addEventListener('click', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        }).then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                window.location.reload();
            }
        }).catch(error => {
            console.error('Logout failed:', error);
        });
    });
</script>
@endsection