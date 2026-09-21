---
description: Analisis dan perbaiki bug Rentara melalui engineering workflow
agent: manager
---

Bug yang dilaporkan Owner:

$ARGUMENTS

Reproduce dan analisis bug sebelum melakukan perubahan.

Buat Task ID `RENTARA-BUG-NNN` yang sesuai.

Tentukan:
- severity;
- risk;
- root cause;
- affected scope;
- regression risk.

Gunakan Architect hanya jika bug menunjukkan masalah architecture atau security boundary yang material.

Delegasikan correction kepada Engineer yang sesuai.

Jangan memperbaiki symptom saja apabila root cause dapat diidentifikasi secara aman.

Setelah correction:
Engineer → Reviewer → QA.

Tambahkan regression test apabila applicable.

Update documentation hanya jika behavior atau dokumentasi memang berubah.

Ikuti correction-loop limit dalam `AGENTS.md`.

Jangan push remote.
Jangan deploy.

Berhenti pada:

- `READY_FOR_OWNER_REVIEW`
- `OWNER_DECISION_REQUIRED`
- `BLOCKED`

Laporkan root cause dan hasil correction secara ringkas.