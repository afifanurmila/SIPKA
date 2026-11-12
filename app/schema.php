<?php
declare(strict_types=1);

function buat_skema(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE bidang (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    nama      TEXT NOT NULL,
    singkatan TEXT NOT NULL
);

CREATE TABLE entitas (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    nama              TEXT NOT NULL,
    level             TEXT NOT NULL CHECK (level IN ('satker', 'unit')),
    induk_id          INTEGER REFERENCES entitas(id) ON DELETE SET NULL,
    kategori_anggaran TEXT NOT NULL DEFAULT 'RM',
    aktif             INTEGER NOT NULL DEFAULT 1,
    urutan            INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    nama          TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('admin', 'pengampu', 'entitas', 'pimpinan')),
    bidang_id     INTEGER REFERENCES bidang(id) ON DELETE SET NULL,
    entitas_id    INTEGER REFERENCES entitas(id) ON DELETE SET NULL,
    aktif         INTEGER NOT NULL DEFAULT 1,
    last_login    TEXT
);

CREATE TABLE tahun (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun   INTEGER NOT NULL UNIQUE,
    status  TEXT NOT NULL DEFAULT 'pengisian' CHECK (status IN ('pengisian', 'verifikasi', 'final')),
    catatan TEXT
);

CREATE TABLE pilar (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun_id INTEGER NOT NULL REFERENCES tahun(id) ON DELETE CASCADE,
    level    TEXT NOT NULL,
    nama     TEXT NOT NULL,
    urutan   INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE indikator (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun_id  INTEGER NOT NULL REFERENCES tahun(id) ON DELETE CASCADE,
    level     TEXT NOT NULL,
    pilar_id  INTEGER NOT NULL REFERENCES pilar(id) ON DELETE CASCADE,
    kode      TEXT,
    nama      TEXT NOT NULL,
    bobot     REAL NOT NULL,
    bidang_id INTEGER REFERENCES bidang(id) ON DELETE SET NULL,
    frekuensi TEXT NOT NULL DEFAULT 'tahunan',
    deskripsi TEXT,
    urutan    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE nilai (
    indikator_id INTEGER NOT NULL REFERENCES indikator(id) ON DELETE CASCADE,
    entitas_id   INTEGER NOT NULL REFERENCES entitas(id),
    sub_periode  INTEGER NOT NULL,
    nilai        REAL NOT NULL,
    PRIMARY KEY (indikator_id, entitas_id, sub_periode)
);

CREATE TABLE isian (
    indikator_id INTEGER NOT NULL REFERENCES indikator(id) ON DELETE CASCADE,
    entitas_id   INTEGER NOT NULL REFERENCES entitas(id),
    na           INTEGER NOT NULL DEFAULT 0,
    catatan      TEXT,
    bukti_url    TEXT,
    updated_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at   TEXT,
    PRIMARY KEY (indikator_id, entitas_id)
);

CREATE TABLE predikat (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun_id    INTEGER NOT NULL REFERENCES tahun(id) ON DELETE CASCADE,
    nama        TEXT NOT NULL,
    min_nilai   REAL NOT NULL,
    warna       TEXT NOT NULL DEFAULT '#64748b',
    jenis       TEXT NOT NULL DEFAULT 'pembinaan',
    konsekuensi TEXT
);

CREATE TABLE sanggahan (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun_id        INTEGER NOT NULL REFERENCES tahun(id) ON DELETE CASCADE,
    entitas_id      INTEGER NOT NULL REFERENCES entitas(id) ON DELETE CASCADE,
    indikator_id    INTEGER REFERENCES indikator(id) ON DELETE SET NULL,
    isi             TEXT NOT NULL,
    status          TEXT NOT NULL DEFAULT 'diajukan',
    tanggapan       TEXT,
    dibuat_oleh     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    dibuat_pada     TEXT,
    ditanggapi_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL,
    ditanggapi_pada TEXT
);

CREATE TABLE log_aktivitas (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    aksi    TEXT NOT NULL,
    detail  TEXT,
    waktu   TEXT NOT NULL
);

CREATE INDEX idx_indikator_tahun ON indikator (tahun_id, level);
CREATE INDEX idx_nilai_entitas ON nilai (entitas_id);
CREATE INDEX idx_sanggahan_tahun ON sanggahan (tahun_id, entitas_id);
SQL);
}
