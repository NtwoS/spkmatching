<?php
session_start();

//membatasi halaman sebelum login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('anda belum login');
            document.location.href = 'login.php';
        </script>";
    exit;
}

include 'layout/header.php';
$data_soal = select("SELECT * FROM soal ORDER BY id_soal DESC");

// Cek tombol ditekan
if (isset($_POST['kirim'])) {
    if (create_siswa($_POST) > 0) {
        // Simpan nama siswa ke session
        $_SESSION['siswa'] = $_POST['siswa'];
        $_SESSION['nis'] = $_POST['nis'];

        echo "<script>
                alert('Terima Kasih Telah Mengisi Kuesioner');
                document.location.href = 'logout.php';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Data Gagal Dikirim');
                document.location.href = 'data-siswa.php';
              </script>";
    }
}
?>

<?php
$total_soal = count($data_soal);
$soal_array = array_values($data_soal);
?>

<style>
    body { background: #f0f4f8; }

    .exam-wrapper {
        min-height: 100vh;
        padding: 24px 16px 60px;
    }

    /* Header bar */
    .exam-header {
        background: #fff;
        border-radius: 16px;
        padding: 16px 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .exam-header .exam-title { font-weight: 700; font-size: 1rem; color: #1e293b; margin: 0; }
    .exam-header .exam-meta  { font-size: 0.82rem; color: #64748b; }

    /* Progress bar */
    .exam-progress-wrap { background: #f1f5f9; border-radius: 99px; height: 8px; flex: 1; min-width: 120px; }
    .exam-progress-bar  { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #4361ee, #7b5ea7); transition: width .4s ease; }

    /* Question card */
    .question-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,.08);
        padding: 32px;
        margin-bottom: 24px;
        display: none;
        animation: fadeSlide .3s ease;
    }
    .question-card.active { display: block; }
    @keyframes fadeSlide {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .q-number {
        display: inline-flex; align-items: center; justify-content: center;
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, #4361ee, #7b5ea7);
        color: #fff; font-weight: 700; font-size: 0.85rem; margin-bottom: 16px;
    }
    .q-text { font-size: 1.05rem; font-weight: 600; color: #1e293b; line-height: 1.6; margin-bottom: 24px; }

    /* Radio card options */
    .option-label {
        display: flex; align-items: flex-start; gap: 14px;
        border: 2px solid #e2e8f0; border-radius: 12px;
        padding: 14px 18px; margin-bottom: 10px; cursor: pointer;
        transition: all .2s ease; background: #f8fafc;
    }
    .option-label:hover { border-color: #4361ee; background: #eff3ff; }
    .option-label input[type="radio"] { display: none; }
    .option-label.selected { border-color: #4361ee; background: #eff3ff; }
    .option-badge {
        flex-shrink: 0; width: 30px; height: 30px; border-radius: 8px;
        background: #e2e8f0; color: #64748b;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; transition: all .2s;
    }
    .option-label.selected .option-badge { background: #4361ee; color: #fff; }
    .option-text { font-size: 0.95rem; color: #334155; padding-top: 3px; }

    /* Navigation */
    .exam-nav {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,.08);
        padding: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    .q-dots {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
        width: 100%;
        padding-bottom: 15px;
        border-bottom: 1px dashed #e2e8f0;
    }
    .q-dot {
        width: 32px; height: 32px; border-radius: 8px; border: 2px solid #e2e8f0;
        background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        transition: all .2s;
    }
    .q-dot.answered  { background: #dbeafe; border-color: #60a5fa; color: #1d4ed8; }
    .q-dot.active    { background: linear-gradient(135deg, #4361ee, #7b5ea7); border-color: transparent; color: #fff; }
    .btn-exam-nav {
        padding: 10px 24px; border-radius: 10px; font-weight: 600; border: none; cursor: pointer; transition: all .2s;
    }
    .btn-prev { background: #f1f5f9; color: #475569; }
    .btn-prev:hover { background: #e2e8f0; }
    .btn-next { background: linear-gradient(135deg, #4361ee, #7b5ea7); color: #fff; }
    .btn-next:hover { opacity: .9; }
    .btn-submit {
        background: linear-gradient(135deg, #059669, #047857); color: #fff;
        padding: 12px 32px; border-radius: 12px; font-weight: 700; border: none;
        cursor: pointer; font-size: 1rem; box-shadow: 0 4px 14px rgba(5,150,105,.3);
        transition: all .2s; display: none;
    }
    .btn-submit:hover { opacity: .9; transform: translateY(-1px); }

    /* Siswa info card */
    .siswa-card {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
        border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; color: #fff;
    }
    .siswa-card .label { font-size: 0.78rem; opacity: .8; text-transform: uppercase; letter-spacing: .5px; }
    .siswa-card .value { font-weight: 700; font-size: 1rem; }
</style>

<div class="exam-wrapper">
    <div class="container-xl">

        <!-- Siswa Info -->
        <?php if ($_SESSION['level'] != 1): ?>
        <div class="siswa-card d-flex flex-column flex-md-row gap-4">
            <div>
                <div class="label">Nama Siswa</div>
                <div class="value"><?= htmlspecialchars($_SESSION['nama']); ?></div>
            </div>
            <div>
                <div class="label">NIS</div>
                <div class="value"><?= htmlspecialchars($akun['nis'] ?? '-'); ?></div>
            </div>
            <div class="ms-auto d-flex align-items-center">
                <span class="badge bg-white text-primary fw-bold px-3 py-2 rounded-pill">
                    <i class="fas fa-clipboard-list me-1"></i> <?= $total_soal; ?> Soal
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Exam Header: progress -->
        <div class="exam-header">
            <div>
                <p class="exam-title"><i class="fas fa-file-alt text-primary me-2"></i>Kuesioner Penilaian PKL</p>
                <p class="exam-meta mb-0">Pilih satu jawaban terbaik untuk setiap pertanyaan</p>
            </div>
            <div class="d-flex align-items-center gap-3" style="flex:1; min-width:200px;">
                <div class="exam-progress-wrap flex-grow-1">
                    <div class="exam-progress-bar" id="progressBar" style="width:0%"></div>
                </div>
                <span class="fw-semibold text-primary small" id="progressText">0 / <?= $total_soal; ?></span>
            </div>
        </div>

        <!-- Form -->
        <form action="" method="post" id="examForm">
            <?php if ($_SESSION['level'] != 1): ?>
                <input type="hidden" name="siswa" value="<?= htmlspecialchars($_SESSION['nama']); ?>">
                <input type="hidden" name="nis" value="<?= htmlspecialchars($akun['nis'] ?? ''); ?>">
            <?php endif; ?>

            <!-- Question Cards -->
            <?php foreach ($soal_array as $i => $soal):
                $no = $i + 1;
                $opts = [
                    'a' => ['label' => 'A', 'val' => $soal['value_a'], 'text' => $soal['jawaban_a']],
                    'b' => ['label' => 'B', 'val' => $soal['value_b'], 'text' => $soal['jawaban_b']],
                    'c' => ['label' => 'C', 'val' => $soal['value_c'], 'text' => $soal['jawaban_c']],
                    'd' => ['label' => 'D', 'val' => $soal['value_d'], 'text' => $soal['jawaban_d']],
                ];
            ?>
            <div class="question-card <?= $i === 0 ? 'active' : ''; ?>" id="qcard-<?= $i; ?>">
                <div class="q-number"><?= $no; ?></div>
                <div class="q-text"><?= htmlspecialchars($soal['soal']); ?></div>
                <?php foreach ($opts as $key => $opt): ?>
                <label class="option-label" onclick="selectOption(this, <?= $i; ?>)">
                    <input type="radio" name="soal_<?= $no; ?>" value="<?= htmlspecialchars($opt['val']); ?>" required>
                    <span class="option-badge"><?= $opt['label']; ?></span>
                    <span class="option-text"><?= htmlspecialchars($opt['text']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- Navigation bar -->
            <div class="exam-nav mt-4">
                <div class="q-dots" id="qDots">
                    <?php for ($i = 0; $i < $total_soal; $i++): ?>
                    <div class="q-dot <?= $i === 0 ? 'active' : ''; ?>" onclick="goToQuestion(<?= $i; ?>)"><?= $i + 1; ?></div>
                    <?php endfor; ?>
                </div>
                <div class="d-flex justify-content-center gap-3 w-100">
                    <button type="button" class="btn-exam-nav btn-prev" id="btnPrev" onclick="prevQ()" style="display:none;">
                        <i class="fas fa-chevron-left me-1"></i> Sebelumnya
                    </button>
                    <button type="button" class="btn-exam-nav btn-next" id="btnNext" onclick="nextQ()">
                        Berikutnya <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                    <?php if ($_SESSION['level'] != 1): ?>
                    <button type="submit" name="kirim" class="btn-submit" id="btnSubmit">
                        <i class="fas fa-paper-plane me-2"></i> Kirim Jawaban
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

    </div>
</div>

<script>
const totalQ = <?= $total_soal; ?>;
let currentQ = 0;
const answered = new Array(totalQ).fill(false);

function goToQuestion(idx) {
    document.getElementById('qcard-' + currentQ).classList.remove('active');
    currentQ = idx;
    document.getElementById('qcard-' + currentQ).classList.add('active');
    updateUI();
}

function nextQ() {
    if (currentQ < totalQ - 1) goToQuestion(currentQ + 1);
}
function prevQ() {
    if (currentQ > 0) goToQuestion(currentQ - 1);
}

function selectOption(label, qIdx) {
    // Unselect siblings
    label.closest('.question-card').querySelectorAll('.option-label').forEach(l => l.classList.remove('selected'));
    label.classList.add('selected');
    label.querySelector('input[type="radio"]').checked = true;
    answered[qIdx] = true;
    updateDots();
    updateProgress();
}

function updateUI() {
    // Nav buttons
    document.getElementById('btnPrev').style.display = currentQ === 0 ? 'none' : 'inline-flex';
    const isLast = currentQ === totalQ - 1;
    document.getElementById('btnNext').style.display = isLast ? 'none' : 'inline-flex';
    const btnSubmit = document.getElementById('btnSubmit');
    if (btnSubmit) btnSubmit.style.display = isLast ? 'inline-flex' : 'none';
    updateDots();
    updateProgress();
}

function updateDots() {
    document.querySelectorAll('.q-dot').forEach((dot, i) => {
        dot.classList.remove('active', 'answered');
        if (i === currentQ) dot.classList.add('active');
        else if (answered[i]) dot.classList.add('answered');
    });
}

function updateProgress() {
    const count = answered.filter(Boolean).length;
    const pct = Math.round((count / totalQ) * 100);
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressText').textContent = count + ' / ' + totalQ;
}

// Init
updateUI();

// Restore selected state if back-button
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const label = this.closest('.option-label');
        const card  = this.closest('.question-card');
        card.querySelectorAll('.option-label').forEach(l => l.classList.remove('selected'));
        label.classList.add('selected');
        const qIdx = parseInt(card.id.split('-')[1]);
        answered[qIdx] = true;
        updateDots();
        updateProgress();
    });
});
</script>

<?php
include 'layout/footer.php';
?>
