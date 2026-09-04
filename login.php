<?php

session_start();

include 'config/app.php';

//check apakah tombol ditekan
if (isset($_POST['login'])) {
    // Mulai session jika belum dimulai
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include atau koneksi ke database (pastikan $db sudah tersedia)
    // require 'koneksi.php'; // jika belum ada

    // Ambil input dan sanitasi
    $username = mysqli_real_escape_string($db, $_POST['username']);
    $password = $_POST['password'];

    // Query untuk cek user berdasarkan username
    $query = "SELECT * FROM akun WHERE username = '$username'";
    $result = mysqli_query($db, $query);

    if (!$result) {
        die('Query error: ' . mysqli_error($db));
    }

    // Cek apakah user ditemukan
    if (mysqli_num_rows($result) === 1) {
        $akun = mysqli_fetch_assoc($result);

        // Verifikasi password
        if (password_verify($password, $akun['password'])) {
            // Set session
            $_SESSION['login'] = true;
            $_SESSION['id_akun'] = $akun['id_akun'];
            $_SESSION['nama'] = $akun['nama'];
            $_SESSION['username'] = $akun['username'];
            $_SESSION['level'] = $akun['level'];

            // Redirect ke halaman setelah login
            if ($akun['level'] == 1) { // Admin
                header("Location: home.php");
            } else { // Student (assuming level 2 or other)
                header("Location: input-data.php");
            }
            exit;
        }
    }

    // Jika username tidak ditemukan atau password salah
    $error = true;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistem Informasi</title>
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
        }

        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2b3452;
            margin-bottom: 0.5rem;
        }

        .login-header p {
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

        .btn-login {
            background-color: #4361ee;
            border: none;
            border-radius: 12px;
            padding: 0.8rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
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

        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .signup-link a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .signup-link a:hover {
            color: #3f37c9;
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: #4b5563;
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

    <main class="login-card">
        <div class="login-header">
            <img src="img/smk.jpg" alt="Logo" width="80" height="80">
            <h1>Selamat Datang</h1>
            <p>Silakan masuk ke akun Anda</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle me-1"></i> Username atau Password salah!
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-floating"> 
                <input type="text" name="username" class="form-control" id="floatingInput" placeholder="Username" required autofocus>
                <label for="floatingInput"><i class="fas fa-user text-muted me-2"></i>Username</label>
            </div>

            <div class="form-floating position-relative"> 
                <input type="password" name="password" class="form-control pe-5" id="floatingPassword" placeholder="Password" required>
                <label for="floatingPassword"><i class="fas fa-lock text-muted me-2"></i>Password</label>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                </span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check"> 
                    <input class="form-check-input" type="checkbox" value="remember-me" id="checkDefault"> 
                    <label class="form-check-label" for="checkDefault">Ingat saya</label>
                </div>
            </div>

            <button class="btn btn-primary w-100 btn-login" type="submit" name="login">
                MASUK <i class="fas fa-sign-in-alt ms-1"></i>
            </button>
            
            <div class="signup-link">
                Belum punya akun? <a href="signup.php">Daftar sekarang</a>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('floatingPassword');
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