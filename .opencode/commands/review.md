---
description: Jalankan independent review terhadap perubahan saat ini
agent: manager
---

Lakukan review terhadap perubahan implementation yang saat ini belum disetujui.

Manager harus mendelegasikan independent review kepada `reviewer`.

Reviewer tidak boleh memperbaiki implementation yang sedang direview.

Review minimal mencakup:
- requirement compliance;
- correctness;
- security;
- authentication;
- authorization;
- workspace isolation;
- data integrity;
- validation;
- regression risk;
- architecture compliance;
- test coverage;
- unnecessary complexity.

Gunakan severity sesuai `AGENTS.md`.

Return:

REVIEWED TASK

FINDINGS

CRITICAL

HIGH

MEDIUM

LOW

TEST OBSERVATIONS

RECOMMENDATION

Final review result harus:

`APPROVED`

atau

`CHANGES_REQUIRED`

Command ini bersifat review-only.

Jangan melakukan correction otomatis.
Jangan commit.
Jangan push.
Jangan deploy.