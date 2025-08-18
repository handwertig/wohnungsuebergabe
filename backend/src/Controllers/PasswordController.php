<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Database;
use App\Flash;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;

final class PasswordController
{
    public function forgotForm(): void {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Passwort vergessen</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
        echo '<body class="bg-light"><div class="container py-5" style="max-width:480px">';
        echo '<h1 class="h4 mb-3 text-center">Passwort vergessen</h1>';
        echo '<form method="post" action="/password/forgot">';
        echo '<div class="mb-3"><label class="form-label">E-Mail</label><input required name="email" type="email" class="form-control"></div>';
        echo '<button class="btn btn-primary w-100">Link senden</button>';
        echo '</form></div></body></html>';
    }

    public function forgot(): void {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') { header('Location: /password/forgot'); return; }

        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT id,email FROM users WHERE email=?'); $st->execute([$email]);
        if (!$user = $st->fetch(PDO::FETCH_ASSOC)) {
            Flash::add('info','Falls die E-Mail existiert, wurde ein Link gesendet.');
            header('Location: /login'); return;
        }

        $token = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', time()+3600);
        $ins = $pdo->prepare('INSERT INTO password_resets (id,user_id,token,expires_at,created_at) VALUES (UUID(),?,?,?,NOW())');
        $ins->execute([$user['id'],$token,$exp]);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'mailpit';
        $mail->Port = (int)(getenv('SMTP_PORT') ?: 1025);
        $mail->SMTPAuth = false;
        $mail->setFrom(getenv('SMTP_FROM') ?: 'app@example.com', 'Wohnungsübergabe');
        $mail->addAddress($email);
        $mail->Subject = 'Passwort zurücksetzen';
        $base = (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : 'http://localhost:8080');
        $link = $base.'/password/reset?token='.$token;
        $mail->Body = "Hallo,\n\nzum Zurücksetzen deines Passworts klicke bitte:\n$link\n\nDer Link ist 60 Minuten gültig.";
        $mail->send();

        Flash::add('success','E-Mail mit Reset-Link gesendet (Mailpit).');
        header('Location: /login');
    }

    public function resetForm(): void {
        $token = $_GET['token'] ?? '';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Passwort zurücksetzen</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
        echo '<body class="bg-light"><div class="container py-5" style="max-width:480px">';
        echo '<h1 class="h4 mb-3 text-center">Neues Passwort setzen</h1>';
        echo '<form method="post" action="/password/reset">';
        echo '<input type="hidden" name="token" value="'.htmlspecialchars($token).'">';
        echo '<div class="mb-3"><label class="form-label">Neues Passwort</label><input required name="password" type="password" class="form-control"></div>';
        echo '<button class="btn btn-primary w-100">Speichern</button>';
        echo '</form></div></body></html>';
    }

    public function reset(): void {
        $token = $_POST['token'] ?? '';
        $pass  = (string)($_POST['password'] ?? '');
        if ($token === '' || $pass === '') { header('Location: /password/reset?token='.urlencode($token)); return; }

        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT pr.id, pr.user_id FROM password_resets pr WHERE pr.token=? AND pr.expires_at > NOW() AND pr.used_at IS NULL');
        $st->execute([$token]);
        if (!$row = $st->fetch(PDO::FETCH_ASSOC)) {
            Flash::add('error','Token ungültig oder abgelaufen.');
            header('Location: /login'); return;
        }

        $upd = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $upd->execute([password_hash($pass, PASSWORD_BCRYPT), $row['user_id']]);

        $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$row['id']]);

        Flash::add('success','Passwort aktualisiert. Bitte einloggen.');
        header('Location: /login');
    }
}
