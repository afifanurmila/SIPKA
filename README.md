# SIPKA — Sistem Informasi Penilaian Kinerja Anggaran & Reward-Punishment

Sistem informasi berbasis web untuk penilaian kinerja anggaran terpadu, tata kelola, dan penetapan reward & punishment bagi **Satuan Kerja (Satker DIPA)** dan **Unit Organisasi (Unor)**.

---

## 🚀 Cara Menjalankan di Localhost

Server lokal saat ini **sudah aktif** dan dapat langsung diakses melalui peramban web:

👉 **URL:** [http://127.0.0.1:8000](http://127.0.0.1:8000) atau [http://localhost:8000](http://localhost:8000)

Jika ingin menjalankan ulang di kemudian hari:
```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

---

## 👥 Akun Login Demo (Berdasarkan Peran)

| Username | Password | Peran (*Role*) | Unit / Afiliasi | Wewenang Utama |
|---|---|---|---|---|
| `admin` | `admin123` | **Super Admin** | Biro Perencanaan dan Keuangan | Pengaturan tahun, bobot, indikator dinamis, buka/kunci periode, verifikasi sanggahan, ekspor rekap |
| `inspektorat` | `pengampu123` | **Pengampu Indikator** | Inspektorat | Mengisi & impor nilai TL Audit, Manajemen Risiko, SAKIP, Indeks Pencegahan Korupsi |
| `biroumum` | `pengampu123` | **Pengampu Indikator** | Biro Umum (BMN & Barjas) | Mengisi & impor nilai RUP, Realisasi Pengadaan, Indeks Pengelolaan Aset (BMN) |
| `birohos` | `pengampu123` | **Pengampu Indikator** | Biro HOS (SDM & Layanan) | Mengisi nilai Survei Kepuasan Masyarakat (SKM) & Bangkom SDM 20 JP |
| `lanjakarta` | `satker123` | **Entitas (Satker)** | LAN Jakarta | Melihat Rapor Kinerja Satker, analisis radar, prioritas perbaikan, mengajukan sanggahan |
| `poltekbdg` | `satker123` | **Entitas (Satker)** | Politeknik STIA LAN Bandung | Melihat Rapor Kinerja Satker Politeknik, cetak rapor |
| `pusdatin` | `satker123` | **Entitas (Unit Kerja)** | Pusat Data dan Informasi | Melihat Rapor Kinerja Unit Kerja internal kantor pusat |
| `pimpinan` | `pimpinan123` | **Pimpinan** | Sekretaris Utama / Pimpinan | Dashboard eksekutif, leaderboard, distribusi predikat reward & punishment (read-only) |

---

## 🌟 Fitur-Fitur Utama yang Tersedia

1. **Dashboard & Leaderboard Peringkat**
   - Pemeringkatan otomatis secara *real-time* dengan visualisasi peringkat podium emas/perak/perunggu.
   - Pilihan filter periode tahun dan level penilaian (**Satuan Kerja** vs **Unit Kerja / Unor**).
   - Grafik batang interaktif capaian nilai total serta diagram donat sebaran predikat.
2. **Rapor Kinerja Individual per Satker / Unit**
   - **Radar Chart 360°**: Membandingkan profil capaian satker terhadap rata-rata institusi secara visual.
   - **Grafik Tren Tahunan**: Memantau perkembangan nilai total dari tahun ke tahun.
   - **Prioritas Perbaikan (*Action Items*)**: Rekomendasi otomatis 3 indikator dengan daya ungkit (*leverage*) terbesar untuk mendongkrak nilai total.
   - **Siap Cetak / Ekspor PDF**: Tampilan ramah cetak (`Ctrl + P` / tombol Cetak).
3. **Dynamic Scoring & Normalisasi Bobot**
   - Perhitungan rata-rata otomatis untuk indikator berkala (contoh: 12 bulan IKPA).
   - Penanganan fleksibel untuk indikator **Tidak Berlaku (N/A)**: bobot dibagi ulang secara proporsional (misal: SAKIP pada LAN Jakarta atau SKM pada direktorat yang tidak memiliki layanan publik).
4. **Modul Pengisian Nilai Kolaboratif**
   - *Grid form* entri nilai per baris dengan perhitungan rata-rata langsung saat diketik.
   - Fasilitas **Unduh Template CSV** dan **Impor Berkas CSV** per indikator.
   - Tautan bukti dukung dokumen (Google Drive/Cloud) dan catatan per satuan kerja.
5. **Mekanisme Sanggahan & Verifikasi Nilai**
   - Satker dapat mengajukan klarifikasi nilai pada masa verifikasi sebelum status dikunci.
   - Pengampu dan Admin dapat memberikan tanggapan resmi (Diterima / Ditolak / Dalam Peninjauan).
6. **Konfigurasi Fleksibel (Admin Biro Perencanaan dan Keuangan)**
   - Manajemen pembukaan tahun anggaran baru (bisa menyalin indikator dari tahun sebelumnya).
   - Perubahan status siklus: **Pengisian Nilai $\rightarrow$ Verifikasi & Sanggahan $\rightarrow$ Final (Terkunci)**.
   - Tambah/edit pilar dan indikator, persentase bobot, unit pengampu, serta frekuensi penilaian.
   - Penyesuaian ambang batas predikat (**Sangat Baik, Baik, Cukup, Kurang**) serta konsekuensi *Reward & Punishment*.
   - Manajemen Satker/Unit Kerja dan Pengguna.
7. **Ekspor Rekapitulasi Data**
   - Ekspor tabel rekap lengkap ke CSV/Excel dengan format rapi dan berstandar UTF-8 BOM.
8. **Audit Trail (Log Aktivitas)**
   - Pencatatan seluruh riwayat login, pengisian nilai, perubahan konfigurasi, dan penanganan sanggahan.
