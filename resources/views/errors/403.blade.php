@extends('errors.layout')

@section('title', 'Access denied')
@section('badge', 'Restricted')
@section('code', '403')
@section('heading', 'You don\'t have access to this page')

@section('message')
    {{ ($exception ?? null) && $exception->getMessage()
        ? $exception->getMessage()
        : 'This area is restricted to specific roles. If you believe this is a mistake, ask your subject owner or an admin to grant access.' }}
@endsection
