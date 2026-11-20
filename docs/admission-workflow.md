# Admission and bed rest workflow

## Architecture audit before implementation

OPD already records `admitted_bed_rest` or `observation` on ClinicalEncounter. Partial and final completion create a BED PatientQueue through WorkflowService. BED is an existing department enabled by a migration. VisitClosureService recognizes admission decisions and active ObservationAdmission rows as closure blockers.

The operational gap is that no screen calls ObservationAdmissionService::admit: the observation queue lists only existing admissions. Doctor decisions therefore create queues without a chart, bed assignment or usable nursing entry point. AdmissionShow supports transfer but not initial assignment. OPD availability blocks admission patients, even with an open partial consultation.

Existing tables are observation_admissions, observation_rooms, beds, bed_assignments, bed_reservations, nursing_observations, observation_orders, observation_clinical_reviews, observation_discharges, medication_administrations, iv_fluid_administrations, nursing_tasks and nursing_handovers. No replacement admission or prescribing architecture is needed. ObservationAdmissionType already includes hourly_observation and short_stay; these can represent Observation / Bed Rest and Admission respectively. Assignment history and current bed/room pointers already exist. Beds have available, reserved, occupied, cleaning, blocked and out_of_service states.

BedManagementService locks the bed on assignment, but does not validate room activity, admission activity or duplicate assignment to the patient. Cleaning actions can accidentally make occupied beds available. Cancellation does not release the bed. Discharge completes pending observation orders without evidence and directly marks the visit discharged, bypassing downstream work and debt. Existing discharge release intentionally requires cleaning before availability.

Nursing vitals, IV, intake/output, bedside records and reviews already exist. AdmissionShow does not display structured PrescriptionItem directions, encounter procedures or laboratory results. The observation queue search has an ungrouped OR that can escape the facility constraint.

ObservationBillingService can add a configured Service as a normal InvoiceItem using InvoiceService and ServicePricingService. Hourly/daily bed rate helpers exist but are not invoiced; no automated recurring charging implementation is present. The admission service currently blocks cash admission on the entire invoice balance. Laboratory, pharmacy and procedures have their own financial clearance, selective PaymentAllocation and inventory workflows, which must remain authoritative.

Existing observation permissions cover admit, assign-bed, clinical-review, discharge, authorize-discharge and nursing actions; cancellation needs a narrow permission. Doctor and nurse role grants need initial bed assignment access. ActivityLog supports old_values and new_values for contextual audit data.
