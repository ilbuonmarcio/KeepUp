@extends('layouts.app')

@section('page-content')
<div class="card">
    <h5>Dashboard</h5>

    @forelse($monitors as $monitor)
        
    @empty
        <p>There are currently no active monitors. Add one by <span class="brand-color">clicking on the top right button</span>.</p>
    @endforelse
</div>
@endsection

