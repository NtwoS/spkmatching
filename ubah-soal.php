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

$id_soal = (int) $_GET['id_soal'];
$data_perusahaan = select("SELECT * FROM perusahaan ORDER BY id_perusahaan DESC");
$soal = select("SELECT * FROM soal WHERE id_soal = $id_soal")[0];

// Cek tombol ubah ditekan
if (isset($_POST['ubah_soal'])) {
    if (ubah_soal($_POST) > 0) {
        echo "<script>
                alert('Data Berhasil Diubah');
                document.location.href = 'data-soal.php';
                </script>";
    } else {
        echo "<script>
                alert('Data Gagal Diubah');
                document.location.href = 'ubah-soal.php?id_soal=$id_soal';
                </script>";
    }
}
?>

<style>
    body { background-color: #f8fafc; }
    
    .form-card {
        background: #fff;
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-top: 2rem;
        margin-bottom: 4rem;
    }

    .card-header-gradient {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
        padding: 2.5rem 2rem;
        color: white;
    }

    .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Modern Radio Group (Factor) */
    .factor-group {
        display: flex;
        gap: 15px;
    }
    .factor-item {
        flex: 1;
        position: relative;
    }
    .factor-item input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }
    .factor-label {
        display: block;
        padding: 12px 20px;
        background: #f1f5f9;
        border: 2px solid transparent;
        border-radius: 12px;
        text-align: center;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
    }
    .factor-item input:checked + .factor-label {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #2563eb;
    }

    /* Value Chips (1-4) */
    .value-chips {
        display: flex;
        gap: 10px;
    }
    .chip-item {
        position: relative;
    }
    .chip-item input {
        position: absolute;
        opacity: 0;
    }
    .chip-label {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: 2px solid transparent;
        border-radius: 10px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .chip-item input:checked + .chip-label {
        background: #3b82f6;
        color: #fff;
        border-color: #2563eb;
        transform: scale(1.05);
    }

    /* Input Styling */
    .form-control, .form-select {
        border-radius: 12px;
        padding: 12px 16px;
        border: 1.5px solid #e2e8f0;
        font-size: 0.95rem;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .btn-gradient-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }
    .btn-gradient-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        color: white;
    }
    .btn-gradient-primary:disabled {
        background: #cbd5e1;
        box-shadow: none;
        cursor: not-allowed;
    }

    .btn-outline-secondary {
        border: 1.5px solid #e2e8f0;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        color: #64748b;
        background: white;
    }
    .btn-outline-secondary:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #475569;
    }

    .quiz-letter {
        width: 32px;
        height: 32px;
        background: #4361ee;
        color: white;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 12px;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            
            <div class="form-card">
                <div class="card-header-gradient">
                    <h2 class="mb-1 fw-bold">Ubah Soal</h2>
                    <p class="mb-0 opacity-75">Perbarui informasi dan nilai kuesioner</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <form action="" method="post">
                        <input type="hidden" name="id_soal" value="<?= $soal['id_soal'] ?>">
                        
                        <!-- Section 1: General Info -->
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i> Informasi Dasar
                        </div>
                        
                        <div class="mb-4">
                            <label for="perusahaan" class="form-label fw-semibold">Pilih Perusahaan</label>
                            <select class="form-select" id="perusahaan" name="perusahaan" required>
                                <option value="" disabled>-- Hubungkan dengan Perusahaan --</option>
                                <?php foreach ($data_perusahaan as $perusahaan): ?>
                                    <option value="<?= htmlspecialchars($perusahaan['perusahaan']); ?>" <?= ($soal['perusahaan'] ?? '') == $perusahaan['perusahaan'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($perusahaan['perusahaan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-semibold">Kategori Faktor</label>
                            <div class="factor-group">
                                <div class="factor-item">
                                    <input type="radio" value="core" id="factor_core" name="factor" <?= ($soal['factor'] ?? '') == 'core' ? 'checked' : '' ?> required>
                                    <label class="factor-label" for="factor_core">
                                        <i class="fas fa-star me-2"></i> Core Factor
                                    </label>
                                </div>
                                <div class="factor-item">
                                    <input type="radio" value="secondary" id="factor_secondary" name="factor" <?= ($soal['factor'] ?? '') == 'secondary' ? 'checked' : '' ?> required>
                                    <label class="factor-label" for="factor_secondary">
                                        <i class="fas fa-layer-group me-2"></i> Secondary Factor
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Question -->
                        <div class="section-title">
                            <i class="fas fa-question-circle"></i> Isi Pertanyaan
                        </div>

                        <div class="mb-5">
                            <label for="soal" class="form-label fw-semibold">Teks Soal</label>
                            <textarea class="form-control" id="soal" name="soal" rows="3" placeholder="Masukkan pertanyaan kuesioner..." required><?= htmlspecialchars($soal['soal']); ?></textarea>
                        </div>

                        <!-- Section 3: Answers -->
                        <div class="section-title">
                            <i class="fas fa-list-ul"></i> Jawab & Nilai (1-4)
                        </div>

                        <?php 
                        $options = ['a', 'b', 'c', 'd'];
                        foreach ($options as $opt): 
                            $upper = strtoupper($opt);
                            $db_val = $soal['value_'.$opt] ?? 0;
                        ?>
                        <div class="row align-items-center mb-4 g-3">
                            <div class="col-md-7">
                                <div class="d-flex align-items-center">
                                    <span class="quiz-letter"><?= $upper ?></span>
                                    <input type="text" class="form-control" id="jawaban_<?= $opt ?>" name="jawaban_<?= $opt ?>" placeholder="Jawaban Opsi <?= $upper ?>..." value="<?= htmlspecialchars($soal['jawaban_'.$opt]); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="d-flex align-items-center justify-content-md-end gap-3">
                                    <span class="small fw-bold text-secondary">Nilai:</span>
                                    <div class="value-chips">
                                        <?php for ($v = 1; $v <= 4; $v++): ?>
                                        <div class="chip-item">
                                            <input type="radio" id="value_<?= $opt ?>_<?= $v ?>" name="value_<?= $opt ?>" value="<?= $v ?>" <?= $db_val == $v ? 'checked' : '' ?> required onclick="updateValues()">
                                            <label for="value_<?= $opt ?>_<?= $v ?>" class="chip-label"><?= $v ?></label>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                            <a href="data-soal.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                            <button type="submit" name="ubah_soal" class="btn btn-gradient-primary" id="submitBtn">
                                <i class="far fa-edit me-2"></i> Ubah Soal
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to check if there are any duplicate selected values or empty required fields
    function checkDuplicateValues() {
        const values = [];
        const options = ['a', 'b', 'c', 'd'];
        
        let allSelected = true;
        options.forEach(opt => {
            const checked = document.querySelector(`input[name="value_${opt}"]:checked`);
            if (checked) {
                values.push(checked.value);
            } else {
                allSelected = false;
            }
        });

        if (!allSelected) return true; // Disable if not all are selected

        // Check duplicates
        const uniqueValues = new Set(values);
        return uniqueValues.size !== values.length;
    }

    function checkEmptyFields() {
        const requiredFields = [
            'perusahaan', 'soal', 'jawaban_a', 'jawaban_b', 'jawaban_c', 'jawaban_d'
        ];

        return requiredFields.some(field => {
            const element = document.getElementById(field);
            return !element || !element.value.trim();
        });
    }

    function updateValues() {
        const isDuplicate = checkDuplicateValues();
        const isEmpty = checkEmptyFields();
        const submitButton = document.getElementById('submitBtn');
        
        submitButton.disabled = isDuplicate || isEmpty;
        
        // Optional warning for duplicates when all 4 are selected
        if (isDuplicate && document.querySelectorAll('input[type="radio"]:checked').length >= 4) {
            console.warn("Ada nilai ganda pada pilihan!");
        }
    }

    // Attach event listeners
    document.addEventListener('DOMContentLoaded', function () {
        // Evaluate initial state on load
        updateValues();

        // Add listeners to all relevant inputs to evaluate state on change
        const inputs = document.querySelectorAll('input[type="text"], textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', updateValues);
            input.addEventListener('change', updateValues);
        });

        const radios = document.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => {
            radio.addEventListener('change', updateValues);
        });

        // Add form submit block just in case
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            const isDuplicate = checkDuplicateValues();
            const isEmpty = checkEmptyFields();
            if (isDuplicate || isEmpty) {
                alert('Terdapat isian kosong atau nilai opsi jawaban yang ganda (harus 1,2,3,4).');
                event.preventDefault();
            }
        });
    });
</script>

<?php include 'layout/footer.php'; ?>