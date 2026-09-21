---
description: Audit kesiapan Rentara untuk release tanpa melakukan deployment
agent: manager
---

Lakukan Release Readiness Review untuk Rentara.

Command ini TIDAK memberikan izin deployment.

Periksa:
- task yang termasuk release;
- acceptance criteria;
- Reviewer status;
- QA status;
- automated tests;
- build;
- database migrations;
- migration safety;
- environment configuration;
- secrets;
- `.env`;
- production debug configuration;
- dependency state;
- documentation;
- known limitations;
- unresolved CRITICAL/HIGH findings;
- deployment prerequisites;
- rollback considerations.

Delegasikan pemeriksaan kepada Architect, Reviewer, QA, atau Documentation apabila diperlukan.

Jangan:
- deploy;
- push;
- menjalankan production migration;
- mengubah production environment;
- membuat destructive operation.

Berikan:

RELEASE

READINESS STATUS

INCLUDED TASKS

TESTS

BUILD

DATABASE

SECURITY

CONFIGURATION

DOCUMENTATION

KNOWN LIMITATIONS

BLOCKERS

DEPLOYMENT PREREQUISITES

OWNER DECISIONS

Final status:

`READY_FOR_RELEASE_APPROVAL`

atau

`NOT_READY`

atau

`OWNER_DECISION_REQUIRED`

`READY_FOR_RELEASE_APPROVAL` bukan authorization untuk melakukan deployment.