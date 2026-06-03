@extends('errors.layout')

@section('title', 'Too many requests')
@section('badge', 'Slow down')
@section('code', '429')
@section('heading', 'Too many requests')
@section('message', 'You\'ve hit the rate limit. Wait a moment, then try again.')
