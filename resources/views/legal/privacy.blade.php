@php
    $company = config('legal.company');
    $email = config('legal.contact_email');
@endphp

<x-legal-page title="Privacy Policy">
    <p>This policy explains what personal data {{ $company }} ("we", "us") collects when you use Freelance Manager, why we
        collect it, and the choices you have. We have tried to keep it plain and specific to how the app actually works.</p>

    <h2>1. Who is responsible for your data</h2>
    <p>{{ $company }}@if (config('legal.address')), {{ config('legal.address') }}@endif is the controller of the account
        data described below. For the client and project data you enter into the app, you are the controller and we act as
        your processor (see section 3).</p>
    <p>Contact: @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else the contact address provided by the operator of this service @endif</p>

    <h2>2. Data we collect</h2>
    <ul>
        <li><strong>Account data:</strong> your name, email address and a hashed password. We also record when you accepted
            these terms and verified your email address.</li>
        <li><strong>Sign-in and security data:</strong> one-time email codes used for sign-in verification, and technical
            logs such as IP address, browser type and timestamps.</li>
        <li><strong>Content you add:</strong> clients and their contact people, projects, tasks, work logs, comments and
            attachments, quotes, invoices, bank details you choose to show on invoices, email templates, and the names and
            email addresses of teammates you invite.</li>
        <li><strong>Emails sent through the app:</strong> when you send a quote or invoice, we send the email and PDF on
            your behalf to the recipient you chose.</li>
    </ul>
    <p>We do not knowingly collect special-category data and ask that you do not put it in the app.</p>

    <h2>3. Your clients' data</h2>
    <p>Information about your own clients belongs to you. You are responsible for having a lawful basis to store it and
        to send them quotes and invoices. We process it only to provide the service to you, and we do not use it for our own
        purposes.</p>

    <h2>4. How we use data and why</h2>
    <ul>
        <li>To provide and secure the service, including creating your account, signing you in, and sending the emails you
            ask us to send (performance of a contract).</li>
        <li>To keep the service reliable and prevent abuse, and to fix problems (legitimate interests).</li>
        <li>To meet legal obligations, and to respond to your requests (legal obligation).</li>
    </ul>
    <p>We do not sell personal data and we do not use it for advertising or profiling.</p>

    <h2>5. Cookies and similar technologies</h2>
    <p>We use only what is necessary to run the site: a session cookie that keeps you signed in, and a security
        (CSRF) cookie that protects forms. Your light or dark display preference is stored in your browser's local storage.
        We do not use analytics or advertising cookies, so there is no cookie banner to accept. Fonts are hosted by us, not
        loaded from a third party.</p>

    <h2>6. Who we share data with</h2>
    <ul>
        <li>Service providers that help us run the app, such as hosting and email delivery, bound to use data only for that
            purpose.</li>
        <li>People you choose: teammates you invite to your account, and recipients of quotes and invoices you send.</li>
        <li>Authorities, when the law requires it.</li>
    </ul>

    <h2>7. Where data is processed</h2>
    <p>Our providers may process data in countries other than your own. Where that involves a transfer out of the EEA or
        UK, we rely on an approved safeguard such as standard contractual clauses.</p>

    <h2>8. How long we keep data</h2>
    <p>We keep account data and your content while your account is active. If you ask us to delete your account we will
        delete or anonymise your data within a reasonable period, except what we must keep by law. Backups are overwritten
        on their normal cycle.</p>

    <h2>9. Security</h2>
    <p>Passwords are stored hashed, email verification and an email sign-in code are required, and access to each
        workspace is limited to its members. No system is perfectly secure, so please use a unique password and tell us
        promptly if you suspect misuse.</p>

    <h2>10. Your rights</h2>
    <p>Depending on where you live, you may have the right to access, correct, delete or export your personal data, to
        object to or restrict some processing, and to withdraw consent. To use any of these rights, email us at
        @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else the contact address above @endif and we will
        respond within one month. You may also complain to your local data protection authority.</p>

    <h2>11. Children</h2>
    <p>The service is for business use and is not directed at anyone under 16. If you believe a child has given us
        personal data, contact us and we will delete it.</p>

    <h2>12. Changes to this policy</h2>
    <p>If we make material changes we will update the effective date above and, where appropriate, notify you by email
        or in the app.</p>

    <p class="mt-10 text-sm text-brand-ink/60 dark:text-gray-500">See also our <a href="{{ route('terms') }}">Terms of Service</a>.</p>
</x-legal-page>
