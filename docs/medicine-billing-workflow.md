# Medicine billing and pharmacy release

Medicine charges use the facility's existing **upfront prescribed quantity** policy.
Completing a consultation creates one invoice item per prescription item using the
full prescribed quantity and snapshots its unit price and payer split. Dispensing
transactions never replace that invoice quantity with the latest dispensed amount.

If only part of the prescription is supplied, the original charge remains intact
while the prescription stays active. When an unfilled remainder becomes terminal
(cancelled, declined, unavailable, or supplied elsewhere), the linked invoice item
is reduced to the cumulative quantity actually dispensed. Any paid patient amount
above the adjusted charge enters the existing refund/credit workflow; financial
records are not deleted.

Pharmacy release is calculated only from active invoice items linked to the
prescription. It requires a zero patient-share balance and any required insurance
authorization. Unrelated visit charges do not block medicine release, although
they can continue to block final visit closure.
