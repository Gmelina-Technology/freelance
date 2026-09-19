@php
    $company = config('legal.company');
    $email = config('legal.contact_email');
@endphp

<x-legal-page title="Terms of Service">
    <p>These terms govern your use of Freelance Manager, operated by {{ $company }} ("we", "us"). By creating an account
        or using the service you agree to them and to our <a href="{{ route('privacy') }}">Privacy Policy</a>. If you are
        using the service for an organisation, you confirm you can bind it to these terms.</p>

    <h2>1. The service</h2>
    <p>Freelance Manager helps freelancers and small teams manage clients, projects, tasks, quotes and invoices. We may
        improve, change or remove features over time.</p>

    <h2>2. Your account</h2>
    <ul>
        <li>You must be at least 18 and able to enter a binding contract.</li>
        <li>Give accurate information and keep your sign-in details secret. You are responsible for activity under your
            account, including by teammates you invite.</li>
        <li>Tell us promptly if you think your account has been compromised.</li>
    </ul>

    <h2>3. Acceptable use</h2>
    <p>Do not use the service to break the law, infringe others' rights, send spam or misleading invoices, upload malware,
        probe or disrupt our systems, or attempt to access other people's workspaces.</p>

    <h2>4. Your content</h2>
    <p>You own the content you put into the service, including client details, quotes and invoices. You give us permission
        to store, process and transmit it only as needed to run the service for you, including sending emails you
        initiate. You are responsible for having the right to use that content and to store your clients' personal data.</p>

    <h2>5. Quotes, invoices and tax</h2>
    <p>The app produces documents from the information you enter. You are responsible for their accuracy and for
        complying with tax, invoicing and consumer rules that apply to you. The service does not provide legal,
        accounting or tax advice.</p>

    <h2>6. Fees</h2>
    <p>If we introduce paid plans, we will tell you the price and billing terms before you are charged, and you may
        cancel before then.</p>

    <h2>7. Availability and support</h2>
    <p>We aim to keep the service available but do not promise uninterrupted or error-free operation. Keep your own
        copies of documents you need, such as issued invoices.</p>

    <h2>8. Ending your account</h2>
    <p>You can stop using the service at any time and ask us to delete your account. We may suspend or end access if you
        seriously or repeatedly breach these terms or if required by law. Sections that by nature should survive
        (ownership, liability and disputes) continue after termination.</p>

    <h2>9. Disclaimers</h2>
    <p>The service is provided "as is" and "as available". To the extent the law allows, we disclaim warranties of any
        kind, including fitness for a particular purpose.</p>

    <h2>10. Limitation of liability</h2>
    <p>To the extent the law allows, we are not liable for indirect or consequential losses, lost profits or lost data,
        and our total liability for any claim is limited to the amount you paid us in the 12 months before the claim, or
        zero if you paid nothing. Nothing in these terms limits liability that cannot be limited by law.</p>

    <h2>11. Changes to these terms</h2>
    <p>We may update these terms. If a change is material we will give reasonable notice, and continuing to use the
        service afterwards means you accept the new terms.</p>

    <h2>12. Governing law</h2>
    <p>@if (config('legal.jurisdiction'))These terms are governed by the laws of {{ config('legal.jurisdiction') }}, and its courts have jurisdiction, without affecting any mandatory consumer rights you have where you live.@else These terms are governed by the laws of the place where {{ $company }} is established, without affecting any mandatory consumer rights you have where you live.@endif</p>

    <h2>13. Contact</h2>
    <p>Questions about these terms:
        @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else the contact address provided by the operator of this service @endif.</p>
</x-legal-page>
