@php($payerType = $patient->primaryPayerProfile?->payer_type?->label())
<article class="patient-id-card" data-testid="patient-id-card">
    <header class="patient-id-card__header">
        <div class="patient-id-card__brand">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ $facility->name }} logo" class="patient-id-card__logo">
            @else
                <span class="patient-id-card__logo-fallback" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><path d="M5 5h14v14H5z"/></svg>
                </span>
            @endif
            <div class="patient-id-card__facility-copy">
                <p class="patient-id-card__facility">{{ $facility->name }}</p>
                @if ($facilitySlogan)<p class="patient-id-card__slogan">{{ $facilitySlogan }}</p>@endif
            </div>
        </div>
    </header>

    <section class="patient-id-card__body">
        <div class="patient-id-card__photo-wrap">
            @if ($photoDataUri)
                <img src="{{ $photoDataUri }}" alt="Photo of {{ $patient->fullName() }}" class="patient-id-card__photo" data-testid="patient-card-photo">
            @else
                <div class="patient-id-card__avatar" data-testid="patient-card-avatar" aria-label="Patient initials">{{ $patient->initials() ?: 'P' }}</div>
            @endif
        </div>

        <div class="patient-id-card__identity">
            <h1>{{ $patient->fullName() }}</h1>
            <span class="patient-id-card__badge">Patient Card</span>
        </div>

        <dl class="patient-id-card__details">
            <div class="patient-id-card__detail patient-id-card__detail--wide">
                <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 9h14M4 15h14M10 3 8 21M16 3l-2 18"/></svg>Patient number</dt>
                <dd>{{ $patient->patient_number }}</dd>
            </div>
            <div class="patient-id-card__detail patient-id-card__detail--wide">
                <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>Gender / Age</dt>
                <dd>{{ $patient->gender?->label() }} · {{ $patient->ageLabel() }}</dd>
            </div>
            @if (filled($patient->primary_phone))
                <div class="patient-id-card__detail patient-id-card__detail--wide">
                    <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.33 1.84.56 2.8.69A2 2 0 0 1 22 16.92z"/></svg>Phone</dt>
                    <dd>{{ $patient->primary_phone }}</dd>
                </div>
            @endif
            @if ($payerType)
                <div class="patient-id-card__detail patient-id-card__detail--wide">
                    <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>Payer type</dt>
                    <dd>{{ $payerType }}</dd>
                </div>
            @endif
        </dl>

        <div class="patient-id-card__verification">
            <div class="patient-id-card__qr" data-testid="patient-card-qr" data-qr-payload="{{ $qrPayload }}">
                <img src="{{ $qrDataUri }}" alt="QR code for authorized patient card lookup">
            </div>
            <div>
                <p class="patient-id-card__verify-title">Scan to verify</p>
                <p class="patient-id-card__verify-copy">Authentication is required to view this patient card.</p>
            </div>
        </div>
    </section>

    <footer class="patient-id-card__footer">
        <span>Issued {{ $issueDate }}</span>
        <span>{{ $facility->name }}</span>
    </footer>
</article>
