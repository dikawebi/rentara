---
description: Setujui task yang sudah READY_FOR_OWNER_REVIEW dan buat commit
agent: manager
---

Approve task aktif yang saat ini berstatus `READY_FOR_OWNER_REVIEW`.

Sebelum commit, pastikan:
- Reviewer sudah `APPROVED`;
- QA sudah `PASS` apabila QA diperlukan;
- documentation sudah disinkronkan apabila diperlukan;
- tidak ada secret atau `.env` yang ikut commit;
- perubahan hanya mencakup scope task yang disetujui.

Buat local Git commit dengan commit message yang sesuai dengan task.

Jangan push remote.
Jangan deploy.
Jangan otomatis memulai task berikutnya.

Setelah selesai laporkan:
- Task ID
- commit hash
- commit message
- branch
- working tree status
- rekomendasi task berikutnya

Jika task belum memenuhi syarat approval, jangan commit dan jelaskan alasannya.