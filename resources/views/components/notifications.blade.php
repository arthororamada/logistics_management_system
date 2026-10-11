@php
    $notificationMessages = [];
    foreach (['success', 'error', 'warning', 'info'] as $notificationType) {
        if (session()->has($notificationType)) {
            $notificationMessages[] = ['type' => $notificationType, 'message' => (string) session($notificationType)];
        }
    }
@endphp
<script type="application/json" data-app-notifications>@json(['messages' => $notificationMessages, 'errors' => $errors->all()])</script>
<noscript>
    @foreach($notificationMessages as $notification)
        <div class="alert alert-{{ $notification['type'] }}" role="status">{{ $notification['message'] }}</div>
    @endforeach
    @if($errors->any())<div class="alert alert-error" role="alert">{{ implode(' ', $errors->all()) }}</div>@endif
</noscript>
