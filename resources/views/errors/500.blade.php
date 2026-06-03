@extends('errors.layout')

@section('title', 'Something broke')
@section('badge', 'Server error')
@section('code', '500')
@section('heading', 'Something broke on our end')
@section('message', 'We hit an unexpected error processing that request. The team has been notified — try again in a moment, and if it keeps happening, reach out to support.')
