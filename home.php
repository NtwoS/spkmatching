<?php
session_start();

// Membatasi halaman sebelum login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('Anda belum login');
            document.location.href = 'login.php';
        </script>";
    exit;
}

include 'layout/header.php';
?>

<!-- Custom CSS for Home -->
<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Inter', sans-serif;
    }

    .hero-section {
        background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
        color: white;
        padding: 4rem 2rem;
        border-radius: 20px;
        margin-bottom: 3rem;
        box-shadow: 0 10px 30px rgba(67, 97, 238, 0.2);
        position: relative;
        overflow: hidden;
    }

    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .hero-title {
        font-weight: 800;
        font-size: 2.5rem;
        margin-bottom: 1rem;
        letter-spacing: -0.5px;
    }

    .hero-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 600px;
        line-height: 1.6;
    }

    .hover-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08) !important;
    }

    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .bg-soft-primary {
        background-color: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }

    .bg-soft-success {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .section-title {
        font-weight: 700;
        color: #2b3452;
        position: relative;
        padding-bottom: 0.75rem;
        margin-bottom: 2rem;
    }

    .section-title::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 60px;
        height: 4px;
        background-color: #4361ee;
        border-radius: 2px;
    }

    /* Table Styling */
    .custom-table-container {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
        padding: 1rem;
    }

    .table td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        line-height: 1.6;
    }
    
    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-factor {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .badge-core {
        background-color: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }

    .badge-secondary {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .question-text {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.75rem;
    }
    
    .options-list p {
        margin-bottom: 0.25rem;
        color: #64748b;
    }
</style>

<div class="container mt-4 mb-5">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-white text-primary mb-3 px-3 py-2 rounded-pill fw-semibold">Dashboard SPK</span>
                <h1 class="hero-title">Sistem Pendukung Keputusan Profile Matching</h1>
                <p class="hero-subtitle">Implementasi Aplikasi Rekomendasi Lowongan PKL SMK Berbasis Profile Matching untuk penempatan siswa yang optimal.</p>
            </div>
            <div class="col-lg-4 d-none d-lg-flex justify-content-end">
                <i class="fas fa-chart-line fa-6x" style="opacity: 0.2;"></i>
            </div>
        </div>
    </div>

    <!-- Latar Belakang Section -->
    <h3 class="section-title">Latar Belakang</h3>
    <div class="card border-0 shadow-sm rounded-4 mb-5 hover-card">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-start">
                <div class="me-4 d-none d-md-block">
                    <i class="fas fa-info-circle text-primary" style="font-size: 2.5rem;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-3 text-dark d-md-none"><i class="fas fa-info-circle text-primary me-2"></i>Informasi Umum</h5>
                    <p class="mb-3 text-secondary" style="line-height: 1.8; text-align: justify;">
                        SMK (Sekolah Menengah Kejuruan) merupakan jenjang pendidikan vokasi yang
                        bertujuan mempersiapkan siswa untuk siap bekerja di dunia industri. Salah satu komponen
                        penting dalam kurikulum SMK adalah Praktik Kerja Lapangan (PKL), yang memberikan pengalaman
                        langsung kepada siswa di lingkungan kerja nyata. Namun, seringkali terjadi ketidaksesuaian
                        antara kompetensi siswa dengan kebutuhan industri, sehingga penempatan PKL kurang optimal.
                    </p>
                    <div class="alert bg-soft-primary border-0 text-dark fw-medium mb-0 mt-4 p-3 rounded-3" style="line-height: 1.6;">
                        Oleh karena itu, diperlukan sebuah Sistem Pendukung Keputusan (SPK) berbasis
                        Profile Matching untuk memberikan rekomendasi lowongan PKL yang sesuai dengan profil siswa.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kriteria Section -->
    <h3 class="section-title">Kriteria Penilaian</h3>
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 hover-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-box bg-soft-primary text-primary me-3">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 class="fw-bold mb-0 text-dark">Core Factor (60%)</h4>
                    </div>
                    <p class="text-secondary mb-0" style="line-height: 1.7;">
                        Soal yang memiliki pertanyaan tentang kompetensi inti siswa
                        yang diukur melalui soal-soal yang paling menentukan terkait kebutuhan utama perusahaan.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 hover-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-box bg-soft-success text-success me-3">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h4 class="fw-bold mb-0 text-dark">Secondary Factor (40%)</h4>
                    </div>
                    <p class="text-secondary mb-0" style="line-height: 1.7;">
                        Soal yang memiliki pertanyaan tentang kompetensi pendukung
                        siswa melalui soal-soal dengan relevansi sedang terhadap operasional perusahaan.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contoh Soal Section -->
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h3 class="section-title mb-0">Contoh Persyaratan Penilaian</h3>
        <span class="text-muted small">Berdasarkan hasil nilai akademik</span>
    </div>
    
    <div class="custom-table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col" width="80%">Deskripsi Soal & Pilihan</th>
                        <th scope="col" class="text-center" width="20%">Faktor Penilaian</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <p class="question-text">1. Sebagai desainer di BAZNAS Kabupaten Pinrang, Anda diminta membuat poster digital untuk kampanye zakat. Apa yang paling penting diperhatikan?</p>
                            <div class="options-list ps-3">
                                <p>A. Penggunaan font yang unik meskipun sulit dibaca (1)</p>
                                <p class="text-dark fw-medium">B. Kombinasi warna yang menarik dan sesuai dengan branding BAZNAS (4)</p>
                                <p>C. Memasukkan banyak gambar tanpa hierarchy visual (2)</p>
                                <p>D. Desain monoton dengan satu warna saja (3)</p>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge-factor badge-core d-inline-flex flex-column align-items-center">
                                <i class="fas fa-star text-primary mb-1"></i>
                                Core
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p class="question-text">2. Berapa jarak lokasi rumah siswa dengan kantor BAZNAS Kabupaten Pinrang?</p>
                            <div class="options-list ps-3">
                                <p class="text-dark fw-medium">A. 5–10 km (4)</p>
                                <p>B. 10–20 km (3)</p>
                                <p>C. Kurang dari 5 km (2)</p>
                                <p>D. Lebih dari 20 km (1)</p>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge-factor badge-secondary d-inline-flex flex-column align-items-center">
                                <i class="fas fa-layer-group text-success mb-1"></i>
                                Secondary
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'layout/footer.php';
?>