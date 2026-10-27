<?php

/**
 * Gửi email qua Gmail SMTP
 */

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once ROOT_PATH . '/libs/PHPMailer/Exception.php';
require_once ROOT_PATH . '/libs/PHPMailer/PHPMailer.php';
require_once ROOT_PATH . '/libs/PHPMailer/SMTP.php';

/**
 * Kiểm tra đã cấu hình SMTP chưa
 */
function isMailConfigured()
{
  return SMTP_USERNAME !== '' && SMTP_PASSWORD !== '';
}

/**
 * Gửi email HTML
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param string $error  Thông báo lỗi (nếu có) - truyền tham chiếu
 * @return bool
 */
function sendMail($toEmail, $toName, $subject, $htmlBody, &$error = null)
{
  if (!isMailConfigured()) {
    $error = 'Chưa cấu hình SMTP (SMTP_USERNAME / SMTP_PASSWORD trong config/config.php).';
    error_log('[WinK Mail] ' . $error);
    return false;
  }

  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_PORT == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($toEmail, $toName);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));

    $mail->send();
    return true;
  } catch (MailException $e) {
    $error = $mail->ErrorInfo;
    error_log('[WinK Mail] ' . $error);
    return false;
  }
}

/**
 * Template email mã OTP đặt lại mật khẩu
 * @param string $name
 * @param string $otp
 * @return string
 */
function buildOtpEmail($name, $otp)
{
  $name = e($name);
  $digits = implode('', array_map(function ($d) {
    return '<span style="display:inline-block;width:44px;height:52px;line-height:52px;margin:0 4px;border-radius:10px;background:#fff6f0;border:1px solid #ffd2b3;color:#ff5e00;font-size:26px;font-weight:800;">' . $d . '</span>';
  }, str_split($otp)));
  $minutes = OTP_EXPIRE_MINUTES;
  $store = e(STORE_NAME);
  $year = date('Y');

  return <<<HTML
    <!DOCTYPE html>
    <html lang="vi">
    <body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px;">
        <tr><td align="center">
          <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08);">
            <tr><td style="background:linear-gradient(135deg,#ff5e00,#ff8800);background-color:#ff5e00;padding:28px;text-align:center;color:#fff;">
              <div style="font-size:30px;font-weight:800;letter-spacing:-.5px;">WinK</div>
              <div style="font-size:14px;opacity:.9;margin-top:4px;">Đặt lại mật khẩu</div>
            </td></tr>
            <tr><td style="padding:32px 36px;color:#334155;font-size:15px;line-height:1.6;">
              <p style="margin:0 0 12px;">Xin chào <strong>{$name}</strong>,</p>
              <p style="margin:0 0 24px;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Hãy nhập mã xác thực dưới đây để tiếp tục:</p>
              <div style="text-align:center;margin:0 0 24px;">{$digits}</div>
              <p style="margin:0 0 8px;text-align:center;font-size:13px;color:#64748b;">Mã có hiệu lực trong <strong>{$minutes} phút</strong>.</p>
              <hr style="border:none;border-top:1px solid #e2e8f0;margin:28px 0 20px;">
              <p style="margin:0;font-size:13px;color:#64748b;">Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này — mật khẩu của bạn sẽ không thay đổi. Tuyệt đối không chia sẻ mã này cho bất kỳ ai.</p>
            </td></tr>
            <tr><td style="background:#f8fafc;padding:16px;text-align:center;font-size:12px;color:#94a3b8;">© {$year} {$store}</td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}
