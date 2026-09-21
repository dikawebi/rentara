---
description: Kerjakan task Rentara berikutnya berdasarkan roadmap
agent: manager
---

Tentukan dan kerjakan task berikutnya berdasarkan roadmap, status project, dokumentasi approved, dan pekerjaan yang sudah selesai.

Ikuti `AGENTS.md` dan workflow engineering yang berlaku.

Tentukan sendiri:
- Task ID
- type
- risk
- acceptance criteria
- agent yang diperlukan
- kebutuhan Architect
- testing
- review
- QA
- documentation

Jangan meminta Owner mengambil routine implementation decision.

Jangan memperluas scope di luar task yang dipilih.

Jangan push remote atau deploy tanpa persetujuan Owner.

Jalankan workflow sampai salah satu status berikut:

- `READY_FOR_OWNER_REVIEW`
- `OWNER_DECISION_REQUIRED`
- `BLOCKED`

Laporkan hasil akhir secara ringkas kepada Owner.