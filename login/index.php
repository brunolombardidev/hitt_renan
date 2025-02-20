<?php
    session_start();
    if(isset($_SESSION['login_user'])){
        header("location: ../");
        exit;
    }

    // Carregar configurações de customização
    $customizacao = json_decode(file_get_contents(__DIR__ . '/../customizacao.json'), true);

    // Verificar se a logo URL está definida e acessível
    $logo_url = isset($customizacao['login_logo_url']) ? $customizacao['login_logo_url'] : 'https://i.postimg.cc/prpT2HPt/agendapro.png';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Agenda PRO</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../assets/js/mobile-detection.js"></script>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, <?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#0042DA'; ?>, <?php echo isset($customizacao['navbar_color']) ? $customizacao['navbar_color'] : '#007BFF'; ?>);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        #blur-overlay {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .row {
            width: 100%;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-form {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease-out;
            margin: 0 auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-form img {
            max-width: 200px;
            height: auto;
            margin: 0 auto 2rem;
            display: block;
            transition: transform 0.3s ease;
        }

        .login-form img:hover {
            transform: scale(1.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            height: 50px;
            padding: 0.75rem 1.2rem;
            border-radius: 10px;
            border: 2px solid #e1e1e1;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: <?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#0042DA'; ?>;
            box-shadow: 0 0 0 0.2rem rgba(0, 66, 218, 0.1);
        }

        .btn {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: <?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#0042DA'; ?>;
            border: none;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: <?php echo isset($customizacao['navbar_color']) ? $customizacao['navbar_color'] : '#007BFF'; ?>;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        label {
            font-weight: 500;
            color: #555;
            margin-bottom: 0.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: transparent;
            border: none;
            padding-right: 0;
        }

        .input-group .form-control {
            padding-left: 45px;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            z-index: 10;
        }

        @media (max-width: 576px) {
            .login-form {
                margin: 1rem auto;
                padding: 1.5rem;
                width: calc(100% - 2rem);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="blur-overlay">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="login-form">
                        <?php if (@file_get_contents($logo_url) !== false): ?>
                            <img src="<?php echo htmlentities($logo_url); ?>" alt="Logo" class="logo">
                        <?php else: ?>
                            <p class="text-danger text-center">Logo não pôde ser carregada. Verifique a URL.</p>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="login.php" method="POST">
                            <div class="form-group">
                                <label for="username">Usuário</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="username" name="username" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="password">Senha</label>
                                <div class="input-group">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <button type="submit" name="submit" class="btn btn-primary">
                                ENTRAR <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
