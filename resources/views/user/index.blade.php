@extends('layouts.app')

@section('title', 'Users')

@section('content')
    @if (session('password'))
        <div id="flash-password">
            {{ session('password') }}
        </div>
    @endif
@endsection
