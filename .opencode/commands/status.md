---
description: Tampilkan status development Rentara
agent: manager
---

Lakukan pemeriksaan read-only terhadap status project Rentara.

Jangan mengubah file.
Jangan melakukan commit.
Jangan melakukan implementasi.

Periksa:
- Git status;
- task terakhir;
- task yang sudah selesai;
- task aktif jika ada;
- test/build status yang diketahui;
- known blockers;
- known technical debt;
- roadmap progress;
- dokumentasi yang relevan.

Berikan laporan ringkas dengan format:

PROJECT STATUS

LAST COMPLETED TASK

CURRENT TASK

GIT STATUS

TEST STATUS

COMPLETED

IN PROGRESS

BLOCKED

NEXT RECOMMENDED TASK

OWNER DECISION REQUIRED

Jika tidak ada Owner Decision yang diperlukan, tulis:

`OWNER DECISION REQUIRED: NONE`