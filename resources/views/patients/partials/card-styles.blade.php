<style>
    .patient-card-workspace { display: grid; justify-items: center; gap: 1.25rem; padding: 1.25rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: radial-gradient(circle at top, #f0fdfa 0, #f8fafc 42%, #f1f5f9 100%); }
    .patient-card-preview-heading { width: min(100%, 360px); display: flex; align-items: center; gap: .75rem; color: #0f172a; }
    .patient-card-preview-heading h2 { margin: 0; font-size: .95rem; font-weight: 700; }
    .patient-card-preview-heading p { margin: .15rem 0 0; font-size: .75rem; color: #64748b; }
    .patient-card-preview-icon { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; flex: none; border-radius: .65rem; color: #0f766e; background: #ccfbf1; }
    .patient-card-preview-icon svg { width: 1.25rem; height: 1.25rem; }
    .patient-id-card, .patient-id-card * { box-sizing: border-box; }
    .patient-id-card { --card-teal: #075e59; --card-emerald: #0f766e; width: min(100%, 360px); aspect-ratio: 53.98 / 85.60; display: flex; flex-direction: column; overflow: hidden; position: relative; border: 1px solid #d7e2e2; border-radius: 1rem; color: #0f172a; background: #fff; box-shadow: 0 24px 55px -24px rgba(15, 23, 42, .45); font-family: Poppins, Arial, sans-serif; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .patient-id-card__header { min-height: 23%; padding: 6.5% 6% 13%; position: relative; color: #fff; background: linear-gradient(145deg, #064e4a, #0f766e); }
    .patient-id-card__header::after { content: ''; position: absolute; right: -12%; bottom: -35%; width: 58%; aspect-ratio: 1; border: 1px solid rgba(255,255,255,.14); border-radius: 999px; }
    .patient-id-card__brand { display: flex; align-items: center; gap: 3.5%; position: relative; z-index: 1; }
    .patient-id-card__logo, .patient-id-card__logo-fallback { width: 14%; aspect-ratio: 1; flex: none; border-radius: 18%; object-fit: contain; background: #fff; padding: 1.5%; }
    .patient-id-card__logo-fallback { display: grid; place-items: center; color: var(--card-emerald); }
    .patient-id-card__logo-fallback svg { width: 72%; height: 72%; }
    .patient-id-card__facility-copy { min-width: 0; }
    .patient-id-card__facility { margin: 0; overflow: hidden; font-size: clamp(.65rem, 3.2vw, .98rem); font-weight: 700; line-height: 1.15; letter-spacing: -.015em; text-overflow: ellipsis; white-space: nowrap; }
    .patient-id-card__slogan { margin: 2% 0 0; overflow: hidden; font-size: clamp(.4rem, 1.8vw, .58rem); line-height: 1.2; opacity: .78; text-overflow: ellipsis; white-space: nowrap; }
    .patient-id-card__badge { display: inline-block; margin-top: 2%; border-radius: 999px; padding: 1.6% 4%; color: #0f766e; font-size: clamp(.4rem, 1.8vw, .57rem); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: #ccfbf1; }
    .patient-id-card__body { flex: 1; min-height: 0; display: flex; flex-direction: column; align-items: center; padding: 0 7% 4%; background: #fff; }
    .patient-id-card__photo-wrap { width: 31%; aspect-ratio: 1; margin-top: -12%; z-index: 2; }
    .patient-id-card__photo, .patient-id-card__avatar { width: 100%; height: 100%; border: 4px solid #fff; border-radius: 999px; box-shadow: 0 5px 15px rgba(15,23,42,.18); object-fit: cover; background: #dff7f2; }
    .patient-id-card__avatar { display: grid; place-items: center; color: var(--card-teal); font-size: clamp(1.1rem, 6vw, 1.8rem); font-weight: 800; }
    .patient-id-card__identity { width: 100%; margin-top: 3%; text-align: center; }
    .patient-id-card__identity h1 { margin: 0; overflow: hidden; color: #0f172a; font-size: clamp(.8rem, 4.2vw, 1.2rem); font-weight: 750; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
    .patient-id-card__details { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 5% 0 0; border: 1px solid #d9e7e5; border-radius: .58rem; overflow: hidden; background: #f8fbfb; }
    .patient-id-card__detail { min-width: 0; padding: 4% 6%; border-bottom: 1px solid #e3eceb; }
    .patient-id-card__detail:nth-child(even):not(.patient-id-card__detail--wide) { border-left: 1px solid #e3eceb; }
    .patient-id-card__detail--wide { grid-column: 1 / -1; }
    .patient-id-card__detail:last-child { border-bottom: 0; }
    .patient-id-card__detail dt { display: flex; align-items: center; gap: 2%; color: #64748b; font-size: clamp(.38rem, 1.75vw, .55rem); font-weight: 600; line-height: 1.2; text-transform: uppercase; letter-spacing: .04em; }
    .patient-id-card__detail dt svg { width: 1em; height: 1em; flex: none; stroke-width: 2; }
    .patient-id-card__detail dd { margin: 1.2% 0 0; overflow: hidden; color: #0f172a; font-size: clamp(.52rem, 2.55vw, .75rem); font-weight: 650; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
    .patient-id-card__verification { width: 100%; display: flex; align-items: center; gap: 5%; margin-top: auto; padding-top: 5%; }
    .patient-id-card__qr { width: 28%; aspect-ratio: 1; flex: none; border: 1px solid #cbd5e1; border-radius: .35rem; overflow: hidden; background: #fff; }
    .patient-id-card__qr img { display: block; width: 100%; height: 100%; }
    .patient-id-card__verify-title { margin: 0; color: var(--card-teal); font-size: clamp(.53rem, 2.5vw, .74rem); font-weight: 700; }
    .patient-id-card__verify-copy { margin: 2% 0 0; color: #64748b; font-size: clamp(.38rem, 1.65vw, .52rem); line-height: 1.35; }
    .patient-id-card__footer { min-height: 7%; display: flex; align-items: center; justify-content: space-between; gap: 4%; padding: 2% 6%; color: #d7fffa; font-size: clamp(.34rem, 1.55vw, .49rem); background: #064e4a; }
    .patient-id-card__footer span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .patient-id-card__footer span:last-child { max-width: 55%; text-align: right; }
    .dark .patient-card-workspace { border-color: #334155; background: radial-gradient(circle at top, #123733 0, #111827 48%, #0f172a 100%); }
    .dark .patient-card-preview-heading { color: #f8fafc; }
    .dark .patient-card-preview-heading p { color: #94a3b8; }
    .dark .patient-id-card { color: #0f172a; background: #fff; }
    @media (max-width: 480px) { .patient-card-workspace { margin-inline: -.5rem; padding: 1rem .65rem; } }
    @media print {
        * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
        .patient-id-card { break-inside: avoid; }
    }
</style>
