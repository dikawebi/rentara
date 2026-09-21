---
description: Implementasikan feature Rentara melalui engineering workflow
agent: manager
---

Permintaan feature dari Owner:

$ARGUMENTS

Analisis permintaan tersebut terhadap:
- `AGENTS.md`;
- approved product documentation;
- approved architecture;
- existing implementation;
- roadmap dan scope Rentara.

Buat Task ID yang sesuai.

Tentukan type, risk, scope, acceptance criteria, dan agent yang diperlukan.

Gunakan Architect apabila memenuhi kriteria architecture review dalam `AGENTS.md`.

Delegasikan implementasi kepada Engineer yang sesuai.

Setelah implementasi:
Engineer → Reviewer → QA → Documentation bila diperlukan.

Tangani correction loop sesuai `AGENTS.md`.

Jangan meminta Owner mengambil routine implementation decisions.

Jangan push remote.
Jangan deploy.

Berhenti hanya pada:

- `READY_FOR_OWNER_REVIEW`
- `OWNER_DECISION_REQUIRED`
- `BLOCKED`

Laporkan hasil akhir secara ringkas kepada Owner.