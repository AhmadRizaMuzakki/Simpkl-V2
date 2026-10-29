CREATE TABLE IF NOT EXISTS users (
    id_user VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'PEMBINA', 'STAFF', 'ANGGOTA') NOT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    PRIMARY KEY (id_user),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peserta (
    id_peserta VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    asal_sekolah VARCHAR(150) DEFAULT NULL,
    jurusan VARCHAR(150) DEFAULT NULL,
    nomor_telepon VARCHAR(30) DEFAULT NULL,
    tanggal_mulai DATE DEFAULT NULL,
    tanggal_selesai DATE DEFAULT NULL,
    status_peserta ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    PRIMARY KEY (id_peserta),
    UNIQUE KEY uq_peserta_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absensi (
    id_absen VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    id_peserta VARCHAR(20) NOT NULL,
    jam_masuk TIME DEFAULT NULL,
    jam_pulang TIME DEFAULT NULL,
    status_kehadiran VARCHAR(30) DEFAULT 'Hadir',
    keterangan VARCHAR(255) DEFAULT NULL,
    agenda TEXT DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    disetujui TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_absen),
    UNIQUE KEY uq_absen_harian (id_peserta, tanggal),
    KEY idx_absensi_tanggal (tanggal),
    CONSTRAINT fk_absensi_peserta FOREIGN KEY (id_peserta) REFERENCES peserta (id_peserta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sekolah (
    id_sekolah VARCHAR(20) NOT NULL,
    nama_sekolah VARCHAR(200) NOT NULL,
    alamat VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id_sekolah),
    UNIQUE KEY uq_sekolah_nama (nama_sekolah)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS set_alat (
    id_kit VARCHAR(20) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    jenjang VARCHAR(20) NOT NULL,
    komponen TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'Dikembalikan',
    PRIMARY KEY (id_kit),
    UNIQUE KEY uq_set_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kit (
    id_kit VARCHAR(20) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    jumlah INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_kit),
    UNIQUE KEY uq_kit_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS set_addon (
    id_set VARCHAR(20) NOT NULL,
    id_kit VARCHAR(20) NOT NULL,
    jumlah INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id_set, id_kit),
    KEY idx_set_addon_kit (id_kit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS silabus (
    id_silabus VARCHAR(20) NOT NULL,
    materi VARCHAR(255) NOT NULL,
    jenjang VARCHAR(20) NOT NULL,
    semester VARCHAR(10) NOT NULL,
    tahun SMALLINT NOT NULL,
    PRIMARY KEY (id_silabus),
    KEY idx_silabus_level (jenjang, tahun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training (
    id_training VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    id_sekolah VARCHAR(20) DEFAULT NULL,
    nama_sekolah VARCHAR(200) NOT NULL,
    jenjang VARCHAR(20) NOT NULL,
    materi VARCHAR(255) NOT NULL,
    jumlah_siswa INT NOT NULL DEFAULT 0,
    pertemuan INT NOT NULL DEFAULT 1,
    catatan TEXT,
    PRIMARY KEY (id_training),
    KEY idx_training_tanggal (tanggal),
    KEY idx_training_sekolah (id_sekolah),
    CONSTRAINT fk_training_sekolah FOREIGN KEY (id_sekolah) REFERENCES sekolah (id_sekolah)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notulen (
    id_notulen VARCHAR(20) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    cabang VARCHAR(150) DEFAULT NULL,
    pekan VARCHAR(150) DEFAULT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    id_user VARCHAR(20) NOT NULL,
    nama_notulen VARCHAR(150) NOT NULL,
    PRIMARY KEY (id_notulen),
    KEY idx_notulen_periode (tanggal_mulai),
    KEY idx_notulen_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notulen_baris (
    id_baris VARCHAR(20) NOT NULL,
    id_notulen VARCHAR(20) NOT NULL,
    hari VARCHAR(10) NOT NULL,
    jenis VARCHAR(20) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    isi TEXT NOT NULL,
    urutan SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_baris),
    KEY idx_notulen_baris (id_notulen, hari, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_peserta (
    id VARCHAR(20) NOT NULL,
    id_training VARCHAR(20) NOT NULL,
    id_peserta VARCHAR(20) NOT NULL,
    peran VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tp_training (id_training),
    KEY idx_tp_peserta (id_peserta),
    CONSTRAINT fk_tp_training FOREIGN KEY (id_training) REFERENCES training (id_training),
    CONSTRAINT fk_tp_peserta FOREIGN KEY (id_peserta) REFERENCES peserta (id_peserta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
