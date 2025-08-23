@extends('layouts.layout')

@section('title', __('Telegram Integration'))

@section('sidebar')
    @include('layouts.sidebar', ['sidebar' => Menu::get('sidebar_admin')])
@endsection

@section('breadcrumbs')
    @include('shared.breadcrumbs', [
        'routes' => [
            __('Profile') => route('profile.edit'),
            __('Telegram Integration') => null,
        ]
    ])
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <i class="fab fa-telegram-plane text-info mr-2" style="font-size: 1.5em;"></i>
                            <h4 class="mb-0">{{ __('Telegram Integration') }}</h4>
                        </div>
                    </div>

                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle mr-2"></i>
                                {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                {{ session('warning') }}
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-times-circle mr-2"></i>
                                {{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                        @endif

                        @if($isConnected)
                            {{-- Connected State --}}
                            <div class="alert alert-success border-left-success">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle text-success" style="font-size: 2em;"></i>
                                    </div>
                                    <div class="col">
                                        <h5 class="alert-heading mb-1">
                                            <i class="fas fa-link mr-2"></i>{{ __('Telegram Connected') }}
                                        </h5>
                                        <p class="mb-2">{{ __('Your Telegram account is successfully connected to ProcessMaker.') }}</p>
                                        <small class="text-muted">
                                            <i class="far fa-calendar-alt mr-1"></i>
                                            {{ __('Connected since:') }} {{ $connectedAt->format('M j, Y \a\t H:i') }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <h5>{{ __('What you can do:') }}</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item border-0 px-0">
                                            <i class="fas fa-bell text-primary mr-3"></i>
                                            <strong>{{ __('Task Notifications') }}</strong><br>
                                            <small class="text-muted">{{ __('Receive instant notifications when tasks are assigned to you') }}</small>
                                        </li>
                                        <li class="list-group-item border-0 px-0">
                                            <i class="fas fa-mouse-pointer text-success mr-3"></i>
                                            <strong>{{ __('Quick Actions') }}</strong><br>
                                            <small class="text-muted">{{ __('Complete, claim, or manage tasks directly from Telegram') }}</small>
                                        </li>
                                        <li class="list-group-item border-0 px-0">
                                            <i class="fas fa-chart-line text-info mr-3"></i>
                                            <strong>{{ __('Process Updates') }}</strong><br>
                                            <small class="text-muted">{{ __('Stay informed about process status and completions') }}</small>
                                        </li>
                                    </ul>
                                </div>

                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fab fa-telegram-plane text-info mb-2" style="font-size: 3em;"></i>
                                            <h6 class="card-title">{{ __('Connected Account') }}</h6>
                                            <p class="card-text">
                                                <strong>{{ $user->fullname }}</strong><br>
                                                <small class="text-muted">@{{ $user->username }}</small>
                                            </p>
                                            @if($user->telegram_username)
                                                <small class="text-muted">
                                                    <i class="fab fa-telegram mr-1"></i>
                                                    @{{ $user->telegram_username }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <form method="POST" action="{{ route('telegram.disconnect') }}"
                                      onsubmit="return confirm('{{ __('Are you sure you want to disconnect Telegram? You will stop receiving notifications.') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fas fa-unlink mr-2"></i>
                                        {{ __('Disconnect Telegram') }}
                                    </button>
                                </form>
                            </div>
                        @else
                            {{-- Not Connected State --}}
                            <div class="alert alert-info border-left-info">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <i class="fas fa-info-circle text-info" style="font-size: 2em;"></i>
                                    </div>
                                    <div class="col">
                                        <h5 class="alert-heading mb-1">{{ __('Connect your Telegram account') }}</h5>
                                        <p class="mb-0">{{ __('Receive task notifications and manage your work directly from Telegram.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">
                                                <i class="fas fa-link mr-2"></i>
                                                {{ __('Connection Setup') }}
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            @if($botUsername)
                                                <div class="setup-steps">
                                                    <div class="step-item">
                                                        <div class="step-number">1</div>
                                                        <div class="step-content">
                                                            <h6>{{ __('Open Telegram') }}</h6>
                                                            <p class="text-muted mb-2">{{ __('Click the button below to open our ProcessMaker bot in Telegram') }}</p>
                                                            <a href="https://t.me/{{ $botUsername }}"
                                                               class="btn btn-primary"
                                                               target="_blank">
                                                                <i class="fab fa-telegram-plane mr-2"></i>
                                                                {{ __('Open @:bot in Telegram', ['bot' => $botUsername]) }}
                                                            </a>
                                                        </div>
                                                    </div>

                                                    <div class="step-item">
                                                        <div class="step-number">2</div>
                                                        <div class="step-content">
                                                            <h6>{{ __('Start conversation') }}</h6>
                                                            <p class="text-muted">{{ __('Send the following command to the bot:') }}</p>
                                                            @if(isset($authToken))
                                                                <div class="input-group">
                                                                    <input type="text"
                                                                           class="form-control bg-light"
                                                                           value="/start {{ $authToken }}"
                                                                           id="authCommand"
                                                                           readonly>
                                                                    <div class="input-group-append">
                                                                        <button class="btn btn-outline-secondary"
                                                                                type="button"
                                                                                onclick="copyToClipboard('authCommand')">
                                                                            <i class="fas fa-copy"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted">{{ __('Token expires in 1 hour') }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="step-item">
                                                        <div class="step-number">3</div>
                                                        <div class="step-content">
                                                            <h6>{{ __('Confirmation') }}</h6>
                                                            <p class="text-muted">{{ __('The bot will confirm your connection and you\'ll start receiving notifications.') }}</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <form method="POST" action="{{ route('telegram.regenerate-token') }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-sync-alt mr-1"></i>
                                                            {{ __('Generate New Token') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    {{ __('Bot configuration is incomplete. Please contact your system administrator.') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-transparent">
                                            <h6 class="mb-0">
                                                <i class="fas fa-question-circle mr-2"></i>
                                                {{ __('Why connect Telegram?') }}
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    {{ __('Instant notifications') }}
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    {{ __('Quick task actions') }}
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    {{ __('Mobile-friendly') }}
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    {{ __('Secure connection') }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="card mt-3">
                                        <div class="card-header bg-transparent">
                                            <h6 class="mb-0">
                                                <i class="fas fa-shield-alt mr-2"></i>
                                                {{ __('Privacy & Security') }}
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <small class="text-muted">
                                                {{ __('Your connection is secure and you can disconnect at any time. We only access your chat ID for notifications.') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .border-left-success {
            border-left: 4px solid #1cc88a !important;
        }
        .border-left-info {
            border-left: 4px solid #36b9cc !important;
        }

        .setup-steps .step-item {
            display: flex;
            margin-bottom: 2rem;
            align-items: flex-start;
        }

        .step-number {
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .step-content {
            flex: 1;
        }

        .step-content h6 {
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .copy-success {
            color: #28a745 !important;
        }
    </style>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999);
            document.execCommand('copy');

            // Visual feedback
            const button = element.nextElementSibling.querySelector('button');
            const icon = button.querySelector('i');
            const originalClass = icon.className;

            icon.className = 'fas fa-check copy-success';
            setTimeout(() => {
                icon.className = originalClass;
            }, 2000);
        }

        // Auto-refresh connection status
        @if(!$isConnected && isset($authToken))
        let checkInterval;
        function startConnectionCheck() {
            checkInterval = setInterval(() => {
                fetch(window.location.href, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                    .then(response => response.text())
                    .then(html => {
                        if (html.includes('Telegram Connected') || html.includes('telegram_connected')) {
                            clearInterval(checkInterval);
                            window.location.reload();
                        }
                    })
                    .catch(error => console.log('Connection check failed:', error));
            }, 5000);
        }

        // Start checking after 10 seconds
        setTimeout(startConnectionCheck, 10000);

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });
        @endif
    </script>
@endsection