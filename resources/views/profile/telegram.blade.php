@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Telegram Integration</div>

                <div class="card-body">
                    @if (auth()->user()->telegram_verified_at)
                        <div class="alert alert-success">
                            Telegram connected since: {{ auth()->user()->telegram_verified_at->format('M d, Y H:i') }}
                        </div>
                        <form method="POST" action="{{ route('telegram.disconnect') }}">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                Disconnect Telegram
                            </button>
                        </form>
                    @else
                        <ol>
                            <li>Click the button below to open Telegram</li>
                            <li>Start a conversation with our bot</li>
                            <li>Send the authentication code: <strong>{{ $authToken }}</strong></li>
                        </ol>
                        
                        <a href="https://t.me/{{ $botUsername }}" 
                           class="btn btn-primary" 
                           target="_blank">
                            Connect with Telegram
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection