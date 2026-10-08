# IRCUB Entity Relationship Diagram

Logical ERD for the PostgreSQL schema used by the IRCUB POC.  
Rendered with Mermaid (GitHub / VS Code / most Markdown previews).

Framework tables (`cache`, `jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens`) are omitted for clarity.

---

## Overview (core domain)

```mermaid
erDiagram
    users ||--o{ role_user : has
    roles ||--o{ role_user : assigned
    roles ||--o{ permission_role : grants
    permissions ||--o{ permission_role : granted_by

    users ||--o{ payers : creates
    payers ||--o{ payer_obligations : has
    payers ||--o{ water_accounts : owns
    payers ||--o{ assessments : assessed
    payers ||--o{ payments : pays
    payers ||--o{ water_bills : billed
    payers ||--o{ channel_payments : initiates

    assessments ||--o{ payments : settled_by
    water_accounts ||--o{ meter_readings : read
    billing_cycles ||--o{ water_bills : produces
    water_accounts ||--o{ water_bills : billed_on
    meter_readings ||--o| water_bills : drives
    water_bills ||--o{ payments : paid_via

    exchange_rates ||--o{ channel_payments : priced_with
    channel_payments }o--o| payments : posts
    channel_payments }o--o| assessments : for
    channel_payments }o--o| water_bills : for

    reconciliation_runs ||--o{ reconciliation_items : contains

    fmis_journal_batches ||--o{ fmis_journal_lines : contains
    payments ||--o| fmis_journal_lines : posted_as
    fmis_journal_lines }o--|| payments : references

    users ||--o{ audit_logs : performs
    users ||--o{ fmis_journal_batches : creates
    users ||--o{ reconciliation_runs : creates
```

---

## 1. Identity & access

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string phone
        string password
        boolean is_active
        timestamp email_verified_at
    }

    roles {
        bigint id PK
        string name
        string slug UK
        string description
        boolean is_system
        boolean is_active
    }

    permissions {
        bigint id PK
        string name
        string slug UK
        string module
    }

    role_user {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
    }

    permission_role {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
    }

    users ||--o{ role_user : ""
    roles ||--o{ role_user : ""
    roles ||--o{ permission_role : ""
    permissions ||--o{ permission_role : ""
```

---

## 2. Registry, revenue & audit

```mermaid
erDiagram
    payers {
        bigint id PK
        string payer_type
        string tin UK
        string full_name
        string national_id
        string phone
        string email
        string status
        boolean duplicate_flagged
        bigint created_by FK
    }

    payer_obligations {
        bigint id PK
        bigint payer_id FK
        string revenue_code
        string status
    }

    revenue_types {
        bigint id PK
        string revenue_code UK
        string name
        string category
        string gl_code
        decimal default_rate
        boolean is_active
    }

    assessments {
        bigint id PK
        bigint payer_id FK
        string revenue_code
        string control_number UK
        decimal amount_due
        decimal amount_paid
        decimal penalty_amount
        date due_date
        string status
        string period
        bigint created_by FK
    }

    payments {
        bigint id PK
        bigint payer_id FK
        bigint assessment_id FK
        bigint water_bill_id FK
        string revenue_code
        decimal amount
        string currency
        string channel
        string external_ref UK
        timestamp paid_at
        string status
        string fmis_status
        string fmis_reference
        bigint fmis_journal_line_id FK
        bigint created_by FK
    }

    audit_logs {
        bigint id PK
        string entity_type
        bigint entity_id
        string action
        bigint user_id FK
        json before
        json after
        string ip_address
        string prev_hash
        string entry_hash
        timestamp created_at
    }

    payers ||--o{ payer_obligations : ""
    payers ||--o{ assessments : ""
    payers ||--o{ payments : ""
    assessments ||--o{ payments : ""
```

Logical (non-FK) links: `assessments.revenue_code` / `payments.revenue_code` → `revenue_types.revenue_code`.

---

## 3. Water billing

```mermaid
erDiagram
    water_accounts {
        bigint id PK
        bigint payer_id FK
        string account_number UK
        string meter_number
        string customer_category
        string status
    }

    water_tariffs {
        bigint id PK
        string customer_category
        int tier_from
        int tier_to
        decimal rate_per_unit
        decimal fixed_charge
        boolean is_active
    }

    meter_readings {
        bigint id PK
        bigint water_account_id FK
        date reading_date
        decimal reading_value
        string reading_type
        boolean rollover_flag
        boolean replacement_flag
        bigint created_by FK
    }

    billing_cycles {
        bigint id PK
        string period UK
        string status
        int bills_created
        int exceptions_count
        bigint created_by FK
    }

    water_bills {
        bigint id PK
        bigint billing_cycle_id FK
        bigint water_account_id FK
        bigint payer_id FK
        bigint meter_reading_id FK
        string bill_number UK
        string period
        decimal consumption
        decimal total_due
        decimal amount_paid
        string status
        boolean abnormal_flag
        bigint created_by FK
    }

    payers ||--o{ water_accounts : ""
    water_accounts ||--o{ meter_readings : ""
    water_accounts ||--o{ water_bills : ""
    billing_cycles ||--o{ water_bills : ""
    meter_readings ||--o| water_bills : ""
    water_bills ||--o{ payments : ""
```

`water_tariffs` is keyed by `customer_category` (matched to `water_accounts.customer_category` at billing time).

---

## 4. Payment channels & reconciliation

```mermaid
erDiagram
    exchange_rates {
        bigint id PK
        string base_currency
        string quote_currency
        decimal rate
        timestamp fetched_at
        string provider
    }

    channel_payments {
        bigint id PK
        bigint payer_id FK
        bigint assessment_id FK
        bigint water_bill_id FK
        bigint payment_id FK
        bigint exchange_rate_id FK
        string provider
        string channel_ref
        string status
        decimal amount
        string currency
        decimal amount_usd
        int retry_count
        bigint created_by FK
    }

    reconciliation_runs {
        bigint id PK
        date statement_date
        string channel
        string status
        int matched_count
        int variance_count
        bigint created_by FK
    }

    reconciliation_items {
        bigint id PK
        bigint reconciliation_run_id FK
        string status
        string ircub_ref
        string statement_ref
        decimal ircub_amount
        decimal statement_amount
    }

    supervisor_notifications {
        bigint id PK
        string type
        string severity
        string message
        bigint payload_id
        timestamp read_at
    }

    exchange_rates ||--o{ channel_payments : ""
    payers ||--o{ channel_payments : ""
    channel_payments }o--o| payments : ""
    reconciliation_runs ||--o{ reconciliation_items : ""
```

---

## 5. FMIS posting & dashboard

```mermaid
erDiagram
    gl_mappings {
        bigint id PK
        string revenue_code UK
        string gl_code
        string description
        boolean is_active
    }

    fmis_journal_batches {
        bigint id PK
        string batch_number UK
        date journal_date
        string status
        string fmis_reference UK
        int line_count
        decimal total_amount
        bigint created_by FK
    }

    fmis_journal_lines {
        bigint id PK
        bigint fmis_journal_batch_id FK
        bigint payment_id FK
        bigint source_payment_id
        string revenue_code
        string gl_code
        decimal amount
        string fmis_line_ref
        timestamp reversed_at
    }

    revenue_targets {
        bigint id PK
        string period_type
        string period_key
        string revenue_code
        decimal target_amount
        boolean is_active
    }

    dashboard_daily_aggregates {
        bigint id PK
        date stat_date
        string revenue_code
        string channel
        int payment_count
        decimal collected_amount
        int reversal_count
        decimal reversed_amount
    }

    fmis_journal_batches ||--o{ fmis_journal_lines : ""
    payments ||--o| fmis_journal_lines : ""
```

Traceability path: **Payment → FMIS journal line → FMIS journal batch → mock FMIS reference**.

`dashboard_daily_aggregates` is a **pre-aggregated** table rebuilt from `payments` (not a live FK graph).

---

## Notes

| Topic | Detail |
|---|---|
| Engine | PostgreSQL 16 |
| Auth tokens | Laravel Sanctum (`personal_access_tokens`) |
| Soft constraints | Some links use business keys (`revenue_code`) rather than FKs for flexibility |
| Seed data | `php artisan migrate:fresh --seed` (includes dashboard history) |
| Migrations | `backend/database/migrations/` |
