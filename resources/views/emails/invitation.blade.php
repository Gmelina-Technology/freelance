@component('mail::message')
# Invitation to join an account

Hello,

You have been invited to join an account with the role of **{{ $invitation->role_label }}**.

@component('mail::button', ['url' => $acceptUrl])
Accept Invitation
@endcomponent

If you do not already have an account, please register with the same email address before accepting.

Thanks,

Team
@endcomponent
