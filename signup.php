<?php
session_start();
include 'config/app.php';

// jika tombol tambah di tekan jalankan script
if (isset($_POST['tambah'])) {
    // Validasi email harus @gmail.com
    if (!preg_match("/@gmail\.com$/i", (string) ($_POST['email'] ?? ''))) {
        echo "<script>
        alert('Harap masukkan alamat email Gmail yang valid (contoh: example@gmail.com)');
        document.location.href = 'signup.php';
        </script>";
        exit();
    }

    $error_pendaftaran = validasi_pendaftaran_siswa($_POST['nis'] ?? '');
    if ($error_pendaftaran !== null) {
        echo '<script>alert(' . json_encode(
            $error_pendaftaran,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . '); document.location.href = "signup.php";</script>';
        exit();
    }

    if (create_akun($_POST) > 0) {
        echo "<script>
        alert('Pendaftaran Akun Berhasil');
        document.location.href = 'login.php';
      </script>";
    } else {
        echo "<script>
        alert('Pendaftaran Akun Gagal');
        document.location.href = 'signup.php';
      </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun - Sistem Informasi</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="img/smk.jpg">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 1rem;
        }

        .signup-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .signup-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .signup-header img {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .signup-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2b3452;
            margin-bottom: 0.5rem;
        }

        .signup-header p {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control {
            border-radius: 12px;
            padding: 1rem 0.75rem;
            border: 1px solid #e5e7eb;
            font-size: 0.95rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
            border-color: #4361ee;
        }

        .form-control[readonly] {
            background-color: #f9fafb;
            color: #6b7280;
        }

        .btn-signup {
            background-color: #4361ee;
            border: none;
            border-radius: 12px;
            padding: 0.8rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-signup:hover {
            background-color: #3f37c9;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(67, 97, 238, 0.3);
        }

        .error-alert {
            background-color: #fee2e2;
            color: #ef4444;
            border-radius: 10px;
            padding: 0.75rem;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .login-link a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #3f37c9;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #4361ee;
        }
    </style>
</head>

<body>

    <main class="signup-card">
        <div class="signup-header">
            <img src="img/smk.jpg" alt="Logo" width="80" height="80">
            <h1>Buat Akun Baru</h1>
            <p>Lengkapi data di bawah ini untuk mendaftar</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-floating">
                <input type="text" class="form-control" id="nis" name="nis" placeholder="NIS" pattern="[0-9]*" inputmode="numeric" required autofocus>
                <label for="nis"><i class="fas fa-id-card text-muted me-2"></i>NIS</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap" required>
                <label for="nama"><i class="fas fa-id-badge text-muted me-2"></i>Nama Lengkap</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                <label for="username"><i class="fas fa-user text-muted me-2"></i>Username</label>
            </div>

            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="Email"
                    pattern="[a-zA-Z0-9._%+-]+@gmail\.com$"
                    title="Harap masukkan alamat email Gmail (contoh: example@gmail.com)" required>
                <label for="email"><i class="fas fa-envelope text-muted me-2"></i>Email (Gmail)</label>
            </div>

            <div class="form-floating position-relative">
                <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="fas fa-lock text-muted me-2"></i>Password</label>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                </span>
            </div>

            <div class="form-floating">
                <input type="hidden" name="level" value="2">
                <input type="text" class="form-control" id="level-display" value="Siswa" readonly>
                <label for="level-display"><i class="fas fa-users-cog text-muted me-2"></i>Level Akses</label>
            </div>

            <button class="w-100 btn btn-primary btn-signup" type="submit" name="tambah">
                DAFTAR <i class="fas fa-user-plus ms-1"></i>
            </button>

            <div class="login-link">
                Punya akun? <a href="login.php">Masuk di sini</a>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>
