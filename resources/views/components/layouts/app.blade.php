@props(['title' => 'Dashibodi', 'description' => null, 'actions' => null])
@php
    $currentFacility = app(\App\Services\FacilityContext::class)->current();
    $displayFacilityName = $currentFacility?->name ?? 'Professional Dispensary';
    $user = auth()->user();
    $navGroups = [
        'Huduma' => [
            ['label' => 'Dashibodi', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'enabled' => true],
            ['label' => 'Wagonjwa Wote', 'icon' => 'users', 'route' => 'patients.index', 'enabled' => $user?->can('patients.view') ?? false],
            ['label' => 'Sajili Mgonjwa', 'icon' => 'user-plus', 'route' => 'patients.index', 'enabled' => $user?->can('patients.create') ?? false],
            ['label' => 'Reception Dashboard', 'icon' => 'clipboard-list', 'route' => 'reception.index', 'enabled' => $user?->can('reception.access') ?? false],
            ['label' => 'Foleni', 'icon' => 'list-ordered', 'route' => 'reception.queue', 'enabled' => $user?->can('reception.manage-queue') ?? false],
            ['label' => 'Appointments Dashboard', 'icon' => 'calendar-days', 'route' => 'appointments.dashboard', 'active' => 'appointments.dashboard', 'enabled' => ($user?->can('appointments.access') || $user?->can('appointments.view-dashboard') || $user?->can('appointments.view')) ?? false, 'visible' => ($user?->can('appointments.access') || $user?->can('appointments.view-dashboard') || $user?->can('appointments.view')) ?? false],
            ['label' => 'Calendar', 'icon' => 'calendar', 'route' => 'appointments.calendar', 'active' => 'appointments.calendar', 'enabled' => ($user?->can('appointments.view-calendar') || $user?->can('appointments.view')) ?? false, 'visible' => ($user?->can('appointments.view-calendar') || $user?->can('appointments.view')) ?? false],
            ['label' => 'All Appointments', 'icon' => 'calendar-check', 'route' => 'appointments.index', 'active' => 'appointments.index', 'enabled' => $user?->can('appointments.view') ?? false, 'visible' => $user?->can('appointments.view') ?? false],
            ['label' => 'Book Appointment', 'icon' => 'calendar-plus', 'route' => 'appointments.book', 'active' => 'appointments.book', 'enabled' => $user?->can('appointments.create') ?? false, 'visible' => $user?->can('appointments.create') ?? false],
            ['label' => 'Doctor Schedules', 'icon' => 'clock', 'route' => 'appointments.doctor-schedules', 'active' => 'appointments.doctor-schedules', 'enabled' => $user?->can('appointments.manage-doctor-schedules') ?? false, 'visible' => $user?->can('appointments.manage-doctor-schedules') ?? false],
            ['label' => 'Department Schedules', 'icon' => 'calendar-sync', 'route' => 'appointments.department-schedules', 'active' => 'appointments.department-schedules', 'enabled' => $user?->can('appointments.manage-department-schedules') ?? false, 'visible' => $user?->can('appointments.manage-department-schedules') ?? false],
            ['label' => 'Appointment Reports', 'icon' => 'alarm-clock', 'route' => 'appointments.index', 'active' => 'appointments.index', 'enabled' => ($user?->can('appointments.reports.view') || $user?->can('appointments.reports')) ?? false, 'visible' => ($user?->can('appointments.reports.view') || $user?->can('appointments.reports')) ?? false],
            ['label' => 'Triage', 'icon' => 'heart-pulse', 'route' => 'triage.index', 'enabled' => $user?->can('triage.access') ?? false],
            ['label' => 'OPD Dashboard', 'icon' => 'activity', 'route' => 'opd.dashboard', 'enabled' => $user?->can('opd.access') ?? false],
            ['label' => 'Foleni ya OPD', 'icon' => 'stethoscope', 'route' => 'opd.index', 'enabled' => $user?->can('opd.view-queue') ?? false],
            ['label' => 'Maabara', 'icon' => 'flask-conical', 'route' => 'laboratory.index', 'active' => 'laboratory.*', 'enabled' => $user?->can('laboratory.view-queue') ?? false],
            ['label' => 'Lab Dashboard', 'icon' => 'chart-no-axes-combined', 'route' => 'laboratory.dashboard', 'enabled' => $user?->can('laboratory.view-dashboard') ?? false],
            ['label' => 'Critical Results', 'icon' => 'triangle-alert', 'route' => 'laboratory.critical-results', 'enabled' => $user?->can('laboratory-critical-results.view') ?? false],
            ['label' => 'Clinical Lab Results', 'icon' => 'file-check-2', 'route' => 'clinical.laboratory-results', 'enabled' => $user?->can('laboratory-results.view') ?? false],
            ['label' => 'Famasi', 'icon' => 'pill', 'route' => 'pharmacy.index', 'active' => 'pharmacy.*', 'enabled' => $user?->can('pharmacy.view-queue') ?? false],
            ['label' => 'Pharmacy Dashboard', 'icon' => 'chart-no-axes-combined', 'route' => 'pharmacy.dashboard', 'enabled' => $user?->can('pharmacy.view-dashboard') ?? false],
            ['label' => 'Medicines', 'icon' => 'package', 'route' => 'pharmacy.medicines.index', 'enabled' => $user?->can('pharmacy.view-medicines') ?? false],
            ['label' => 'Stock Receiving', 'icon' => 'package-check', 'route' => 'pharmacy.receipts.index', 'enabled' => $user?->can('pharmacy.receive-stock') ?? false],
            ['label' => 'Stock Transfers', 'icon' => 'arrow-right-left', 'route' => 'pharmacy.transfers.index', 'enabled' => $user?->can('pharmacy.transfer-stock') ?? false],
            ['label' => 'Dental Dashboard', 'icon' => 'badge-plus', 'route' => 'dental.dashboard', 'active' => 'dental.*', 'enabled' => $user?->can('dental.view-dashboard') ?? false],
            ['label' => 'Dental Queue', 'icon' => 'smile', 'route' => 'dental.index', 'active' => 'dental.*', 'enabled' => $user?->can('dental.view-queue') ?? false],
            ['label' => 'RCH Dashboard', 'icon' => 'baby', 'route' => 'rch.dashboard', 'active' => 'rch.dashboard', 'enabled' => ($user?->can('rch.view-dashboard') || $user?->can('rch.access')) ?? false, 'visible' => ($user?->can('rch.view-dashboard') || $user?->can('rch.access')) ?? false],
            ['label' => 'RCH Queue', 'icon' => 'list-ordered', 'route' => 'rch.index', 'active' => 'rch.index', 'enabled' => ($user?->can('rch.view-queue') || $user?->can('rch.access')) ?? false, 'visible' => ($user?->can('rch.view-queue') || $user?->can('rch.access')) ?? false],
            ['label' => 'Active Pregnancies', 'icon' => 'heart-pulse', 'route' => 'rch.pregnancies.index', 'active' => 'rch.pregnancies.*', 'enabled' => $user?->can('rch.pregnancies.view') ?? false, 'visible' => $user?->can('rch.pregnancies.view') ?? false],
            ['label' => 'ANC Visits', 'icon' => 'clipboard-check', 'route' => 'rch.pregnancies.index', 'active' => 'rch.pregnancies.*', 'enabled' => $user?->can('rch.anc.view') ?? false, 'visible' => $user?->can('rch.anc.view') ?? false],
            ['label' => 'High-risk Pregnancies', 'icon' => 'triangle-alert', 'route' => 'rch.pregnancies.index', 'active' => 'rch.pregnancies.*', 'enabled' => $user?->can('rch.pregnancies.manage-risk') ?? false, 'visible' => $user?->can('rch.pregnancies.manage-risk') ?? false],
            ['label' => 'Family Planning', 'icon' => 'users-round', 'route' => 'rch.family-planning.index', 'active' => 'rch.family-planning.*', 'enabled' => $user?->can('rch.family-planning.view') ?? false, 'visible' => $user?->can('rch.family-planning.view') ?? false],
            ['label' => 'Children', 'icon' => 'baby', 'route' => 'rch.children.index', 'active' => 'rch.children.*', 'enabled' => $user?->can('rch.children.view') ?? false, 'visible' => $user?->can('rch.children.view') ?? false],
            ['label' => 'Growth Monitoring', 'icon' => 'chart-no-axes-combined', 'route' => 'rch.children.index', 'active' => 'rch.children.*', 'enabled' => $user?->can('rch.growth.view') ?? false, 'visible' => $user?->can('rch.growth.view') ?? false],
            ['label' => 'Nutrition Alerts', 'icon' => 'activity', 'route' => 'rch.children.nutrition', 'active' => 'rch.children.nutrition', 'enabled' => $user?->can('rch.growth.assess-nutrition') ?? false, 'visible' => $user?->can('rch.growth.assess-nutrition') ?? false],
            ['label' => 'Immunization', 'icon' => 'syringe', 'route' => 'rch.immunization.index', 'active' => 'rch.immunization.*', 'enabled' => $user?->can('rch.immunization.view') ?? false, 'visible' => $user?->can('rch.immunization.view') ?? false],
            ['label' => 'Defaulters', 'icon' => 'alarm-clock', 'route' => 'rch.immunization.defaulters', 'active' => 'rch.immunization.defaulters', 'enabled' => $user?->can('rch.immunization.view-defaulters') ?? false, 'visible' => $user?->can('rch.immunization.view-defaulters') ?? false],
            ['label' => 'RCH Appointments', 'icon' => 'calendar-plus', 'route' => 'appointments.index', 'active' => 'appointments.*', 'enabled' => $user?->can('rch.appointments.view') ?? false, 'visible' => $user?->can('rch.appointments.view') ?? false],
            ['label' => 'RCH Reports', 'icon' => 'chart-no-axes-combined', 'route' => 'rch.reports', 'active' => 'rch.reports', 'enabled' => $user?->can('rch.reports.view') ?? false, 'visible' => $user?->can('rch.reports.view') ?? false],
            ['label' => 'Bed Rest', 'icon' => 'bed', 'route' => 'observation.index', 'active' => 'observation.*', 'enabled' => $user?->can('observation.access') ?? false],
            ['label' => 'Bed Board', 'icon' => 'layout-grid', 'route' => 'observation.bed-board', 'enabled' => $user?->can('observation.view-bed-board') ?? false],
            ['label' => 'Observation Dashboard', 'icon' => 'heart-pulse', 'route' => 'observation.dashboard', 'enabled' => $user?->can('observation.view-dashboard') ?? false],
        ],
        'Fedha' => [
            ['label' => 'Billing Dashboard', 'icon' => 'receipt', 'route' => 'billing.dashboard', 'active' => 'billing.*', 'enabled' => $user?->can('billing.view-dashboard') ?? false],
            ['label' => 'Billing Queue', 'icon' => 'list-ordered', 'route' => 'billing.index', 'active' => 'billing.*', 'enabled' => $user?->can('billing.view-queue') ?? false],
            ['label' => 'Invoices', 'icon' => 'file-text', 'route' => 'billing.invoices.index', 'active' => 'billing.invoices.*', 'enabled' => $user?->can('billing.view-invoice') ?? false],
            ['label' => 'Cashier Dashboard', 'icon' => 'hand-coins', 'route' => 'cashier.dashboard', 'active' => 'cashier.*', 'enabled' => $user?->can('billing.access') ?? false],
            ['label' => 'Cashier Sessions', 'icon' => 'wallet-cards', 'route' => 'cashier.sessions.index', 'active' => 'cashier.sessions.*', 'enabled' => $user?->can('cashier.sessions.view') ?? false],
            ['label' => 'Open Session', 'icon' => 'badge-plus', 'route' => 'cashier.sessions.index', 'active' => 'cashier.sessions.index', 'enabled' => $user?->can('cashier.sessions.open') ?? false],
            ['label' => 'Current Session', 'icon' => 'wallet', 'route' => 'cashier.sessions.current', 'active' => 'cashier.sessions.current', 'enabled' => $user?->can('cashier.sessions.view') ?? false],
            ['label' => 'Close Session', 'icon' => 'badge-x', 'route' => 'cashier.sessions.current', 'active' => 'cashier.sessions.current', 'enabled' => $user?->can('cashier.sessions.close') ?? false],
            ['label' => 'Session History', 'icon' => 'history', 'route' => 'cashier.sessions.history', 'active' => 'cashier.sessions.history', 'enabled' => $user?->can('cashier.sessions.view') ?? false],
            ['label' => 'Payment Methods', 'icon' => 'credit-card', 'route' => 'settings.billing.payment-methods', 'active' => 'settings.billing.*', 'enabled' => $user?->can('billing.manage-payment-methods') ?? false],
            ['label' => 'Billing Reports', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.billing.collections', 'active' => 'reports.billing.*', 'enabled' => $user?->can('billing.reports.view') ?? false],
            ['label' => 'Uhasibu', 'icon' => 'wallet-cards', 'route' => 'coming-soon', 'enabled' => false],
            ['label' => 'Insurance Dashboard', 'icon' => 'shield-check', 'route' => 'insurance.dashboard', 'active' => 'insurance.*', 'enabled' => $user?->can('insurance.view-dashboard') ?? false],
            ['label' => 'Memberships', 'icon' => 'user-round-check', 'route' => 'insurance.memberships.index', 'active' => 'insurance.memberships.*', 'enabled' => $user?->can('insurance.view-memberships') ?? false],
            ['label' => 'Pre-authorizations', 'icon' => 'clipboard-check', 'route' => 'insurance.pre-authorizations.index', 'active' => 'insurance.pre-authorizations.*', 'enabled' => $user?->can('insurance.pre-authorizations.view') ?? false],
            ['label' => 'Claims', 'icon' => 'file-text', 'route' => 'insurance.claims.index', 'active' => 'insurance.claims.*', 'enabled' => $user?->can('insurance.claims.view') ?? false],
            ['label' => 'Claim Batches', 'icon' => 'package-check', 'route' => 'insurance.claim-batches.index', 'active' => 'insurance.claim-batches.*', 'enabled' => $user?->can('insurance.claim-batches.view') ?? false],
            ['label' => 'Insurance Payments', 'icon' => 'hand-coins', 'route' => 'insurance.payments.index', 'active' => 'insurance.payments.*', 'enabled' => $user?->can('insurance.payments.view') ?? false],
            ['label' => 'Reconciliation', 'icon' => 'receipt', 'route' => 'insurance.reconciliation.index', 'active' => 'insurance.reconciliation.*', 'enabled' => $user?->can('insurance.reconciliation.manage') ?? false],
            ['label' => 'NHIF Claim Report', 'icon' => 'chart-no-axes-combined', 'route' => 'insurance.claims.reports.nhif', 'active' => 'insurance.claims.reports.*', 'enabled' => $user?->can('insurance.reports.view') ?? false],
        ],
        'Utawala' => [
            ['label' => 'Watumishi Wote', 'icon' => 'users', 'route' => 'staff.index', 'enabled' => $user?->can('staff.view') ?? false],
            ['label' => 'Ongeza Mtumishi', 'icon' => 'user-plus', 'route' => 'staff.index', 'enabled' => $user?->can('staff.create') ?? false],
            ['label' => 'Ripoti ya Watumishi', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.staff', 'enabled' => ($user?->can('staff.export') || $user?->can('reports.view')) ?? false],
            ['label' => 'Ripoti ya Wagonjwa', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.patients', 'enabled' => ($user?->can('patients.export') || $user?->can('reports.view')) ?? false],
            ['label' => 'Ripoti ya Reception', 'icon' => 'receipt', 'route' => 'reports.reception', 'enabled' => ($user?->can('patients.export') || $user?->can('reports.view')) ?? false],
            ['label' => 'Triage Report', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.triage', 'enabled' => $user?->can('reports.view') ?? false],
            ['label' => 'OPD Report', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.opd', 'enabled' => $user?->can('reports.view') ?? false],
            ['label' => 'Diagnosis Report', 'icon' => 'brain', 'route' => 'reports.diagnoses', 'enabled' => $user?->can('reports.view') ?? false],
            ['label' => 'Referral Report', 'icon' => 'send', 'route' => 'reports.referrals', 'enabled' => $user?->can('reports.view') ?? false],
            ['label' => 'Laboratory Report', 'icon' => 'flask-conical', 'route' => 'reports.laboratory', 'enabled' => $user?->can('laboratory-reports.view') ?? false],
            ['label' => 'Pharmacy Report', 'icon' => 'pill', 'route' => 'reports.pharmacy', 'enabled' => $user?->can('pharmacy.reports.view') ?? false],
            ['label' => 'Observation Reports', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.observation.admissions', 'active' => 'reports.observation.*', 'enabled' => $user?->can('observation.reports.view') ?? false],
            ['label' => 'Dental Reports', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.dental.patients', 'active' => 'reports.dental.*', 'enabled' => $user?->can('dental.reports.view') ?? false],
            ['label' => 'Kituo', 'icon' => 'building-2', 'route' => 'settings.facility', 'enabled' => $user?->can('facility.view') ?? false],
            ['label' => 'Departments', 'icon' => 'network', 'route' => 'settings.departments.index', 'enabled' => $user?->can('departments.view') ?? false],
            ['label' => 'Vyeo', 'icon' => 'briefcase-medical', 'route' => 'settings.job-titles.index', 'enabled' => $user?->can('job-titles.view') ?? false],
            ['label' => 'Service Categories', 'icon' => 'folder-open', 'route' => 'settings.service-categories.index', 'enabled' => $user?->can('service-categories.view') ?? false],
            ['label' => 'Services', 'icon' => 'heart-pulse', 'route' => 'settings.services.index', 'enabled' => $user?->can('services.view') ?? false],
            ['label' => 'Lab Categories', 'icon' => 'folder-open', 'route' => 'settings.laboratory.categories', 'enabled' => $user?->can('laboratory.manage-test-categories') ?? false],
            ['label' => 'Specimens', 'icon' => 'test-tube', 'route' => 'settings.laboratory.specimens', 'enabled' => $user?->can('laboratory.manage-specimens') ?? false],
            ['label' => 'Lab Tests', 'icon' => 'flask-conical', 'route' => 'settings.laboratory.tests', 'enabled' => $user?->can('laboratory.manage-tests') ?? false],
            ['label' => 'Pharmacy Setup', 'icon' => 'pill', 'route' => 'settings.pharmacy.categories', 'active' => 'settings.pharmacy.*', 'enabled' => $user?->can('pharmacy.manage-medicine-categories') ?? false],
            ['label' => 'Observation Rooms', 'icon' => 'door-open', 'route' => 'settings.observation.rooms', 'active' => 'settings.observation.*', 'enabled' => $user?->can('observation.manage-rooms') ?? false],
            ['label' => 'Dental Findings', 'icon' => 'scan-face', 'route' => 'settings.dental.findings', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-odontogram') ?? false],
            ['label' => 'Dental Procedure Types', 'icon' => 'wrench', 'route' => 'settings.dental.procedure-types', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-settings') ?? false],
            ['label' => 'Dental Templates', 'icon' => 'file-signature', 'route' => 'settings.dental.procedure-templates', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-procedure-templates') ?? false],
            ['label' => 'Dental Rooms', 'icon' => 'door-open', 'route' => 'settings.dental.rooms', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-rooms') ?? false],
            ['label' => 'Dental Chairs', 'icon' => 'armchair', 'route' => 'settings.dental.chairs', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-chairs') ?? false],
            ['label' => 'Dental Preferences', 'icon' => 'settings', 'route' => 'settings.dental.preferences', 'active' => 'settings.dental.*', 'enabled' => $user?->can('dental.manage-settings') ?? false],
            ['label' => 'RCH Services', 'icon' => 'heart-handshake', 'route' => 'settings.services.index', 'active' => 'settings.services.*', 'enabled' => $user?->can('rch.manage-settings') ?? false, 'visible' => $user?->can('rch.manage-settings') ?? false],
            ['label' => 'Pregnancy Risk Factors', 'icon' => 'triangle-alert', 'route' => 'rch.settings.risk-factors', 'active' => 'rch.settings.risk-factors', 'enabled' => $user?->can('rch.manage-settings') ?? false, 'visible' => $user?->can('rch.manage-settings') ?? false],
            ['label' => 'Family Planning Methods', 'icon' => 'users-round', 'route' => 'rch.settings.family-planning-methods', 'active' => 'rch.settings.family-planning-methods', 'enabled' => $user?->can('rch.family-planning.manage-methods') ?? false, 'visible' => $user?->can('rch.family-planning.manage-methods') ?? false],
            ['label' => 'Vaccines', 'icon' => 'syringe', 'route' => 'rch.settings.vaccines', 'active' => 'rch.settings.vaccines', 'enabled' => $user?->can('rch.vaccines.manage') ?? false, 'visible' => $user?->can('rch.vaccines.manage') ?? false],
            ['label' => 'Immunization Schedules', 'icon' => 'calendar-sync', 'route' => 'rch.settings.immunization-schedules', 'active' => 'rch.settings.immunization-schedules', 'enabled' => $user?->can('rch.immunization.manage-schedules') ?? false, 'visible' => $user?->can('rch.immunization.manage-schedules') ?? false],
            ['label' => 'Growth Standards', 'icon' => 'chart-no-axes-combined', 'route' => 'rch.settings.growth-standards', 'active' => 'rch.settings.growth-standards', 'enabled' => $user?->can('rch.manage-settings') ?? false, 'visible' => $user?->can('rch.manage-settings') ?? false],
            ['label' => 'RCH Preferences', 'icon' => 'settings', 'route' => 'rch.settings.preferences', 'active' => 'rch.settings.preferences', 'enabled' => $user?->can('rch.manage-settings') ?? false, 'visible' => $user?->can('rch.manage-settings') ?? false],
            ['label' => 'RCH Report Settings', 'icon' => 'file-sliders', 'route' => 'rch.settings.report-settings', 'active' => 'rch.settings.report-settings', 'enabled' => $user?->can('rch.manage-settings') ?? false, 'visible' => $user?->can('rch.manage-settings') ?? false],
            ['label' => 'Billing Preferences', 'icon' => 'settings', 'route' => 'settings.billing.preferences', 'active' => 'settings.billing.*', 'enabled' => $user?->can('billing.manage-settings') ?? false],
            ['label' => 'Insurance Providers', 'icon' => 'shield-check', 'route' => 'settings.insurance.providers', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-providers') ?? false],
            ['label' => 'Insurance Schemes', 'icon' => 'badge-check', 'route' => 'settings.insurance.schemes', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-schemes') ?? false],
            ['label' => 'Benefit Packages', 'icon' => 'wallet-cards', 'route' => 'settings.insurance.benefit-packages', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-benefit-packages') ?? false],
            ['label' => 'Coverage Rules', 'icon' => 'clipboard-check', 'route' => 'settings.insurance.coverage-rules', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-coverage') ?? false],
            ['label' => 'Contract Prices', 'icon' => 'hand-coins', 'route' => 'settings.insurance.contract-prices', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-contract-prices') ?? false],
            ['label' => 'Claim Rules', 'icon' => 'settings', 'route' => 'settings.insurance.claim-rules', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-claim-rules') ?? false],
            ['label' => 'Insurance Preferences', 'icon' => 'settings', 'route' => 'settings.insurance.preferences', 'active' => 'settings.insurance.*', 'enabled' => $user?->can('insurance.manage-settings') ?? false],
            ['label' => 'Insurance Providers', 'icon' => 'shield-check', 'route' => 'settings.insurance-providers.index', 'enabled' => $user?->can('insurance-providers.view') ?? false],
            ['label' => 'Corporate Accounts', 'icon' => 'briefcase-business', 'route' => 'settings.corporate-accounts.index', 'enabled' => $user?->can('corporate-accounts.view') ?? false],
            ['label' => 'Roles', 'icon' => 'shield-check', 'route' => 'settings.roles.index', 'active' => 'settings.roles.*', 'enabled' => $user?->can('roles.view') ?? false],
            ['label' => 'Permissions', 'icon' => 'key-round', 'route' => 'settings.permissions.index', 'enabled' => $user?->can('permissions.view') ?? false],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
<meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) document.documentElement.classList.add('dark');
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    x-data="{ mobileSidebar: false, collapsed: localStorage.getItem('sidebar') === 'collapsed', toggleTheme() { const dark = document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', dark ? 'dark' : 'light') } }"
    class="min-h-screen bg-app-light font-sans text-slate-900 antialiased dark:bg-app-dark dark:text-slate-100"
>
    <div class="min-h-screen lg:flex">
        <aside :class="collapsed ? 'lg:w-20' : 'lg:w-72'" class="fixed inset-y-0 left-0 z-40 hidden border-r border-slate-200 bg-white transition-all dark:border-slate-700 dark:bg-card-dark lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-700">
                <x-facility-logo :facility="$currentFacility" class="h-10 w-10 shrink-0" />
                <div x-show="!collapsed" class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $displayFacilityName }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">Management System</p>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4">
                @foreach ($navGroups as $group => $items)
                    <div class="mb-5">
                        <p x-show="!collapsed" class="mb-2 px-3 text-xs font-semibold uppercase text-slate-400">{{ $group }}</p>
                        <div class="space-y-1">
                            @foreach ($items as $item)
                                @continue(array_key_exists('visible', $item) && ! $item['visible'])
                                @php($active = $item['enabled'] && request()->routeIs($item['active'] ?? $item['route']))
                                <a href="{{ $item['enabled'] ? route($item['route']) : '#' }}" title="{{ $item['enabled'] ? $item['label'] : $item['label'].' - Inakuja hivi karibuni' }}" class="group flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium {{ $active ? 'bg-primary text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }} {{ $item['enabled'] ? '' : 'cursor-not-allowed opacity-50' }}">
                                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5 shrink-0" />
                                    <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
            <div class="border-t border-slate-200 p-3 dark:border-slate-700">
                <button type="button" @click="collapsed = !collapsed; localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded')" class="mb-3 flex h-10 w-full items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    <x-lucide-panel-left-close x-show="!collapsed" class="h-5 w-5" />
                    <x-lucide-panel-left-open x-show="collapsed" class="h-5 w-5" />
                </button>
                <div class="flex items-center gap-3 rounded-md bg-slate-50 p-2 dark:bg-slate-800">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary-2 text-sm font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <div x-show="!collapsed" class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div x-show="mobileSidebar" x-cloak class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-slate-950/50" @click="mobileSidebar = false"></div>
            <aside class="relative flex h-full w-80 max-w-[88vw] flex-col bg-white dark:bg-card-dark">
                <div class="flex h-16 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-700">
                    <span class="font-semibold">{{ $displayFacilityName }}</span>
                    <button type="button" @click="mobileSidebar = false" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-x class="h-5 w-5" /></button>
                </div>
                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    @foreach ($navGroups as $group => $items)
                        <p class="mb-2 mt-3 px-3 text-xs font-semibold uppercase text-slate-400">{{ $group }}</p>
                        @foreach ($items as $item)
                            @continue(array_key_exists('visible', $item) && ! $item['visible'])
                            <a href="{{ $item['enabled'] ? route($item['route']) : '#' }}" class="flex h-10 items-center gap-3 rounded-md px-3 text-sm {{ $item['enabled'] ? 'text-slate-700 dark:text-slate-200' : 'cursor-not-allowed text-slate-400' }}">
                                <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </aside>
        </div>

        <div :class="collapsed ? 'lg:pl-20' : 'lg:pl-72'" class="min-h-screen flex-1 transition-all">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-700 dark:bg-card-dark/95">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                    <button type="button" @click="mobileSidebar = true" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden"><x-lucide-menu class="h-5 w-5" /></button>
                    <div class="hidden min-w-0 flex-1 items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900 md:flex">
                        <x-lucide-search class="mr-2 h-4 w-4" />
                        Tafuta kwenye mfumo
                    </div>
                    <button type="button" @click="toggleTheme()" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Badilisha mwonekano">
                        <x-lucide-sun class="h-5 w-5 dark:hidden" />
                        <x-lucide-moon class="hidden h-5 w-5 dark:block" />
                    </button>
                    <button type="button" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Taarifa"><x-lucide-bell class="h-5 w-5" /></button>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-md p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            <x-lucide-chevron-down class="h-4 w-4" />
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-64 rounded-md border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-card-dark">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-user-round class="h-4 w-4" /> Wasifu wangu</a>
                            <a href="{{ route('profile.password') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-shield-check class="h-4 w-4" /> Badilisha nenosiri</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-danger hover:bg-red-50 dark:hover:bg-red-950/30"><x-lucide-log-out class="h-4 w-4" /> Toka</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main class="px-4 py-6 sm:px-6">
                <x-page-header :title="$title" :description="$description">
                    {{ $actions ?? '' }}
                </x-page-header>
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-confirm-modal />
    <x-toaster-hub />
    @livewireScripts
</body>
</html>
